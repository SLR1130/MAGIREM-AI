<?php

return [
    // Institution-specific values must be set by authorized deployment configuration.
    'timezone' => env('MINSUANSCORE_APPOINTMENT_TIMEZONE', config('app.timezone', 'Asia/Manila')),
    'session_duration_minutes' => (int) env('MINSUANSCORE_SESSION_DURATION_MINUTES', 60),
    'grace_period_minutes' => (int) env('MINSUANSCORE_SESSION_GRACE_MINUTES', 15),
];