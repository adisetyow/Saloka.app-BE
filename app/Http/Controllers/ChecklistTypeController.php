<?php

namespace App\Http\Controllers;

use App\Models\ChecklistType;
use Illuminate\Http\Request;

class ChecklistTypeController extends Controller
{
    public function index()
    {
        return ChecklistType::latest()->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:checklist_types,name|max:255',
        ]);

        $type = ChecklistType::create($validated);

        return response()->json($type, 201);
    }
}