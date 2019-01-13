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

require('functions.php');

$loop   = Factory::create();
$server = new Server();
$curl = new Curl($loop);

$proxy_max  = 20;
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
	if(stristr($start, '/')){
		$start = str_replace('/', '-', $start);
	}
	if(stristr($end, '/')){
		$end = str_replace('/', '-', $end);
	}

	$datatime1 = new DateTime($start);
	$datatime2 = new DateTime($end);

	$data1  = $datatime1->format('Y-m-d H:i:s');
	$data2  = $datatime2->format('Y-m-d H:i:s');

	$diff = $datatime1->diff($datatime2);
	return $diff->s . 's';
	//$horas = $diff->s + ($diff->days * 24);
	//return $horas;
}

function getProxy($getApiProxy, $qnt=2, $timeout=1, $cb_ok, $cb_err, $curl, LoopInterface $loop){
	echo "entrou na 67, coleta de proxy" . PHP_EOL;
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
	echo "-----> entrou na 53, test proxy... " . PHP_EOL;

	for ($x = 0; $x <= $qnt; $x++) {
		$payload = [
			CURLOPT_URL => $url,
			CURLOPT_AUTOREFERER => true,
			CURLOPT_TIMEOUT => $timeout,
			CURLOPT_PROXY => $ipport,			
			CURLOPT_CONNECTTIMEOUT => $timeout
		];

		$curl->add($payload)->then($cb_ok, $cb_err);
	}

	$curl->run();
}


