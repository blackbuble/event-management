<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->after('id')->unique()->nullable(); // Nullable first to populate existing
            $table->string('otp')->nullable()->after('password');
            $table->timestamp('otp_expires_at')->nullable()->after('otp');
            $table->softDeletes()->after('updated_at');
        });

        // Optional: Populate UUIDs for existing users if any (Pseudo-code, better in a seeder or manual update)
        // DB::table('users')->get()->each(function ($user) {
        //     DB::table('users')->where('id', $user->id)->update(['uuid' => Str::uuid()]);
        // });
        
        // After populating, one would typically make it not nullable. 
        // For this migration, we'll leave it nullable or assume fresh DB. 
        // If fresh DB, we can make it not nullable default.
        // But to be safe with existing data:
        // $table->uuid('uuid')->after('id')->unique()->nullable(false)->change(); 
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['uuid', 'otp', 'otp_expires_at', 'deleted_at']);
        });
    }
};
