<?php

namespace ZanPHP\NSQ\Test;

use ZanPHP\NSQ\Producer;
use ZanPHP\NSQ\NSQ;
use Zan\Framework\Foundation\Coroutine\Task;

require_once __DIR__ . "/boot.php";

ini_set("memory_limit", "1024m");
//cli_set_process_title(__FILE__);

$taskPub = function () {
    $payload = str_repeat("a", 1024 * 2);

    $task = function() use($payload) {
        $topic = "zan_mqworker_test";

        /* @var Producer $producer */
        while (true) {
            try {
                $r = yield NSQ::publish($topic, $payload);
                var_dump($r);
            } catch (\Throwable $e) {
            } catch (\Exception $e) {}


            if (isset($e)) {
                echo_exception($e);
            }

            yield taskSleep(10);
        }
    };

    $tasks = [];
    for ($i = 0; $i < 1000; $i++) {
        $tasks[] = $task();
    }

    yield parallel($tasks);
};

Task::execute($taskPub());