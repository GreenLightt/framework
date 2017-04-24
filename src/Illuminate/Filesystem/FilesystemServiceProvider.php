<?php

namespace Illuminate\Filesystem;

use Illuminate\Support\ServiceProvider;

class FilesystemServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerNativeFilesystem();

        $this->registerFlysystem();
    }

    /**
     * Register the native filesystem implementation.
     *
     * @return void
     */
    protected function registerNativeFilesystem()
    {
        $this->app->singleton('files', function () {
            return new Filesystem;
        });
    }

    /**
     * Register the driver based filesystem.
     *
     * @return void
     */
    protected function registerFlysystem()
    {
        $this->registerManager();

        $this->app->singleton('filesystem.disk', function () {
            return $this->app['filesystem']->disk($this->getDefaultDriver());
        });

        $this->app->singleton('filesystem.cloud', function () {
            return $this->app['filesystem']->disk($this->getCloudDriver());
        });
    }

    /*
     * 注册文件系统管理
     *
     * @return void
     */
    protected function registerManager()
    {
        $this->app->singleton('filesystem', function () {
            return new FilesystemManager($this->app);
        });
    }

    /*
     * 获取默认的文件存储驱动
     *
     * @return string
     */
    protected function getDefaultDriver()
    {
        return $this->app['config']['filesystems.default'];
    }

    /*
     * 获取云文件存储驱动
     *
     * @return string
     */
    protected function getCloudDriver()
    {
        return $this->app['config']['filesystems.cloud'];
    }
}
