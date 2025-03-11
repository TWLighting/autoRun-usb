<?php

namespace App\Http\Controllers\PublicApi;

use Illuminate\Http\Request;
use DB;
use Log;
use Validator;

class AddMsgController extends GlobalController
{

    public function addMsg(Request $request)
    {
        $this->validate($request, [
            'data' => 'required',
        ]);

        $data = $request->input('data');
        $insert_data = [
            'title' => $data['title'],
            'msg' => $data['msg'],
        ];

        $insert = DB::table('news_msg')->insert($insert_data);
        if (!$insert) {
            return $this->presenter->json([], '新增失敗', 2);
        }

        return $this->presenter->json([], '新增成功', 1);
    }
}
