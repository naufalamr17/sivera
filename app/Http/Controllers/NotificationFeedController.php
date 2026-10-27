<?php

namespace App\Http\Controllers;

use App\Models\EventReceipt;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationFeedController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $filters = $request->validate(['after' => ['nullable', 'integer', 'min:0']]);
        $after = $filters['after'] ?? null;
        $query = EventReceipt::query()->with('issue.application:id,name');
        $receipts = $after === null
            ? $query->latest('id')->limit(20)->get()->reverse()->values()
            : $query->where('id', '>', $after)->orderBy('id')->limit(50)->get();

        return response()->json([
            'cursor' => (int) ($receipts->last()?->id ?? $after ?? 0),
            'has_more' => $after !== null && $receipts->count() === 50,
            'events' => $receipts->map(fn (EventReceipt $receipt): array => [
                'id' => $receipt->id, 'issue_id' => $receipt->issue->uuid,
                'application' => $receipt->issue->application->name,
                'exception_class' => $receipt->issue->exception_class,
                'message' => mb_substr($receipt->issue->message, 0, 200),
                'severity' => $receipt->issue->severity,
                'received_at' => CarbonImmutable::parse($receipt->getRawOriginal('created_at'), 'UTC')->toIso8601String(),
                'url' => route('issues.show', $receipt->issue),
            ]),
        ])->header('Cache-Control', 'private, no-store');
    }
}
