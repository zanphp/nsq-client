<?php

namespace ZanPHP\NSQ\Test;

use ZanPHP\NSQ\Utils\Dns;
use Zan\Framework\Foundation\Coroutine\Task;

require_once __DIR__ . "/boot.php";


$task = function() {
    // success
    $ip = (yield Dns::lookup("www.youzan.com"));
    assert(filter_var($ip, FILTER_VALIDATE_IP) === $ip);

    // fail
    try {
        $ip = yield Dns::lookup("xxx.yyy.xxx", 100);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            assert(false);
        }
    } catch (\Exception $ex) { }

    swoole_event_exit();
};

Task::execute($task());

