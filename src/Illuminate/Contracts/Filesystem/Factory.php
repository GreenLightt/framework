<?php

namespace Illuminate\Contracts\Filesystem;

interface Factory
{
    /*
     * 获取一个文件系统实例
     *
     * @param  string  $name
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     */
    public function disk($name = null);
}
