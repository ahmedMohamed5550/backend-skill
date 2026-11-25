<?php

namespace App\Http\Requests\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SocialLoginRequest extends FormRequest
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
     * Exactly one provider credential is required: the provider access token
     * (verified with Socialite's userFromToken) or the provider ID token
     * (verified with the provider's own verification path — never decoded
     * unverified; references/authentication.md, Provider tokens: access
     * tokens and ID tokens).
     *
     * The PKCE fields are identical to LoginRequest: they bind the issued
     * authorization code to this login attempt, and S256 is the only
     * accepted method so the downgrade path stays closed.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'access_token' => ['required_without:id_token', 'prohibited_with:id_token', 'string'],
            'id_token' => ['required_without:access_token', 'prohibited_with:access_token', 'string'],
            'client_id' => ['required', 'uuid'],
            'redirect_uri' => ['required', 'string', 'url'],
            'code_challenge' => [
                'required',
                'string',
                'min:43',
                'max:128',
                'regex:/^[A-Za-z0-9\-_]+$/',
            ],
            'code_challenge_method' => ['required', 'string', 'in:S256'],
        ];
    }
}
