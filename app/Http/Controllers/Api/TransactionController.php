<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // [FIX] Cek apakah user punya wallet
        if (!$user->wallet) {
            // Return array kosong jika belum ada wallet
            return TransactionResource::collection([]);
        }

        $transactions = $user->wallet
            ->transactions()
            ->latest()
            ->paginate(15);

        return TransactionResource::collection($transactions);
    }
}