<?php

namespace App\Libraries;

use ipip\db\City;
use itbdw\Ip\IpLocation;

class Functions
{
    public static function isAdmin($request): bool
    {
        return ($request->session()->get('accountType') == 'admin');
    }

    public static function get_ip()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } else if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }

    // des 加密
    public static function sign3des($data)
    {
        $key = config('admin.des_key');
        $data = openssl_encrypt($data, 'des-ede3', $key, 0);
        return $data;
    }

    // des 解密
    public static function desing3des($decrypted)
    {
        $key = config('admin.des_key');
        $result = openssl_decrypt($decrypted, 'des-ede3', $key, 0);
        return $result;
    }

    // AES 解密
    public static function decryptAES($decrypted, $key, $salt, $iv)
    {
        try {
            $salt = hex2bin($salt);
            $iv  = hex2bin($iv);
        } catch(Exception $e) {
            return null;
        }

        $ciphertext = base64_decode($decrypted);
        $iterations = 999; //same as js encrypting

        $key = hash_pbkdf2("sha512", $key, $salt, $iterations, 64);

        $decrypted = openssl_decrypt($ciphertext , 'aes-256-cbc', hex2bin($key), OPENSSL_RAW_DATA, $iv);

        return $decrypted;
    }

    // AES 加密
    public static function encryptAES($data)
    {
        $salt = random_bytes(256);
        $iv = random_bytes(16);

        $plain_text = json_encode($data);

        $iterations = 999;
        // $passphrase = self::randtext(112);
        $passphrase = config('admin.aes_key');
        $key = hash_pbkdf2("sha512", $passphrase, $salt, $iterations, 64);

        $encrypted_data = openssl_encrypt($plain_text, 'aes-256-cbc', hex2bin($key), OPENSSL_RAW_DATA, $iv);

        return [
            "data" => base64_encode($encrypted_data),
            "i" => bin2hex($iv),
            "s" => bin2hex($salt),
        ];
    }

    // RSA 解密
    public static function rsaDecrypt($data)
    {
        $privateKey = config('admin.rsa_private_key');

        $res_prv = openssl_get_privatekey($privateKey);
        if (openssl_private_decrypt(base64_decode($data), $decrypted, $res_prv))
            $data = $decrypted;
        else
            $data = '';

        return $data;
    }

    //获取乱数
    public static function randtext($length)
    {
        $password_len = $length;
        $password = '';
        $word = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $len = strlen($word);
        for ($i = 0; $i < $password_len; $i++) {
            $password .= $word[rand() % $len];
        }
        return $password;
    }

    //转换银行代码
    public static function convertBank($bankName)
    {
        $bank = [
            '1100' => '工商银行',
            '1101' => '农业银行',
            '1102' => '招商银行',
            '1103' => '兴业银行',
            '1104' => '中信银行',
            '1106' => '中国建设银行',
            '1107' => '中国银行',
            '1108' => '交通银行',
            '1109' => '浦发银行',
            '1110' => '民生银行',
            '1111' => '华夏银行',
            '1112' => '光大银行',
            '1113' => '北京银行',
            '1114' => '广发银行',
            '1115' => '南京银行',
            '1116' => '上海银行',
            '1117' => '杭州银行',
            '1118' => '宁波银行',
            '1119' => '邮储银行',
            '1120' => '浙商银行',
            '1121' => '平安银行',
            '1122' => '东亚银行',
            '1123' => '渤海银行',
            '1124' => '北京农商行',
            '1127' => '浙江泰隆商业银行',
        ];
        foreach ($bank as $code => $name) {
            if ($bankName == $name) {
                return $code;
            }
        }
        return "";
    }

    public static function getRegionFromIp($ip)
    {
        $result = self::getip_ipipDB($ip);
        if (!$result) {
            $result = self::getip_itbdw($ip);
        }
        if (!$result) {
            $result = self::getip_taobao($ip);
        }
        $result['region'] = $result['region'] ?? '';
        $result['city'] = $result['city'] ?? '';
        return $result;
    }

    /**
     * https://www.ipip.net/product/client.html
     */
    public static function getip_ipipDB($ip)
    {
        try {
            $city = new City(resource_path('ip/ipiptest.ipdb'));
            $result = $city->findMap($ip, 'CN');
            if ($result) {
                $result['region'] = $result['region_name'];
                $result['city'] = $result['city_name'];
            }
            return $result;
        } catch (\Exception $e) {

        } catch (\Throwable $t) {

        }
        return false;
    }

    /**
     * 纯真ip库
     *
     * https://github.com/itbdw/ip-database
     * https://github.com/WisdomFusion/qqwry.dat
     */
    public static function getip_itbdw($ip)
    {
        try {
            $result = IpLocation::getLocation($ip, resource_path('ip/qqwry.dat'));
            if (isset($result['error'])) {
                return false;
            }
            if ($result) {
                $result['region'] = $result['province'];
                $result['city'] = $result['city'];
            }
            return $result;
        } catch (\Exception $e) {

        } catch (\Throwable $t) {

        }
        return false;
    }


    public static function getip_taobao($ip)
    {
        try {
            $url = "http://ip.taobao.com/service/getIpInfo.php?ip=$ip";
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_FAILONERROR, false);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($curl, CURLOPT_TIMEOUT, 5);
            $_result = curl_exec($curl);
            curl_close($curl);
            $res = json_decode($_result,true);
            if($res['code'] =='0'){
                $result = $res['data'];
                if (empty($result['region']) && empty($result['city'])) {
                    return false;
                }
                return $result;
            }

        } catch (\Exception $e) {

        } catch (\Throwable $t) {

        }
        return false;
    }

    // U盒效验码
    public static function calcUidCode($uid)
    {
        $sum = 0;
        for ($i = 0; $i < strlen($uid); $i++){
            $sum += hexdec($uid[$i]);
        }
        $code = sprintf('%X', ($sum * $sum) % 4096);
        $code = str_pad($code, 3, "0", STR_PAD_LEFT);
        return $code;
    }

    // U盒效验码
    public static function verifyUidCode($uid)
    {
        $code = substr($uid, -3);
        $uid = substr($uid, 0, -3);
        return (self::calcUidCode($uid) == $code);
    }

    public static function checkSign($data, $key)
    {
        if (empty($key)) {
            return false;
        }

        $sign = $data['sign'];
        unset($data['sign']);
        $tempSign = Cryptology::md5sign($key, $data);

        if ($sign != $tempSign) {
            error_log('验签失败 正确应为:' . $tempSign);
            return false;
        }
        return true;
    }

}
