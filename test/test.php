<?php

require_once('../phpQuery-onefile.php');

// $message = '渋谷';

// Jsonの受け取り
$jsonString = file_get_contents('message.json');
error_log($jsonString);
$jsonObj = json_decode($jsonString);

// メッセージイベントのメッセージを取り出す
$message = $jsonObj->{"events"}[0]->{"message"}->text;

var_dump($message);

$file = 'debug.txt';
$body = file_get_contents($file);
$body = $body . "\n" . '$messageを受け取った直後:' . $message;
file_put_contents($file, $body);


if(!isset($message) && empty($message)) {
    return;
}

var_dump($message);

$accept_message = trim($message);

// 一時的に発行されるリプライトークンを取得
// $replyToken = $jsonObj->{"events"}[0]->{"replyToken"};

// メッセージのログを取る
$file = 'debug.txt';
$body = file_get_contents($file);
$body = $body . "\n" . $accept_message;
file_put_contents($file, $body);

// スクレイピング
$html = file_get_contents('https://nomalog.herokuapp.com/');
$doc  = phpQuery::newDocument($html);

$areas = [];

// 全てのエリアを取得
for ($i = 0; $i < 34; ++$i) {
    // 変数$iが使用されているので、''ではなく""を使う
    $areas[] = $doc->find("#left_contents")->find(".select__contents__list:eq($i)")->find("a")->text();
 }

// 配列$areasで使えるindexを作成
$index = $num = 0;

// 本番用
foreach($areas as $key => $area) {
    // 送信された地名がある配列$areasのkey番号を取得
    if (mb_strpos($area, $accept_message) !== false) {
        $num = $index + 1;
        break;
    }

    ++$index;
}

var_dump($message);
var_dump($num);
var_dump(mb_strpos('エリア', $accept_message));

if(mb_strpos('一覧', $accept_message) !== false || mb_strpos('エリア', $accept_message) !== false) {

    $area_list = "";

    foreach ($areas as $key => $area) {
        $area_list .= $area . "\n";
    }

        var_dump(trim($area_list));


    $messageData = [
        'type' => 'text',
        'text' => $area_list
    ];

    var_dump($messageData);

} else if (is_numeric($index) && $accept_message !== '・' && $num !== 0) {
// 送られてきたメッセージの中身からレスポンスのタイプを選択


    $area_html = file_get_contents('https://nomalog.herokuapp.com/area' . $num);
    $area_doc = phpQuery::newDocument($area_html);

    // 該当する店がある場合

    // カフェ一覧のページを取得する
    $cafe_list = $area_doc->find(".card__bottom.clearfix");

    // カフェの店舗数を数える。カフェの投稿がないか、１０店舗より多ければループを抜ける
    $cafe_len = 0;
    while ($cafe_list->find("a:eq($cafe_len)")->attr("href") && $cafe_len < 10) {
        ++$cafe_len;
    }

    if ($cafe_len === 0) {
    // そのエリアにカフェの投稿がない場合
        $messageData = [
            'type' => 'text',
            'text' => 'そのエリアにはまだ投稿がありません😣' . "\n"
                         . '「渋谷・恵比寿・代官山」や「六本木・麻布・広尾」に投稿があります'
        ];
        
    } else {

        //カフェが投稿されている場合、10件以下で取得する
        $images = $names = $stars = $url = [];

        for ($i = 0; $i < $cafe_len; ++$i ) {
          $images[] = $cafe_list->find("a:eq($i) img")->attr("src");
          $names[]  = $cafe_list->find("a:eq($i) h4")->text();
          $star     = $cafe_list->find("a:eq($i) .card__item__bottom__count--rating")->text();
          $stars[]  = trim($star);

          // カフェの紹介ページのurlを取得
          $url[] = $cafe_list->find("a:eq($i)")->attr("href");
        }

        // お店のカルーセルを作る
        $carousel_columns = [];

        for ($i = 0; $i < $cafe_len; ++$i ) {

            $carousel_columns[] = 
                [
                    'thumbnailImageUrl' => $images[$i],
                    'title'   => $names[$i] . ' (🌟' . $stars[$i] . ')',
                    'text'    => $areas[$index],
                    'actions' => [
                        [
                            'type'  => 'uri',
                            'label' => 'Nomalogを見る',
                            'uri'   => 'https://nomalog.herokuapp.com' . $url[$i]
                        ],
                        [
                            'type'  => 'uri',
                            'label' => 'Googleで検索する',
                            'uri'  => 'https://www.google.com/search?q=' . str_replace(' ', '+', $names[$i])
                        ]
                    ]
                ];
        }
        // カルーセルタイプ
        $messageData = [
            'type' => 'template',
            'altText' => 'カルーセル',
            'template' => [
                'type' => 'carousel',
                'columns' => $carousel_columns
            ]
        ];
    }

    var_dump($messageData);
} else {

    // 該当するエリアがない場合
    $messageData = [
        'type' => 'text',
        'text' => '渋谷・恵比寿・六本木などNomalogに記載の地名を入力してください😅' . "\n"
                     . 'https://nomalog.herokuapp.com'
    ];
}