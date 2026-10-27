<?php

use App\Models\EventReceipt;
use App\Models\Issue;
use App\Models\NotificationLog;
use App\Models\NotificationRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('paginates overview issues without changing period totals or rankings', function () {
    $issues = Issue::factory()->count(12)->create(['severity' => 'ERROR']);
    foreach ($issues as $issue) {
        $receipt = EventReceipt::factory()->for($issue)->create();
        DB::table('event_receipts')->where('id', $receipt->id)->update(['created_at' => '2026-10-26 06:00:00']);
    }
    $filters = ['from' => '2026-10-26', 'to' => '2026-10-26'];
    $first = $this->actingAs(User::factory()->create())->getJson(route('dashboard.live-summary', $filters))
        ->assertOk()->assertJsonCount(10, 'latest')->assertJsonPath('events', 12)
        ->assertJsonPath('latest_pagination.total', 12)->assertJsonPath('latest_pagination.current_page', 1)
        ->assertJsonPath('latest_pagination.last_page', 2);
    $second = $this->getJson(route('dashboard.live-summary', [...$filters, 'issues_page' => 2]))
        ->assertOk()->assertJsonCount(2, 'latest')->assertJsonPath('events', 12)
        ->assertJsonPath('latest_pagination.current_page', 2);
    expect(array_intersect(array_column($first->json('latest'), 'id'), array_column($second->json('latest'), 'id')))->toBeEmpty()
        ->and($second->json('ranking'))->toBe($first->json('ranking'));
    $this->get(route('dashboard', [...$filters, 'issues_page' => 2]))->assertOk()
        ->assertViewHas('summary', fn (array $summary): bool => $summary['latest_pagination']['current_page'] === 2)
        ->assertSee('Monitoring history older than 3 years');
});

it('validates overview page numbers on initial and polling requests', function (mixed $page) {
    $this->actingAs(User::factory()->create())->getJson(route('dashboard', ['issues_page' => $page]))->assertUnprocessable();
    $this->getJson(route('dashboard.live-summary', ['issues_page' => $page]))->assertUnprocessable();
})->with([0, -1, 'invalid', 100001]);

it('paginates recipients and delivery history independently', function () {
    NotificationRule::factory()->count(22)->create();
    $rule = NotificationRule::first();
    NotificationLog::factory()->count(23)->create(['notification_rule_id' => $rule->id]);

    $this->actingAs(User::factory()->admin()->create())->get(route('notifications.index', ['recipients_page' => 2]))
        ->assertOk()->assertViewHas('rules', fn ($rules): bool => $rules->count() === 2 && $rules->currentPage() === 2)
        ->assertViewHas('logs', fn ($logs): bool => $logs->count() === 20 && $logs->currentPage() === 1)
        ->assertSee('recipients_page=2&amp;deliveries_page=2', false);
    $this->get(route('notifications.index', ['recipients_page' => 2, 'deliveries_page' => 2]))
        ->assertOk()->assertViewHas('rules', fn ($rules): bool => $rules->currentPage() === 2)
        ->assertViewHas('logs', fn ($logs): bool => $logs->count() === 3 && $logs->currentPage() === 2);
});

it('shows the retention policy to both roles on workspace pages', function (string $role) {
    $this->actingAs(User::factory()->create(['role' => $role]))->get(route('issues.index'))->assertOk()
        ->assertSee('Monitoring history older than 3 years')->assertSee('This includes open issues');
})->with(['admin', 'viewer']);
