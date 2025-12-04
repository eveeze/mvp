<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class HotelController extends Controller
{
    /**
     * Menampilkan daftar hotel dengan fitur pencarian dan pagination.
     */
    public function index(Request $request)
    {
        $query = Hotel::query();

        // Fitur 1: Pencarian berdasarkan nama
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Fitur 2: Filter status aktif (Opsional)
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Production Fix: Gunakan paginate, bukan get() untuk performa
        $hotels = $query->latest()->paginate(10);

        return response()->json([
            'status' => 'success',
            'data' => $hotels,
        ]);
    }

    /**
     * Menyimpan data hotel baru.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            // FIX: Validasi unique mencegah duplikasi nama di level aplikasi
            'name'           => ['required', 'string', 'max:255', 'unique:hotels,name'],
            'city'           => ['nullable', 'string', 'max:255'],
            'address'        => ['nullable', 'string', 'max:500'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_phone'  => ['nullable', 'string', 'max:50'],
            'is_active'      => ['boolean'],
        ]);

        $hotel = Hotel::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Hotel created successfully.',
            'data' => $hotel,
        ], Response::HTTP_CREATED);
    }

    /**
     * Menampilkan detail hotel.
     */
    public function show(string $id)
    {
        $hotel = Hotel::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $hotel,
        ]);
    }

    /**
     * Memperbarui data hotel.
     */
    public function update(Request $request, string $id)
    {
        $hotel = Hotel::findOrFail($id);

        $validated = $request->validate([
            // FIX: Validasi unique, tapi KECUALIKAN id hotel ini sendiri
            'name'           => [
                'sometimes', 
                'required', 
                'string', 
                'max:255', 
                Rule::unique('hotels', 'name')->ignore($hotel->id)
            ],
            'city'           => ['nullable', 'string', 'max:255'],
            'address'        => ['nullable', 'string', 'max:500'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_phone'  => ['nullable', 'string', 'max:50'],
            'is_active'      => ['boolean'],
        ]);

        $hotel->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Hotel updated successfully.',
            'data' => $hotel->fresh(),
        ]);
    }

    /**
     * Menghapus hotel (Soft Delete).
     */
    public function destroy(string $id)
    {
        $hotel = Hotel::findOrFail($id);
        
        // Data tidak benar-benar hilang, hanya ditandai terhapus (Soft Delete)
        $hotel->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Hotel deleted successfully.',
        ]);
    }
}