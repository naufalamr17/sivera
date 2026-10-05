<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNotificationRuleRequest;
use App\Models\AuditLog;
use App\Models\NotificationLog;
use App\Models\NotificationRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationSettingController extends Controller
{
    public function index(): View
    {
        return view('notifications.index', [
            'rules' => NotificationRule::query()->orderBy('name')->get(),
            'logs' => NotificationLog::query()->with('issue', 'rule')->latest('id')->limit(20)->get(),
        ]);
    }

    public function create(): View
    {
        return view('notifications.form', ['rule' => new NotificationRule]);
    }

    public function store(StoreNotificationRuleRequest $request): RedirectResponse
    {
        $rule = NotificationRule::create($request->validated());
        $this->audit($request, 'notification_rule.created', $rule);

        return redirect()->route('notifications.index')->with('status', 'Notification rule created.');
    }

    public function edit(NotificationRule $rule): View
    {
        return view('notifications.form', compact('rule'));
    }

    public function update(StoreNotificationRuleRequest $request, NotificationRule $rule): RedirectResponse
    {
        $rule->update($request->validated());
        $this->audit($request, 'notification_rule.updated', $rule);

        return redirect()->route('notifications.index')->with('status', 'Notification rule updated.');
    }

    private function audit(Request $request, string $action, NotificationRule $rule): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'subject_type' => NotificationRule::class,
            'subject_id' => $rule->id,
            'ip_address' => $request->ip(),
        ]);
    }
}
