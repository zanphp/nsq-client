<?php

namespace ZanPHP\NSQ\Test;

use ZanPHP\NSQ\Contract\MsgDelegate;
use ZanPHP\NSQ\Message;

require_once __DIR__ . "/boot.php";

class NopMsgDelegate implements MsgDelegate
{
    public function onFinish(Message $message) {}
    public function onRequeue(Message $message, $delay, $backoff) {}
    public function onTouch(Message $message) {}
}

$id = substr(md5(__FILE__), 0, 16);
$attempts = 4;
$payload = "hello,  o(╯□╰)o";
$bin = Message::pack($id, 4, $payload);
$bytes = $bin->readFull();
$msg = new Message($bytes, new NopMsgDelegate());
assert($msg->getId() === $id);
assert($msg->getAttempts() === $attempts);
assert($msg->getBody() === $payload);

assert($msg->hasResponsed() === false);
$msg->touch();
assert($msg->hasResponsed() === false);
$msg->finish();
assert($msg->hasResponsed() === true);
$msg->requeue(1000);