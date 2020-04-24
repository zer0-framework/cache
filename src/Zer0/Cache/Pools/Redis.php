<?php

namespace Zer0\Cache\Pools;

use RedisClient\Pipeline\PipelineInterface;
use RedisClient\RedisClient;
use Zer0\App;
use Zer0\Cache\Item\Item;
use Zer0\Config\Interfaces\ConfigInterface;

/**
 * Class Redis
 *
 * @package Zer0\Cache\Pools
 */
final class Redis extends Base
{

    /**
     * @var RedisClient
     */
    protected $redis;

    /**
     * @var string
     */
    protected $prefix;

    /**
     * @var string
     */
    protected $tagPrefix;

    /**
     * @var bool
     */
    protected $saving = false;

    /**
     * Redis constructor.
     *
     * @param ConfigInterface $config
     * @param App             $app
     */
    public function __construct (ConfigInterface $config, App $app)
    {
        parent::__construct($config, $app);
        $this->redis     = $this->app->broker('Redis')->get($config->redis ?? '');
        $this->prefix    = $config->prefix ?? 'cache:';
        $this->tagPrefix = $config->tag_prefix ?? $this->prefix . 'tag:';
    }

    /**
     * @param string $key
     * @param bool & $hasValue
     *
     * @return mixed|null
     */
    public function getValueByKey (string $key, &$hasValue = null)
    {
        $raw = $this->redis->get($this->prefix . $key);
        if ($raw === null) {
            $hasValue = false;

            return null;
        }
        try {
            $value    = igbinary_unserialize($raw);
            $hasValue = true;

            return $value;
        } catch (\ErrorException $e) {
            $hasValue = false;

            return null;
        }
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @param int    $ttl Seconds to live
     *
     * @return bool
     */
    public function saveKey (string $key, $value, int $ttl = 0): bool
    {
        return (bool)$this->redis->set($this->prefix . $key, igbinary_serialize($value), $ttl > 0 ? $ttl : null);
    }

    /**
     * @param Item $item
     *
     * @return bool
     */
    public function invalidate (Item $item): bool
    {
        return (bool)$this->redis->del($this->prefix . $item->key);
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function invalidateKey (string $key): bool
    {
        return (bool)$this->redis->del($this->prefix . $key);
    }

    /**
     * @param string $tag
     *
     * @return bool
     */
    public function invalidateTag (string $tag): bool
    {
        $this->redis->eval(
            "local keys = redis.call('smembers', KEYS[1]);
        for i=1,#keys,5000 do
            redis.call('del', unpack(keys, i, math.min(i+4999, #keys)))
        end",
            [$this->tagPrefix . $tag]
        );

        return true;
    }

    /**
     * @param string $tag
     *
     * @return bool
     */
    public function invalidateTagSlow (string $tag): bool
    {
        $keys = $this->redis->smembers($this->tagPrefix . $tag);

        if (!$keys) {
            return false;
        }

        $this->redis->pipeline(
            function (PipelineInterface $redis) use ($keys, $tag) {
                foreach ($keys as $key) {
                    $redis->del($key);
                    $redis->srem($this->tagPrefix . $tag, $key);
                }
            }
        );

        return true;
    }

    /**
     * @param Item $item
     *
     * @return self
     */
    public function save (Item $item)
    {
        try {
            $this->saving = true;
            if (!$item->addTags && !$item->removeTags && $item->hasValue) {
                $this->redis->set(
                    $this->prefix . $item->key,
                    igbinary_serialize($item->value),
                    $item->ttl > 0 ? $ttl : null
                );
            }
            else {
                $this->redis->pipeline(
                    function (PipelineInterface $redis) use ($item) {
                        foreach ($item->addTags as $tag) {
                            $redis->sadd($this->tagPrefix . $tag, $this->prefix . $item->key);
                        }
                        foreach ($item->removeTags as $tag) {
                            $redis->srem($this->tagPrefix . $tag, $this->prefix . $item->key);
                        }
                        if ($item->hasValue) {
                            $redis->set(
                                $this->prefix . $item->key,
                                igbinary_serialize($item->value),
                                $item->ttl > 0 ? $ttl : null
                            );
                        }
                        else {
                            $redis->expires($this->prefix . $item->key, $item->ttl);
                        }
                    }
                );
            }

            return $this;
        } finally {
            $this->saving = false;
        }
    }
}
