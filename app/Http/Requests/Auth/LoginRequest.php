<?php

namespace App\Http\Requests\Auth;

use App\Enums\ActivityAction;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * `is_active` is part of the attempt itself, so a blocked account fails
     * exactly like a wrong password -- same message, same status, no hint that
     * the account exists at all.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $credentials = [
            'email' => mb_strtolower((string) $this->string('email')),
            'password' => (string) $this->string('password'),
            'is_active' => true,
        ];

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            $this->hitRateLimiters();
            $this->recordFailure(User::query()->where('email', $credentials['email'])->first());

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        // A user of a deactivated customer authenticates correctly but must not
        // reach the portal. Same generic message again.
        if (! Auth::user()?->canAccessPortal()) {
            $user = Auth::user();
            Auth::guard('web')->logout();
            $this->recordFailure($user);

            $this->hitRateLimiters();

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        // Only the per-account counter. The per-IP one keeps running, or an
        // attacker could reset it by signing into an account of their own.
        RateLimiter::clear($this->throttleKey());
    }

    private function hitRateLimiters(): void
    {
        RateLimiter::hit($this->throttleKey(), $this->decaySeconds());
        RateLimiter::hit($this->ipThrottleKey(), $this->decaySeconds());
    }

    /**
     * Failed sign-ins are logged against the account they aimed at. Attempts
     * on addresses without an account are not logged at all: the typed address
     * is never stored.
     */
    private function recordFailure(?User $user): void
    {
        if ($user !== null) {
            Activity::record(ActivityAction::LoginFailed, $user);
        }
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        $limits = [
            $this->throttleKey() => (int) config('smallgate.login.max_attempts', 5),
            $this->ipThrottleKey() => (int) config('smallgate.login.max_attempts_per_ip', 20),
        ];

        $exceeded = array_keys(array_filter(
            $limits,
            fn (int $maxAttempts, string $key) => RateLimiter::tooManyAttempts($key, $maxAttempts),
            ARRAY_FILTER_USE_BOTH,
        ));

        if ($exceeded === []) {
            return;
        }

        Event::dispatch(new Lockout($this));

        // Same message for either limit: which one was hit is nobody's business.
        $seconds = max(array_map(fn (string $key) => RateLimiter::availableIn($key), $exceeded));

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => (int) ceil($seconds / 60),
            ]),
        ]);
    }

    private function decaySeconds(): int
    {
        return (int) config('smallgate.login.decay_seconds', 60);
    }

    /**
     * Throttle per email+IP combination: guessing against one account never
     * locks another one out, and a single IP is limited per account.
     *
     * An attacker with many source addresses gets a fresh budget per address;
     * distributed guessing is not covered here.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(
            Str::lower((string) $this->string('email')).'|'.$this->ip()
        );
    }

    /**
     * Throttle per IP across all addresses: a few guesses against every
     * account from one source add up here.
     */
    public function ipThrottleKey(): string
    {
        return 'login-ip|'.$this->ip();
    }
}
