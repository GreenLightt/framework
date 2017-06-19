<?php

namespace Illuminate\Mail;

use ReflectionClass;
use ReflectionProperty;
use BadMethodCallException;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Factory as Queue;
use Illuminate\Contracts\Mail\Mailer as MailerContract;
use Illuminate\Contracts\Mail\Mailable as MailableContract;

class Mailable implements MailableContract
{
    /*
     * The person the message is from.
     *
     * @var array
     */
    public $from = [];

    /*
     * The "to" recipients of the message.
     *
     * @var array
     */
    public $to = [];

    /*
     * The "cc" recipients of the message.
     *
     * @var array
     */
    public $cc = [];

    /*
     * The "bcc" recipients of the message.
     *
     * @var array
     */
    public $bcc = [];

    /*
     * The "reply to" recipients of the message.
     *
     * @var array
     */
    public $replyTo = [];

    /*
     * 邮件的标题
     *
     * @var string
     */
    public $subject;

    /*
     * The Markdown template for the message (if applicable).
     *
     * @var string
     */
    protected $markdown;

    /*
     * The view to use for the message.
     *
     * @var string
     */
    public $view;

    /*
     * The plain text view to use for the message.
     *
     * @var string
     */
    public $textView;

    /*
     * The view data for the message.
     *
     * @var array
     */
    public $viewData = [];

    /*
     * The attachments for the message.
     *
     * @var array
     */
    public $attachments = [];

    /*
     * The raw attachments for the message.
     *
     * @var array
     */
    public $rawAttachments = [];

    /*
     * The callbacks for the message.
     *
     * @var array
     */
    public $callbacks = [];

    /*
     * 发送邮件
     *
     * @param  \Illuminate\Contracts\Mail\Mailer  $mailer
     * @return void
     */
    public function send(MailerContract $mailer)
    {
        Container::getInstance()->call([$this, 'build']);

        $mailer->send($this->buildView(), $this->buildViewData(), function ($message) {
            $this->buildFrom($message)
                 ->buildRecipients($message)
                 ->buildSubject($message)
                 ->buildAttachments($message)
                 ->runCallbacks($message);
        });
    }

    /*
     * 将邮件放到队列发送
     *
     * @param  \Illuminate\Contracts\Queue\Factory  $queue
     * @return mixed
     */
    public function queue(Queue $queue)
    {
        $connection = property_exists($this, 'connection') ? $this->connection : null;

        $queueName = property_exists($this, 'queue') ? $this->queue : null;

        return $queue->connection($connection)->pushOn(
            $queueName ?: null, new SendQueuedMailable($this)
        );
    }

    /*
     * 将邮件放到队列延迟发送
     *
     * @param  \DateTime|int  $delay
     * @param  Queue  $queue
     * @return mixed
     */
    public function later($delay, Queue $queue)
    {
        $connection = property_exists($this, 'connection') ? $this->connection : null;

        $queueName = property_exists($this, 'queue') ? $this->queue : null;

        return $queue->connection($connection)->laterOn(
            $queueName ?: null, $delay, new SendQueuedMailable($this)
        );
    }

    /*
     * 构建邮件消息的视图
     *
     * @return array|string
     */
    protected function buildView()
    {
        if (isset($this->markdown)) {
            return $this->buildMarkdownView();
        }

        if (isset($this->view, $this->textView)) {
            return [$this->view, $this->textView];
        } elseif (isset($this->textView)) {
            return ['text' => $this->textView];
        }

        return $this->view;
    }

    /*
     * 构建邮件消息的 Markdown 视图
     *
     * @return array
     */
    protected function buildMarkdownView()
    {
        $markdown = Container::getInstance()->make(Markdown::class);

        $data = $this->buildViewData();

        return [
            'html' => $markdown->render($this->markdown, $data),
            'text' => $markdown->renderText($this->markdown, $data),
        ];
    }

    /*
     * 构建邮件需要的 data 数据
     *
     * @return array
     */
    public function buildViewData()
    {
        $data = $this->viewData;

        foreach ((new ReflectionClass($this))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getDeclaringClass()->getName() != self::class) {
                $data[$property->getName()] = $property->getValue($this);
            }
        }

