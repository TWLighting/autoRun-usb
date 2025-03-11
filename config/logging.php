<?php

use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Out of
    | the box, Laravel uses the Monolog PHP logging library. This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog",
    |                    "custom", "stack"
    |
    */

    'channels' => [
        // 排程 log
        'cronjob' => [
            'driver' => 'daily',
            'path' => storage_path('logs/cronjob.log'),
            'level' => 'info',
            'days' => 14,
        ],
        // [api] U盒 烧录
        'ubox' => [
            'driver' => 'daily',
            'path' => storage_path('logs/api/ubox.log'),
            'level' => 'info',
            'days' => 7,
        ],
        // [admin] U盾 修改 AutoRun
        'admin_autorun' => [
            'driver' => 'daily',
            'path' => storage_path('logs/admin/admin_autorun.log'),
            'level' => 'info',
            'days' => 7,
        ],
        // [api, admin] 发送 websocket 通知
        'notify' => [
            'driver' => 'daily',
            'path' => storage_path('logs/notify.log'),
            'level' => 'info',
            'days' => 14,
        ],
        // [api] 分享功能
        'sharelist' => [
            'driver' => 'daily',
            'path' => storage_path('logs/api/sharelist.log'),
            'level' => 'info',
            'days' => 14,
        ],
        // [api] 设备
        'devices' => [
            'driver' => 'daily',
            'path' => storage_path('logs/api/devices.log'),
            'level' => 'info',
            'days' => 14,
        ],
        // [api] 银行卡
        'bank' => [
            'driver' => 'daily',
            'path' => storage_path('logs/api/bank.log'),
            'level' => 'info',
            'days' => 14,
        ],
        // [api] u盾任务
        'usbtask' => [
            'driver' => 'daily',
            'path' => storage_path('logs/api/usbtask.log'),
            'level' => 'info',
            'days' => 14,
        ],
        // [public-api] autorun 新增任务
        'autorunJob' => [
            'driver' => 'daily',
            'path' => storage_path('logs/public-api/autorunJob.log'),
            'level' => 'info',
            'days' => 14,
        ],
        // [api] autorun 任务
        'obtpjob' => [
            'driver' => 'daily',
            'path' => storage_path('logs/api/obtpjob.log'),
            'level' => 'info',
            'days' => 14,
        ],
        // 目前没用到
        'stack' => [
            'driver' => 'stack',
            'channels' => ['daily'],
        ],

        'single' => [
            'driver' => 'daily',
            'path' => storage_path('logs/lumen.log'),
            'level' => 'info',
        ],

        'callback' => [
            'driver' => 'single',
            'path' => storage_path('logs/callback.log'),
            'level' => 'debug',
            'days' => 14,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/lumen.log'),
            'level' => 'debug',
            'days' => 14,
        ],
        // C# slack 通知
        'csharp_slack' => [
            'driver' => 'slack',
            'url' => 'https://hooks.slack.com/services/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',
            'username' => '系统回报',
            'emoji' => ':bell:',
            'level' => 'critical',
            'short' => true,
            'bubble' => false,
            'attachment' => false,
            'context' => false,
        ],
        // 商务 slack 通知
        'business_slack' => [
            'driver' => 'slack',
            'url' => 'https://hooks.slack.com/services/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', // 测试通道
            // 'url' => 'https://hooks.slack.com/services/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' //商务正式群
            'username' => '系统通知',
            'emoji' => ':bell:',
            'formatter' => Monolog\Formatter\LineFormatter::class,
            'formatter_with' => [
                'format' => "[%datetime%] %message%\n",
            ],
            'level' => 'info',
            'short' => true,
            'bubble' => false,
            'attachment' => false,
            'context' => false,
        ],

        'papertrail' => [
            'driver'  => 'monolog',
            'level' => 'debug',
            'handler' => SyslogUdpHandler::class,
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
            ],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'handler' => StreamHandler::class,
            'with' => [
                'stream' => 'php://stderr',
            ],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => 'debug',
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => 'debug',
        ],
    ],

];
