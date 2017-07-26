<?php

namespace Illuminate\Routing;

use Symfony\Component\Routing\Route as SymfonyRoute;

class RouteCompiler
{
    /*
     * 路由实例
     *
     * @var \Illuminate\Routing\Route
     */
    protected $route;

    /*
     * 创建一个路由编译实例
     *
     * @param  \Illuminate\Routing\Route  $route
     * @return void
     */
    public function __construct($route)
    {
        $this->route = $route;
    }

    /*
     * 编译路由
     *
     * @return \Symfony\Component\Routing\CompiledRoute
     */
    public function compile()
    {
        $optionals = $this->getOptionalParameters();

        $uri = preg_replace('/\{(\w+?)\?\}/', '{$1}', $this->route->uri());

        return (
            new SymfonyRoute($uri, $optionals, $this->route->wheres, [], $this->route->domain() ?: '')
        )->compile();
    }

    /*
     * 获取路由上的缺省参数
     *
     * @return array
     */
    protected function getOptionalParameters()
    {
        preg_match_all('/\{(\w+?)\?\}/', $this->route->uri(), $matches);

        return isset($matches[1]) ? array_fill_keys($matches[1], null) : [];
    }
}
