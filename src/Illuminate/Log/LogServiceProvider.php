<?php

namespace Illuminate\Log;

use Monolog\Logger as Monolog;
use Illuminate\Support\ServiceProvider;

class LogServiceProvider extends ServiceProvider
{
    /*
     * 注册服务提供者
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('log', function () {
            return $this->createLogger();
        });
    }

    /*
     * 创建 log 日志器
     *
     * @return \Illuminate\Log\Writer
     */
    public function createLogger()
    {
        $log = new Writer(
            new Monolog($this->channel()), $this->app['events']
        );

        if ($this->app->hasMonologConfigurator()) {
            call_user_func($this->app->getMonologConfigurator(), $log->getMonolog());
        } else {
            $this->configureHandler($log);
        }

        return $log;
    }

    /*
     * 获取Log日志的频道名称
     *
     * @return string
     */
    protected function channel()
    {
        return $this->app->bound('env') ? $this->app->environment() : 'production';
    }

    /*
     * 配置日志的处理方式
     *
     * @param  \Illuminate\Log\Writer  $log
     * @return void
     */
    protected function configureHandler(Writer $log)
    {
        $this->{'configure'.ucfirst($this->handler()).'Handler'}($log);
    }

    /*
     * 配置日志单一处理方式
     *
     * @param  \Illuminate\Log\Writer  $log
     * @return void
     */
    protected function configureSingleHandler(Writer $log)
    {
        $log->useFiles(
            $this->app->storagePath().'/logs/laravel.log',
            $this->logLevel()
        );
    }

    /*
     * 配置每日生成的日志处理方式
     *
     * @param  \Illuminate\Log\Writer  $log
     * @return void
     */
    protected function configureDailyHandler(Writer $log)
    {
        $log->useDailyFiles(
            $this->app->storagePath().'/logs/laravel.log', $this->maxFiles(),
            $this->logLevel()
        );
    }

    /*
     * 配置：将日志记录到 syslog 中
     *
     * @param  \Illuminate\Log\Writer  $log
     * @return void
     */
    protected function configureSyslogHandler(Writer $log)
    {
        $log->useSyslog('laravel', $this->logLevel());
    }

    /*
     * 配置：将日志记录到 error_log 中
     *
     * @param  \Illuminate\Log\Writer  $log
     * @return void
     */
    protected function configureErrorlogHandler(Writer $log)
    {
        $log->useErrorLog($this->logLevel());
    }

    /*
     * 获取默认的日志处理方式
     *
     * @return string
     */
    protected function handler()
    {
        if ($this->app->bound('config')) {
            return $this->app->make('config')->get('app.log', 'single');
        }

        return 'single';
    }

    /*
     * 获取日志的错误等级
     *
     * @return string
     */
    protected function logLevel()
    {
        if ($this->app->bound('config')) {
            return $this->app->make('config')->get('app.log_level', 'debug');
        }

        return 'debug';
    }

    /*
     * 获取应用中能保持连接的最大文件数
     *
     * @return int
     */
    protected function maxFiles()
    {
        if ($this->app->bound('config')) {
            return $this->app->make('config')->get('app.log_max_files', 5);
        }

        return 0;
    }
}
