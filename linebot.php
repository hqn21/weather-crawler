<?php

require 'vendor/autoload.php';

use LINE\LINEBot;
use LINE\LINEBot\HTTPClient\CurlHTTPClient;
use LINE\LINEBot\MessageBuilder\TextMessageBuilder;

date_default_timezone_set("Asia/Taipei");

// 讀取資訊 
$HttpRequestBody = file_get_contents('php://input');
$HeaderSignature = $_SERVER['HTTP_X_LINE_SIGNATURE'];

// 驗證來源是否是LINE官方伺服器 
$Hash = hash_hmac('sha256', $HttpRequestBody, $ChannelSecret, true);
$HashSignature = base64_encode($Hash);
if ($HashSignature != $HeaderSignature) {
    die('hash error!');
}

http_response_code(200);

function getJsonDataFromURL($url) {
    $ch = curl_init(); // 初始化 cURL
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 設定回傳值不會印出
    curl_setopt($ch, CURLOPT_URL, $url); // 設定 URL
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 5000); // 設置連線超時
    curl_setopt($ch, CURLOPT_FAILONERROR, true); // 開啟報錯
    $result = curl_exec($ch); // 執行 cURL
    $curl_errno = curl_errno($ch); // 報錯代碼
    $curl_error = curl_error($ch); // 報錯訊息
    if ($curl_errno) {
        error_log("cURL 執行錯誤[" . $curl_errno . "]：" . $curl_error . ", " . $url . "\n");
        curl_close($ch); // 關閉 cURL
        return false;
    }
    else {
        $jsonData = json_decode($result, true); // 解析
        curl_close($ch); // 關閉 cURL
        return $jsonData;
    } 
}

// 設定 LINE Bot Token 
$ChannelSecret = '';
$ChannelAccessToken = '';

// 設定 API KEY
$CWBAPIKey = ''; // 中央氣象局

$httpClient = new CurlHTTPClient($ChannelAccessToken);
$bot = new LINEBot($httpClient, ['channelSecret' => $ChannelSecret]);

$dataBody = json_decode($HttpRequestBody, true); // 解析
foreach ($dataBody['events'] as $Events) { // 逐一處理事件 
    if ($Events['type'] == 'message') { // 訊息事件
        $msgType = $Events['message']['type'];
        if ($msgType == 'text') { // 處理文字訊息
            $msgText = $Events['message']['text'];
            $msgTextLength = strlen(utf8_decode($msgText));
            if(mb_substr($msgText, $msgTextLength - 2, $msgTextLength, 'utf-8') == '天氣') { // 判斷最後兩次是否為「天氣」
                $cityRaw = mb_substr($msgText, 0, $msgTextLength - 2, 'utf-8');
                $city = str_replace('台', '臺', $cityRaw);
                $listCity = ['基隆市', '臺北市', '新北市', '桃園市', '新竹市', '臺中市', '嘉義市', '臺南市', '高雄市', '新竹縣', '苗栗縣', '彰化縣', '南投縣', '雲林縣', '屏東縣', '宜蘭縣', '花蓮縣', '臺東縣', '澎湖縣', '金門縣', '連江縣', '嘉義縣'];
                $listShii = ['基隆', '臺北', '新北', '桃園', '新竹', '臺中', '嘉義', '臺南', '高雄'];
                $listShan = ['新竹', '苗栗', '彰化', '南投', '雲林', '屏東', '宜蘭', '花蓮', '臺東', '澎湖', '金門', '連江', '嘉義'];
                if(in_array($city, $listShii)){
                    $city = $city . '市';
                }
                else if(in_array($city, $listShan)){
                    $city = $city . '縣';
                }
                if(in_array($city, $listCity)) {
                    $weatherJson = getJsonDataFromURL('https://opendata.cwb.gov.tw/api/v1/rest/datastore/F-C0032-001?Authorization=' . $CWBAPIKey . '&format=JSON&locationName=' . $city);
                    if ($weatherJson == false) {
                        $replyMsg = new TextMessageBuilder('取得資料超時，請重新嘗試。');
                    }
                    else {
                        // 天氣資料處理
                        $weatherData = array();
                        for ($i = 0; $i <= 2; $i++) {
                            $weatherData[$i] = array();
                            for ($k = 0; $k <= 4; $k++) {
                                array_push($weatherData[$i], $weatherJson['records']['location'][0]['weatherElement'][$k]['time'][$i]['parameter']['parameterName']);
                            }
                            array_push($weatherData[$i], $weatherJson['records']['location'][0]['weatherElement'][0]['time'][$i]['startTime']); //$weatherData[$i][5]
                            array_push($weatherData[$i], $weatherJson['records']['location'][0]['weatherElement'][0]['time'][$i]['endTime']); //$weatherData[$i][6]
                            array_push($weatherData[$i], $weatherJson['records']['location'][0]['weatherElement'][0]['time'][$i]['parameter']['parameterValue']); //$weatherData[$i][7]
                        }
                        $replyMsg = new TextMessageBuilder("[" . $city . "]\n\n時間範圍：" . $weatherData[0][5] . " ~ " . $weatherData[0][6] . "\n天氣狀況：" . $weatherData[0][0] . "\n溫度狀況：" . $weatherData[0][2] . " ~ " . $weatherData[0][4] . "\n感受狀況：" . $weatherData[0][3] . "\n天氣編號：" . $weatherData[0][7] . "\n\n時間範圍：" . $weatherData[1][5] . " ~ " . $weatherData[1][6] . "\n天氣狀況：" . $weatherData[1][0] . "\n溫度狀況：" . $weatherData[1][2] . " ~ " . $weatherData[1][4] . "\n感受狀況：" . $weatherData[1][3] . "\n天氣編號：" . $weatherData[1][7] . "\n\n時間範圍：" . $weatherData[2][5] . " ~ " . $weatherData[2][6] . "\n天氣狀況：" . $weatherData[2][0] . "\n溫度狀況：" . $weatherData[2][2] . " ~ " . $weatherData[2][4] . "\n感受狀況：" . $weatherData[2][3] . "\n天氣編號：" . $weatherData[2][7]);
                    }
                }
                else {
                    $replyMsg = new TextMessageBuilder('請輸入正確的縣市名。');
                }
                $response = $bot->replyMessage($Events['replyToken'], $replyMsg);
                if(!$response->isSucceeded()) {
                    error_log($response->getHTTPStatus() . ' ' . $response->getRawBody());
                }
            }
            else {
                $response = $bot->replyMessage($Events['replyToken'], new TextMessageBuilder('使用方法：[縣市名]天氣'));
                if(!$response->isSucceeded()) {
                    error_log($response->getHTTPStatus() . ' ' . $response->getRawBody());
                }
            }
        }
    }
}
