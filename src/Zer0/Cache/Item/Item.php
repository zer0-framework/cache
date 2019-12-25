<?php

namespace Zer0\Cache\Item;

use Zer0\Cache\Pools\Base;
use Zer0\Queue\TaskAbstract;

/**
 * Class Item
 * @package Zer0\Cache\Item
 */
class Item extends ItemAbstract
{
    /**
     * @var Base
     */
    protected $pool;

    /**
     * Item constructor.
     * @param string $key
     * @param Base $pool
     */
    public function __construct(string $key, Base $pool)
    {
        $this->key = $key;
        $this->pool = $pool;
    }

    /**
     * @return mixed
     */
    public function get()
    {
        if ($this->hasValue === null) {
            $this->value = $this->pool->getValueByKey($this->key, $this->hasValue);
        }
        if ($this->hasValue === true) {
            return $this->value;
        } else {
            if ($this->callback) {
                call_user_func($this->callback, $this);
                if ($this->hasValue) {
                    return $this->value;
                }
            }
            return null;
        }
    }

    /**
     * @return bool
     */
    public function invalidate()
    {
        return $this->pool->invalidate($this);
    }

    /**
     * @return bool
     */
    public function save()
    {
        return $this->pool->save($this);
    }

    /**
     * @param TaskAbstract $task
     */
    public function setCallbackTask(TaskAbstract $task, \Zer0\Queue\Pools\Base $queue, int $timeout = 30): self
    {
        return $this->setCallback(function (Item $item) use ($queue, $timeout) {
            try {
                $queue->enqueueWait(
                    $task,
                    $timeout
                )->throwException();
                $item->reset()->get();
            } catch (\Zer0\Queue\Exceptions\WaitTimeoutException $e) {
            }
        });
    }
}
