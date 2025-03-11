-- phpMyAdmin SQL Dump
-- version 4.6.6deb5
-- https://www.phpmyadmin.net/
--
-- 主機: 35.194.184.219
-- 產生時間： 2019 年 05 月 10 日 12:20
-- 伺服器版本: 5.7.14-google-log
-- PHP 版本： 7.2.17-0ubuntu0.18.04.1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 資料庫： `autorun_usb2`
--

-- --------------------------------------------------------

--
-- 資料表結構 `account`
--

CREATE TABLE `account` (
  `id` int(11) NOT NULL,
  `account` varchar(30) NOT NULL,
  `top_account_id` int(3) NOT NULL COMMENT '主商户ID (父类)',
  `permission` tinyint(4) NOT NULL DEFAULT '1' COMMENT '0: 一般(只能看), 1:全功能',
  `password` varchar(255) NOT NULL,
  `pay_password` varchar(255) NOT NULL COMMENT '交易密码',
  `md5_key` varchar(50) DEFAULT NULL,
  `des_key` varchar(50) DEFAULT NULL,
  `name` varchar(20) DEFAULT NULL,
  `is_admin` tinyint(4) NOT NULL DEFAULT '0' COMMENT '0:客户，1:管理员',
  `login_ip` varchar(20) DEFAULT NULL,
  `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '0: 停用，1:启用',
  `telegram_path` varchar(200) DEFAULT NULL COMMENT 'telegram位址',
  `telegram_chatid` varchar(20) DEFAULT NULL COMMENT 'telegram位址',
  `telegram_code` varchar(4) DEFAULT NULL COMMENT '登入验证码',
  `frequence` int(11) DEFAULT '0' COMMENT '失败次数',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '創建時間',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新時間',
  `last_login_time` timestamp NULL DEFAULT NULL COMMENT '最后登入时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 資料表結構 `autorun_device`
--

CREATE TABLE `autorun_device` (
  `id` int(11) NOT NULL COMMENT '流水序號',
  `dev_id` varchar(32) NOT NULL COMMENT 'autorun設備ID',
  `enable` tinyint(4) NOT NULL DEFAULT '1' COMMENT '是否有效資料(true/false)',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '創建時間',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新時間',
  `heartbeat_time` timestamp NULL DEFAULT NULL COMMENT 'autorun回报时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='autorun設備資訊';

-- --------------------------------------------------------

--
-- 資料表結構 `autorun_job`
--

CREATE TABLE `autorun_job` (
  `id` int(11) NOT NULL COMMENT '流水序號',
  `job_order_number` varchar(50) DEFAULT NULL COMMENT '工作编号',
  `account_id` int(11) DEFAULT NULL COMMENT '客戶ID',
  `recharge_url` varchar(255) DEFAULT NULL COMMENT '充值URL',
  `bank_name` varchar(30) NOT NULL COMMENT '銀行名稱',
  `card_no` varchar(30) NOT NULL COMMENT '銀行卡號',
  `dev_id` varchar(50) DEFAULT NULL COMMENT 'autorun_device  dev_id',
  `usb_device_hashcode` varchar(32) NOT NULL COMMENT '设备hashcode',
  `usb_uid` varchar(100) NOT NULL COMMENT 'usb_uid',
  `status` int(2) NOT NULL DEFAULT '0' COMMENT '0:未執行，1:成功，2:失敗，3:處理中，-1中止',
  `attach` varchar(255) DEFAULT NULL COMMENT '執行結果訊息',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '創建時間',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新時間',
  `success_at` timestamp NULL DEFAULT NULL COMMENT '完成时间',
  `amount` double(10,3) NOT NULL COMMENT '交易金額',
  `pay_order_number` varchar(50) DEFAULT NULL COMMENT '充值訂單號碼',
  `tran_card_name` varchar(20) DEFAULT NULL COMMENT '交易卡戶名',
  `type` enum('1','2') NOT NULL DEFAULT '1' COMMENT '1: 充值；2:轉帳',
  `tran_card_no` varchar(50) DEFAULT NULL COMMENT '交易卡號',
  `user_attach` text COMMENT '備註',
  `autorun_change_time` datetime DEFAULT NULL COMMENT 'autorun_device更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='autorun工作列表\r\n';

-- --------------------------------------------------------

--
-- 資料表結構 `autorun_job_schedule`
--

CREATE TABLE `autorun_job_schedule` (
  `id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `suc_amount` double(15,3) NOT NULL,
  `fail_amount` double(15,3) NOT NULL,
  `suc_num` int(10) NOT NULL,
  `fail_num` int(10) NOT NULL,
  `job_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 資料表結構 `bank_card_cookie`
--

CREATE TABLE `bank_card_cookie` (
  `card_no` varchar(30) NOT NULL,
  `value` text NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 資料表結構 `bank_card_deleted`
--

CREATE TABLE `bank_card_deleted` (
  `id` int(11) NOT NULL COMMENT '流水序號',
  `account_id` int(11) NOT NULL COMMENT '客戶ID',
  `usb_key_id` int(11) DEFAULT NULL COMMENT 'usb_key流水序號',
  `bank_name` varchar(30) NOT NULL COMMENT '銀行名稱',
  `card_no` varchar(30) NOT NULL COMMENT '銀行卡號',
  `acc_name` varchar(30) NOT NULL COMMENT '戶名',
  `login_pwd` varchar(30) NOT NULL COMMENT '登入密碼',
  `ukey_pwd` varchar(30) NOT NULL COMMENT 'U盾密碼',
  `pay_pwd` varchar(30) NOT NULL COMMENT '支付密碼',
  `balance` double(12,2) NOT NULL DEFAULT '0.00' COMMENT '餘額',
  `status` int(11) NOT NULL DEFAULT '0' COMMENT '0:disable, 1:enable , -1:error',
  `created_at` timestamp NULL DEFAULT NULL COMMENT '旧资料創建時間',
  `updated_at` timestamp NULL DEFAULT NULL COMMENT '旧资料更新時間'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='銀行卡資訊';

-- --------------------------------------------------------

--
-- 資料表結構 `bank_card_info`
--

CREATE TABLE `bank_card_info` (
  `id` int(11) NOT NULL COMMENT '流水序號',
  `account_id` int(11) NOT NULL COMMENT '客戶ID',
  `usb_key_id` int(11) DEFAULT NULL COMMENT 'usb_key流水序號',
  `bank_name` varchar(30) NOT NULL COMMENT '銀行名稱',
  `card_no` varchar(30) NOT NULL COMMENT '銀行卡號',
  `acc_name` varchar(30) NOT NULL COMMENT '戶名',
  `login_pwd` varchar(30) NOT NULL COMMENT '登入密碼',
  `ukey_pwd` varchar(30) NOT NULL COMMENT 'U盾密碼',
  `pay_pwd` varchar(30) NOT NULL COMMENT '支付密碼',
  `balance` double(12,2) NOT NULL DEFAULT '0.00' COMMENT '餘額',
  `status` int(11) NOT NULL DEFAULT '0' COMMENT '0:disable, 1:enable , -1:error',
  `notify_enable` tinyint(4) NOT NULL DEFAULT '1' COMMENT '入账通知(开/关)',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '創建時間',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新時間'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='銀行卡資訊';

-- --------------------------------------------------------

--
-- 資料表結構 `captcha_code`
--

CREATE TABLE `captcha_code` (
  `id` int(11) NOT NULL,
  `card_no` varchar(30) NOT NULL COMMENT '银行卡号',
  `captcha_base64` text NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '創建時間',
  `account` varchar(30) DEFAULT NULL COMMENT '回报的管理员',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新時間'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 資料表結構 `config`
--

CREATE TABLE `config` (
  `name` varchar(30) NOT NULL,
  `value` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 資料表結構 `device_log`
--

CREATE TABLE `device_log` (
  `id` int(11) NOT NULL,
  `account` varchar(30) NOT NULL COMMENT '商户号',
  `hashcode` varchar(32) NOT NULL COMMENT 'USB設備ID',
  `type` int(11) NOT NULL COMMENT '1:第三方异常',
  `msg` text NOT NULL COMMENT '讯息',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 資料表結構 `login_log`
--

CREATE TABLE `login_log` (
  `id` int(11) NOT NULL,
  `account` varchar(30) NOT NULL,
  `user_login_ip` varchar(20) NOT NULL,
  `user_login_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `province` varchar(10) DEFAULT NULL,
  `city` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 資料表結構 `news_msg`
--

CREATE TABLE `news_msg` (
  `id` int(11) NOT NULL,
  `create_account` varchar(30) NOT NULL,
  `last_edit_account` varchar(30) DEFAULT NULL,
  `title` varchar(100) NOT NULL,
  `msg` text NOT NULL,
  `alert_type` tinyint(4) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 資料表結構 `temp_device`
--

CREATE TABLE `temp_device` (
  `id` int(11) NOT NULL,
  `hashcode` varchar(32) NOT NULL,
  `port_count` int(11) NOT NULL,
  `status` int(11) NOT NULL DEFAULT '0' COMMENT '-1:取消, 0:未新增, 1:已新增',
  `print_status` tinyint(4) NOT NULL DEFAULT '0' COMMENT '列印贴纸状态 0 未列印, 1:已列印',
  `test_status` smallint(6) NOT NULL DEFAULT '0' COMMENT '测试阶段',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='装置暫存表';

-- --------------------------------------------------------

--
-- 資料表結構 `transaction_info`
--

CREATE TABLE `transaction_info` (
  `id` int(11) NOT NULL COMMENT '流水序號',
  `card_no` varchar(30) NOT NULL COMMENT '銀行卡卡號',
  `bank_name` varchar(30) NOT NULL COMMENT '銀行名稱',
  `tran_time` varchar(50) NOT NULL COMMENT '交易時間(原始字串)',
  `trans_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '交易時間',
  `amt` double(12,2) NOT NULL COMMENT '交易金額',
  `balance` double(12,2) NOT NULL COMMENT '餘額',
  `tran_info` varchar(200) NOT NULL COMMENT '對方信息',
  `tran_type` varchar(50) NOT NULL COMMENT '交易類型',
  `tran_way` varchar(50) NOT NULL COMMENT '交易渠道',
  `note` varchar(200) NOT NULL COMMENT '備註',
  `bankapi_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '收到银行端api 时间',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '創建時間',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新時間'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 資料表結構 `usb_device`
--

CREATE TABLE `usb_device` (
  `id` int(11) NOT NULL COMMENT '流水序號',
  `hashcode` varchar(32) NOT NULL DEFAULT '0' COMMENT 'USB設備ID(hashcode)',
  `nickname` varchar(32) NOT NULL,
  `account_id` int(11) DEFAULT NULL COMMENT '客戶ID',
  `autorun_id` int(11) NOT NULL COMMENT 'autorun設備ID(autorun.id)',
  `ip` varchar(32) DEFAULT '0' COMMENT 'USB設備IP',
  `enable` int(5) NOT NULL DEFAULT '0' COMMENT '是否有效資料(true/false)',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '創建時間',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新時間',
  `port_count` int(8) NOT NULL DEFAULT '16',
  `heartbeat_time` timestamp NULL DEFAULT NULL COMMENT '设备最后回报时间',
  `version` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='USB設備資訊';

-- --------------------------------------------------------

--
-- 資料表結構 `usb_job`
--

CREATE TABLE `usb_job` (
  `id` int(11) NOT NULL COMMENT '流水序號',
  `autorun_job_id` int(11) DEFAULT NULL COMMENT '任务ID',
  `action` varchar(32) NOT NULL DEFAULT '0' COMMENT '0:按壓, 1:重開電, 2:分享, 3:取消分享, 4:取得設備清單',
  `usb_device_hashcode` varchar(32) NOT NULL COMMENT '设备hashcode',
  `usb_uid` varchar(100) NOT NULL COMMENT 'usb_uid',
  `status` int(2) NOT NULL DEFAULT '0' COMMENT '0: 待处理, 1:成功, 2:失败, 3:超时, 4处理中',
  `attach` varchar(255) DEFAULT NULL COMMENT '執行結果訊息',
  `callback_info` varchar(255) DEFAULT NULL COMMENT '其他参数',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '創建時間',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新時間'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='USB設備工作列表\r\n';

-- --------------------------------------------------------

--
-- 資料表結構 `usb_key`
--

CREATE TABLE `usb_key` (
  `id` int(11) NOT NULL COMMENT '流水序號',
  `account_id` varchar(11) NOT NULL DEFAULT '0' COMMENT '客戶ID',
  `autorun_id` int(11) NOT NULL,
  `usb_uid` varchar(100) DEFAULT NULL COMMENT 'usb_uid',
  `name` varchar(100) DEFAULT NULL COMMENT 'usb名称',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '創建時間',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新時間',
  `key_status` int(1) NOT NULL DEFAULT '0',
  `autorun_change_time` datetime DEFAULT NULL COMMENT 'autorun_device更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='UKey裝置資訊\r\n';

-- --------------------------------------------------------

--
-- 資料表結構 `usb_port`
--

CREATE TABLE `usb_port` (
  `id` int(11) NOT NULL COMMENT '流水序號',
  `usb_device_id` varchar(32) NOT NULL DEFAULT '0' COMMENT 'usb_device 流水序號',
  `index` int(11) NOT NULL DEFAULT '0' COMMENT 'USB設備Port的index，最大255',
  `usb_status` int(5) NOT NULL DEFAULT '0' COMMENT 'USB設備端狀態(port_error[-1]/free[0]/shared[1]/connected[2])',
  `enable` int(5) NOT NULL DEFAULT '0' COMMENT '是否具有設備(true[1]/false[0])',
  `devcon_name` varchar(255) DEFAULT NULL COMMENT '设备第三方usb名称',
  `usb_uid` varchar(100) DEFAULT NULL COMMENT 'usb_uid',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '創建時間',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新時間'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='USB設備對應資訊';

--
-- 已匯出資料表的索引
--

--
-- 資料表索引 `account`
--
ALTER TABLE `account`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `account_un` (`account`),
  ADD KEY `top_account_id` (`top_account_id`);

--
-- 資料表索引 `autorun_device`
--
ALTER TABLE `autorun_device`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `dev_id` (`dev_id`);

--
-- 資料表索引 `autorun_job`
--
ALTER TABLE `autorun_job`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `job_order_number_uniq` (`job_order_number`),
  ADD KEY `bank_name_idx` (`bank_name`),
  ADD KEY `card_idx` (`card_no`),
  ADD KEY `status_idx` (`status`),
  ADD KEY `created_at_idx` (`created_at`),
  ADD KEY `pay_order_number_idx` (`pay_order_number`),
  ADD KEY `account_id_idx` (`account_id`),
  ADD KEY `autorun_change_time_idx` (`autorun_change_time`),
  ADD KEY `dev_id` (`dev_id`),
  ADD KEY `success_at` (`success_at`);

--
-- 資料表索引 `autorun_job_schedule`
--
ALTER TABLE `autorun_job_schedule`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `account_id` (`account_id`,`job_date`);

--
-- 資料表索引 `bank_card_cookie`
--
ALTER TABLE `bank_card_cookie`
  ADD PRIMARY KEY (`card_no`);

--
-- 資料表索引 `bank_card_deleted`
--
ALTER TABLE `bank_card_deleted`
  ADD PRIMARY KEY (`id`);

--
-- 資料表索引 `bank_card_info`
--
ALTER TABLE `bank_card_info`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `card_no` (`card_no`) USING BTREE,
  ADD KEY `account_id` (`account_id`),
  ADD KEY `usb_key_id` (`usb_key_id`);

--
-- 資料表索引 `captcha_code`
--
ALTER TABLE `captcha_code`
  ADD PRIMARY KEY (`id`),
  ADD KEY `card_no` (`card_no`);

--
-- 資料表索引 `config`
--
ALTER TABLE `config`
  ADD PRIMARY KEY (`name`);

--
-- 資料表索引 `device_log`
--
ALTER TABLE `device_log`
  ADD PRIMARY KEY (`id`);

--
-- 資料表索引 `login_log`
--
ALTER TABLE `login_log`
  ADD PRIMARY KEY (`id`);

--
-- 資料表索引 `news_msg`
--
ALTER TABLE `news_msg`
  ADD PRIMARY KEY (`id`);

--
-- 資料表索引 `temp_device`
--
ALTER TABLE `temp_device`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hashcode` (`hashcode`);

--
-- 資料表索引 `transaction_info`
--
ALTER TABLE `transaction_info`
  ADD PRIMARY KEY (`id`),
  ADD KEY `card_no` (`card_no`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `bankapi_time` (`bankapi_time`),
  ADD KEY `bank_name` (`bank_name`);

--
-- 資料表索引 `usb_device`
--
ALTER TABLE `usb_device`
  ADD PRIMARY KEY (`id`,`hashcode`),
  ADD UNIQUE KEY `usb_device_id` (`hashcode`) USING BTREE,
  ADD KEY `customer_id` (`account_id`);

--
-- 資料表索引 `usb_job`
--
ALTER TABLE `usb_job`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usb_device_hashcode` (`usb_device_hashcode`),
  ADD KEY `autorun_job_id` (`autorun_job_id`);

--
-- 資料表索引 `usb_key`
--
ALTER TABLE `usb_key`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usb_name_uk` (`account_id`,`name`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `autorun_id` (`autorun_id`),
  ADD KEY `usb_uid` (`usb_uid`);

--
-- 資料表索引 `usb_port`
--
ALTER TABLE `usb_port`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usb_uid` (`usb_uid`) USING BTREE,
  ADD KEY `usb_device_id` (`usb_device_id`);

--
-- 在匯出的資料表使用 AUTO_INCREMENT
--

--
-- 使用資料表 AUTO_INCREMENT `account`
--
ALTER TABLE `account`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;
--
-- 使用資料表 AUTO_INCREMENT `autorun_device`
--
ALTER TABLE `autorun_device`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '流水序號', AUTO_INCREMENT=13;
--
-- 使用資料表 AUTO_INCREMENT `autorun_job`
--
ALTER TABLE `autorun_job`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '流水序號', AUTO_INCREMENT=3;
--
-- 使用資料表 AUTO_INCREMENT `autorun_job_schedule`
--
ALTER TABLE `autorun_job_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- 使用資料表 AUTO_INCREMENT `bank_card_info`
--
ALTER TABLE `bank_card_info`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '流水序號', AUTO_INCREMENT=81;
--
-- 使用資料表 AUTO_INCREMENT `captcha_code`
--
ALTER TABLE `captcha_code`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- 使用資料表 AUTO_INCREMENT `device_log`
--
ALTER TABLE `device_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
--
-- 使用資料表 AUTO_INCREMENT `login_log`
--
ALTER TABLE `login_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=178;
--
-- 使用資料表 AUTO_INCREMENT `news_msg`
--
ALTER TABLE `news_msg`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;
--
-- 使用資料表 AUTO_INCREMENT `temp_device`
--
ALTER TABLE `temp_device`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;
--
-- 使用資料表 AUTO_INCREMENT `transaction_info`
--
ALTER TABLE `transaction_info`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '流水序號', AUTO_INCREMENT=332;
--
-- 使用資料表 AUTO_INCREMENT `usb_device`
--
ALTER TABLE `usb_device`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '流水序號', AUTO_INCREMENT=68;
--
-- 使用資料表 AUTO_INCREMENT `usb_job`
--
ALTER TABLE `usb_job`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '流水序號', AUTO_INCREMENT=19;
--
-- 使用資料表 AUTO_INCREMENT `usb_key`
--
ALTER TABLE `usb_key`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '流水序號', AUTO_INCREMENT=288;
--
-- 使用資料表 AUTO_INCREMENT `usb_port`
--
ALTER TABLE `usb_port`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '流水序號', AUTO_INCREMENT=705;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
