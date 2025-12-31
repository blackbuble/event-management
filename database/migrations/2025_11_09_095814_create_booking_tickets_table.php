<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->onDelete('cascade');
            $table->foreignId('ticket_id')->constrained()->onDelete('cascade');
            $table->string('ticket_code')->unique();
            $table->integer('quantity');
            $table->decimal('price', 10, 2);
            $table->string('attendee_name')->nullable();
            $table->string('attendee_email')->nullable();
            $table->boolean('checked_in')->default(false);
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamps();
            
            $table->index('ticket_code');
            $table->index(['booking_id', 'checked_in']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_tickets');
    }
};