<?php

namespace Illuminate\Support;

use Countable;
use Exception;
use ArrayAccess;
use Traversable;
use ArrayIterator;
use CachingIterator;
use JsonSerializable;
use IteratorAggregate;
use InvalidArgumentException;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Support\Arrayable;

class Collection implements ArrayAccess, Arrayable, Countable, IteratorAggregate, Jsonable, JsonSerializable
{
    use Macroable;

    /*
     * 集合中的元素
     *
     * @var array
     */
    protected $items = [];

    /**
     * The methods that can be proxied.
     *
     * @var array
     */
    protected static $proxies = [
        'contains', 'each', 'every', 'filter', 'first', 'flatMap',
        'map', 'partition', 'reject', 'sortBy', 'sortByDesc', 'sum',
    ];

    /*
     * 创建一个新的集合
     *
     * @param  mixed  $items
     * @return void
     */
    public function __construct($items = [])
    {
        $this->items = $this->getArrayableItems($items);
    }

    /*
     * 创建一个新的 collection 实例
     *
     * @param  mixed  $items
     * @return static
     */
    public static function make($items = [])
    {
        return new static($items);
    }

    /**
     * Create a new collection by invoking the callback a given amount of times.
     *
     * @param  int  $amount
     * @param  callable  $callback
     * @return static
     */
    public static function times($amount, callable $callback)
    {
        if ($amount < 1) {
            return new static;
        }

        return (new static(range(1, $amount)))->map($callback);
    }

    /*
     * 获取集合中的所有元素
     *
     * @return array
     */
    public function all()
    {
        return $this->items;
    }

    /*
     * 求平均值
     *
     * @param  callable|string|null  $callback
     * @return mixed
     */
    public function avg($callback = null)
    {
        if ($count = $this->count()) {
            return $this->sum($callback) / $count;
        }
    }

    /*
     * avg 方法的别名
     *
     * @param  callable|string|null  $callback
     * @return mixed
     */
    public function average($callback = null)
    {
        return $this->avg($callback);
    }

    /*
     * 获取中位数
     *
     * @param  null $key
     * @return mixed
     */
    public function median($key = null)
    {
        $count = $this->count();

        if ($count == 0) {
            return;
        }

        $values = with(isset($key) ? $this->pluck($key) : $this)
                    ->sort()->values();

        $middle = (int) ($count / 2);

        if ($count % 2) {
            return $values->get($middle);
        }

        return (new static([
            $values->get($middle - 1), $values->get($middle),
        ]))->average();
    }

    /*
     * 获取出现指定键的键值次数最多的键值
     *
     * @param  mixed  $key
     * @return array|null
     */
    public function mode($key = null)
    {
        $count = $this->count();

        if ($count == 0) {
            return;
        }

        $collection = isset($key) ? $this->pluck($key) : $this;

        $counts = new self;

        $collection->each(function ($value) use ($counts) {
            $counts[$value] = isset($counts[$value]) ? $counts[$value] + 1 : 1;
        });

        $sorted = $counts->sort();

        $highestValue = $sorted->last();

        return $sorted->filter(function ($value) use ($highestValue) {
            return $value == $highestValue;
        })->sort()->keys()->all();
    }

    /*
     * 多维数组集合收缩成一个一维数组
     *
     * @return static
     */
    public function collapse()
    {
        return new static(Arr::collapse($this->items));
    }

    /*
     * 判断集合中是否存在指定键值对
     *
     * @param  mixed  $key
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return bool
     */
    public function contains($key, $operator = null, $value = null)
    {
        if (func_num_args() == 1) {
            if ($this->useAsCallable($key)) {
                return ! is_null($this->first($key));
            }

            return in_array($key, $this->items);
        }

        if (func_num_args() == 2) {
            $value = $operator;

            $operator = '=';
        }

        return $this->contains($this->operatorForWhere($key, $operator, $value));
    }

    /*
     * 判断集合中是否存在指定键值对, 严格模式
     *
     * @param  mixed  $key
     * @param  mixed  $value
     * @return bool
     */
    public function containsStrict($key, $value = null)
    {
        if (func_num_args() == 2) {
            return $this->contains(function ($item) use ($key, $value) {
                return data_get($item, $key) === $value;
            });
        }

        if ($this->useAsCallable($key)) {
            return ! is_null($this->first($key));
        }

        return in_array($key, $this->items, true);
    }

