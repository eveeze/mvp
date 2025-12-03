<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\Screen;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ScreenController extends Controller
{
    /**
     * List semua screen untuk satu hotel.
     * GET /api/hotels/{hotel}/screens
     */
    public function index(Hotel $hotel)
    {
        $screens = $hotel->screens()
            ->orderByDesc('is_online')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $screens,
        ]);
    }

    /**
     * Create screen baru untuk hotel tertentu.
     * POST /api/hotels/{hotel}/screens
     */
    public function store(Request $request, Hotel $hotel)
    {
        $validated = $this->validatePayload($request);

        $screen = $hotel->screens()->create($validated);

        return response()->json([
            'data' => $screen,
        ], 201);
    }

    /**
     * Detail satu screen milik hotel.
     * GET /api/hotels/{hotel}/screens/{screen}
     */
    public function show(Hotel $hotel, Screen $screen)
    {
        $this->ensureBelongsToHotel($hotel, $screen);

        return response()->json([
            'data' => $screen,
        ]);
    }

    /**
     * Update screen milik hotel.
     * PUT /api/hotels/{hotel}/screens/{screen}
     */
    public function update(Request $request, Hotel $hotel, Screen $screen)
    {
        $this->ensureBelongsToHotel($hotel, $screen);

        $validated = $this->validatePayload($request, $screen);

        $screen->update($validated);

        return response()->json([
            'data' => $screen->fresh(),
        ]);
    }

    /**
     * Hapus screen dari hotel.
     * DELETE /api/hotels/{hotel}/screens/{screen}
     */
    public function destroy(Hotel $hotel, Screen $screen)
    {
        $this->ensureBelongsToHotel($hotel, $screen);

        $screen->delete();

        return response()->json([
            'message' => 'Screen deleted.',
        ]);
    }

    /**
     * Validasi payload untuk create/update.
     */
    protected function validatePayload(Request $request, ?Screen $screen = null): array
    {
        $screenId = $screen?->id;

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],

            'code' => [
                'nullable',
                'string',
                'max:100',
                // unik, tapi boleh sama untuk screen yang sedang di-update
                Rule::unique('screens', 'code')->ignore($screenId),
            ],

            'location' => ['nullable', 'string', 'max:255'],

            'resolution_width'  => ['nullable', 'integer', 'min:1', 'max:10000'],
            'resolution_height' => ['nullable', 'integer', 'min:1', 'max:10000'],

            'orientation' => ['nullable', 'string', Rule::in(['landscape', 'portrait'])],

            'is_online' => ['nullable', 'boolean'],

            'allowed_categories'   => ['nullable', 'array'],
            'allowed_categories.*' => ['string', 'max:50'],
        ]);
    }

    /**
     * Pastikan screen memang milik hotel yang diminta.
     * Kalau tidak, balikin 404 biar aman.
     */
    protected function ensureBelongsToHotel(Hotel $hotel, Screen $screen): void
    {
        if ($screen->hotel_id !== $hotel->id) {
            abort(404);
        }
    }
}
