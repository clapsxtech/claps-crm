<?php

return [
    // Optional comma-separated IPs/CIDRs. Wildcards and catch-all CIDRs are rejected.
    // Leave empty for the Cloudflare container path: canonical origin handling
    // below does not trust any request-supplied forwarding headers.
    'trusted_proxies' => env('TRUSTED_PROXIES', ''),

    // Explicit HTTPS deployment origin, injected by the Worker from APP_URL.
    // Applied only when the actual HTTP Host exactly matches this origin.
    'public_origin' => env('PUBLIC_ORIGIN', ''),
];
