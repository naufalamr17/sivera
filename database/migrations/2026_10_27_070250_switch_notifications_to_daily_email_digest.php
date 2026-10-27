<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::withoutForeignKeyConstraints(function () {
            Schema::table('notification_rules', function (Blueprint $table) {
                $table->string('trigger')->change();
            });
        });
        DB::table('notification_rules')->where('channel', 'email')->update(['trigger' => 'daily_digest', 'severity' => null]);
        DB::table('notification_rules')->where('channel', 'qontak')->update(['is_active' => false]);
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('issue_id')->nullable()->change();
            $table->date('digest_date')->nullable();
            $table->string('digest_recipient')->nullable();
            $table->unique(['digest_date', 'digest_recipient'], 'notification_digest_unique');
        });
    }

    public function down(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->dropUnique('notification_digest_unique');
            $table->dropColumn(['digest_date', 'digest_recipient']);
        });
        // Historical digest logs have no issue, so issue_id must remain nullable.
        DB::table('notification_rules')->where('trigger', 'daily_digest')->update(['trigger' => 'new_critical']);
    }
};
