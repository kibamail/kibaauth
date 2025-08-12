<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTeamMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => 'nullable|uuid|exists:users,id',
            'email' => 'nullable|email|max:255',
            'status' => 'sometimes|string|in:active,pending',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $userId = $this->input('user_id');
            $email = $this->input('email');

            if (empty($userId) && empty($email)) {
                $validator->errors()->add('user_id', 'Either user_id or email must be provided.');
                $validator->errors()->add('email', 'Either user_id or email must be provided.');
                return;
            }

            if (!empty($userId) && !empty($email)) {
                $validator->errors()->add('user_id', 'Cannot provide both user_id and email. Choose one.');
                $validator->errors()->add('email', 'Cannot provide both user_id and email. Choose one.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'user_id.uuid' => 'The user ID must be a valid UUID.',
            'user_id.exists' => 'The specified user does not exist.',
            'email.email' => 'The email must be a valid email address.',
            'email.max' => 'The email may not be greater than 255 characters.',
            'status.in' => 'The status must be either active or pending.',
        ];
    }
}
