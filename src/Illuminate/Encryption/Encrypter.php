<?php

namespace Illuminate\Encryption;

use RuntimeException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\EncryptException;
use Illuminate\Contracts\Encryption\Encrypter as EncrypterContract;

class Encrypter implements EncrypterContract
{
    /*
     * 加密的密钥
     *
     * @var string
     */
    protected $key;

    /*
     * 用于加密的算法格式
     *
     * @var string
     */
    protected $cipher;

    /*
     * 创建一个加密器实例
     *
     * @param  string  $key
     * @param  string  $cipher
     * @return void
     *
     * @throws \RuntimeException
     */
    public function __construct($key, $cipher = 'AES-128-CBC')
    {
        $key = (string) $key;

        if (static::supported($key, $cipher)) {
            $this->key = $key;
            $this->cipher = $cipher;
        } else {
            throw new RuntimeException('The only supported ciphers are AES-128-CBC and AES-256-CBC with the correct key lengths.');
        }
    }

    /*
     * 判断给定的算法与密钥组合是否有效
     *
     * @param  string  $key
     * @param  string  $cipher
     * @return bool
     */
    public static function supported($key, $cipher)
    {
        $length = mb_strlen($key, '8bit');

        return ($cipher === 'AES-128-CBC' && $length === 16) ||
               ($cipher === 'AES-256-CBC' && $length === 32);
    }

    /*
     * 加密给定的对象
     *
     * @param  mixed  $value
     * @param  bool  $serialize
     * @return string
     *
     * @throws \Illuminate\Contracts\Encryption\EncryptException
     */
    public function encrypt($value, $serialize = true)
    {
        // random_bytes是PHP7的方法，但在 vendor/paragonie/random_compat 作了兼容
        $iv = random_bytes(16);

        // First we will encrypt the value using OpenSSL. After this is encrypted we
        // will proceed to calculating a MAC for the encrypted value so that this
        // value can be verified later as not having been changed by the users.
        $value = \openssl_encrypt(
            $serialize ? serialize($value) : $value,
            $this->cipher, $this->key, 0, $iv
        );

        if ($value === false) {
            throw new EncryptException('Could not encrypt the data.');
        }

        // Once we have the encrypted value we will go ahead base64_encode the input
        // vector and create the MAC for the encrypted value so we can verify its
        // authenticity. Then, we'll JSON encode the data in a "payload" array.
        $mac = $this->hash($iv = base64_encode($iv), $value);

        $json = json_encode(compact('iv', 'value', 'mac'));

        if (! is_string($json)) {
            throw new EncryptException('Could not encrypt the data.');
        }

        return base64_encode($json);
    }

    /*
     * 加密字符串，不需要再序列化
     *
     * @param  string  $value
     * @return string
     */
    public function encryptString($value)
    {
        return $this->encrypt($value, false);
    }

    /*
     * 解密指定的值
     *
     * @param  mixed  $payload
     * @param  bool  $unserialize
     * @return string
     *
     * @throws \Illuminate\Contracts\Encryption\DecryptException
     */
    public function decrypt($payload, $unserialize = true)
    {
        $payload = $this->getJsonPayload($payload);

        $iv = base64_decode($payload['iv']);

        // Here we will decrypt the value. If we are able to successfully decrypt it
        // we will then unserialize it and return it out to the caller. If we are
        // unable to decrypt this value we will throw out an exception message.
        $decrypted = \openssl_decrypt(
            $payload['value'], $this->cipher, $this->key, 0, $iv
        );

        if ($decrypted === false) {
            throw new DecryptException('Could not decrypt the data.');
        }

        return $unserialize ? unserialize($decrypted) : $decrypted;
    }

    /*
     * 解密指定的字符串，不需要序列化
     *
     * @param  string  $payload
     * @return string
     */
    public function decryptString($payload)
    {
        return $this->decrypt($payload, false);
    }

    /*
     * 根据指定的值创建哈希信息验证码 (hmac)
     *
     * @param  string  $iv
     * @param  mixed  $value
     * @return string
     */
    protected function hash($iv, $value)
    {
        return hash_hmac('sha256', $iv.$value, $this->key);
    }

    /*
     * 从加密的字符串得到 Json数组('iv', 'value', 'mac')
     *
     * @param  string  $payload
     * @return array
     *
     * @throws \Illuminate\Contracts\Encryption\DecryptException
     */
    protected function getJsonPayload($payload)
    {
        $payload = json_decode(base64_decode($payload), true);

        // If the payload is not valid JSON or does not have the proper keys set we will
        // assume it is invalid and bail out of the routine since we will not be able
        // to decrypt the given value. We'll also check the MAC for this encryption.
        if (! $this->validPayload($payload)) {
            throw new DecryptException('The payload is invalid.');
        }

        if (! $this->validMac($payload)) {
            throw new DecryptException('The MAC is invalid.');
        }

        return $payload;
    }

    /*
     * 验证从加密的字符串得到的Json数组是否有效
     *
     * @param  mixed  $payload
     * @return bool
     */
    protected function validPayload($payload)
    {
        return is_array($payload) && isset(
            $payload['iv'], $payload['value'], $payload['mac']
        );
    }

    /*
     * 通过计算计算哈希信息验证码判断给定的 payload 是否有效
     *
     * @param  array  $payload
     * @return bool
     */
    protected function validMac(array $payload)
    {
        $calculated = $this->calculateMac($payload, $bytes = random_bytes(16));

        return hash_equals(
            hash_hmac('sha256', $payload['mac'], $bytes, true), $calculated
        );
    }

    /*
     * 根据给定的 payload 计算哈希信息验证码
     *
     * @param  array  $payload
     * @param  string  $bytes
     * @return string
     */
    protected function calculateMac($payload, $bytes)
    {
        return hash_hmac(
            'sha256', $this->hash($payload['iv'], $payload['value']), $bytes, true
        );
    }

    /*
     * 获取加密密钥
     *
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }
}
