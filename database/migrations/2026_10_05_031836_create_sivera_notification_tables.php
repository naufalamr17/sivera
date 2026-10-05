<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notification_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('trigger', ['new_critical', 'becomes_critical', 'reopened', 'high_frequency']);
            $table->enum('channel', ['email', 'qontak']);
            $table->string('target');
            $table->string('target_name')->nullable();
            $table->string('severity', 10)->nullable();
            $table->unsignedInteger('frequency_threshold')->default(10);
            $table->unsignedSmallInteger('frequency_window_minutes')->default(5);
            $table->unsignedSmallInteger('cooldown_minutes')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notification_rule_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 10);
            $table->string('status', 10)->default('pending');
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['issue_id', 'notification_rule_id', 'created_at'], 'notification_cooldown_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('notification_rules');
    }
};
