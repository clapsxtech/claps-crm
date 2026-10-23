<?php

namespace App\Modules\Integrations\Providers;

use App\Modules\Integrations\Contracts\SheetsTransport;
use App\Modules\Integrations\Transports\GoogleSheetsTransport;
use Illuminate\Support\ServiceProvider;

class IntegrationServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(SheetsTransport::class, GoogleSheetsTransport::class);
    }
}
