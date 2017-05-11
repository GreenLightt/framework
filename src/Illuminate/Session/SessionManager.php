<?php

namespace Illuminate\Session;

use Illuminate\Support\Manager;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NullSessionHandler;

class SessionManager extends Manager
{
    /*
     * 创建基于自定义驱动的 session
     *
     * @param  string  $driver
     * @return mixed
     */
    protected function callCustomCreator($driver)
    {
        return $this->buildSession(parent::callCustomCreator($driver));
    }

    /*
     * 创建基于数组驱动的 session
     *
     * @return \Illuminate\Session\Store
     */
    protected function createArrayDriver()
    {
        return $this->buildSession(new NullSessionHandler);
    }

    /*
     * 创建基于 cookie 驱动的 session
     *
     * @return \Illuminate\Session\Store
     */
    protected function createCookieDriver()
    {
        return $this->buildSession(new CookieSessionHandler(
            $this->app['cookie'], $this->app['config']['session.lifetime']
        ));
    }

    /*
     * 创建基于文件驱动的 session
     *
     * @return \Illuminate\Session\Store
     */
    protected function createFileDriver()
    {
        return $this->createNativeDriver();
    }

    /*
     * 创建基于默认文件驱动的 session
     *
     * @return \Illuminate\Session\Store
     */
    protected function createNativeDriver()
    {
        $lifetime = $this->app['config']['session.lifetime'];

        return $this->buildSession(new FileSessionHandler(
            $this->app['files'], $this->app['config']['session.files'], $lifetime
        ));
    }

    /*
     * 创建基于数据库驱动的 session
     *
     * @return \Illuminate\Session\Store
     */
    protected function createDatabaseDriver()
    {
        $table = $this->app['config']['session.table'];

        $lifetime = $this->app['config']['session.lifetime'];

        return $this->buildSession(new DatabaseSessionHandler(
            $this->getDatabaseConnection(), $table, $lifetime, $this->app
        ));
    }

    /*
     * 获取数据库连接的连接对象
     *
     * @return \Illuminate\Database\Connection
     */
    protected function getDatabaseConnection()
    {
        $connection = $this->app['config']['session.connection'];

        return $this->app['db']->connection($connection);
    }

    /*
     * 创建基于 Apc Cache 驱动的 session
     *
     * @return \Illuminate\Session\Store
     */
    protected function createApcDriver()
    {
        return $this->createCacheBased('apc');
    }

    /*
     * 创建基于 Memcached Cache 驱动的 session
     *
     * @return \Illuminate\Session\Store
     */
    protected function createMemcachedDriver()
    {
        return $this->createCacheBased('memcached');
    }

    /*
     * 创建基于 Redis Cache 驱动的 session
     *
     * @return \Illuminate\Session\Store
     */
    protected function createRedisDriver()
    {
        $handler = $this->createCacheHandler('redis');

        $handler->getCache()->getStore()->setConnection(
            $this->app['config']['session.connection']
        );

        return $this->buildSession($handler);
    }

    /*
     * 创建基于 Cache 驱动的 session
     *
     * @param  string  $driver
     * @return \Illuminate\Session\Store
     */
    protected function createCacheBased($driver)
    {
        return $this->buildSession($this->createCacheHandler($driver));
    }

    /*
     * 创建 cache handler
     *
     * @param  string  $driver
     * @return \Illuminate\Session\CacheBasedSessionHandler
     */
    protected function createCacheHandler($driver)
    {
        $store = $this->app['config']->get('session.store') ?: $driver;

        return new CacheBasedSessionHandler(
            clone $this->app['cache']->store($store),
            $this->app['config']['session.lifetime']
        );
    }

    /*
     * 创建 session 实例
     *
     * @param  \SessionHandlerInterface  $handler
     * @return \Illuminate\Session\Store
     */
    protected function buildSession($handler)
    {
        if ($this->app['config']['session.encrypt']) {
            return $this->buildEncryptedSession($handler);
        } else {
            return new Store($this->app['config']['session.cookie'], $handler);
        }
    }

    /*
     * 创建支持加密的 session 实例
     *
     * @param  \SessionHandlerInterface  $handler
     * @return \Illuminate\Session\EncryptedStore
     */
    protected function buildEncryptedSession($handler)
    {
        return new EncryptedStore(
            $this->app['config']['session.cookie'], $handler, $this->app['encrypter']
        );
    }

    /*
     * 获取 session 配置项
     *
     * @return array
     */
    public function getSessionConfig()
    {
        return $this->app['config']['session'];
    }

    /*
     * 获取默认的 session (存储)驱动
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return $this->app['config']['session.driver'];
    }

    /*
     * 设置默认的 session 存储驱动
     *
     * @param  string  $name
     * @return void
     */
    public function setDefaultDriver($name)
    {
        $this->app['config']['session.driver'] = $name;
    }
}
