<?php

declare(strict_types = 1);

date_default_timezone_set('America/Sao_Paulo');

use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use \React\EventLoop\Factory;
use \Legionth\React\Http\Rest\Server;
use \React\Socket\Server as ServerSock;
use \React\Http\Response;
use \Psr\Http\Message\ServerRequestInterface as Request;
require_once 'vendor/autoload.php';
require('Curl.php');
require('functions.php');
require("Telegram.php");


// define('A_USER_CHAT_ID', '427583453');
// define('A_GROUP_CHAT_ID', '-377734581');

$status  = true;
$start   = date("Y-m-d H:i:s");
$update  = date("Y-m-d H:i:s");
$totalproxy = 0;
$proxyrm = [];
$proxy   = [];
$url     = 'YUhSMGNITTZMeTkzZDNjdWMyTndZMjVsZEM1amIyMHVZbkl2UVVOVFVFNUZWQzlRY205bmNtRnR';
$url    .= 'ZWE12VTBsQlUxQlRRMEV1WVdwaGVDNXdhSEEvYkd0ZlUyUnZZejFEVUVZbWJHdGZUbVJ2WXowPQ';
$bot_id = '714388705:AAH8z02IcJrwAdWZNN8GPvE6gfG7-XU03Qo';
$admins = [427583453, -377734581];//[93077939, 231812624];
$chatId = '-377734581';


$telegram = new Telegram($bot_id);

$loop   = Factory::create();
$server = new Server();


$server->get('/cpf/:cpf', function (Request $request, callable $next) use (&$status, $url, &$proxy) {
    $cpf      = $request->getQueryParams()['cpf'];
    $doccheck = false;
	    
    if(is_numeric($cpf) && strlen($cpf) === 11 && validaCPF($cpf) === true){
    	$doccheck = true;
    }else{
    	$doccheck = false;
    }

    if(count($proxy) == 0){
	    $body = json_encode([
	    	"rede"   => [],
	    	"cpf"    => $cpf,
	    	"check"  => $doccheck,
	    	"status" => false,
	    	"dados"  => ["msg"=> "Sem rede, aguarde alguns instantes..."]
	    ]);
	    //mostrar log de erro ao consultar?
    	return new Response(200, array('Content-Type' => "application/json" ), $body);
    }

	$keyProxy = array_rand($proxy);
	$proxyOk  = $proxy[$keyProxy];

	$url   = base64_decode(base64_decode($url)).$cpf;
	$dados = curl($url, null, null, true, null, false, $proxyOk);

	if(stristr($dados, '200 OK')) {
		$body = explode("\n\r\n", $dados);
		if(count($body) > 2){
			$body = $body[2];
		}else{
			$body = $body[1];
		}

		if(stristr($body, ';')){
			$body = explode(';', $body);
			$cod  = trim(rtrim(str_replace(["\t", "  "], '', $body[0])));
			$nome = trim(rtrim(str_replace(["\t", "  "], '', $body[1])));
			$nasc = trim(rtrim(str_replace(["\t", "  "], '', $body[2])));
			$mae  = trim(rtrim(str_replace(["\t", "  "], '', $body[3])));
			$dados = [
				'cod'  => $cod,
				'nome' => $nome,
				'nasc' => $nasc,
				'mae'  => $mae
			];
			$status = true;
		}else{
			$status = false;
			$dados  = [];
		}
	}else{
		$status = false;
		$dados = ['msg'=> 'nada encontrado'];		
	}

    $body = json_encode([
    	"rede"   => $proxyOk,
    	"cpf"    => $cpf,
    	"check"  => $doccheck,
    	"status" => $status,
    	"dados"  => $dados
    ]);

    #mostrar log de consulta ok ???
    return new Response(200, array('Content-Type' => "application/json" ), $body);
});

$server->get('/config', function (Request $request, callable $next) use (&$status, $url, &$proxy, &$update, &$totalproxy, &$proxyrm, &$start) {

    $body = json_encode([
    	"rede"   => $proxy,
    	"contAtivo" => count($proxy),
    	"contInativo" => count($proxyrm),
    	"countTotal"  => $totalproxy,
    	"start"  => $start,
    	"status" => $status,
    	"update" => $update
    ]);

    return new Response(200, array('Content-Type' => "application/json" ), $body);
});

// $server->get('/logs', function (Request $request, callable $next) {
// 	$logs = file_get_contents('logs.txt');

//     return new Response(200, array('Content-Type' => "text/plain" ), $logs);
// });


// $server->post('/config/:status', function (Request $request, callable $next) use (&$status) {

//     $nstatus = $request->getQueryParams()['status'];
//     $status  = $nstatus;

//     return new Response(200, array(), 'You said: ' . $status . ' hello > ');

// });

