<?php

namespace Database\Seeders;

use App\Models\FaultEvent;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use Illuminate\Database\Seeder;
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

    protected const LEVELS = ['error', 'error', 'error', 'warning', 'fatal', 'info'];

    protected const STATUSES = ['unresolved', 'unresolved', 'unresolved', 'resolved', 'ignored'];

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

                collect(range(1, min(5, $timesSeen)))->each(fn ($i) => FaultEvent::create([
                    'fault_project_id' => $project->id,
                    'fault_issue_id' => $issue->id,
                    'event_id' => (string) Str::uuid(),
                    'level' => $issue->level,
                    'message' => $sample['message'],
                    'culprit' => $sample['culprit'],
                    'environment' => 'production',
                    'exception' => ['values' => $values = $this->buildExceptionPayload($sample)],
                    'payload' => ['message' => $sample['message'], 'exception' => ['values' => $values]],
                    'occurred_at' => (clone $firstSeenAt)->addMinutes($i * random_int(5, 240)),
                ]));
            });
        });
    }

    /**
     * @param  array{type: string, message: string, culprit: string, file: string}  $sample
     * @return array<int, array{type: string, value: string, stacktrace: array{frames: array<int, array<string, mixed>>}}>
     */
    protected function buildExceptionPayload(array $sample): array
    {
        return [[
            'type' => $sample['type'],
            'value' => $sample['message'],
            'stacktrace' => [
                'frames' => [
                    [
                        'filename' => 'vendor/laravel/framework/src/Illuminate/Routing/Router.php',
                        'lineno' => 806,
                        'function' => 'dispatch',
                        'in_app' => false,
                    ],
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
