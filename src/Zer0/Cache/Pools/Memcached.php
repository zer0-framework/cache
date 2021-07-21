<?php

namespace Zer0\Cache\Pools;

use RedisClient\Exception\InvalidArgumentException;
use Zer0\App;
use Zer0\Cache\Item\Item;
use Zer0\Config\Interfaces\ConfigInterface;
use Zer0\Cache\Exceptions\NotSupportedException;
use Zer0\Cache\Exceptions\QueryFailedException;

/**
 * Class Memcached
 *
 * @package Zer0\Cache\Pools
 */
final class Memcached extends Base
{

    /**
     * @var \Memcached
     */
    protected $memcached;

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
        $this->memcached     = $this->app->broker('Memcached')->get($config->memcached ?? '');
        $this->prefix    = $config->prefix ?? '';
    }

    /** {@inheritDoc} */
    public function getValueByKey (string $key, &$hasValue = null)
    {
        try {
            $raw = $this->memcached->get($this->prefix . $key);
            if ($this->memcached->getResultCode() === \Memcached::RES_NOTFOUND) {
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
        } catch (\Throwable $exception) {
            throw new QueryFailedException('Failed to get value by key: ' . $key, 0, $exception);
        }
    }

    /** {@inheritDoc} */
    public function saveKey (string $key, $value, int $ttl = 0): bool
    {
        try {
             $this->memcached->set($this->prefix . $key, $this->serialize($value), $ttl > 0 ? $ttl : null);
            if ($this->memcached->getResultCode() == \Memcached::RES_NOTSTORED) {
                throw new QueryFailedException('Failed to save key: '. $key, 0);
            }
        } catch (\Throwable $exception) {
            throw new QueryFailedException('Failed to save key: '. $key, 0, $exception);
        }
    }

    /** {@inheritDoc} */
    public function invalidate (Item $item)
    {
        try {
            $this->memcached->delete($this->prefix . $item->key);
        } catch (\Throwable $exception) {
            throw new QueryFailedException('Failed to invalidate item', 0, $exception);
        }
    }

    /** {@inheritDoc} */
    public function invalidateKey (string $key)
    {
        try {
            $this->memcached->delete($this->prefix . $key);

        } catch (\Throwable $exception) {
            throw new QueryFailedException('Failed to invalidate key: ' . $key, 0, $exception);
        }
    }

    /** {@inheritDoc} */
    public function invalidateTag (string $tag)
    {
        throw new NotSupportedException('tags as are not supported in Memcached');
    }

    /** {@inheritDoc} */
    public function invalidateTagSlow (string $tag)
    {
        throw new NotSupportedException('tags as are not supported in Memcached');
    }

    /** {@inheritDoc} */
    public function save (Item $item)
    {
        try {
            $this->saving = true;
            $this->memcached->set(
                $this->prefix . $item->key,
                $this->serialize($item->value),
                $item->ttl > 0 ? $item->ttl : null
            );
        } catch (\Throwable|InvalidArgumentException $exception) {
            throw new QueryFailedException('Failed to save the item', 0, $exception);
        } finally {
            $this->saving = false;

            return $this;
        }
    }
}
