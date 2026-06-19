<?php

/**
 * Subscription plans and their limits. Single source of truth — tune freely.
 *  - null limit = unlimited.
 *  - max_active_loans: loans not deleted and not completed, across the tenant's books.
 *  - max_users: total active users in the tenant (the owner counts as one).
 *  - max_books: books in the tenant.
 *
 * 'trial' is the plan a self-signup starts on (status=trial). Paid tiers are
 * assigned on subscribe (Phase 4) or manually by the platform admin for now.
 */
return [
    'trial' => [
        'label' => 'Free Trial',
        'max_active_loans' => 25,
        'max_users' => 2,
        'max_books' => 1,
    ],
    'basic' => [
        'label' => 'Basic',
        'max_active_loans' => 150,
        'max_users' => 3,
        'max_books' => null,
    ],
    'standard' => [
        'label' => 'Standard',
        'max_active_loans' => 600,
        'max_users' => 8,
        'max_books' => null,
    ],
    'premium' => [
        'label' => 'Premium',
        'max_active_loans' => 2500,
        'max_users' => 25,
        'max_books' => null,
    ],
    'enterprise' => [
        'label' => 'Enterprise',
        'max_active_loans' => null,
        'max_users' => null,
        'max_books' => null,
    ],
];
