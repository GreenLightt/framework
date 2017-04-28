<?php

namespace Illuminate\Hashing;

use Illuminate\Support\ServiceProvider;

class HashServiceProvider extends ServiceProvider
{
    /*
     * 表明该服务提供者延迟加载
     *
     * @var bool
     */
    protected $defer = true;

    /*
     * 注册服务提供者
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('hash', function () {
            return new BcryptHasher;
        });
    }

    /*
     * 配合$defer变量，返回需要延迟加载的服务名称
     *
     * @return array
     */
    public function provides()
    {
        return ['hash'];
    }
}
