<?php

namespace Illuminate\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;

class PendingMail
{
    /*
     * The mailer instance.
     *
     * @var array
     */
    protected $mailer;

    /*
     * 发送目标
     *
     * @var array
     */
    protected $to = [];

    /*
     * 抄送
     *
     * @var array
     */
    protected $cc = [];

    /*
     * 暗抄送
     *
     * @var array
     */
    protected $bcc = [];

    /*
     * Create a new mailable mailer instance.
     *
     * @param  Mailer  $mailer
     * @return void
     */
    public function __construct(Mailer $mailer)
    {
        $this->mailer = $mailer;
    }

    /*
     * 设置邮件的接收人
     *
     * @param  mixed  $users
     * @return $this
     */
    public function to($users)
    {
        $this->to = $users;

        return $this;
    }

    /*
     * 设置邮件的抄送人
     *
     * @param  mixed  $users
     * @return $this
     */
    public function cc($users)
    {
        $this->cc = $users;

        return $this;
    }

    /*
     * 设置邮件的暗抄送
     *
     * @param  mixed  $users
     * @return $this
     */
    public function bcc($users)
    {
        $this->bcc = $users;

        return $this;
    }

    /*
     * Send a new mailable message instance.
     *
     * @param  Mailable  $mailable
     * @return mixed
     */
    public function send(Mailable $mailable)
    {
        if ($mailable instanceof ShouldQueue) {
            return $this->queue($mailable);
        }

        return $this->mailer->send($this->fill($mailable));
    }

    /*
     * 立即发送邮件
     *
     * @param  Mailable  $mailable
     * @return mixed
     */
    public function sendNow(Mailable $mailable)
    {
        return $this->mailer->send($this->fill($mailable));
    }

    /*
     * Push the given mailable onto the queue.
     *
     * @param  Mailable  $mailable
     * @return mixed
     */
    public function queue(Mailable $mailable)
    {
        $mailable = $this->fill($mailable);

        if (isset($mailable->delay)) {
            return $this->mailer->later($mailable->delay, $mailable);
        }

        return $this->mailer->queue($mailable);
    }

    /*
     * Deliver the queued message after the given delay.
     *
     * @param  \DateTime|int  $delay
     * @param  Mailable  $mailable
     * @return mixed
     */
    public function later($delay, Mailable $mailable)
    {
        return $this->mailer->later($delay, $this->fill($mailable));
    }

    /*
     * Populate the mailable with the addresses.
     *
     * @param  Mailable  $mailable
     * @return Mailable
     */
    protected function fill(Mailable $mailable)
    {
        return $mailable->to($this->to)
                        ->cc($this->cc)
                        ->bcc($this->bcc);
    }
}
