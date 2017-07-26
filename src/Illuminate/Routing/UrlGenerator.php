<?php

namespace Illuminate\Routing;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Contracts\Routing\UrlGenerator as UrlGeneratorContract;

class UrlGenerator implements UrlGeneratorContract
{
    use Macroable;

    /*
     * route collection 集合
     *
     * @var \Illuminate\Routing\RouteCollection
     */
    protected $routes;

    /*
     * request 请求实例
     *
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * The forced URL root.
     *
     * @var string
     */
    protected $forcedRoot;

    /*
     * url 强制设定的 schema
     *
     * @var string
     */
    protected $forceScheme;

    /**
     * A cached copy of the URL root for the current request.
     *
     * @var string|null
     */
    protected $cachedRoot;

    /**
     * A cached copy of the URL schema for the current request.
     *
     * @var string|null
     */
    protected $cachedSchema;

    /**
     * The root namespace being applied to controller actions.
     *
     * @var string
     */
    protected $rootNamespace;

    /**
     * The session resolver callable.
     *
     * @var callable
     */
    protected $sessionResolver;

    /**
     * The callback to use to format hosts.
     *
     * @var \Closure
     */
    protected $formatHostUsing;

    /**
     * The callback to use to format paths.
     *
     * @var \Closure
     */
    protected $formatPathUsing;

    /**
     * The route URL generator instance.
     *
     * @var \Illuminate\Routing\RouteUrlGenerator
     */
    protected $routeGenerator;

    /*
     * 创建一个 url generator 实例
     *
     * @param  \Illuminate\Routing\RouteCollection  $routes
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    public function __construct(RouteCollection $routes, Request $request)
    {
        $this->routes = $routes;

        $this->setRequest($request);
    }

    /*
     * 获取当前请求的完整 url
     *
     * @return string
     */
    public function full()
    {
        return $this->request->fullUrl();
    }

    /*
     * 获取当前请求的 url
     *
     * @return string
     */
    public function current()
    {
        return $this->to($this->request->getPathInfo());
    }

    /*
     * 获取前一次请求的 url
     *
     * @param  mixed  $fallback
     * @return string
     */
    public function previous($fallback = false)
    {
        $referrer = $this->request->headers->get('referer');

        $url = $referrer ? $this->to($referrer) : $this->getPreviousUrlFromSession();

        if ($url) {
            return $url;
        } elseif ($fallback) {
            return $this->to($fallback);
        } else {
            return $this->to('/');
        }
    }

    /*
     * 从 session 中读取上一次的 url
     *
     * @return string|null
     */
    protected function getPreviousUrlFromSession()
    {
        $session = $this->getSession();

        return $session ? $session->previousUrl() : null;
    }

    /*
     * 给定 path 生成 url
     *
     * @param  string  $path
     * @param  mixed  $extra
     * @param  bool|null  $secure
     * @return string
     */
    public function to($path, $extra = [], $secure = null)
    {
        // First we will check if the URL is already a valid URL. If it is we will not
        // try to generate a new one but will simply return the URL as is, which is
        // convenient since developers do not always have to check if it's valid.
        if ($this->isValidUrl($path)) {
            return $path;
        }

        $tail = implode('/', array_map(
            'rawurlencode', (array) $this->formatParameters($extra))
        );

        // Once we have the scheme we will compile the "tail" by collapsing the values
        // into a single string delimited by slashes. This just makes it convenient
        // for passing the array of parameters to this URL as a list of segments.
        $root = $this->formatRoot($this->formatScheme($secure));

        list($path, $query) = $this->extractQueryString($path);

        return $this->format(
            $root, '/'.trim($path.'/'.$tail, '/')
        ).$query;
    }

    /*
     * 给定 path 生成 https url
     *
     * @param  string  $path
     * @param  array   $parameters
     * @return string
     */
    public function secure($path, $parameters = [])
    {
        return $this->to($path, $parameters, true);
    }

    /*
     * Generate the URL to an application asset.
     *
     * @param  string  $path
     * @param  bool|null  $secure
     * @return string
     */
    public function asset($path, $secure = null)
    {
        if ($this->isValidUrl($path)) {
            return $path;
        }

        // Once we get the root URL, we will check to see if it contains an index.php
        // file in the paths. If it does, we will remove it since it is not needed
        // for asset paths, but only for routes to endpoints in the application.
        $root = $this->formatRoot($this->formatScheme($secure));

        return $this->removeIndex($root).'/'.trim($path, '/');
    }

