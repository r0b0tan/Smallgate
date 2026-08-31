<?php

namespace App\Http\Requests\Admin;

class UpdatePreviewRequest extends StorePreviewRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('preview')) ?? false;
    }
}
