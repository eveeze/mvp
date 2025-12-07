<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Enums\ModerationStatus; // [NEW]
use App\Http\Resources\MediaResource; // [NEW]
use Illuminate\Http\Request;

class AdminMediaController extends Controller
{
    public function index(Request $request)
    {
        $query = Media::with('user')->latest();

        if ($request->has('status')) {
            $query->where('moderation_status', $request->status);
        } else {
            // [REFACTOR] Use Enum
            $query->where('moderation_status', ModerationStatus::PENDING);
        }

        return MediaResource::collection($query->paginate(20));
    }

    public function approve(string $id)
    {
        $media = Media::findOrFail($id);
        // [REFACTOR] Use Enum
        $media->update(['moderation_status' => ModerationStatus::APPROVED, 'moderation_notes' => null]);

        return response()->json(['status' => 'success', 'message' => 'Media approved.']);
    }

    public function reject(Request $request, string $id)
    {
        $request->validate(['reason' => 'required|string|max:500']);

        $media = Media::findOrFail($id);
        // [REFACTOR] Use Enum
        $media->update([
            'moderation_status' => ModerationStatus::REJECTED,
            'moderation_notes' => $request->reason
        ]);

        return response()->json(['status' => 'success', 'message' => 'Media rejected.']);
    }
}