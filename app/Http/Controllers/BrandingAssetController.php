<?php

namespace App\Http\Controllers;

use App\Models\Branding;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The colour overrides and the logos, for every page including the sign-in
 * page -- so public, and without a session (see routes/web.php).
 */
class BrandingAssetController extends Controller
{
    /**
     * Loaded after app.css. Versioned through the query string; a request for
     * an old version still gets the current colours, just not cached.
     */
    public function stylesheet(): Response
    {
        $branding = Branding::current();

        $current = request()->query('v') === $branding->stylesheetVersion();

        return response($branding->stylesheet(), 200, [
            'Content-Type' => 'text/css; charset=utf-8',
            'Cache-Control' => $current ? 'public, max-age=31536000, immutable' : 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Only the current file of a variant, under its current version. The
     * type is the one recorded at upload, never guessed again, and the file
     * is sandboxed should anybody open it directly.
     */
    public function logo(string $variant, string $version): StreamedResponse
    {
        $branding = Branding::current();
        $variant = array_search($variant, Branding::LOGO_VARIANTS, true);

        abort_if($variant === false || $branding->logoVersion($variant) !== $version, 404);

        $path = $branding->getAttribute("logo_{$variant}_path");
        $mime = $branding->getAttribute("logo_{$variant}_mime");
        $disk = Storage::disk(Branding::DISK);

        abort_unless($disk->exists($path), 404);

        return $disk->response($path, 'logo.'.($mime === 'image/webp' ? 'webp' : 'png'), [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
