<?php

namespace App\Http\Controllers;

use App\Models\Issue;
use App\Models\IssueAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationFeedController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $filters = $request->validate(['after' => ['nullable', 'integer', 'min:0']]);
        $after = $filters['after'] ?? null;
        $initialCursor = $after === null ? (int) IssueAlert::query()->max('id') : null;
        $query = IssueAlert::query()->with('issue.application:id,name');
        $alerts = $after === null
            ? $query->where('id', '<=', $initialCursor)->latest('created_at')->latest('id')->limit(20)->get()->reverse()->values()
            : $query->where('id', '>', $after)->orderBy('id')->limit(50)->get();
        $visible = $alerts->filter(fn (IssueAlert $alert): bool => $alert->type !== 'follow_up'
            || ! in_array($alert->issue->status, Issue::CLOSED_STATUSES, true))->values();

        return response()->json([
            'cursor' => $initialCursor ?? (int) ($alerts->last()?->id ?? $after ?? 0),
            'has_more' => $after !== null && $alerts->count() === 50,
            'events' => $visible->map(fn (IssueAlert $alert): array => [
                'id' => $alert->id,
                'issue_id' => $alert->issue->uuid,
                'type' => $alert->type,
                'status' => $alert->type === 'status_changed' ? $alert->status : $alert->issue->status,
                'from_status' => $alert->from_status,
                'application' => $alert->issue->application->name,
                'exception_class' => $alert->issue->exception_class,
                'message' => mb_substr($alert->issue->message, 0, 200),
                'severity' => $alert->issue->severity,
                'received_at' => $alert->created_at->toIso8601String(),
                'url' => route('issues.show', $alert->issue),
            ]),
        ])->header('Cache-Control', 'private, no-store');
    }
}
