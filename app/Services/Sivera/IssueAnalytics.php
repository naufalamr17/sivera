<?php

namespace App\Services\Sivera;

use App\Models\Application;
use App\Models\EventReceipt;
use App\Models\Issue;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class IssueAnalytics
{
    /** @return array<string, mixed> */
    public function summary(string $from, string $to, int $issuesPage = 1): array
    {
        $timezone = config('sivera.timezone');
        $start = CarbonImmutable::parse($from, $timezone)->startOfDay()->utc();
        $end = CarbonImmutable::parse($to, $timezone)->addDay()->startOfDay()->utc();
        $receipts = EventReceipt::query()->where('created_at', '>=', $start)->where('created_at', '<', $end);
        $counts = (clone $receipts)->selectRaw('issue_id, COUNT(*) as period_count, MAX(created_at) as period_last_seen')->groupBy('issue_id');
        $issues = Issue::query()->joinSub($counts, 'period', 'issues.id', '=', 'period.issue_id');
        $errors = (clone $issues)->whereIn('issues.severity', ['ERROR', 'CRITICAL']);
        $ranking = DB::query()->fromSub(
            (clone $errors)->select('issues.application_id', 'period.period_count'), 'errors'
        )->join('applications', 'applications.id', '=', 'errors.application_id')
            ->select('applications.id', 'applications.name', 'applications.slug')
            ->selectRaw('SUM(errors.period_count) as events, COUNT(*) as issues')
            ->groupBy('applications.id', 'applications.name', 'applications.slug')
            ->orderByDesc('events')->orderBy('applications.id')->limit(5)->get();

        $latest = (clone $issues)->with('application:id,name,slug')->orderByDesc('period_last_seen')->orderBy('issues.id')
            ->paginate(10, ['issues.*', 'period.period_count', 'period.period_last_seen'], 'issues_page', $issuesPage);

        return [
            'from' => $from,
            'to' => $to,
            'timezone' => $timezone,
            'applications' => Application::query()->count(),
            'open' => (clone $issues)->whereNotIn('status', ['RESOLVED', 'IGNORED'])->count(),
            'critical' => (clone $issues)->where('severity', 'CRITICAL')->whereNotIn('status', ['RESOLVED', 'IGNORED'])->count(),
            'events' => (clone $receipts)->count(),
            'resolved' => (clone $issues)->where('status', 'RESOLVED')->count(),
            'latest' => $latest->items(),
            'latest_pagination' => [
                'current_page' => $latest->currentPage(),
                'last_page' => $latest->lastPage(),
                'total' => $latest->total(),
                'from' => $latest->firstItem(),
                'to' => $latest->lastItem(),
            ],
            'ranking' => $ranking,
            'top_errors' => (clone $errors)->with('application:id,name,slug')->orderByDesc('period_count')->orderBy('issues.id')->limit(5)
                ->get(['issues.*', 'period.period_count']),
            'activity' => $this->activity($receipts, $start, $end),
        ];
    }

    /** @return array<int, array{label: string, count: int}> */
    private function activity(Builder $receipts, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $hours = $start->diffInHours($end) <= 24;
        $format = $hours ? 'Y-m-d H:00' : 'Y-m-d';
        $bucket = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m-%d %H:00', created_at)"
            : "DATE_FORMAT(created_at, '%Y-%m-%d %H:00')";
        $counts = (clone $receipts)->selectRaw($bucket.' as bucket, COUNT(*) as total')->groupBy('bucket')->pluck('total', 'bucket');
        $localCounts = [];
        foreach ($counts as $utc => $count) {
            $key = CarbonImmutable::parse($utc, 'UTC')->setTimezone(config('sivera.timezone'))->format($format);
            $localCounts[$key] = ($localCounts[$key] ?? 0) + (int) $count;
        }

        $activity = [];
        for ($time = $start->setTimezone(config('sivera.timezone')); $time->utc()->lt($end); $time = $hours ? $time->addHour() : $time->addDay()) {
            $activity[] = ['label' => $time->format($hours ? 'H:i' : 'd M'), 'count' => $localCounts[$time->format($format)] ?? 0];
        }

        return $activity;
    }
}
