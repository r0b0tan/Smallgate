<?php

namespace Database\Seeders;

use App\Enums\PreviewStatus;
use App\Enums\PreviewTargetType;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Preview;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

/**
 * Development seed data.
 *
 * Passwords come from environment variables and default to an obviously
 * throwaway value. The seeder refuses to run in production, so these accounts
 * can never appear on a live system by accident.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (App::isProduction()) {
            $this->command?->error('Der Demo-Seeder wird in der Produktionsumgebung nicht ausgeführt.');

            return;
        }

        $password = env('SEED_PASSWORD', 'passwort-nur-fuer-lokale-entwicklung');

        $admin = new User;
        $admin->name = env('SEED_ADMIN_NAME', 'Admin');
        $admin->email = env('SEED_ADMIN_EMAIL', 'admin@example.test');
        $admin->password = $password;
        $admin->role = UserRole::Admin;
        $admin->customer_id = null;
        $admin->is_active = true;
        $admin->email_verified_at = now();
        $admin->save();

        $this->seedHolzmann($password);
        $this->seedBergblick($password);
        $this->seedInactiveCustomer($password);

        $this->command?->info('Seed-Daten angelegt. Admin: '.$admin->email);
        $this->command?->info('Passwort für alle Demo-Zugänge: '.$password);
    }

    private function seedHolzmann(string $password): void
    {
        $customer = Customer::create([
            'name' => 'Holzmann Bau GmbH',
            'slug' => 'holzmann',
            'contact_email' => 'kontakt@holzmann.test',
            'is_active' => true,
        ]);

        $this->customerUser($customer, 'Marion Holzmann', 'marion@holzmann.test', $password);
        $this->customerUser($customer, 'Peter Holzmann', 'peter@holzmann.test', $password);

        $relaunch = $this->project($customer, 'Website-Relaunch', 'website-relaunch', ProjectStatus::Active,
            'Kompletter Relaunch der Unternehmenswebsite inklusive neuer Bildsprache und Karriereseite.');

        // An available preview: hostname and target are both set, which the
        // previews_available_needs_target_check constraint requires.
        $this->preview($relaunch, 'Stand Kalenderwoche 12', 'kw12', PreviewStatus::Available, 'holzmann');
        $this->preview($relaunch, 'Entwurf Karriereseite', 'karriere', PreviewStatus::Draft);

        $this->project($customer, 'Wartung 2026', 'wartung-2026', ProjectStatus::WaitingForFeedback,
            'Laufende Pflege, Updates und kleinere Anpassungen.');
    }

    private function seedBergblick(string $password): void
    {
        $customer = Customer::create([
            'name' => 'Hotel Bergblick',
            'slug' => 'bergblick',
            'contact_email' => 'info@bergblick.test',
            'is_active' => true,
        ]);

        $this->customerUser($customer, 'Sabine Wirth', 'sabine@bergblick.test', $password);

        $buchung = $this->project($customer, 'Buchungsstrecke', 'buchungsstrecke', ProjectStatus::Active,
            'Neue Buchungsstrecke mit direkter Zimmerauswahl.');

        $this->preview($buchung, 'Prototyp', 'prototyp', PreviewStatus::Available, 'bergblick');

        $this->project($customer, 'Foto-Shooting Landingpage', 'fotoshooting', ProjectStatus::Completed,
            'Landingpage zur Sommerkampagne. Abgeschlossen und übergeben.');
    }

    /**
     * A deactivated customer, so the "deactivated customer loses access"
     * behaviour can be tried out by hand as well as in the test suite.
     */
    private function seedInactiveCustomer(string $password): void
    {
        $customer = Customer::create([
            'name' => 'Altmann Immobilien (Archiv)',
            'slug' => 'altmann',
            'contact_email' => 'archiv@altmann.test',
            'is_active' => false,
        ]);

        $this->customerUser($customer, 'Jörg Altmann', 'joerg@altmann.test', $password);

        $this->project($customer, 'Alte Website', 'alte-website', ProjectStatus::Archived,
            'Archiviertes Projekt eines deaktivierten Kunden.');
    }

    private function customerUser(Customer $customer, string $name, string $email, string $password): User
    {
        $user = new User;
        $user->name = $name;
        $user->email = $email;
        $user->password = $password;
        $user->role = UserRole::Customer;
        $user->customer_id = $customer->id;
        $user->is_active = true;
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    private function project(
        Customer $customer,
        string $name,
        string $slug,
        ProjectStatus $status,
        string $description,
    ): Project {
        $project = new Project;
        $project->fill([
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'status' => $status,
        ]);
        $project->customer_id = $customer->id;
        $project->save();

        return $project;
    }

    private function preview(
        Project $project,
        string $name,
        string $slug,
        PreviewStatus $status,
        ?string $subdomain = null,
    ): Preview {
        $preview = new Preview;
        $preview->fill([
            'name' => $name,
            'slug' => $slug,
            'target_type' => PreviewTargetType::StaticDirectory,
        ]);
        // Not fillable: the status decides whether the customer is offered the
        // preview, so it is assigned explicitly here just as the admin actions do.
        $preview->status = $status;

        if ($subdomain !== null) {
            $roots = (array) config('previews.allowed_roots', []);

            $preview->hostname = $subdomain.'.'.config('previews.base_domain');
            // Always inside an allow-listed root -- the same rule the admin
            // form and the provisioner enforce.
            $preview->target = rtrim((string) ($roots[0] ?? '/srv/previews'), '/').'/'.$subdomain.'/'.$slug;
        }

        $preview->project_id = $project->id;
        $preview->provisioned_at = $status === PreviewStatus::Available ? now() : null;
        $preview->save();

        return $preview;
    }
}
