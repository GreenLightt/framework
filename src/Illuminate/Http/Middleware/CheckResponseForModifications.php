<?php

namespace Illuminate\Http\Middleware;

use Closure;
use Symfony\Component\HttpFoundation\Response;

class CheckResponseForModifications
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        // 根据 request 请求中的 If-Modified-Since 和 If-None-Match 字段
        // 判断请求资源是否在客户端处是最新的，如果为 true , 则修改 respnse
        // 状态码为304, 并清空 返回内容, 由客户端自行读取本地资源
        if ($response instanceof Response) {
            $response->isNotModified($request);
        }

        return $response;
    }
}
