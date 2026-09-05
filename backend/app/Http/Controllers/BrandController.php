<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Http\Requests\StoreBrandRequest;
use App\Http\Requests\UpdateBrandRequest;
use App\Http\Resources\BrandResource;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function index(Request $request)
    {
        $query = Brand::query();

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('industry', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->input('compact') == 'true') {
            return response()->json([
                'success' => true,
                'data' => $query->select('id', 'name', 'is_default')->get()
            ]);
        }

        $brands = $query->withCount('templates')->latest()->paginate(10);
        return BrandResource::collection($brands)->additional(['success' => true]);
    }

    public function store(StoreBrandRequest $request)
    {
        $data = $request->validated();
        
        if (!empty($data['is_default'])) {
            Brand::where('id', '!=', 0)->update(['is_default' => false]);
        }

        $brand = Brand::create($data);

        $brand->versions()->create([
            'version_number' => 1,
            'snapshot' => $brand->toArray(),
            'change_summary' => 'Created brand',
            'created_by' => auth()->id() ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tạo thương hiệu thành công.',
            'data' => new BrandResource($brand)
        ]);
    }

    public function show($id)
    {
        $brand = Brand::find($id);
        if (!$brand) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy thương hiệu.',
                'error_code' => 'BRAND_NOT_FOUND'
            ], 404);
        }

        $brand->loadCount('templates');

        return response()->json([
            'success' => true,
            'data' => new BrandResource($brand)
        ]);
    }

    public function update(UpdateBrandRequest $request, $id)
    {
        $brand = Brand::find($id);
        if (!$brand) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy thương hiệu.',
                'error_code' => 'BRAND_NOT_FOUND'
            ], 404);
        }

        $data = $request->validated();

        if (!empty($data['is_default']) && !$brand->is_default) {
            Brand::where('id', '!=', $brand->id)->update(['is_default' => false]);
        }

        $oldData = $brand->only([
            'name', 'brand_type', 'industry', 'website', 'hotline', 'email', 'address', 'description', 
            'products_services', 'positioning', 'unique_value_proposition', 'brand_story', 'brand_personality', 
            'target_audience', 'tone', 'slogan', 'default_cta', 'service_areas', 'competitive_advantages',
            'customer_pain_points', 'customer_desires', 'customer_objections', 'default_language', 'emoji_limit',
            'preferred_addressing', 'platform_rules', 'default_hashtags', 'required_keywords', 'prohibited_terms',
            'writing_rules'
        ]);

        $brand->update($data);

        $newData = $brand->fresh()->only(array_keys($oldData));
        if ($oldData !== $newData) {
            $brand->versions()->create([
                'version_number' => $brand->versions()->max('version_number') + 1,
                'snapshot' => $brand->fresh()->toArray(),
                'change_summary' => 'Updated brand profile',
                'created_by' => auth()->id() ?? null,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật thương hiệu thành công.',
            'data' => new BrandResource($brand)
        ]);
    }

    public function destroy($id)
    {
        $brand = Brand::find($id);
        if (!$brand) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy thương hiệu.',
                'error_code' => 'BRAND_NOT_FOUND'
            ], 404);
        }

        $brand->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa thương hiệu.'
        ]);
    }

    public function setDefault($id)
    {
        $brand = Brand::find($id);
        if (!$brand) {
            return response()->json(['success' => false, 'error_code' => 'BRAND_NOT_FOUND'], 404);
        }

        if (!$brand->is_active) {
            return response()->json(['success' => false, 'message' => 'Không thể đặt thương hiệu đang tắt làm mặc định.', 'error_code' => 'BRAND_INACTIVE'], 400);
        }

        Brand::where('id', '!=', $id)->update(['is_default' => false]);
        $brand->update(['is_default' => true]);

        return response()->json(['success' => true, 'message' => 'Đã đặt làm mặc định.']);
    }

    public function setStatus(Request $request, $id)
    {
        $brand = Brand::find($id);
        if (!$brand) return response()->json(['success' => false, 'error_code' => 'BRAND_NOT_FOUND'], 404);

        $isActive = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN);
        $brand->update(['is_active' => $isActive]);

        if (!$isActive && $brand->is_default) {
            $brand->update(['is_default' => false]);
        }

        return response()->json(['success' => true, 'message' => 'Cập nhật trạng thái thành công.']);
    }

    public function getDefault()
    {
        $brand = Brand::where('is_default', true)->where('is_active', true)->first();
        
        return response()->json([
            'success' => true,
            'data' => $brand ? new BrandResource($brand) : null
        ]);
    }

    public function versions($id)
    {
        $brand = Brand::find($id);
        if (!$brand) return response()->json(['success' => false, 'error_code' => 'BRAND_NOT_FOUND'], 404);

        $versions = $brand->versions()->latest()->get();
        return response()->json(['success' => true, 'data' => $versions]);
    }

    public function restoreVersion($id, $versionId)
    {
        $brand = Brand::find($id);
        if (!$brand) return response()->json(['success' => false, 'error_code' => 'BRAND_NOT_FOUND'], 404);

        $version = $brand->versions()->find($versionId);
        if (!$version) return response()->json(['success' => false, 'error_code' => 'BRAND_VERSION_NOT_FOUND'], 404);

        $snapshot = $version->snapshot;
        unset($snapshot['id'], $snapshot['created_at'], $snapshot['updated_at'], $snapshot['deleted_at'], $snapshot['profile_completeness']);

        $brand->update($snapshot);

        $brand->versions()->create([
            'version_number' => $brand->versions()->max('version_number') + 1,
            'snapshot' => $brand->fresh()->toArray(),
            'change_summary' => 'Restored to version ' . $version->version_number,
            'created_by' => auth()->id() ?? null,
        ]);

        return response()->json(['success' => true, 'message' => 'Đã khôi phục phiên bản.', 'data' => new BrandResource($brand->fresh())]);
    }
}
