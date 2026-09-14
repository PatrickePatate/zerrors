/**
 * k6 load test for the Fault issue-ingestion endpoint.
 *
 * Sends Sentry-style envelopes to POST /api/{projectId}/envelope/, mirroring
 * the shape asserted in tests/Feature/Fault/IngestTest.php. Ingestion is
 * queued (ProcessFaultEvent dispatched on the `fault-ingest` queue), so this
 * measures HTTP accept throughput; pair it with `php artisan queue:monitor
 * fault-ingest` (or the Horizon dashboard) to see whether workers keep up.
 *
 * Setup:
 *   1. Create a project to test against (or reuse one), and disable/raise
 *      the `fault-ingest` rate limiter for the duration of the run, e.g. in
 *      app/Providers/AppServiceProvider.php bump 300/min or key it per-IP
 *      only for this test, since ingest is throttled per project id.
 *   2. Export BASE_URL, PROJECT_ID, PUBLIC_KEY (see env vars below).
 *
 * Usage:
 *   k6 run \
 *     -e BASE_URL=http://zerrors.test \
 *     -e PROJECT_ID=1 \
 *     -e PUBLIC_KEY=abc123 \
 *     tests/load/k6-ingest.js
 *
 * Tune load shape via VUS / DURATION, or edit the `scenarios` block below
 * for ramping/stages instead of a flat rate.
 */

import http from 'k6/http';
import { check } from 'k6';
import { Counter, Trend } from 'k6/metrics';
import { uuidv4 } from 'https://jslib.k6.io/k6-utils/1.4.0/index.js';

const BASE_URL = __ENV.BASE_URL || 'http://localhost';
const PROJECT_ID = __ENV.PROJECT_ID || '1';
const PUBLIC_KEY = __ENV.PUBLIC_KEY;
const VUS = Number(__ENV.VUS || 20);
const DURATION = __ENV.DURATION || '30s';
const RATE = Number(__ENV.RATE || 200); // requests/sec target for the constant-arrival-rate scenario

if (!PUBLIC_KEY) {
  throw new Error('PUBLIC_KEY env var is required (the project public_key / DSN key)');
}

const acceptedEvents = new Counter('accepted_events');
const ingestDuration = new Trend('ingest_duration', true);

export const options = {
  scenarios: {
    ingest_load: {
      executor: 'constant-arrival-rate',
      rate: RATE,
      timeUnit: '1s',
      duration: DURATION,
      preAllocatedVUs: VUS,
      maxVUs: VUS * 4,
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<500'],
  },
};

const EXCEPTION_TYPES = ['RuntimeException', 'LogicException', 'TypeError', 'ValueError', 'OutOfRangeException'];

function randomEventId() {
  return uuidv4().replace(/-/g, '');
}

// One "event" envelope item, matching what sentry-php / sentry-laravel sends
// and what IngestController parses (event_id, level, exception.values[]).
function buildEnvelope() {
  const eventId = randomEventId();
  const exceptionType = EXCEPTION_TYPES[Math.floor(Math.random() * EXCEPTION_TYPES.length)];

  const envelopeHeader = JSON.stringify({ event_id: eventId });
  const itemHeader = JSON.stringify({ type: 'event' });
  const itemPayload = JSON.stringify({
    event_id: eventId,
    level: 'error',
    timestamp: Math.floor(Date.now() / 1000),
    exception: {
      values: [
        {
          type: exceptionType,
          value: `Synthetic load-test failure ${eventId}`,
          stacktrace: {
            frames: [
              { filename: 'app/Services/LoadTestService.php', function: 'run', lineno: 42 },
              { filename: 'app/Http/Controllers/LoadTestController.php', function: 'index', lineno: 17 },
            ],
          },
        },
      ],
    },
    request: {
      url: 'https://example.test/load-test',
      method: 'GET',
      headers: { 'user-agent': 'k6-load-test' },
    },
  });

  return `${envelopeHeader}\n${itemHeader}\n${itemPayload}\n`;
}

export default function () {
  const url = `${BASE_URL}/api/${PROJECT_ID}/envelope/`;
  const body = buildEnvelope();

  const res = http.post(url, body, {
    headers: {
      'Content-Type': 'application/x-sentry-envelope',
      'X-Sentry-Auth': `Sentry sentry_version=7, sentry_key=${PUBLIC_KEY}`,
    },
    tags: { name: 'envelope_ingest' },
  });

  ingestDuration.add(res.timings.duration);

  const ok = check(res, {
    'status is 200': (r) => r.status === 200,
    'response has id': (r) => {
      try {
        return typeof JSON.parse(r.body).id === 'string';
      } catch (e) {
        return false;
      }
    },
  });

  if (ok) {
    acceptedEvents.add(1);
  }
}
