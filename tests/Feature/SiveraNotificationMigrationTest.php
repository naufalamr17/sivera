<?php

use App\Models\NotificationLog;
use App\Models\NotificationRule;
use App\Models\User;
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

it('preserves digest and legacy delivery history when realtime email fields are rolled back and reapplied', function () {
    $user = User::factory()->create();
    $digest = NotificationLog::create([
        'user_id' => $user->id, 'channel' => 'email', 'status' => 'sent',
        'digest_date' => '2026-10-27', 'digest_recipient' => $user->email,
    ]);
    $legacy = NotificationLog::factory()->create(['status' => 'sent']);
    $migration = require database_path('migrations/2026_11_09_071514_add_realtime_email_fields_to_notification_logs_table.php');

    $migration->down();
    expect($digest->fresh()->digest_recipient)->toBe($user->email)
        ->and($legacy->fresh()->status)->toBe('sent');
    $migration->up();
    expect(NotificationLog::count())->toBe(2)
        ->and($digest->fresh()->digest_date)->toBe('2026-10-27')
        ->and($legacy->fresh()->issue_id)->toBe($legacy->issue_id);
});
