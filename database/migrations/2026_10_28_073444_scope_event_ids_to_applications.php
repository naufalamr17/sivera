<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['event_receipts', 'issue_occurrences'] as $tableName) {
            if (! Schema::hasColumn($tableName, 'application_id')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->foreignId('application_id')->nullable()->constrained()->cascadeOnDelete();
                });
            }

            DB::table($tableName)->whereNull('application_id')->update([
                'application_id' => DB::raw('(SELECT application_id FROM issues WHERE issues.id = '.$tableName.'.issue_id)'),
            ]);

            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('application_id')->nullable(false)->change();
            });

            if (! Schema::hasIndex($tableName, ['application_id', 'event_id'], 'unique')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->unique(['application_id', 'event_id']);
                });
            }

            if (Schema::hasIndex($tableName, ['event_id'], 'unique')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->dropUnique(['event_id']);
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['event_receipts', 'issue_occurrences'] as $tableName) {
            if (DB::table($tableName)->select('event_id')->groupBy('event_id')->havingRaw('COUNT(*) > 1')->exists()) {
                throw new RuntimeException('Cannot restore global event IDs while applications have colliding IDs. Keep the application-scoped schema.');
            }
        }

        foreach (['event_receipts', 'issue_occurrences'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unique('event_id');
                $table->dropForeign(['application_id']);
                $table->dropUnique(['application_id', 'event_id']);
                $table->dropColumn('application_id');
            });
        }
    }
};
