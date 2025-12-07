<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Update Hotels (Tambah Bintang)
        Schema::table('hotels', function (Blueprint $table) {
            // Rating bintang 1-5 (Nullable karena mungkin ada lokasi non-hotel)
            $table->unsignedTinyInteger('star_rating')->nullable()->after('address');
        });

        // 2. Update Campaigns (Moderasi)
        Schema::table('campaigns', function (Blueprint $table) {
            // Status moderasi konten (Flow: pending -> approved -> active)
            $table->string('moderation_status')
                  ->default('pending_review')
                  ->after('status')
                  ->comment('pending_review, approved, rejected, revision_needed');
                  
            // Catatan revisi jika ditolak
            $table->text('moderation_notes')->nullable()->after('moderation_status');
        });
        
        // 3. Update Campaign Items (Snapshot Harga & Paket)
        Schema::table('campaign_items', function (Blueprint $table) {
            // Jika nanti ada sistem paket, kita butuh tahu item ini ikut paket apa
            // Tapi 'price_per_play' yang sudah ada sebenarnya sudah cukup sebagai snapshot harga
            $table->string('pricing_type')->default('dynamic')->after('price_per_play')
                  ->comment('rate_card, override_screen, override_hotel, dynamic');
        });
    }

    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn('star_rating');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['moderation_status', 'moderation_notes']);
        });
        
        Schema::table('campaign_items', function (Blueprint $table) {
            $table->dropColumn('pricing_type');
        });
    }
};