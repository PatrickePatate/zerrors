<p align="center">
  <img src="public/images/logo.png" alt="Zerrors" width="320">
</p>

<p align="center">
  Self-hosted error tracking for your applications — Sentry-compatible ingestion, AI-assisted issue analysis, and multi-organization workspaces, built on Laravel.
</p>

## What is Zerrors?

Zerrors collects exceptions and error events from your applications (via the Sentry SDK/DSN protocol) and gives your team a place to triage, discuss, and resolve them.

- **Organizations & projects** — group projects under organizations, invite teammates, and manage roles/members.
- **Sentry-compatible ingestion** — point any existing Sentry SDK at a Zerrors project DSN (`/api/{projectId}/envelope` and `/api/{projectId}/store`) with no code changes.
- **Issue tracking** — events are grouped into issues, with occurrence counts, releases, environments, and regression detection.
- **AI-assisted analysis** — ask for an AI summary or a deeper investigation of an issue directly from the UI.
- **Notifications** — per-project alert channels (email, Slack, Telegram) with configurable rules: new issue, regression, every event, or occurrence thresholds.
- **GitHub integration** — create GitHub issues from a Zerrors issue, and forward events to Sentry as a second DSN if you want to keep both.
- **Security** — two-factor authentication, audit logging, and a REST API for programmatic access.

## Tech stack

