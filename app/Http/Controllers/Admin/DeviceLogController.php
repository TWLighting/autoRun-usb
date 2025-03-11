<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Carbon\Carbon;
use DB;

class DeviceLogController extends AdminController
{
    public function getList(Request $request)
    {
        $data = DB::table('device_log')
            ->orderBy('created_at', 'desc');

        if($request->filled('account')){
            $data->where('account', $request->input('account'));
        }
        if ($request->filled('hashcode')) {
            $data->where('hashcode', 'like', '%' . $request->input('hashcode') . '%');
        }

        if($request->filled('type')){
            $data->where('type', $request->input('type'));
        }

        if($request->filled('startDate')){
            $data->where('created_at', '>=', $request->input('startDate'));
        }
        if($request->filled('endDate')){
            $data->where('created_at', '<=', $request->input('endDate'));
        }

        $result = $data->paginate($request->input('perpage', 20));
        return $this->presenter->json($result, "成功", 1);
    }
}