function findProxy($interval=1.0, $timer, $curl, &$proxy, $getApiProxy, $url, &$proxy_cont, &$update, LoopInterface $loop) {

	$loop->addPeriodicTimer($interval, function ($timer) use ($loop, $curl, &$proxy, $getApiProxy, &$url, &$proxy_cont, &$update) {

		$maxProxys = 3;
		
		if(count($proxy) > $maxProxys){
			echo "paro a lasanha, tem mais q 5";
			$loop->cancelTimer($timer);
		}

		getProxy($getApiProxy(), 2, 5,
			function(MCurl\Result $result) use (&$proxy, &$proxy_cont, &$update){
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
									'start' => date("d/m/Y H:i:s"),
									'update' => date("d/m/Y H:i:s"),
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

$verTotalProxy = $loop->addPeriodicTimer($timeTotProxy, function ($timer) use (&$url, $curl, $loop, &$proxy, $findProxy, $getApiProxy, &$proxy_max, &$proxy_cont, &$update) {
	echo "----> ver total proxys:> ".count($proxy)." ---\n";

	if(count($proxy) < 5) {
		if($proxy_max > $proxy_cont){
			findProxy(1.0, $timer, $curl, $proxy, $getApiProxy, $url, $proxy_cont, $update, $loop);
			echo ">>>pouco proxy, buscar mais...\n";			
		}else{
			if(count($proxyof) > 0){
				echo "\n========= copiando proxys off e jogando para novos testes... ===========\n";
				echo "\nTotal de proxyof: ". count($proxyof);
				echo "\nTotal proxy Onnn: ". count($proxy);
				print_r($proxy);
				$proxy = $proxyof;
				unset($proxyof);
				echo "\nTotal proxy Onnn atualizadoooo????: ". count($proxy);
				print_r($proxy);
			}else{
				echo "\n\n\n\n>>>pouco proxy, nao pode buscar mais, ultrapassou o limite maximo.........\n\n\n";
			}
		}
	}else{
		echo "cache de proxys ok......";
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

$testAllProxy = $loop->addPeriodicTimer($timeAllProxy, function ($timer) use (&$url, &$urlstr, $curl, $loop, &$proxy, &$proxyof, $findProxy, &$update) {

	if(count($proxy) > 0){

		$rand_keys = array_rand($proxy, 1);
		$redeok = $proxy[$rand_keys];
		if(is_array($redeok)){
			$lifetime = diffHoras($redeok['start'], date("d/m/Y H:i:s"));
			unset($redeok['debug']);
		}else{
			echo "\n\n\n\n ======================== rede ok nao eh array =========================== \n\n\n\n";
		}







		$urltest = $url;
		foreach($proxy as $ipport){
			$ipport = $ipport['proxy'];

			if(!stristr($ipport, ':')){
				continue;
			}else{

				testProxy(
					$urltest, $ipport, 3,
					function(MCurl\Result $result) use (&$proxy, $ipport, &$proxyof, &$urlstr, &$url, &$update){

						if(!stristr($result, $urlstr)){

							if (($key = searcharray($ipport, 'proxy', $proxy)) !== false) {
								$prpayl = [
										'proxy'  => $ipport,
										'status'  => false,
										'url' 	  => $url,
										'debug'   => $result,
										'start'   => date("d/m/Y H:i:s"),
										'update'  => date("d/m/Y H:i:s"),
										'lifetime'=> 0,
										'timeout' => $result->info['total_time']
								];
								array_push($proxyof, $prpayl);
						    	unset($proxy[$key]);
						    	$update =  date("d/m/Y H:i:s");
								echo "\n{$ipport} deletado da lista --------->";					    	
							}
						}else{
							if (($key = searcharray($ipport, 'proxy', $proxy)) !== false) {

								$proxy[$key]['url'] = $url;
								$proxy[$key]['status'] = true;
								$proxy[$key]['debug'] = (string) $result;
								$proxy[$key]['timeout'] = $result->info['total_time'];
								$proxy[$key]['update'] = date("d/m/Y H:i:s");
								$proxy[$key]['lifetime'] = diffHoras($proxy[$key]['start'], date("d/m/Y H:i:s"));
								$update =  date("d/m/Y H:i:s");
							}
						}
					},
					function(Exception $e) use(&$proxy, $ipport, &$proxyof, &$url) {
						if (($key = searcharray($ipport, 'proxy', $proxy)) !== false) {


							$prpayl = [
									'proxy'  => $ipport,
									'status'  => false,
									'url' 	  => $url,
									'debug'   => $e->getMessage(),
									'start'   => $proxy[$key]['start'],
									'update'  => date("d/m/Y H:i:s"),
									'lifetime'=> diffHoras($proxy[$key]['start'], date("d/m/Y H:i:s")),
									'timeout' => ''
							];
							array_push($proxyof, $prpayl);

							unset($proxy[$key]);
							echo "\n{$ipport} deletado da lista --------->";
							$update =  date("d/m/Y H:i:s");
						}
						echo $e->result->info['url'], "\t", $e->getMessage(), " --- Fim.....", PHP_EOL;
				  		//echo $e->result->info['url'] . ' - Fim..' . PHP_EOL;
					},
					$curl, $loop
				);
			}
		}
	}else{
		echo "nenhum proxy a verificar...";
		$update =  date("d/m/Y H:i:s");
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
		$rand_keys = array_rand($proxy, 1);
		$redeok = $proxy[$rand_keys];
		if(is_array($redeok)){
			if($redeok['status'] != false) {
				$lifetime = diffHoras($redeok['start'], date("d/m/Y H:i:s"));
				unset($redeok['debug']);
			}else{
				$redeok = ['fazer loop ???????'];
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


$server->get('/config', function (Request $request, callable $next) use (&$status, &$url, &$urlstr, &$getApiProxy, &$proxy, &$proxyof, &$update, &$start, &$proxy_max, &$proxy_cont, &$timeAllProxy, &$timeTotProxy) {

    $body = json_encode([
    	"redeOn"    => $proxy,
    	"redeOff"   => $proxyof,
    	"urlcheck"  => $url,
    	"checkstr"  => $urlstr,
    	"api_proxy" => $getApiProxy(false),
    	"max_proxy" => $proxy_max,
    	"max_cont"  => $proxy_cont,
    	"proxy_on"  => count($proxy),
    	"proxy_off" => count($proxyof),
    	"start"     => $start,
    	"update"    => $update,
    	"lifetime"  => diffHoras($start, date("Y-m-d H:i:s")),
    	"interval_test" => $timeAllProxy,
    	"interval_coletor_proxy" => $timeTotProxy,
    	"status"    => $status
    ]);

    return new Response(200, array('Content-Type' => "application/json" ), $body);
});

$socket = new ServerSock('0.0.0.0:3333', $loop);

$server->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;

$loop->run();







