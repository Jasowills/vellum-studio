<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EstimateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'prompt' => ['nullable', 'string', 'max:'.config('pixelforge.max_prompt_characters', 4000)],
            'size' => ['required', 'string', Rule::in(config('pixelforge.sizes'))],
            'quality' => ['required', 'string', Rule::in(config('pixelforge.qualities'))],
            'reference_images' => ['present', 'array', 'max:'.config('pixelforge.max_reference_images', 4)],
            'reference_images.*' => ['string', 'url:http,https', 'max:2048', 'distinct'],
        ];
    }
}
