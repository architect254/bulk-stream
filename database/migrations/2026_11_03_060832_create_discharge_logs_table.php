<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discharge_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discharge_id')->constrained()->cascadeOnDelete();
            $table->string('external_event_id')->unique();
            $table->timestamp('occurred_at');
            $table->string('sequence')->nullable();
            $table->decimal('gross_mass', 18, 3);
            $table->json('metadata')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('discharge_id');
            $table->index('occurred_at');
            $table->index(['discharge_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discharge_logs');
    }
};
