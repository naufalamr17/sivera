<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreApplicationRequest;
use App\Models\Application;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    public function index(): View
    {
        return view('applications.index', [
            'applications' => Application::query()->withCount('issues')->orderBy('name')->paginate(20),
        ]);
    }

    public function show(Application $application): View
    {
        return view('applications.show', [
            'application' => $application->load(['credentials' => fn ($query) => $query->latest()]),
        ]);
    }

    public function create(): View
    {
        return view('applications.form', ['application' => new Application]);
    }

    public function store(StoreApplicationRequest $request): RedirectResponse
    {
        $application = Application::create([
            ...$request->validated(),
            'uuid' => (string) Str::uuid(),
        ]);
        $this->audit($request, 'application.created', $application);

        return redirect()->route('applications.show', $application)->with('status', 'Application created.');
    }

    public function edit(Application $application): View
    {
        return view('applications.form', compact('application'));
    }

    public function update(StoreApplicationRequest $request, Application $application): RedirectResponse
    {
        $application->update($request->validated());
        $this->audit($request, 'application.updated', $application);

        return redirect()->route('applications.show', $application)->with('status', 'Application updated.');
    }

    public function toggle(Request $request, Application $application): RedirectResponse
    {
        $application->update(['is_active' => ! $application->is_active]);
        $this->audit($request, $application->is_active ? 'application.enabled' : 'application.disabled', $application);

        return back()->with('status', 'Application status updated.');
    }

    private function audit(Request $request, string $action, Application $application): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'subject_type' => Application::class,
            'subject_id' => $application->id,
            'ip_address' => $request->ip(),
        ]);
    }
}
