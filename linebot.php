<?php

require 'vendor/autoload.php';

use LINE\LINEBot;
use LINE\LINEBot\HTTPClient\CurlHTTPClient;
use LINE\LINEBot\MessageBuilder\TextMessageBuilder;

date_default_timezone_set("Asia/Taipei");

// 設定Token 
$ChannelSecret = 'CHANNEL_SECRET';
$ChannelAccessToken = 'CHANNEL_ACCESS_TOKEN';

// 設定API KEY
$weatherAPIKey = 'WEATHER_API_KEY';
$aqiAPIKey = 'AQI_API_KEY';

// 讀取資訊 
$HttpRequestBody = file_get_contents('php://input');
$HeaderSignature = $_SERVER['HTTP_X_LINE_SIGNATURE'];

// 驗證來源是否是LINE官方伺服器 
$Hash = hash_hmac('sha256', $HttpRequestBody, $ChannelSecret, true);
$HashSignature = base64_encode($Hash);
if ($HashSignature != $HeaderSignature) {
    die('hash error!');
}
$httpClient = new CurlHTTPClient($ChannelAccessToken);
$bot = new LINEBot($httpClient, ['channelSecret' => $ChannelSecret]);

function getJsonDataFromURL($url)
{
    // 初始化 cURL
    $ch = curl_init();
    // 設定回傳值不會印出
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // 設定 URL
    curl_setopt($ch, CURLOPT_URL, $url);
    // 設置超時
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 1000);
    curl_setopt($ch, CURLOPT_TIMEOUT_MS, 5000);
    // 開啟報錯
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    // 執行 cURL
    $result = curl_exec($ch);
    // 處理報錯
    $curl_errno = curl_errno($ch);
    $curl_error = curl_error($ch);
    if ($curl_errno) {
        error_log("cURL 執行錯誤[" . $curl_errno . "]：" . $curl_error . ", " . $url . "\n");
        // 關閉 cURL
        curl_close($ch);
        return false;
    } else {
        // 處理 Json 並返回值
        $jsonData = json_decode($result, true);
        // 關閉 cURL
        curl_close($ch);
        return $jsonData;
    }
}

// 解析
$dataBody = json_decode($HttpRequestBody, true);

