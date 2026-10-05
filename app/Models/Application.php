<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['uuid', 'name', 'slug', 'description', 'environment', 'is_active', 'rate_limit_per_minute', 'allowed_ips'])]
class Application extends Model
{
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(ApplicationCredential::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'allowed_ips' => 'array'];
    }
}
