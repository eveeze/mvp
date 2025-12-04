<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Media;
use App\Models\Deposit;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Statistik Ringkas untuk Advertiser
     */
    public function stats(Request $request)
    {
        $user = $request->user();

        $stats = [
            'wallet_balance' => $user->wallet->balance ?? 0,
            'total_spent'    => Campaign::where('user_id', $user->id)->sum('total_cost'),
            'active_campaigns' => Campaign::where('user_id', $user->id)->where('status', 'active')->count(),
            'total_campaigns'  => Campaign::where('user_id', $user->id)->count(),
            'uploaded_videos'  => Media::where('user_id', $user->id)->count(),
            'pending_deposits' => Deposit::where('user_id', $user->id)->where('status', 'pending')->count(),
        ];

        return response()->json([
            'status' => 'success',
            'data' => $stats
        ]);
    }

    /**
     * Update Profile User
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            // Email skip dulu untuk MVP, ribet verifikasinya
        ]);

        $user->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Profile updated.',
            'data' => $user
        ]);
    }
}