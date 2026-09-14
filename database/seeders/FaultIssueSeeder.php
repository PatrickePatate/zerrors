<?php

namespace Database\Seeders;

use App\Models\FaultEvent;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Support\Fault\EventPayloadCensor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class FaultIssueSeeder extends Seeder
{
    /**
     * Sample exception types/messages/culprits per platform, so seeded issues at
     * least look like something that platform would actually throw.
     *
     * @var array<string, array<int, array{type: string, message: string, culprit: string, file: string}>>
     */
    protected const SAMPLES = [
        'php' => [
            ['type' => 'TypeError', 'message' => 'Argument #1 ($id) must be of type int, string given', 'culprit' => 'UserRepository::find', 'file' => 'app/Repositories/UserRepository.php'],
            ['type' => 'DivisionByZeroError', 'message' => 'Division by zero', 'culprit' => 'PriceCalculator::apply', 'file' => 'app/Services/PriceCalculator.php'],
            ['type' => 'PDOException', 'message' => 'SQLSTATE[HY000] [2002] Connection refused', 'culprit' => 'Database::connect', 'file' => 'app/Support/Database.php'],
        ],
        'laravel' => [
            ['type' => 'Illuminate\\Database\\QueryException', 'message' => 'SQLSTATE[23000]: Integrity constraint violation', 'culprit' => 'OrderController@store', 'file' => 'app/Http/Controllers/OrderController.php'],
            ['type' => 'Illuminate\\Validation\\ValidationException', 'message' => 'The email field is required.', 'culprit' => 'RegisterController@store', 'file' => 'app/Http/Controllers/Auth/RegisterController.php'],
            ['type' => 'ErrorException', 'message' => 'Undefined array key "total"', 'culprit' => 'CartService::checkout', 'file' => 'app/Services/CartService.php'],
            ['type' => 'Illuminate\\Http\\Client\\ConnectionException', 'message' => 'cURL error 28: Operation timed out', 'culprit' => 'PaymentGateway::charge', 'file' => 'app/Support/PaymentGateway.php'],
            ['type' => 'Livewire\\Exceptions\\PropertyNotFoundException', 'message' => 'Property [$selectedIssueId] not found on component', 'culprit' => 'IssueActions::mount', 'file' => 'app/Livewire/IssueActions.php'],
            ['type' => 'Illuminate\\Queue\\MaxAttemptsExceededException', 'message' => 'App\\Jobs\\ProcessFaultEvent has been attempted too many times', 'culprit' => 'ProcessFaultEvent::handle', 'file' => 'app/Jobs/ProcessFaultEvent.php'],
        ],
        'symfony' => [
            ['type' => 'Symfony\\Component\\Routing\\Exception\\RouteNotFoundException', 'message' => 'Unable to generate a URL for the named route "invoice_show"', 'culprit' => 'InvoiceController::show', 'file' => 'src/Controller/InvoiceController.php'],
            ['type' => 'Doctrine\\DBAL\\Exception\\ConnectionException', 'message' => 'An exception occurred while establishing a connection', 'culprit' => 'DoctrineBootstrap::connect', 'file' => 'src/Kernel.php'],
        ],
        'wordpress' => [
            ['type' => 'Error', 'message' => 'Call to undefined function acf_get_field()', 'culprit' => 'theme_setup', 'file' => 'wp-content/themes/custom/functions.php'],
            ['type' => 'Error', 'message' => 'Maximum execution time of 30 seconds exceeded', 'culprit' => 'process_bulk_import', 'file' => 'wp-content/plugins/importer/import.php'],
        ],
        'nodejs' => [
            ['type' => 'TypeError', 'message' => "Cannot read properties of undefined (reading 'id')", 'culprit' => 'orderController.create', 'file' => 'src/controllers/orderController.js'],
            ['type' => 'MongoServerError', 'message' => 'E11000 duplicate key error collection', 'culprit' => 'UserModel.save', 'file' => 'src/models/User.js'],
        ],
        'other' => [
            ['type' => 'RuntimeException', 'message' => 'Unexpected end of input', 'culprit' => 'Parser::parse', 'file' => 'src/Parser.php'],
        ],
    ];

    /**
     * Sample log messages, seeded as issues with no exception (see the "log" item
     * type in Sentry's structured logs protocol, handled by IngestController::dispatchLogItems()
     * and rendered via FaultEvent::log_context).
     *
     * @var array<int, array{level: string, message: string, context: array<string, string|int>}>
     */
    protected const LOG_SAMPLES = [
        ['level' => 'info', 'message' => 'User logged in', 'context' => ['user_id' => 42, 'ip' => '203.0.113.7']],
        ['level' => 'info', 'message' => 'Order placed', 'context' => ['order_id' => 'ord_9f2a1c', 'total' => '129.00']],
        ['level' => 'warning', 'message' => 'Slow query detected', 'context' => ['duration_ms' => 1840, 'connection' => 'mysql']],
        ['level' => 'warning', 'message' => 'Cache miss rate above threshold', 'context' => ['store' => 'redis', 'miss_rate' => '0.42']],
        ['level' => 'error', 'message' => 'Failed to send notification email', 'context' => ['channel' => 'mail', 'recipient' => 'user@example.test']],
    ];

    protected const LEVELS = ['error', 'error', 'error', 'warning', 'fatal', 'info'];

    protected const STATUSES = ['unresolved', 'unresolved', 'unresolved', 'resolved', 'ignored'];

    /**
     * Runtime name/version pairs per platform, used to fill contexts.runtime
     * with something plausible for that platform.
     *
     * @var array<string, array<int, array{name: string, version: string}>>
     */
    protected const RUNTIMES = [
        'php' => [['name' => 'php', 'version' => '8.4.1'], ['name' => 'php', 'version' => '8.3.14']],
        'laravel' => [['name' => 'php', 'version' => '8.4.1'], ['name' => 'php', 'version' => '8.3.14']],
        'symfony' => [['name' => 'php', 'version' => '8.4.1'], ['name' => 'php', 'version' => '8.3.14']],
        'wordpress' => [['name' => 'php', 'version' => '8.2.20']],
        'nodejs' => [['name' => 'node', 'version' => '20.11.1'], ['name' => 'node', 'version' => '22.3.0']],
        'other' => [['name' => 'php', 'version' => '8.4.1']],
    ];

    /**
     * @var array<int, array{name: string, version: string, kernel_version?: string}>
     */
    protected const OPERATING_SYSTEMS = [
        ['name' => 'Linux', 'version' => '5.15.0-185-generic', 'kernel_version' => 'Linux 5.15.0-185-generic #195-Ubuntu SMP'],
        ['name' => 'Linux', 'version' => '6.8.0-45-generic', 'kernel_version' => 'Linux 6.8.0-45-generic #45-Ubuntu SMP'],
        ['name' => 'Darwin', 'version' => '23.5.0'],
    ];

    protected const USER_AGENTS = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
    ];

    /**
     * Canned markdown analyses so seeded issues that get an AI analysis at
     * least look like a real model wrote them.
     *
     * @var array<int, string>
     */
    protected const AI_ANALYSES = [
        "## Likely cause\nThis is most likely triggered by unvalidated input reaching a code path that assumes a well-formed value. The stack trace points to the failure happening before any guard clause runs.\n\n## Suggested fix\n- Validate the input at the boundary (request/job payload) before it reaches this code.\n- Add a defensive check with a clear exception message so future occurrences are easier to diagnose.",
        "## Likely cause\nThe error suggests a race condition or stale state: the code assumes a resource exists or is in a particular state, but that assumption doesn't hold under concurrent access or after an external change.\n\n## Suggested fix\n- Re-fetch or re-check the resource immediately before use.\n- Wrap the operation in a transaction or add optimistic locking if concurrent writes are expected.",
        "## Likely cause\nAn external dependency (database, HTTP service, or queue) appears to be unavailable or slow, and the calling code doesn't handle that failure mode gracefully.\n\n## Suggested fix\n- Add retries with backoff for transient failures.\n- Surface a user-friendly error instead of letting the exception bubble up unhandled.",
        "## Likely cause\nThis looks like a regression from a recent change to the surrounding logic — the failing line assumes a shape of data that no longer matches what's actually being passed in.\n\n## Suggested fix\n- Add a type check or default value at the start of the function.\n- Add a regression test covering this exact input shape.",
    ];

    protected const AI_DEEP_ANALYSES = [
        "### Root cause walkthrough\nTracing back through the stack, the failure originates a few frames up from where the exception is thrown. The immediate cause is a missing guard, but the deeper issue is that the calling code never guarantees the invariant this function relies on.\n\n### Recommended fix\n1. Enforce the invariant at the source (e.g., form request validation or a value object).\n2. Keep the defensive check here too, but turn it into a typed exception with context.\n3. Add a test that reproduces this exact trace.\n\n### Related risk\nOther call sites of this same method may share the same assumption — worth a quick audit.",
        "### Root cause walkthrough\nThis is a timing-dependent failure. Under normal load the resource is present by the time this code runs, but under load spikes or slow external calls the assumption breaks.\n\n### Recommended fix\n1. Make the dependent operation idempotent so retries are safe.\n2. Add explicit timeout and retry handling around the external call.\n3. Monitor the retry rate after deploying the fix to confirm it resolves the underlying contention.",
    ];

    /**
     * Seed a handful of realistic-looking issues (each with a few events) for
     * every existing project, so lists, filters, and the issue detail page
     * have something to render during local development.
     */
    public function run(): void
    {
        FaultProject::query()->with('organization.users')->each(function (FaultProject $project) {
            $members = $project->organization->users;
            $samples = self::SAMPLES[$project->platform->value] ?? self::SAMPLES['other'];

            collect(range(1, random_int(6, 12)))->each(function () use ($project, $samples, $members) {
                $sample = $samples[array_rand($samples)];
                $timesSeen = random_int(1, 480);
                $firstSeenAt = now()->subDays(random_int(1, 45))->subMinutes(random_int(0, 1440));
                $lastSeenAt = (clone $firstSeenAt)->addMinutes(random_int(0, max(1, $timesSeen)));

                $hasAnalysis = random_int(1, 100) <= 60;
                $hasDeepAnalysis = $hasAnalysis && random_int(1, 100) <= 40;

                $issue = FaultIssue::create([
                    'fault_project_id' => $project->id,
                    'fingerprint' => Str::random(20),
                    'type' => $sample['type'],
                    'title' => $sample['message'],
                    'culprit' => $sample['culprit'],
                    'level' => self::LEVELS[array_rand(self::LEVELS)],
                    'status' => self::STATUSES[array_rand(self::STATUSES)],
                    'times_seen' => $timesSeen,
                    'first_seen_at' => $firstSeenAt,
                    'last_seen_at' => $lastSeenAt,
                    'assigned_to_user_id' => random_int(0, 2) === 0 && $members->isNotEmpty()
                        ? $members->random()->id
                        : null,
                    'ai_analysis' => $hasAnalysis ? self::AI_ANALYSES[array_rand(self::AI_ANALYSES)] : null,
                    'ai_analyzed_at' => $hasAnalysis ? (clone $lastSeenAt)->addMinutes(random_int(1, 60)) : null,
                    'ai_deep_analysis' => $hasDeepAnalysis ? self::AI_DEEP_ANALYSES[array_rand(self::AI_DEEP_ANALYSES)] : null,
                    'ai_deep_analyzed_at' => $hasDeepAnalysis ? (clone $lastSeenAt)->addMinutes(random_int(61, 120)) : null,
                ]);

                collect(range(1, min(5, $timesSeen)))->each(function ($i) use ($project, $issue, $sample, $firstSeenAt) {
                    $user = $this->buildUserPayload();

                    FaultEvent::create([
                        'fault_project_id' => $project->id,
                        'fault_issue_id' => $issue->id,
                        'event_id' => (string) Str::uuid(),
                        'level' => $issue->level,
                        'message' => $sample['message'],
                        'culprit' => $sample['culprit'],
                        'environment' => 'production',
                        'server_name' => 'web-'.random_int(1, 4).'.prod.internal',
                        'exception' => ['values' => $values = $this->buildExceptionPayload($sample)],
                        'request' => $this->buildRequestPayload($project, $sample),
                        'contexts' => $this->buildContextsPayload($project),
                        'extra' => $this->buildExtraPayload(),
                        'payload' => ['message' => $sample['message'], 'exception' => ['values' => $values], 'user' => $user],
                        'occurred_at' => (clone $firstSeenAt)->addMinutes($i * random_int(5, 240)),
                    ]);
                });
            });

            $this->seedLogIssues($project, $members);
        });
    }

    /**
     * Seed a few log-derived issues (no exception, just a message and
     * structured context) so the show page's "Log context" panel has
     * something to render.
     */
    protected function seedLogIssues(FaultProject $project, Collection $members): void
    {
        collect(range(1, random_int(2, 4)))->each(function () use ($project, $members) {
            $sample = self::LOG_SAMPLES[array_rand(self::LOG_SAMPLES)];
            $timesSeen = random_int(1, 200);
            $firstSeenAt = now()->subDays(random_int(1, 45))->subMinutes(random_int(0, 1440));
            $lastSeenAt = (clone $firstSeenAt)->addMinutes(random_int(0, max(1, $timesSeen)));

            $issue = FaultIssue::create([
                'fault_project_id' => $project->id,
                'fingerprint' => Str::random(20),
                'type' => null,
                'title' => $sample['message'],
                'culprit' => null,
                'level' => $sample['level'],
                'status' => self::STATUSES[array_rand(self::STATUSES)],
                'times_seen' => $timesSeen,
                'first_seen_at' => $firstSeenAt,
                'last_seen_at' => $lastSeenAt,
                'assigned_to_user_id' => random_int(0, 2) === 0 && $members->isNotEmpty()
                    ? $members->random()->id
                    : null,
            ]);

            collect(range(1, min(5, $timesSeen)))->each(function ($i) use ($project, $issue, $sample, $firstSeenAt) {
                FaultEvent::create([
                    'fault_project_id' => $project->id,
                    'fault_issue_id' => $issue->id,
                    'event_id' => (string) Str::uuid(),
                    'level' => $issue->level,
                    'message' => $sample['message'],
                    'environment' => 'production',
                    'server_name' => 'web-'.random_int(1, 4).'.prod.internal',
                    'log_context' => $sample['context'],
                    'payload' => ['message' => $sample['message'], 'log_context' => $sample['context']],
                    'occurred_at' => (clone $firstSeenAt)->addMinutes($i * random_int(5, 240)),
                ]);
            });
        });
    }

    /**
     * @return array{id: string, email: string, username: string, ip_address: string}
     */
    protected function buildUserPayload(): array
    {
        $id = random_int(1, 5000);

        return [
            'id' => (string) $id,
            'email' => "user{$id}@example.com",
            'username' => 'user'.$id,
            'ip_address' => implode('.', [random_int(1, 255), random_int(0, 255), random_int(0, 255), random_int(1, 255)]),
        ];
    }

    /**
     * Builds a realistic set of request headers, occasionally including
     * sensitive ones (Authorization, Cookie) so seeded events exercise the
     * project's censorship settings the same way real traffic would.
     *
     * @return array<string, string>
     */
    protected function buildRequestHeaders(): array
    {
        $headers = [
            'User-Agent' => self::USER_AGENTS[array_rand(self::USER_AGENTS)],
            'Accept' => 'application/json',
            'Host' => 'example.test',
        ];

        if (random_int(1, 100) <= 50) {
            $headers['Authorization'] = 'Bearer '.Str::random(40);
        }

        if (random_int(1, 100) <= 50) {
            $headers['Cookie'] = 'zerrors_session='.Str::random(32);
        }

        return $headers;
    }

    /**
     * @param  array{type: string, message: string, culprit: string, file: string}  $sample
     * @return array{method: string, url: string, query_string?: string, headers: array<string, string>}
     */
    protected function buildRequestPayload(FaultProject $project, array $sample): array
    {
        if (Str::startsWith($sample['file'], 'app/Jobs/')) {
            return [];
        }

        if (Str::startsWith($sample['file'], 'app/Livewire/')) {
            $request = [
                'method' => 'POST',
                'url' => 'https://example.test/livewire/update',
                'headers' => $this->buildRequestHeaders(),
            ];

            return EventPayloadCensor::redact(['request' => $request], $project->censoredHeaders())['request'];
        }

        $method = Str::contains($sample['culprit'], ['store', 'create', 'charge', 'save']) ? 'POST' : 'GET';
        $path = '/'.Str::slug(Str::before($sample['culprit'], '@') ?: Str::before($sample['culprit'], '::'));

        $request = array_filter([
            'method' => $method,
            'url' => 'https://example.test'.$path,
            'query_string' => $method === 'GET' ? 'page='.random_int(1, 5) : null,
            'headers' => $this->buildRequestHeaders(),
        ], fn ($value) => $value !== null);

        return EventPayloadCensor::redact(['request' => $request], $project->censoredHeaders())['request'];
    }

    /**
     * @return array{os: array<string, string>, runtime: array<string, string>, trace: array{span_id: string, trace_id: string, status: string}}
     */
    protected function buildContextsPayload(FaultProject $project): array
    {
        $runtimes = self::RUNTIMES[$project->platform->value] ?? self::RUNTIMES['other'];

        return [
            'os' => self::OPERATING_SYSTEMS[array_rand(self::OPERATING_SYSTEMS)],
            'runtime' => $runtimes[array_rand($runtimes)],
            'trace' => [
                'span_id' => Str::random(16),
                'trace_id' => Str::random(32),
                'status' => random_int(1, 10) === 1 ? 'internal_error' : 'ok',
            ],
        ];
    }

    /**
     * @return array<string, string|int>
     */
    protected function buildExtraPayload(): array
    {
        return [
            'order_id' => 'ord_'.Str::random(8),
            'memory_usage_mb' => random_int(32, 256),
        ];
    }

    /**
     * @param  array{type: string, message: string, culprit: string, file: string}  $sample
     * @return array<int, array{type: string, value: string, mechanism: array{handled: bool}, stacktrace: array{frames: array<int, array<string, mixed>>}}>
     */
    protected function buildExceptionPayload(array $sample): array
    {
        $isLivewire = Str::startsWith($sample['file'], 'app/Livewire/');
        $isQueueJob = Str::startsWith($sample['file'], 'app/Jobs/');

        return [[
            'type' => $sample['type'],
            'value' => $sample['message'],
            'mechanism' => [
                'handled' => random_int(1, 100) <= 70,
            ],
            'stacktrace' => [
                'frames' => [
                    match (true) {
                        $isLivewire => [
                            'filename' => 'vendor/livewire/livewire/src/Mechanisms/HandleComponents/HandleComponents.php',
                            'lineno' => 214,
                            'function' => 'callMethod',
                            'in_app' => false,
                        ],
                        $isQueueJob => [
                            'filename' => 'vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php',
                            'lineno' => 124,
                            'function' => 'call',
                            'in_app' => false,
                        ],
                        default => [
                            'filename' => 'vendor/laravel/framework/src/Illuminate/Routing/Router.php',
                            'lineno' => 806,
                            'function' => 'dispatch',
                            'in_app' => false,
                        ],
                    },
                    [
                        'filename' => $sample['file'],
                        'lineno' => $lineNo = random_int(15, 220),
                        'function' => Str::afterLast($sample['culprit'], '::') ?: Str::afterLast($sample['culprit'], '.'),
                        'in_app' => true,
                        'context_line' => '    throw new '.class_basename($sample['type']).'("'.$sample['message'].'");',
                        'pre_context' => [
                            '    if (! $this->isValid($value)) {',
                        ],
                        'post_context' => [
                            '    }',
                        ],
                        'vars' => [
                            'value' => 'null',
                            'context' => '[]',
                        ],
                    ],
                ],
            ],
        ]];
    }
}
