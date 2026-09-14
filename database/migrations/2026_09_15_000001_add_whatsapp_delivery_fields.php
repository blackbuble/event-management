<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Per-event switch: deliver ticket QRs over WhatsApp (uses organizer quota).
            $table->boolean('whatsapp_enabled')->default(false)->after('meeting_link');
        });

        Schema::table('booking_tickets', function (Blueprint $table) {
            // Optional attendee WhatsApp number for per-ticket delivery.
            $table->string('attendee_phone')->nullable()->after('attendee_email');
        });

        Schema::table('users', function (Blueprint $table) {
            // Remaining WhatsApp ticket messages the organizer can send.
            $table->unsignedInteger('whatsapp_quota')->default(0)->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('whatsapp_enabled');
        });

        Schema::table('booking_tickets', function (Blueprint $table) {
            $table->dropColumn('attendee_phone');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('whatsapp_quota');
        });
    }
};
