<?php

namespace ZanPHP\NSQ;

use ZanPHP\NSQ\Contract\MsgDelegate;
use ZanPHP\NSQ\Utils\Binary;
use ZanPHP\NSQ\Utils\ObjectPool;
use ZanPHP\Support\Time;


class Message
{
    /**
     * Message ID
     * @var int
     */
    private $id;

    /**
     * Message payload
     * @var string
     */
    private $body;

    /**
     * @var string
     */
    private $timestamp;

    /**
     * How many attempts have been made
     * @var int
     */
    private $attempts;

    private $isResponded = false;

    private $autoResponse = true;

    /**
     * @var MsgDelegate
     */
    private $delegate;

    public function __construct($bytes, MsgDelegate $delegate)
    {
        $this->unpack($bytes);
        $this->delegate = $delegate;
        $this->autoResponse = NsqConfig::getMessageAutoResponse();
    }

    public function __clone()
    {
        $this->id = null;
        $this->body = null;
        $this->timestamp = null;
        $this->attempts = null;
        $this->isResponded = false;
        $this->autoResponse = true;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @return string
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    /**
     * @return int
     */
    public function getAttempts()
    {
        return $this->attempts;
    }

    /**
     * DisableAutoResponse disables the automatic response that
     * would normally be sent when a MsgHandler:;handleMessage
     * returns (FIN/REQ based on the value returned).
     * @return void
     */
    public function disableAutoResponse()
    {
        $this->autoResponse = false;
    }

    /**
     * IsAutoResponseDisabled indicates whether or not this message
     * will be responded to automatically
     * @return bool
     */
    public function isAutoResponse()
    {
        return $this->autoResponse;
    }

    /**
     * HasResponded indicates whether or not this message has been responded to
     * @return bool
     */
    public function hasResponsed()
    {
        return $this->isResponded;
    }

    /**
     * Finish sends a FIN command to the nsqd which
     * sent this message
     */
    public function finish()
    {
        $this->isResponded = true;
        $this->delegate->onFinish($this);
    }

    /**
     * Touch sends a TOUCH command to the nsqd which
     * sent this message
     */
    public function touch()
    {
        if ($this->isResponded) {
            return;
        }
        $this->delegate->onTouch($this);
    }

    /**
     * Requeue sends a REQ command to the nsqd which
     * sent this message, using the supplied delay.
     *
     * A delay of -1 will automatically calculate
     * based on the number of attempts and the
     * configured default_requeue_delay
     * @param $delay
     * @param bool $backoff
     */
    public function requeue($delay, $backoff = false)
    {
        $this->isResponded = true;
        $this->delegate->onRequeue($this, $delay, $backoff);
    }

    /**
     * unpack binary message
     * @param string $bytes
     * @throws NsqException
     *
     * message format:
     *  [x][x][x][x][x][x][x][x][x][x][x][x][x][x][x][x][x][x][x][x][x][x][x][x][x][x][x][x][x][x]...
     *  |       (int64)        ||    ||      (hex string encoded in ASCII)           || (binary)
     *  |       8-byte         ||    ||                 16-byte                      || N-byte
     *  ------------------------------------------------------------------------------------------...
     *    nanosecond timestamp    ^^                   message ID                       message body
     *                         (uint16)
     *                          2-byte
     *                         attempts
     */
    private function unpack($bytes)
    {
        if (strlen($bytes) < 26) {
            throw new NsqException("not enough data to decode valid message");
        }

        /* @var Binary $binary */
        // $binary = new Binary();
        $binary = ObjectPool::get(Binary::class);
        $binary->write($bytes);
        $this->timestamp = $binary->readUInt64BE();
        $this->attempts = $binary->readUInt16BE();
        $this->id = $binary->read(16);
        $this->body = $binary->readFull();
        ObjectPool::release($binary);
    }

    /**
     * For Debug
     * @param string $id
     * @param int $attempts
     * @param string $body
     * @return Binary
     */
    public static function pack($id, $attempts, $body)
    {
        $binary = new Binary();
        $binary->writeUInt64BE(Time::stamp());
        $binary->writeUInt16BE(intval($attempts));
        $binary->write(str_pad($id, 16, "\0", STR_PAD_RIGHT));
        $binary->write($body);
        return $binary;
    }

    public function __toString()
    {
        return "[id=$this->id, ts=$this->timestamp, attempts=$this->attempts, body=$this->body]";
    }
}