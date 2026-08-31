<?php

namespace App\Providers;

use App\Contracts\PreviewProvisioner;
use App\Services\Previews\NullPreviewProvisioner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The only provisioner the MVP ships. It changes nothing on the server;
        // see docs/adr/0001-preview-subdomain-architecture.md.
        $this->app->bind(PreviewProvisioner::class, function () {
            return match (config('previews.provisioner')) {
                'null' => $this->app->make(NullPreviewProvisioner::class),
                default => throw new \InvalidArgumentException(
                    'Unbekannter Preview-Provisioner: '.config('previews.provisioner')
                ),
            };
        });
    }

    public function boot(): void
    {
        $this->configureModels();
        $this->configurePasswords();
        $this->configureTrustedProxies();
        $this->configureUrls();
    }

    private function configureModels(): void
    {
        // Outside production, quietly dropping an attribute during fill() is an
        // error rather than a shrug. Controllers only ever fill() validated
        // input, so this never fires on a legitimate request -- it fires when
        // someone adds a field to a form without thinking about $fillable,
        // which is exactly the mistake that turns into a privilege escalation.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
    }

    private function configurePasswords(): void
    {
        // Minimum policy for every password the application accepts. Length is
        // deliberately favoured over composition rules, per the current NIST
        // and BSI guidance.
        //
        // Note what is *not* enabled: ->uncompromised() would check the
        // password against the haveibeenpwned range API, which means talking to
        // a third party on every password change. That is a decision for the
        // operator to make consciously, not a default.
        Password::defaults(fn () => Password::min(12));
    }

    private function configureTrustedProxies(): void
    {
        // Only the proxies listed in TRUSTED_PROXIES may speak for the client.
        // With none configured the X-Forwarded-* headers are ignored entirely,
        // so nobody can claim a different host, scheme or address by sending
        // them. This lives here rather than in bootstrap/app.php because that
        // callback runs before the configuration is loaded.
        //
        // HEADER_X_FORWARDED_AWS_ELB is deliberately absent: Smallgate is self
        // hosted behind a single reverse proxy.
        if ($proxies = config('smallgate.trusted_proxies')) {
            TrustProxies::at($proxies);
        }

        TrustProxies::withHeaders(
            Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO
        );
    }

    private function configureUrls(): void
    {
        // Pin every generated URL to APP_URL instead of letting it follow the
        // incoming request. A password reset link must not depend on the Host
        // header of the request that triggered the mail -- TrustHosts already
        // rejects a foreign Host, this is the second layer and additionally
        // covers queue workers and console commands, where no request exists.
        if ($root = (string) config('app.url')) {
            URL::forceRootUrl($root);
        }

        // Every generated URL is https in production, so a reset or invitation
        // link can never be handed out over plain http.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
