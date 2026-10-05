<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'trigger', 'channel', 'target', 'target_name', 'severity', 'frequency_threshold', 'frequency_window_minutes', 'cooldown_minutes', 'is_active'])]
class NotificationRule extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
