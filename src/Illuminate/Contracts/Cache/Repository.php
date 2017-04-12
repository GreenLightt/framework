<?php

namespace Illuminate\Contracts\Cache;

use Closure;

interface Repository
{
    /**
     * 根据键名，判断是否存在键值对
     *
     * @param  string  $key
     * @return bool
     */
    public function has($key);

    /**
     * 根据键名，获取键值；如果不存在，返回默认值
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    public function get($key, $default = null);

    /**
     * 根据键名，获取键值，并从缓存中移除键值对
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    public function pull($key, $default = null);

    /**
     * 存储键值对，并设置缓存时间
     *
     * @param  string  $key
     * @param  mixed   $value
     * @param  \DateTime|float|int  $minutes
     * @return void
     */
    public function put($key, $value, $minutes);

    /**
     * 如果键值对在缓存中不存在，则添加至缓存，并返回 true; 否则返回 false
     *
     * @param  string  $key
     * @param  mixed   $value
     * @param  \DateTime|float|int  $minutes
     * @return bool
     */
    public function add($key, $value, $minutes);

    /**
     * 根据键名，给对应的键值增加 指定数目
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return int|bool
     */
    public function increment($key, $value = 1);

    /**
     * 根据键名，给对应的键值减少 指定数目
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return int|bool
     */
    public function decrement($key, $value = 1);

    /**
     * 永久缓存键值对
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function forever($key, $value);

    /**
     * 根据键名，查找键值；
     * 如果不存在，调用回调函数，将其结果作为键值存储并返回；
     *
     * @param  string  $key
     * @param  \DateTime|float|int  $minutes
     * @param  \Closure  $callback
     * @return mixed
     */
    public function remember($key, $minutes, Closure $callback);

    /**
     * 根据键名，查找键值；
     * 如果不存在，调用回调函数，将其结果作为键值永久存储并返回；
     * 同 ‘rememberForever’
     *
     * @param  string   $key
     * @param  \Closure  $callback
     * @return mixed
     */
    public function sear($key, Closure $callback);

    /**
     * 根据键名，查找键值；
     * 如果不存在，调用回调函数，将其结果作为键值永久存储并返回；
     *
     * @param  string   $key
     * @param  \Closure  $callback
     * @return mixed
     */
    public function rememberForever($key, Closure $callback);

    /**
     * 根据键名，从缓存手动移除键值对
     *
     * @param  string $key
     * @return bool
     */
    public function forget($key);
}
