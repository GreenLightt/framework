<?php

namespace Illuminate\Contracts\Support;

interface Htmlable
{
    /*
     * 内容转为 HTML 格式
     *
     * @return string
     */
    public function toHtml();
}
