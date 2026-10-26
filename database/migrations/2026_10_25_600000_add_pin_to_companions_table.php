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
        if (! Schema::hasColumn('companions', 'pin')) {
            Schema::table('companions', function (Blueprint $table) {
                $table->text('pin')->nullable()->after('relationship');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('companions', 'pin')) {
            Schema::table('companions', function (Blueprint $table) {
                $table->dropColumn('pin');
            });
        }
    }
};
