<?php

namespace Illuminate\Support;

use Illuminate\Contracts\Support\Htmlable;

class HtmlString implements Htmlable
{
    /**
     * The HTML string.
     *
     * @var string
     */
    protected $html;

    /*
     * 创建一个 HTMLString 实例
     *
     * @param  string  $html
     * @return void
     */
    public function __construct($html)
    {
        $this->html = $html;
    }

    /*
     * 获取 Html 内容
     *
     * @return string
     */
    public function toHtml()
    {
        return $this->html;
    }

    /*
     * 获取 Html 内容
     *
     * @return string
     */
    public function __toString()
    {
        return $this->toHtml();
    }
}
