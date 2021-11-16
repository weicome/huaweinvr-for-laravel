<?php

namespace Wei\HuaweiNvr\Command;

use Wei\HuaweiNvr\Server\NVRService;
use Illuminate\Console\Command;

class NVRListen extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nvr:listen';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '启动监听华为nvr服务';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            $this->info("启动NVR监听...");
            app(NVRService::class)->listen();
        } catch (\Throwable $th) {
            //throw $th;
            $this->error('错误信息:' . $th->getMessage());
        }
        return 0;
    }
}