- [Laravel](https://laravel.com) 13 (PHP 8.4)
- [Livewire](https://livewire.laravel.com) for interactive UI
- [Laravel Horizon](https://laravel.com/docs/horizon) for queue processing of incoming events
- [Laravel AI](https://laravel.com/docs/ai) for issue analysis
- [Sentry SDK](https://docs.sentry.io/platforms/php/guides/laravel/) compatibility for ingestion
- Tailwind CSS + Alpine.js on the frontend

## Getting started

### Requirements

- PHP 8.4+
- Composer
- Node.js & npm
- A database supported by Laravel (MySQL/PostgreSQL/SQLite)
- Redis (recommended, used by Horizon/queues)

### Installation

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

php artisan migrate

npm run build
```

### Running locally

```bash
composer run dev
```

This starts the app server, queue worker, and Vite dev server together. Visit the app and register the first account to create your organization.

### Running in production with Octane

Zerrors ships with [Laravel Octane](https://laravel.com/docs/octane) for high-performance production serving. Octane keeps the framework booted in memory between requests instead of bootstrapping Laravel from scratch on every request.

1. Install an Octane server binary. The default is [RoadRunner](https://roadrunner.dev):
   ```bash
   php artisan octane:install --server=roadrunner
   ```
   [FrankenPHP](https://frankenphp.dev) and [Swoole](https://www.swoole.co.uk) are also supported — pass `--server=frankenphp` or `--server=swoole` instead.
2. Set the server in `.env`:
   ```
   OCTANE_SERVER=roadrunner
   ```
3. Start the server:
   ```bash
   php artisan octane:start
   ```
4. Keep Horizon running separately for queue processing (`php artisan horizon`), since Octane only serves HTTP requests.

A few things to watch for when running under Octane, since the application stays in memory across requests:

- Avoid storing per-request state in static properties, singletons, or globally bound container instances — it will leak between requests.
- Use [Octane's `Octane::state()` / listeners](https://laravel.com/docs/octane#managing-memory-leaks) or the built-in listeners (already wired in `config/octane.php`) to reset things like the database connection between requests if you add stateful services.
- Restart workers after deploying new code (`php artisan octane:reload`) — Octane won't pick up code changes on its own since the app stays booted.

### Sending errors to Zerrors

Once you've created a project, use its DSN with any [Sentry SDK](https://docs.sentry.io/platforms/) exactly as you would with Sentry — Zerrors implements the same ingestion endpoints.

## Setting up the GitHub App

Zerrors uses a [GitHub App](https://docs.github.com/en/apps) (not a personal access token) to create issues in your repos and to receive push events for releases. Each organization installs the app once, then picks a repo per project.

1. Go to **github.com/settings/apps/new** (or your GitHub org's equivalent) and create a new app with:
   - **Homepage URL**: your Zerrors instance URL.
   - **Callback URL** (under "Identifying and authorizing users") and **Setup URL** (under "Post installation"): both set to `https://your-domain.com/integrations/github/callback`.
   - Check **"Redirect on update"** under "Post installation" — this makes GitHub re-fire the Setup URL whenever the installation changes (e.g. repos added/removed), not just on first install.
   - Leave **"Request user authorization (OAuth) during installation"** unchecked — Zerrors authenticates as the app (via a private key), not as the installing user, so no user OAuth exchange is needed.
   - **Webhook URL**: `https://your-domain.com/api/webhooks/github`, with a webhook secret you generate yourself (keep it, you'll need it below).
   - **Webhook events**: subscribe to `push`.
   - **Repository permissions**: `Contents: Read-only`, `Issues: Read and write`.
   - **Where can this GitHub App be installed?**: "Any account" (or "Only on this account" if it's just for you).
2. After creating the app, note its **App ID**, generate a **Client secret**, and generate a **private key** (downloads a `.pem` file). Also note the app's **slug** (the URL-friendly name shown in its settings URL).
3. Add these to your `.env`:
   ```
   GITHUB_APP_ID=123456
   GITHUB_APP_SLUG=your-app-slug
   GITHUB_APP_CLIENT_ID=Iv1.xxxxxxxxxxxx
   GITHUB_APP_CLIENT_SECRET=xxxxxxxxxxxx
   GITHUB_APP_WEBHOOK_SECRET=xxxxxxxxxxxx
   GITHUB_APP_PRIVATE_KEY="-----BEGIN RSA PRIVATE KEY-----\nMII...\n-----END RSA PRIVATE KEY-----\n"
   ```
   For `GITHUB_APP_PRIVATE_KEY`, paste the `.pem` contents on one line with `\n` in place of real newlines.
4. In Zerrors, go to an organization's **Settings → Integrations** and click **Connect GitHub** to install the app on your account/org and authorize it.
5. In each project's settings, set the **GitHub repo** (`owner/repo`) you want issues and releases linked to.

## Setting up the Slack App

Zerrors uses a [Slack App](https://api.slack.com/apps) with OAuth so alerts post as a bot into channels you pick, instead of pasting an incoming webhook URL per channel.

1. Go to **api.slack.com/apps** and create a new app ("From scratch") in your workspace.
2. Under **OAuth & Permissions**:
   - Add a **Redirect URL**: `https://your-domain.com/integrations/slack/callback`.
   - Add **Bot Token Scopes**: `chat:write`, `channels:read`, `groups:read`.
3. Under **Basic Information**, note the **Client ID**, **Client Secret**, and **Signing Secret**.
4. Add these to your `.env`:
   ```
   SLACK_APP_CLIENT_ID=xxxxxxxxxxxx
   SLACK_APP_CLIENT_SECRET=xxxxxxxxxxxx
   SLACK_APP_SIGNING_SECRET=xxxxxxxxxxxx
   SLACK_APP_SCOPES=chat:write,channels:read,groups:read
   ```
5. In Zerrors, go to an organization's **Settings → Integrations** and click **Connect Slack** to authorize the app for your workspace.
6. In a project's notification channels, add a Slack channel — it's now picked from a list fetched from your workspace instead of a webhook URL.

## Benchmarking ingestion throughput

A [k6](https://k6.io) load test lives at [`tests/load/k6-ingest.js`](tests/load/k6-ingest.js), sending synthetic Sentry envelopes to `POST /api/{projectId}/envelope/` at a fixed rate:

```bash
k6 run \
  -e BASE_URL=https://your-instance.example \
  -e PROJECT_ID=1 \
  -e PUBLIC_KEY=your-project-public-key \
  -e RATE=500 \
  -e DURATION=60s \
  tests/load/k6-ingest.js
```

Ingestion is cheap on the request path — it just validates the DSN and pushes onto the `fault-ingest` Redis queue. The real ceiling is how fast Horizon's `supervisor-ingest` workers drain that queue, so watch `php artisan queue:monitor fault-ingest` alongside a run to catch a growing backlog.

Two `.env` keys tune this without editing code: `FAULT_INGEST_RATE_LIMIT` (requests/minute per project, default `300`, see `config/fault.php`) and `FAULT_INGEST_MAX_PROCESSES` (Horizon's `supervisor-ingest` worker cap in production, default `30`, see `config/horizon.php`). Restart Octane/Horizon after changing either — running workers don't pick up `.env` changes on their own.

Don't size `FAULT_INGEST_MAX_PROCESSES` past your CPU core count: in testing, 30 workers against PostgreSQL on a single shared machine performed *worse* than 4 (HTTP latency rose from ~25ms to ~425ms) because workers were competing with Octane for CPU, not waiting on the database. With workers near the core count, throughput was roughly 15 jobs/sec/worker against PostgreSQL — re-measure this on your own hardware.

Using that baseline, here's a starting point for `FAULT_INGEST_MAX_PROCESSES` at various sustained rates (raise `FAULT_INGEST_RATE_LIMIT` too, since it caps a single project at 300 req/min by default):

| Target sustained rate | `FAULT_INGEST_MAX_PROCESSES` | Notes |
|---|---|---|
| 50 RPS | 6 | Well within the default cap. |
| 100 RPS | 10 | Still within the default cap. |
| 300 RPS | 30 | The current default — verify against your core count. |
| 500 RPS | 50 | Above default; raise the rate limit too. |
| 1000 RPS | 100 | Likely needs dedicated queue-worker hosts and a database sized for the write volume. |

These are starting points, not guarantees — verify with the k6 script against hardware that mirrors production.

## Testing

```bash
php artisan test
```

## Code style

This project uses [Laravel Pint](https://laravel.com/docs/pint):

```bash
vendor/bin/pint
```

## Contributing

Contributions are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md) for how to get set up and submit changes. Please also read our [Code of Conduct](CODE_OF_CONDUCT.md).

## Security

If you discover a security vulnerability, please follow the responsible disclosure process described in [SECURITY.md](SECURITY.md) instead of opening a public issue.

## License

Zerrors is licensed under the [PolyForm Noncommercial License 1.0.0](LICENSE). You're free to use, modify, and self-host it for any noncommercial purpose. Commercial use (including offering Zerrors, or a service built on it, to third parties for a fee) requires a separate commercial license — get in touch if that's you.
