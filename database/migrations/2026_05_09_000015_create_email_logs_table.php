<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();

            $table->string('type'); // e.g. mission_started, incident_reported...
            $table->string('to_email');
            $table->string('to_name')->nullable();
            $table->string('subject');

            $table->string('status'); // sent|failed|skipped
            $table->text('error_message')->nullable();

            $table->nullableMorphs('related'); // Mission/Incident/Maintenance/User...
            $table->json('payload')->nullable(); // safe snapshot (no secrets)

            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['to_email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};

