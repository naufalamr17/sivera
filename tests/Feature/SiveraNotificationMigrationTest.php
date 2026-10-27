<?php

use App\Models\NotificationLog;
use App\Models\NotificationRule;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

it('migrates existing recipients and preserves historical whatsapp delivery records', function () {
    $migration = require database_path('migrations/2026_10_27_070250_switch_notifications_to_daily_email_digest.php');
    $migration->down();
    $email = NotificationRule::factory()->create(['trigger' => 'new_critical', 'target' => 'legacy@example.test']);
    $whatsapp = NotificationRule::factory()->create(['channel' => 'qontak', 'trigger' => 'reopened']);
    $log = NotificationLog::factory()->create(['notification_rule_id' => $whatsapp->id, 'channel' => 'qontak', 'status' => 'sent']);
    $migration->up();
    expect($email->fresh()->trigger)->toBe('daily_digest')->and($email->fresh()->target)->toBe('legacy@example.test')
        ->and($whatsapp->fresh()->is_active)->toBeFalse()->and($log->fresh()->status)->toBe('sent');
});
