<?php

namespace Illuminate\Contracts\Cache;

interface Factory
{
    /**
     * 根据指定的缓存系统名，返回不同的缓存实例对象；比如‘file’， ‘redis’
     *
     * @param  string|null  $name
     * @return mixed
     */
    public function store($name = null);
}
