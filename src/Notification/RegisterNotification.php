<?php

namespace Nycorp\LiteApi\Notification;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Nycorp\LiteApi\Notification\Channels\ShortextSms;
use Nycorp\LiteApi\Notification\Message\SmsMessage;

class RegisterNotification extends Notification
{
    use Queueable;

    private $email;

    private $password;

    private $name;

    /**
     * RegisterNotification constructor.
     */
    public function __construct($email = '', $password = '', $name = '')
    {
        $this->email = $email;
        $this->name = $name;
        $this->password = $password;
    }

    /**
     * Get the notification’s delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return [ShortextSms::class];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $mailMessage = (new MailMessage)
            ->subject('Welcome')
            ->greeting(new HtmlString("Hello <strong>$this->name</strong> !"))
            ->line('You are receiving this email because we received registered you in our system.')
            ->line('Use theses credentials to login in your account')
            ->line(new HtmlString("<strong>Email : $this->email</strong>"));

        if (! empty($this->password)) {
            $mailMessage->line(new HtmlString("<strong>Mot de passe : $this->password</strong>"));
        }

        $mailMessage->action('Login', '/')
            ->line('Thank you for using our application!');

        return $mailMessage;
    }

    public function toShortextSms($notifiable): SmsMessage
    {
        return (new SmsMessage())
            ->setContent(env('APP_NAME').' : '.$this->password.' is your security code.')
            ->setRecipient($notifiable->phone);
    }
}
