<?php

namespace Illuminate\Mail\Transport;

use Swift_Transport;
use Swift_Mime_Message;
use Swift_Events_SendEvent;
use Swift_Events_EventListener;

abstract class Transport implements Swift_Transport
{
    /**
     * The plug-ins registered with the transport.
     *
     * @var array
     */
    public $plugins = [];

    /*
     * {@inheritdoc}
     */
    public function isStarted()
    {
        return true;
    }

    /*
     * {@inheritdoc}
     */
    public function start()
    {
        return true;
    }

    /*
     * {@inheritdoc}
     */
    public function stop()
    {
        return true;
    }

    /*
     * 注册插件
     *
     * @param  \Swift_Events_EventListener  $plugin
     * @return void
     */
    public function registerPlugin(Swift_Events_EventListener $plugin)
    {
        array_push($this->plugins, $plugin);
    }

    /*
     * 迭代执行 plugins 中的方法 'beforeSendPerformed'
     *
     * @param  \Swift_Mime_Message  $message
     * @return void
     */
    protected function beforeSendPerformed(Swift_Mime_Message $message)
    {
        $event = new Swift_Events_SendEvent($this, $message);

        foreach ($this->plugins as $plugin) {
            if (method_exists($plugin, 'beforeSendPerformed')) {
                $plugin->beforeSendPerformed($event);
            }
        }
    }

    /*
     * 迭代执行 plugins 中的方法 'sendPerformed'
     *
     * @param  \Swift_Mime_Message  $message
     * @return void
     */
    protected function sendPerformed(Swift_Mime_Message $message)
    {
        $event = new Swift_Events_SendEvent($this, $message);

        foreach ($this->plugins as $plugin) {
            if (method_exists($plugin, 'sendPerformed')) {
                $plugin->sendPerformed($event);
            }
        }
    }

    /*
     * 获取收件人的数目
     *
     * @param  \Swift_Mime_Message  $message
     * @return int
     */
    protected function numberOfRecipients(Swift_Mime_Message $message)
    {
        return count(array_merge(
            (array) $message->getTo(), (array) $message->getCc(), (array) $message->getBcc()
        ));
    }
}