    /*
     * 比较两个数组的键值，并返回差集
     *
     * @param  mixed  $items
     * @return static
     */
    public function diff($items)
    {
        return new static(array_diff($this->items, $this->getArrayableItems($items)));
    }

    /*
     * 比较两个数组的键名，并返回差集
     *
     * @param  mixed  $items
     * @return static
     */
    public function diffKeys($items)
    {
        return new static(array_diff_key($this->items, $this->getArrayableItems($items)));
    }

    /*
     * 迭代集合中的数据项并传递每个数据项到给定回调
     *
     * @param  callable  $callback
     * @return $this
     */
    public function each(callable $callback)
    {
        foreach ($this->items as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }

        return $this;
    }

    /*
     * 判断是否所有元素都通过测试
     *
     * @param  string|callable  $key
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return bool
     */
    public function every($key, $operator = null, $value = null)
    {
        if (func_num_args() == 1) {
            $callback = $this->valueRetriever($key);

            foreach ($this->items as $k => $v) {
                if (! $callback($v, $k)) {
                    return false;
                }
            }

            return true;
        }

        if (func_num_args() == 2) {
            $value = $operator;

            $operator = '=';
        }

        return $this->every($this->operatorForWhere($key, $operator, $value));
    }

    /*
     * 获取除指定键值对外的所有元素
     *
     * @param  mixed  $keys
     * @return static
     */
    public function except($keys)
    {
        $keys = is_array($keys) ? $keys : func_get_args();

        return new static(Arr::except($this->items, $keys));
    }

    /*
     * 调用回调函数，过滤元素
     *
     * @param  callable|null  $callback
     * @return static
     */
    public function filter(callable $callback = null)
    {
        if ($callback) {
            return new static(Arr::where($this->items, $callback));
        }

        return new static(array_filter($this->items));
    }

    /*
     * 如何 $value 为 true, 执行回调函数
     *
     * @param  bool  $value
     * @param  callable  $callback
     * @param  callable  $default
     * @return mixed
     */
    public function when($value, callable $callback, callable $default = null)
    {
        if ($value) {
            return $callback($this);
        } elseif ($default) {
            return $default($this);
        }

        return $this;
    }

    /*
     * 根据指定键值操作对象过滤元素
     *
     * @param  string  $key
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return static
     */
    public function where($key, $operator, $value = null)
    {
        if (func_num_args() == 2) {
            $value = $operator;

            $operator = '=';
        }

        return $this->filter($this->operatorForWhere($key, $operator, $value));
    }

    /*
     * 获取操作闭包
     *
     * @param  string  $key
     * @param  string  $operator
     * @param  mixed  $value
     * @return \Closure
     */
    protected function operatorForWhere($key, $operator, $value)
    {
        return function ($item) use ($key, $operator, $value) {
            $retrieved = data_get($item, $key);

            switch ($operator) {
                default:
                case '=':
                case '==':  return $retrieved == $value;
                case '!=':
                case '<>':  return $retrieved != $value;
                case '<':   return $retrieved < $value;
                case '>':   return $retrieved > $value;
                case '<=':  return $retrieved <= $value;
                case '>=':  return $retrieved >= $value;
                case '===': return $retrieved === $value;
                case '!==': return $retrieved !== $value;
            }
        };
    }

    /*
     * 过滤元素，使用严格的比较
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return static
     */
    public function whereStrict($key, $value)
    {
        return $this->where($key, '===', $value);
    }

    /*
     * whereIn 过滤
     *
     * @param  string  $key
     * @param  mixed  $values
     * @param  bool  $strict
     * @return static
     */
    public function whereIn($key, $values, $strict = false)
    {
        $values = $this->getArrayableItems($values);

        return $this->filter(function ($item) use ($key, $values, $strict) {
            return in_array(data_get($item, $key), $values, $strict);
        });
    }

    /*
     * 严格的 whereIn 过滤
     *
     * @param  string  $key
     * @param  mixed  $values
     * @return static
     */
    public function whereInStrict($key, $values)
    {
        return $this->whereIn($key, $values, true);
    }

