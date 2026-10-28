<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $addUser = ! Schema::hasColumn('notification_logs', 'user_id');
        Schema::table('notification_logs', function (Blueprint $table) use ($addUser) {
            $table->unsignedBigInteger('notification_rule_id')->nullable()->change();
            if ($addUser) {
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
        // Keep the legacy rule nullable to preserve user-based delivery history.
    }
};
