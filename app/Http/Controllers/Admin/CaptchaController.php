<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Carbon\Carbon;
use DB;

class CaptchaController extends AdminController
{
    public function getList(Request $request)
    {
        $data = DB::table('captcha_code')
            ->select(['id', 'card_no', 'captcha_base64', 'created_at'])
            ->orderBy('id', 'asc')
            ->whereRaw('created_at >= NOW() - INTERVAL 2 MINUTE')
            ->whereNull('code')
            ->get();

        return $this->presenter->json($data, "成功", 1);
    }

    public function updateCode(Request $request)
    {
        $this->validate($request, [
            'id' => 'required',
            'code' => 'required'
        ]);

        DB::table('captcha_code')
            ->where('id', $request->input('id'))
            ->whereNull('code')
            ->update([
                'code' => $request->input('code'),
                'account' => $request->session()->get('account')
            ]);

        return $this->presenter->json([], "成功", 1);
    }

}
