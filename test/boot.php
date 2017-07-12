<?php

use ZanPHP\NSQ\NsqConfig;
use ZanPHP\NSQ\Utils\Binary;
use ZanPHP\NSQ\Utils\MemoryBuffer;
use ZanPHP\NSQ\Utils\ObjectPool;
use Zan\Framework\Foundation\Core\Debug;

require_once __DIR__ . "/../vendor/autoload.php";
Debug::detect();

$config = require_once __DIR__ . "/config.php";
NsqConfig::init($config);

ObjectPool::create(new Binary(new MemoryBuffer(8192)), 30);