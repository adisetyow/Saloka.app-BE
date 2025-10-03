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

    public function destroy($id)
    {
        $type = ChecklistType::findOrFail($id);
        $type->delete();

        return response()->json([
            'message' => 'Checklist Type berhasil dihapus'
        ], 200);
    }
}