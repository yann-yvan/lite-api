<?php

namespace Nycorp\LiteApi\Notification\Channels;

use Illuminate\Notifications\Notification;

class ShortextSms
{
    public function send($notifiable, Notification $notification)
    {
        $message = $notification->toShortextSms($notifiable);

        //Controller::sendSms($message->getRecipient(), $message->getContent());
    }
}
