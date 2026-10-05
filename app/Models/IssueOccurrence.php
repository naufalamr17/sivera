<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['issue_id', 'event_id', 'stack_trace_json', 'request_metadata_json', 'user_reference', 'source_ip', 'occurred_at'])]
class IssueOccurrence extends Model
{
    use HasFactory;

    public $timestamps = false;

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    protected function casts(): array
    {
        return [
            'stack_trace_json' => 'array',
            'request_metadata_json' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