        return $data;
    }

    /*
     * 向邮件添加发送人
     *
     * @param  \Illuminate\Mail\Message  $message
     * @return $this
     */
    protected function buildFrom($message)
    {
        if (! empty($this->from)) {
            $message->from($this->from[0]['address'], $this->from[0]['name']);
        }

        return $this;
    }

    /*
     * 向邮件添加接收人
     *
     * @param  \Illuminate\Mail\Message  $message
     * @return $this
     */
    protected function buildRecipients($message)
    {
        foreach (['to', 'cc', 'bcc', 'replyTo'] as $type) {
            foreach ($this->{$type} as $recipient) {
                $message->{$type}($recipient['address'], $recipient['name']);
            }
        }

        return $this;
    }

    /*
     * 设置邮件的标题
     *
     * @param  \Illuminate\Mail\Message  $message
     * @return $this
     */
    protected function buildSubject($message)
    {
        if ($this->subject) {
            $message->subject($this->subject);
        } else {
            $message->subject(Str::title(Str::snake(class_basename($this), ' ')));
        }

        return $this;
    }

    /*
     * 邮件添加附件
     *
     * @param  \Illuminate\Mail\Message  $message
     * @return $this
     */
    protected function buildAttachments($message)
    {
        foreach ($this->attachments as $attachment) {
            $message->attach($attachment['file'], $attachment['options']);
        }

        foreach ($this->rawAttachments as $attachment) {
            $message->attachData(
                $attachment['data'], $attachment['name'], $attachment['options']
            );
        }

        return $this;
    }

    /*
     * 对 swift_message 执行回调
     *
     * @param  \Illuminate\Mail\Message  $message
     * @return $this
     */
    protected function runCallbacks($message)
    {
        foreach ($this->callbacks as $callback) {
            $callback($message->getSwiftMessage());
        }

        return $this;
    }

    /*
     * 设置消息的优先级
     *
     * The value is an integer where 1 is the highest priority and 5 is the lowest.
     *
     * @param  int  $level
     * @return $this
     */
    public function priority($level = 3)
    {
        $this->callbacks[] = function ($message) use ($level) {
            $message->setPriority($level);
        };

        return $this;
    }

    /*
     * 设置邮件的发送者
     *
     * @param  object|array|string  $address
     * @param  string|null  $name
     * @return $this
     */
    public function from($address, $name = null)
    {
        return $this->setAddress($address, $name, 'from');
    }

    /*
     * 判断是否指定发送人在发送人名单中
     *
     * @param  object|array|string  $address
     * @param  string|null  $name
     * @return bool
     */
    public function hasFrom($address, $name = null)
    {
        return $this->hasRecipient($address, $name, 'from');
    }

    /*
     * 设置邮件的接收者
     *
     * @param  object|array|string  $address
     * @param  string|null  $name
     * @return $this
     */
    public function to($address, $name = null)
    {
        return $this->setAddress($address, $name, 'to');
    }

    /*
     * 判断是否指定接收者在接收人名单中
     *
     * @param  object|array|string  $address
     * @param  string|null  $name
     * @return bool
     */
    public function hasTo($address, $name = null)
    {
        return $this->hasRecipient($address, $name, 'to');
    }

    /*
     * 设置邮件的抄送者
     *
     * @param  object|array|string  $address
     * @param  string|null  $name
     * @return $this
     */
    public function cc($address, $name = null)
    {
        return $this->setAddress($address, $name, 'cc');
    }

    /*
     * 判断是否指定接收者在抄送名单中
     *
     * @param  object|array|string  $address
     * @param  string|null  $name
     * @return bool
     */
    public function hasCc($address, $name = null)
    {
        return $this->hasRecipient($address, $name, 'cc');
    }

    /*
     * 设置邮件的暗抄送者
     *
     * @param  object|array|string  $address
     * @param  string|null  $name
     * @return $this
     */
    public function bcc($address, $name = null)
    {
        return $this->setAddress($address, $name, 'bcc');
    }

    /*
     * 判断是否指定接收者在暗抄送名单中
     *
     * @param  object|array|string  $address
     * @param  string|null  $name
     * @return bool
     */
    public function hasBcc($address, $name = null)
    {
        return $this->hasRecipient($address, $name, 'bcc');
    }

    /*
     * 设置邮件的回复者
     *
     * @param  object|array|string  $address
     * @param  string|null  $name
     * @return $this
     */
    public function replyTo($address, $name = null)
    {
        return $this->setAddress($address, $name, 'replyTo');
    }

    /*
     * 设置消息的接收者
     *
     * All recipients are stored internally as [['name' => ?, 'address' => ?]]
     *
     * @param  object|array|string  $address
     * @param  string|null  $name
     * @param  string  $property
     * @return $this
     */
    protected function setAddress($address, $name = null, $property = 'to')
    {
        foreach ($this->addressesToArray($address, $name) as $recipient) {
            $recipient = $this->normalizeRecipient($recipient);

            $this->{$property}[] = [
                'name' => isset($recipient->name) ? $recipient->name : null,
                'address' => $recipient->email,
            ];
        }

        return $this;
    }

    /*
     * 将参数转为 array 格式
     *
     * @param  object|array|string  $address
     * @param  string|null  $name
     * @return array
     */
    protected function addressesToArray($address, $name)
    {
        if (! is_array($address) && ! $address instanceof Collection) {
            $address = is_string($name) ? [['name' => $name, 'email' => $address]] : [$address];
        }

        return $address;
    }

    /*
     * 转换指定的 recipient 为 object 对象
     *
     * @param  mixed  $recipient
     * @return object
     */
    protected function normalizeRecipient($recipient)
    {
        if (is_array($recipient)) {
            return (object) $recipient;
        } elseif (is_string($recipient)) {
            return (object) ['email' => $recipient];
        }

        return $recipient;
    }

    /*
     * 判断指定接收人是否在指定类型的名单中
     *
     * @param  object|array|string  $address
     * @param  string|null  $name
     * @param  string  $property
     * @return bool
     */
    protected function hasRecipient($address, $name = null, $property = 'to')
    {
        $expected = $this->normalizeRecipient(
            $this->addressesToArray($address, $name)[0]
        );

        $expected = [
            'name' => isset($expected->name) ? $expected->name : null,
            'address' => $expected->email,
        ];

        return collect($this->{$property})->contains(function ($actual) use ($expected) {
            if (! isset($expected['name'])) {
                return $actual['address'] == $expected['address'];
            } else {
                return $actual == $expected;
            }
        });
    }

    /*
     * 设置邮件的主题
     *
     * @param  string  $subject
     * @return $this
     */
    public function subject($subject)
    {
        $this->subject = $subject;

        return $this;
    }

    /*
     * 设置消息内容，markdown模板
     *
     * @param  string  $view
     * @param  array  $data
     * @return $this
     */
    public function markdown($view, array $data = [])
    {
        $this->markdown = $view;
        $this->viewData = array_merge($this->viewData, $data);

        return $this;
    }

    /*
     * 设置消息内容，视图模板
     *
     * @param  string  $view
     * @param  array  $data
     * @return $this
     */
    public function view($view, array $data = [])
    {
        $this->view = $view;
        $this->viewData = array_merge($this->viewData, $data);

        return $this;
    }

    /*
     * 设置消息内容，纯文本
     *
     * @param  string  $textView
     * @param  array  $data
     * @return $this
     */
    public function text($textView, array $data = [])
    {
        $this->textView = $textView;
        $this->viewData = array_merge($this->viewData, $data);

        return $this;
    }

    /*
     * 绑定消息内容需要的数据
     *
     * @param  string|array  $key
     * @param  mixed   $value
     * @return $this
     */
    public function with($key, $value = null)
    {
        if (is_array($key)) {
            $this->viewData = array_merge($this->viewData, $key);
        } else {
            $this->viewData[$key] = $value;
        }

        return $this;
    }

    /*
     * Attach a file to the message.
     *
     * @param  string  $file
     * @param  array  $options
     * @return $this
     */
    public function attach($file, array $options = [])
    {
        $this->attachments[] = compact('file', 'options');

        return $this;
    }

    /*
     * Attach in-memory data as an attachment.
     *
     * @param  string  $data
     * @param  string  $name
     * @param  array  $options
     * @return $this
     */
    public function attachData($data, $name, array $options = [])
    {
        $this->rawAttachments[] = compact('data', 'name', 'options');

        return $this;
    }

    /*
     * 注册回调函数实例，回调函数的参数是 swift_message 实例，
     * 发送邮件之前会执行回调函数
     *
     * @param  callable  $callback
     * @return $this
     */
    public function withSwiftMessage($callback)
    {
        $this->callbacks[] = $callback;

        return $this;
    }

    /*
     * Dynamically bind parameters to the message.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return $this
     *
     * @throws \BadMethodCallException
     */
    public function __call($method, $parameters)
    {
        if (Str::startsWith($method, 'with')) {
            return $this->with(Str::snake(substr($method, 4)), $parameters[0]);
        }

        throw new BadMethodCallException("Method [$method] does not exist on mailable.");
    }
}
