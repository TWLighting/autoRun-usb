<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;

class VersionController extends Controller
{

    public function getVersion(Request $request)
    {
        $config = DB::table('config')
            ->get()
            ->keyBy('name');

        $data = [
            'autorun_version' => config('autorun.version'),
            'device_version' => (isset($config['device_version'])) ? $config['device_version']->value : "",
            'ftp_link' => (isset($config['ftp_link'])) ? $config['ftp_link']->value : "",
            'initial_version' => (isset($config['initial_version'])) ? $config['initial_version']->value : "",
            'monitor_version' => (isset($config['monitor_version'])) ? $config['monitor_version']->value : "",
            'usb_network_gate' => (isset($config['usb_network_gate'])) ? $config['usb_network_gate']->value : "",
        ];

        return $this->presenter->json($data, '成功', 1);
    }

    public function testingConfig(Request $request)
    {
        $data = DB::table('config')
            ->select(['name', 'value'])
            ->where('name', 'like', 'testing_%')
            ->get();

        $result = [];
        foreach ($data as $val) {
            $result[$val->name] = $val->value;
        }
        return $this->presenter->json($result, '成功', 1);
    }

}
