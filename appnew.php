<?php

use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use \React\EventLoop\Factory;
use \Legionth\React\Http\Rest\Server;
use \React\Socket\Server as ServerSock;
use \React\Http\Response;
use \Psr\Http\Message\ServerRequestInterface as Request;

use \KHR\React\Curl\Curl;
use \KHR\React\Curl\Exception;

date_default_timezone_set('America/Sao_Paulo');
require_once 'vendor/autoload.php';


//https://github.com/mesour/ArrayManager
require("Telegram.php");
require('functions.php');

$bot_id = '714388705:AAH8z02IcJrwAdWZNN8GPvE6gfG7-XU03Qo';
$admins = [427583453];//, -377734581];//[93077939, 231812624];
$chatId = '-1001342136473';//'-377734581';


$telegram = new Telegram($bot_id);


$loop   = Factory::create();
$server = new Server();
$curl = new Curl($loop);


$proxy_max  = 200;
$proxy_cont = 0;
$status  = true;
$start   = date("Y-m-d H:i:s");
$update  = date("Y-m-d H:i:s");
$proxy   = [];
$proxyof = [];
$url 	 = 'https://www.scpcnet.com.br/';
$urlstr  = 'SCPCNET';
$timeTotProxy = 10.0;
$timeAllProxy = 5.0;
$maxProxyOn   = 10;


$getApiProxy = function($ver=true) {

	$api_pr_key = 'VYZK892qeodPDML7fU6BFAjGtQuh4HWc';
	$api_pr_frm = [
		'apiKey'  => $api_pr_key,
		'country' => 'br'
	]; 

	if($ver != true){
		unset($api_pr_frm['apiKey']);
	}

	$api_proxy  = "http://falcon.proxyrotator.com:51337/?" . http_build_query($api_pr_frm);
	return $api_proxy;
};


function diffHoras($start, $end){

	$start = new DateTime($start);
	$end = new DateTime($end);

	$start->format('Y-m-d H:i:s');
	$end->format('Y-m-d H:i:s');


	$interval = $end->diff($start);

	$days = $interval->d;
	if ($days > 0) {
	    return $interval->format("%a %hh %im %ss");
	} else {
	    return $interval->format("%hh %im %ss");
	}

	// $diff = $datatime1->diff($datatime2);
	// return $diff->s . 's';
	//$horas = $diff->s + ($diff->days * 24);
	//return $horas;
}
//echo diffHoras(date("Y-m-d H:i:s"), date("Y-m-d H:i:s"));
//die;
function getProxy($getApiProxy, $qnt=2, $timeout=1, $cb_ok, $cb_err, $curl, LoopInterface $loop){
	//echo "entrou na 67, coleta de proxy" . PHP_EOL;
	$api_proxy = $getApiProxy;
	for ($x = 0; $x <= $qnt; $x++) {
		$payload = [
			CURLOPT_URL => $api_proxy,
			CURLOPT_AUTOREFERER => true,
			CURLOPT_TIMEOUT => $timeout,
			CURLOPT_CONNECTTIMEOUT => $timeout
		];

		$curl->add($payload)->then($cb_ok, $cb_err);
	}

	$curl->run();
}

function testProxy($url, $ipport, $timeout=3, $cb_ok, $cb_err, $curl, LoopInterface $loop) {
	//echo "-----> entrou na 53, test proxy... " . PHP_EOL;

//	for ($x = 0; $x <= 1; $x++) {
		$payload = [
			CURLOPT_URL => $url,
			CURLOPT_AUTOREFERER => true,
			CURLOPT_TIMEOUT => $timeout,
			CURLOPT_PROXY => $ipport,			
			CURLOPT_CONNECTTIMEOUT => $timeout
		];

		$curl->add($payload)->then($cb_ok, $cb_err);
//	}

	$curl->run();
}


function findProxy($interval=1.0, $timer, $curl, &$proxy, $getApiProxy, $url, &$proxy_cont, &$update, LoopInterface $loop) {

	$loop->addPeriodicTimer($interval, function ($timer) use ($loop, $curl, &$proxy, $getApiProxy, &$url, &$proxy_cont, &$update) {

		$maxProxys = 3;
		
		if(count($proxy) > $maxProxys){
			//echo "paro a lasanha, tem mais q 5";
			$loop->cancelTimer($timer);
		}

		getProxy($getApiProxy(), 2, 5,
			function(MCurl\Result $result) use (&$proxy, &$proxy_cont, &$update, &$url){
				$res = json_decode($result);
				if(isset($res->proxy)){
					if(strlen($res->proxy) > 5){
						$proxy_cont++;
						$update = date("Y-m-d H:i:s");
						$prpayl = [
									'proxy'=>  $res->proxy,
									'status'=> false,
									'url' => $url,
									'debug' => false,
									'start' => date("Y-m-d H:i:s"),
									'update' => date("Y-m-d H:i:s"),
									'lifetime'=> 0,
									'timeout'=> ''
						];
						array_push($proxy, $prpayl);
					}
				}
			}, function(Exception $e) {
				echo $e->result->info['url'], "\t", $e->getMessage(), " --- Fim.....", PHP_EOL;
		    		//echo $e->result->info['url'] . ' - Fim..' . PHP_EOL;
			},$curl, $loop);
	});
}

