<?php

namespace App\Support\Slack;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

class SlackAppClient
{
    public function authorizeUrl(int $organizationId): string
    {
        $state = Crypt::encryptString((string) $organizationId);

        return 'https://slack.com/oauth/v2/authorize?'.http_build_query([
            'client_id' => config('services.slack_app.client_id'),
            'scope' => config('services.slack_app.scopes'),
            'state' => $state,
            'redirect_uri' => route('integrations.slack.callback'),
        ]);
    }

    public function decryptState(string $state): int
    {
        return (int) Crypt::decryptString($state);
    }

    /**
     * @return array<string, mixed>
     */
    public function exchangeCode(string $code): array
    {
        $response = Http::asForm()->post('https://slack.com/api/oauth.v2.access', [
            'client_id' => config('services.slack_app.client_id'),
            'client_secret' => config('services.slack_app.client_secret'),
            'code' => $code,
            'redirect_uri' => route('integrations.slack.callback'),
        ]);

        return $response->json();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listChannels(string $botToken): array
    {
        $response = Http::withToken($botToken)->get('https://slack.com/api/conversations.list', [
            'types' => 'public_channel,private_channel',
        ]);

        $channels = $response->json('channels') ?? [];
        $cursor = $response->json('response_metadata.next_cursor');

        if (! empty($cursor)) {
            $next = Http::withToken($botToken)->get('https://slack.com/api/conversations.list', [
                'types' => 'public_channel,private_channel',
                'cursor' => $cursor,
            ]);

            $channels = array_merge($channels, $next->json('channels') ?? []);
        }

        return $channels;
    }

    public function postMessage(string $botToken, string $channelId, string $text): void
    {
        Http::withToken($botToken)->post('https://slack.com/api/chat.postMessage', [
            'channel' => $channelId,
            'text' => $text,
        ]);
    }
}
