<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use App\Models\ImpressionLog;

class ProcessImpressionBatch extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'impression:process-batch';

    /**
     * The console command description.
     */
    protected $description = 'Process impression logs from Redis buffer to Database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $batchSize = 1000; // Ambil 1000 data sekaligus
        $redisKey = 'impression_logs_queue';
        
        $this->info("Starting batch processing for impressions...");

        // Ambil data dari Redis (LPOP)
        // Kita loop sampai Redis kosong atau limit tercapai
        $count = 0;
        $batchData = [];

        // Mengambil item dari Redis satu per satu sampai batchSize terpenuhi
        // (Redis Pipeline bisa lebih cepat, tapi lpop aman untuk konsistensi)
        for ($i = 0; $i < $batchSize; $i++) {
            $item = Redis::lpop($redisKey);
            if (!$item) break; // Redis kosong

            $batchData[] = json_decode($item, true);
            $count++;
        }

        if (empty($batchData)) {
            $this->info("No impressions to process.");
            return;
        }

        // Bulk Insert ke Database (1 Query untuk 1000 data!)
        try {
            ImpressionLog::insert($batchData);
            $this->info("Successfully inserted {$count} impression logs.");
        } catch (\Exception $e) {
            $this->error("Failed to insert batch: " . $e->getMessage());
            
            // [CRITICAL] Kembalikan data ke Redis jika DB gagal (Reliability)
            // Push kembali ke head list agar diproses lagi nanti
            foreach (array_reverse($batchData) as $item) {
                Redis::lpush($redisKey, json_encode($item));
            }
            $this->error("Data pushed back to Redis queue.");
        }
    }
}