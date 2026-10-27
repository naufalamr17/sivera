<?php

namespace Mlp\SiveraReporter;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

class EventBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Throwable $exception): array
    {
        $request = ! app()->runningInConsole() ? request() : null;
        $userId = config('sivera.capture_user', true) ? Auth::id() : null;

        return [
            'event_id' => (string) Str::ulid(),
            'application' => config('sivera.app_id'),
            'environment' => config('sivera.environment', 'production'),
            'severity' => 'error',
            'exception' => [
                'class' => $exception::class,
                'message' => $this->clean($exception->getMessage()),
                'file' => $this->relativePath($exception->getFile()),
                'line' => $exception->getLine(),
            ],
            'request' => [
                'method' => $request?->method(),
                'route' => $request?->route()?->getName(),
                'path' => $request ? $this->clean('/'.$request->path()) : null,
            ],
            'user' => $userId === null ? null : ['id' => $userId],
            'stack_trace' => array_map(fn (array $frame): array => [
                'file' => $this->relativePath($frame['file'] ?? null),
                'line' => $frame['line'] ?? null,
                'function' => $frame['function'] ?? null,
                'class' => $frame['class'] ?? null,
            ], array_slice($exception->getTrace(), 0, 50)),
            'occurred_at' => now()->toIso8601String(),
        ];
    }

    private function relativePath(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $path = str_replace('\\', '/', $path);
        $base = rtrim(str_replace('\\', '/', base_path()), '/').'/';

        if (str_starts_with($path, $base)) {
            return substr($path, strlen($base));
        }

        if (preg_match('~(?:^|/)((?:app|vendor)/.+)$~', $path, $matches)) {
            return $matches[1];
        }

        return basename($path);
    }

    private function clean(string $value): string
    {
        $pattern = <<<'REGEX'
~(["']?[\w-]*(?:password|passwd|secret|token|authorization|cookie|session|bearer|api[_-]?key)[\w-]*["']?\s*[:=]\s*)(?:"(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'|[^,;\r\n}&]+)~i
REGEX;
        $value = preg_replace($pattern, '$1"[REDACTED]"', $value);
        $value = preg_replace('/\bBearer\s+[^\s"\'<> ,;}\]]+/i', '[REDACTED]', $value);

        return mb_substr($value, 0, 10000);
    }
}
