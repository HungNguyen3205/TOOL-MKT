<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\BrandKnowledgeItem;
use Illuminate\Http\Request;

class BrandKnowledgeController extends Controller
{
    public function index($brandId, Request $request)
    {
        $brand = Brand::find($brandId);
        if (!$brand) return response()->json(['success' => false, 'error_code' => 'BRAND_NOT_FOUND'], 404);

        $query = $brand->knowledgeItems();
        
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }
        
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderBy('priority', 'desc')->latest()->get()
        ]);
    }

    public function store($brandId, Request $request)
    {
        $brand = Brand::find($brandId);
        if (!$brand) return response()->json(['success' => false, 'error_code' => 'BRAND_NOT_FOUND'], 404);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:50',
            'content' => 'required|string|max:5000',
            'source_url' => 'nullable|url|max:255',
            'is_verified' => 'boolean',
            'is_active' => 'boolean',
            'priority' => 'integer'
        ]);

        $item = $brand->knowledgeItems()->create($data);

        return response()->json(['success' => true, 'message' => 'Tạo kiến thức thành công.', 'data' => $item]);
    }

    public function show($brandId, $itemId)
    {
        $brand = Brand::find($brandId);
        if (!$brand) return response()->json(['success' => false, 'error_code' => 'BRAND_NOT_FOUND'], 404);

        $item = $brand->knowledgeItems()->find($itemId);
        if (!$item) return response()->json(['success' => false, 'error_code' => 'KNOWLEDGE_ITEM_NOT_FOUND'], 404);

        return response()->json(['success' => true, 'data' => $item]);
    }

    public function update($brandId, $itemId, Request $request)
    {
        $brand = Brand::find($brandId);
        if (!$brand) return response()->json(['success' => false, 'error_code' => 'BRAND_NOT_FOUND'], 404);

        $item = $brand->knowledgeItems()->find($itemId);
        if (!$item) return response()->json(['success' => false, 'error_code' => 'KNOWLEDGE_ITEM_NOT_FOUND'], 404);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:50',
            'content' => 'required|string|max:5000',
            'source_url' => 'nullable|url|max:255',
            'is_verified' => 'boolean',
            'is_active' => 'boolean',
            'priority' => 'integer'
        ]);

        $item->update($data);

        return response()->json(['success' => true, 'message' => 'Cập nhật kiến thức thành công.', 'data' => $item]);
    }

    public function destroy($brandId, $itemId)
    {
        $brand = Brand::find($brandId);
        if (!$brand) return response()->json(['success' => false, 'error_code' => 'BRAND_NOT_FOUND'], 404);

        $item = $brand->knowledgeItems()->find($itemId);
        if (!$item) return response()->json(['success' => false, 'error_code' => 'KNOWLEDGE_ITEM_NOT_FOUND'], 404);

        $item->delete();

        return response()->json(['success' => true, 'message' => 'Đã xóa kiến thức.']);
    }
}
