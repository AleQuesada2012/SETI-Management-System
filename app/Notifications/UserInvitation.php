<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class UserInvitation extends Notification
{
    public function __construct(public readonly string $token) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('users.invitation.subject'))
            ->line(__('users.invitation.introduction'))
            ->action(__('users.invitation.accept'), route('invitations.accept', [
                'token' => $this->token,
                'email' => $notifiable->email,
            ]))
            ->line(__('users.invitation.expiry', ['hours' => config('auth.passwords.invitations.expire') / 60]))
            ->line(__('users.invitation.unexpected'));
    }
}
