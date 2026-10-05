<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['uuid', 'application_id', 'fingerprint', 'severity', 'status', 'exception_class', 'message', 'file', 'line', 'route', 'method', 'first_seen_at', 'last_seen_at', 'occurrence_count', 'acknowledged_at', 'investigating_at', 'resolved_at', 'ignored_at', 'reopened_at'])]
class Issue extends Model
{
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(IssueOccurrence::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(EventReceipt::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(IssueStatusHistory::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(IssueComment::class);
    }

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'investigating_at' => 'datetime',
            'resolved_at' => 'datetime',
            'ignored_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }
}
