<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['application_id', 'client_id', 'source_ip', 'http_status', 'signature_valid', 'request_size'])]
class ApiRequestLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
