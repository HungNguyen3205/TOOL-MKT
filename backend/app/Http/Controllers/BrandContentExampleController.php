<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\BrandContentExample;
use Illuminate\Http\Request;

class BrandContentExampleController extends Controller
{
    public function index($brandId, Request $request)
    {
        $brand = Brand::find($brandId);
        if (!$brand) return response()->json(['success' => false, 'error_code' => 'BRAND_NOT_FOUND'], 404);

        $query = $brand->contentExamples();
        
        if ($request->has('example_type')) {
            $query->where('example_type', $request->example_type);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json([
            'success' => true,
            'data' => $query->latest()->get()
        ]);
    }

    public function store($brandId, Request $request)
    {
        $brand = Brand::find($brandId);
        if (!$brand) return response()->json(['success' => false, 'error_code' => 'BRAND_NOT_FOUND'], 404);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string|max:10000',
            'example_type' => 'required|in:good,bad',
            'explanation' => 'nullable|string|max:3000',
            'objective' => 'nullable|string|max:255',
            'is_active' => 'boolean'
        ]);

        $item = $brand->contentExamples()->create($data);

        return response()->json(['success' => true, 'message' => 'Tạo bài mẫu thành công.', 'data' => $item]);
    }

    public function show($brandId, $itemId)
    {
        $brand = Brand::find($brandId);
        if (!$brand) return response()->json(['success' => false, 'error_code' => 'BRAND_NOT_FOUND'], 404);

        $item = $brand->contentExamples()->find($itemId);
        if (!$item) return response()->json(['success' => false, 'error_code' => 'EXAMPLE_NOT_FOUND'], 404);

        return response()->json(['success' => true, 'data' => $item]);
    }

    public function update($brandId, $itemId, Request $request)
    {
        $brand = Brand::find($brandId);
        if (!$brand) return response()->json(['success' => false, 'error_code' => 'BRAND_NOT_FOUND'], 404);

        $item = $brand->contentExamples()->find($itemId);
        if (!$item) return response()->json(['success' => false, 'error_code' => 'EXAMPLE_NOT_FOUND'], 404);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string|max:10000',
            'example_type' => 'required|in:good,bad',
            'explanation' => 'nullable|string|max:3000',
            'objective' => 'nullable|string|max:255',
            'is_active' => 'boolean'
        ]);

        $item->update($data);

        return response()->json(['success' => true, 'message' => 'Cập nhật bài mẫu thành công.', 'data' => $item]);
    }

    public function destroy($brandId, $itemId)
    {
        $brand = Brand::find($brandId);
        if (!$brand) return response()->json(['success' => false, 'error_code' => 'BRAND_NOT_FOUND'], 404);

        $item = $brand->contentExamples()->find($itemId);
        if (!$item) return response()->json(['success' => false, 'error_code' => 'EXAMPLE_NOT_FOUND'], 404);

        $item->delete();

        return response()->json(['success' => true, 'message' => 'Đã xóa bài mẫu.']);
    }
}
