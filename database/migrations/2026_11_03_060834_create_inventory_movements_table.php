<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->nullable()->unique();
            $table->foreignId('tank_id')->constrained()->restrictOnDelete();
            $table->foreignId('commodity_id')->constrained()->restrictOnDelete();
            $table->string('movement_type', 32);
            $table->decimal('quantity', 18, 3);
            $table->decimal('balance_before', 18, 3);
            $table->decimal('balance_after', 18, 3);
            $table->nullableMorphs('source');
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index('tank_id');
            $table->index('commodity_id');
            $table->index('movement_type');
            $table->index('occurred_at');
            $table->index(['tank_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
