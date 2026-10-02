<?php

use App\Enums\PreviewStatus;
use App\Enums\ThumbnailStatus;
use App\Enums\UserRole;
use App\Jobs\GeneratePreviewThumbnail;
use App\Models\Preview;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Queue thumbnails for every available preview whose current version has
 * none yet -- after the first deployment of thumbnails, or after the browser
 * was missing. --all also redoes the ones that exist.
 */
Artisan::command('previews:thumbnails {--all : Auch vorhandene Vorschaubilder neu erstellen}', function () {
    if (! config('previews.thumbnails.enabled')) {
        $this->error('Vorschaubilder sind in der Konfiguration abgeschaltet.');

        return 1;
    }

    $count = 0;

    Preview::query()
        ->where('status', PreviewStatus::Available)
        ->where('version', '>=', 1)
        ->each(function (Preview $preview) use (&$count) {
            if (! $this->option('all') && $preview->hasCurrentThumbnail()) {
                return;
            }

            // Query builder on purpose: thumbnail state must not move updated_at.
            Preview::query()->whereKey($preview->id)->toBase()
                ->update(['thumbnail_status' => ThumbnailStatus::Pending->value]);

            GeneratePreviewThumbnail::for($preview);
            $count++;
        });

    $this->info("{$count} Vorschaubild(er) in die Warteschlange gestellt.");

    return 0;
})->purpose('Vorschaubilder für verfügbare Vorschauen erzeugen');

/*
 * Create an administrator -- the first one after a fresh deployment, or any
 * further one. Customer users are never created here; they come only through
 * an invitation. The password is asked for hidden and never accepted as an
 * argument or option: the process list and the shell history are readable.
 */
Artisan::command('admin:create', function () {
    if (! $this->input->isInteractive()) {
        $this->error('Dieser Befehl fragt das Passwort verdeckt ab und läuft nur interaktiv.');

        return 1;
    }

    $input = [
        'name' => trim((string) $this->ask('Name')),
        'email' => mb_strtolower(trim((string) $this->ask('E-Mail-Adresse'))),
        'password' => (string) $this->secret('Passwort (mindestens 12 Zeichen)'),
        'password_confirmation' => (string) $this->secret('Passwort wiederholen'),
    ];

    $validator = Validator::make($input, [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
        'password' => ['required', 'confirmed', Password::defaults()],
    ]);

    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $message) {
            $this->error($message);
        }

        return 1;
    }

    // Not mass assignable on purpose: role, customer and active flag are set
    // explicitly, as in every other administrator-only code path.
    $user = new User;
    $user->name = $input['name'];
    $user->email = $input['email'];
    $user->password = $input['password']; // hashed by the model cast
    $user->role = UserRole::Admin;
    $user->customer_id = null;
    $user->is_active = true;
    $user->email_verified_at = now();
    $user->save();

    $this->info("Administrator {$user->email} angelegt.");

    return 0;
})->purpose('Administrator anlegen');
