<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use DB;

class AutorunController extends Controller
{

    public function heartbeat(Request $request)
    {
        $this->validate($request, [
            'dev_id' => 'required',
        ]);

        $result = DB::table('autorun_device')
            ->where('dev_id', $request->input('dev_id'))
            ->update(['heartbeat_time' => Carbon::now()]);

        if (!$result) {
            return $this->presenter->json([], "失败", 2);
        }
        return $this->presenter->json([], "成功", 1);
    }
}
