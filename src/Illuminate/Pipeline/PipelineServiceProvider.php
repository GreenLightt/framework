<?php

namespace Illuminate\Pipeline;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Pipeline\Hub as PipelineHubContract;

class PipelineServiceProvider extends ServiceProvider
{
    /*
     * 表明该服务提供者是否是延迟加载
     *
     * @var bool
     */
    protected $defer = true;

    /*
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(
            PipelineHubContract::class, Hub::class
        );
    }

    /*
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            PipelineHubContract::class,
        ];
    }
}