$server->post('/proxy/:proxy', function (Request $request, callable $next) use (&$proxy) {

    $nproxy  = $request->getQueryParams()['proxy'];
    $proxy[] = $nproxy;

    return new Response(200, array(), 'proxy: ' . $nproxy . ' ');

});

$loop->addPeriodicTimer(60.000, function () use (&$proxy, $url, &$proxyrm, $chatId, $telegram) {
	$cpf  = '';
	$url  = base64_decode(base64_decode($url));
	$url  = explode('?', $url);
	$url  = $url[0];
	
	if(count($proxy) > 0) {
		$msg = "Iniciando rotina de testes em: ".date("m/d/Y H:i:s").", total proxys: ".count($proxy);
		sendMsg($msg, $chatId, $telegram);

		$Curl = new Curl();
		$Curl->setTimeout(5);
		
		foreach($proxy as $pr) {
			if(!stristr($pr, ':')){
				continue;
			}else{
				$Curl->add($url, null, null, $pr);
			}
		}

		$resProxys = $Curl->run();
		if (array_key_exists("info", $resProxys)) {
			$totalpr = count($resProxys['info']);
		}else{

		}

		for ($i = 0; $i <= $totalpr; $i++) {

			if(!isset($resProxys['info'][$i])) {
				continue;
			}

		    $info    = $resProxys['info'][$i];
			$nproxy  = $info['primary_ip'].':'.$info['primary_port'];
			$msg = '';
			if(strlen($nproxy) > 8){
			    $headers = $resProxys['headers'][$i];
			    $body    = $resProxys['body'][$i];
			    if(stristr($body, '99;')){

					$msg .= ">Proxy: $nproxy - Online..";
			    }else{
					$msg .= ">Proxy: $nproxy - off-line - deletado";

					if (($key = array_search($nproxy, $proxy)) !== false) {

						array_push($proxyrm, $proxy[$key]);

					    unset($proxy[$key]);
					}

			    }

				sendMsg($msg, $chatId, $telegram);

			}
		}


		$msg = "------> Fim do teste de proxys. total: ".count($proxy);
		sendMsg($msg, $chatId, $telegram);

	}else{

		//$msg = "\n------> nada foi verificado: ".count($proxy);
		//sendMsg($msg, $chatId, $telegram);
	}

    // $kmem     = round(memory_get_usage() / 1024);
    // $kmemReal = round(memory_get_usage(true) / 1024);
    
    // echo "\nMemory (internal):\t$kmem KiB\n";
    // echo "Memory (real):\t\t$kmemReal KiB\n";
    // echo str_repeat('-', 50), "\n";
});

$loop->addPeriodicTimer(90.000, function () use (&$proxy, $url, &$update, &$totalproxy, $chatId, $telegram) {

	if(count($proxy) < 5) {

		$msg = "Iniciando coleta de novos proxys, total de: ".count($proxy);
		sendMsg($msg, $chatId, $telegram);


		$url  = base64_decode(base64_decode($url));
		$url  = explode('?', $url);
		$url  = $url[0];

		$novoproxy = getRemoteProxy($url);
		$totalproxy = $totalproxy + count($novoproxy);

		if(count($novoproxy) > 0) {
			for ($i = 0; $i <= count($novoproxy); $i++) {
				if(isset($novoproxy[$i]) && stristr($novoproxy[$i], ':')) {
					array_push($proxy, $novoproxy[$i]);
				}
			}
			$update  = date("Y-m-d H:i:s");
			$msg = "Adicionou um total de: ".count($novoproxy)." proxys...";
			sendMsg($msg, $chatId, $telegram);
		}else{
			$msg = "Total de ".count($novoproxy).", nada alterado";
			sendMsg($msg, $chatId, $telegram);			
		}

	}
	//$msg =  "\n---> total proxy ativo:" . count($proxy);
	//$msg .= "\n----> total de proxys usados:" . count($totalproxy);
	// $msg = "Fim do coletor de proxy, total: ".count($proxy);
	// sendMsg($msg, $chatId, $telegram);
});


$loop->addPeriodicTimer(6.000, function () use ($telegram, $admins) {

	$req = $telegram->getUpdates();
	for ($i = 0; $i < $telegram->UpdateCount(); $i++) {
		$telegram->serveUpdate($i);
		$result  = $telegram->getData();
		$text = "";
		if(isset( $result["message"]["text"] )){
			$text    = $result["message"]["text"];
		}
		$chat_id = $result["message"]["chat"]["id"];
		print_r($chat_id);
    	if(in_array($chat_id, $admins)){
		  CheckText($text, $chat_id, $telegram);
    	}
	}
	//echo "\n================= Loop BOT =================\n\n";
});


$socket = new ServerSock('0.0.0.0:8888', $loop);

$server->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;

$loop->run();





