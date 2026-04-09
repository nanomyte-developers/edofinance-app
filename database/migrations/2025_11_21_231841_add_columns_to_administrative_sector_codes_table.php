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
        Schema::table('administrative_sector_codes', function (Blueprint $table) {
            // Add the new columns after 'code'
            $table->string('type', 50)->nullable()->after('code')->comment('e.g., Ministry, Agency, Sector');
            $table->string('initials', 10)->nullable()->after('type')->comment('Short acronym (e.g., GH, MME)');
            $table->string('location')->nullable()->after('initials')->comment('Geographical location of the administrative unit');
            $table->boolean('status')->default(true)->after('location')->comment('Active/Inactive status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('administrative_sector_codes', function (Blueprint $table) {
            $table->dropColumn(['type', 'initials', 'location', 'status']);
        });
    }
};
