<?php

namespace Illuminate\Redis;

use Illuminate\Support\Arr;
use InvalidArgumentException;
use Illuminate\Contracts\Redis\Factory;

class RedisManager implements Factory
{
    /*
     * 默认 redis 驱动的名称
     *
     * @var string
     */
    protected $driver;

    /*
     * redis 配置
     *
     * @var array
     */
    protected $config;

    /*
     * redis 连接对象
     *
     * @var mixed
     */
    protected $connections;

    /*
     * 创建 redis 管理实例
     *
     * @param  string  $driver
     * @param  array  $config
     */
    public function __construct($driver, array $config)
    {
        $this->driver = $driver;
        $this->config = $config;
    }

    /*
     * 根据名称获取连接对象
     *
     * @param  string|null  $name
     * @return \Illuminate\Redis\Connections\Connection
     */
    public function connection($name = null)
    {
        $name = $name ?: 'default';

        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }

        return $this->connections[$name] = $this->resolve($name);
    }

    /*
     * 解析连接对象
     *
     * @param  string  $name
     * @return \Illuminate\Redis\Connections\Connection
     *
     * @throws \InvalidArgumentException
     */
    protected function resolve($name)
    {
        $options = Arr::get($this->config, 'options', []);

        if (isset($this->config[$name])) {
            return $this->connector()->connect($this->config[$name], $options);
        }

        if (isset($this->config['clusters'][$name])) {
            return $this->resolveCluster($name);
        }

        throw new InvalidArgumentException(
            "Redis connection [{$name}] not configured."
        );
    }

    /*
     * 解析集群中的连接对象
     *
     * @param  string  $name
     * @return \Illuminate\Redis\Connections\Connection
     */
    protected function resolveCluster($name)
    {
        $clusterOptions = Arr::get($this->config, 'clusters.options', []);

        return $this->connector()->connectToCluster(
            $this->config['clusters'][$name], $clusterOptions, Arr::get($this->config, 'options', [])
        );
    }

    /*
     * 获取 Redis 客户端对象
     *
     * @return \Illuminate\Redis\Connectors\PhpRedisConnector|\Illuminate\Redis\Connectors\PredisConnector
     */
    protected function connector()
    {
        switch ($this->driver) {
            case 'predis':
                return new Connectors\PredisConnector;
            case 'phpredis':
                return new Connectors\PhpRedisConnector;
        }
    }

    /**
     * Pass methods onto the default Redis connection.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->connection()->{$method}(...$parameters);
    }
}