    /*
     * whereNotIn 过滤
     *
     * @param  string  $key
     * @param  mixed  $values
     * @param  bool  $strict
     * @return static
     */
    public function whereNotIn($key, $values, $strict = false)
    {
        $values = $this->getArrayableItems($values);

        return $this->reject(function ($item) use ($key, $values, $strict) {
            return in_array(data_get($item, $key), $values, $strict);
        });
    }

    /*
     * 严格地 whereNotIn 过滤
     *
     * @param  string  $key
     * @param  mixed  $values
     * @return static
     */
    public function whereNotInStrict($key, $values)
    {
        return $this->whereNotIn($key, $values, true);
    }

    /*
     * 获取集合中的第一个元素
     *
     * @param  callable|null  $callback
     * @param  mixed  $default
     * @return mixed
     */
    public function first(callable $callback = null, $default = null)
    {
        return Arr::first($this->items, $callback, $default);
    }

    /*
     * 多维度的集合变成一维的;
     *
     * @param  int  $depth
     * @return static
     */
    public function flatten($depth = INF)
    {
        return new static(Arr::flatten($this->items, $depth));
    }

    /*
     * 反转集合中的元素键名与键值
     *
     * @return static
     */
    public function flip()
    {
        return new static(array_flip($this->items));
    }

    /*
     * 删除元素值
     *
     * @param  string|array  $keys
     * @return $this
     */
    public function forget($keys)
    {
        foreach ((array) $keys as $key) {
            $this->offsetUnset($key);
        }

        return $this;
    }

    /*
     * 获取元素值
     *
     * @param  mixed  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        if ($this->offsetExists($key)) {
            return $this->items[$key];
        }

        return value($default);
    }

    /*
     * 关联数组分组
     *
     * @param  callable|string  $groupBy
     * @param  bool  $preserveKeys
     * @return static
     */
    public function groupBy($groupBy, $preserveKeys = false)
    {
        $groupBy = $this->valueRetriever($groupBy);

        $results = [];

        foreach ($this->items as $key => $value) {
            $groupKeys = $groupBy($value, $key);

            if (! is_array($groupKeys)) {
                $groupKeys = [$groupKeys];
            }

            foreach ($groupKeys as $groupKey) {
                $groupKey = is_bool($groupKey) ? (int) $groupKey : $groupKey;

                if (! array_key_exists($groupKey, $results)) {
                    $results[$groupKey] = new static;
                }

                $results[$groupKey]->offsetSet($preserveKeys ? $key : null, $value);
            }
        }

        return new static($results);
    }

    /*
     * 关联数组重置键名
     *
     * @param  callable|string  $keyBy
     * @return static
     */
    public function keyBy($keyBy)
    {
        $keyBy = $this->valueRetriever($keyBy);

        $results = [];

        foreach ($this->items as $key => $item) {
            $resolvedKey = $keyBy($item, $key);

            if (is_object($resolvedKey)) {
                $resolvedKey = (string) $resolvedKey;
            }

            $results[$resolvedKey] = $item;
        }

        return new static($results);
    }

    /*
     * 判断集合中是否存在指定键
     *
     * @param  mixed  $key
     * @return bool
     */
    public function has($key)
    {
        return $this->offsetExists($key);
    }

    /*
     * 连接集合中的数据项
     *
     * @param  string  $value
     * @param  string  $glue
     * @return string
     */
    public function implode($value, $glue = null)
    {
        $first = $this->first();

        if (is_array($first) || is_object($first)) {
            return implode($glue, $this->pluck($value)->all());
        }

        return implode($value, $this->items);
    }

    /*
     * 比较两个数组的键值，并返回交集
     *
     * @param  mixed  $items
     * @return static
     */
    public function intersect($items)
    {
        return new static(array_intersect($this->items, $this->getArrayableItems($items)));
    }

    /*
     * 判断集合是否为空
     *
     * @return bool
     */
    public function isEmpty()
    {
        return empty($this->items);
    }

    /*
     * 判断集合是否为非空
     *
     * @return bool
     */
    public function isNotEmpty()
    {
        return ! $this->isEmpty();
    }

    /*
     * 判断指定对象是 callable 对象，且不是字符串
     *
     * @param  mixed  $value
     * @return bool
     */
    protected function useAsCallable($value)
    {
        return ! is_string($value) && is_callable($value);
    }

    /*
     * 获取集合元素中的键数组
     *
     * @return static
     */
    public function keys()
    {
        return new static(array_keys($this->items));
    }

