<?php

namespace App\Http\Controllers;

use App\Services\Previews\PreviewAccess;
use App\Services\Previews\PreviewFile;
use App\Services\Previews\PreviewFileResolver;
use App\Services\Previews\PreviewRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Serves a draft on its own preview host, e.g. holzmann.clickit-preview.de
 * (ADR 0003). Runs without the "web" middleware group: there is no portal
 * session here, and the only cookie is the preview session's own.
 *
 * Generated URLs are pinned to the portal (AppServiceProvider), so every
 * redirect that stays on the preview host uses a relative Location.
 */
class PreviewHostController extends Controller
{
    public function __construct(
        private readonly PreviewAccess $access,
        private readonly PreviewFileResolver $files,
    ) {}

    /**
     * Exchange the portal's handoff token for a preview session and drop the
     * token from the address at once.
     */
    public function enter(Request $request): Response
    {
        $token = $request->query('token');
        $session = is_string($token) ? $this->access->redeem($token, $request->getHost()) : null;

        if ($session === null) {
            // Deliberately not back to the portal: a token that keeps failing
            // must not turn into a redirect loop.
            return response()
                ->view('preview-host.link-expired', status: 404)
                ->header('Referrer-Policy', 'no-referrer');
        }

        [$sessionToken, $expiresAt] = $session;

        $response = new RedirectResponse('/', 303, ['Referrer-Policy' => 'no-referrer']);

        // Lax, not Strict: the visit starts in the portal, a different site,
        // and Strict would withhold the cookie on the redirect that follows.
        // See ADR 0003, "Cookie".
        $response->headers->setCookie(Cookie::create(
            PreviewAccess::COOKIE,
            $sessionToken,
            $expiresAt,
            path: '/',
            domain: null,
            secure: true,
            httpOnly: true,
            sameSite: Cookie::SAMESITE_LAX,
        ));

        return $response;
    }

    /**
     * A file of the draft, after checking the visitor's session and rights.
     */
    public function show(Request $request, string $label, string $path = ''): Response
    {
        $visitor = $this->access->visitor($request->cookies->get(PreviewAccess::COOKIE), $request->getHost());

        if ($visitor === null) {
            // The same answer for every host below the preview domain, whether
            // a preview lives there or not. Only the portal decides, with its
            // usual 404 -- or sends a signed-in user straight back with a token.
            return redirect()->route('portal.previews.open', ['hostname' => $request->getHost()]);
        }

        [, $preview] = $visitor;

        // The router trims the trailing slash off the parameter, but it is what
        // tells a directory's index from a redirect -- without it "/about/"
        // would redirect to itself forever.
        if ($path !== '' && str_ends_with($request->getPathInfo(), '/')) {
            $path .= '/';
        }

        $file = $this->files->resolve((string) $preview->target, $path);

        return match (true) {
            $file instanceof PreviewFile => $this->send($request, $file),
            $file instanceof PreviewRedirect => new RedirectResponse($file->location),
            default => response('Nicht gefunden.', 404, ['Content-Type' => 'text/plain; charset=UTF-8']),
        };
    }

    /**
     * Anything but GET and HEAD. Answered here rather than left to the router,
     * which would otherwise try the portal's routes for it.
     */
    public function refuse(): Response
    {
        return response('Methode nicht erlaubt.', 405, [
            'Allow' => 'GET, HEAD',
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    private function send(Request $request, PreviewFile $file): BinaryFileResponse
    {
        $response = new BinaryFileResponse($file->path, 200, ['Content-Type' => $file->mimeType], public: false);

        if ($file->attachment) {
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT);
        }

        // From size and modification time, not from the content: a content
        // hash would read the whole file on every revalidation.
        $response->setEtag(dechex($response->getFile()->getSize()).'-'.dechex($response->getFile()->getMTime()));

        // Kept by the browser, but asked for again every time -- and every
        // request goes through the checks above. Unchanged files cost a 304.
        $response->headers->set('Cache-Control', 'private, no-cache');
        $response->isNotModified($request);

        return $response;
    }
}
