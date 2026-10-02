<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the *previous* address after a user changed their email address, so
 * the rightful owner notices a change they did not make. It names neither the
 * new address nor anything else about the account.
 *
 * Queued, so a mail server hiccup cannot fail the profile save after the fact.
 * Encrypted, because the payload carries a name and an address.
 */
class EmailChangedNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $name,
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
        $contact = (string) config('smallgate.contact_email');

        return (new MailMessage)
            ->subject('E-Mail-Adresse geändert – '.config('app.name'))
            ->greeting('Hallo '.$this->name.',')
            ->line('die E-Mail-Adresse Ihres Zugangs zum Kundenportal wurde soeben geändert. An diese Adresse schickt das Portal ab jetzt keine Nachrichten mehr.')
            ->line($contact !== ''
                ? "Haben Sie das nicht selbst getan, melden Sie sich bitte umgehend unter {$contact}."
                : 'Haben Sie das nicht selbst getan, melden Sie sich bitte umgehend bei Ihrem Ansprechpartner.')
            ->salutation('Viele Grüße'."\n".config('app.name'));
    }
}