    /*
     * 获取最后一个元素
     *
     * @param  callable|null  $callback
     * @param  mixed  $default
     * @return mixed
     */
    public function last(callable $callback = null, $default = null)
    {
        return Arr::last($this->items, $callback, $default);
    }

    /*
     * 根据键名 pluck 值
     *
     * @param  string|array  $value
     * @param  string|null  $key
     * @return static
     */
    public function pluck($value, $key = null)
    {
        return new static(Arr::pluck($this->items, $value, $key));
    }

    /*
     * 对集合中的元素调用 map 方法
     *
     * @param  callable  $callback
     * @return static
     */
    public function map(callable $callback)
    {
        $keys = array_keys($this->items);

        $items = array_map($callback, $this->items, $keys);

        return new static(array_combine($keys, $items));
    }

    /**
     * Run an associative map over each of the items.
     *
     * The callback should return an associative array with a single key/value pair.
     *
     * @param  callable  $callback
     * @return static
     */
    public function mapWithKeys(callable $callback)
    {
        $result = [];

        foreach ($this->items as $key => $value) {
            $assoc = $callback($value, $key);

            foreach ($assoc as $mapKey => $mapValue) {
                $result[$mapKey] = $mapValue;
            }
        }

        return new static($result);
    }

    /*
     * map 集合并扁平化结果
     *
     * @param  callable  $callback
     * @return static
     */
    public function flatMap(callable $callback)
    {
        return $this->map($callback)->collapse();
    }

    /*
     * 返回最大值
     *
     * @param  callable|string|null  $callback
     * @return mixed
     */
    public function max($callback = null)
    {
        $callback = $this->valueRetriever($callback);

        return $this->filter(function ($value) {
            return ! is_null($value);
        })->reduce(function ($result, $item) use ($callback) {
            $value = $callback($item);

            return is_null($result) || $value > $result ? $value : $result;
        });
    }

    /*
     * 合并集合
     *
     * @param  mixed  $items
     * @return static
     */
    public function merge($items)
    {
        return new static(array_merge($this->items, $this->getArrayableItems($items)));
    }

    /*
     * 根据键名数组和键值数组，创建一个新的集合
     *
     * @param  mixed  $values
     * @return static
     */
    public function combine($values)
    {
        return new static(array_combine($this->all(), $this->getArrayableItems($values)));
    }

    /*
     * 并集
     *
     * @param  mixed  $items
     * @return static
     */
    public function union($items)
    {
        return new static($this->items + $this->getArrayableItems($items));
    }

    /*
     * 获取最小值
     *
     * @param  callable|string|null  $callback
     * @return mixed
     */
    public function min($callback = null)
    {
        $callback = $this->valueRetriever($callback);

        return $this->filter(function ($value) {
            return ! is_null($value);
        })->reduce(function ($result, $item) use ($callback) {
            $value = $callback($item);

            return is_null($result) || $value < $result ? $value : $result;
        });
    }

    /*
     * 将每隔 step 的元素以集合返回
     *
     * @param  int  $step
     * @param  int  $offset
     * @return static
     */
    public function nth($step, $offset = 0)
    {
        $new = [];

        $position = 0;

        foreach ($this->items as $item) {
            if ($position % $step === $offset) {
                $new[] = $item;
            }

            $position++;
        }

        return new static($new);
    }

    /*
     * 获取指定的元素
     *
     * @param  mixed  $keys
     * @return static
     */
    public function only($keys)
    {
        if (is_null($keys)) {
            return new static($this->items);
        }

        $keys = is_array($keys) ? $keys : func_get_args();

        return new static(Arr::only($this->items, $keys));
    }

    /*
     * 按页取元素
     *
     * @param  int  $page
     * @param  int  $perPage
     * @return static
     */
    public function forPage($page, $perPage)
    {
        return $this->slice(($page - 1) * $perPage, $perPage);
    }

    /*
     * 将集合元素分成两组
     *
     * @param  callable|string  $callback
     * @return static
     */
    public function partition($callback)
    {
        $partitions = [new static, new static];

        $callback = $this->valueRetriever($callback);

        foreach ($this->items as $key => $item) {
            $partitions[(int) ! $callback($item)][$key] = $item;
        }

        return new static($partitions);
    }

