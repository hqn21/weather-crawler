# weather-crawler
[English](https://github.com/hqn21/weather-crawler/blob/main/README.md) | 繁體中文
## 關於專案
這是一個抓取未來 36 小時天氣預報的爬蟲 LINE Bot
### 使用資源
* LINE Messaging API SDK
## 開始部屬
跟隨指示以在本地端部屬此專案。
### 事先準備
* Apache
* PHP 7.2
### 安裝步驟
1. 複製此 repo
   ```sh
   git clone https://github.com/hqn21/weather-crawler.git
   ```
2. 下載 LINE Messaging API SDK
   ```sh
   composer require linecorp/line-bot-sdk
   ```
3. 在 `linebot.php` 中輸入您的 LINE@ 帳號資訊
   ```php
   $ChannelSecret = '';
   $ChannelAccessToken = '';
   ```
4. 在 `linebot.php` 中輸入您的 LINE API key
   ```php
   $CWBAPIKey = '';
   ```
## License
根據 MIT License 發布，查看 [LICENSE](https://github.com/hqn21/weather-crawler/blob/main/LICENSE) 以獲得更多資訊。
## 聯絡我
劉顥權 Haoquan Liu - [contact@haoquan.me](mailto:contact@haoquan.me)

專案連結：[https://github.com/hqn21/weather-crawler/](https://github.com/hqn21/weather-crawler/)
