<?php

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

$loop   = Factory::create();
$server = new Server();

$status  = true;
$start   = date("Y-m-d H:i:s");
$update  = date("Y-m-d H:i:s");
$totalproxy = 0;
$proxyrm = [];
$proxy   = [];
$url     = 'YUhSMGNITTZMeTkzZDNjdWMyTndZMjVsZEM1amIyMHVZbkl2UVVOVFVFNUZWQzlRY205bmNtRnR';
$url    .= 'ZWE12VTBsQlUxQlRRMEV1WVdwaGVDNXdhSEEvYkd0ZlUyUnZZejFEVUVZbWJHdGZUbVJ2WXowPQ';

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

$server->get('/logs', function (Request $request, callable $next) {
	$logs = file_get_contents('logs.txt');

    return new Response(200, array('Content-Type' => "text/plain" ), $logs);
});


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

$loop->addPeriodicTimer(30.000, function () use (&$proxy, $url, &$proxyrm) {
	$cpf  = '';
	$url  = base64_decode(base64_decode($url));
	$url  = explode('?', $url);
	$url  = $url[0];

	echo "\nTotal proxys:\t\t\t".count($proxy)." ---> iniciando testes....\n";

	if(count($proxy) > 0) {
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
			if(strlen($nproxy) > 8){
			    $headers = $resProxys['headers'][$i];
			    $body    = $resProxys['body'][$i];
			    if(stristr($body, '99;')){
			    	echo "\n>Proxy: $nproxy - Online..\n";
			    }else{
			    	echo "\n>Proxy: $nproxy - off-line - deletado\n";

					if (($key = array_search($nproxy, $proxy)) !== false) {

						array_push($proxyrm, $proxy[$key]);

					    unset($proxy[$key]);
					}

			    }
			}
		}

		echo "\n------> Fim do teste de proxys. total: ".count($proxy);
	}else{
		echo "\n------> nada foi verificado: ".count($proxy);
	}

	echo PHP_EOL;
    // $kmem     = round(memory_get_usage() / 1024);
    // $kmemReal = round(memory_get_usage(true) / 1024);
    
    // echo "\nMemory (internal):\t$kmem KiB\n";
    // echo "Memory (real):\t\t$kmemReal KiB\n";
    // echo str_repeat('-', 50), "\n";
});

$loop->addPeriodicTimer(60.000, function () use (&$proxy, $url, &$update, &$totalproxy) {

	if(count($proxy) < 5) {
		echo "\nBuscar novos proxys...\n";
		$url  = base64_decode(base64_decode($url));
		$url  = explode('?', $url);
		$url  = $url[0];

		$novoproxy = getRemoteProxy($url);
		if(stristr($novoproxy, ':')){
			array_push($proxy, $novoproxy); //add proxy a lista....
			$update  = date("Y-m-d H:i:s");
			$totalproxy++;
			echo "\nAdicionou nova rede....:\t\t".$novoproxy;
		}else{
			echo "\nErro ao coletar nova rede....\t\t";
		}
	}else{
		//echo "\nTa tudo na boa...........\n";
		$update  = date("Y-m-d H:i:s");
	}

	echo "\n-----> total proxy ativo:" . count($proxy);
	echo "\n-----> total de proxys usados:" . count($totalproxy);

});



$socket = new ServerSock('0.0.0.0:8888', $loop);

$server->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;

$loop->run();





