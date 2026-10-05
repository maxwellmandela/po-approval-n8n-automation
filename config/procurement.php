<?php

return [
    'approval_threshold' => env('PROCUREMENT_APPROVAL_THRESHOLD', 100000),
    'n8n_webhook_secret' => env('N8N_WEBHOOK_SECRET'),
    'n8n_base_url' => env('N8N_BASE_URL'),
    'request_statuses' => [
        'draft',
        'submitted',
        'under_review',
        'clarification_required',
        'resubmitted',
        'approved',
        'rejected',
        'po_created',
        'recorded',
    ],
    'approval_actions' => [
        'approved',
        'rejected',
        'clarification_requested',
    ],
];
