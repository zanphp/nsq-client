<?php

namespace ZanPHP\NSQ;


use ZanPHP\NSQ\Contract\MsgHandler;
use ZanPHP\NSQ\Utils\Lock;
use ZanPHP\Support\Json;

class NSQ
{
    /**
     * @param string $topic
     * @param string $channel
     * @param MsgHandler|callable $msgHandler
     * @param int $maxInFlight
     * @return \Generator yield return Consumer
     * @throws NsqException
     */
    public static function subscribe($topic, $channel, $msgHandler, $maxInFlight = -1)
    {
        Command::checkTopicChannelName($topic);
        Command::checkTopicChannelName($channel);

        if (is_callable($msgHandler)) {
            $msgHandler = new SimpleMsgHandler($msgHandler);
        }

        if (!($msgHandler instanceof MsgHandler)) {
            throw new NsqException("invalid msgHandler");
        }

        $consumer = new Consumer($topic, $channel, $msgHandler);
        $maxInFlight = $maxInFlight > 0 ? $maxInFlight : NsqConfig::getMaxInFlightCount();
        $consumer->changeMaxInFlight($maxInFlight ?: $maxInFlight);

        $lookup = NsqConfig::getLookup();
        if (empty($lookup)) {
            throw new NsqException("no nsq lookup address");
        }

        if (!isset(InitializeNSQ::$consumers["$topic:$channel"])) {
            InitializeNSQ::$consumers["$topic:$channel"] = [];
        }
        InitializeNSQ::$consumers["$topic:$channel"][] = $consumer;

        if (is_array($lookup)) {
            yield $consumer->connectToNSQLookupds($lookup);
        } else {
            yield $consumer->connectToNSQLookupd($lookup);
        }
    }

    /**
     * @param string $topic
     * @param string $channel
     * @return bool
     */
    public static function unSubscribe($topic, $channel)
    {
        if (!isset(InitializeNSQ::$consumers["$topic:$channel"]) || !InitializeNSQ::$consumers["$topic:$channel"]) {
            return false;
        }

        /* @var Consumer $consumer */
        foreach (InitializeNSQ::$consumers["$topic:$channel"] as $consumer) {
            $consumer->stop();
        }
        return true;
    }

    /**
     * @param string $topic
     * @param string[] ...$messages
     * @return \Generator yield bool
     * @throws NsqException
     */
    public static function publish($topic, ...$messages)
    {
        Command::checkTopicChannelName($topic);

        $lookup = NsqConfig::getLookup();
        if (empty($lookup)) {
            throw new NsqException("no nsq lookup address");
        }

        if (empty($messages)) {
            throw new NsqException("empty messages");
        }

        foreach ($messages as $i => $message) {
            if (is_scalar($message)) {
                $messages[$i] = /*strval(*/$message/*)*/;
            } else {
                $messages[$i] = Json::encode($message);
            }
        }

        yield Lock::lock(__CLASS__);
        try {
            if (!isset(InitializeNSQ::$producers[$topic])) {
                yield InitializeNSQ::initProducers([$topic => NsqConfig::getMaxConnectionPerTopic()]);
            }
        } finally {
            yield Lock::unlock(__CLASS__);
        }

        $producer = InitializeNSQ::$producers[$topic];
        $retry = NsqConfig::getPublishRetry();
        yield self::publishWithRetry($producer, $topic, $messages, $retry);
    }

    private static function publishWithRetry(Producer $producer, $topic, $messages, $n = 3)
    {
        $resp = null;

        try {
            if (count($messages) === 1) {
                $resp = (yield $producer->publish($messages[0]));
            } else {
                $resp = (yield $producer->multiPublish($messages));
            }
        } catch (\Throwable $ex) {
        } catch (\Exception $ex) {
        }

        if ($resp === "OK") {
            yield true;
        } else {
            if (--$n > 0) {
                if ($resp === "E_BAD_TOPIC") {
                    yield $producer->queryNSQLookupd();
                }
                $i = (3 - $n);
                $msg = isset($ex) ? $ex->getMessage() : "";
                sys_error("publish fail <$msg>, retry [topic=$topic, n=$i]");
                yield taskSleep(100 * $i);
                yield self::publishWithRetry($producer, $topic, $messages, $n);
            } else {
                $previous = isset($ex) ? $ex : null;
                throw  new NsqException("publish [$topic] fail", 0, $previous, [
                    "topic" => $topic,
                    "msg"   => $messages,
                    "resp"  => $resp,
                ]);
            }
        }
    }

    /**
     * @return array
     */
    public static function stat()
    {
        $stat = [
            "consumer" => [],
            "producer" => [],
        ];
        foreach (InitializeNSQ::$consumers as $topicCh => $consumers) {
            /* @var Consumer $consumer */
            foreach ($consumers as $consumer) {
                $stat["consumer"][$topicCh] = $consumer->stats();
            }
        }
        foreach (InitializeNSQ::$producers as $topic => $producer) {
            $stat["producer"][$topic] = $producer->stats();
        }
        return $stat;
    }
}