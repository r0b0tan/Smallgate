<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Headers for every answer of a preview host (ADR 0003). They leave the draft
 * itself alone -- scripts, fonts and storage work as on any website; its own
 * origin is the isolation -- and only stop what a draft must never do:
 *
 *  - nosniff: a file is only ever what its extension says.
 *  - Cross-Origin-Resource-Policy: all previews share one site, so without it
 *    a draft could embed another preview's files, e.g. its scripts.
 *  - frame-ancestors 'none': no preview inside someone else's page.
 *  - noindex: drafts never end up in a search engine.
 */
class SecurePreviewHostResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        $response->headers->set('Content-Security-Policy', "frame-ancestors 'none'");
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }
}
