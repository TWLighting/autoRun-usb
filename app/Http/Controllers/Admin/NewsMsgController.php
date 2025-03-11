<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Libraries\Functions;
use DB;


class NewsMsgController extends AdminController
{
    public function getList(Request $request)
    {
        $data = DB::table('news_msg')
            ->orderBy('id', 'asc')
            ->whereNull('deleted_at');
        if ($request->filled('title')) {
            $data->where('title', 'like', '%' . $request->input('title') . '%');
        }
        
        $data = $data->paginate($request->input('perpage', 20));
        
        return $this->presenter->json($data, "成功", 1);
    }
    
    public function edit(Request $request)
    {
        $this->validate($request, [
            'id' => 'required',
            'title' => 'required',
            'msg' => 'required',
            'created_at' => 'required',
            'alert_type' => 'required'
        ]);
        
        if (!strtotime($request->input('created_at'))) {
            return $this->presenter->json('', "时间不合法", 2);
        }
        
        $update_data = [
            'title' => $request->input('title'),
            'msg' => $request->input('msg'),
            'created_at' => $request->input('created_at'),
            'last_edit_account' => $request->session()->get('account'),
            'alert_type' => $request->input('alert_type'),
        ];
        
        $update = DB::table('news_msg')
            ->where('id', $request->input('id'))
            ->update($update_data);
        if (!$update) {
            return $this->presenter->json('', "编辑失败", 2);
        }
        
        return $this->presenter->json('', "编辑成功", 1);
        
    }
    
    public function insert(Request $request)
    {
        $this->validate($request, [
            'title' => 'required',
            'msg' => 'required',
            'created_at' => 'required',
            'alert_type' => 'required'
        ]);
        
        if (!strtotime($request->input('created_at'))) {
            return $this->presenter->json('', "时间不合法", 2);
        }
        
        $insert_data = [
            'create_account' => $request->session()->get('account'),
            'title' => $request->input('title'),
            'msg' => $request->input('msg'),
            'created_at' => $request->input('created_at'),
            'alert_type' => $request->input('alert_type'),
        ];
        
        $insert = DB::table('news_msg')->insert($insert_data);
        
        if (!$insert) {
            return $this->presenter->json('', "新增失败", 2);
        }
        
        return $this->presenter->json('', "新增成功", 1);
    }

    public function delete(Request $request)
    {
        $this->validate($request, [
            'id' => 'required',
        ]);

        $delete_data = [
            'deleted_account' => $request->session()->get('account'),
            'deleted_at' => date("Y-m-d H:i:s"),
        ];

        $update = DB::table('news_msg')
            ->where('id', $request->input('id'))
            ->update($delete_data);
        if (!$update) {
            return $this->presenter->json('', "刪除失败", 2);
        }

        return $this->presenter->json('', "刪除成功", 1);

    }
}
