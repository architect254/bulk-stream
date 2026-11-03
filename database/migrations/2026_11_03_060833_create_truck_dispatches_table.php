<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('truck_dispatches', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('truck_registration');
            $table->string('driver_name');
            $table->foreignId('commodity_id')->constrained()->restrictOnDelete();
            $table->foreignId('tank_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 3);
            $table->string('status', 32)->default('planned');
            $table->timestamp('dispatched_at')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamps();

            $table->index('commodity_id');
            $table->index('tank_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('truck_dispatches');
    }
};
