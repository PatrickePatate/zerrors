<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

#[Signature('zerrors:send-test-error {dsn : The project DSN, e.g. https://<public_key>@host/<project_id>} {--message= : Override the exception message}')]
#[Description('Throw a real (small, harmless) exception and ship it to the given Zerrors DSN, like an SDK would.')]
class SendTestError extends Command
{
    public function handle(): int
    {
        $dsn = $this->parseDsn((string) $this->argument('dsn'));

        if (! $dsn) {
            $this->error('That DSN could not be parsed. Expected format: https://<public_key>@host/<project_id>');

            return self::FAILURE;
        }

        $exception = $this->captureException();

        $eventId = str_replace('-', '', (string) Str::uuid());
        $payload = $this->buildPayload($eventId, $exception);

        $envelope = json_encode(['event_id' => $eventId])."\n"
            .json_encode(['type' => 'event'])."\n"
            .json_encode($payload)."\n";

        $response = Http::withHeaders([
            'Content-Type' => 'application/x-sentry-envelope',
            'X-Sentry-Auth' => 'Sentry sentry_version=7, sentry_client=zerrors-test-error/1.0, sentry_key='.$dsn['publicKey'],
        ])->withBody($envelope, 'application/x-sentry-envelope')
            ->post("{$dsn['baseUrl']}/api/{$dsn['projectId']}/envelope/");

        if (! $response->successful()) {
            $this->error("Ingest endpoint responded with HTTP {$response->status()}: {$response->body()}");

            return self::FAILURE;
        }

        $exceptionClass = $exception::class;
        $this->info("Sent \"{$exception->getMessage()}\" ({$exceptionClass}) to {$dsn['baseUrl']}, project {$dsn['projectId']}.");
        $this->line('Event id: '.($response->json('id') ?? $eventId));

        return self::SUCCESS;
    }

    /**
     * @return array{baseUrl: string, projectId: string, publicKey: string}|null
     */
    protected function parseDsn(string $dsn): ?array
    {
        $parts = parse_url($dsn);

        if (! $parts || empty($parts['host']) || empty($parts['user']) || empty($parts['path'])) {
            return null;
        }

        $scheme = $parts['scheme'] ?? 'https';
        $port = isset($parts['port']) ? ":{$parts['port']}" : '';

        return [
            'baseUrl' => "{$scheme}://{$parts['host']}{$port}",
            'projectId' => ltrim($parts['path'], '/'),
            'publicKey' => $parts['user'],
        ];
    }

    /**
     * Throws a small, realistic exception a few call-frames deep so the
     * resulting stacktrace actually has something to look at.
     */
    protected function captureException(): Throwable
    {
        try {
            $this->checkoutOrder(-500);
        } catch (Throwable $e) {
            return $e;
        }

        // Unreachable, but keeps static analysis happy about the return type.
        throw new \LogicException('checkoutOrder() was expected to throw.');
    }

    protected function checkoutOrder(int $amountCents): void
    {
        $this->chargeCard($amountCents, 'cus_test_'.Str::random(8));
    }

    protected function chargeCard(int $amountCents, string $customerId): void
    {
        $message = $this->option('message') ?: "Amount must be a positive integer, got {$amountCents}";

        throw new \InvalidArgumentException($message);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPayload(string $eventId, Throwable $exception): array
    {
        return [
            'event_id' => $eventId,
            'timestamp' => time(),
            'level' => 'error',
            'environment' => app()->environment(),
            'server_name' => gethostname() ?: 'cli',
            'sdk' => ['name' => 'zerrors.send-test-error', 'version' => '1.0'],
            'exception' => [
                'values' => [[
                    'type' => $exception::class,
                    'value' => $exception->getMessage(),
                    'stacktrace' => ['frames' => $this->buildFrames($exception)],
                ]],
            ],
            'user' => [
                'id' => '1',
                'email' => 'demo@example.com',
                'username' => 'demo',
                'ip_address' => '127.0.0.1',
            ],
            'request' => [
                'url' => 'https://example.test/checkout',
                'method' => 'POST',
                'query_string' => 'coupon=SAVE10',
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
                    'Accept' => 'application/json',
                ],
            ],
            'contexts' => [
                'os' => ['name' => PHP_OS_FAMILY],
                'runtime' => ['name' => 'PHP', 'version' => PHP_VERSION],
            ],
            'extra' => [
                'order_id' => 'ord_'.Str::random(8),
                'cart_total_cents' => -500,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildFrames(Throwable $exception): array
    {
        $trace = $exception->getTrace();

        $frames = [[
            'filename' => $exception->getFile(),
            'lineno' => $exception->getLine(),
            'function' => $trace[0]['function'] ?? '{main}',
        ]];

        foreach ($trace as $frame) {
            if (! isset($frame['file'])) {
                continue;
            }

            $frames[] = [
                'filename' => $frame['file'],
                'lineno' => $frame['line'] ?? null,
                'function' => isset($frame['class'])
                    ? $frame['class'].($frame['type'] ?? '::').$frame['function']
                    : $frame['function'],
            ];
        }

        $frames = array_reverse($frames);

        return array_map(fn (array $frame) => [
            ...$frame,
            'in_app' => str_starts_with($frame['filename'], base_path().'/app'),
            ...$this->readContext($frame['filename'], $frame['lineno']),
        ], $frames);
    }

    /**
     * @return array{pre_context?: array<int, string>, context_line?: string, post_context?: array<int, string>}
     */
    protected function readContext(?string $filename, ?int $line): array
    {
        if (! $filename || ! $line || ! is_readable($filename)) {
            return [];
        }

        $lines = file($filename);

        if ($lines === false) {
            return [];
        }

        $index = $line - 1;

        return array_filter([
            'pre_context' => array_map(fn ($l) => rtrim($l, "\n"), array_slice($lines, max(0, $index - 2), min(2, $index))),
            'context_line' => isset($lines[$index]) ? rtrim($lines[$index], "\n") : null,
            'post_context' => array_map(fn ($l) => rtrim($l, "\n"), array_slice($lines, $index + 1, 2)),
        ], fn ($v) => $v !== null && $v !== []);
    }
}
