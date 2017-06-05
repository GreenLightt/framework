<?php

namespace Illuminate\Foundation\Auth\Access;

use Illuminate\Contracts\Auth\Access\Gate;

trait Authorizable
{
    /*
     * 判断用户是否有权限
     *
     * @param  string  $ability
     * @param  array|mixed  $arguments
     * @return bool
     */
    public function can($ability, $arguments = [])
    {
        return app(Gate::class)->forUser($this)->check($ability, $arguments);
    }

    /*
     * Determine if the entity does not have a given ability.
     *
     * @param  string  $ability
     * @param  array|mixed  $arguments
     * @return bool
     */
    public function cant($ability, $arguments = [])
    {
        return ! $this->can($ability, $arguments);
    }

    /*
     * Determine if the entity does not have a given ability.
     *
     * @param  string  $ability
     * @param  array|mixed  $arguments
     * @return bool
     */
    public function cannot($ability, $arguments = [])
    {
        return $this->cant($ability, $arguments);
    }
}
