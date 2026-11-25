<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ListingClaimVerificationCode extends Notification
{
    public function __construct(public readonly string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify your TaleemFinder listing claim')
            ->line('Your verification code is '.$this->code.'.')
            ->line('It expires in 10 minutes. If you did not request it, ignore this message.');
    }
}
