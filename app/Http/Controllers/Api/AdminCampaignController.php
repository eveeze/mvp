<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\WalletService;
use Illuminate\Http\Request;

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
            $query->where('status', 'pending_review');
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->paginate(20)
        ]);
    }

    public function approve(string $id)
    {
        $campaign = Campaign::findOrFail($id);
        
        if ($campaign->status !== 'pending_review') {
            return response()->json(['message' => 'Campaign not in pending status.'], 400);
        }

        $campaign->update(['status' => 'active']);

        return response()->json(['status' => 'success', 'message' => 'Campaign Approved & Active.']);
    }

    public function reject(Request $request, string $id)
    {
        $request->validate(['reason' => 'required|string']);

        $campaign = Campaign::findOrFail($id);

        if ($campaign->status !== 'pending_review') {
            return response()->json(['message' => 'Campaign not in pending status.'], 400);
        }

        // Refund Saldo
        $this->walletService->creditBalance(
            $campaign->user, 
            $campaign->total_cost, 
            "Refund Rejected Campaign: {$campaign->name}", 
            $campaign
        );

        $campaign->update([
            'status' => 'rejected',
            // Anda bisa menambahkan kolom 'rejection_reason' di tabel campaign nanti jika perlu
        ]);

        return response()->json(['status' => 'success', 'message' => 'Campaign Rejected & Refunded.']);
    }
}