// 逐一處理事件 
foreach ($dataBody['events'] as $Events) {

    // 訊息事件
    if ($Events['type'] == 'message') {

        $msgType = $Events['message']['type'];

        // 處理文字訊息
        if ($msgType == 'text') {

            $msgText = $Events['message']['text'];

            // 判斷用戶輸入的文字最後兩個字為「天氣」
            if (substr($msgText, -6) == '天氣' || strlen($msgText) > 1) {
                $city = str_replace('天氣', '', $msgText);
                if (!$city) {
                    $replyMsg = new TextMessageBuilder('請輸入要查詢的縣市名稱。');
                } else {
                    $city = str_replace('台', '臺', $city);
                    $listCity = ['基隆市', '臺北市', '新北市', '桃園市', '新竹市', '臺中市', '嘉義市', '臺南市', '高雄市', '新竹縣', '苗栗縣', '彰化縣', '南投縣', '雲林縣', '屏東縣', '宜蘭縣', '花蓮縣', '臺東縣', '澎湖縣', '金門縣', '連江縣', '嘉義縣'];
                    $listShii = ['基隆', '臺北', '新北', '桃園', '新竹', '臺中', '嘉義', '臺南', '高雄'];
                    $listShan = ['新竹', '苗栗', '彰化', '南投', '雲林', '屏東', '宜蘭', '花蓮', '臺東', '澎湖', '金門', '連江', '嘉義'];

                    if (!(strpos($city, '市') || strpos($city, '縣'))) {
                        if (in_array($city, $listShii)) {
                            $city = $city . '市';
                        } else if (in_array($city, $listShan)) {
                            $city = $city . '縣';
                        }
                    }

                    if (in_array($city, $listCity)) {

                        $weatherJson = getJsonDataFromURL('https://opendata.cwb.gov.tw/api/v1/rest/datastore/F-C0032-001?Authorization=' . $weatherAPIKey . '&format=JSON&locationName=' . $city);
                        $aqiJson = getJsonDataFromURL('https://data.epa.gov.tw/api/v1/aqx_p_432?offset=0&limit=1000&api_key=' . $aqiAPIKey);

                        if ($weatherJson == false || $aqiJson == false) {
                            $replyMsg = new TextMessageBuilder('取得資料超時，請重新嘗試。');
                            $response = $bot->replyMessage($Events['replyToken'], $replyMsg);
                            if ($response->isSucceeded()) {
                                break;
                            }
                            // 傳送訊息失敗
                            error_log("LineBot 錯誤：" . $response->getHTTPStatus() . " " . $response->getRawBody() . "\n");
                            break;
                        }

                        // 天氣資料處理
                        $weatherIcon = array('1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '15');
                        $weatherData = array();

                        for ($i = 0; $i <= 2; $i++) {
                            $weatherData[$i] = array();
                            for ($k = 0; $k <= 4; $k++) {
                                array_push($weatherData[$i], $weatherJson['records']['location'][0]['weatherElement'][$k]['time'][$i]['parameter']['parameterName']);
                            }
                            array_push($weatherData[$i], $weatherJson['records']['location'][0]['weatherElement'][0]['time'][$i]['startTime']); //$weatherData[$i][5]
                            array_push($weatherData[$i], $weatherJson['records']['location'][0]['weatherElement'][0]['time'][$i]['endTime']); //$weatherData[$i][6]
                            if (in_array($weatherJson['records']['location'][0]['weatherElement'][0]['time'][$i]['parameter']['parameterValue'], $weatherIcon)) {
                                array_push($weatherData[$i], $weatherJson['records']['location'][0]['weatherElement'][0]['time'][$i]['parameter']['parameterValue']); //$weatherData[$i][7]
                            } else {
                                array_push($weatherData[$i], '0'); //$weatherData[$i][7]
                            }
                        }

                        if (substr($weatherJson['records']['location'][0]['weatherElement'][0]['time'][1]['endTime'], -8, 2) == '18') {
                            $folderOption = array('night', 'day', 'night');
                            $dateOption = array('明日白天', '明日晚上');
                        } else {
                            $folderOption = array('day', 'night', 'day');
                            $dateOption = array('今晚明晨', '明日白天');
                        }

                        // 空氣品質資料處理
                        $num = 0;
                        $found = 0;
                        while ($found != 1) {
                            if (!$aqiJson['records'][$num]['County']) {
                                $found = 1;
                            }
                            if ($aqiJson['records'][$num]['County'] != $city) {
                                $num++;
                            } else {
                                $aqiData = $aqiJson['records'][$num];
                                $found = 1;
                            }
                        }
                        $aqiIcon = array(
                            '良好' => 1,
                            '普通' => 2,
                            '對敏感族群不健康' => 3,
                            '對所有族群不健康' => 4,
                            '非常不健康' => 5,
                            '危害' => 6
                        );

                        /*

                        共有三個時間段

                        $city               - 縣市名稱
                        $weatherData[][0]   - 天氣狀況
                        $weatherData[][1]   - 降雨機率
                        $weatherData[][2]   - 最低溫度
                        $weatherData[][3]   - 感受狀況
                        $weatherData[][4]   - 最高溫度
                        $weatherData[][5]   - 前綴時間
                        $weatherData[][6]   - 後綴時間
                        $weatherData[][7]   - 天氣編號

                        */

                        $replyMsg = new TextMessageBuilder("[" . $city . "]\n\n時間範圍：" . $weatherData[0][5] . " ~ " . $weatherData[0][6] . "\n天氣狀況：" . $weatherData[0][0] . "\n溫度狀況：" . $weatherData[0][2] . " ~ " . $weatherData[0][4] . "\n感受狀況：" . $weatherData[0][3] . "\n天氣編號：" . $weatherData[0][7] . "\n\n時間範圍：" . $weatherData[1][5] . " ~ " . $weatherData[1][6] . "\n天氣狀況：" . $weatherData[1][0] . "\n溫度狀況：" . $weatherData[1][2] . " ~ " . $weatherData[1][4] . "\n感受狀況：" . $weatherData[1][3] . "\n天氣編號：" . $weatherData[1][7] . "\n\n時間範圍：" . $weatherData[2][5] . " ~ " . $weatherData[2][6] . "\n天氣狀況：" . $weatherData[2][0] . "\n溫度狀況：" . $weatherData[2][2] . " ~ " . $weatherData[2][4] . "\n感受狀況：" . $weatherData[2][3] . "\n天氣編號：" . $weatherData[2][7]);
                    } else {
                        $replyMsg = new TextMessageBuilder('請輸入正確的縣市名稱。');
                    }
                }
                $response = $bot->replyMessage($Events['replyToken'], $replyMsg);
                if ($response->isSucceeded()) {
                    break;
                }
                // 傳送訊息失敗
                error_log("LineBot 錯誤[" . $Events['replyToken'] . "]：" . $response->getHTTPStatus() . " " . $response->getRawBody() . "\n");
            } else if ($Events['source']['type'] == 'user') {
                $response = $bot->replyMessage($Events['replyToken'], new TextMessageBuilder("您似乎輸入了錯誤的縣市名，請確認後重新輸入，或輸入\n「/help」以獲得幫助列表。"));
            }
        }
    }
}
