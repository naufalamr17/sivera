<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(): View
    {
        return view('audit.index', ['logs' => AuditLog::query()->latest('id')->paginate(50)]);
    }
}
