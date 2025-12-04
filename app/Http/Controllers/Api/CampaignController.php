<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignItem;
use App\Models\Screen;
use App\Models\Media;
use App\Services\WalletService;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class CampaignController extends Controller
{
    protected $walletService;
    protected $pricingService;

    public function __construct(WalletService $walletService, PricingService $pricingService)
    {
        $this->walletService = $walletService;
        $this->pricingService = $pricingService;
    }

    /**
     * Store a newly created campaign in storage.
     */
    public function store(Request $request)
    {
        // 1. Validasi Input Request
        $request->validate([
            'name'        => 'required|string|max:255',
            'start_date'  => 'required|date|after_or_equal:today',
            'end_date'    => 'required|date|after_or_equal:start_date',
            'media_id'    => 'required|exists:media,id',
            'screens'     => 'required|array|min:1',
            'screens.*.id' => 'required|exists:screens,id',
            'screens.*.plays_per_day' => 'required|integer|min:1',
        ]);

        $user = $request->user();
        $startDate = Carbon::parse($request->start_date);
        $endDate   = Carbon::parse($request->end_date);
        $days      = $startDate->diffInDays($endDate) + 1;

        // 2. Validasi Media
        $media = Media::where('id', $request->media_id)
            ->where('user_id', $user->id)
            ->first();

        // A. Cek Keberadaan
        if (!$media) {
            return response()->json(['message' => 'Media not found or not owned by user.'], 422);
        }

        // B. Cek Status Teknis (Processing)
        if ($media->status !== 'completed') {
            return response()->json(['message' => 'Media is still processing/failed. Please wait.'], 422);
        }

        // C. [BARU] Cek Status Moderasi (Admin Approval)
        if ($media->moderation_status !== 'approved') {
            return response()->json([
                'message' => 'Media belum disetujui oleh Admin.',
                'moderation_status' => $media->moderation_status,
                'reason' => $media->moderation_notes // Tampilkan alasan penolakan jika ada
            ], 422);
        }

        try {
            // 3. Database Transaction
            $campaign = DB::transaction(function () use ($user, $request, $media, $startDate, $endDate, $days) {
                
                $totalCost = 0;
                $campaignItemsData = [];

                foreach ($request->screens as $item) {
                    // Lock screen row
                    $screen = Screen::with('hotel')
                                ->where('id', $item['id'])
                                ->lockForUpdate() 
                                ->first();

                    // Validasi Screen Aktif
                    if (!$screen->is_active) {
                        throw ValidationException::withMessages([
                            'screens' => "Screen '{$screen->name}' sedang tidak aktif."
                        ]);
                    }

                    // Validasi Durasi Video vs Screen Rule
                    if ($media->duration > $screen->max_duration_sec) {
                        throw ValidationException::withMessages([
                            'media' => "Durasi media ({$media->duration}s) melebihi batas screen '{$screen->name}' ({$screen->max_duration_sec}s)."
                        ]);
                    }

                    // 4. Cek Kapasitas (Inventory Check)
                    $existingUsage = CampaignItem::where('screen_id', $screen->id)
                        ->whereHas('campaign', function ($query) use ($startDate, $endDate) {
                            $query->where('status', 'active')
                                  ->where(function ($q) use ($startDate, $endDate) {
                                      // Overlap check: (StartA <= EndB) and (EndA >= StartB)
                                      $q->whereDate('start_date', '<=', $endDate)
                                        ->whereDate('end_date', '>=', $startDate);
                                  });
                        })
                        ->sum('plays_per_day');

                    $requestedPlays = $item['plays_per_day'];
                    
                    if (($existingUsage + $requestedPlays) > $screen->max_plays_per_day) {
                        $sisa = max(0, $screen->max_plays_per_day - $existingUsage);
                        throw ValidationException::withMessages([
                            'screens' => "Screen '{$screen->name}' penuh. Sisa slot: {$sisa}."
                        ]);
                    }

                    // 5. Hitung Biaya (PricingService)
                    $itemTotalCost = $this->pricingService->calculatePrice($screen, $days, $requestedPlays);
                    $priceSnapshot = $itemTotalCost / ($requestedPlays * $days); // Harga satuan

                    $totalCost += $itemTotalCost;

                    $campaignItemsData[] = [
                        'screen_id'      => $screen->id,
                        'media_id'       => $media->id,
                        'plays_per_day'  => $requestedPlays,
                        'price_per_play' => $priceSnapshot,
                        'pricing_type'   => 'dynamic',
                    ];
                }

                // 6. Potong Saldo Wallet
                if (!$this->walletService->debitBalance($user, $totalCost)) {
                    throw ValidationException::withMessages([
                        'balance' => 'Saldo Wallet tidak mencukupi. Total tagihan: ' . number_format($totalCost)
                    ]);
                }

                // 7. Simpan Data Campaign
                $campaign = Campaign::create([
                    'user_id'    => $user->id,
                    'name'       => $request->name,
                    'start_date' => $startDate,
                    'end_date'   => $endDate,
                    'total_cost' => $totalCost,
                    'status'     => 'active',
                    'moderation_status' => 'approved', // Campaign otomatis approved jika medianya sudah approved (untuk fase ini)
                ]);

                $campaign->items()->createMany($campaignItemsData);

                return $campaign;
            });

            return response()->json([
                'status' => 'success', 
                'message' => 'Campaign created successfully.', 
                'data' => $campaign->load('items.screen'),
            ], 201);

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Transaction failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * List campaigns for the authenticated user.
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
     * Show specific campaign details.
     */
    public function show(Request $request, $id)
    {
        $campaign = Campaign::with(['items.screen', 'items.media'])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json(['data' => $campaign]);
    }
}