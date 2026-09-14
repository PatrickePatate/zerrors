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

];
