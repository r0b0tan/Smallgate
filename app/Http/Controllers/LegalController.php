<?php

namespace App\Http\Controllers;

use App\Enums\LegalLinkMode;
use App\Models\Branding;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Imprint and privacy policy, as set under "Erscheinungsbild": the text the
 * administrator pasted in, a redirect to the operator's own page (so old
 * links follow along), or nothing at all.
 */
class LegalController extends Controller
{
    private const TITLES = ['imprint' => 'Impressum', 'privacy' => 'Datenschutzerklärung'];

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
            LegalLinkMode::Text => view('legal.page', [
                'title' => self::TITLES[$page],
                'html' => $branding->legalHtml($page),
            ]),
            LegalLinkMode::Link => redirect()->away($branding->legalUrl($page)),
            LegalLinkMode::Hidden => abort(404),
        };
    }
}
