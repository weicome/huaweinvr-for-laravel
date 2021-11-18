<?php

namespace Wei\HuaweiNvr\Servers;

use Wei\HuaweiNvr\Client\NVRClient;
use Wei\HuaweiNvr\NVRInterface;
use Illuminate\Support\Facades\Log;

class NVRServer
{
    private $client;
    private $running;
    private $channel;
    private $services;

    public function __construct()
    {
        $this->client = new NVRClient();
        $this->initialize();
    }

    /**
     * 初始化
     */
    protected function initialize()
    {
        $config = config('huaweinvr');
        $this->client->setUrl($config['url']);
        $this->client->setUsername($config['username']);
        $this->client->setPassword($config['password']);
        $this->services = $config['service'];
    }

    /**
     * 启动监听
     */
    public function listen()
    {
        while (true) {
            try {
                return $this->run();
            } catch (\Throwable $th) {
                sleep(10);
                //  throw $th;
            }
        }
    }

    public function run($session_timeout = 5)
    {
        $sigHandle = function () {
            $this->running = false;
        };
        //注册信号监听器
        pcntl_signal(SIGINT, $sigHandle);
        pcntl_signal(SIGHUP, $sigHandle);
        try {
            dump('开始登录');
            $this->running = true;
            while ($this->running) {
                Log::info('登录NVR:', [$this->client->url()]);
                $this->client->login();
                Log::info('设置心跳', [$this->client->url()]);
                $this->client->heartbeat();
                Log::info('登录会话时间', [$session_timeout, $this->client->url()]);
                $this->client->setSystemConfig($session_timeout);

                $channelInfo = $this->client->getChannelInfo();
                $this->channel = $this->channelFormat($channelInfo);
                log::info('通道信息:', $channelInfo);
                $reader_id = null;
                $sequence = null;
                $lap_number = null;
                $pictureTime = now();
                $endTime = now()->addMinutes($session_timeout - 1);
                while (now()->lt($endTime) && $this->running) {
                    $start = microtime(true);
                    dump('开始监听检测事件');
                    Log::info('开始监听检测事件', [$this->client->url()]);
                    $data = $this->client->eventCheck($reader_id, $sequence, $lap_number)['data'];
                    Log::info('事件检查结束', [$this->client->url(), (microtime(true) - $start)]);
                    $reader_id = $data['reader_id'];
                    $sequence = $data['sequence'];
                    $lap_number = $data['lap_number'];
                    foreach ($this->services as $event) {
                        try {
                            dispatch(function () use ($event, $data) {
                                dump('分发事件'.$event);
                                if(app($event) instanceof NVRInterface) {
                                    app($event)->handler(['event' => $data], $this->channel);
                                }else{
                                    Log::info($event.': 未实现接口');
                                }
                            });
                        } catch (\Throwable $th) {
                            Log::info('分发事件失败',[$event,$th->getMessage()]);
                        }
                    }
                    //发送心跳包
                    dispatch(function () {
                        try {
                            if ($this->client->logined()) {
                                Log::info('发送心跳包', [$this->client->url()]);
                                $this->client->heartbeat();
                            }
                        } catch (\Throwable $th) {
                            Log::error('发送心跳包错误', [$th->getCode(), $th->getMessage(), $this->client->url()]);
                        }
                    });

                    //获取图片任务
                    if ($pictureTime->lt(now())) {
                        $pictureTime->addSeconds(10);
                        dispatch(function () {
                            Log::info('执行告警图片获取任务', [$this->client->url()]);
                            $data = $this->getPlaybackPicture();
                            foreach ($this->services as $event) {
                                try {
                                    dump('分发图片'.$event);
                                    if(app($event) instanceof NVRInterface) {
                                        app($event)->handler(['picture' => $data], $this->channel,$this);
                                    }else{
                                        Log::info($event.': 未实现接口');
                                    }
                                } catch (\Throwable $th) {
                                    Log::info('分发事件失败',[$event,$th->getMessage()]);
                                }
                            }
                        });
                    }
                }
                Log::info('正常登出系统', [$this->client->url()]);
                $this->client->logout();
            }
        } catch (\Throwable $th) {
            Log::error('NVR错误', [$th->getCode(), $th->getMessage(), $this->client->url()]);
            if ($this->client->logined()) {
                Log::info('异常,登出系统!', [$this->client->url()]);
                $this->client->logout();
            }
            throw $th;
        }
        return 0;
    }

    // 通道信息格式化
    private function channelFormat($channel)
    {
        $array = [];
        try {
            $channel = $channel['data'];
            foreach ($channel['channel_param']['items'] as $k => $v) {
                $array[$v['channel']] = $v['channel_name'];
            }
        } catch (\Throwable $th) {
            Log::info('通道格式化信息错误', [$th->getCode(), $th->getMessage(), $this->client->url()]);
        } finally {
            return $array;
        }
    }

    /**
     * 查询一天内的图片信息
     */
    public function getPlaybackPicture($pic_info = null)
    {
        try {
            $data = $this->client->getPlaybackPicture($pic_info, array_keys($this->channel));
        } catch (\Throwable $th) {
            Log::info('获取图片信息错误', [$th->getCode(), $th->getMessage(), $this->client->url()]);
            return null;
        }
        return $data;
    }
}
