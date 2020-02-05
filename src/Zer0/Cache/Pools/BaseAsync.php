<?php

namespace Zer0\Cache\Pools;

use Zer0\App;
use Zer0\Cache\Item\ItemAsync;
use Zer0\Cache\Traits\Hash;
use Zer0\Config\Interfaces\ConfigInterface;

/**
 * Class BaseAsync
 * @package Zer0\Cache\Pools
 */
abstract class BaseAsync
{
    use Hash;

    /**
     * @var ConfigInterface
     */
    protected $config;

    /**
     * @var App
     */
    protected $app;

    /**
     * Base constructor.
     * @param ConfigInterface $config
     * @param App $app
     */
    public function __construct(ConfigInterface $config, App $app)
    {
        $this->config = $config;
        $this->app = $app;
    }

    /**
     * @param string $key
     * @return ItemAsync
     */
    public function item(string $key, $hash = false): ItemAsync
    {
        if ($hash !== null) {
            $key .= $this->hash($hash);
        }
        $item = new ItemAsync($key, $this);
        $ttl = $this->config->expiration[$key] ?? null;
        if ($ttl !== null) {
            $item->expiresAt($ttl);
        }
        return $item;
    }

    /**
     * @param string $key
     * @param callable $cb
     */
    abstract public function getValueByKey(string $key, $cb): void;

    /**
     * @param string $key
     * @param mixed $value
     * @param int $ttl Seconds to live
     * @param callable $cb
     */
    abstract public function saveKey(string $key, $value, int $ttl = 0, $cb): void;

    /**
     * @param ItemAsync $item
     * @param callable $cb
     */
    abstract public function invalidate(ItemAsync $item, $cb): void;

    /**
     * @param string $key
     * @param callable $cb
     */
    abstract public function invalidateKey(string $key, $cb): void;

    /**
     * @param ItemAsync $item
     * @param callable $cb
     */
    abstract public function save(ItemAsync $item, $cb): void;
}
