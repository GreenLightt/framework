<?php

namespace Illuminate\Hashing;

use RuntimeException;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;

class BcryptHasher implements HasherContract
{
    /*
     * 指明算法递归的层数
     *
     * @var int
     */
    protected $rounds = 10;

    /*
     * hash加密指定的值
     *
     * @param  string  $value
     * @param  array   $options
     * @return string
     *
     * @throws \RuntimeException
     */
    public function make($value, array $options = [])
    {
        $hash = password_hash($value, PASSWORD_BCRYPT, [
            'cost' => $this->cost($options),
        ]);

        if ($hash === false) {
            throw new RuntimeException('Bcrypt hashing not supported.');
        }

        return $hash;
    }

    /*
     * 检查指定的值和哈希值是否匹配
     *
     * @param  string  $value
     * @param  string  $hashedValue
     * @param  array   $options
     * @return bool
     */
    public function check($value, $hashedValue, array $options = [])
    {
        if (strlen($hashedValue) === 0) {
            return false;
        }

        return password_verify($value, $hashedValue);
    }

    /*
     * 判断一个 hash 值是否使用特定算法及选项创建的
     *
     * @param  string  $hashedValue
     * @param  array   $options
     * @return bool
     */
    public function needsRehash($hashedValue, array $options = [])
    {
        return password_needs_rehash($hashedValue, PASSWORD_BCRYPT, [
            'cost' => $this->cost($options),
        ]);
    }

    /*
     * 设置算法递归的层数
     *
     * @param  int  $rounds
     * @return $this
     */
    public function setRounds($rounds)
    {
        $this->rounds = (int) $rounds;

        return $this;
    }

    /*
     * 从参数 options 数组中抽取 rounds 值
     *
     * @param  array  $options
     * @return int
     */
    protected function cost(array $options = [])
    {
        return isset($options['rounds']) ? $options['rounds'] : $this->rounds;
    }
}
