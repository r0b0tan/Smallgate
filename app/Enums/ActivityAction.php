<?php

namespace App\Enums;

/**
 * Everything the activity log records. Adding a case also needs a migration
 * that widens the activities_action_check constraint.
 */
enum ActivityAction: string
{
    case Login = 'login';
    case LoginFailed = 'login_failed';
    case PasswordReset = 'password_reset';
    case PasswordChanged = 'password_changed';
    case ProfileUpdated = 'profile_updated';

    case InvitationSent = 'invitation_sent';
    case InvitationResent = 'invitation_resent';
    case InvitationRevoked = 'invitation_revoked';
    case InvitationAccepted = 'invitation_accepted';
    case UserBlocked = 'user_blocked';
    case UserUnblocked = 'user_unblocked';

    case CustomerCreated = 'customer_created';
    case CustomerUpdated = 'customer_updated';
    case CustomerActivated = 'customer_activated';
    case CustomerDeactivated = 'customer_deactivated';

    case ProjectCreated = 'project_created';
    case ProjectUpdated = 'project_updated';

    case PreviewCreated = 'preview_created';
    case PreviewUpdated = 'preview_updated';
    case PreviewDeleted = 'preview_deleted';
    case PreviewProvisioned = 'preview_provisioned';
    case PreviewProvisionFailed = 'preview_provision_failed';
    case PreviewDisabled = 'preview_disabled';

    case FeedbackApproved = 'feedback_approved';
    case FeedbackChangesRequested = 'feedback_changes_requested';

    case BrandingUpdated = 'branding_updated';

    public function label(): string
    {
        return match ($this) {
            self::Login => 'Angemeldet',
            self::LoginFailed => 'Anmeldung fehlgeschlagen',
            self::PasswordReset => 'Passwort zurückgesetzt',
            self::PasswordChanged => 'Passwort geändert',
            self::ProfileUpdated => 'Profil geändert',
            self::InvitationSent => 'Einladung versendet',
            self::InvitationResent => 'Einladung erneut versendet',
            self::InvitationRevoked => 'Einladung zurückgezogen',
            self::InvitationAccepted => 'Einladung angenommen',
            self::UserBlocked => 'Zugang gesperrt',
            self::UserUnblocked => 'Zugang entsperrt',
            self::CustomerCreated => 'Kunde angelegt',
            self::CustomerUpdated => 'Kunde geändert',
            self::CustomerActivated => 'Kunde aktiviert',
            self::CustomerDeactivated => 'Kunde deaktiviert',
            self::ProjectCreated => 'Projekt angelegt',
            self::ProjectUpdated => 'Projekt geändert',
            self::PreviewCreated => 'Vorschau angelegt',
            self::PreviewUpdated => 'Vorschau geändert',
            self::PreviewDeleted => 'Vorschau gelöscht',
            self::PreviewProvisioned => 'Vorschau bereitgestellt',
            self::PreviewProvisionFailed => 'Bereitstellung fehlgeschlagen',
            self::PreviewDisabled => 'Vorschau deaktiviert',
            self::FeedbackApproved => 'Entwurf freigegeben',
            self::FeedbackChangesRequested => 'Änderung gewünscht',
            self::BrandingUpdated => 'Erscheinungsbild geändert',
        };
    }

    /**
     * The filter on the log page groups actions by topic.
     */
    public function group(): string
    {
        return match ($this) {
            self::Login, self::LoginFailed, self::PasswordReset, self::PasswordChanged,
            self::ProfileUpdated => 'access',
            self::InvitationSent, self::InvitationResent, self::InvitationRevoked, self::InvitationAccepted,
            self::UserBlocked, self::UserUnblocked, self::CustomerCreated, self::CustomerUpdated,
            self::CustomerActivated, self::CustomerDeactivated => 'customers',
            self::ProjectCreated, self::ProjectUpdated, self::PreviewCreated, self::PreviewUpdated,
            self::PreviewDeleted, self::PreviewProvisioned, self::PreviewProvisionFailed,
            self::PreviewDisabled => 'projects',
            self::FeedbackApproved, self::FeedbackChangesRequested => 'feedback',
            self::BrandingUpdated => 'settings',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function groupOptions(): array
    {
        return [
            'access' => 'Anmeldung und Zugangsdaten',
            'customers' => 'Kunden und Zugänge',
            'projects' => 'Projekte und Vorschauen',
            'feedback' => 'Rückmeldungen',
            'settings' => 'Einstellungen',
        ];
    }

    /**
     * @return list<self>
     */
    public static function inGroup(string $group): array
    {
        return array_values(array_filter(self::cases(), fn (self $case) => $case->group() === $group));
    }

    /**
     * Shown in red: something went wrong or somebody lost access.
     */
    public function isWarning(): bool
    {
        return in_array($this, [
            self::LoginFailed, self::PreviewProvisionFailed, self::UserBlocked, self::CustomerDeactivated,
        ], true);
    }
}
