<?php

namespace ZanPHP\NSQ\Utils;

use swoole_buffer as SwooleBuffer;
use ZanPHP\NSQ\Contract\Buffer;


/**
 * Class Buffer
 *
 * 自动扩容, 从尾部写入数据，从头部读出数据
 * 参考 
 *
 * +-------------------+------------------+------------------+
 * | prependable bytes |  readable bytes  |  writable bytes  |
 * |                   |     (CONTENT)    |                  |
 * +-------------------+------------------+------------------+
 * |                   |                  |                  |
 * V                   V                  V                  V
 * 0      <=      readerIndex   <=   writerIndex    <=     size
 */
class MemoryBuffer implements Buffer
{
    private $buffer;

    private $readerIndex;

    private $writerIndex;

    public static function ofBytes($bytes)
    {
        $self = new static;
        $self->write($bytes);
        return $self;
    }

    public function __construct($size = 1024)
    {
        $this->buffer = new SwooleBuffer($size);
        $this->readerIndex = 0;
        $this->writerIndex = 0;
    }

    public function __clone()
    {
        $this->reset();
    }

    public function readableBytes()
    {
        return $this->writerIndex - $this->readerIndex;
    }

    public function writableBytes()
    {
        return $this->buffer->capacity - $this->writerIndex;
    }

    public function prependableBytes()
    {
        return $this->readerIndex;
    }

    public function capacity()
    {
        return $this->buffer->capacity;
    }

    public function get($len)
    {
        if ($len <= 0) {
            return "";
        }

        $len = min($len, $this->readableBytes());
        return $this->rawRead($this->readerIndex, $len);
    }

    public function read($len)
    {
        if ($len <= 0) {
            return "";
        }

        $len = min($len, $this->readableBytes());
        $read = $this->rawRead($this->readerIndex, $len);
        $this->readerIndex += $len;
        if ($this->readerIndex === $this->writerIndex) {
            $this->reset();
        }
        return $read;
    }

    public function readFull()
    {
        return $this->read($this->readableBytes());
    }

    public function write($bytes)
    {
        if ($bytes === "") {
            return false;
        }

        $len = strlen($bytes);

        if ($len <= $this->writableBytes()) {

            write:
            $this->rawWrite($this->writerIndex, $bytes);
            $this->writerIndex += $len;
            return true;
        }

        // expand
        if ($len > ($this->prependableBytes() + $this->writableBytes())) {
            $this->expand(($this->readableBytes() + $len) * 2);
        }

        // copy-move
        if ($this->readerIndex !== 0) {
            $this->rawWrite(0, $this->rawRead($this->readerIndex, $this->writerIndex - $this->readerIndex));
            $this->writerIndex -= $this->readerIndex;
            $this->readerIndex = 0;
        }

        goto write;
    }

    public function reset()
    {
        $this->readerIndex = 0;
        $this->writerIndex = 0;
    }

    /** @noinspection PhpToStringReturnInspection */
    public function __toString()
    {
        return $this->rawRead($this->readerIndex, $this->writerIndex - $this->readerIndex);
    }

    // NOTICE: 影响 IDE Debugger
    public function __debugInfo()
    {
        return [
            "string" => $this->__toString(),
            "capacity" => $this->capacity(),
            "readerIndex" => $this->readerIndex,
            "writerIndex" => $this->writerIndex,
            "prependableBytes" => $this->prependableBytes(),
            "readableBytes" => $this->readableBytes(),
            "writableBytes" => $this->writableBytes(),
        ];
    }

    private function rawRead($offset, $len)
    {
        if ($offset < 0 || $offset + $len > $this->buffer->capacity) {
            throw new \InvalidArgumentException(__METHOD__ . ": offset=$offset, len=$len, capacity={$this->buffer->capacity}");
        }
        return $this->buffer->read($offset, $len);
    }

    private function rawWrite($offset, $bytes)
    {
        $len = strlen($bytes);
        if ($offset < 0 || $offset + $len > $this->buffer->capacity) {
            throw new \InvalidArgumentException(__METHOD__ . ": offset=$offset, len=$len, capacity={$this->buffer->capacity}");
        }
        return $this->buffer->write($offset, $bytes);
    }

    private function expand($size)
    {
        if ($size <= $this->buffer->capacity) {
            throw new \InvalidArgumentException(__METHOD__ . ": size=$size, capacity={$this->buffer->capacity}");
        }
        return $this->buffer->expand($size);
    }
}