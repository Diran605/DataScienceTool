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
        Schema::table('runs', function (Blueprint $table) {
            $table->string('current_file_path')->nullable()->after('source_file_path');
        });

        Schema::table('run_steps', function (Blueprint $table) {
            $table->string('input_file_path')->nullable()->after('step_type');
            $table->string('output_file_path')->nullable()->after('input_file_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('run_steps', function (Blueprint $table) {
            $table->dropColumn(['input_file_path', 'output_file_path']);
        });

        Schema::table('runs', function (Blueprint $table) {
            $table->dropColumn('current_file_path');
        });
    }
};
