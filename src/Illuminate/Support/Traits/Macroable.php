<?php

namespace Illuminate\Support\Traits;

use Closure;
use BadMethodCallException;

trait Macroable
{
    /*
     * 数组存储注册的宏
     *
     * @var array
     */
    protected static $macros = [];

    /*
     * 注册一个自定义宏
     *
     * @param  string    $name
     * @param  callable  $macro
     * @return void
     */
    public static function macro($name, callable $macro)
    {
        static::$macros[$name] = $macro;
    }

    /*
     * 检查指定宏是否存在
     *
     * @param  string  $name
     * @return bool
     */
    public static function hasMacro($name)
    {
        return isset(static::$macros[$name]);
    }

    /*
     * 当调用的静态方法不存在或权限不足时，会自动调用 __callStatic 方法
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     *
     * @throws \BadMethodCallException
     */
    public static function __callStatic($method, $parameters)
    {
        if (! static::hasMacro($method)) {
            throw new BadMethodCallException("Method {$method} does not exist.");
        }

        if (static::$macros[$method] instanceof Closure) {
            return call_user_func_array(Closure::bind(static::$macros[$method], null, static::class), $parameters);
        }

        return call_user_func_array(static::$macros[$method], $parameters);
    }

    /*
     * 当要调用的方法不存在或权限不足时，会自动调用 __call 方法
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     *
     * @throws \BadMethodCallException
     */
    public function __call($method, $parameters)
    {
        if (! static::hasMacro($method)) {
            throw new BadMethodCallException("Method {$method} does not exist.");
        }

        if (static::$macros[$method] instanceof Closure) {
            // Closure 的 bindTo 方法中，第一个参数为绑定匿名函数的对象,
            // 第二个参数为作用域, 'static' 保持当前状态
            return call_user_func_array(static::$macros[$method]->bindTo($this, static::class), $parameters);
        }

        return call_user_func_array(static::$macros[$method], $parameters);
    }
}
