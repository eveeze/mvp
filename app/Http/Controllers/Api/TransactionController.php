<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource; // [NEW]
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $transactions = $request->user()->wallet
            ->transactions()
            ->latest()
            ->paginate(15);

        return TransactionResource::collection($transactions);
    }
}