<?php

namespace App\Http\Controllers;

use App\Enums\LegalLinkMode;
use App\Models\Branding;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Imprint and privacy policy.
 *
 * Placeholders for now, filled entirely from environment variables so no
 * personal data of the operator lives in the repository. Replace the view text
 * with the reviewed legal wording before going live.
 *
 * Under "Erscheinungsbild" an administrator can point either page to the
 * operator's own page instead -- old links then follow it there -- or switch
 * it off, in which case it does not exist.
 */
class LegalController extends Controller
{
    public function imprint(): View|RedirectResponse
    {
        return $this->page('imprint');
    }

    public function privacy(): View|RedirectResponse
    {
        return $this->page('privacy');
    }

    private function page(string $page): View|RedirectResponse
    {
        $branding = Branding::current();

        return match ($branding->legalMode($page)) {
            LegalLinkMode::Builtin => view("legal.{$page}", ['legal' => config('smallgate.legal')]),
            LegalLinkMode::Link => redirect()->away($branding->legalUrl($page)),
            LegalLinkMode::Hidden => abort(404),
        };
    }
}
