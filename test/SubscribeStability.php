<?php

namespace ZanPHP\NSQ\Test;

use ZanPHP\NSQ\Consumer;
use ZanPHP\NSQ\Contract\MsgHandler;
use ZanPHP\NSQ\Message;
use ZanPHP\NSQ\NSQ;
use Zan\Framework\Foundation\Coroutine\Task;

require_once __DIR__ . "/boot.php";

class BenchMsgHandler2 implements MsgHandler
{

    public function handleMessage(Message $message, Consumer $consumer)
    {
        // yield taskSleep(100);
    }

    public function logFailedMessage(Message $message, Consumer $consumer)
    {
        sys_echo("error: logFailedMessage " . $message);
    }
}

ini_set("memory_limit", "1024m");
// cli_set_process_title(__FILE__);

$task = function()
{
    $topic = "zan_mqworker_test";
    $ch = "ch1";
    /* @var Consumer $consumer */
    $consumer = (yield NSQ::subscribe($topic, $ch, new BenchMsgHandler2(), 1));
};

swoole_timer_tick(1000, function() {
    print_r(NSQ::stat());
    echo number_format(memory_get_usage()), "byte\n";
    echo number_format(memory_get_usage(true)), "byte\n";
});

Task::execute($task());