<?php

// server 连 client

class AutorunVPNTest extends TransTest
{

    public function testTransaction()
    {
        $config = [
            'dev_id' => 'autorun_device_ABCDE',
            'hashcode' => 'as1',
            'port' => 4,
        ];
        // 新增 autorun任务
        $job_order_number = $this->addJob();
        error_log('新增 autorun任务 job_order_number:'. $job_order_number);

        // 取得 autorun任务
        $response = $this->curlApi('/autorunjob/get', ['dev_id' => $config['dev_id']]);
        // $this->debug($response);
        $this->assertArrayHasKey('ID', $response);
        $this->assertTrue(boolval($response['ID']));

        $id = $response['ID'];
        error_log('1. 取得 autorun任务 id:'. $id);

        // 检查db是否为3
        $db_autorun_job = DB::table('autorun_job')->where('id', $id)->first();
        $this->assertEquals($db_autorun_job->status, 3);


        // 取得 devices status
        $response = $this->curlApi('/devices/status', ['hashcode' => $config['hashcode'], 'port' => $config['port']]);

        $this->assertArrayHasKey('ip', $response);
        error_log('2. 取得 设备状态 ip:'. $response['ip']);


        // 发送按压需求
        error_log('3. 新增按压任务');
        $press_data = [
            'hashcode' => $config['hashcode'],
            'port' => $config['port'],
            'autorun_job_id' => $id,
        ];
        $response = $this->curlApi('/usbjob/add', $press_data, false);

        // 将按压需求改回0 用于测试 usbjob/get, update
        $db_usbjob = DB::table('usb_job')->where('autorun_job_id', $id)->orderBy('created_at', 'desc')->first();
        $usbjobId = $db_usbjob->id;


        // 删除其它任务
        DB::table('usb_job')
            ->where('autorun_job_id', $id)
            ->where('id', '!=', $usbjobId)
            ->delete();
        DB::table('usb_job')->where('id', $usbjobId)->update(['status' => 0]);

        $response = $this->curlApi('/usbjob/get', ['hashcode' => $config['hashcode']]);
        $this->assertArrayHasKey('work_Id', $response);
        $this->assertArrayHasKey('port', $response);
        $this->assertEquals($response['work_Id'], $usbjobId);
        $this->assertEquals($response['port'], $config['port']);
        $workId = $response['work_Id'];
        error_log('4. 获取按压任务 id:'. $workId);

        $response = $this->curlApi('/usbjob/update', ['work_Id' => $workId, 'result' => 1, 'remark' => '测试'.date("Y-m-d H:i:s")]);
        $db_usbjob = DB::table('usb_job')
            ->where('autorun_job_id', $id)
            ->where('id', $workId)
            ->first();
        $this->assertEquals($db_usbjob->status, 1);
        error_log('5. 回报按压任务完成');

        $response = $this->curlApi('/autorunjob/update', ['id' => $id, 'status' => 1, 'attach' => '测试'.date("Y-m-d H:i:s")]);

        $db_autorun_job = DB::table('autorun_job')->where('id', $id)->first();
        $this->assertEquals($db_autorun_job->status, 1);
        error_log('6. 回报autorun任务完成');
    }
}
