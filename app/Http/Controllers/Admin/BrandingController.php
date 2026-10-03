<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateBrandingRequest;
use App\Models\Activity;
use App\Models\Branding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * "Erscheinungsbild": name, footer text, colours and logos of the portal.
 */
class BrandingController extends Controller
{
    public function edit(): View
    {
        $branding = Branding::current();

        $this->authorize('update', $branding);

        return view('admin.branding.edit', ['branding' => $branding]);
    }

    public function update(UpdateBrandingRequest $request): RedirectResponse
    {
        $branding = Branding::current();

        $this->authorize('update', $branding);

        $branding->fill($request->safe()->only([
            'name', 'footer_text', 'brand_color', 'accent_color',
            'imprint_mode', 'imprint_url', 'imprint_text',
            'privacy_mode', 'privacy_url', 'privacy_text',
        ]));

        $disk = Storage::disk(Branding::DISK);
        $replaced = [];

        foreach (array_keys(Branding::LOGO_VARIANTS) as $variant) {
            $file = $request->file("logo_{$variant}");

            if ($file === null && ! $request->boolean("remove_logo_{$variant}")) {
                continue;
            }

            $replaced[] = $branding->getAttribute("logo_{$variant}_path");

            // Not fillable: only a stored, validated upload may set these.
            $branding->setAttribute("logo_{$variant}_path", $file?->store(Branding::DIRECTORY, Branding::DISK) ?: null);
            $branding->setAttribute("logo_{$variant}_mime", $file?->getMimeType());
        }

        $branding->save();

        // Old files go only once the row no longer points at them.
        $disk->delete(array_values(array_filter($replaced)));

        if ($branding->wasRecentlyCreated || $branding->wasChanged()) {
            Activity::record(ActivityAction::BrandingUpdated);
        }

        return redirect()->route('admin.branding.edit')
            ->with('status', 'Erscheinungsbild wurde gespeichert.');
    }
}
