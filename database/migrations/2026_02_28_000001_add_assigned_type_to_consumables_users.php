<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add assigned_type column to consumables_users table to support
     * polymorphic checkout (to users, assets, or locations).
     * Follows the same pattern as the accessories_checkout table.
     */
    public function up(): void
    {
        Schema::table('consumables_users', function (Blueprint $table) {
            $table->string('assigned_type')->nullable()->after('assigned_to');
        });

        // Set existing records to User type since all prior checkouts were to users
        DB::update(
            'UPDATE ' . DB::getTablePrefix() . 'consumables_users SET assigned_type = ? WHERE assigned_type IS NULL',
            ['App\\Models\\User']
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consumables_users', function (Blueprint $table) {
            $table->dropColumn('assigned_type');
        });
    }
};
