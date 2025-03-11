# autoRun-usb

#### docs
https://drive.google.com/drive/folders/15JwGKVA23AZTlgdH1jbl8emIhwczEUac

#### run server local
```
$ php -S 0.0.0.0:8000 -t public
```

#### run server production (Apache2)
```
<VirtualHost *:8081>
ServerName localhost:8080
 DocumentRoot "C:/xampp7.1/htdocs/autoRun-usb/public"
    <Directory "C:/xampp7.1/htdocs/autoRun-usb/public">
        Options -Indexes +FollowSymLinks +MultiViews
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

#### run unittests
```
$ vendor/bin/phpunit
```

#### run unittests in directory
```
$ vendor/bin/phpunit tests/admin_api

//windows
php vendor/phpunit/phpunit/phpunit tests/db
```

#### run single test
```
vendor/bin/phpunit --filter AutorunTest::testTransaction tests/transaction

vendor/bin/phpunit --filter AutorunVPNTest::testTransaction tests/transaction
```


#### run test (curl version)
```
php tests/php/autorun.php
php tests/php/autorun_vpn.php
```

#### crontab 设定
```
10 0 * * * cd /var/www/autoRun-usb/ && php artisan autorunDaily
* * * * * cd /var/www/autoRun-usb/ && php artisan autorunStatusCheck
0 1 * * * cd /var/www/autoRun-usb/ && php artisan log:clear
0 2 * * * cd /var/www/autoRun-usb/ && php artisan data:clear
```

#### 删除两个月前的log
```
php artisan log:clear
```

#### 删除DB 旧log
```
php artisan data:clear
```

#### run cronjob cli 统计autorun资料
```
// yesterday
php artisan autorunDaily

// target date
php artisan autorunDaily --date=2019-03-27
```