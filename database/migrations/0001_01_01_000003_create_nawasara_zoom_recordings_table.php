<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nawasara_zoom_recordings', function (Blueprint $table) {
            $table->id();
            $table->string('recording_id')->unique(); // Zoom recording ID
            $table->string('meeting_id'); // Zoom meeting ID
            $table->string('owner_id'); // Zoom user ID (owner)
            $table->string('topic');
            $table->timestamp('start_time')->nullable();
            $table->integer('duration')->default(0); // seconds
            $table->string('file_type')->nullable(); // MP4, M4A, VTT
            $table->bigInteger('file_size')->nullable(); // bytes
            $table->string('file_url')->nullable();
            $table->string('play_url')->nullable();
            $table->string('download_url')->nullable();
            $table->string('status')->default('processing'); // processing, completed
            $table->string('recording_type')->nullable(); // shared_screen_with_speaker_video, etc
            
            $table->timestamp('zoom_created_at')->nullable();
            
            // Retention
            $table->integer('retention_days')->default(30); // auto-delete after X days
            
            // Sync tracking
            $table->string('sync_status')->default('pending');
            $table->text('sync_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            
            $table->timestamps();
            
            $table->index('meeting_id');
            $table->index('owner_id');
            $table->index('status');
            $table->index('start_time');
            $table->index('sync_status');
            $table->index('last_synced_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_zoom_recordings');
    }
};
