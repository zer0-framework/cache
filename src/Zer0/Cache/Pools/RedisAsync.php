<?php

namespace Zer0\Cache\Pools;

use PHPDaemon\Clients\Redis\Connection;
use PHPDaemon\Clients\Redis\Pool;
use Zer0\App;
use Zer0\Cache\Item\ItemAsync;
use Zer0\Config\Interfaces\ConfigInterface;

/**
 * Class RedisAsync
 * @package Zer0\Cache\Pools
 */
final class RedisAsync extends BaseAsync
{

    /**
     * @var Pool
     */
    protected $redis;


    /**
     * @var string
     */
    protected $prefix;

    /**
     * Redis constructor.
     * @param ConfigInterface $config
     * @param App $app
     */
    public function __construct(ConfigInterface $config, App $app)
    {
        parent::__construct($config, $app);
        $this->redis = $this->app->broker('RedisAsync')->get($config->redis ?? '');
        $this->prefix = $config->prefix ?? 'cache:';
    }

    /**
     * @param string $key
     * @param callable $cb
     */
    public function getValueByKey(string $key, $cb): void
    {
        $this->redis->get($this->prefix . $key, function ($redis) use ($cb) {
            if ($redis->result === null) {
                $cb(null, false);
            } else {
                try {
                    $cb($this->unserialize($redis->result), true);
                } catch (\ErrorException $e) {
                    $cb(null, false);
                }
            }
        });
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param int $ttl Seconds to live
     * @param callable $cb
     * @return void
     */
    public function saveKey(string $key, $value, int $ttl = 0, $cb): void
    {
        $this->redis->setex($this->prefix . $key, $ttl, $this->serialize($value), function ($redis) use ($cb) {
            $cb(true);
        });
    }

    /**
     * @param ItemAsync $item
     * @param callable $cb
     * @return mixed
     */
    public function invalidate(ItemAsync $item, $cb): void
    {
        $this->redis->unlink($this->prefix . $item->key, function ($redis) use ($cb) {
            $cb(true);
        });
    }

    /**
     * @param string $key
     * @param callable $cb
     * @return mixed
     */
    public function invalidateKey(string $key, $cb): void
    {
        $this->redis->unlink($this->prefix . $key, function ($redis) use ($cb) {
            $cb(true);
        });
    }

    /**
     * @param string $tag
     * @param callable|null $cb
     */
    public function invalidateTag(string $tag, callable $cb = null): void
    {
        $this->redis->eval("local keys = redis.call('smembers', KEYS[1]);
        for i=1,#keys,5000 do
            redis.call('unlink', unpack(keys, i, math.min(i+4999, #keys)))
        end",
            1,
            $this->tagPrefix . $tag,
            function (Connection $redis) use ($cb): void {
                if ($cb !== null) {
                    $cb(true);
                }
            }
        );
    }

    /**
     * @param string $tag
     * @param callable $cb
     * @return void
     */
    public function invalidateTagSlow(string $tag, callable $cb = null): void
    {
        $this->redis->sMembers($this->tagPrefix . $tag, function ($redis) use ($cb, $tag) {
            $this->redis->multi(function ($redis) use ($cb, $tag) {
                foreach ($redis->result as $key) {
                    $redis->unlink($key);
                    $redis->sRem($this->tagPrefix . $tag, $key);
                }
                if ($cb !== null) {
                    $redis->exec(function ($redis) use ($cb) {
                        $cb(true);
                    });
                } else {
                    $redis->exec();
                }
            });
        });
    }

    /**
     * @param ItemAsync $item
     * @param callable $cb
     */
    public function save(ItemAsync $item, $cb): void
    {
        if (!$item->addTags && !$item->removeTags) {
            $this->saveKey($item->key, $item->value, $item->ttl, $cb);
        } else {
            $this->redis->multi(function ($redis) use ($item, $cb) {
                foreach ($item->addTags as $tag) {
                    $this->redis->sAdd($this->tagPrefix . $tag, $this->prefix . $item->key);
                }
                foreach ($item->removeTags as $tag) {
                    $this->redis->sRem($this->tagPrefix . $tag, $this->prefix . $item->key);
                }
                $redis->setex($this->prefix . $item->key, $item->ttl, $this->serialize($item->value));
                $redis->exec(function () use ($cb) {
                    $cb($this);
                });
            });
        }
    }
}
