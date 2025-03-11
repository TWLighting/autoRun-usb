<?php


$router->group(['prefix' => 'login'], function () use ($router) {
    // 登入
    $router->post('/login', ['uses' => 'LoginController@login']);

    // 取得登入资讯
    $router->post('/checkstatus', ['uses' => 'LoginController@checkStatus']);

    // 登出
    $router->post('/logout', ['uses' => 'LoginController@logout']);

    //获取验证码
    $router->post('/obtain', ['uses' => 'LoginController@obtain']);
});


// 只有 admin 能连的 api
$router->group(['middleware' => 'adminAuth'], function () use ($router) {
    $router->group(['prefix' => 'account'], function () use ($router) {

        $router->post('/edit', ['uses' => 'AccountController@edit']);
        $router->post('/insert', ['uses' => 'AccountController@insert']);

        $router->post('/notification', ['uses' => 'AccountController@callNotification']);
    });

    $router->group(['prefix' => 'usbdevice'], function () use ($router) {
        $router->post('/create', ['uses' => 'UsbDeviceController@create_device']);

        // 通知商户 未更新的设备
        $router->post('/notify_version', ['uses' => 'UsbDeviceController@notifyOldVersionDevice']);

        // 异常log
        $router->post('/device_log', ['uses' => 'DeviceLogController@getList']);

        // 注册列表
        $router->post('/register_list', ['uses' => 'TempDeviceController@getRegisterList']);
        $router->post('/multi_add', ['uses' => 'TempDeviceController@multiAdd']);
        $router->post('/cancel_register', ['uses' => 'TempDeviceController@cancelRegister']);
        $router->get('/export_temp', ['uses' => 'TempDeviceController@exportData']);
    });

    // U盒管理
    $router->group(['prefix' => 'temp_ubox'], function () use ($router) {
        $router->post('/list', ['uses' => 'TempUBoxController@getList']);
        $router->post('/multi_add', ['uses' => 'TempUBoxController@multiAdd']);
        $router->get('/export_temp', ['uses' => 'TempUBoxController@exportData']);
    });

    $router->group(['prefix' => 'captcha'], function () use ($router) {
        $router->post('/list', ['uses' => 'CaptchaController@getList']);
        $router->post('/update', ['uses' => 'CaptchaController@updateCode']);
    });

    //usb_key
    $router->group(['prefix' => 'usbkey'], function () use ($router) {
        $router->post('/update_autorun', ['uses' => 'UsbKeyAutorunController@updateUKeyAutoRun']);
        $router->post('/getby_autorun', ['uses' => 'UsbKeyAutorunController@getUKeyByAutorun']);
    });

    //autoRunDevice
    $router->group(['prefix' => 'rundevice'], function () use ($router) {
        $router->post('/list', ['uses' => 'AutoRunDeviceController@getList']);
        $router->post('/create', ['uses' => 'AutoRunDeviceController@create']);
        $router->post('/update', ['uses' => 'AutoRunDeviceController@update']);
        $router->post('/update_status', ['uses' => 'AutoRunDeviceController@updateStatus']);
        $router->post('/select_list', ['uses' => 'AutoRunDeviceController@getListForSelect']);
    });

    //news_msg
    $router->group(['prefix' => 'news_msg'], function () use ($router) {
        $router->post('/list', ['uses' => 'NewsMsgController@getList']);
        $router->post('/edit', ['uses' => 'NewsMsgController@edit']);
        $router->post('/insert', ['uses' => 'NewsMsgController@insert']);
        $router->post('/delete', ['uses' => 'NewsMsgController@delete']);
    });

    //bank_card_info
    $router->group(['prefix' => 'bankcard'], function () use ($router) {
        $router->post('/set_login_server', ['uses' => 'BankCardInfoController@setLoginServer']);
        $router->post('/get_login_server', ['uses' => 'BankCardInfoController@getLoginServerList']);
    });
});

