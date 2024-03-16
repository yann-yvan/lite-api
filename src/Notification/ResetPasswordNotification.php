<?php


namespace App\Notification;



use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Nycorp\LiteApi\Notification\Channels\ShortextSms;
use Nycorp\LiteApi\Notification\Message\SmsMessage;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    private $token, $code;

    /**
     * ResetPasswordNotification constructor.
     *
     * @param $token
     * @param $code
     */
    public function __construct($token, $code)
    {
        $this->token = $token;
        $this->code = $code;
    }

    /**
     * Get the notification’s delivery channels.
     *
     * @param mixed $notifiable
     *
     * @return array
     */
    public function via($notifiable)
    {
        return [ShortextSms::class];

    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     *
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Hello !')
            ->line('You are receiving this email because we received a password reset request for your account.')
            ->line("Please use this code to reset your password")
            ->line(new HtmlString("Code : <strong> Code : $this->code</strong>"))
            ->line('If you did not request a password reset, no further action is required.')
            ->line('Thank you for using our application!');
    }

    /**
     * @param $notifiable
     * @return SmsMessage
     */
    public function toShortextSms($notifiable): SmsMessage
    {
        return (new SmsMessage())
            ->setContent(env("APP_NAME")." : ".$this->code." is your security code.")
            ->setRecipient($notifiable->phone);
    }
}
