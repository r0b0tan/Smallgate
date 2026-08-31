<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Password reset mail in the portal's own wording. Sent only to accounts that
 * may actually sign in -- see User::sendPasswordResetNotification().
 */
class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        #[\SensitiveParameter] private readonly string $token,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Passwort zurücksetzen – '.config('app.name'))
            ->greeting('Hallo '.$notifiable->name.',')
            ->line('für Ihren Zugang zum Kundenportal wurde ein neues Passwort angefordert.')
            ->action('Passwort zurücksetzen', $url)
            ->line("Der Link ist {$minutes} Minuten gültig.")
            ->line('Haben Sie das nicht angefordert, ist keine weitere Aktion nötig – Ihr Passwort bleibt unverändert.')
            ->salutation('Viele Grüße'."\n".config('app.name'));
    }
}
