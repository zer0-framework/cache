<?php

namespace Zer0\Cache\Traits;

use Zer0\App;
use Zer0\Cache\Item\Item;
use Zer0\Queue\Pools\Base;

/**
 * Trait QueueTask
 * @package Zer0\Cache\Traits
 */
trait QueueTask
{
    /**
     * @param Base  $queue
     * @param int   $timeout
     * @param array $arguments
     *
     * @return \Closure
     */
    public static function cacheCallback(Base $queue, int $timeout = 3, array $arguments = []): \Closure
    {
        return function (Item $item) use ($queue, $timeout) {
            try {
                $queue->pushWait(
                    new self(...$arguments),
                    $timeout
                )->throwException();
                $item->reset()->get();
            } catch (\Zer0\Queue\Exceptions\WaitTimeoutException $e) {
                // Задача не завершилась за 3 секунды
            }
        };
    }
}
