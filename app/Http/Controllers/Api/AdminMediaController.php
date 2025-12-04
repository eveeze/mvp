<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\Request;

class AdminMediaController extends Controller
{
    /**
     * List media pending review
     */
    public function index(Request $request)
    {
        $query = Media::with('user')->latest();

        if ($request->has('status')) {
            $query->where('moderation_status', $request->status);
        } else {
            $query->where('moderation_status', 'pending');
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->paginate(20)
        ]);
    }

    /**
     * Approve
     */
    public function approve(string $id)
    {
        $media = Media::findOrFail($id);
        $media->update([
            'moderation_status' => 'approved', 
            'moderation_notes' => null
        ]);

        return response()->json(['status' => 'success', 'message' => 'Media approved.']);
    }

    /**
     * Reject
     */
    public function reject(Request $request, string $id)
    {
        $request->validate(['reason' => 'required|string|max:500']);

        $media = Media::findOrFail($id);
        $media->update([
            'moderation_status' => 'rejected',
            'moderation_notes' => $request->reason
        ]);

        return response()->json(['status' => 'success', 'message' => 'Media rejected.']);
    }
}