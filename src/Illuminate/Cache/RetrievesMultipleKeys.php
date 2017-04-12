<?php

namespace Illuminate\Cache;

trait RetrievesMultipleKeys
{
    /**
     * 根据一堆键名，分别查找其对应的键值，如果找不到，则该键名对应的键值为 null
     *
     * @param  array  $keys
     * @return array
     */
    public function many(array $keys)
    {
        $return = [];

        foreach ($keys as $key) {
            $return[$key] = $this->get($key);
        }

        return $return;
    }

    /**
     * 存储一堆给定键值对，并设置缓存时间
     *
     * @param  array  $values
     * @param  float|int  $minutes
     * @return void
     */
    public function putMany(array $values, $minutes)
    {
        foreach ($values as $key => $value) {
            $this->put($key, $value, $minutes);
        }
    }
}
