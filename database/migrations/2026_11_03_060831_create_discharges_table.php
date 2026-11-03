<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discharges', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('vessel_id')->constrained()->restrictOnDelete();
            $table->foreignId('commodity_id')->constrained()->restrictOnDelete();
            $table->foreignId('tank_id')->constrained()->restrictOnDelete();
            $table->string('status', 32)->default('draft');
            $table->decimal('gross_mass', 18, 3)->nullable();
            $table->decimal('transit_loss_mass', 18, 3)->nullable();
            $table->decimal('net_received_mass', 18, 3)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamps();

            $table->index('vessel_id');
            $table->index('commodity_id');
            $table->index('tank_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discharges');
    }
};
