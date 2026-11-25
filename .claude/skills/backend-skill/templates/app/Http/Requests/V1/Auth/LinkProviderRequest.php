<?php

namespace App\Http\Requests\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LinkProviderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Linking runs under the auth:api middleware on the route group: only an
     * authenticated user can attach a provider identity to their account.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Linking does not issue tokens, so no PKCE fields are involved: exactly
     * one provider credential (access token or ID token) proves the
     * provider identity to attach.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'access_token' => ['required_without:id_token', 'prohibited_with:id_token', 'string'],
            'id_token' => ['required_without:access_token', 'prohibited_with:access_token', 'string'],
        ];
    }
}
