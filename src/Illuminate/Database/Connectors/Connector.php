<?php

namespace Illuminate\Database\Connectors;

use PDO;
use Exception;
use Illuminate\Support\Arr;
use Doctrine\DBAL\Driver\PDOConnection;
use Illuminate\Database\DetectsLostConnections;

class Connector
{
    use DetectsLostConnections;

    /*
     * 默认的 PDO 连接选项
     *
     * @var array
     */
    protected $options = [
        // PDO::ATTR_CASE：强制列名为指定的大小写
        //     PDO::CASE_LOWER：强制列名小写
        //     PDO::CASE_NATURAL：保留数据库驱动返回的列名
        //     PDO::CASE_UPPER：强制列名大写
        PDO::ATTR_CASE => PDO::CASE_NATURAL,
        // PDO::ATTR_ERRMODE：错误报告
        //     PDO::ERRMODE_SILENT： 仅设置错误代码
        //     PDO::ERRMODE_WARNING: 引发 E_WARNING 错误
        //     PDO::ERRMODE_EXCEPTION: 抛出 exceptions 异常
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        // PDO::ATTR_ORACLE_NULLS （在所有驱动中都可用，不仅限于Oracle）： 转换 NULL 和空字符串
        //     PDO::NULL_NATURAL: 不转换
        //     PDO::NULL_EMPTY_STRING： 将空字符串转换成 NULL
        //     PDO::NULL_TO_STRING: 将 NULL 转换成空字符串
        PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
        // PDO::ATTR_STRINGIFY_FETCHES: 提取的时候将数值转换为字符串
        PDO::ATTR_STRINGIFY_FETCHES => false,
        // PDO::ATTR_EMULATE_PREPARES 启用或禁用预处理语句的模拟
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    /*
     * Create a new PDO connection.
     *
     * @param  string  $dsn
     * @param  array   $config
     * @param  array   $options
     * @return \PDO
     */
    public function createConnection($dsn, array $config, array $options)
    {
        list($username, $password) = [
            Arr::get($config, 'username'), Arr::get($config, 'password'),
        ];

        try {
            return $this->createPdoConnection(
                $dsn, $username, $password, $options
            );
        } catch (Exception $e) {
            return $this->tryAgainIfCausedByLostConnection(
                $e, $dsn, $username, $password, $options
            );
        }
    }

    /*
     * Create a new PDO connection instance.
     *
     * @param  string  $dsn
     * @param  string  $username
     * @param  string  $password
     * @param  array  $options
     * @return \PDO
     */
    protected function createPdoConnection($dsn, $username, $password, $options)
    {
        if (class_exists(PDOConnection::class) && ! $this->isPersistentConnection($options)) {
            return new PDOConnection($dsn, $username, $password, $options);
        }

        return new PDO($dsn, $username, $password, $options);
    }

    /*
     * 判断连接是否是持久的
     *
     * @param  array  $options
     * @return bool
     */
    protected function isPersistentConnection($options)
    {
        return isset($options[PDO::ATTR_PERSISTENT]) &&
               $options[PDO::ATTR_PERSISTENT];
    }

    /*
     * Handle an exception that occurred during connect execution.
     *
     * @param  \Exception  $e
     * @param  string  $dsn
     * @param  string  $username
     * @param  string  $password
     * @param  array   $options
     * @return \PDO
     *
     * @throws \Exception
     */
    protected function tryAgainIfCausedByLostConnection(Exception $e, $dsn, $username, $password, $options)
    {
        if ($this->causedByLostConnection($e)) {
            return $this->createPdoConnection($dsn, $username, $password, $options);
        }

        throw $e;
    }

    /*
     * 获取配置项与默认 PDO 连接配置项的并集
     *
     * @param  array  $config
     * @return array
     */
    public function getOptions(array $config)
    {
        $options = Arr::get($config, 'options', []);

        return array_diff_key($this->options, $options) + $options;
    }

    /*
     * 获取默认的 PDO 连接参数
     *
     * @return array
     */
    public function getDefaultOptions()
    {
        return $this->options;
    }

    /*
     * 设置默认的 PDO 连接参数
     *
     * @param  array  $options
     * @return void
     */
    public function setDefaultOptions(array $options)
    {
        $this->options = $options;
    }
}
