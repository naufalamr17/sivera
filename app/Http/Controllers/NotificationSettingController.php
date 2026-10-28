<?php

namespace App\Http\Controllers;

use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\View\View;

class NotificationSettingController extends Controller
{
    public function index(): View
    {
        return view('notifications.index', [
            'recipients' => User::query()->where('is_active', true)->orderBy('name')->orderBy('id')
                ->paginate(20, ['*'], 'recipients_page')->withQueryString(),
            'logs' => NotificationLog::query()->with('issue', 'user', 'rule')->latest('id')
                ->paginate(20, ['*'], 'deliveries_page')->withQueryString(),
        ]);
    }
}
