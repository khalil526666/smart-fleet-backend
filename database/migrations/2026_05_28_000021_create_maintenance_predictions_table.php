<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->integer('predicted_days');
            $table->date('predicted_date');
            $table->enum('urgency_level', ['faible', 'moyen', 'eleve']);
            $table->json('features_snapshot');
            $table->string('confidence_note')->nullable();
            $table->string('model_version', 20)->default('1.0.0');
            $table->timestamps();

            $table->index(['vehicle_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_predictions');
    }
};
