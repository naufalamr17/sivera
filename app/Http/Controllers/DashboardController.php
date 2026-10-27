<?php

namespace App\Http\Controllers;

use App\Http\Requests\DashboardRequest;
use App\Services\Sivera\IssueAnalytics;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(DashboardRequest $request, IssueAnalytics $analytics): View
    {
        return view('dashboard', ['summary' => $this->summary($request, $analytics)]);
    }

    public function liveSummary(DashboardRequest $request, IssueAnalytics $analytics): JsonResponse
    {
        return response()->json($this->summary($request, $analytics))->header('Cache-Control', 'private, no-store');
    }

    /** @return array<string, mixed> */
    private function summary(DashboardRequest $request, IssueAnalytics $analytics): array
    {
        $filters = $request->validated();
        $today = CarbonImmutable::now(config('sivera.timezone'));
        $from = $filters['from'] ?? $filters['to'] ?? $today->subDays(6)->toDateString();
        $to = $filters['to'] ?? $filters['from'] ?? $today->toDateString();
        abort_if(CarbonImmutable::parse($from)->diffInDays($to) > 365, 422, 'Choose a range of at most 366 days.');

        return $analytics->summary($from, $to);
    }
}
