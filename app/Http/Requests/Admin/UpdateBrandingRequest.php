<?php

namespace App\Http\Requests\Admin;

use App\Enums\LegalLinkMode;
use App\Models\Branding;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', Branding::current()) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // PNG and WebP only. An SVG served from the portal's own origin could
        // carry script; a raster image cannot. The type is taken from the
        // file's content, not from its name.
        $logo = [
            'nullable', 'file', 'max:512',
            'mimes:png,webp', 'mimetypes:image/png,image/webp',
            'dimensions:min_width=32,min_height=32,max_width=1000,max_height=1000',
        ];

        return [
            'name' => ['nullable', 'string', 'max:60'],
            'footer_text' => ['nullable', 'string', 'max:120'],
            'brand_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'accent_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo_light' => $logo,
            'logo_dark' => $logo,
            'remove_logo_light' => ['boolean'],
            'remove_logo_dark' => ['boolean'],
            // The URLs end up in an href: https only, never javascript: or data:.
            'imprint_mode' => ['sometimes', Rule::enum(LegalLinkMode::class)],
            'imprint_url' => ['nullable', 'string', 'max:2048', 'url:https', 'required_if:imprint_mode,link'],
            'imprint_text' => ['nullable', 'string', 'max:100000', 'required_if:imprint_mode,text'],
            'privacy_mode' => ['sometimes', Rule::enum(LegalLinkMode::class)],
            'privacy_url' => ['nullable', 'string', 'max:2048', 'url:https', 'required_if:privacy_mode,link'],
            'privacy_text' => ['nullable', 'string', 'max:100000', 'required_if:privacy_mode,text'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'brand_color.regex' => 'Bitte geben Sie die Farbe als Hex-Wert an, z. B. #344f68.',
            'accent_color.regex' => 'Bitte geben Sie die Farbe als Hex-Wert an, z. B. #2f6299.',
            'logo_light.mimes' => 'Das Logo muss eine PNG- oder WebP-Datei sein.',
            'logo_light.mimetypes' => 'Das Logo muss eine PNG- oder WebP-Datei sein.',
            'logo_dark.mimes' => 'Das Logo muss eine PNG- oder WebP-Datei sein.',
            'logo_dark.mimetypes' => 'Das Logo muss eine PNG- oder WebP-Datei sein.',
            'logo_light.dimensions' => 'Das Logo muss zwischen 32 und 1000 Pixel breit und hoch sein.',
            'logo_dark.dimensions' => 'Das Logo muss zwischen 32 und 1000 Pixel breit und hoch sein.',
            'imprint_url.url' => 'Bitte geben Sie eine vollständige Adresse mit https:// an.',
            'privacy_url.url' => 'Bitte geben Sie eine vollständige Adresse mit https:// an.',
            'imprint_url.required_if' => 'Für einen eigenen Link brauchen wir die Adresse.',
            'privacy_url.required_if' => 'Für einen eigenen Link brauchen wir die Adresse.',
            'imprint_text.required_if' => 'Für einen eigenen Text fügen Sie bitte den Text ein.',
            'privacy_text.required_if' => 'Für einen eigenen Text fügen Sie bitte den Text ein.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'Schriftzug',
            'footer_text' => 'Fußzeile',
            'brand_color' => 'Hauptfarbe',
            'accent_color' => 'Akzentfarbe',
            'logo_light' => 'Logo für helle Flächen',
            'logo_dark' => 'Logo für dunkle Flächen',
            'imprint_mode' => 'Impressum',
            'imprint_url' => 'Link zum Impressum',
            'privacy_mode' => 'Datenschutz',
            'privacy_url' => 'Link zur Datenschutzerklärung',
            'imprint_text' => 'Text des Impressums',
            'privacy_text' => 'Text der Datenschutzerklärung',
        ];
    }
}
