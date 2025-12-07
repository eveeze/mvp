<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignItem;
use App\Models\Screen;
use App\Models\Media;
use App\Services\WalletService;
use App\Services\PricingService;
use App\Enums\CampaignStatus; // [NEW] Enum
use App\Enums\ModerationStatus; // [NEW] Enum
use App\Http\Resources\CampaignResource; // [NEW] Resource
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
            ->first();

        if (!$media) return response()->json(['message' => 'Media not found.'], 422);
        if ($media->status !== 'completed') return response()->json(['message' => 'Media processing not finished.'], 422);
        
        // [REFACTOR] Gunakan Enum untuk perbandingan
        if ($media->moderation_status !== ModerationStatus::APPROVED) {
            return response()->json([
                'message' => 'Media belum disetujui oleh Admin.',
                'status' => $media->moderation_status,
                'notes' => $media->moderation_notes
            ], 422);
        }

        try {
            $campaign = DB::transaction(function () use ($user, $request, $media, $startDate, $endDate, $days) {
                
                $totalCost = 0;
                $campaignItemsData = [];

                foreach ($request->screens as $item) {
                    $screen = Screen::with('hotel')->where('id', $item['id'])->lockForUpdate()->first();

                    if (!$screen->is_active) throw ValidationException::withMessages(['screens' => "Screen inactive."]);
                    if ($media->duration > $screen->max_duration_sec) throw ValidationException::withMessages(['media' => "Media too long."]);

                    // [REFACTOR] Gunakan Enum di query
                    $existingUsage = CampaignItem::where('screen_id', $screen->id)
                        ->whereHas('campaign', function ($query) use ($startDate, $endDate) {
                            $query->whereIn('status', [CampaignStatus::ACTIVE, CampaignStatus::PENDING_REVIEW])
                                  ->where(function ($q) use ($startDate, $endDate) {
                                      $q->whereDate('start_date', '<=', $endDate)
                                        ->whereDate('end_date', '>=', $startDate);
                                  });
                        })
                        ->sum('plays_per_day');

                    $requestedPlays = $item['plays_per_day'];
                    if (($existingUsage + $requestedPlays) > $screen->max_plays_per_day) {
                        $sisa = max(0, $screen->max_plays_per_day - $existingUsage);
                        throw ValidationException::withMessages(['screens' => "Screen '{$screen->name}' penuh. Sisa slot: {$sisa}."]);
                    }

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

                if (!$this->walletService->debitBalance($user, $totalCost, "Booking Campaign", null)) {
                     throw ValidationException::withMessages(['balance' => 'Saldo Wallet tidak mencukupi.']);
                }

                // [REFACTOR] Set status menggunakan Enum
                $campaign = Campaign::create([
                    'user_id'    => $user->id,
                    'name'       => $request->name,
                    'start_date' => $startDate,
                    'end_date'   => $endDate,
                    'total_cost' => $totalCost,
                    'status'     => CampaignStatus::PENDING_REVIEW,
                    'moderation_status' => ModerationStatus::APPROVED, // Asumsi media sudah approved
                ]);

                $campaign->items()->createMany($campaignItemsData);
                
                return $campaign;
            });

            // [REFACTOR] Return API Resource
            return new CampaignResource($campaign->load('items.screen'));

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
            
        // [REFACTOR] Return Collection Resource
        return CampaignResource::collection($campaigns);
    }

    public function show(Request $request, $id)
    {
        $campaign = Campaign::with(['items.screen', 'items.media'])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);
            
        return new CampaignResource($campaign);
    }
}