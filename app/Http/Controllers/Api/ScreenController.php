<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\Screen;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ScreenController extends Controller
{
    /**
     * List screen di hotel tertentu dengan pagination.
     * GET /api/hotels/{hotel}/screens
     */
    public function index(Request $request, Hotel $hotel)
    {
        // Query builder
        $query = $hotel->screens()
            ->orderByDesc('is_online') // Prioritaskan layar online di atas
            ->orderBy('name');

        // Filter opsional: Hanya tampilkan yang aktif (Ready for Ads)
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Production: Gunakan pagination (10 per halaman)
        $screens = $query->paginate(10);

        return response()->json([
            'status' => 'success',
            'data' => $screens,
        ]);
    }

    /**
     * Membuat screen baru.
     * POST /api/hotels/{hotel}/screens
     */
    public function store(Request $request, Hotel $hotel)
    {
        $validated = $this->validatePayload($request);

        // Buat screen via relasi agar hotel_id otomatis terisi
        $screen = $hotel->screens()->create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Screen created successfully.',
            'data' => $screen,
        ], Response::HTTP_CREATED);
    }

    /**
     * Detail screen.
     * GET /api/hotels/{hotel}/screens/{screen}
     */
    public function show(Hotel $hotel, Screen $screen)
    {
        $this->ensureBelongsToHotel($hotel, $screen);

        return response()->json([
            'status' => 'success',
            'data' => $screen,
        ]);
    }

    /**
     * Update screen.
     * PUT /api/hotels/{hotel}/screens/{screen}
     */
    public function update(Request $request, Hotel $hotel, Screen $screen)
    {
        $this->ensureBelongsToHotel($hotel, $screen);

        // Kirim object screen saat ini untuk pengecualian unique validation
        $validated = $this->validatePayload($request, $screen);

        $screen->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Screen updated successfully.',
            'data' => $screen->fresh(),
        ]);
    }

    /**
     * Hapus screen (Soft Delete).
     * DELETE /api/hotels/{hotel}/screens/{screen}
     */
    public function destroy(Hotel $hotel, Screen $screen)
    {
        $this->ensureBelongsToHotel($hotel, $screen);

        $screen->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Screen deleted successfully.',
        ]);
    }

    /**
     * Pusat validasi agar tidak menulis ulang rule yang sama.
     */
    protected function validatePayload(Request $request, ?Screen $screen = null): array
    {
        $screenId = $screen?->id;

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            
            // Code (Device ID) harus unik secara global di tabel screens
            'code' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('screens', 'code')->ignore($screenId),
            ],

            'location' => ['nullable', 'string', 'max:255'],

            'resolution_width'  => ['required', 'integer', 'min:100', 'max:10000'],
            'resolution_height' => ['required', 'integer', 'min:100', 'max:10000'],
            'orientation'       => ['required', Rule::in(['landscape', 'portrait'])],

            // Validasi uang/harga
            'price_per_play' => ['required', 'numeric', 'min:0'],

            'is_active' => ['boolean'],
            // is_online biasanya diupdate oleh device/IoT, tapi admin boleh override manual jika perlu
            'is_online' => ['boolean'], 

            'allowed_categories'   => ['nullable', 'array'],
            'allowed_categories.*' => ['string', 'max:50'],
        ]);
    }

    /**
     * Security Check: Pastikan ID Screen match dengan ID Hotel di URL.
     */
    protected function ensureBelongsToHotel(Hotel $hotel, Screen $screen): void
    {
        if ($screen->hotel_id !== $hotel->id) {
            abort(404, 'Screen not found in this hotel.');
        }
    }
}