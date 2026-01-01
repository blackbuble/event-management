<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $foreignKey = 'organization_id'; // Explicitly using organization_id as set in config

        // 1. Update Roles Table
        if (Schema::hasTable($tableNames['roles']) && !Schema::hasColumn($tableNames['roles'], $foreignKey)) {
            Schema::table($tableNames['roles'], function (Blueprint $table) use ($foreignKey) {
                $table->unsignedBigInteger($foreignKey)->nullable()->after('id');
                $table->index($foreignKey, 'roles_organization_id_index');
                
                // We drop the unique constraint on name+guard to allow same role name in different orgs
                // But we need to check if the index exists first. 
                // Typically key name is roles_name_guard_name_unique
                $table->dropUnique(['name', 'guard_name']);
                $table->unique([$foreignKey, 'name', 'guard_name']);
            });
        }

        // 2. Update Model Has Permissions Table
        if (Schema::hasTable($tableNames['model_has_permissions']) && !Schema::hasColumn($tableNames['model_has_permissions'], $foreignKey)) {
            Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($foreignKey) {
                $table->unsignedBigInteger($foreignKey)->nullable()->after('permission_id');
                $table->index($foreignKey, 'model_has_permissions_organization_id_index');
                
                // Note: Not updating Primary Key to avoid data conflicts with existing global permissions.
                // Assuming application logic handles uniqueness or we accept soft duplicates for now.
            });
        }

        // 3. Update Model Has Roles Table
        if (Schema::hasTable($tableNames['model_has_roles']) && !Schema::hasColumn($tableNames['model_has_roles'], $foreignKey)) {
            Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($foreignKey) {
                $table->unsignedBigInteger($foreignKey)->nullable()->after('role_id');
                $table->index($foreignKey, 'model_has_roles_organization_id_index');
                
                // Note: Not updating Primary Key for safety.
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');
        $foreignKey = 'organization_id';

        if (Schema::hasTable($tableNames['roles'])) {
            Schema::table($tableNames['roles'], function (Blueprint $table) use ($foreignKey) {
                $table->dropUnique([$foreignKey, 'name', 'guard_name']);
                $table->unique(['name', 'guard_name']);
                $table->dropColumn($foreignKey);
            });
        }

        if (Schema::hasTable($tableNames['model_has_permissions'])) {
            Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($foreignKey) {
                $table->dropColumn($foreignKey);
            });
        }

        if (Schema::hasTable($tableNames['model_has_roles'])) {
            Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($foreignKey) {
                $table->dropColumn($foreignKey);
            });
        }
    }
};
