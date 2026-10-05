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
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'viewer'])->default('viewer');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
        });

        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('environment', 32)->default('production');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('rate_limit_per_minute')->default(60);
            $table->json('allowed_ips')->nullable();
            $table->timestamps();
        });

        Schema::create('application_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('client_id')->unique();
            $table->text('encrypted_secret');
            $table->timestamp('valid_from');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->ipAddress('last_ip')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->char('fingerprint', 64);
            $table->string('severity', 10)->default('ERROR')->index();
            $table->string('status', 16)->default('NEW')->index();
            $table->string('exception_class');
            $table->text('message');
            $table->string('file')->nullable();
            $table->unsignedInteger('line')->nullable();
            $table->string('route')->nullable();
            $table->string('method', 10)->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at')->index();
            $table->unsignedBigInteger('occurrence_count')->default(1);
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('investigating_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('ignored_at')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->timestamps();
            $table->unique(['application_id', 'fingerprint']);
        });

        Schema::create('event_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->string('event_id', 64)->unique();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['issue_id', 'created_at']);
        });

        Schema::create('issue_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->string('event_id', 64)->unique();
            $table->json('stack_trace_json');
            $table->json('request_metadata_json');
            $table->string('user_reference')->nullable();
            $table->ipAddress('source_ip')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['issue_id', 'occurred_at']);
        });

        Schema::create('issue_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 16)->nullable();
            $table->string('to_status', 16);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('issue_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('comment');
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('metadata_json')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('api_request_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->nullable()->constrained()->nullOnDelete();
            $table->string('client_id')->nullable();
            $table->ipAddress('source_ip')->nullable();
            $table->unsignedSmallInteger('http_status');
            $table->boolean('signature_valid')->default(false);
            $table->unsignedInteger('request_size');
            $table->timestamp('received_at')->useCurrent()->index();
            $table->index(['application_id', 'http_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_request_logs');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('issue_comments');
        Schema::dropIfExists('issue_status_histories');
        Schema::dropIfExists('issue_occurrences');
        Schema::dropIfExists('event_receipts');
        Schema::dropIfExists('issues');
        Schema::dropIfExists('application_credentials');
        Schema::dropIfExists('applications');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'is_active', 'last_login_at']);
        });
    }
};
