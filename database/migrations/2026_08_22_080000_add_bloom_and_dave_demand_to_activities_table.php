<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->string('bloom_demand', 32)->nullable()->after('difficulty');
            $table->string('dave_demand', 32)->nullable()->after('bloom_demand');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn(['bloom_demand', 'dave_demand']);
        });
    }
};
