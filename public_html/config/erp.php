<?php
return [
    // This installation is a single organization. This identifies its existing users,
    // not a claim that academic tables are safe for multi-tenant hosting.
    'business_id' => (int) env('ERP_BUSINESS_ID', 1),
];
