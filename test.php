<?php
// header('Content-Type: text/html; charset=UTF-8');

require_once('phpQuery-onefile.php');

else if ($message->text == '六本木') {
        $messageData = array(
            'type' => 'template',
            'altText' => '確認ダイアログ',
            'template' => array(
                'type' => 'carousel',
                'columns' => array(
                    array(
                        'thumbnailImageUrl' => 'https://tblg.k-img.com/restaurant/images/Rvw/75384/150x150_square_75384481.jpg',
                        'text' => 'エゴジーヌ',
                        'actions' => array(
                            array(
                                'type' => 'uri',
                                'label' => '食べログを表示',
                                'uri' => 'https://tabelog.com/tokyo/A1307/A130701/13175433/'
                            )
                        )
                    ),
    }