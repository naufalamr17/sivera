<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\EventReceipt;
use App\Models\Issue;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('dashboard', [
            'summary' => $this->summary(),
            'activity' => $this->activity(),
            'pollSeconds' => config('sivera.poll_seconds'),
        ]);
    }

    public function liveSummary(): JsonResponse
    {
        return response()->json($this->summary());
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(): array
    {
        return [
            'applications' => Application::query()->count(),
            'open' => Issue::query()->whereNotIn('status', ['RESOLVED', 'IGNORED'])->count(),
            'critical' => Issue::query()->where('severity', 'CRITICAL')->whereNotIn('status', ['RESOLVED', 'IGNORED'])->count(),
            'today' => EventReceipt::query()->where('created_at', '>=', today())->where('created_at', '<', today()->addDay())->count(),
            'resolved' => Issue::query()->where('status', 'RESOLVED')->count(),
            'latest' => Issue::query()->with('application:id,name,slug')->latest('last_seen_at')->limit(10)->get([
                'id', 'uuid', 'application_id', 'exception_class', 'message', 'severity', 'status', 'occurrence_count', 'last_seen_at',
            ]),
        ];
    }

    /**
     * @return array<int, array{label: string, count: int, height: int}>
     */
    private function activity(): array
    {
        $bucket = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m-%d %H:00', created_at)"
            : "DATE_FORMAT(created_at, '%Y-%m-%d %H:00')";
        $counts = EventReceipt::query()
            ->where('created_at', '>=', now()->subHours(23)->startOfHour())
            ->selectRaw($bucket.' as hour_bucket, COUNT(*) as total')
            ->groupBy('hour_bucket')
            ->pluck('total', 'hour_bucket');
        $hours = [];

        for ($offset = 23; $offset >= 0; $offset--) {
            $hour = now()->subHours($offset)->startOfHour();
            $hours[] = ['label' => $hour->format('H:i'), 'count' => (int) ($counts[$hour->format('Y-m-d H:00')] ?? 0)];
        }

        $maximum = max(1, ...array_column($hours, 'count'));

        return array_map(fn (array $hour): array => [
            ...$hour,
            'height' => $hour['count'] === 0 ? 0 : max(3, (int) round($hour['count'] / $maximum * 100)),
        ], $hours);
    }
}
