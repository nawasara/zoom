<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nawasara_zoom_users', function (Blueprint $table) {
            $table->id();
            $table->string('user_id')->unique(); // Zoom user ID
            $table->string('email')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->integer('user_type'); // 1=Basic, 2=Licensed, 3=On-prem
            $table->string('license_type')->nullable();
            $table->string('status')->default('active'); // active, pending, inactive
            $table->timestamp('last_login_at')->nullable();
            $table->integer('total_meetings_30d')->default(0);
            $table->integer('total_minutes_30d')->default(0);
            $table->string('dept_id')->nullable(); // registry department ID
            $table->timestamp('zoom_created_at')->nullable();
            
            // Sync tracking
            $table->string('sync_status')->default('pending'); // pending, syncing, synced, failed
            $table->text('sync_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            
            $table->timestamps();
            
            $table->index('email');
            $table->index('status');
            $table->index('sync_status');
            $table->index('last_synced_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_zoom_users');
    }
};
