<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use DB;

class AutorunJob extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'autorunDaily {--date=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '统计autorun资料';

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
        $date = $this->option('date');
        if ($date) {
            if (strtotime($date)) {
                $date = Carbon::parse($date);
            }
            else {
                $this->error('时间不合法');
                return;
            }
        } else {
            $date = Carbon::yesterday();
        }
        $date = $date->toDateString();

        $select = DB::table('autorun_job')
            ->selectRaw('account_id,
    SUM(IF(status = 1, amount, 0)) AS suc_amount,
    SUM(IF(status = 2, amount, 0)) AS fail_amount,
    SUM(IF(status = 1, 1, 0)) As suc_num,
    SUM(IF(status = 2, 1, 0)) AS fail_num,
    ? AS job_date', [$date])
            ->whereDate('success_at', $date)
            ->groupBy('account_id');

        $bindings = $select->getBindings();

        $insertQuery = 'INSERT INTO autorun_job_schedule(account_id, suc_amount, fail_amount, suc_num, fail_num, job_date) ' . $select->toSql();
        DB::insert($insertQuery, $bindings);
        $this->info($date . '完成');
    }
}