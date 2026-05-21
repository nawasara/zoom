<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * meeting_id holds the Zoom-assigned meeting ID, which only exists AFTER the
 * Zoom API creates the meeting. The create flow inserts a local "pending" row
 * first (so it shows in the list while the queued job runs), so meeting_id
 * must be nullable until the job fills it in. The unique index stays — MySQL
 * permits multiple NULLs under a UNIQUE constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nawasara_zoom_meetings', function (Blueprint $table) {
            $table->string('meeting_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('nawasara_zoom_meetings', function (Blueprint $table) {
            $table->string('meeting_id')->nullable(false)->change();
        });
    }
};
