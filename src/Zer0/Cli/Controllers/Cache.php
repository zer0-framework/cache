<?php

namespace Zer0\Cli\Controllers;

use Zer0\Cli\AbstractController;
use Zer0\Cli\Controllers\Queue\Top;
use Zer0\Queue\SomeTask;
use Zer0\Queue\TaskAbstract;

/**
 * Class Cache
 * @package Zer0\Cli\Controllers
 */
final class Cache extends AbstractController
{
    /**
     * @var \Zer0\Cache\Pools\Base
     */
    protected $cache;

    /**
     * @var string
     */
    protected $command = 'cache';

    /**
     *
     */
    public function before(): void
    {
        parent::before();
        $this->cache = $this->app->factory('CachePool');
    }

    /**
     * @param ...string $key
     */
    public function invalidateAction(...$args): void
    {
        foreach ($args as $key) {
            $this->cli->write(($this->cache->invalidateKey($key) ? '1' : '0') . ' ');
        }
        $this->cli->writeln('');
    }

    /**
     * @param ...string $tag
     */
    public function invalidateTagsAction(...$args): void
    {
        foreach ($args as $tag) {
            $this->cli->write(($this->cache->invalidateTag($tag) ? '1' : '0') . ' ');
        }
        $this->cli->writeln('');
    }
}
