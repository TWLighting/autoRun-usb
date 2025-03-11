<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->post('/version', ['uses' => 'VersionController@getVersion']);
$router->post('/testing_config', ['uses' => 'VersionController@testingConfig']);

$router->group(['middleware' => 'decryption'], function () use ($router) {

    $router->group(['prefix' => 'autorun'], function () use ($router) {
        // autorun 状态回报
        $router->post('/heartbeat', ['uses' => 'AutorunController@heartbeat']);
    });

    $router->group(['prefix' => 'autorunjob'], function () use ($router) {
        // 派发工作 for autorun
        $router->post('/get', ['uses' => 'AutoJobController@getJob']);

        // 更新任务 状态
        $router->post('/update', ['uses' => 'AutoJobController@update']);
    });

    $router->group(['prefix' => 'usbjob'], function () use ($router) {
        // 按壓需求
        $router->post('/add', ['uses' => 'UsbTaskController@addJob']);

        // 派发工作 for USB device
        $router->post('/get', ['uses' => 'UsbTaskController@getTask']);

        // 更新工作 状态 for USB device
        $router->post('/update', ['uses' => 'UsbTaskController@updateTask']);
    });

    $router->group(['prefix' => 'test/usbjob'], function () use ($router) {
        // 按壓需求 测试
        $router->post('/add', ['uses' => 'DevicesTestController@addJob']);

        // 按壓需求 测试结果
        $router->post('/test_report', ['uses' => 'DevicesTestController@testResult']);
    });

    $router->group(['prefix' => 'devices'], function () use ($router) {
        // 回报U盾状况
        $router->post('/update', ['uses' => 'DevicesController@update']);

        // 更新版本中
        $router->post('/updating', ['uses' => 'DevicesController@updating']);

        // 确认U盾状况
        $router->post('/status', ['uses' => 'DevicesController@status']);

        // 注册api
        $router->post('/register', ['uses' => 'TempDevicesController@register']);

        // unittest 进度
        $router->post('/update_test_status', ['uses' => 'TempDevicesController@updateTestStatus']);

        // 测试设备列表
        $router->post('/test/list', ['uses' => 'TempDevicesController@testDeviceList']);
    });


    $router->group(['prefix' => 'bank'], function () use ($router) {
        // 取得全部银行卡
        $router->post('/list', ['uses' => 'BankController@getList']);

        // 取得全部银行卡 - 登入伺服器
        $router->post('/login_list', ['uses' => 'BankController@getLoginList']);

        // 取得全部银行卡 - 根据账号
        $router->post('/list_byaccount', ['uses' => 'BankController@getListByAccount']);

        // 记录银行卡cookie
        $router->post('/set_cookie', ['uses' => 'BankController@setCardCookie']);

        // 取得银行卡cookie
        $router->post('/get_cookie', ['uses' => 'BankController@getCardCookie']);

        // 取得银行卡cookie by Autorun
        $router->post('/get_cookie_autorun', ['uses' => 'BankController@getCardCookieByAutorun']);

        $router->group(['prefix' => 'detail'], function () use ($router) {
            // 取得某笔银行卡 最后记录
            $router->post('/get', ['uses' => 'BankController@detailGet']);

            // 新增 / 更新银行卡 记录
            $router->post('/update', ['uses' => 'BankController@detailUpdate']);
        });
    });

    $router->group(['prefix' => 'captcha'], function () use ($router) {
        // 新增验证码请求
        $router->post('/solve', ['uses' => 'CaptchaController@solve']);
    });

    $router->group(['prefix' => 'ubox'], function () use ($router) {
        // 烧录uid 验证
        $router->post('/register', ['uses' => 'UBoxController@register']);

        // 作废api
        $router->post('/discard', ['uses' => 'UBoxController@discard']);
    });

    $router->group(['prefix' => 'sharelist'], function () use ($router) {
        // 分享任务监听
        $router->post('/get', ['uses' => 'ShareListController@get']);

        // 更新分享任务结果
        $router->post('/update', ['uses' => 'ShareListController@update']);

        // 分享任务注册
        $router->post('/register', ['uses' => 'ShareListController@register']);

        // 分享任务请求
        $router->post('/request', ['uses' => 'ShareListController@request']);

    });
});
