<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the audit actor name instead of the user id and loads users together', function () {
    $admin = User::factory()->admin()->create();
    $actor = User::factory()->create(['name' => 'Audit <Operator> & Team']);
    AuditLog::factory()->create(['user_id' => $actor->id]);

    $this->actingAs($admin)->get(route('audit.index'))->assertOk()
        ->assertSee('<th>USER NAME</th>', false)
        ->assertSee('<td>'.e($actor->name).'</td>', false)
        ->assertDontSee('<td>'.$actor->name.'</td>', false)
        ->assertViewHas('logs', fn ($logs): bool => $logs->every(
            fn (AuditLog $log): bool => $log->relationLoaded('user') && $log->user?->name === $actor->name,
        ));
});

it('keeps audit entries readable when the associated user has been deleted', function () {
    $admin = User::factory()->admin()->create();
    $actor = User::factory()->create();
    AuditLog::factory()->create(['user_id' => $actor->id]);
    $actor->delete();

    $this->actingAs($admin)->get(route('audit.index'))->assertOk()
        ->assertSee('<td>—</td><td><strong>application.created</strong>', false);
});