// admin, user 都能连的 api
$router->group(['middleware' => 'userAuth'], function () use ($router) {

    $router->group(['prefix' => 'account'], function () use ($router) {
        $router->post('/list', ['uses' => 'AccountController@getList']);
        $router->post('/change_status', ['uses' => 'AccountController@changeStatus']);
        $router->post('/user_insert', ['uses' => 'AccountController@userInsert']);
        $router->post('/user_edit', ['uses' => 'AccountController@userEdit']);
        $router->post('/view_key', ['uses' => 'AccountController@viewKey']);
    });

    $router->group(['prefix' => 'user'], function () use ($router) {
        $router->post('/change_pw', ['uses' => 'AccountController@changePw']);
    });

    $router->group(['prefix' => 'usbdevice'], function () use ($router) {

        $router->post('/list', ['uses' => 'UsbDeviceController@getList']);
        $router->post('/all', ['uses' => 'UsbDeviceController@getAll']);

        $router->post('/register_device', ['uses' => 'UsbDeviceController@register_device']);
        $router->post('/change_status', ['uses' => 'UsbDeviceController@changeStatus']);
        $router->post('/update_nickname', ['uses' => 'UsbDeviceController@update_nickname']);
    });

    $router->group(['prefix' => 'transaction'], function () use ($router) {
        $router->post('/list', ['uses' => 'TransactionController@getList']);
        $router->get('/export', ['uses' => 'TransactionController@exportData']);
        $router->post('/sum_amount', ['uses' => 'TransactionController@getSumAmount']);
    });

    //usb_key
    $router->group(['prefix' => 'usbkey'], function () use ($router) {

        $router->post('/list', ['uses' => 'UsbKeyController@getList']);

        $router->post('/create', ['uses' => 'UsbKeyController@create']);

        $router->post('/updateport', ['uses' => 'UsbKeyController@update_port']);

        $router->post('/updatekey', ['uses' => 'UsbKeyController@update_key']);
        $router->post('/change_status', ['uses' => 'UsbKeyController@changeStatus']);
        $router->post('/clear_usbuid', ['uses' => 'UsbKeyController@clearUsbUid']);

        // usbkey列表 for 下拉式选单
        $router->post('/key_list', ['uses' => 'UsbKeyController@getUsbKey']);
    });
    //bank_card_info
    $router->group(['prefix' => 'bankcard'], function () use ($router) {

        $router->post('/list', ['uses' => 'BankCardInfoController@getList']);


        $router->post('/cardno', ['uses' => 'BankCardInfoController@getCardNo']);

        // 商户管理员才能呼叫
        $router->group(['middleware' => 'managerAuth'], function () use ($router) {
            $router->post('/change_status', ['uses' => 'BankCardInfoController@changeStatus']);
            $router->post('/change_notify_status', ['uses' => 'BankCardInfoController@changeNotifyStatus']);
            $router->post('/creat', ['uses' => 'BankCardInfoController@creatBankCard']);
            $router->post('/update', ['uses' => 'BankCardInfoController@updateBankCard']);
            $router->post('/delete', ['uses' => 'BankCardInfoController@deleteBankCard']);
        });
    });
    //autorun_job
    $router->group(['prefix' => 'autorun'], function () use ($router) {
        $router->post('/list', ['uses' => 'AutoRunJobController@getList']);
        $router->post('/list_sumamount', ['uses' => 'AutoRunJobController@getListSum']);
        $router->post('/add', ['uses' => 'AutoRunJobController@addJob']);
        $router->post('/change_status', ['uses' => 'AutoRunJobController@changeStatus']);
        $router->post('/redo_jobs', ['uses' => 'AutoRunJobController@redoJobs']);
    });
    //usb_port
    $router->group(['prefix' => 'usbport'], function () use ($router) {
        $router->post('/getport', ['uses' => 'UsbPortController@getUserPort']);
    });

    // 首页api
    $router->group(['prefix' => 'home'], function () use ($router) {
        $router->post('/login_record', ['uses' => 'HomeController@loginRecord']);
        $router->post('/news_msg', ['uses' => 'HomeController@newsMsg']);
        $router->post('/trans_calc', ['uses' => 'HomeController@transCalc']);
        $router->post('/devices_status', ['uses' => 'HomeController@devicesStatus']);
    });

    $router->group(['prefix' => 'share'], function () use ($router) {
        $router->post('/list', ['uses' => 'ShareListController@getList']);
        $router->post('/create', ['uses' => 'ShareListController@insert']);
    });
});