    /**
     * Pass the collection to the given callback and return the result.
     *
     * @param  callable $callback
     * @return mixed
     */
    public function pipe(callable $callback)
    {
        return $callback($this);
    }

    /*
     * 获取并删除数组中的最后一个元素
     *
     * @return mixed
     */
    public function pop()
    {
        return array_pop($this->items);
    }

    /*
     * 新增元素至集合的头部
     *
     * @param  mixed  $value
     * @param  mixed  $key
     * @return $this
     */
    public function prepend($value, $key = null)
    {
        $this->items = Arr::prepend($this->items, $value, $key);

        return $this;
    }

    /*
     * 新增元素至集合尾部
     *
     * @param  mixed  $value
     * @return $this
     */
    public function push($value)
    {
        $this->offsetSet(null, $value);

        return $this;
    }

    /*
     * 获取并移除指定元素
     *
     * @param  mixed  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function pull($key, $default = null)
    {
        return Arr::pull($this->items, $key, $default);
    }

    /*
     * 新增元素
     *
     * @param  mixed  $key
     * @param  mixed  $value
     * @return $this
     */
    public function put($key, $value)
    {
        $this->offsetSet($key, $value);

        return $this;
    }

    /*
     * 随机获取1个或多个元素
     *
     * @param  int|null  $amount
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function random($amount = 1)
    {
        if ($amount > ($count = $this->count())) {
            throw new InvalidArgumentException("You requested {$amount} items, but there are only {$count} items in the collection.");
        }

        $keys = array_rand($this->items, $amount);

        if (count(func_get_args()) == 0) {
            return $this->items[$keys];
        }

        $keys = array_wrap($keys);

        return new static(array_intersect_key($this->items, array_flip($keys)));
    }

    /*
     * 用回调函数迭代地将数组简化为单一的值
     *
     * @param  callable  $callback
     * @param  mixed  $initial
     * @return mixed
     */
    public function reduce(callable $callback, $initial = null)
    {
        return array_reduce($this->items, $callback, $initial);
    }

    /*
     * 过滤不满足条件的元素
     *
     * @param  callable|mixed  $callback
     * @return static
     */
    public function reject($callback)
    {
        if ($this->useAsCallable($callback)) {
            return $this->filter(function ($value, $key) use ($callback) {
                return ! $callback($value, $key);
            });
        }

        return $this->filter(function ($item) use ($callback) {
            return $item != $callback;
        });
    }

    /*
     * 元素顺序反转
     *
     * @return static
     */
    public function reverse()
    {
        return new static(array_reverse($this->items, true));
    }

    /*
     * 搜索键值，并返回键名
     *
     * @param  mixed  $value
     * @param  bool  $strict
     * @return mixed
     */
    public function search($value, $strict = false)
    {
        if (! $this->useAsCallable($value)) {
            return array_search($value, $this->items, $strict);
        }

        foreach ($this->items as $key => $item) {
            if (call_user_func($value, $item, $key)) {
                return $key;
            }
        }

        return false;
    }

    /*
     * 从集合中获取并移除第一个元素
     *
     * @return mixed
     */
    public function shift()
    {
        return array_shift($this->items);
    }

    /*
     * 打乱集合中的元素
     *
     * @param  int  $seed
     * @return static
     */
    public function shuffle($seed = null)
    {
        $items = $this->items;

        if (is_null($seed)) {
            shuffle($items);
        } else {
            srand($seed);

            usort($items, function () {
                return rand(-1, 1);
            });
        }

        return new static($items);
    }

    /*
     * 返回数组中的选定部分
     *
     * @param  int  $offset
     * @param  int  $length
     * @return static
     */
    public function slice($offset, $length = null)
    {
        return new static(array_slice($this->items, $offset, $length, true));
    }

    /*
     * 数组元素分块, 参数为每块的大小
     *
     * @param  int  $numberOfGroups
     * @return static
     */
    public function split($numberOfGroups)
    {
        if ($this->isEmpty()) {
            return new static;
        }

        $groupSize = ceil($this->count() / $numberOfGroups);

        return $this->chunk($groupSize);
    }

    /*
     * 数组元素分块，对当前数组有影响
     *
     * @param  int  $size
     * @return static
     */
    public function chunk($size)
    {
        if ($size <= 0) {
            return new static;
        }

        $chunks = [];

        foreach (array_chunk($this->items, $size, true) as $chunk) {
            $chunks[] = new static($chunk);
        }

        return new static($chunks);
    }

