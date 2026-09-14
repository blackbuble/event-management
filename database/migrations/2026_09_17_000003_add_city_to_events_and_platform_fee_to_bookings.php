<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('city')->nullable()->after('category');
        });

        Schema::table('bookings', function (Blueprint $table) {
            // Platform fee captured separately from the ticket subtotal.
            $table->decimal('platform_fee', 12, 2)->default(0)->after('total_amount');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('city');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('platform_fee');
        });
    }
};
