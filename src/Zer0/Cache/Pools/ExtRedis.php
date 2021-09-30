<?php

namespace Zer0\Cache\Pools;

use Zer0\App;
use Zer0\Cache\Item\Item;
use Zer0\Config\Interfaces\ConfigInterface;
use Zer0\Cache\Exceptions\QueryFailedException;

/**
 * Class ExtRedis
 *
 * @package Zer0\Cache\Pools
 */
final class ExtRedis extends Base
{

    /**
     * @var \Redis
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
     * ExtRedis constructor.
     *
     * @param ConfigInterface $config
     * @param App             $app
     */
    public function __construct (ConfigInterface $config, App $app)
    {
        parent::__construct($config, $app);
        $this->redis     = $this->app->broker('ExtRedis')->get($config->redis ?? '');
        $this->prefix    = $config->prefix ?? 'cache:';
        $this->tagPrefix = $config->tag_prefix ?? $this->prefix . 'tag:';
    }

    /** {@inheritDoc} */
    public function getValueByKey (string $key, &$hasValue = null)
    {
        try {
            $raw = $this->redis->get($this->prefix . $key);
            if ($raw === false) {
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
        } catch (\RedisException $exception) {
            throw new QueryFailedException('Failed to get value by key: ' . $key, 0, $exception);
        }
    }

    /** {@inheritDoc} */
    public function saveKey (string $key, $value, int $ttl = 0): bool
    {
        try {
            return (bool)$this->redis->set($this->prefix . $key, $this->serialize($value), $ttl > 0 ? $ttl : null);
        } catch (\RedisException $exception) {
            throw new QueryFailedException('Failed to save key: ' . $key, 0, $exception);
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
        } catch (\RedisException $exception) {
            throw new QueryFailedException('Failed to invalidate item', 0, $exception);
        }
    }

    /** {@inheritDoc} */
    public function invalidateKey (string $key)
    {
        try {
            $this->redis->unlink($this->prefix . $key);
        } catch (\RedisException $exception) {
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
                [$this->tagPrefix . $tag],
                1
            );
        } catch (\RedisException $exception) {
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

            $this->redis->pipeline();
            foreach ($keys as $key) {
                $this->redis->unlink($key);
                $this->redis->srem($this->tagPrefix . $tag, $key);
            }
            $this->redis->exec();

            return true;
        } catch (\RedisException $exception) {
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
                $this->redis->pipeline();
                foreach ($item->addTags as $tag) {
                    $this->redis->sadd($this->tagPrefix . $tag, $this->prefix . $item->key);
                }
                foreach ($item->removeTags as $tag) {
                    $this->redis->srem($this->tagPrefix . $tag, $this->prefix . $item->key);
                }
                if ($item->hasValue) {
                    $this->redis->set(
                        $this->prefix . $item->key,
                        $this->serialize($item->value),
                        $item->ttl > 0 ? $item->ttl : null
                    );
                }
                else {
                    $this->redis->expires($this->prefix . $item->key, $item->ttl);
                }
                $this->redis->exec();
            }
        } catch (\RedisException|InvalidArgumentException $exception) {
            throw new QueryFailedException('Failed to save the item', 0, $exception);
        } finally {
            $this->saving = false;

            return $this;
        }
    }
}
