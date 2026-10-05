<?php

namespace App\Http\Middleware;

use App\Models\ApiRequestLog;
use App\Models\ApplicationCredential;
use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class VerifySiveraSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $applicationId = null;
        $signatureValid = false;
        $status = 500;
        $body = $request->getContent();
        $clientId = $request->header('X-SIVERA-Client-ID');

        try {
            if (strlen($body) > 262144) {
                return $this->error($status, 413, 'Payload too large');
            }

            if (app()->isProduction() && ! $request->isSecure()) {
                return $this->error($status, 401, 'Unauthorized');
            }

            $appSlug = $request->header('X-SIVERA-App-ID');
            $timestamp = $request->header('X-SIVERA-Timestamp');
            $nonce = $request->header('X-SIVERA-Nonce');
            $signature = $request->header('X-SIVERA-Signature');

            if (! is_string($appSlug) || ! is_string($clientId) || ! is_string($timestamp)
                || ! is_string($nonce) || ! is_string($signature)
                || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $appSlug)
                || ! preg_match('/^cli_[a-z0-9]{24}$/', $clientId)
                || ! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300
                || ! preg_match('/^[A-Za-z0-9-]{8,100}$/', $nonce)
                || ! preg_match('/^sha256=[a-f0-9]{64}$/', $signature)) {
                return $this->error($status, 401, 'Unauthorized');
            }

            $credential = ApplicationCredential::query()
                ->with('application')
                ->where('client_id', $clientId)
                ->where('is_active', true)
                ->first();
            $application = $credential?->application;
            $applicationId = $application?->id;

            if (! $application || ! $application->is_active || $application->slug !== $appSlug
                || $credential->valid_from->isFuture()
                || ($credential->expires_at && $credential->expires_at->isPast())
                || ($application->allowed_ips && ! in_array($request->ip(), $application->allowed_ips, true))) {
                return $this->error($status, 401, 'Unauthorized');
            }

            $canonical = implode("\n", [
                $request->method(),
                $request->getPathInfo(),
                $timestamp,
                $nonce,
                hash('sha256', $body),
            ]);
            try {
                $expected = 'sha256='.hash_hmac('sha256', $canonical, $credential->encrypted_secret);
            } catch (DecryptException) {
                return $this->error($status, 401, 'Unauthorized');
            }

            if (! hash_equals($expected, $signature)) {
                return $this->error($status, 401, 'Unauthorized');
            }

            $signatureValid = true;
            $rateKey = 'sivera:rate:'.$credential->id;

            if (RateLimiter::tooManyAttempts($rateKey, $application->rate_limit_per_minute)) {
                return $this->error($status, 429, 'Too many requests', ['Retry-After' => (string) RateLimiter::availableIn($rateKey)]);
            }

            RateLimiter::hit($rateKey, 60);

            if (! Cache::add('sivera:nonce:'.$credential->id.':'.$nonce, true, 600)) {
                return $this->error($status, 401, 'Unauthorized');
            }

            $credential->forceFill(['last_used_at' => now(), 'last_ip' => $request->ip()])->save();
            $request->attributes->set('sivera_application', $application);
            $response = $next($request);
            $status = $response->getStatusCode();

            return $response;
        } catch (HttpResponseException $exception) {
            $status = $exception->getResponse()->getStatusCode();

            throw $exception;
        } finally {
            ApiRequestLog::create([
                'application_id' => $applicationId,
                'client_id' => is_string($clientId) ? substr($clientId, 0, 255) : null,
                'source_ip' => $request->ip(),
                'http_status' => $status,
                'signature_valid' => $signatureValid,
                'request_size' => strlen($body),
            ]);
        }
    }

    private function error(int &$status, int $code, string $message, array $headers = []): Response
    {
        $status = $code;

        return response()->json(['error' => $message], $code, $headers);
    }
}
