<?php

declare(strict_types = 1);


use React\EventLoop\Factory;
use \unreal4u\TelegramAPI\HttpClientRequestHandler;
use unreal4u\TelegramAPI\Telegram\Methods\GetUpdates;
use \unreal4u\TelegramAPI\Abstracts\TraversableCustomType;
use unreal4u\TelegramAPI\TgLog;

$botkey = '714388705:AAH8z02IcJrwAdWZNN8GPvE6gfG7-XU03Qo';


$loop = Factory::create();
$tgLog = new TgLog($botkey, new HttpClientRequestHandler($loop));

$getUpdates = new GetUpdates();

// If using this method, send an offset (AKA last known update_id) to avoid getting duplicate update notifications.
#$getUpdates->offset = 328221148;
$updatePromise = $tgLog->performApiRequest($getUpdates);
$updatePromise->then(
    function (TraversableCustomType $updatesArray) {
        foreach ($updatesArray as $update) {
            print_r($update);
        }
    },
    function (\Exception $exception) {
        // Onoes, an exception occurred...
        echo 'Exception ' . get_class($exception) . ' caught, message: ' . $exception->getMessage();
    }
);

$loop->run();