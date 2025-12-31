<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_reservations', function (Blueprint $table) {
            $table->id();
            $table->string('reservation_token')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->json('ticket_data');
            $table->timestamp('expires_at');
            $table->enum('status', ['active', 'completed', 'expired', 'cancelled'])->default('active');
            $table->timestamps();
            
            $table->index(['expires_at', 'status']);
            $table->index('reservation_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_reservations');
    }
};