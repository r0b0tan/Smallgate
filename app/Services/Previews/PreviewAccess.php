<?php

namespace App\Services\Previews;

use App\Models\Preview;
use App\Models\PreviewHandoff;
use App\Models\PreviewSession;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Gate;
use LogicException;

/**
 * Gets a signed-in portal user onto a preview host and recognises them there
 * (ADR 0003).
 *
 * The portal session never leaves the portal host. Instead the portal hands
 * out a one-time token, valid for seconds, and the preview host exchanges it
 * for a session of its own that is bound to that one preview. Both tokens are
 * random, only their SHA-256 hashes are stored, and neither is ever logged.
 *
 * Holding a valid session is not enough: every request re-checks
 * PreviewPolicy::open, so a disabled preview, a blocked user or a project
 * moved to another customer locks the visitor out at once.
 */
class PreviewAccess
{
    /**
     * Host-only by its prefix: the browser refuses it with a Domain attribute,
     * so one preview cannot plant a cookie for another (cookie tossing).
     */
    public const COOKIE = '__Host-smallgate-preview';

    /** Below PreviewFileResolver::RESERVED_PREFIX, so no draft can shadow it. */
    public const HANDOFF_PATH = '/__smallgate/zugang';

    /**
     * Hand out a one-time token for the preview and return the address on the
     * preview host that redeems it. The caller has checked PreviewPolicy::open.
     */
    public function handoffUrl(User $user, Preview $preview): string
    {
        $host = $preview->hostUrl() ?? throw new LogicException('Preview without a valid host.');
        $token = $this->newToken();
        $now = now();

        $handoff = new PreviewHandoff;
        $handoff->preview_id = $preview->id;
        $handoff->user_id = $user->id;
        $handoff->token_hash = $this->hash($token);
        $handoff->created_at = $now;
        $handoff->expires_at = $now->copy()->addSeconds((int) config('previews.access.handoff_seconds'));
        $handoff->save();

        return $host.self::HANDOFF_PATH.'?token='.$token;
    }

    /**
     * Exchange a handoff token for a session on the host it was issued for.
     * Returns the session token for the cookie and its expiry -- or null for a
     * token that is unknown, used, expired, meant for another host or no
     * longer covered by the user's rights.
     *
     * @return array{0: string, 1: CarbonInterface}|null
     */
    public function redeem(string $token, string $host): ?array
    {
        if (! $this->isWellFormed($token)) {
            return null;
        }

        $now = now();
        $hash = $this->hash($token);

        // A single statement, so of two requests racing with the same token
        // only one can set used_at.
        $redeemed = PreviewHandoff::query()
            ->where('token_hash', $hash)
            ->whereNull('used_at')
            ->where('expires_at', '>', $now)
            ->update(['used_at' => $now]);

        if ($redeemed !== 1) {
            return null;
        }

        $handoff = PreviewHandoff::query()->with(['preview', 'user'])->where('token_hash', $hash)->first();
        $preview = $handoff?->preview;
        $user = $handoff?->user;

        if ($preview === null || $user === null || $preview->hostname !== $host
            || Gate::forUser($user)->denies('open', $preview)) {
            return null;
        }

        $this->pruneExpired($now);

        $sessionToken = $this->newToken();

        $session = new PreviewSession;
        $session->preview_id = $preview->id;
        $session->user_id = $user->id;
        $session->token_hash = $this->hash($sessionToken);
        $session->created_at = $now;
        $session->expires_at = $now->copy()->addHours((int) config('previews.access.session_hours'));
        $session->save();

        return [$sessionToken, $session->expires_at];
    }

    /**
     * The user and preview behind a session cookie on this host, checked
     * afresh on every request -- or null, and the visitor goes back through
     * the portal.
     *
     * @return array{0: User, 1: Preview}|null
     */
    public function visitor(?string $sessionToken, string $host): ?array
    {
        if ($sessionToken === null || ! $this->isWellFormed($sessionToken)) {
            return null;
        }

        $session = PreviewSession::query()
            ->with('user')
            ->where('token_hash', $this->hash($sessionToken))
            ->where('expires_at', '>', now())
            ->first();

        $user = $session?->user;

        if ($user === null) {
            return null;
        }

        $preview = Preview::query()
            ->visibleTo($user)
            ->whereKey($session->preview_id)
            ->where('hostname', $host)
            ->first();

        if ($preview === null || Gate::forUser($user)->denies('open', $preview)) {
            return null;
        }

        return [$user, $preview];
    }

    /**
     * Signing out of the portal ends every preview session of the user too.
     */
    public function endSessionsOf(User $user): void
    {
        PreviewSession::query()->where('user_id', $user->id)->delete();
    }

    /**
     * Expired rows are useless and are cleared on the way, so no scheduler is
     * needed for it.
     */
    private function pruneExpired(CarbonInterface $now): void
    {
        PreviewHandoff::query()->where('expires_at', '<', $now)->delete();
        PreviewSession::query()->where('expires_at', '<', $now)->delete();
    }

    private function newToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    private function isWellFormed(string $token): bool
    {
        return preg_match('/^[0-9a-f]{64}$/', $token) === 1;
    }
}
