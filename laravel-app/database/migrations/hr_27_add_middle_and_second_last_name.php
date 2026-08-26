<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('middle_name')->nullable()->after('first_name');
            $table->string('second_last_name')->nullable()->after('last_name');
        });

        Schema::table('residents', function (Blueprint $table) {
            $table->string('middle_name')->nullable()->after('first_name');
            $table->string('second_last_name')->nullable()->after('last_name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['middle_name', 'second_last_name']);
        });

        Schema::table('residents', function (Blueprint $table) {
            $table->dropColumn(['middle_name', 'second_last_name']);
        });
    }
};
