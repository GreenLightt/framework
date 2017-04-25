<?php

namespace Illuminate\Contracts\Support;

interface Jsonable
{
    /*
     * 将对象转成Json格式
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0);
}
