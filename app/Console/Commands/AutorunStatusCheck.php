<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use DB;
use LOG;

class AutorunStatusCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'autorunStatusCheck {--dev_id=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'autorun检查';

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
        $select = DB::table('autorun_device')
            ->select(['dev_id', 'heartbeat_time']);
        $dev_id = $this->option('dev_id');
        if ($dev_id) {
           $select->where('dev_id', $dev_id);
        }

        $autorun_device = $select
            ->where('enable', 1)
            ->where(function($query)
            {
                $query->whereNull('heartbeat_time')
                    ->orWhereRaw('heartbeat_time < NOW() - INTERVAL 1 MINUTE');
            })
            ->get();

        $msg = "";
        $lineBreak = "\n";
        foreach ($autorun_device as $data) {
            if ($msg) {
                $msg .= $lineBreak;
            } else {
                $msg .= '[autorun异常回报] 离线:' . $lineBreak;
            }
            $msg .= sprintf('%s, 最后回报:%s', $data->dev_id, ($data->heartbeat_time) ?? '无');
        }

        if ($msg) {
            // $this->info($msg);
            Log::channel('csharp_slack')->critical($msg);
        }
    }
}