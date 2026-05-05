<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nawasara_zoom_meetings', function (Blueprint $table) {
            $table->id();
            $table->string('meeting_id')->unique(); // Zoom meeting ID
            $table->string('host_id'); // Zoom user ID (host)
            $table->string('topic');
            $table->timestamp('start_time')->nullable();
            $table->integer('duration')->default(0); // minutes
            $table->string('timezone')->nullable();
            $table->string('password')->nullable();
            $table->text('agenda')->nullable();
            $table->string('status')->default('not_started'); // not_started, started, finished
            $table->integer('type')->default(2); // 1=instant, 2=scheduled, 3=recurring, 8=pmi
            $table->string('join_url')->nullable();
            $table->boolean('recording_consent')->default(false);
            $table->string('auto_recording')->default('none'); // none, local, cloud, both
            $table->boolean('waiting_room')->default(false);
            $table->json('recurrence_config')->nullable(); // {type: daily|weekly|monthly, repeat_interval}
            $table->json('settings')->nullable();
            
            $table->timestamp('zoom_created_at')->nullable();
            $table->timestamp('zoom_updated_at')->nullable();
            
            // Sync tracking
            $table->string('sync_status')->default('pending');
            $table->text('sync_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            
            $table->timestamps();
            
            $table->index('host_id');
            $table->index('start_time');
            $table->index('status');
            $table->index('sync_status');
            $table->index('last_synced_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_zoom_meetings');
    }
};
