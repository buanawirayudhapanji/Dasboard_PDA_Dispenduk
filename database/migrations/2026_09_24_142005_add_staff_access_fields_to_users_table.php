<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nik', 16)->nullable()->unique()->after('name');
            $table->string('role')->default('staff')->after('password');
            $table->boolean('is_active')->default(true)->after('role');
        });

        DB::table('users')->where('email', 'buanawirayudhapanji@gmail.com')->update([
            'nik' => '3508153103040002',
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['nik']);
            $table->dropColumn(['nik', 'role', 'is_active']);
        });
    }
};
