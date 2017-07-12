<?php

namespace ZanPHP\NSQ\Test;

use ZanPHP\NSQ\Producer;
use ZanPHP\NSQ\NSQ;
use Zan\Framework\Foundation\Coroutine\Task;

require_once __DIR__ . "/boot.php";


function taskPub()
{
    $topic = "zan_mqworker_test";

    $oneMsg = "hello";
    $multiMsgs = [
        "hello",
        "hi",
    ];


    /* @var Producer $producer */
    try {
        $ok = (yield NSQ::publish($topic, $oneMsg));
        var_dump($ok);
    } catch (\Throwable $t) {
        echo_exception($t);
    } catch (\Exception $e) {
        echo_exception($e);
    }

    try {
        $ok = (yield NSQ::publish($topic, "hello", "hi"));
        var_dump($ok);
    } catch (\Throwable $t) {
        echo_exception($t);
    } catch (\Exception $e) {
        echo_exception($e);
    }

    try {
        $ok = (yield NSQ::publish($topic, ...$multiMsgs));
        var_dump($ok);
    } catch (\Throwable $t) {
        echo_exception($t);
    } catch (\Exception $e) {
        echo_exception($e);
    }


    swoole_event_exit();
}

Task::execute(taskPub());