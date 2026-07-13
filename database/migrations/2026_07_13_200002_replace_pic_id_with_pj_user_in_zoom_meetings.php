<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replace the meeting's manual PIC (registry) reference with a "penanggung
 * jawab" (person in charge) backed by a real Laravel User (sourced from
 * Keycloak). The registry Pic model/table has been removed app-wide, so the
 * old pic_id FK to nawasara_registry_pic must go.
 *
 * Prod data check: 0 zoom meetings carry a pic_id, so dropping the column is
 * safe (no data migration needed).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Idempotent + defensive: the registry migration may have already
        // dropped the pic_id FK/column (it does so to be able to drop the PIC
        // table), so only drop when the column is still present.
        if (Schema::hasColumn('nawasara_zoom_meetings', 'pic_id')) {
            Schema::table('nawasara_zoom_meetings', function (Blueprint $table) {
                $table->dropConstrainedForeignId('pic_id');
            });
        }

        if (! Schema::hasColumn('nawasara_zoom_meetings', 'pj_user_id')) {
            Schema::table('nawasara_zoom_meetings', function (Blueprint $table) {
                $table->foreignId('pj_user_id')
                    ->nullable()
                    ->after('host_id')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('nawasara_zoom_meetings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pj_user_id');

            // The registry PIC table has been removed, so we cannot restore a
            // foreign constraint to nawasara_registry_pic here. Re-add pic_id
            // as a plain nullable column only — a rollback resurrects the
            // shape, not the (now nonexistent) relationship.
            $table->unsignedBigInteger('pic_id')->nullable()->after('host_id');
        });
    }
};
