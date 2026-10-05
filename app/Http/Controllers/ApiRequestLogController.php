<?php

namespace App\Http\Controllers;

use App\Models\ApiRequestLog;
use Illuminate\View\View;

class ApiRequestLogController extends Controller
{
    public function index(): View
    {
        return view('api-logs.index', [
            'logs' => ApiRequestLog::query()->with('application:id,name')->latest('id')->paginate(50),
        ]);
    }
}
