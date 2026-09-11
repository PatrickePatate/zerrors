<?php

namespace App\Support\Github;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GithubAppClient
{
    /**
     * Builds the URL that starts a GitHub App installation, carrying the
     * organization id (encrypted) through GitHub's "state" round trip.
     */
    public function installUrl(int $organizationId): string
    {
        $state = Crypt::encryptString((string) $organizationId);

        return 'https://github.com/apps/'.config('services.github.slug').'/installations/new?'
            .http_build_query(['state' => $state]);
    }

    public function decryptState(string $state): int
    {
        return (int) Crypt::decryptString($state);
    }

    /**
     * Fetches (and caches) a short-lived installation access token used to
     * authenticate GitHub API requests made on behalf of the installation.
     */
    public function installationToken(string $installationId): string
    {
        return Cache::remember(
            "github-installation-token:{$installationId}",
            now()->addMinutes(55),
            function () use ($installationId): string {
                $response = Http::withToken($this->buildJwt())
                    ->withHeaders(['Accept' => 'application/vnd.github+json'])
                    ->post("https://api.github.com/app/installations/{$installationId}/access_tokens");

                if ($response->failed()) {
                    throw new RuntimeException('Failed to mint a GitHub installation token: '.($response->json('message') ?? $response->status()));
                }

                return $response->json('token');
            }
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listRepositories(string $installationId): array
    {
        $response = Http::withToken($this->installationToken($installationId))
            ->withHeaders(['Accept' => 'application/vnd.github+json'])
            ->get('https://api.github.com/installation/repositories');

        if ($response->failed()) {
            throw new RuntimeException('Failed to list GitHub installation repositories: '.($response->json('message') ?? $response->status()));
        }

        return $response->json('repositories') ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function installation(string $installationId): array
    {
        $response = Http::withToken($this->buildJwt())
            ->withHeaders(['Accept' => 'application/vnd.github+json'])
            ->get("https://api.github.com/app/installations/{$installationId}");

        if ($response->failed()) {
            throw new RuntimeException('Failed to fetch the GitHub installation: '.($response->json('message') ?? $response->status()));
        }

        return $response->json();
    }

    protected function buildJwt(): string
    {
        $now = now()->timestamp;

        return JWT::encode([
            'iat' => $now - 10,
            'exp' => $now + 540,
            'iss' => config('services.github.app_id'),
        ], config('services.github.private_key'), 'RS256');
    }
}
