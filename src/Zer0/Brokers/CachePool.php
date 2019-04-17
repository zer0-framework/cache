<?php

namespace Zer0\Brokers;

use PHPDaemon\Core\ClassFinder;
use Zer0\Config\Interfaces\ConfigInterface;

/**
 * Class CachePool
 * @package Zer0\Brokers
 */
class CachePool extends Base
{
    /**
     * @param ConfigInterface $config
     * @return object
     */
    public function instantiate(ConfigInterface $config)
    {
        $class = ClassFinder::find($config->type, ClassFinder::getNamespace(\Zer0\Cache\Pools\Base::class), '~');
        return new $class($config, $this->app);
    }

    /**
     * @param string $name
     * @param bool $caching
     * @return \Zer0\Cache\Pools\Base
     */
    public function get(string $name = '', bool $caching = true): \Zer0\Cache\Pools\Base
    {
        return parent::get($name, $caching);
    }
}
