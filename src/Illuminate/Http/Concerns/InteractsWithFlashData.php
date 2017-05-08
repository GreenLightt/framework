<?php

namespace Illuminate\Http\Concerns;

trait InteractsWithFlashData
{
    /*
     * 检索旧的请求参数
     *
     * @param  string  $key
     * @param  string|array|null  $default
     * @return string|array
     */
    public function old($key = null, $default = null)
    {
        return $this->session()->getOldInput($key, $default);
    }

    /*
     * 闪存所有当前请求参数至 session
     *
     * @return void
     */
    public function flash()
    {
        $this->session()->flashInput($this->input());
    }

    /*
     * 闪存指定请求参数至 session
     *
     * @param  array|mixed  $keys
     * @return void
     */
    public function flashOnly($keys)
    {
        $this->session()->flashInput(
            $this->only(is_array($keys) ? $keys : func_get_args())
        );
    }

    /*
     * 闪存除指定参数外的 request 请求参数至 session
     *
     * @param  array|mixed  $keys
     * @return void
     */
    public function flashExcept($keys)
    {
        $this->session()->flashInput(
            $this->except(is_array($keys) ? $keys : func_get_args())
        );
    }

    /*
     * 清空所有闪存信息
     *
     * @return void
     */
    public function flush()
    {
        $this->session()->flashInput([]);
    }
}
