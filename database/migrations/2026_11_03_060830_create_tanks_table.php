<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tanks', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('commodity_id')->constrained()->restrictOnDelete();
            $table->decimal('capacity', 18, 3);
            $table->decimal('current_quantity', 18, 3)->default(0);
            $table->string('status', 32)->default('active');
            $table->timestamps();

            $table->index('commodity_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tanks');
    }
};
