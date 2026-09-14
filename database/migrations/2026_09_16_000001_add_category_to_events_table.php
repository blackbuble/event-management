<?php

use App\Enums\EventCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Every event is categorised; defaults to `other` for existing rows.
            $table->string('category')->default(EventCategory::Other->value)->after('type');
        });

        DB::table('events')->whereNull('category')->update(['category' => EventCategory::Other->value]);
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
