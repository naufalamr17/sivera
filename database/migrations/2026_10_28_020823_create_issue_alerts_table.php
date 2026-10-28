<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('issue_alerts')) {
            Schema::create('issue_alerts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
                $table->foreignId('event_receipt_id')->nullable()->unique()->constrained()->cascadeOnDelete();
                $table->foreignId('status_history_id')->nullable()->unique()->constrained('issue_status_histories')->cascadeOnDelete();
                $table->string('type', 20);
                $table->string('from_status', 20)->nullable();
                $table->string('status', 20)->nullable();
                $table->date('follow_up_date')->nullable();
                $table->timestamp('created_at')->useCurrent()->index();
                $table->unique(['issue_id', 'follow_up_date']);
            });
        }

        DB::table('event_receipts')->orderBy('id')->chunkById(500, function ($receipts): void {
            DB::table('issue_alerts')->insertOrIgnore($receipts->map(fn ($receipt): array => [
                'issue_id' => $receipt->issue_id,
                'event_receipt_id' => $receipt->id,
                'type' => 'event',
                'created_at' => $receipt->created_at,
            ])->all());
        });
        DB::table('issue_status_histories')->orderBy('id')->chunkById(500, function ($histories): void {
            DB::table('issue_alerts')->insertOrIgnore($histories->map(fn ($history): array => [
                'issue_id' => $history->issue_id,
                'status_history_id' => $history->id,
                'type' => 'status_changed',
                'from_status' => $history->from_status,
                'status' => $history->to_status,
                'created_at' => $history->created_at,
            ])->all());
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_alerts');
    }
};
