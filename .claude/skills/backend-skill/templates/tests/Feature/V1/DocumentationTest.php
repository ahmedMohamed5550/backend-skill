<?php

namespace Tests\Feature\V1;

use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Tests\TestCase;

/**
 * Public documentation endpoint spec (GET /api/v1/documentation).
 *
 * The generated docs/API_DOCUMENTATION.md file starts with the Public
 * Authentication Configuration section, and the endpoint serves the
 * documentation beginning with that section carrying the runtime values:
 * the dynamically resolved client_id, first registered redirect_uri, and
 * the S256 code_challenge_method of the seeded public First Party PKCE
 * Client, rebuilt from the database on every request
 * (references/authentication.md, Token Lifecycle). Web and Mobile clients
 * use the same client and redirect URI, discover them directly from this
 * public response, and no client secret is ever exposed.
 *
 * The endpoint's existing contract must stay intact: no authentication,
 * the generated file body, and the 404 envelope while the documentation
 * file has not been generated yet.
 */
class DocumentationTest extends TestCase
{
    protected function firstPartyClientId(): string
    {
        return Client::where('name', 'First Party PKCE Client')
            ->where('revoked', false)
            ->sole()
            ->id;
    }

    protected function firstPartyRedirectUri(): string
    {
        return Client::where('name', 'First Party PKCE Client')
            ->where('revoked', false)
            ->sole()
            ->redirect_uris[0];
    }

    public function test_documentation_is_publicly_accessible(): void
    {
        // No authentication, no permission: the anonymous request succeeds
        // and receives the markdown document, not the JSON envelope.
        $this->getJson('/api/v1/documentation')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=utf-8');
    }

    public function test_documentation_starts_with_the_first_party_pkce_configuration(): void
    {
        $clientId = $this->firstPartyClientId();
        $redirectUri = $this->firstPartyRedirectUri();

        $content = $this->getJson('/api/v1/documentation')->getContent();

        // The Public Authentication Configuration section is the mandatory
        // first section of the served documentation: the frontend reads the
        // runtime configuration straight from the top of the response.
        $this->assertStringStartsWith('## Public Authentication Configuration', $content);

        // The section resolves the seeded client dynamically: the values
        // must be the actual database values of the First Party PKCE
        // Client, each on its labeled configuration line.
        $this->assertMatchesRegularExpression(
            '/client_id[^\n]*'.preg_quote($clientId, '/').'/',
            $content,
            'The documentation must expose the seeded First Party PKCE Client id as client_id.'
        );
        $this->assertMatchesRegularExpression(
            '/redirect_uri[^\n]*'.preg_quote($redirectUri, '/').'/',
            $content,
            'The documentation must expose the client\'s registered redirect URI.'
        );
        $this->assertMatchesRegularExpression('/code_challenge_method[^\n]*S256/', $content);
    }

    public function test_documentation_configuration_is_resolved_at_request_time(): void
    {
        // The section's values come from the database at request time, not
        // from the generation-time snapshot baked into the file: replacing
        // the seeded client must be reflected in the next response without
        // regenerating the documentation file.
        $oldClientId = $this->firstPartyClientId();
        Client::where('name', 'First Party PKCE Client')->delete();

        $client = new Client();
        $client->forceFill([
            'id' => (string) Str::uuid(),
            'name' => 'First Party PKCE Client',
            'redirect_uris' => ['https://reseeded.example.test/callback'],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'secret' => null,
            'provider' => null,
            'revoked' => false,
        ])->save();

        $content = $this->getJson('/api/v1/documentation')->getContent();

        $this->assertStringContainsString($client->id, $content);
        $this->assertStringContainsString('https://reseeded.example.test/callback', $content);
        $this->assertStringNotContainsString($oldClientId, $content);
    }

    public function test_documentation_never_exposes_client_secrets(): void
    {
        // Plant a secret on the public client and a value identifying the
        // personal access client: neither may ever reach the response.
        Client::where('name', 'First Party PKCE Client')
            ->update(['secret' => 'planted-secret-value-that-must-never-leak']);
        $personalAccessClientId = Client::where('name', 'Personal Access Client')->sole()->id;

        $content = $this->getJson('/api/v1/documentation')->getContent();

        $this->assertStringNotContainsString('planted-secret-value-that-must-never-leak', $content);
        $this->assertStringNotContainsString('client_secret', $content);
        $this->assertStringNotContainsString($personalAccessClientId, $content);
    }

    public function test_documentation_serves_the_generated_file_content(): void
    {
        $path = base_path('docs/API_DOCUMENTATION.md');
        $fileContent = (string) file_get_contents($path);

        $content = $this->getJson('/api/v1/documentation')->getContent();

        // The generated file starts with the configuration section; the
        // response rebuilds that leading section with the runtime database
        // values and keeps the file body after the `---` separator. A file
        // that does not yet follow the approved shape is served as-is after
        // the rebuilt section.
        $this->assertStringStartsWith('## Public Authentication Configuration', $content);

        $separator = strpos($fileContent, "\n---\n");
        $fileBody = $separator === false
            ? ltrim($fileContent)
            : ltrim(substr($fileContent, $separator + strlen("\n---\n")));

        if ($fileBody !== '') {
            $this->assertStringContainsString($fileBody, $content);
        }
    }

    public function test_documentation_returns_the_404_envelope_when_the_file_is_missing(): void
    {
        $path = base_path('docs/API_DOCUMENTATION.md');
        $backupPath = $path.'.missing-under-test';
        rename($path, $backupPath);

        try {
            $this->getJson('/api/v1/documentation')
                ->assertStatus(404)
                ->assertJsonPath('status_code', 404)
                ->assertHeader('Content-Type', 'application/json');
        } finally {
            rename($backupPath, $path);
        }
    }

    public function test_documentation_still_serves_when_the_first_party_client_is_missing(): void
    {
        // Seeding has not run: the endpoint must keep serving the
        // documentation, without a fabricated configuration section — and
        // without the stale generation-time snapshot of the section the
        // generated file starts with.
        $clientId = $this->firstPartyClientId();
        Client::where('name', 'First Party PKCE Client')->delete();

        $content = $this->getJson('/api/v1/documentation')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('## Public Authentication Configuration', $content);
        $this->assertStringNotContainsString($clientId, $content);
    }
}