$verTotalProxy = $loop->addPeriodicTimer($timeTotProxy, function ($timer) use (&$url, $curl, $loop, &$proxy, $getApiProxy, &$proxy_max, &$proxy_cont, &$update, &$maxProxyOn, $telegram, $chatId) {
	$msg = "->iniciando verificacao do cache proxys: ".count($proxy);
	sendMsg($msg, $chatId, $telegram);

	if(count($proxy) < $maxProxyOn) {
		if($proxy_max > $proxy_cont){
			// findProxy($interval=1.0, $timer, $curl, &$proxy, $getApiProxy, $url, &$proxy_cont, &$update, LoopInterface $loop)
			findProxy(1.0, $timer, $curl, $proxy, $getApiProxy, $url, $proxy_cont, $update, $loop);
			$msg = "--> pouco proxy, buscar mais...";			
			sendMsg($msg, $chatId, $telegram);
		}else{
			if(count($proxyof) > 0){

				$msg = "========= copiando proxys off e jogando para novos testes... ===========".
						"\n--->Total de proxyof: ". count($proxyof);
				sendMsg($msg, $chatId, $telegram);

				$proxy = $proxyof;
				unset($proxyof);
				$msg = "\nLista de proxys atualizado total de: ". count($proxy);
				sendMsg($msg, $chatId, $telegram);
			}else{
				$msg = "---->>> pouco proxy, nao pode buscar mais, ultrapassou o limite maximo..";
				sendMsg($msg, $chatId, $telegram);
			}
		}
	}else{
		$msg = "cache de proxys ok, nada a fazer...";
		sendMsg($msg, $chatId, $telegram);
	}

});

function searcharray($value, $key, $array) {
   foreach ($array as $k => $val) {
       if ($val[$key] == $value) {
           return $k;
       }
   }
   return null;
}

$testAllProxy = $loop->addPeriodicTimer($timeAllProxy, function ($timer) use (&$url, &$urlstr, $curl, $loop, &$proxy, &$proxyof, &$update, $telegram, $chatId) {

	if(count($proxy) > 0){

		$rand_keys = array_rand($proxy, 1);
		$redeok = $proxy[$rand_keys];
		if(is_array($redeok)){
			$lifetime = diffHoras($redeok['start'], date("Y-m-d H:i:s"));
			unset($redeok['debug']);
		}else{
			//echo "\n\n\n\n ======================== rede ok nao eh array =========================== \n\n\n\n";
		}







		$urltest = $url;
		foreach($proxy as $ipport){
			if(!isset($ipport['proxy'])){
				continue;
			}

			$ipport = $ipport['proxy'];

			if(!stristr($ipport, ':')){
				continue;
			}else{

				testProxy(
					$urltest, $ipport, 3,
					function(MCurl\Result $result) use (&$proxy, $ipport, &$proxyof, &$urlstr, &$url, &$update, $telegram, $chatId){

						if(!stristr($result, $urlstr)){

							if (($key = searcharray($ipport, 'proxy', $proxy)) !== false) {
								$prpayl = [
										'proxy'  => $ipport,
										'status'  => false,
										'url' 	  => $url,
										'debug'   => $result,
										'start'   => date("Y-m-d H:i:s"),
										'update'  => date("Y-m-d H:i:s"),
										'lifetime'=> 0,
										'timeout' => $result->info['total_time']
								];
								array_push($proxyof, $prpayl);
						    	unset($proxy[$key]);
						    	$update =  date("Y-m-d H:i:s");
								$msg = "-----> {$ipport} deletado da lista";	
								sendMsg($msg, $chatId, $telegram);				    	
							}
						}else{
							if (($key = searcharray($ipport, 'proxy', $proxy)) !== false) {

								$proxy[$key]['url'] = $url;
								$proxy[$key]['status'] = true;
								$proxy[$key]['debug'] = (string) $result;
								$proxy[$key]['timeout'] = $result->info['total_time'];
								$proxy[$key]['update'] = date("Y-m-d H:i:s");
								if(isset($proxy[$key]['start'])){
									$lifetime = diffHoras($proxy[$key]['start'], date("Y-m-d H:i:s"));
								}else{
									$lifetime = 0;
								}
								$proxy[$key]['lifetime'] = $lifetime;
								$update =  date("Y-m-d H:i:s");
							}
						}
					},
					function(Exception $e) use(&$proxy, $ipport, &$proxyof, &$url, $telegram, $chatId) {
						if (($key = searcharray($ipport, 'proxy', $proxy)) !== false) {

							if(isset($proxy[$key]['start'])){
							$prpayl = [
									'proxy'  => $ipport,
									'status'  => false,
									'url' 	  => $url,
									'debug'   => $e->getMessage(),
									'start'   => $proxy[$key]['start'],
									'update'  => date("Y-m-d H:i:s"),
									'lifetime'=> diffHoras($proxy[$key]['start'], date("Y-m-d H:i:s")),
									'timeout' => ''
							];
							array_push($proxyof, $prpayl);

							unset($proxy[$key]);
							$msg = "------> {$ipport} deletado da lista --------->";
							sendMsg($msg, $chatId, $telegram);

							$update =  date("Y-m-d H:i:s");
							}
						}
						$msg = $e->result->info['url'] . "\t" . $e->getMessage() . " --- Fim.....";
						sendMsg($msg, $chatId, $telegram);
					},
					$curl, $loop
				);
			}
		}
	}else{
		$msg = "nenhum proxy a verificar...";
		$update =  date("d/m/Y H:i:s");
		sendMsg($msg, $chatId, $telegram);
	}

});

