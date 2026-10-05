<?php

namespace Mlp\SiveraReporter;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Mlp\SiveraReporter\Jobs\SendEventJob;
use Throwable;

class Reporter
{
    public function __construct(private EventBuilder $events) {}

    public function report(Throwable $exception): void
    {
        try {
            $event = $this->events->build($exception);
            $this->send($event);
        } catch (Throwable) {
            // Monitoring must never alter the target application's error handling.
        }
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public function send(array $event): bool
    {
        try {
            if (! config('sivera.enabled')) {
                return false;
            }

            if (config('sivera.transport') === 'queue') {
                SendEventJob::dispatch($event);

                return true;
            }

            return $this->sendNow($event);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public function sendNow(array $event): bool
    {
        try {
            $url = config('sivera.url');
            $appId = config('sivera.app_id');
            $clientId = config('sivera.client_id');
            $secret = config('sivera.secret');

            if (! config('sivera.enabled') || ! is_string($url) || ! str_starts_with($url, 'https://')
                || ! is_string($appId) || ! is_string($clientId) || ! is_string($secret)
                || $appId === '' || $clientId === '' || $secret === '') {
                return false;
            }

            $body = json_encode($event, JSON_THROW_ON_ERROR);
            $timestamp = (string) time();
            $nonce = (string) Str::uuid();
            $path = '/api/v1/events';
            $canonical = implode("\n", ['POST', $path, $timestamp, $nonce, hash('sha256', $body)]);
            $signature = 'sha256='.hash_hmac('sha256', $canonical, $secret);
            $response = Http::connectTimeout((float) config('sivera.connect_timeout', 0.5))
                ->timeout((float) config('sivera.timeout', 2))
                ->withHeaders([
                    'X-SIVERA-App-ID' => $appId,
                    'X-SIVERA-Client-ID' => $clientId,
                    'X-SIVERA-Timestamp' => $timestamp,
                    'X-SIVERA-Nonce' => $nonce,
                    'X-SIVERA-Signature' => $signature,
                ])
                ->withBody($body, 'application/json')
                ->post(rtrim($url, '/').$path);

            return $response->status() === 202;
        } catch (Throwable) {
            return false;
        }
    }
}
