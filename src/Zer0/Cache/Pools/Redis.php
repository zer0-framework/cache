<?php

namespace Zer0\Cache\Pools;

use RedisClient\Exception\EmptyResponseException;
use RedisClient\Exception\InvalidArgumentException;
use RedisClient\Pipeline\PipelineInterface;
use RedisClient\RedisClient;
use Zer0\App;
use Zer0\Cache\Item\Item;
use Zer0\Config\Interfaces\ConfigInterface;
use Zer0\Cache\Exceptions\QueryFailedException;

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

    /** {@inheritDoc} */
    public function getValueByKey (string $key, &$hasValue = null)
    {
        try {
            $raw = $this->redis->get($this->prefix . $key);
            if ($raw === null) {
                $hasValue = false;

                return null;
            }
            try {
                $value    = $this->unserialize($raw);
                $hasValue = true;

                return $value;
            } catch (\ErrorException $e) {
                $hasValue = false;

                return null;
            }
        } catch (EmptyResponseException $exception) {
            throw new QueryFailedException('Failed to get value by key: ' . $key, 0, $exception);
        }
    }

    /** {@inheritDoc} */
    public function saveKey (string $key, $value, int $ttl = 0): bool
    {
        try {
            return (bool)$this->redis->set($this->prefix . $key, $this->serialize($value), $ttl > 0 ? $ttl : null);
        } catch (EmptyResponseException $exception) {
            throw new QueryFailedException('Failed to save key: '. $key, 0, $exception);
        }
    }

    /** {@inheritDoc} */
    public function invalidate (Item $item, $after = null)
    {
        try {
            if ($after !== null) {
                if (is_int($after)) {
                    $this->redis->expire($this->prefix . $item->key, $after);
                } else {
                    $this->redis->expireAt($this->prefix . $item->key, strtotime($after));
                }
            } else {
                $this->redis->unlink($this->prefix . $item->key);
            }
        } catch (EmptyResponseException $exception) {
            throw new QueryFailedException('Failed to invalidate item', 0, $exception);
        }
    }

    /** {@inheritDoc} */
    public function invalidateKey (string $key)
    {
        try {
            $this->redis->unlink($this->prefix . $key);
        } catch (EmptyResponseException $exception) {
            throw new QueryFailedException('Failed to invalidate key: ' . $key, 0, $exception);
        }
    }

    /** {@inheritDoc} */
    public function invalidateTag (string $tag)
    {
        try {
            $this->redis->eval(
                "local keys = redis.call('smembers', KEYS[1]);
        for i=1,#keys,5000 do
            redis.call('unlink', unpack(keys, i, math.min(i+4999, #keys)))
        end",
                [$this->tagPrefix . $tag]
            );
        } catch (EmptyResponseException $exception) {
            throw new QueryFailedException('Failed to invalidate tag: ' . $tag, 0, $exception);
        }
    }

    /** {@inheritDoc} */
    public function invalidateTagSlow (string $tag): bool
    {
        try {
            $keys = $this->redis->smembers($this->tagPrefix . $tag);

            if (!$keys) {
                return false;
            }

            $this->redis->pipeline(
                function (PipelineInterface $redis) use ($keys, $tag) {
                    foreach ($keys as $key) {
                        $redis->unlink($key);
                        $redis->srem($this->tagPrefix . $tag, $key);
                    }
                }
            );

            return true;
        } catch (EmptyResponseException $exception) {
            throw new QueryFailedException('Failed to invalidate tag: ' . $tag, 0, $exception);
        }
    }

    /** {@inheritDoc} */
    public function save (Item $item)
    {
        try {
            $this->saving = true;
            if (!$item->addTags && !$item->removeTags && $item->hasValue) {
                $this->redis->set(
                    $this->prefix . $item->key,
                    $this->serialize($item->value),
                    $item->ttl > 0 ? $item->ttl : null
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
                                $this->serialize($item->value),
                                $item->ttl > 0 ? $item->ttl : null
                            );
                        }
                        else {
                            $redis->expires($this->prefix . $item->key, $item->ttl);
                        }
                    }
                );
            }
        } catch (EmptyResponseException|InvalidArgumentException $exception) {
            throw new QueryFailedException('Failed to save the item', 0, $exception);
        } finally {
            $this->saving = false;

            return $this;
        }
    }
}
