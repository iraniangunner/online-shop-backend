<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Branch;

class BranchController extends Controller
{
    public function index()
    {
        return response()->json(Branch::active()->orderBy('name')->get());
    }

    public function show(Branch $branch)
    {
        if (! $branch->is_active) {
            return response()->json(['message' => 'شعبه یافت نشد.'], 404);
        }

        return response()->json($branch->load('services'));
    }
}