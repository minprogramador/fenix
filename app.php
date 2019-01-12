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


require_once 'vendor/autoload.php';

require('functions.php');

$loop   = Factory::create();
$server = new Server();
$curl = new Curl($loop);


$status  = true;
$update  = date("Y-m-d H:i:s");
$proxy   = [];
$proxyof = [];
$url     = 'YUhSMGNITTZMeTkzZDNjdWMyTndZMjVsZEM1amIyMHVZbkl2UVVOVFVFNUZWQzlRY205bmNtRnR';
$url    .= 'ZWE12VTBsQlUxQlRRMEV1WVdwaGVDNXdhSEEvYkd0ZlUyUnZZejFEVUVZbWJHdGZUbVJ2WXowPQ';

$a = 0;


function getProxy($qnt=2, $timeout=1, $cb_ok, $cb_err, $curl, LoopInterface $loop) {
	echo "entrou na 71" . PHP_EOL;
	$api_proxy = 'http://falcon.proxyrotator.com:51337/?apiKey=VYZK892qeodPDML7fU6BFAjGtQuh4HWc&country=br&port=3128';

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


function findProxy($interval=1.0, $timer, $curl, &$proxy, LoopInterface $loop) {

	$loop->addPeriodicTimer($interval, function ($timer) use ($loop, $curl, &$proxy) {

		$maxProxys = 3;
		
		if(count($proxy) > $maxProxys){
			echo "paro a lasanha, tem mais q 5";
			$loop->cancelTimer($timer);
		}

		getProxy(2, 2,
			function(MCurl\Result $result) use (&$proxy){
				$res = json_decode($result);
				if(isset($res->proxy)){
					if(strlen($res->proxy) > 5){
						array_push($proxy, ['proxy'=>  $res->proxy, 'status'=> false, 'debug' => '', 'update' => date("d/m/Y H:i:s")]);
					}
				}
			}, function(Exception $e) {
				echo $e->result->info['url'], "\t", $e->getMessage(), " --- Fim.....", PHP_EOL;
		    		//echo $e->result->info['url'] . ' - Fim..' . PHP_EOL;
			},$curl, $loop);
	});
}


$verTotalProxy = $loop->addPeriodicTimer(2.0, function ($timer) use ($curl, $loop, &$proxy, $findProxy) {
	echo "----> ver total proxys:> ".count($proxy)." ---\n";

	if(count($proxy) < 5) {
	
		findProxy(1.0, $timer, $curl, $proxy, $loop);
		echo ">>>pouco proxy, buscar mais...\n";

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

$testAllProxy = $loop->addPeriodicTimer(5.0, function ($timer) use ($url, $curl, $loop, &$proxy, &$proxyof, $findProxy) {

	if(count($proxy) > 0){
		
		$urltest = 	base64_decode(base64_decode($url));
		$urltest = explode('?', $urltest)[0];
		foreach($proxy as $ipport){
			$ipport = $ipport['proxy'];

			if(!stristr($ipport, ':')){
				continue;
			}else{

				testProxy(
					$urltest, $ipport, 3,
					function(MCurl\Result $result) use (&$proxy, $ipport, &$proxyof){
						if(!stristr($result, '99;')){

							if (($key = searcharray($ipport, 'proxy', $proxy)) !== false) {
								array_push($proxyof, ['proxy'=> $ipport, 'status'=> false, 'debug' => $result, 'update' => date("d/m/Y H:i:s")]);
						    	unset($proxy[$key]);
								echo "\n{$ipport} deletado da lista --------->";					    	
							}
						}else{
							if (($key = searcharray($ipport, 'proxy', $proxy)) !== false) {
								$proxy[$key]['status'] = true;
								$proxy[$key]['debug'] = (string) $result;
								$proxy[$key]['update'] = date("d/m/Y H:i:s");

///								array_push($proxyof, ['proxy'=> $ipport, 'status'=> true, 'debug' => (string) $result, 'update' => date("d/m/Y H:i:s")]);
	//					    	unset($proxy[$key]);
	//							echo "\n{$ipport} deletado da lista --------->";	
							}
						}
					},
					function(Exception $e) use(&$proxy, $ipport, &$proxyof) {
						if (($key = searcharray($ipport, 'proxy', $proxy)) !== false) {
							array_push($proxyof, ['proxy'=> $ipport, 'status'=> false, 'debug' => $e->getMessage(), 'update' => date("d/m/Y H:i:s")]);
							unset($proxy[$key]);
							echo "\n{$ipport} deletado da lista --------->";
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
	}

});

$server->get('/config', function (Request $request, callable $next) use (&$status, $url, &$proxy, &$proxyof, &$update) {

    $body = json_encode([
    	"redeOn"   => $proxy,
    	"redeOff"   => $proxyof,
    	"status" => $status,
    	"update" => $update
    ]);

    return new Response(200, array('Content-Type' => "application/json" ), $body);
});

$socket = new ServerSock('0.0.0.0:3333', $loop);

$server->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;

$loop->run();