    /*
     * Generate the URL to a secure asset.
     *
     * @param  string  $path
     * @return string
     */
    public function secureAsset($path)
    {
        return $this->asset($path, true);
    }

    /*
     * Generate the URL to an asset from a custom root domain such as CDN, etc.
     *
     * @param  string  $root
     * @param  string  $path
     * @param  bool|null  $secure
     * @return string
     */
    public function assetFrom($root, $path, $secure = null)
    {
        // Once we get the root URL, we will check to see if it contains an index.php
        // file in the paths. If it does, we will remove it since it is not needed
        // for asset paths, but only for routes to endpoints in the application.
        $root = $this->formatRoot($this->formatScheme($secure), $root);

        return $this->removeIndex($root).'/'.trim($path, '/');
    }

    /*
     * 从路径中删除 index.php
     *
     * @param  string  $root
     * @return string
     */
    protected function removeIndex($root)
    {
        $i = 'index.php';

        return Str::contains($root, $i) ? str_replace('/'.$i, '', $root) : $root;
    }

    /*
     * 获取默认的 scheme
     *
     * @param  bool|null  $secure
     * @return string
     */
    public function formatScheme($secure)
    {
        if (! is_null($secure)) {
            return $secure ? 'https://' : 'http://';
        }

        if (is_null($this->cachedSchema)) {
            $this->cachedSchema = $this->forceScheme ?: $this->request->getScheme().'://';
        }

        return $this->cachedSchema;
    }

    /*
     * 根据路由的 as 名生成 url
     *
     * @param  string  $name
     * @param  mixed   $parameters
     * @param  bool  $absolute
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public function route($name, $parameters = [], $absolute = true)
    {
        if (! is_null($route = $this->routes->getByName($name))) {
            return $this->toRoute($route, $parameters, $absolute);
        }

        throw new InvalidArgumentException("Route [{$name}] not defined.");
    }

    /*
     * 根据路由实例 名生成 url
     *
     * @param  \Illuminate\Routing\Route  $route
     * @param  mixed  $parameters
     * @param  bool   $absolute
     * @return string
     *
     * @throws \Illuminate\Routing\Exceptions\UrlGenerationException
     */
    protected function toRoute($route, $parameters, $absolute)
    {
        return $this->routeUrl()->to(
            $route, $this->formatParameters($parameters), $absolute
        );
    }

    /*
     * 根据 controller action 生成 url
     *
     * @param  string  $action
     * @param  mixed   $parameters
     * @param  bool    $absolute
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public function action($action, $parameters = [], $absolute = true)
    {
        if (is_null($route = $this->routes->getByAction($action = $this->formatAction($action)))) {
            throw new InvalidArgumentException("Action {$action} not defined.");
        }

        return $this->toRoute($route, $parameters, $absolute);
    }

    /*
     * 格式化给定的 controller action
     *
     * @param  string  $action
     * @return string
     */
    protected function formatAction($action)
    {
        if ($this->rootNamespace && ! (strpos($action, '\\') === 0)) {
            return $this->rootNamespace.'\\'.$action;
        } else {
            return trim($action, '\\');
        }
    }

    /*
     * Format the array of URL parameters.
     *
     * @param  mixed|array  $parameters
     * @return array
     */
    public function formatParameters($parameters)
    {
        $parameters = array_wrap($parameters);

        foreach ($parameters as $key => $parameter) {
            if ($parameter instanceof UrlRoutable) {
                $parameters[$key] = $parameter->getRouteKey();
            }
        }

        return $parameters;
    }

    /*
     * 从指定的 path 分离路径和 query 查询
     *
     * @param  string  $path
     * @return array
     */
    protected function extractQueryString($path)
    {
        if (($queryPosition = strpos($path, '?')) !== false) {
            return [
                substr($path, 0, $queryPosition),
                substr($path, $queryPosition),
            ];
        }

        return [$path, ''];
    }

