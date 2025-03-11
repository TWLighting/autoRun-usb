<?php

namespace App\Libraries;

class Cryptology {

    private static function enShift($str)
    {
        $temp_header = substr($str, 0, 10);
        $temp_footer = substr($str, 10);
        return substr_replace($temp_footer, $temp_header, -2, 0);
    }

    private static function deShift($str)
    {
        $temp_header = substr($str, -12, 10);
        $temp_footer = substr($str, 0,-12);
        $temp_footer2 = substr($str, -2);
        return $temp_header.$temp_footer.$temp_footer2;
    }

    public static function encryption($data)
    {
        $result = json_encode($data,320);
        $result = base64_encode($result);
        $result = self::enShift($result);
        $result = base64_encode($result);
        $result = self::enShift($result);
        return $result;
    }

    public static function decryption($post_data)
    {
        $result = self::deShift($post_data);
        $result = base64_decode($result);
        $result = self::deShift($result);
        $result = base64_decode($result);
        return json_decode($result, true);
    }

    public static function md5sign($key, $list)
    {
        ksort($list);
        $md5str = "";
        foreach($list as $k => $v) {
            if("" != $v && "sign" != $k && "key" != $k) {
                $md5str .= $k . "=" . $v . "&";
            }
        }
        $sign = strtoupper(md5($md5str . "key=" . $key));
        return $sign;
    }

}