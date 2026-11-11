<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(): View
    {
        return view('audit.index', ['logs' => AuditLog::query()->with('user:id,name')->latest('id')->paginate(50)]);
    }
}
