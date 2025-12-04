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

    public function store(Request $request)
    {
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

        $media = Media::where('id', $request->media_id)
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->first();

        if (!$media) {
            return response()->json(['message' => 'Media invalid/not ready.'], 422);
        }

        try {
            $campaign = DB::transaction(function () use ($user, $request, $media, $startDate, $endDate, $days) {
                
                $totalCost = 0;
                $campaignItemsData = [];

                foreach ($request->screens as $item) {
                    $screen = Screen::with('hotel')
                                ->where('id', $item['id'])
                                ->lockForUpdate() 
                                ->first();

                    if (!$screen->is_active) {
                        throw ValidationException::withMessages(['screens' => "Screen {$screen->name} inactive."]);
                    }

                    if ($media->duration > $screen->max_duration_sec) {
                        throw ValidationException::withMessages(['media' => "Media duration too long."]);
                    }

                    // --- CEK KAPASITAS / OVERBOOKING ---
                    // Pastikan menggunakan whereDate untuk akurasi tanggal
                    $existingUsage = CampaignItem::where('screen_id', $screen->id)
                        ->whereHas('campaign', function ($query) use ($startDate, $endDate) {
                            $query->where('status', 'active')
                                  ->where(function ($q) use ($startDate, $endDate) {
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

                    // Hitung Harga
                    $itemTotalCost = $this->pricingService->calculatePrice($screen, $days, $requestedPlays);
                    $priceSnapshot = $itemTotalCost / ($requestedPlays * $days);

                    $totalCost += $itemTotalCost;

                    $campaignItemsData[] = [
                        'screen_id'      => $screen->id,
                        'media_id'       => $media->id,
                        'plays_per_day'  => $requestedPlays,
                        'price_per_play' => $priceSnapshot,
                        'pricing_type'   => 'dynamic',
                    ];
                }

                // --- CEK & POTONG SALDO (FIX LOGIC) ---
                // Sebelumnya hanya dipanggil tanpa dicek hasilnya. Sekarang dicek.
                $balanceOk = $this->walletService->debitBalance($user, $totalCost);
                
                if (!$balanceOk) {
                    throw ValidationException::withMessages([
                        'balance' => 'Saldo Wallet tidak mencukupi. Total tagihan: ' . number_format($totalCost)
                    ]);
                }
                // --------------------------------------

                $campaign = Campaign::create([
                    'user_id'    => $user->id,
                    'name'       => $request->name,
                    'start_date' => $startDate,
                    'end_date'   => $endDate,
                    'total_cost' => $totalCost,
                    'status'     => 'active', 
                ]);

                $campaign->items()->createMany($campaignItemsData);

                return $campaign;
            });

            return response()->json([
                'status' => 'success', 
                'message' => 'Campaign created.', 
                'data' => $campaign
            ], 201);

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function index(Request $request)
    {
        $campaigns = Campaign::with(['items.screen', 'items.media'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10);
        return response()->json(['data' => $campaigns]);
    }

    public function show(Request $request, $id)
    {
        $campaign = Campaign::with(['items.screen', 'items.media'])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);
        return response()->json(['data' => $campaign]);
    }
}