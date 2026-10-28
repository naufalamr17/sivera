<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['issue_id', 'event_receipt_id', 'status_history_id', 'type', 'from_status', 'status', 'follow_up_date', 'created_at'])]
class IssueAlert extends Model
{
    use HasFactory;

    public $timestamps = false;

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
