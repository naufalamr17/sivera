<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['issue_id', 'event_id', 'occurred_at'])]
class EventReceipt extends Model
{
    use HasFactory;

    public $timestamps = false;

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }
}
