<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::whereNull('deleted_at')
            ->where('id', '!=', 1)
            ->orderBy('category')
            ->get();

        return response()->json($categories);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $category = Category::create(['category' => $validated['name']]);

        return response()->json($category, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if ($id === 1) {
            return response()->json(['message' => 'Protected category.'], 403);
        }

        $category = Category::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $category->category = $validated['name'];
        $category->save();

        return response()->json($category);
    }

    public function destroy(int $id): JsonResponse
    {
        if ($id === 1) {
            return response()->json(['message' => 'Protected category.'], 403);
        }

        $category = Category::findOrFail($id);
        $category->delete();

        return response()->json(['message' => 'Category deleted.']);
    }
}
