<?php

namespace App\Http\Requests\Admin;

use App\Models\Branding;
use Illuminate\Foundation\Http\FormRequest;

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
            'dimensions:min_width=32,min_height=32,max_width=2000,max_height=2000',
        ];

        return [
            'name' => ['nullable', 'string', 'max:60'],
            'copyright' => ['nullable', 'string', 'max:120'],
            'brand_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'accent_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo_light' => $logo,
            'logo_dark' => $logo,
            'remove_logo_light' => ['boolean'],
            'remove_logo_dark' => ['boolean'],
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
            'logo_light.dimensions' => 'Das Logo muss zwischen 32 und 2000 Pixel breit und hoch sein.',
            'logo_dark.dimensions' => 'Das Logo muss zwischen 32 und 2000 Pixel breit und hoch sein.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'Schriftzug',
            'copyright' => 'Copyright',
            'brand_color' => 'Hauptfarbe',
            'accent_color' => 'Akzentfarbe',
            'logo_light' => 'Logo für helle Flächen',
            'logo_dark' => 'Logo für dunkle Flächen',
        ];
    }
}
