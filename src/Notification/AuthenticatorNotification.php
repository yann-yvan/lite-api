<?php

namespace Nycorp\LiteApi\Notification;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;
use NotificationChannels\WhatsApp\WhatsAppChannel;
use NotificationChannels\WhatsApp\WhatsAppTemplate;
use Nycorp\LiteApi\Notification\Channels\ShortextSms;
use Nycorp\LiteApi\Notification\Message\SmsMessage;
use NotificationChannels\WhatsApp\Component;

class AuthenticatorNotification extends Notification
{
    use Queueable;

    private string $token;

    private string $code;

    /**
     * ResetPasswordNotification constructor.
     */
    public function __construct($token, $code)
    {
        $this->token = $token;
        $this->code = $code;
    }

    /**
     * Get the notification’s delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return [ShortextSms::class,WhatsAppChannel::class];

    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Hello !')
            ->line('You are receiving this email because we received a password reset request for your account.')
            ->line('Please use this code to reset your password')
            ->line(new HtmlString("Code : <strong> Code : $this->code</strong>"))
            ->line('If you did not request a password reset, no further action is required.')
            ->line('Thank you for using our application!');
    }

    public function toShortextSms($notifiable): SmsMessage
    {
        return (new SmsMessage())
            ->setContent(env('APP_NAME').' : '.$this->code.' is your security code.')
            ->setRecipient($notifiable->phone);
    }


    public function toWhatsapp($notifiable)
    {
        return WhatsAppTemplate::create()
            ->name('auth') // Name of your configured template
            ->body(Component::text($this->code))
            ->language('fr')
            ->buttons(Component::urlButton([$this->code]))
            ->to($notifiable->phone);
    }
}
