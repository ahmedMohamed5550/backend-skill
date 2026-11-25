<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\Client;
use RuntimeException;

/**
 * Public API documentation endpoint.
 *
 * Serves the generated docs/API_DOCUMENTATION.md file as a markdown
 * response beginning with the Public Authentication Configuration
 * section. The generated file itself starts with that section, and this
 * endpoint rebuilds the leading section from the database on every
 * request, so the response always carries the runtime values of the
 * seeded public First Party PKCE Client — a re-seeded client is served
 * without regenerating the file (references/authentication.md, Token
 * Lifecycle). Web and Mobile clients discover the login flow
 * configuration (client_id, redirect_uri, code_challenge_method)
 * directly from this public response — the same client and redirect URI
 * serve both — and no client secret or any other internal credential is
 * ever exposed.
 * Approved exception to the module structure: no Service, no Resources.
 */
class DocumentationController extends Controller
{
    /**
     * The seeded public client the login flow uses
     * (references/authentication.md, Token Lifecycle). Its name is the
     * stable lookup key created by PassportClientSeeder.
     */
    protected const FIRST_PARTY_CLIENT_NAME = 'First Party PKCE Client';

    /**
     * The heading of the mandatory first section of the generated
     * documentation (references/api-documentation.md, Public
     * Authentication Configuration).
     */
    protected const SECTION_HEADING = '## Public Authentication Configuration';

    /**
     * Serve the generated API documentation file, beginning with the
     * Public Authentication Configuration section resolved at request time.
     *
     * @OA\Get(
     *     path="/api/v1/documentation",
     *     operationId="documentationShow",
     *     tags={"Documentation"},
     *     summary="Get API documentation",
     *     description="Return the generated <code>docs/API_DOCUMENTATION.md</code> file as a markdown response beginning with the Public Authentication Configuration section: the <code>client_id</code> and registered <code>redirect_uri</code> of the seeded public First Party PKCE Client (resolved from the database at request time; the same client and redirect URI serve Web and Mobile), plus <code>code_challenge_method: S256</code>. No client secret is ever exposed. Frontend and mobile clients access this public link alongside the Swagger UI.<br><br>🌐 <b>Public endpoint:</b> no authentication or permission required.",
     *
     *     @OA\Response(response=200, description="API documentation markdown beginning with the Public Authentication Configuration (client_id, redirect_uri, code_challenge_method=S256)"),
     *     @OA\Response(response=404, description="API documentation has not been generated yet"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function show(): Response|JsonResponse
    {
        try {
            $path = base_path('docs/API_DOCUMENTATION.md');

            if (! is_file($path)) {
                return $this->apiResponse(__('messages.not_found'), 404);
            }

            $documentation = file_get_contents($path);

            if ($documentation === false) {
                throw new RuntimeException('docs/API_DOCUMENTATION.md is not readable.');
            }

            // The generated file starts with the configuration section; the
            // leading section is rebuilt from the database so the response
            // always carries the runtime values, and the file's generation-
            // time copy of the section is stripped first so it is never
            // served twice.
            return response(
                $this->publicAuthenticationSection().$this->documentationBody($documentation),
                200,
                ['Content-Type' => 'text/markdown; charset=utf-8'],
            );
        } catch (\Throwable $exception) {
            Log::error('Failed to serve API documentation.', ['error' => $exception->getMessage()]);

            return $this->apiResponse(__('messages.server_error'), 500);
        }
    }

    /**
     * Build the Public Authentication Configuration section that starts
     * the returned documentation: the dynamically resolved configuration
     * of the seeded public first-party client. Only public values are
     * exposed — never a client secret or any other internal credential.
     * Returns an empty string when the client is not seeded, so the
     * endpoint keeps serving the documentation instead of failing it.
     */
    protected function publicAuthenticationSection(): string
    {
        $client = Client::where('name', self::FIRST_PARTY_CLIENT_NAME)
            ->where('revoked', false)
            ->first();

        if (! $client) {
            // The OAuth clients are missing (seeding has not run): keep the
            // documentation endpoint serving the file instead of failing it,
            // and leave a trace in the log for the operations team.
            Log::warning('The Public Authentication Configuration is unavailable: the First Party PKCE Client is not seeded.');

            return '';
        }

        $redirectUri = $client->redirect_uris[0] ?? '';

        return self::SECTION_HEADING."\n\n"
            ."OAuth 2.0 authorization code + PKCE login configuration for the\n"
            ."first-party client (POST /api/v1/auth/login, then POST /oauth/token).\n"
            ."Web and Mobile use the same client and redirect URI. The client is\n"
            ."public: it holds no secret, and PKCE protects the token exchange.\n\n"
            ."- `client_id`: `{$client->id}`\n"
            ."- `redirect_uri`: `{$redirectUri}`\n"
            ."- `code_challenge_method`: `S256`\n"
            ."\n---\n\n";
    }

    /**
     * Strip a leading Public Authentication Configuration section from the
     * generated file content: the endpoint rebuilds that section from the
     * database, so the file's generation-time snapshot must not appear in
     * the response.
     */
    protected function documentationBody(string $documentation): string
    {
        $trimmed = ltrim($documentation);

        if (! str_starts_with($trimmed, self::SECTION_HEADING)) {
            return $documentation;
        }

        // The approved section ends with a `---` separator before the body.
        $separator = strpos($trimmed, "\n---\n");

        if ($separator !== false) {
            return ltrim(substr($trimmed, $separator + strlen("\n---\n")));
        }

        // A file that does not follow the approved section shape (no
        // separator): cut at the next heading instead of serving a stale
        // section.
        $nextHeading = strpos($trimmed, "\n## ", strlen(self::SECTION_HEADING));

        if ($nextHeading !== false) {
            return ltrim(substr($trimmed, $nextHeading + 1));
        }

        return '';
    }
}
