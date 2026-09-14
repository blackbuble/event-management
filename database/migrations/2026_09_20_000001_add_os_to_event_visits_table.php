<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_visits', function (Blueprint $table) {
            $table->string('os', 30)->nullable()->after('device');
            $table->index('os');
        });
    }

    public function down(): void
    {
        Schema::table('event_visits', function (Blueprint $table) {
            $table->dropColumn('os');
        });
    }
};
