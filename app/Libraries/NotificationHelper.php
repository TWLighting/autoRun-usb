<?php
/**
 * Created by PhpStorm.
 * User: User
 * Date: 2019/2/21
 * Time: 下午 05:34
 */

namespace App\Libraries;

use App\Libraries\CurlRequest;

class NotificationHelper
{
    public static function telegramSendmsg($msg, $path, $chatid, $timeout=1)
    {
        try{
            if (empty($path) || empty($chatid) ) {
                return false;
            }
            $host = "https://api.telegram.org";
            $querys = "chat_id=".$chatid."&text=".urlencode($msg);
            $url = sprintf('%s%s?%s', $host, $path, $querys);
            $output = CurlRequest::curl($url, [], [], 'off', $timeout);
            $res = json_decode($output,true);
            if($res['ok'] == true){
                return true;
            }
            return false;
        } catch(\Exception $e) {
            return false;
        }
    }

    public static function slackSendmsg($url, $msg)
    {
        try{
            $msg = json_encode(['text' => $msg], 320);
            $output = CurlRequest::curl($url, $msg, [], 'off', 1);
            if($output == 'ok'){
                return true;
            }
            return false;
        } catch(\Exception $e) {
            return false;
        }
    }
}