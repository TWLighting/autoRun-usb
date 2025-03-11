<?php
namespace App\Libraries;

use DB;

class Verify
{
    public static function verificaPayPassword($id, $pay_pass){
        $account_data = DB::table('account')
            ->where('id', '=', "$id")
            ->first();

        if (!password_verify($pay_pass, $account_data->pay_password)) {
            return false;
        }
        return true;
    }
}
