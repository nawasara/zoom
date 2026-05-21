<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional PIC (person in charge) for a Zoom meeting, referencing the registry
 * package's PIC table. Nullable — most meetings won't have one. nullOnDelete:
 * if the registry PIC is removed, the meeting keeps existing with pic_id null
 * rather than being deleted along with it.
 *
 * This couples nawasara/zoom to nawasara/registry — the dependency is declared
 * in composer.json.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nawasara_zoom_meetings', function (Blueprint $table) {
            $table->foreignId('pic_id')
                ->nullable()
                ->after('host_id')
                ->constrained('nawasara_registry_pic')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('nawasara_zoom_meetings', function (Blueprint $table) {
            $table->dropForeign(['pic_id']);
            $table->dropColumn('pic_id');
        });
    }
};
