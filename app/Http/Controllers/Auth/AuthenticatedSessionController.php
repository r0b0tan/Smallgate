<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // Session fixation defence: the id the visitor arrived with must not
        // survive the privilege change from guest to authenticated user.
        $request->session()->regenerate();

        $user = $request->user();
        $user->forceFill(['last_login_at' => Carbon::now()])->save();

        return redirect()->intended(
            $user->isAdmin() ? route('admin.dashboard') : route('portal.dashboard')
        );
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Sie wurden abgemeldet.');
    }
}