    /*
     * 排序, 升序
     *
     * @param  callable|null  $callback
     * @return static
     */
    public function sort(callable $callback = null)
    {
        $items = $this->items;

        $callback
            ? uasort($items, $callback) // 用户自定义，关联数组的值进行排序
            : asort($items); // 按照键值对关联数组进行升序排序

        return new static($items);
    }

    /*
     * 排序, 自定义升降
     *
     * @param  callable|string  $callback
     * @param  int  $options
     * @param  bool  $descending
     * @return static
     */
    public function sortBy($callback, $options = SORT_REGULAR, $descending = false)
    {
        $results = [];

        $callback = $this->valueRetriever($callback);

        // First we will loop through the items and get the comparator from a callback
        // function which we were given. Then, we will sort the returned values and
        // and grab the corresponding values for the sorted keys from this array.
        foreach ($this->items as $key => $value) {
            $results[$key] = $callback($value, $key);
        }

        $descending ? arsort($results, $options)
                    : asort($results, $options);

        // Once we have sorted all of the keys in the array, we will loop through them
        // and grab the corresponding model so we can set the underlying items list
        // to the sorted version. Then we'll just return the collection instance.
        foreach (array_keys($results) as $key) {
            $results[$key] = $this->items[$key];
        }

        return new static($results);
    }

    /*
     * 降序排序
     *
     * @param  callable|string  $callback
     * @param  int  $options
     * @return static
     */
    public function sortByDesc($callback, $options = SORT_REGULAR)
    {
        return $this->sortBy($callback, $options, true);
    }

    /*
     * 返回移除的元素
     *
     * @param  int  $offset
     * @param  int|null  $length
     * @param  mixed  $replacement
     * @return static
     */
    public function splice($offset, $length = null, $replacement = [])
    {
        if (func_num_args() == 1) {
            return new static(array_splice($this->items, $offset));
        }

        return new static(array_splice($this->items, $offset, $length, $replacement));
    }

    /*
     * 求和
     *
     * @param  callable|string|null  $callback
     * @return mixed
     */
    public function sum($callback = null)
    {
        if (is_null($callback)) {
            return array_sum($this->items);
        }

        $callback = $this->valueRetriever($callback);

        return $this->reduce(function ($result, $item) use ($callback) {
            return $result + $callback($item);
        }, 0);
    }

    /*
     * 获取开头或最后 {$limit} 个元素
     *
     * @param  int  $limit
     * @return static
     */
    public function take($limit)
    {
        if ($limit < 0) {
            return $this->slice($limit, abs($limit));
        }

        return $this->slice(0, $limit);
    }

    /**
     * Pass the collection to the given callback and then return it.
     *
     * @param  callable  $callback
     * @return $this
     */
    public function tap(callable $callback)
    {
        $callback(new static($this->items));

        return $this;
    }

    /*
     * 迭代集合并对集合中每个数据项调用给定回调。
     * 集合中的数据项将会被替代成从回调中返回的值
     *
     * @param  callable  $callback
     * @return $this
     */
    public function transform(callable $callback)
    {
        $this->items = $this->map($callback)->all();

        return $this;
    }

    /*
     * 返回集合中独一无二的元素
     *
     * @param  string|callable|null  $key
     * @param  bool  $strict
     * @return static
     */
    public function unique($key = null, $strict = false)
    {
        if (is_null($key)) {
            return new static(array_unique($this->items, SORT_REGULAR));
        }

        $callback = $this->valueRetriever($key);

        $exists = [];

        return $this->reject(function ($item, $key) use ($callback, $strict, &$exists) {
            if (in_array($id = $callback($item, $key), $exists, $strict)) {
                return true;
            }

            $exists[] = $id;
        });
    }

    /*
     * 返回集合中不重复的元素，严格模式
     *
     * @param  string|callable|null  $key
     * @return static
     */
    public function uniqueStrict($key = null)
    {
        return $this->unique($key, true);
    }

    /*
     * 重新索引
     *
     * @return static
     */
    public function values()
    {
        return new static(array_values($this->items));
    }

