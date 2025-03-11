#### run dev service
```
npm install
node app.js
```

## production server
```
sudo npm install pm2 -g

pm2 start app.js -i 3

# 开机自动启动
pm2 startup
[PM2] Freeze a process list on reboot via:
$ pm2 save

[PM2] Remove init script via:
$ pm2 unstartup systemd
```

# ref
1. [PM2实用入门指南](https://www.cnblogs.com/chyingp/p/pm2-documentation.html)
2. [pm2常用指令](https://bitcoin-on-nodejs.ebookchain.org/4-开发实践/5-部署/2-生产环境下的pm2部署.html)
3. [pm2开机自启动](https://www.zhaofinger.com/detail/9)
4. [开机启动官方文件](http://pm2.keymetrics.io/docs/usage/startup/)
5. [cnodejs讨论](https://cnodejs.org/topic/556f02a98ce3684b284b55ad)
