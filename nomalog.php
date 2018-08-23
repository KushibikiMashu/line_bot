<?php

require_once('phpQuery-onefile.php');

$accessToken = 'アクセストークン';

// LINEから送られてきたメッセージを取得
$jsonString = file_get_contents('php://input');
error_log($jsonString);
$jsonObj      = json_decode($jsonString);
$sent_message = $jsonObj->{"events"}[0]->{"message"}->text;

if (is_null($sent_message)) {
	return;
}

$message = trim($sent_message);

// メッセージ内容のログを出力
$file = 'debug.txt';
$body = file_get_contents($file);
$body = $body . "\n" . $message;
file_put_contents($file, $body);

// スクレイピングの準備
$nomalog_url = 'https://nomalog.herokuapp.com';
$html = file_get_contents($nomalog_url);
$doc  = phpQuery::newDocument($html);

// 全てのエリア名を取得
$areas = [];
for ($i = 0; $i < 34; ++ $i) {
	$areas[] = $doc->find("#left_contents")->find(".select__contents__list:eq($i)")->find("a")->text();
}

// LINEメッセージで指定された地名を含む添字配列$areasのkey番号を取得
$index = $num = 0;
foreach ($areas as $key => $area) {

	if (mb_strpos($area, $message) !== false) {
		$num = $index + 1;
		break;
	}

	++ $index;
}

if (mb_strpos('一覧', $message) !== false || mb_strpos('エリア', $message) !== false) {

	// 対象エリア一覧を送る
	$area_names = array_values($areas);
	$area_list  = implode("\n", $area_names);
	$message_data = set_message_data($area_list);
	send_request($jsonObj, $message_data, $accessToken);

	return;
}

if (is_numeric($index) && $message !== '・' && $num !== 0) {

	// カフェ一覧のページを取得する
	$area_html = file_get_contents($nomalog_url . '/area' . $num);
	$area_doc  = phpQuery::newDocument($area_html);
	$cafe_list = $area_doc->find(".card__bottom.clearfix");

	// カフェの店舗数を数える。0店舗、或いは１０店舗より多ければループを抜ける
	$cafe_len = 0;
	while ($cafe_list->find("a:eq($cafe_len)")->attr("href") && $cafe_len < 10) {
		++ $cafe_len;
	}

	// そのエリアにまだ投稿がない場合
	if ($cafe_len === 0) {
		$text = 'そのエリアにはまだ投稿がありません😣' . "\n"
		        . '「渋谷・恵比寿・代官山」や「六本木・麻布・広尾」に投稿があります';
		$message_data = set_message_data($text);
		send_request($jsonObj, $message_data, $accessToken);
		return;
	}

	//カフェが投稿されている場合、最大10店舗を取得する
	$images = $names = $stars = $url = [];

	for ($i = 0; $i < $cafe_len; ++ $i) {
		$images[] = $cafe_list->find("a:eq($i) img")->attr("src");
		$names[]  = $cafe_list->find("a:eq($i) h4")->text();
		$star     = $cafe_list->find("a:eq($i) .card__item__bottom__count--rating")->text();
		$stars[]  = trim($star);

		// カフェの紹介ページのurlを取得
		$url[] = $cafe_list->find("a:eq($i)")->attr("href");
	}

	// カフェのカルーセルを作る
	$carousel_columns = [];

	for ($i = 0; $i < $cafe_len; ++ $i) {

		$carousel_columns[] =
			[
				'thumbnailImageUrl' => $images[$i],
				'title'             => $names[$i] . ' (🌟' . $stars[$i] . ')',
				'text'              => $areas[$index],
				'actions'           => [
					[
						'type'  => 'uri',
						'label' => 'Nomalogを見る',
						'uri'   => $nomalog_url . $url[$i]
					],
					[
						'type'  => 'uri',
						'label' => 'Googleで検索する',
						'uri'   => 'https://www.google.com/search?q=' . str_replace(' ', '+', $names[$i])
					]
				]
			];
	}

	// カルーセルタイプを使用する
	$message_data = [
		'type'     => 'template',
		'altText'  => 'カフェ情報一覧',
		'template' => [
			'type'    => 'carousel',
			'columns' => $carousel_columns
		]
	];

	error_log($carousel_columns['actions']['0']['uri']);
	send_request($jsonObj, $message_data, $accessToken);

	return;
}

// 該当するエリアがない場合
$text = '渋谷・恵比寿・六本木などNomalogに記載の地名を入力してください😅' . "\n"
          . $nomalog_url;
$message_data = set_message_data($text);
send_request($jsonObj, $message_data, $accessToken);

error_log($message_data[0]);

function set_message_data($text) {
	$message_data = [
		'type' => 'text',
		'text' => $text
	];

	return $message_data;
}

function send_request($jsonObj, $message_data, $accessToken) {
	// リプライ用のワンタイムトークンを取得
	$replyToken = $jsonObj->{"events"}[0]->{"replyToken"};
	$response   = [
		'replyToken' => $replyToken,
		'messages'   => [$message_data]
	];

	error_log(json_encode($response));

	// curlでLINEに結果を送信する
	// HACK オプションは配列で指定する
	$ch = curl_init('https://api.line.me/v2/bot/message/reply');
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($response));
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		'Content-Type: application/json; charser=UTF-8',
		'Authorization: Bearer ' . $accessToken
	));

	$result = curl_exec($ch);
	error_log($result);
	curl_close($ch);
}