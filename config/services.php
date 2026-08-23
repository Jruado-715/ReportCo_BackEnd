<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // The Python/FastAPI microservice hosting the SVM classifier and
    // K-Means clustering. See MlClassifierService.
    'ml_classifier' => [
        'base_url' => env('ML_CLASSIFIER_URL', 'http://127.0.0.1:8001'),
        'timeout' => env('ML_CLASSIFIER_TIMEOUT', 5),
    ],

    // The single water-level sensor and its Emergency Override behavior.
    // See IotReadingController and TriggerEmergencyOverride.
    'lgu' => [
        'endpoint' => env('LGU_ESCALATION_ENDPOINT'),
        'timeout' => env('LGU_ESCALATION_TIMEOUT', 10),
    ],

    'fcm' => [
        'project_id' => env('FCM_PROJECT_ID'),
        'client_email' => env('FCM_CLIENT_EMAIL'),
        'private_key' => env('FCM_PRIVATE_KEY'),
    ],

    'flood_sensor' => [
        'device_key' => env('FLOOD_SENSOR_DEVICE_KEY'),
        'threshold_cm' => env('FLOOD_SENSOR_THRESHOLD_CM', 150),
        // Seed a "ReportCo System" user and put its id here — automated
        // alerts need a sender, but shouldn't be attributed to a human admin.
        'system_user_id' => env('FLOOD_SENSOR_SYSTEM_USER_ID'),
        // Leave null for barangay-wide alerts; set to a purok id if the
        // sensor's flood-prone point only affects one purok.
        'purok_id' => env('FLOOD_SENSOR_PUROK_ID'),
    ],

    // SLA Resolution Tracker: how many hours a report can go without any
    // update before it's flagged "stale", keyed by ReportPriority value.
    // An untouched emergency report is a much bigger problem than an
    // untouched low-priority one, so each tier gets its own window.
    'sla' => [
        'stale_after_hours' => [
            'emergency' => (int) env('SLA_STALE_HOURS_EMERGENCY', 2),
            'high' => (int) env('SLA_STALE_HOURS_HIGH', 24),
            'medium' => (int) env('SLA_STALE_HOURS_MEDIUM', 72),
            'low' => (int) env('SLA_STALE_HOURS_LOW', 168),
            'unclassified' => (int) env('SLA_STALE_HOURS_UNCLASSIFIED', 72),
        ],
        // Don't re-notify admins about the same stale report more often
        // than this, even if the scheduled check runs hourly.
        'renotify_after_hours' => (int) env('SLA_RENOTIFY_AFTER_HOURS', 24),
    ],

];
