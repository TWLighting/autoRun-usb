<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use DB;
use Log;

class ClearDatas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '清除DB 旧资料';

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
     * @return mixed
     */
    public function handle()
    {
        $halfYear = Carbon::now()->subMonth(6);
        $halfMonth = Carbon::now()->subDays(15);
        $aWeek = Carbon::now()->subDays(7);

        // 清除过期验证码
        $result = DB::table('captcha_code')
            ->where('created_at', '<', $halfMonth)
            ->delete();

        $this->doLog('过期验证码清除数量:%s', $result);

        // 清除 usb_job
        $result = DB::table('usb_job')
            ->where('created_at', '<', $aWeek)
            ->delete();

        $this->doLog('usb_job清除数量:%s', $result);

        // TODO: 清除 device_log by $halfYear

        // TODO: 清除 login_log by $halfYear
    }

    private function doLog($text, $count)
    {
        $msg = sprintf($text, $count);
        Log::channel('cronjob')->info($msg);
        $this->info($msg);
    }
}