$server->get('/proxyOn', function (Request $request, callable $next) use (&$status, &$url, &$urlstr, &$getApiProxy, &$proxy, &$proxyof, &$update, &$start, &$proxy_max, &$proxy_cont, &$timeAllProxy, &$timeTotProxy) {

    $body = json_encode([
    	"redeOn"    => $proxy,
    	"urlcheck"  => $url,
    	"checkstr"  => $urlstr,
    	"totalOn"   => count($proxy),
    	"max_proxy" => $proxy_max,
    	"max_cont"  => $proxy_cont,
    	"status"    => $status
    ]);

    return new Response(200, array('Content-Type' => "application/json" ), $body);
});

$server->get('/proxyOff', function (Request $request, callable $next) use (&$status, &$url, &$urlstr, &$getApiProxy, &$proxy, &$proxyof, &$update, &$start, &$proxy_max, &$proxy_cont, &$timeAllProxy, &$timeTotProxy) {

    $body = json_encode([
    	"redeOff"   => $proxyof,
    	"urlcheck"  => $url,
    	"checkstr"  => $urlstr,
    	"totalOff"   => count($proxyof),
    	"max_proxy" => $proxy_max,
    	"max_cont"  => $proxy_cont,
    	"status"    => $status
    ]);

    return new Response(200, array('Content-Type' => "application/json" ), $body);
});

$server->get('/proxy', function (Request $request, callable $next) use (&$status, &$url, &$urlstr, &$getApiProxy, &$proxy, &$proxyof, &$update, &$start, &$proxy_max, &$proxy_cont, &$timeAllProxy, &$timeTotProxy) {
	if(count($proxy) > 0){
		$proxylist = $proxy;
		$proxylistok = [];

		foreach($proxylist as $vrprlist){
			if(isset($vrprlist['status']) && ($vrprlist['status'] == 'true')) {
				$proxylistok[] = $vrprlist;
			}
		}
		if(count($proxylistok) == 0) {
			$redeok = ['aguarde alguns instantes...'];
		}else{
			$rand_keys = array_rand($proxylistok, 1);
			$redeok = $proxylistok[$rand_keys];
			if(is_array($redeok)){
				if($redeok['status'] != false) {
					$lifetime = diffHoras($redeok['start'], date("Y-m-d H:i:s"));
					unset($redeok['debug']);
				}else{
					$redeok = ['fazer loop ???????'];
				}
			}
		}
	}else {
		$redeok = ['aguarde alguns instantes...'];
	}
    $body = json_encode([
    	"proxy"   => $redeok,
    	"urlcheck"  => $url,
    	"status"    => $status
    ]);

    return new Response(200, array('Content-Type' => "application/json" ), $body);
});


$server->get('/config', function (Request $request, callable $next) use (&$status, &$url, &$urlstr, &$getApiProxy, &$proxy, &$proxyof, &$update, &$start, &$proxy_max, &$proxy_cont, &$timeAllProxy, &$timeTotProxy, &$maxProxyOn) {

    $body = json_encode([
    	"redeOn"    => $proxy,
    	"redeOff"   => $proxyof,
    	"urlcheck"  => $url,
    	"checkstr"  => $urlstr,
    	"api_proxy" => $getApiProxy(false),
    	"proxy_on"  => count($proxy),
    	"proxy_off" => count($proxyof),
    	"max_proxy_on" => $maxProxyOn,
    	"max_proxy_search" => $proxy_max,
    	"max_proxy_cont"  => $proxy_cont,
    	"start"     => $start,
    	"update"    => $update,
    	"lifetime"  => diffHoras($start, date("Y-m-d H:i:s")),
    	"interval_test" => $timeAllProxy,
    	"interval_coletor_proxy" => $timeTotProxy,
    	"status"    => $status
    ]);

    return new Response(200, array('Content-Type' => "application/json" ), $body);
});

$socket = new ServerSock('0.0.0.0:5555', $loop);

$server->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;

$loop->run();







