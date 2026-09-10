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
