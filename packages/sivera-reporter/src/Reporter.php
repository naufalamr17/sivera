<?php

namespace Mlp\SiveraReporter;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Mlp\SiveraReporter\Jobs\SendEventJob;
use Throwable;

class Reporter
{
    private ?string $lastError = null;

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
        $this->lastError = null;

        try {
            $url = config('sivera.url');
            $appId = config('sivera.app_id');
            $clientId = config('sivera.client_id');
            $secret = config('sivera.secret');

            if (! config('sivera.enabled')) {
                return $this->fail('Reporting is disabled (SIVERA_ENABLED=false).');
            }

            if (! is_string($url) || ! str_starts_with($url, 'https://')) {
                return $this->fail('SIVERA_URL must be an HTTPS base URL without /api/v1/events.');
            }

            foreach (['SIVERA_APP_ID' => $appId, 'SIVERA_CLIENT_ID' => $clientId, 'SIVERA_SECRET' => $secret] as $name => $value) {
                if (! is_string($value) || $value === '') {
                    return $this->fail($name.' is missing. Check the application configuration.');
                }
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

            $status = $response->status();

            if ($status === 202) {
                return true;
            }

            return $this->fail('HTTP '.$status.': '.match (true) {
                $status === 401 => 'Authentication rejected. Check the app ID, client ID, secret, active credential, IP allowlist, server clock, and HTTPS/proxy settings.',
                $status === 403 => 'Access forbidden. Check firewall/WAF rules and server access restrictions.',
                $status === 404 => 'Ingestion endpoint not found. SIVERA_URL must be the server base URL without /api/v1/events.',
                $status === 413 => 'Event payload exceeds the 256 KiB limit.',
                $status === 422 => 'Payload validation failed. Check SIVERA_APP_ID and SIVERA_ENVIRONMENT against the application registered in SIVERA.',
                $status === 429 => 'Rate limit exceeded. Wait before retrying.',
                $status >= 500 => 'SIVERA server or gateway error. Check the SIVERA server logs.',
                default => 'Unexpected response; event delivery requires HTTP 202. Check the endpoint and any redirects.',
            });
        } catch (ConnectionException $exception) {
            preg_match('/cURL error (\d+)/', $exception->getMessage(), $matches);
            $code = isset($matches[1]) ? (int) $matches[1] : null;

            return $this->fail(($code === null ? 'Connection failed: ' : 'cURL error '.$code.': ').match ($code) {
                6 => 'Could not resolve the SIVERA hostname. Check DNS on this server.',
                7 => 'Could not connect to SIVERA. Check outbound access, port 443, and server availability.',
                28 => 'Request timed out. Check connectivity, SIVERA_CONNECT_TIMEOUT, and SIVERA_TIMEOUT.',
                35 => 'TLS handshake failed. Check the server TLS configuration and PHP/cURL support.',
                60 => 'TLS certificate verification failed. Check the certificate chain and PHP/cURL CA configuration.',
                default => 'No HTTP response received. Check DNS, TLS, and outbound network access.',
            });
        } catch (Throwable $exception) {
            return $this->fail('Could not send event ('.$exception::class.'). Check event encoding and reporter configuration.');
        }
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    private function fail(string $message): bool
    {
        $this->lastError = $message;

        return false;
    }
}
