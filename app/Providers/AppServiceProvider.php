<?php

namespace App\Providers;

use App\Http\Middleware\Utf8JsonEncodingMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        DB::statement("SET DATEFORMAT ymd");
        DB::statement("SET LANGUAGE English");
    }
}
