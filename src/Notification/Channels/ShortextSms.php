<?php


namespace Nycorp\LiteApi\Notification\Channels;


use App\Http\Controllers\Controller;
use Illuminate\Notifications\Notification;

class ShortextSms
{
    /**
     * @param              $notifiable
     * @param Notification $notification
     */
    public function send($notifiable, Notification $notification)
    {
        $message = $notification->toShortextSms($notifiable);

        #Controller::sendSms($message->getRecipient(), $message->getContent());
    }
}
