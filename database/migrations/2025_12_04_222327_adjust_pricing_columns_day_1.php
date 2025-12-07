<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah 'price_override' di Hotels jika belum ada
        Schema::table('hotels', function (Blueprint $table) {
            if (!Schema::hasColumn('hotels', 'price_override')) {
                $table->decimal('price_override', 15, 2)->nullable()->after('star_rating');
            }
        });

        // 2. Ubah 'price_per_play' di Screens jadi Nullable
        Schema::table('screens', function (Blueprint $table) {
            // Kita ubah kolom yang sudah ada
            $table->decimal('price_per_play', 12, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            if (Schema::hasColumn('hotels', 'price_override')) {
                $table->dropColumn('price_override');
            }
        });

        Schema::table('screens', function (Blueprint $table) {
            // Kembalikan ke tidak boleh null (default 0)
            $table->decimal('price_per_play', 12, 2)->default(0)->change();
        });
    }
};