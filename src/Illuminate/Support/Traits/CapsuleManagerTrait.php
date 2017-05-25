<?php

namespace Illuminate\Support\Traits;

use Illuminate\Support\Fluent;
use Illuminate\Contracts\Container\Container;

trait CapsuleManagerTrait
{
    /**
     * The current globally used instance.
     *
     * @var object
     */
    protected static $instance;

    /**
     * The container instance.
     *
     * @var \Illuminate\Contracts\Container\Container
     */
    protected $container;

    /*
     * 设置 Ioc 容器实例
     *
     * @param  \Illuminate\Contracts\Container\Container  $container
     * @return void
     */
    protected function setupContainer(Container $container)
    {
        $this->container = $container;

        if (! $this->container->bound('config')) {
            $this->container->instance('config', new Fluent);
        }
    }

    /*
     * 设置 当前小容器实例 全局静态可访问
     *
     * @return void
     */
    public function setAsGlobal()
    {
        static::$instance = $this;
    }

    /*
     * 获取 Ioc 容器实例
     *
     * @return \Illuminate\Contracts\Container\Container
     */
    public function getContainer()
    {
        return $this->container;
    }

    /*
     * 设置IoC 容器实例
     *
     * @param  \Illuminate\Contracts\Container\Container  $container
     * @return void
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;
    }
}
