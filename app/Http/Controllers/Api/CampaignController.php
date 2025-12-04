<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignItem;
use App\Models\Screen;
use App\Models\Media;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class CampaignController extends Controller
{
    protected $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    /**
     * Store Campaign Baru (Booking Iklan).
     * Melakukan pengecekan kapasitas, lock saldo, dan atomic transaction.
     */
    public function store(Request $request)
    {
        // 1. Validasi Input Dasar
        $request->validate([
            'name'        => 'required|string|max:255',
            'start_date'  => 'required|date|after_or_equal:today',
            'end_date'    => 'required|date|after_or_equal:start_date',
            'media_id'    => 'required|exists:media,id',
            
            // Array Screen yang dipilih
            'screens'     => 'required|array|min:1',
            'screens.*.id' => 'required|exists:screens,id',
            'screens.*.plays_per_day' => 'required|integer|min:1',
        ]);

        $user = $request->user();
        $startDate = Carbon::parse($request->start_date);
        $endDate   = Carbon::parse($request->end_date);
        
        // Hitung durasi hari (Inclusive, Senin s/d Senin = 1 hari)
        $days = $startDate->diffInDays($endDate) + 1;

        // 2. Validasi & Fetch Media
        // Media harus milik user dan statusnya sudah 'completed' (siap tayang)
        $media = Media::where('id', $request->media_id)
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->first();

        if (!$media) {
            return response()->json([
                'message' => 'Media invalid. Pastikan media milik Anda dan statusnya Completed.'
            ], 422);
        }

        // 3. Mulai Database Transaction (Atomic Operation)
        try {
            $campaign = DB::transaction(function () use ($user, $request, $media, $startDate, $endDate, $days) {
                
                $totalCost = 0;
                $campaignItemsData = [];

                // Loop setiap screen yang direquest untuk pengecekan Inventory
                foreach ($request->screens as $item) {
                    
                    // A. Lock Screen Row (Pessimistic Locking)
                    // Mencegah dua user membooking slot terakhir di detik yang sama
                    $screen = Screen::where('id', $item['id'])
                                ->lockForUpdate() 
                                ->first();

                    // Cek Status Admin
                    if (!$screen->is_active) {
                        throw ValidationException::withMessages([
                            'screens' => "Screen '{$screen->name}' sedang tidak aktif."
                        ]);
                    }

                    // B. Validasi Durasi Video vs Kebijakan Screen
                    // Jangan izinkan video panjang di layar yang butuh rotasi cepat
                    if ($media->duration > $screen->max_duration_sec) {
                        throw ValidationException::withMessages([
                            'media' => "Durasi media Anda ({$media->duration}s) melebihi batas screen '{$screen->name}' ({$screen->max_duration_sec}s)."
                        ]);
                    }

                    // C. CEK KAPASITAS (Inventory Availability Check)
                    // Hitung slot yang SUDAH terpakai di rentang tanggal ini oleh campaign lain
                    $existingUsage = CampaignItem::where('screen_id', $screen->id)
                        ->whereHas('campaign', function ($query) use ($startDate, $endDate) {
                            $query->where('status', 'active')
                                  ->where(function ($q) use ($startDate, $endDate) {
                                      // Logika Overlap Tanggal:
                                      // (Start A <= End B) and (End A >= Start B)
                                      $q->where('start_date', '<=', $endDate)
                                        ->where('end_date', '>=', $startDate);
                                  });
                        })
                        ->sum('plays_per_day');

                    $requestedPlays = $item['plays_per_day'];
                    $availableSlots = $screen->max_plays_per_day - $existingUsage;

                    if ($requestedPlays > $availableSlots) {
                        throw ValidationException::withMessages([
                            'screens' => "Screen '{$screen->name}' penuh/tidak cukup slot di tanggal tersebut. Sisa slot: {$availableSlots}."
                        ]);
                    }

                    // D. Hitung Biaya Item Ini
                    // Rumus: Harga x Jumlah Tayang x Durasi Hari
                    $itemCost = $screen->price_per_play * $requestedPlays * $days;
                    $totalCost += $itemCost;

                    // Siapkan data untuk disimpan nanti
                    $campaignItemsData[] = [
                        'screen_id'      => $screen->id,
                        'media_id'       => $media->id,
                        'plays_per_day'  => $requestedPlays,
                        'price_per_play' => $screen->price_per_play,
                    ];
                }

                // E. Potong Saldo (Melalui WalletService)
                // WalletService juga menggunakan lockForUpdate agar saldo aman
                $balanceOk = $this->walletService->debitBalance($user, $totalCost);
                
                if (!$balanceOk) {
                    throw ValidationException::withMessages([
                        'balance' => 'Saldo Wallet tidak mencukupi. Total tagihan: ' . number_format($totalCost)
                    ]);
                }

                // F. Simpan Campaign Header
                $campaign = Campaign::create([
                    'user_id'    => $user->id,
                    'name'       => $request->name,
                    'start_date' => $startDate,
                    'end_date'   => $endDate,
                    'total_cost' => $totalCost,
                    'status'     => 'active',
                ]);

                // G. Simpan Detail Items
                $campaign->items()->createMany($campaignItemsData);

                return $campaign;
            }); // End Transaction

            return response()->json([
                'status' => 'success',
                'message' => 'Campaign berhasil dibuat & Saldo terpotong.',
                'data' => $campaign->load('items.screen'),
            ], 201);

        } catch (ValidationException $e) {
            // Error validasi (saldo kurang, inventory penuh, dll)
            throw $e;
        } catch (\Exception $e) {
            // Error sistem lain
            return response()->json([
                'status' => 'error',
                'message' => 'Transaction failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * List Campaign milik User.
     */
    public function index(Request $request)
    {
        $campaigns = Campaign::with(['items.screen', 'items.media'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10);

        return response()->json(['data' => $campaigns]);
    }

    /**
     * Detail Campaign.
     */
    public function show(Request $request, $id)
    {
        $campaign = Campaign::with(['items.screen', 'items.media'])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json(['data' => $campaign]);
    }
}