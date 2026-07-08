<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $query = Review::with(['user', 'specialist'])->orderByDesc('created_at');

        if ($request->filled('approved')) {
            $query->where('is_approved', $request->boolean('approved'));
        }

        return response()->json($query->paginate(20));
    }

    public function approve(Review $review)
    {
        $review->update(['is_approved' => true]);

        return response()->json(['message' => 'نظر تأیید شد.', 'review' => $review]);
    }

    public function destroy(Review $review)
    {
        $review->delete();

        return response()->json(['message' => 'نظر حذف شد.']);
    }
}