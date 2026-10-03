<?php

namespace App\Notifications;

use App\Models\Branding;
use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The invitation mail. It carries the only copy of the plaintext token, which
 * is why the token is marked sensitive and never written to a log.
 */
class InvitationNotification extends Notification
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

    public function toMail(Invitation $notifiable): MailMessage
    {
        $url = route('invitations.show', ['token' => $this->token]);
        $hours = (int) config('smallgate.invitations.ttl_hours', 72);
        $portal = Branding::current()->displayName();
        $customer = $notifiable->customer?->name ?? $portal;

        return (new MailMessage)
            ->subject('Ihr Zugang zum Kundenportal von '.$portal)
            ->greeting('Hallo '.$notifiable->name.',')
            ->line("Sie wurden für den Bereich \"{$customer}\" zum Kundenportal eingeladen.")
            ->line('Über den folgenden Link vergeben Sie Ihr eigenes Passwort und schließen die Einrichtung ab.')
            ->action('Zugang einrichten', $url)
            ->line("Der Link ist {$hours} Stunden gültig und kann nur einmal verwendet werden.")
            ->line('Wenn Sie diese Einladung nicht erwartet haben, ignorieren Sie diese E-Mail bitte einfach.')
            ->salutation('Viele Grüße'."\n".$portal);
    }
}
