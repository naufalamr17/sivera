<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexIssueRequest;
use App\Http\Requests\UpdateIssueRequest;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Issue;
use App\Models\IssueComment;
use App\Models\IssueStatusHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class IssueController extends Controller
{
    public function index(IndexIssueRequest $request): View
    {
        $filters = $request->validated();
        $issues = Issue::query()->with('application:id,name,slug')
            ->when($filters['application'] ?? null, fn ($query, $value) => $query->where('application_id', $value))
            ->when($filters['severity'] ?? null, fn ($query, $value) => $query->where('severity', $value))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['exception_class'] ?? null, fn ($query, $value) => $query->where('exception_class', 'like', '%'.$value.'%'))
            ->when($filters['route'] ?? null, fn ($query, $value) => $query->where('route', 'like', '%'.$value.'%'))
            ->when($filters['from'] ?? null, fn ($query, $value) => $query->where('last_seen_at', '>=', Carbon::parse($value)->startOfDay()))
            ->when($filters['to'] ?? null, fn ($query, $value) => $query->where('last_seen_at', '<', Carbon::parse($value)->addDay()->startOfDay()))
            ->when($filters['search'] ?? null, function ($query, $value) {
                $query->where(function ($query) use ($value) {
                    $query->where('exception_class', 'like', '%'.$value.'%')
                        ->orWhere('message', 'like', '%'.$value.'%')
                        ->orWhere('file', 'like', '%'.$value.'%')
                        ->orWhere('route', 'like', '%'.$value.'%')
                        ->orWhere('uuid', 'like', '%'.$value.'%');
                });
            })
            ->latest('last_seen_at')->paginate(20)->withQueryString();

        return view('issues.index', [
            'issues' => $issues,
            'applications' => Application::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Issue $issue): View
    {
        return view('issues.show', [
            'issue' => $issue->load('application', 'histories', 'comments.user'),
            'occurrences' => $issue->occurrences()->latest('occurred_at')->paginate(20),
        ]);
    }

    public function status(UpdateIssueRequest $request, Issue $issue): RedirectResponse
    {
        $status = $request->validated('status');
        $previous = $issue->status;

        if ($previous !== $status) {
            $issue->update([
                'status' => $status,
                strtolower($status).'_at' => now(),
            ]);
            IssueStatusHistory::create([
                'issue_id' => $issue->id,
                'from_status' => $previous,
                'to_status' => $status,
                'changed_by' => $request->user()->id,
            ]);
            $this->audit($request, 'issue.status_changed', $issue);

        }

        return back()->with('status', 'Issue status updated.');
    }

    public function severity(UpdateIssueRequest $request, Issue $issue): RedirectResponse
    {
        $issue->update(['severity' => $request->validated('severity')]);
        $this->audit($request, 'issue.severity_changed', $issue);

        return back()->with('status', 'Severity updated.');
    }

    public function comment(UpdateIssueRequest $request, Issue $issue): RedirectResponse
    {
        IssueComment::create([
            'issue_id' => $issue->id,
            'user_id' => $request->user()->id,
            'comment' => $request->validated('comment'),
        ]);
        $this->audit($request, 'issue.comment_added', $issue);

        return back()->with('status', 'Comment added.');
    }

    private function audit(Request $request, string $action, Issue $issue): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'subject_type' => Issue::class,
            'subject_id' => $issue->id,
            'ip_address' => $request->ip(),
        ]);
    }
}
