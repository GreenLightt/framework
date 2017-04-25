<?php

namespace Illuminate\Contracts\Config;

interface Repository
{
    /*
     * 判断指定的配置项是否存在
     *
     * @param  string  $key
     * @return bool
     */
    public function has($key);

    /*
     * 根据键名，获取指定的配置项值
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    public function get($key, $default = null);

    /*
     * 获取所有配置项
     *
     * @return array
     */
    public function all();

    /*
     * 设置配置项值
     *
     * @param  array|string  $key
     * @param  mixed   $value
     * @return void
     */
    public function set($key, $value = null);

    /*
     * 将值插入到指定键名的配置项值的头部
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return void
     */
    public function prepend($key, $value);

    /*
     * 新增键值对进指定键名的配置项中
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return void
     */
    public function push($key, $value);
}
