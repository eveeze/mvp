<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\Screen;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ScreenController extends Controller
{
    public function index(Request $request, Hotel $hotel)
    {
        $query = $hotel->screens()->orderByDesc('is_online')->orderBy('name');

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return response()->json(['status' => 'success', 'data' => $query->paginate(10)]);
    }

    public function store(Request $request, Hotel $hotel)
    {
        $validated = $this->validatePayload($request);
        $screen = $hotel->screens()->create($validated);
        return response()->json(['status' => 'success', 'data' => $screen], 201);
    }

    public function show(Hotel $hotel, Screen $screen)
    {
        $this->ensureBelongsToHotel($hotel, $screen);
        return response()->json(['status' => 'success', 'data' => $screen]);
    }

    public function update(Request $request, Hotel $hotel, Screen $screen)
    {
        $this->ensureBelongsToHotel($hotel, $screen);
        $validated = $this->validatePayload($request, $screen);
        $screen->update($validated);
        return response()->json(['status' => 'success', 'data' => $screen->fresh()]);
    }

    public function destroy(Hotel $hotel, Screen $screen)
    {
        $this->ensureBelongsToHotel($hotel, $screen);
        $screen->delete();
        return response()->json(['status' => 'success', 'message' => 'Screen deleted.']);
    }

    protected function validatePayload(Request $request, ?Screen $screen = null): array
    {
        $screenId = $screen?->id;

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100', Rule::unique('screens', 'code')->ignore($screenId)],
            'location' => ['nullable', 'string', 'max:255'],
            'resolution_width'  => ['required', 'integer', 'min:100', 'max:10000'],
            'resolution_height' => ['required', 'integer', 'min:100', 'max:10000'],
            'orientation'       => ['required', Rule::in(['landscape', 'portrait'])],
            
            // [FIX] Boleh null agar ikut harga hotel
            'price_per_play'    => ['nullable', 'numeric', 'min:0'],
            
            'max_plays_per_day' => ['required', 'integer', 'min:1'],
            'max_duration_sec'  => ['required', 'integer', 'min:5'],
            'is_active' => ['boolean'],
            'is_online' => ['boolean'], 
            'allowed_categories'   => ['nullable', 'array'],
            'allowed_categories.*' => ['string', 'max:50'],
        ]);
    }

    protected function ensureBelongsToHotel(Hotel $hotel, Screen $screen): void
    {
        if ($screen->hotel_id !== $hotel->id) {
            abort(404, 'Screen not found in this hotel.');
        }
    }
}