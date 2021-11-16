<?php

namespace Verus\HuaweiNvr\Client;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

class NVRClient
{
    private $url = null, $username = null, $password = null;

    private $cookie = null, $token = null;

    /**
     * 设置url
     */
    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * 获取url
     */
    public function url()
    {
        return $this->url;
    }

    /**
     * 设置用户名
     */
    public function setUsername($username)
    {
        $this->username = $username;
        return $this;
    }

    /**
     * 获取用户名
     */
    public function username()
    {
        return $this->username;
    }

    /**
     * 设置密码
     */
    public function setPassword($password)
    {
        $this->password = $password;
        return $this;
    }

    /**
     * 获取密码
     */
    public function password()
    {
        return $this->password;
    }

    /**
     * 发送POST
     */
    protected function post($path, $data = [])
    {
        $resp = Http::withoutVerifying()
            ->acceptJson()
            ->asJson()
            ->withHeaders($this->header())
            ->post($this->url . $path, $data);

        if (!$resp->ok()) {
            throw new \Exception($resp->body() ?? '请求错误', $resp->getStatusCode());
        }

        return $resp->json();
    }

    /**
     * 设置公钥
     */
    public function setPubKey(): array
    {
        $resp = Http::withoutVerifying()
            ->acceptJson()
            ->asJson()
            ->withHeaders($this->header())
            ->post($this->url . '/API/Login/TransKey/Get', [
                'type' => 'PubKey'
            ]);

        if (!$resp->ok()) {
            throw new \Exception($resp->body() ?? '请求错误', $resp->getStatusCode());
        }
        return $resp['data'];
    }

    /**
     * 获取登录前设备
     */
    public function befrom()
    {
        $resp = Http::withoutVerifying()->acceptJson()->asJson()->post($this->url . '/API/Login/Range');
        if ($resp['data']['first_login_flag']) {
            $enc = $this->setPubKey()['key_lists'][0];
            $res = Http::withoutVerifying()
                ->acceptJson()
                ->asJson()
                ->post($this->url . '/API/FirstLogin/Password/Set', [
                    'enc_password' => ['cipner' => $enc['key'], 'seq' => $enc['seq']]
                ]);
            dump('设置密码响应字段:' . $res);
            if ($res->json()['result'] == 'success') {
                return true;
            } else {
                throw new \Exception(json_encode($res['reason']));
            }
        } else {
            return true;
        }
    }

    /**
     * 用户登陆
     */
    public function login()
    {
        if (null === $this->url || null === $this->username || null === $this->password) {
            throw new \Exception('url,usrname,passowrd不能为空', 400);
        }
        if (null === $this->token) {
            $resp = Http::withoutVerifying()
                ->acceptJson()
                ->asJson()
                ->post($this->url . '/API/Web/Login');
            if ($resp->getStatusCode() == 401) {
                //计算token，继续登录
                $this->token = $this->generalToken($resp->header('WWW-Authenticate'), $this->username, $this->password, '/API/Web/Login');
                return $this->login();
            }

            throw new \Exception($resp->body() ?? '登陆错误', $resp->getStatusCode());
        }

        $resp = Http::withoutVerifying()
            ->acceptJson()
            ->asJson()
            ->withToken($this->token, 'Digest')
            ->post($this->url . '/API/Web/Login');
        if (!$resp->ok()) {
            $this->token = null;
            throw new \Exception($resp->body() ?? '登录错误', $resp->getStatusCode());
        }

        $this->token = $resp->header('X-csrftoken');
        $this->cookie = $resp->header('Set-cookie');
        return true;
    }

    /**
     * 登出
     */
    public function logout()
    {
        if (!$this->logined()) {
            throw new \Exception('未登录', 400);
        }

        $this->post('/API/Web/Logout');
        $this->token = null;
        $this->cookie = null;
        return true;
    }

    /**
     * 用户保活
     */
    public function heartbeat()
    {
        return $this->post('/API/Login/Heartbeat', [
            'data' => ['keep_alive' => true]
        ]);
    }

    /**
     * 设置系统参数
     */
    public function setSystemConfig($session_timeout)
    {
        return $this->post('/API/SystemConfig/General/Set', [
            'data' => ['session_timeout' => $session_timeout, 'ai_switch' => true]
        ]);
    }

    /**
     *获取通道信息
     */
    public function getChannelInfo()
    {
        return $this->post('/API/Login/ChannelInfo/Get');
    }

    /**
     * 获取服务器事件推送消息
     */
    public function eventCheck($reader_id = null, $sequence = null, $lap_number = null)
    {
        $data = [
            'subscribe_ai_metadata' => true,
            'subscribe_intelligence' => true,
            'need_background_img' => true,
        ];

        $reader_id && $data['reader_id'] = $reader_id;
        $sequence && $data['sequence'] = $sequence;
        $lap_number && $data['lap_number'] = $lap_number;

        return $this->post('/API/Event/Check', ['data' => $data]);
    }

    /**
     * 获取每天图片
     * @param null $pic_info
     * @param array|null $channels
     * @return array|mixed
     * @throws \Exception
     */
    public function getPlaybackPicture($pic_info = null, array $channels = null)
    {
        if (null === $pic_info) {
            $data = [
                'channel' => $channels,
                'start_date' => today()->format('m/d/Y'),
                'end_date' => today()->format('m/d/Y'),
                'start_time' => today()->format('H:i:s'),
                'end_time' => now()->format('H:i:s'),
                'pic_sort' => 0,
            ];
        } else {
            $data['pic_info'] = $pic_info;
        }
        return $this->post('/API/Playback/Picture/Get', ['data' => $data]);
    }

    /**
     * 计算Digest
     */
    protected function generalToken($wwwAuthenticate, $username, $password, $uri): string
    {
        $str = substr($wwwAuthenticate, 7);
        $data = explode(',', $str);
        $value = [];
        foreach ($data as $one) {
            [$key, $val] = explode('=', $one);
            $value[$key] = str_replace('"', '', $val);
        }

        $nc = sprintf('%08x', 1);
        $cnonce = Str::random(8);

        $A1 = "{$username}:{$value['realm']}:{$password}";
        $A2 = "POST:{$uri}";
        $A3 = "{$value['nonce']}:{$nc}:{$cnonce}:auth";

        $response = hash('sha256', hash('sha256', $A1) . ":{$A3}:" . hash('sha256', $A2));

        $token = "username=\"{$username}\",realm=\"{$value['realm']}\",nonce=\"{$value['nonce']}\",uri=\"{$uri}\",response=\"{$response}\",qop=\"{$value['qop']}\",nc={$nc},cnonce=\"{$cnonce}\",algorithm=\"SHA-256\"";
        return $token;
    }

    /**
     * 请求Header
     */
    protected function header()
    {
        return [
            'X-csrftoken' => $this->token,
            'Cookie' => $this->cookie,
        ];
    }

    /**
     * 是否登陆
     */
    public function logined()
    {
        return $this->token !== null && $this->cookie !== null;
    }

    private function __clone()
    {
        // TODO: Implement __clone() method.
    }
}
