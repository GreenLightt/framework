<?php

namespace Illuminate\Session;

use Illuminate\Support\ServiceProvider;
use Illuminate\Session\Middleware\StartSession;

class SessionServiceProvider extends ServiceProvider
{
    /**
     * 服务提供者的注册服务
     *
     * @return void
     */
    public function register()
    {
        $this->registerSessionManager();

        $this->registerSessionDriver();

        $this->app->singleton(StartSession::class);
    }

    /**
     * 注册 session 管理者对象
     *
     * @return void
     */
    protected function registerSessionManager()
    {
        $this->app->singleton('session', function ($app) {
            return new SessionManager($app);
        });
    }

    /**
     * 注册 session 驱动实例
     *
     * @return void
     */
    protected function registerSessionDriver()
    {
        $this->app->singleton('session.store', function ($app) {
            // First, we will create the session manager which is responsible for the
            // creation of the various session drivers when they are needed by the
            // application instance, and will resolve them on a lazy load basis.
            return $app->make('session')->driver();
        });
    }
}
