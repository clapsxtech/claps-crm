<?php
return [
    // This installation is a single organization. This identifies its existing users,
    // not a claim that academic tables are safe for multi-tenant hosting.
    // Confirmed policy: only an explicit admin import posts attended student charges; teacher accrual requires separate administrator approval.
    'migration_import_enabled' => (bool) env('ERP_MIGRATION_IMPORT_ENABLED', false),
    'payroll_policy' => env('ERP_PAYROLL_POLICY', 'approved_attended_only'),
    'finance_policy' => env('ERP_FINANCE_POLICY', 'admin_import_delivered_only'),
    'parent_review_required' => (bool) env('ERP_PARENT_REVIEW_REQUIRED', true),
    'business_id' => (int) env('ERP_BUSINESS_ID', 1),
];

