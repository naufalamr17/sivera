<?php

namespace App\Services\Sivera;

use App\Models\Application;
use App\Models\EventReceipt;
use App\Models\Issue;
use App\Models\IssueOccurrence;
use App\Models\IssueStatusHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventIngestionService
{
    public function __construct(private NotificationService $notifications) {}

    /**
     * @param  array<string, mixed>  $event
     */
    public function ingest(Application $application, array $event, ?string $sourceIp): bool
    {
        return DB::transaction(function () use ($application, $event, $sourceIp): bool {
            Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();

            if (EventReceipt::query()->where('event_id', $event['event_id'])->exists()) {
                return true;
            }

            $exception = $event['exception'];
            $request = $event['request'] ?? [];
            $file = $this->relativePath($exception['file'] ?? null);
            $route = $this->clean($request['route'] ?? null);
            $now = now();
            $wasReopened = false;
            $isNew = false;
            $fingerprint = hash('sha256', implode("\n", [
                $application->id,
                $exception['class'],
                $file,
                $exception['line'] ?? '',
                $route,
            ]));

            $issue = Issue::query()
                ->where('application_id', $application->id)
                ->where('fingerprint', $fingerprint)
                ->first();

            if ($issue) {
                $issue->occurrence_count++;
                $issue->last_seen_at = $now;

                if ($issue->status === 'RESOLVED' && config('sivera.auto_reopen', true)) {
                    IssueStatusHistory::create([
                        'issue_id' => $issue->id,
                        'from_status' => 'RESOLVED',
                        'to_status' => 'REOPENED',
                    ]);
                    $issue->status = 'REOPENED';
                    $issue->reopened_at = $now;
                    $wasReopened = true;
                }

                $issue->save();
            } else {
                $isNew = true;
                $issue = Issue::create([
                    'uuid' => (string) Str::uuid(),
                    'application_id' => $application->id,
                    'fingerprint' => $fingerprint,
                    'severity' => strtoupper($event['severity'] ?? 'error'),
                    'status' => 'NEW',
                    'exception_class' => $exception['class'],
                    'message' => $this->clean($exception['message']),
                    'file' => $file,
                    'line' => $exception['line'] ?? null,
                    'route' => $route,
                    'method' => $request['method'] ?? null,
                    'first_seen_at' => $now,
                    'last_seen_at' => $now,
                    'occurrence_count' => 1,
                ]);
            }

            EventReceipt::create([
                'issue_id' => $issue->id,
                'event_id' => $event['event_id'],
                'occurred_at' => $event['occurred_at'],
            ]);

            if ($this->shouldStoreDetail($issue->occurrence_count)) {
                $frames = array_map(fn (array $frame): array => [
                    'file' => $this->relativePath($frame['file'] ?? null),
                    'line' => $frame['line'] ?? null,
                    'function' => $this->clean($frame['function'] ?? null),
                    'class' => $this->clean($frame['class'] ?? null),
                ], $event['stack_trace'] ?? []);

                IssueOccurrence::create([
                    'issue_id' => $issue->id,
                    'event_id' => $event['event_id'],
                    'stack_trace_json' => $frames,
                    'request_metadata_json' => [
                        'method' => $request['method'] ?? null,
                        'route' => $route,
                        'path' => $this->clean($request['path'] ?? null),
                    ],
                    'user_reference' => isset($event['user']['id']) ? (string) $event['user']['id'] : null,
                    'source_ip' => $sourceIp,
                    'occurred_at' => $event['occurred_at'],
                ]);
            }

            if ($isNew && $issue->severity === 'CRITICAL') {
                $this->notifications->evaluate($issue, 'new_critical');
            }

            if ($wasReopened) {
                $this->notifications->evaluate($issue, 'reopened');
            }

            $this->notifications->evaluate($issue, 'high_frequency');

            return false;
        });
    }

    private function relativePath(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $path = str_replace('\\', '/', $path);

        if (preg_match('~(?:^|/)((?:app|vendor)/.+)$~', $path, $matches)) {
            return $matches[1];
        }

        return basename($path);
    }

    private function shouldStoreDetail(int $count): bool
    {
        return $count <= 100
            || ($count <= 1000 && $count % 10 === 0)
            || $count % 100 === 0;
    }

    private function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $pattern = <<<'REGEX'
~(["']?[\w-]*(?:password|passwd|secret|token|authorization|cookie|session|bearer|api[_-]?key)[\w-]*["']?\s*[:=]\s*)(?:"(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'|[^,;\r\n}&]+)~i
REGEX;
        $value = preg_replace($pattern, '$1"[REDACTED]"', $value);

        return preg_replace('/\bBearer\s+[^\s"\'<> ,;}\]]+/i', '[REDACTED]', $value);
    }
}
