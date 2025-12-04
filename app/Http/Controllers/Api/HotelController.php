<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class HotelController extends Controller
{
    public function index(Request $request)
    {
        $query = Hotel::query();

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $hotels = $query->latest()->paginate(10);

        return response()->json(['status' => 'success', 'data' => $hotels]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:255', 'unique:hotels,name'],
            'city'           => ['nullable', 'string', 'max:255'],
            'address'        => ['nullable', 'string', 'max:500'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_phone'  => ['nullable', 'string', 'max:50'],
            'star_rating'    => ['required', 'integer', 'min:1', 'max:5'], 
            'price_override' => ['nullable', 'numeric', 'min:0'],
            'is_active'      => ['boolean'],
        ]);

        $hotel = Hotel::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Hotel created.',
            'data' => $hotel,
        ], 201);
    }

    public function show(string $id)
    {
        $hotel = Hotel::findOrFail($id);
        return response()->json(['status' => 'success', 'data' => $hotel]);
    }

    public function update(Request $request, string $id)
    {
        $hotel = Hotel::findOrFail($id);

        $validated = $request->validate([
            'name'           => ['sometimes', 'required', 'string', 'max:255', Rule::unique('hotels', 'name')->ignore($hotel->id)],
            'city'           => ['nullable', 'string', 'max:255'],
            'address'        => ['nullable', 'string', 'max:500'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_phone'  => ['nullable', 'string', 'max:50'],
            'star_rating'    => ['sometimes', 'integer', 'min:1', 'max:5'],
            'price_override' => ['nullable', 'numeric', 'min:0'],
            'is_active'      => ['boolean'],
        ]);

        $hotel->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Hotel updated.',
            'data' => $hotel->fresh(),
        ]);
    }

    public function destroy(string $id)
    {
        $hotel = Hotel::findOrFail($id);
        $hotel->delete();
        return response()->json(['status' => 'success', 'message' => 'Hotel deleted.']);
    }
}