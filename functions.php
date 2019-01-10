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
    
    for ($i = 0; $i <= 25; $i++) {
        $res_proxy = curl($url_proxy, null, null, false, null, false, null, 10);

        $res_proxy = json_decode($res_proxy, true);
        $proxy     = $res_proxy['proxy'];   
        if(!stristr($proxy, ':')) {
            echo "Proxy invalido: {$proxy}\n";
            continue;           
        }

        $res = curl($url, null, null, true, null, false, $proxy, 3);

        if(strlen($res) > 10) {
            if(!stristr($res, '99;')){
                echo "Proxy invalido: {$proxy} - acesso invalido\n";
                continue;
            }
            return $proxy;
            break;
        }
        else {
            echo "Proxy invalido: {$proxy}\n";
            continue;
        }
    }

    return false;
}

