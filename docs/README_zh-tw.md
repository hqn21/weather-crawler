# weather-crawler
基本天氣爬蟲 LINE Bot 架構。

## 前置套件
```bash
composer require linecorp/line-bot-sdk  # LINE Messaging API SDK
```

## 變數說明
```php
// 共有三個時間段
$city               // 縣市名稱
$weatherData[][0]   // 天氣狀況
$weatherData[][1]   // 降雨機率
$weatherData[][2]   // 最低溫度
$weatherData[][3]   // 感受狀況
$weatherData[][4]   // 最高溫度
$weatherData[][5]   // 前綴時間
$weatherData[][6]   // 後綴時間
$weatherData[][7]   // 天氣編號
```

## 應用實例
[雲朵國王](https://lin.ee/t69S2TM)