    /*
     * Get the base URL for the request.
     *
     * @param  string  $scheme
     * @param  string  $root
     * @return string
     */
    public function formatRoot($scheme, $root = null)
    {
        if (is_null($root)) {
            if (is_null($this->cachedRoot)) {
                $this->cachedRoot = $this->forcedRoot ?: $this->request->root();
            }

            $root = $this->cachedRoot;
        }

        $start = Str::startsWith($root, 'http://') ? 'http://' : 'https://';

        return preg_replace('~'.$start.'~', $scheme, $root, 1);
    }

    /*
     * 格式化成 url
     *
     * @param  string  $root
     * @param  string  $path
     * @return string
     */
    public function format($root, $path)
    {
        $path = '/'.trim($path, '/');

        if ($this->formatHostUsing) {
            $root = call_user_func($this->formatHostUsing, $root);
        }

        if ($this->formatPathUsing) {
            $path = call_user_func($this->formatPathUsing, $path);
        }

        return trim($root.$path, '/');
    }

    /*
     * 判断给定的 path 是否是完整的 url
     *
     * @param  string  $path
     * @return bool
     */
    public function isValidUrl($path)
    {
        if (! preg_match('~^(#|//|https?://|mailto:|tel:)~', $path)) {
            return filter_var($path, FILTER_VALIDATE_URL) !== false;
        }

        return true;
    }

    /*
     * 获取 route url generator 实例
     *
     * @return \Illuminate\Routing\RouteUrlGenerator
     */
    protected function routeUrl()
    {
        if (! $this->routeGenerator) {
            $this->routeGenerator = new RouteUrlGenerator($this, $this->request);
        }

        return $this->routeGenerator;
    }

    /*
     * 设置默认的变量值
     *
     * @param  array  $defaults
     * @return void
     */
    public function defaults(array $defaults)
    {
        $this->routeUrl()->defaults($defaults);
    }

    /*
     * 设置 scheme
     *
     * @param  string  $schema
     * @return void
     */
    public function forceScheme($schema)
    {
        $this->cachedSchema = null;

        $this->forceScheme = $schema.'://';
    }

    /*
     * 设置根目录
     *
     * @param  string  $root
     * @return void
     */
    public function forceRootUrl($root)
    {
        $this->forcedRoot = rtrim($root, '/');

        $this->cachedRoot = null;
    }

    /*
     * Set a callback to be used to format the host of generated URLs.
     *
     * @param  \Closure  $callback
     * @return $this
     */
    public function formatHostUsing(Closure $callback)
    {
        $this->formatHostUsing = $callback;

        return $this;
    }

    /*
     * Set a callback to be used to format the path of generated URLs.
     *
     * @param  \Closure  $callback
     * @return $this
     */
    public function formatPathUsing(Closure $callback)
    {
        $this->formatPathUsing = $callback;

        return $this;
    }

    /*
     * Get the path formatter being used by the URL generator.
     *
     * @return \Closure
     */
    public function pathFormatter()
    {
        return $this->formatPathUsing ?: function ($path) {
            return $path;
        };
    }

    /*
     * 获取当前请求实例
     *
     * @return \Illuminate\Http\Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /*
     * 设置当前 request 实例
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;

        $this->cachedRoot = null;
        $this->cachedSchema = null;
        $this->routeGenerator = null;
    }

    /*
     * 设置 route 集合实例
     *
     * @param  \Illuminate\Routing\RouteCollection  $routes
     * @return $this
     */
    public function setRoutes(RouteCollection $routes)
    {
        $this->routes = $routes;

        return $this;
    }

    /*
     * 从 session 解析器中获取 session 实例
     *
     * @return \Illuminate\Session\Store|null
     */
    protected function getSession()
    {
        if ($this->sessionResolver) {
            return call_user_func($this->sessionResolver);
        }
    }

    /*
     * 设置 session 解析器
     *
     * @param  callable  $sessionResolver
     * @return $this
     */
    public function setSessionResolver(callable $sessionResolver)
    {
        $this->sessionResolver = $sessionResolver;

        return $this;
    }

    /*
     * 设置 crontroller 所在的命名空间
     *
     * @param  string  $rootNamespace
     * @return $this
     */
    public function setRootControllerNamespace($rootNamespace)
    {
        $this->rootNamespace = $rootNamespace;

        return $this;
    }
}
