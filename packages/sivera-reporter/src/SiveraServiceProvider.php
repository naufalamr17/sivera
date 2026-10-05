<?php

namespace Mlp\SiveraReporter;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\ServiceProvider;
use Mlp\SiveraReporter\Console\InstallCommand;
use Mlp\SiveraReporter\Console\TestConnectionCommand;
use Throwable;

class SiveraServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sivera.php', 'sivera');
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/sivera.php' => config_path('sivera.php')], 'sivera-config');

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class, TestConnectionCommand::class]);
        }

        $handler = $this->app->make(ExceptionHandler::class);

        if (method_exists($handler, 'reportable')) {
            $handler->reportable(function (Throwable $exception): void {
                $this->app->make(Reporter::class)->report($exception);
            });
        }
    }
}
