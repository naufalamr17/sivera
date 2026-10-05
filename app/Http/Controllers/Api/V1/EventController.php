<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventRequest;
use App\Services\Sivera\EventIngestionService;
use Illuminate\Http\JsonResponse;

class EventController extends Controller
{
    public function store(StoreEventRequest $request, EventIngestionService $ingestion): JsonResponse
    {
        $duplicate = $ingestion->ingest(
            $request->attributes->get('sivera_application'),
            $request->validated(),
            $request->ip(),
        );

        return response()->json([
            'accepted' => true,
            'duplicate' => $duplicate,
            'event_id' => $request->validated('event_id'),
        ], 202);
    }
}
