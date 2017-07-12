<?php

namespace ZanPHP\NSQ\Test;

use ZanPHP\NSQ\Connection;
use ZanPHP\NSQ\Lookup;
use Zan\Framework\Foundation\Coroutine\Task;
use Zan\Framework\Network\Server\Timer\Timer;

require_once __DIR__ . "/boot.php";


$connTask = function() {
    $topic = "zan_mqworker_test";
    $lookupDevUrl = "http://" . NSQ_LOOKUP_HOST . ":4161";
    $maxConnNum = 2;

    $lookup = new Lookup($topic, $maxConnNum);


    /* @var Connection[] $conns */
//    $conns = (yield $lookup->connectToNSQD("xx.xx.xx.xx", 4150));
//    assert(count($conns) === $maxConnNum);

    yield $lookup->connectToNSQLookupd($lookupDevUrl);
};

$task = function() {
    $topic = "zan_mqworker_test";
    $lookupQaUrl = "http://" . NSQ_LOOKUP_HOST . ":4161";
    $maxConnNum = 1;

    $lookup = new Lookup($topic, $maxConnNum);

    /* @var Connection[] $conns */
//    $conns = (yield $lookup->connectToNSQD("xx.xx.xx.xx", 4150));
//    assert(count($conns) === $maxConnNum);

    $lookup->disconnectFromNSQDConn($conns[0]);
    yield $lookup->reconnect($conns[0]);

    $conns = (yield $lookup->connectToNSQLookupd($lookupQaUrl));
    $lookup->disconnectFromNSQLookupd($lookupQaUrl);
};

Task::execute($task());