    /**
     * Get a value retrieving callback.
     *
     * @param  string  $value
     * @return callable
     */
    protected function valueRetriever($value)
    {
        if ($this->useAsCallable($value)) {
            return $value;
        }

        return function ($item) use ($value) {
            return data_get($item, $value);
        };
    }

    /*
     * 相应的索引处合并给定数组的值
     *
     * e.g. new Collection([1, 2, 3])->zip([4, 5, 6]);
     *      => [[1, 4], [2, 5], [3, 6]]
     *
     * @param  mixed ...$items
     * @return static
     */
    public function zip($items)
    {
        $arrayableItems = array_map(function ($items) {
            return $this->getArrayableItems($items);
        }, func_get_args());

        $params = array_merge([function () {
            return new static(func_get_args());
        }, $this->items], $arrayableItems);

        return new static(call_user_func_array('array_map', $params));
    }

    /*
     * 获取数组形的集合元素
     *
     * @return array
     */
    public function toArray()
    {
        return array_map(function ($value) {
            return $value instanceof Arrayable ? $value->toArray() : $value;
        }, $this->items);
    }

    /*
     * json 序列化
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return array_map(function ($value) {
            if ($value instanceof JsonSerializable) {
                return $value->jsonSerialize();
            } elseif ($value instanceof Jsonable) {
                return json_decode($value->toJson(), true);
            } elseif ($value instanceof Arrayable) {
                return $value->toArray();
            } else {
                return $value;
            }
        }, $this->items);
    }

    /*
     * 获取 Json 格式的集合
     *
     * @param  int  $options 该参数为 true 时，返回 array 而非 object
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /*
     * 获取元素的迭代器对象
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->items);
    }

    /*
     * 获取元素的 CachingIterator 实例
     *
     * @param  int  $flags
     * @return \CachingIterator
     */
    public function getCachingIterator($flags = CachingIterator::CALL_TOSTRING)
    {
        return new CachingIterator($this->getIterator(), $flags);
    }

    /*
     * 计算集合中元素的个数
     *
     * @return int
     */
    public function count()
    {
        return count($this->items);
    }

    /*
     * 获取最原始的父类Collection对象
     *
     * @return \Illuminate\Support\Collection
     */
    public function toBase()
    {
        return new self($this);
    }

    /*
     * 判断一个元素是否存在
     *
     * @param  mixed  $key
     * @return bool
     */
    public function offsetExists($key)
    {
        return array_key_exists($key, $this->items);
    }

    /*
     * 返回元素的值
     *
     * @param  mixed  $key
     * @return mixed
     */
    public function offsetGet($key)
    {
        return $this->items[$key];
    }

    /*
     * 为一个元素赋值
     *
     * @param  mixed  $key
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet($key, $value)
    {
        if (is_null($key)) {
            $this->items[] = $value;
        } else {
            $this->items[$key] = $value;
        }
    }

    /*
     * 删除一个元素
     *
     * @param  string  $key
     * @return void
     */
    public function offsetUnset($key)
    {
        unset($this->items[$key]);
    }

    /*
     * 集合字符串输出
     *
     * @return string
     */
    public function __toString()
    {
        return $this->toJson();
    }

    /*
     * 返回数组形式的元素
     *
     * @param  mixed  $items
     * @return array
     */
    protected function getArrayableItems($items)
    {
        if (is_array($items)) {
            return $items;
        } elseif ($items instanceof self) {
            return $items->all();
        } elseif ($items instanceof Arrayable) {
            return $items->toArray();
        } elseif ($items instanceof Jsonable) {
            return json_decode($items->toJson(), true);
        } elseif ($items instanceof JsonSerializable) {
            return $items->jsonSerialize();
        } elseif ($items instanceof Traversable) {
            return iterator_to_array($items);
        }

        return (array) $items;
    }

    /**
     * Add a method to the list of proxied methods.
     *
     * @param  string  $method
     * @return void
     */
    public static function proxy($method)
    {
        static::$proxies[] = $method;
    }

    /**
     * Dynamically access collection proxies.
     *
     * @param  string  $key
     * @return mixed
     *
     * @throws \Exception
     */
    public function __get($key)
    {
        if (! in_array($key, static::$proxies)) {
            throw new Exception("Property [{$key}] does not exist on this collection instance.");
        }

        return new HigherOrderCollectionProxy($this, $key);
    }
}
