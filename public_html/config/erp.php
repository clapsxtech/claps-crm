<?php
return [
    // This installation is a single organization. This identifies its existing users,
    // not a claim that academic tables are safe for multi-tenant hosting.
    // Confirmed policy: only an explicit admin import posts attended student charges; teacher accrual requires separate administrator approval.
    'payroll_policy' => env('ERP_PAYROLL_POLICY', 'approved_attended_only'),
    'finance_policy' => env('ERP_FINANCE_POLICY', 'admin_import_delivered_only'),
    'business_id' => (int) env('ERP_BUSINESS_ID', 1),
];

