<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['issue_id', 'notification_rule_id', 'channel', 'status', 'response_code', 'sent_at'])]
class NotificationLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(NotificationRule::class, 'notification_rule_id');
    }

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }
}
