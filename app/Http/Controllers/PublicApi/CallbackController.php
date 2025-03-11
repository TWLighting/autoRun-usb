<?php

namespace App\Http\Controllers\PublicApi;

use App\Libraries\CurlRequest;
use App\Libraries\Functions;
use App\Libraries\Cryptology;
use Illuminate\Http\Request;
use Carbon\Carbon;
use DB;
use Log;

class CallbackController extends GlobalController
{
    public function request(Request $request)
    {
        $this->validate($request, [
            'account_id' => 'required',
            'transaction_info_id' => 'required',
            'sign' => 'required',
        ]);
        $data = $request->input();
        Log::channel('callback')->info('[callback-接收] :' . json_encode($data, 320));

        if (!Functions::checkSign($data, config('admin.callback_key'))) {
            return $this->presenter->json('', '验签失败', 2);
        }
        $transactionInfo = DB::table('transaction_info')
            ->select('card_no', 'bank_name', 'trans_time', 'amt', 'balance', 'tran_info', 'note')
            ->where('id', $data['transaction_info_id'])
            ->first();

        if (!$transactionInfo) {
            return $this->presenter->json([], "失败", 2);
        }
        $transactionInfo = (array) $transactionInfo;
        $account = DB::table('account as a')
            ->select(['callback_url', 'md5_key', 'des_key'])
            ->leftJoin('bank_card_info as bi', 'bi.account_id', '=', 'a.id')
            ->where('bi.card_no', $transactionInfo['card_no'])
            ->where('a.id', $data['account_id'])
            ->first();

        if (!$account || empty($account->callback_url)) {
            return $this->presenter->json([], "失败!", 2);
        }
        $datas = json_encode($transactionInfo);
        $rus = openssl_encrypt($datas, 'des-ede3', $account->des_key, 0);

        $sign = Cryptology::md5sign($account->md5_key, $transactionInfo);
        $result = $this->callbackDownstream($account->callback_url, ['data' => $rus, 'sign' => $sign]);
        if ($result) {
            return $this->presenter->json([], "成功", 1);
        }

        return $this->presenter->json([], "失败", 2);
    }

    private function callbackDownstream($url, $data)
    {
        set_time_limit(300);
        $data = json_encode($data, 320);
        $header = ['Content-Type: application/json'];
        $times = [0, 30, 180];
        foreach ($times as $time) {
            sleep($time);
            $result = CurlRequest::curl($url, $data, $header, 'off', 5);
            Log::channel('callback')->info('[callback-' . $time . '] 接收资料: ' . $result);
            if ($result == 'success') {
                return true;
            }
        }
        return false;
    }

}
