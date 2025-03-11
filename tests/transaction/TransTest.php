<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use App\Libraries\Cryptology;

abstract class TransTest extends TestCase
{

    public function setUp()
    {
        parent::setUp();
    }

    public function curlApi($url, $data = [], $assertMsgCode = true)
    {
        if ($data) {
            $data = Cryptology::encryption($data);
            $data = ['data' => $data];
        }
        $response = $this->post($url, $data);

        $this->assertEquals(200, $response->response->status());
        $data = json_decode($response->response->getContent(), true);
        $this->assertArrayHasKey('data', $data);

        //解密
        $decryption = Cryptology::decryption($data['data']);
        // $this->debug($decryption);
        if ($assertMsgCode) {
            $this->assertEquals($decryption['msg_code'], 1);
        }
        $this->assertArrayHasKey('data', $decryption);
        return $decryption['data'];
    }

    protected function debug($data)
    {
        error_log(print_r($data, true));
    }

    protected function addJob()
    {
        $add_data = $this->generateAddData();
        $response = $this->post('/api/addjob', $add_data);

        $this->assertEquals(200, $response->response->status());
        $job_data = json_decode($response->response->getContent(), true);
        $this->assertEquals($job_data['msg_code'], 1);
        $this->assertArrayHasKey('data', $job_data);
        $data = $job_data['data'];
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('job_order_number', $data[0]);

        return $data[0]['job_order_number'];
    }
    protected function generateAddData()
    {
        $des_key = 'hl733ybk253w5vcrzz251x1uxd4alqi3';
        $md5_key = 'mk6ruvqqw3unrlmvomrzkjhtuk5zax6t';
        $data = [
            [
                'amount'=>'0.1',
                'attach'=> 'test'.date("Y-m-d H:i:s"),
                'bankcard'=> '1234',
                'type' => '2',
                'tran_card_name' => 'tester',
                'tran_card_no' => '4321',
            ]
        ];
        $datas = json_encode($data);
        $rus = openssl_encrypt($datas, 'des-ede3', $des_key, 0);
        $sign = $this->md5sign($md5_key , ['data' => $datas]);
        return [
            'account' => 'jordan',
            'data'=> $rus,
            'sign'=> $sign
        ];
    }

    protected function md5sign($key, $list)
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
