<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use DB;
use App\Libraries\Functions;

class ShareListController extends AdminController
{
    public function getList(Request $request)
    {
        $data = DB::table('share_list')
            ->select([
                '*'
            ])
            ->orderBy('id', 'DESC');
        if (!Functions::isAdmin($request)) {
            $data->where('top_account_id', $request->session()->get('topAccountId'));
        }
        if ($request->filled('status')) {
            $data->where('status', $request->input('status'));
        }
        if ($request->filled('ip')) {
            $data->where('ip', $request->input('ip'));
        }
        if($request->filled('startDate')){
            $data->where('created_at', '>=', $request->input('startDate'));
        }
        if($request->filled('endDate')){
            $data->where('created_at', '<=', $request->input('endDate'));
        }

        $data = $data->paginate($request->input('perpage', 20));

        return $this->presenter->json($data, "成功", 1);
    }

    public function insert(Request $request)
    {
        $this->validate($request, [
            'ip' => 'required',
            'msg' => 'required'
        ]);

        $data = $request->only(['ip', 'msg']);
        $data['account_id'] = $request->session()->get('accountId');
        $data['top_account_id'] = $request->session()->get('topAccountId');

        $id = DB::table('share_list')->insertGetId($data);

        return $this->presenter->json(['id' => $id], "成功", 1);
    }


}
