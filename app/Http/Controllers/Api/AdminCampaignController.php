<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\WalletService;
use App\Enums\CampaignStatus;
use App\Http\Resources\CampaignResource;
use App\Mail\CampaignApproved; // Import
use App\Mail\CampaignRejected; // Import
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail; // Import

class AdminCampaignController extends Controller
{
    protected $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    public function index(Request $request)
    {
        $query = Campaign::with('user')->latest();
        if ($request->has('status')) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', CampaignStatus::PENDING_REVIEW);
        }
        return CampaignResource::collection($query->paginate(20));
    }

    public function approve(string $id)
    {
        $campaign = Campaign::findOrFail($id);
        
        if ($campaign->status !== CampaignStatus::PENDING_REVIEW) {
            return response()->json(['message' => 'Campaign not in pending status.'], 400);
        }

        $campaign->update(['status' => CampaignStatus::ACTIVE]);

        // [KIRIM EMAIL]
        Mail::to($campaign->user->email)->queue(new CampaignApproved($campaign));

        return response()->json(['status' => 'success', 'message' => 'Campaign Approved & Active.']);
    }

    public function reject(Request $request, string $id)
    {
        $request->validate(['reason' => 'required|string']);
        $campaign = Campaign::findOrFail($id);

        if ($campaign->status !== CampaignStatus::PENDING_REVIEW) {
            return response()->json(['message' => 'Campaign not in pending status.'], 400);
        }

        $this->walletService->creditBalance($campaign->user, $campaign->total_cost, "Refund Rejected: {$campaign->name}", $campaign);

        $campaign->update(['status' => CampaignStatus::REJECTED]);

        // [KIRIM EMAIL]
        Mail::to($campaign->user->email)->queue(new CampaignRejected($campaign, $request->reason));

        return response()->json(['status' => 'success', 'message' => 'Campaign Rejected & Refunded.']);
    }
}