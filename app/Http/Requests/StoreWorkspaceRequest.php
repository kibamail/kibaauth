<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkspaceRequest extends FormRequest
{

    public function authorize(): bool
    {
        return auth()->check();
    }


    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|alpha_dash',
        ];
    }


    public function messages(): array
    {
        return [
            'name.required' => 'The workspace name is required.',
            'name.string' => 'The workspace name must be a string.',
            'name.max' => 'The workspace name may not be greater than 255 characters.',
            'slug.string' => 'The workspace slug must be a string.',
            'slug.max' => 'The workspace slug may not be greater than 255 characters.',
            'slug.alpha_dash' => 'The workspace slug may only contain letters, numbers, dashes, and underscores.',
        ];
    }
}
