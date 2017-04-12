<?php

namespace Illuminate\Contracts\Cache;

interface Store
{
    /**
     * 根据键名，从缓存中找到键值
     *
     * @param  string|array  $key
     * @return mixed
     */
    public function get($key);

    /**
     * 根据一堆键名，分别查找其对应的键值，如果找不到，则该键名对应的键值为 null
     *
     * @param  array  $keys
     * @return array
     */
    public function many(array $keys);

    /**
     * 存储给定键值对，并设置缓存时间
     *
     * @param  string  $key
     * @param  mixed   $value
     * @param  float|int  $minutes
     * @return void
     */
    public function put($key, $value, $minutes);

    /**
     * 存储一堆给定键值对，并设置缓存时间
     *
     * @param  array  $values
     * @param  float|int  $minutes
     * @return void
     */
    public function putMany(array $values, $minutes);

    /**
     * 指定键名的值，增加给定数目
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return int|bool
     */
    public function increment($key, $value = 1);

    /**
     * 指定键名的值，减少给定数目
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return int|bool
     */
    public function decrement($key, $value = 1);

    /**
     * 永久保存给定键值对，到缓存
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function forever($key, $value);

    /**
     * 根据键名，从缓存手动移除
     *
     * @param  string  $key
     * @return bool
     */
    public function forget($key);

    /**
     * 从缓存中，移除所有键值对
     *
     * @return bool
     */
    public function flush();

    /**
     * 获取键名的前缀
     *
     * @return string
     */
    public function getPrefix();
}
