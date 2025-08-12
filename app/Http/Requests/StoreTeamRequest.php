<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Permission;
use Illuminate\Validation\ValidationException;

class StoreTeamRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'slug' => 'nullable|string|max:255|alpha_dash',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'uuid|exists:permissions,id',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('permission_ids') && !empty($this->permission_ids)) {
                $this->validatePermissionsBelongToClient($validator);
            }
        });
    }

    /**
     * Validate that all permissions belong to the same client as the workspace.
     */
    protected function validatePermissionsBelongToClient($validator): void
    {
        $workspace = $this->route('workspace');
        $clientId = $workspace->client_id;

        $invalidPermissions = Permission::whereIn('id', $this->permission_ids)
            ->where('client_id', '!=', $clientId)
            ->exists();

        if ($invalidPermissions) {
            $validator->errors()->add(
                'permission_ids',
                'One or more permissions do not belong to this client.'
            );
        }
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Team name is required.',
            'name.string' => 'Team name must be a string.',
            'name.max' => 'Team name must not exceed 255 characters.',
            'slug.alpha_dash' => 'Team slug may only contain letters, numbers, dashes, and underscores.',
            'slug.max' => 'Team slug must not exceed 255 characters.',
            'permission_ids.array' => 'Permission IDs must be an array.',
            'permission_ids.*.integer' => 'Each permission ID must be an integer.',
            'permission_ids.*.exists' => 'One or more permission IDs do not exist.',
        ];
    }
}
