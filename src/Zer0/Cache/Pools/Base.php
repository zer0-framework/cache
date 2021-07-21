<?php

namespace Zer0\Cache\Pools;

use Zer0\App;
use Zer0\Cache\Item\Item;
use Zer0\Cache\Traits\Hash;
use Zer0\Cache\Traits\Serialization;
use Zer0\Config\Interfaces\ConfigInterface;
use Zer0\Cache\Exceptions\QueryFailedException;

/**
 * Class Base
 * @package Zer0\Cache\Pools
 */
abstract class Base
{
    use Hash;
    use Serialization;

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
     * @return Item
     */
    public function item(string $key, $hash = null): Item
    {
        $ttl = $this->config->expiration[$key] ?? null;
        if ($hash !== null) {
            if (!is_string($hash)) {
                $hash = $this->hash($hash);
            }
            $key .= ':' . $hash;
        }
        $item = new Item($key, $this);
        if ($ttl !== null) {
            $item->expiresAt($ttl);
        }
        return $item;
    }

    /**
     * @param string $key
     * @param null   $hasValue
     *
     * @return mixed|null
     * @throws QueryFailedException
     */
    abstract public function getValueByKey(string $key, &$hasValue = null);

    /**
     * @param string $key
     * @param mixed  $value
     * @param int    $ttl
     *
     * @return bool
     * @throws QueryFailedException
     */
    abstract public function saveKey(string $key, $value, int $ttl = 0): bool;


    /**
     * @param Item $item
     *
     * @throws QueryFailedException
     */
    abstract public function invalidate(Item $item);

    /**
     * @param string $key
     *
     * @throws QueryFailedException
     */
    abstract public function invalidateKey(string $key);

    /**
     * @param Item $item
     *
     * @return $this
     * @throws QueryFailedException
     */
    abstract public function save(Item $item);

    /**
     * @param string $tag
     *
     * @throws QueryFailedException
     */
    abstract public function invalidateTag(string $tag);
}
