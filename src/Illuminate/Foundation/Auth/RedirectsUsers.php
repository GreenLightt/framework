<?php

namespace Illuminate\Foundation\Auth;

trait RedirectsUsers
{
    /*
     * 获取 注册 或 登录 后的跳转地址
     *
     * @return string
     */
    public function redirectPath()
    {
        if (method_exists($this, 'redirectTo')) {
            return $this->redirectTo();
        }

        return property_exists($this, 'redirectTo') ? $this->redirectTo : '/home';
    }
}
