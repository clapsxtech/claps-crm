<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->resolving('db', function ($manager) {
            $manager->extend('d1', function ($config, $name) {
                $gateway = new \App\Infrastructure\Cloudflare\Gateway($config['gateway'], $config['token']);
                $pdo = new \App\Infrastructure\Cloudflare\D1Pdo($gateway, $config['database']);
                $config['driver'] = 'sqlite';
                $config['name'] = $name;
                return new \App\Infrastructure\Cloudflare\D1Connection($pdo, $config['database'], '', $config);
            });
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
