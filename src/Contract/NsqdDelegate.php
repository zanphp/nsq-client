<?php

namespace ZanPHP\NSQ\Contract;


use ZanPHP\NSQ\Connection;

interface NsqdDelegate
{
    /**
     * onConnected is called when nsqd connects
     * @param Connection $conn
     * @return mixed
     */
    public function onConnect(Connection $conn);
}