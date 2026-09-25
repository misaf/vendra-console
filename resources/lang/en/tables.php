<?php

declare(strict_types=1);

return [
    'description' => [
        'activity_logs' => 'Read what happened across every store on the platform.',
        'invoices' => 'Every invoice issued for a reseller plan charge.',
        'plans' => 'Manage the subscription plans available to resellers.',
        'resellers' => 'Manage reseller accounts and their store capacity.',
        'stores' => 'Manage stores, domains, and access status.',
        'storefront_images' => 'Manage the approved storefront image catalog.',
        'storefront_deployments' => 'Inspect deployment history, failures, runtime state, and recovery actions.',
    ],
    'empty_state' => [
        'heading' => [
            'activity_logs' => 'No activity',
            'invoices' => 'No invoices',
            'plans' => 'No plans',
            'resellers' => 'No resellers',
            'stores' => 'No stores',
            'storefront_images' => 'No storefront images',
            'storefront_deployments' => 'No storefront deployments',
        ],
        'description' => [
            'activity_logs' => 'Activity appears here as administrators and stores make changes.',
            'invoices' => 'Invoices are issued automatically when a reseller pays for a plan.',
            'plans' => 'Create a plan to define subscription limits and billing periods.',
            'resellers' => 'Create a reseller account to start assigning stores.',
            'stores' => 'Create a store to configure its domain and access.',
            'storefront_images' => 'Add an approved image before creating a storefront.',
            'storefront_deployments' => 'Deployment requests will appear here when storefronts are provisioned.',
        ],
    ],
];
