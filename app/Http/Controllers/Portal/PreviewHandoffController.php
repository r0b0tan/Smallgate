<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Preview;
use App\Services\Previews\PreviewAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Where a preview host sends a visitor without a valid session (ADR 0003) --
 * a bookmark, a link from a mail, an expired session. A signed-in user who
 * may open the preview goes straight back with a fresh handoff token.
 */
class PreviewHandoffController extends Controller
{
    public function __invoke(Request $request, PreviewAccess $access, string $hostname): RedirectResponse
    {
        $user = $request->user();

        $preview = Preview::query()
            ->visibleTo($user)
            ->where('hostname', mb_strtolower($hostname))
            ->first();

        // Unknown, foreign or not live: one and the same 404, never a 403.
        abort_unless($preview !== null && Gate::allows('open', $preview), 404);

        return redirect()->away($access->handoffUrl($user, $preview));
    }
}
