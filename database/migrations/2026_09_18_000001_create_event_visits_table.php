<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip')->nullable();
            $table->string('country', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('device', 20)->default('desktop');
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'created_at']);
            $table->index('country');
            $table->index('device');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_visits');
    }
};
