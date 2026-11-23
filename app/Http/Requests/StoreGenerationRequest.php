<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGenerationRequest extends FormRequest
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
            'prompt' => ['required', 'string', 'max:'.config('pixelforge.max_prompt_characters', 4000)],
            'size' => ['required', 'string', Rule::in(config('pixelforge.sizes'))],
            'quality' => ['required', 'string', Rule::in(config('pixelforge.qualities'))],
            'reference_images' => ['present', 'array', 'max:'.config('pixelforge.max_reference_images', 4)],
            'reference_images.*' => ['string', 'url:http,https', 'max:2048', 'distinct'],
            'attempt_token' => ['required', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'prompt.required' => 'Describe the image you want to make.',
            'prompt.max' => 'Prompts can be up to 4,000 characters.',
            'reference_images.max' => 'You can add at most four reference images.',
            'reference_images.*.url' => 'Reference images must be public http or https URLs.',
            'attempt_token.required' => 'The studio could not verify this attempt. Reload the page and try again.',
            'attempt_token.uuid' => 'The studio could not verify this attempt. Reload the page and try again.',
        ];
    }
}
