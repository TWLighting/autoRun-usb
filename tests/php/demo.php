<?php
header("Content-type: text/html; charset=utf-8");

$bankAry = [
    '6222033803004730166' => '张晓龙',
    '6222033803005356771' => '宋云湛璇'
];

$msg = "";
if (isset($_POST['submit']) && isset($bankAry[$_POST['bankcard']]) && isset($bankAry[$_POST['target']])) {

    $doApi = true;
    if ($_POST['bankcard'] == $_POST['target']) {
        print_r('银行卡不能相同');
        $doApi = false;
    }
    if (empty($_POST['paymoney']) || $_POST['paymoney'] <= 0) {
        print_r('金额异常');
        $doApi = false;
    }

    $data = [];

    $apitimes = 1;
    if (intval($_POST['apitimes'])) {
        $apitimes = intval($_POST['apitimes']);
    }

    for ($i = 1 ; $i <= $apitimes ; $i++) {
        if ($i > 100) {
            break;
        }
        $data[] = [
            'amount' => round($_POST['paymoney'], 2),
            'attach' => 'demo-test',
            'bankcard' => $_POST['bankcard'],
            'type' => '2',
            'tran_card_name' => $bankAry[$_POST['target']],
            'tran_card_no' => $_POST['target']
        ];
    }

    print_r($data);
    if ($doApi) {
        $result = md5Data($data);
        print_r($result);
        $url = 'http://35.229.174.25/api/addjob';
        // $url = 'http://127.0.0.1:8000/api/addjob';
        $header = ['Content-Type: application/json'];
        $apiResult = myCurl($url, json_encode($result), $header, 'off', 20);
        echo '<br/><br/><br/>结果:';
        print_r($apiResult);
        $apiResult = json_decode($apiResult, true);
        if (isset($apiResult['msg'])) {
            $msg = json_encode($apiResult, 320);
        } else {
            $msg = '系统回传异常';
        }
    }
}


function md5Data($data)
{
    $md5Key = 'fzkkt8q4hssmeoeawt409ic4li465531';
    $desKey = 'kvkmb3dh1a55ljjcv2o4ao25jb9e3cw1';

    $datas = json_encode($data);
    $rus = openssl_encrypt($datas, 'des-ede3', $desKey, 0);
    $sign = md5sign($md5Key, array('data' => $datas));
    return [
        'account'=> '121304',
        'data'=> $rus,
        'sign'=> $sign
    ];
}


function md5sign($key, $list)
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

function myCurl($url, $data=[], $header=[], $ssl='off', $time_out = 5)
{

    $ch = curl_init();
    // curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/68.0.3440.106 Safari/537.36");
    curl_setopt($ch, CURLOPT_URL, $url);
    if (!empty($header)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $time_out);
    curl_setopt($ch, CURLOPT_TIMEOUT, $time_out);
    if ($ssl == 'on') {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
    }
    if (!empty($data)) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
}

?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Demo</title>
    <!-- Bootstrap -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css"
        integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">

</head>
<body>
<div class="container">
    <div class="row" style="margin:15px;0;">
        <div class="col-md-12">
            <form class="form-inline" method="post" action="">
                <div>
                    <span>支付账号</span>
                    <select name="bankcard">
                        <?php foreach($bankAry as $cardno => $name):?>
                            <option value="<?php echo $cardno;?>"><?php echo $name;?></option>
                        <?php endforeach; ?>
                    </select>

                    <span class="ml-3">对方账号</span>
                    <select name="target" id="target">
                        <?php foreach($bankAry as $cardno => $name):?>
                            <option value="<?php echo $cardno;?>"><?php echo $name;?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ml-3">
                    金額:
                    <input type="number" name="paymoney" value="0.01" step="0.01">
                </div>
                <div class="ml-3">
                    数量:
                    <input type="number" name="apitimes" value="1" step="1" min="1" max="100">
                </div>
                <div class="ml-3">
                    <button type="submit" name="submit" class="btn btn-primary">送出</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
<script>
    var msg = '<?php echo $msg;?>'
    $(function() {
        if (msg) {
            console.log(msg);
            alert(msg);
            window.location = window.location.href;
        }

        $("#target option:last").attr("selected", "selected");
    });
</script>
</body>
</html>