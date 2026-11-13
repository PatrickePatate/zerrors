<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ingest Rate Limit
    |--------------------------------------------------------------------------
    |
    | Maximum number of ingest requests (envelope/store) accepted per minute
    | for a single project, enforced by the `fault-ingest` rate limiter.
    |
    */

    'ingest_rate_limit' => (int) env('FAULT_INGEST_RATE_LIMIT', 300),

    /*
    |--------------------------------------------------------------------------
    | Ingest Event Rate Limit
    |--------------------------------------------------------------------------
    |
    | Maximum number of events (an envelope may carry many) accepted per minute
    | for a single project. Exceeding it returns 429 with Retry-After.
    |
    */

    'ingest_events_rate_limit' => (int) env('FAULT_INGEST_EVENTS_RATE_LIMIT', 1000),

    /*
    |--------------------------------------------------------------------------
    | Ingest Payload Limits
    |--------------------------------------------------------------------------
    |
    | Maximum raw (possibly compressed) body size, maximum size once
    | decompressed, and maximum number of events dispatched per envelope
    | (extra items are dropped). Oversize bodies are rejected with 413.
    |
    */

    'max_body_bytes' => (int) env('FAULT_MAX_BODY_BYTES', 1024 * 1024),

    'max_decompressed_bytes' => (int) env('FAULT_MAX_DECOMPRESSED_BYTES', 10 * 1024 * 1024),

    'max_envelope_items' => (int) env('FAULT_MAX_ENVELOPE_ITEMS', 100),

    /*
    |--------------------------------------------------------------------------
    | Alert Cooldown
    |--------------------------------------------------------------------------
    |
    | Minimum number of seconds between two "every event" notifications for
    | the same issue on the same channel. New-issue, regression and threshold
    | notifications are never throttled. Set to 0 to disable the cooldown.
    |
    */

    'alert_cooldown_seconds' => (int) env('FAULT_ALERT_COOLDOWN_SECONDS', 60),

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    |
    | Maximum size in bytes of a single attachment and maximum number kept per
    | event. Attachments over either limit are dropped silently.
    |
    */

    'max_attachment_bytes' => (int) env('FAULT_MAX_ATTACHMENT_BYTES', 1024 * 1024),

    'max_attachments_per_event' => (int) env('FAULT_MAX_ATTACHMENTS_PER_EVENT', 5),

    /*
    |--------------------------------------------------------------------------
    | Cron Monitors
    |--------------------------------------------------------------------------
    |
    | Maximum number of cron monitors auto-created from check-ins per project,
    | and how many days of check-in history are kept.
    |
    */

    'max_cron_monitors_per_project' => (int) env('FAULT_MAX_CRON_MONITORS', 100),

    'cron_check_in_retention_days' => (int) env('FAULT_CRON_CHECK_IN_RETENTION_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | AI Triage
    |--------------------------------------------------------------------------
    |
    | Default AI SDK classification provider (typesafe, openrouter or openai),
    | optional model ID override sent as-is (blank uses the provider's own
    | default, Jev for TypeSafe and OpenRouter), and the maximum number of
    | automatic triages per organization per hour.
    |
    */

    'ai_triage_provider' => env('FAULT_AI_TRIAGE_PROVIDER', 'typesafe'),

    'ai_triage_model' => env('FAULT_AI_TRIAGE_MODEL'),

    'ai_triage_hourly_limit' => (int) env('FAULT_AI_TRIAGE_HOURLY_LIMIT', 60),

    /*
    |--------------------------------------------------------------------------
    | Source Maps
    |--------------------------------------------------------------------------
    |
    | Maximum size in bytes of an uploaded source map, maximum number kept per
    | release, and the maximum number of stack frames symbolicated per event.
    |
    */

    'max_source_map_bytes' => (int) env('FAULT_MAX_SOURCE_MAP_BYTES', 20 * 1024 * 1024),

    'max_source_maps_per_release' => (int) env('FAULT_MAX_SOURCE_MAPS_PER_RELEASE', 500),

    'symbolication_max_frames' => (int) env('FAULT_SYMBOLICATION_MAX_FRAMES', 100),

];
