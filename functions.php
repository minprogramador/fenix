<?php


function validaCPF($cpf) {
    $cpf = preg_replace( '/[^0-9]/is', '', $cpf );
    if (strlen($cpf) != 11) {
        return false;
    }
    
    if (preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }

    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf{$c} * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf{$c} != $d) {
            return false;
        }
    }
    return true;
}

function curl($url, $cookies=null, $post=null, $header=true, $referer=null, $follow=false, $proxy=null, $timeout=8) {   
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, $header);
    if ($cookies != null) curl_setopt($ch, CURLOPT_COOKIE, $cookies);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; rv:12.0) Gecko/20100101 Firefox/12.0');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $follow);
    if(isset($referer)) {
        curl_setopt($ch, CURLOPT_REFERER,$referer);
    }else{
        curl_setopt($ch, CURLOPT_REFERER,$url);
    }
    
    if($post != null){
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post); 
    }
    
    if($proxy != null){
        curl_setopt($ch, CURLOPT_PROXY, $proxy);
    }else{
        //die('coloca rede cabecao.');
    }
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE); 
    curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout); 
    curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        
    $res = curl_exec( $ch);
    curl_close($ch); 
    return ($res);
}

function getRemoteProxy($url) {

    $url_proxy = 'http://falcon.proxyrotator.com:51337/?apiKey=VYZK892qeodPDML7fU6BFAjGtQuh4HWc&country=br&port=3128';
    //$res_proxy = curl($url_proxy, null, null, false, null, false, null, 10);

    $Curl = new Curl();
    $Curl->setTimeout(8);
    $Curl->add($url_proxy)->add($url_proxy)->add($url_proxy)->add($url_proxy)->add($url_proxy);
    $docsok = $Curl->run();
    $prlistnew = [];
    if(count($docsok['body']) > 0) {
        for ($i = 0; $i <= count($docsok['body']); $i++) {
            if(isset($docsok['body'][$i])){
                $res = $docsok['body'][$i];
                if(stristr($res, 'proxy":')) {
                    $res = json_decode($res);
                    if(isset($res->proxy) && stristr($res->proxy, ':')) {
                        array_push($prlistnew, $res->proxy); //add proxy a lista....
                    }
                }
            }
        }
    }

    unset($Curl);
    $Curl = new Curl();
    $Curl->setTimeout(3);

    foreach($prlistnew as $prcheck){
        if(stristr($prcheck, ':')) {
            $Curl->add($url, null, null, $prcheck);
        }
    }

    $checknpr = $Curl->run();

    if(count($checknpr['body']) > 0) {
        for ($i = 0; $i <= count($checknpr['body']); $i++) {
            if(isset($checknpr['body'][$i])){
                $info = $checknpr['info'][$i];
                $proxyok = $info['primary_ip'] . ':' . $info['primary_port'];
                $res = $checknpr['body'][$i];
                if(!stristr($res, '99;')) {

                    if (($key = array_search($proxyok, $prlistnew)) !== false) {
                        unset($prlistnew[$key]);
                    }
                }
            }
        }
    }
    unset($Curl);
    return $prlistnew;
}

function sendMsg($msg, $chat_id, $telegram) {
    //echo $msg . PHP_EOL;
     $content = array( 'chat_id' => $chat_id, 'text' => $msg );
     $telegram->sendMessage( $content );
}


function CheckText($text, $chat_id, $telegram){
    $isCreate = preg_match('/create .*/', $text) ? true : false;
    $Start    = preg_match('/start .*/', $text) ? true : false;
    $Stop     = preg_match('/stop .*/', $text) ? true : false;
    $create   = preg_match('/new (.*) (.*)/', $text) ? true : false;
    if(!$create)
        $create = preg_match('/new (.*)/', $text) ? true : false;
    switch ( $text ) {
        case '/start':
            $option = array(
                array( "start tabchi", "stop tabchi" )
            );
            $keyb    = $telegram->buildKeyBoard( $option, false, true );
            $content = array( 'chat_id' => $chat_id,'reply_markup' => $keyb, 'text' => "برای ساخت ربات تبچی دستور زیر را ارسال کنید:
            new (phone number) (password)
            example:
            new 989101961375 farhad07ss
            " );
            $telegram->sendMessage( $content );
            break;
        case $create:
            preg_match('/new (.*) (.*)/', $text, $match);
            if(!isset($match[2])){
                preg_match('/new (.*)/', $text, $match);
                $cmd = "./all ". $match[1];
            }else{
                $cmd = "./all ". $match[1] . " " . $match[2];
            }
            
            $content = array( 'chat_id' => $chat_id, 'text' => "
            صبور باشید!
            " );
            $telegram->sendMessage( $content );

            //print_r(shell_exec($cmd));
            break;
        case '/start':
            $option = array(
                array('start all', 'start tabchi', 'create tabchi')
            );
            // Get the keyboard
            $keyb    = $telegram->buildKeyBoard($option, false, true);
            $content = array( 'chat_id' => $chat_id, 'reply_markup' => $keyb, 'text' => "salam admin" );
            $telegram->sendMessage( $content );
            
            break;
        case $Stop:
            preg_match('/stop (.*)/', $text, $match);
            if($match[1] == "tabchi"){
                $output = '';//exec("cd ../tabchi/ && ./bot stopall");
                print_r($output);
                $content = array( 'chat_id' => $chat_id, 'text' => "انجام شد. \n" . $output );
                $telegram->sendMessage( $content );
            }
            break;
        case $Start:
            preg_match('/start (.*)/', $text, $match);
            if($match[1] == "tabchi"){
                $botp = 'auto';
                $output = '';//exec("cd ../tabchi/ && tmux kill-session -t ". $botp);
                $output = '';//exec("cd ../tabchi/ && tmux new-session -d -s ". $botp . " ./bot autolaunch && tmux detach -s ". $botp);
                print_r($output);
                $content = array( 'chat_id' => $chat_id, 'text' => "انجام شد. \n" . $output );
                $telegram->sendMessage( $content );
            }
            if($match[1] == "api"){
                $bots = glob("bot*");

                $str = '';
                foreach($bots as $bot){
                    $i = (int)str_replace('bot', '', $bot);
                    
                    $botp = 'rob-'. $i;
                    $output = '';//exec("cd ../Telegram/bot/bot". $i ."/ && tmux kill-session -t ". $botp);
                    $output = '';//exec("cd ../Telegram/bot/bot". $i ."/ && tmux new-session -d -s ". $botp . " php index.php ". $botp . " && tmux detach -s ". $botp);
                    $str .= 'ربات شماره '.$i.' راه اندازی شد.' . "\n" . $output ."\n";
                }
                $content = array( 'chat_id' => $chat_id, 'text' => "انجام شد. \n" . $str );
                $telegram->sendMessage( $content );
            }
            
        
            break;
    }
}
