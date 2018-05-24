<?php
// header('Content-Type: text/html; charset=UTF-8');

require_once('phpQuery-onefile.php');


// スクレイピング
$html = file_get_contents('https://nomalog.herokuapp.com/');
$doc  = phpQuery::newDocument($html);


$message->text = PHP_EOL;

$accept_message = trim($message->text);


$areas = [];

// 全てのエリアを取得
for ($i = 0; $i < 34; ++$i) {
    // 変数$iが使用されているので、""を使う
    $areas[] = $doc->find("#left_contents")->find(".select__contents__list:eq($i)")->find("a")->text();
 }

// 配列$areasで使うindexを作成
$index = $num = 0;


// $message->text = '銀座';
var_dump($message->text);

// 本番用
foreach($areas as $key => $area) {
    // var_dump($area);
    if (mb_strpos($area, $message->text) !== false) {
        // 送信された地名がある配列$areasのkey番号を取得
        $num = $index + 1;
        break;
    }

    ++$index;
}

var_dump($index);
var_dump($num);

// 送られてきたメッセージの中身からレスポンスのタイプを選択
if (is_numeric($index) && $message->text !== '・' && $num !== 0) {

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


    var_dump('$cafe_len:');var_dump($cafe_len);

    // そのエリアにカフェの投稿がない場合
    if ($cafe_len === 0) {

        $messageData = [
            'type' => 'text',
            'text' => 'ごめんなさい！まだその場所には投稿がありません😅'
        ];

        var_dump($messageData);

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
                    'title'   => $names[$i] . ' (' . $stars[$i] . ')',
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
        'text' => 'ごめんなさい！Nomalogに記載の地名を入力してください😅'
    ];
        var_dump($messageData);

}

