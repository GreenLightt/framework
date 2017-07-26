<?php

namespace Illuminate\Routing;

use Illuminate\Database\Eloquent\Model;

class ImplicitRouteBinding
{
    /*
     * 解析路由模型绑定
     * Resolve the implicit route bindings for the given route.
     *
     * @param  \Illuminate\Container\Container  $container
     * @param  \Illuminate\Routing\Route  $route
     * @return void
     */
    public static function resolveForRoute($container, $route)
    {
        $parameters = $route->parameters();

        foreach ($route->signatureParameters(Model::class) as $parameter) {
            if (! $parameterName = static::getParameterName($parameter->name, $parameters)) {
                continue;
            }

            $parameterValue = $parameters[$parameterName];

            if ($parameterValue instanceof Model) {
                continue;
            }

            $model = $container->make($parameter->getClass()->name);

            $route->setParameter($parameterName, $model->where(
                $model->getRouteKeyName(), $parameterValue
            )->firstOrFail());
        }
    }

    /*
     * 从参数组中，找到参数在数据中对应的真实键名
     *
     * @param  string  $name
     * @param  array  $parameters
     * @return string|null
     */
    protected static function getParameterName($name, $parameters)
    {
        if (array_key_exists($name, $parameters)) {
            return $name;
        }

        $snakedName = snake_case($name);

        if (array_key_exists($snakedName, $parameters)) {
            return $snakedName;
        }
    }
}
