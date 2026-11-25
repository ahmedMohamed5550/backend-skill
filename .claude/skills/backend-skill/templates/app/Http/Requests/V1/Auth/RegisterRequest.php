<?php

namespace App\Http\Requests\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
     * The unique rule rejects a duplicate email address with the standard
     * 422 validation answer before any user is created. The password rules
     * (minimum 8 characters, confirmed through password_confirmation) mirror
     * ResetPasswordRequest so both lifecycles accept the same password
     * shape. The Request never touches hashing: the model's hashed cast
     * hashes the plain password on creation, and the model's creating hook
     * generates the uuid.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ];
    }
}
