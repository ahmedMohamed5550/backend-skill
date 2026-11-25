<?php

namespace App\Http\Requests\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
     * The PKCE fields bind the issued authorization code to this login
     * attempt: the client generates the code_verifier, sends only the S256
     * code_challenge here, and later proves possession of the verifier at
     * POST /oauth/token. S256 is the only accepted method — league would
     * also accept `plain`, so this rule closes the PKCE downgrade path.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
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
