<?php

namespace Illuminate\Http\Middleware;

use Closure;

class FrameGuard
{
    /**
     * Handle the given request and get the response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        // Iframe 框架调用，只允许本网站的框架内加载
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN', false);

        return $response;
    }
}
