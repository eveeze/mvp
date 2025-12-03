<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use Illuminate\Http\Request;

class HotelController extends Controller
{
    public function index()
    {
        $hotels = Hotel::query()->latest()->get();

        return response()->json([
            'data' => $hotels,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'city'           => ['nullable', 'string', 'max:255'],
            'address'        => ['nullable', 'string', 'max:500'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_phone'  => ['nullable', 'string', 'max:50'],
        ]);

        $hotel = Hotel::create($validated);

        return response()->json([
            'data' => $hotel,
        ], 201);
    }

    public function show(int $id)
    {
        $hotel = Hotel::findOrFail($id);

        return response()->json([
            'data' => $hotel,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $hotel = Hotel::findOrFail($id);

        $validated = $request->validate([
            'name'           => ['sometimes', 'required', 'string', 'max:255'],
            'city'           => ['nullable', 'string', 'max:255'],
            'address'        => ['nullable', 'string', 'max:500'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_phone'  => ['nullable', 'string', 'max:50'],
        ]);

        $hotel->update($validated);

        return response()->json([
            'data' => $hotel->fresh(),
        ]);
    }

    public function destroy(int $id)
    {
        $hotel = Hotel::findOrFail($id);

        $hotel->delete();

        return response()->json([
            'message' => 'Hotel deleted.',
        ]);
    }
}
