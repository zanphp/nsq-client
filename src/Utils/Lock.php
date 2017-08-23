<?php

namespace ZanPHP\NSQ\Utils;


use ZanPHP\Coroutine\Contract\Async;
use ZanPHP\Coroutine\Task;


/**
 * Class Lock
 * @package ZanPHP\NSQ\Utils
 *
 *  yield Lock::lock(__CLASS__);
 *  try {
 *      // do something
 *  } finally {
 *     yield SpinLock::Lock(__CLASS__);
 *  }
 */
final class Lock implements Async
{
    /**
     * TODO memory leak, unset unused lock
     * @var static[] map<string, Lock>
     */
    private static $lockIns;

    /**
     * @var array map<string, list<Lock>>
     */
    private static $callbacks = [];

    private $lockName;

    private $locked = false;

    private function __construct($lockName)
    {
        $this->lockName = $lockName;
    }

    private function lock_()
    {
        if ($this->locked) {
            yield $this;
        } else {
            $this->locked = true;
            static::$callbacks[$this->lockName] = [];
            yield null;
        }
    }

    private function unlock_()
    {
        if (!$this->locked) {
            return;
        }

        $this->locked = false;
        $callbacks = static::$callbacks[$this->lockName];

        foreach ($callbacks as $taskId => $callback) {
            call_user_func($callback, null, null);
        }
        unset(static::$callbacks[$this->lockName]);
    }

    /**
     * @param $lockName
     * @return static
     */
    private static function get($lockName)
    {
        if (!isset(static::$lockIns[$lockName])) {
            static::$lockIns[$lockName] = new static($lockName);
        }
        return static::$lockIns[$lockName];
    }

    public static function lock($lockName)
    {
        yield static::get($lockName)->lock_();
    }

    public static function unlock($lockName)
    {
        yield static::get($lockName)->unlock_();
    }

    /**
     * @param callable $callback
     * @param Task $task
     */
    public function execute(callable $callback, $task)
    {
        static::$callbacks[$this->lockName][$task->getTaskId()] = $callback;
    }
}