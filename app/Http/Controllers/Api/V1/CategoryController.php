<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Family;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'family_id' => 'required|exists:families,id',
            'type' => 'sometimes|in:income,expense,saving,withdraw_saving',
        ]);

        $family = Family::findOrFail($request->family_id);

        // Check if user is member
        if (!$family->members->contains($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $query = $family->categories();

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $categories = $query->with('children')->whereNull('parent_id')->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'family_id' => 'required|exists:families,id',
            'name' => 'required|string|max:255',
            'type' => 'required|in:income,expense,saving,withdraw_saving',
            'parent_id' => 'nullable|exists:categories,id',
            'icon' => 'sometimes|string|max:50',
            'color' => 'sometimes|string|max:7',
        ]);

        $family = Family::findOrFail($request->family_id);

        // Check if user is member
        if (!$family->members->contains($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $category = Category::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully',
            'data' => $category,
        ], 201);
    }

    public function update(Request $request, Category $category)
    {
        // Check if user is member of the family
        if (!$category->family->members->contains($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Prevent editing system categories
        if ($category->is_system) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot edit system categories',
            ], 403);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'icon' => 'sometimes|string|max:50',
            'color' => 'sometimes|string|max:7',
        ]);

        $category->update($request->only(['name', 'icon', 'color']));

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully',
            'data' => $category,
        ]);
    }

    public function destroy(Request $request, Category $category)
    {
        // Check if user is owner of the family
        if ($category->family->owner_user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Only family owner can delete categories',
            ], 403);
        }

        // Prevent deleting system categories
        if ($category->is_system) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete system categories',
            ], 403);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully',
        ]);
    }
}
