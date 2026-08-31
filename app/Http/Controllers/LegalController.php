<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Imprint and privacy policy.
 *
 * Placeholders for now, filled entirely from environment variables so no
 * personal data of the operator lives in the repository. Replace the view text
 * with the reviewed legal wording before going live.
 */
class LegalController extends Controller
{
    public function imprint(): View
    {
        return view('legal.imprint', ['legal' => config('smallgate.legal')]);
    }

    public function privacy(): View
    {
        return view('legal.privacy', ['legal' => config('smallgate.legal')]);
    }
}
