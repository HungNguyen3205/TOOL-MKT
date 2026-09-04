<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\ContentTemplate;
use App\Http\Requests\StoreContentTemplateRequest;
use App\Http\Requests\UpdateContentTemplateRequest;
use App\Http\Resources\ContentTemplateResource;
use Illuminate\Http\Request;

class ContentTemplateController extends Controller
{
    public function index(Request $request, $brandId)
    {
        $brand = Brand::find($brandId);
        if (!$brand) return response()->json(['success' => false, 'error_code' => 'BRAND_NOT_FOUND'], 404);

        $query = $brand->templates();

        if ($request->has('objective')) {
            $query->where('objective', $request->input('objective'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $templates = $query->latest()->get();
        return response()->json([
            'success' => true,
            'data' => ContentTemplateResource::collection($templates)
        ]);
    }

    public function store(StoreContentTemplateRequest $request, $brandId)
    {
        $brand = Brand::find($brandId);
        if (!$brand) return response()->json(['success' => false, 'error_code' => 'BRAND_NOT_FOUND'], 404);

        $data = $request->validated();
        
        if (!empty($data['is_default'])) {
            $brand->templates()->where('objective', $data['objective'])->update(['is_default' => false]);
        }

        $template = $brand->templates()->create($data);

        return response()->json([
            'success' => true,
            'message' => 'Tạo mẫu nội dung thành công.',
            'data' => new ContentTemplateResource($template)
        ]);
    }

    public function show($brandId, $templateId)
    {
        $template = ContentTemplate::where('brand_id', $brandId)->find($templateId);
        if (!$template) return response()->json(['success' => false, 'error_code' => 'TEMPLATE_NOT_FOUND'], 404);

        return response()->json([
            'success' => true,
            'data' => new ContentTemplateResource($template)
        ]);
    }

    public function update(UpdateContentTemplateRequest $request, $brandId, $templateId)
    {
        $template = ContentTemplate::where('brand_id', $brandId)->find($templateId);
        if (!$template) return response()->json(['success' => false, 'error_code' => 'TEMPLATE_NOT_FOUND'], 404);

        $data = $request->validated();

        if (!empty($data['is_default']) && !$template->is_default) {
            ContentTemplate::where('brand_id', $brandId)
                ->where('objective', $data['objective'])
                ->where('id', '!=', $template->id)
                ->update(['is_default' => false]);
        }

        $template->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật mẫu nội dung thành công.',
            'data' => new ContentTemplateResource($template)
        ]);
    }

    public function destroy($brandId, $templateId)
    {
        $template = ContentTemplate::where('brand_id', $brandId)->find($templateId);
        if (!$template) return response()->json(['success' => false, 'error_code' => 'TEMPLATE_NOT_FOUND'], 404);

        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa mẫu nội dung.'
        ]);
    }

    public function setDefault($brandId, $templateId)
    {
        $template = ContentTemplate::where('brand_id', $brandId)->find($templateId);
        if (!$template) return response()->json(['success' => false, 'error_code' => 'TEMPLATE_NOT_FOUND'], 404);
        
        if (!$template->is_active) {
            return response()->json(['success' => false, 'message' => 'Không thể đặt mặc định.', 'error_code' => 'INVALID_DEFAULT_TEMPLATE'], 400);
        }

        ContentTemplate::where('brand_id', $brandId)
            ->where('objective', $template->objective)
            ->where('id', '!=', $template->id)
            ->update(['is_default' => false]);

        $template->update(['is_default' => true]);

        return response()->json(['success' => true, 'message' => 'Đã đặt mẫu làm mặc định.']);
    }

    public function setStatus(Request $request, $brandId, $templateId)
    {
        $template = ContentTemplate::where('brand_id', $brandId)->find($templateId);
        if (!$template) return response()->json(['success' => false, 'error_code' => 'TEMPLATE_NOT_FOUND'], 404);

        $isActive = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN);
        $template->update(['is_active' => $isActive]);

        if (!$isActive && $template->is_default) {
            $template->update(['is_default' => false]);
        }

        return response()->json(['success' => true, 'message' => 'Đã cập nhật trạng thái mẫu.']);
    }

    public function resolveTemplate(Request $request, $brandId)
    {
        $brand = Brand::find($brandId);
        if (!$brand) return response()->json(['success' => false, 'error_code' => 'BRAND_NOT_FOUND'], 404);

        $objective = $request->input('objective');
        $platform = $request->input('platform', 'facebook');
        $contentType = $request->input('content_type');

        // Order of resolution:
        // 1. brand + platform + objective + content_type + is_default
        // 2. brand + platform + objective + is_default
        // 3. brand + objective + content_type
        // 4. brand + objective
        // 5. brand + is_default
        
        $query = $brand->templates()->where('is_active', true);

        // We will fetch all active templates for this brand and filter in memory to find the best match.
        $templates = $query->get();

        if ($templates->isEmpty()) {
            return response()->json(['success' => true, 'data' => null]);
        }

        $bestMatch = null;
        $highestScore = -1;

        foreach ($templates as $t) {
            $score = 0;
            if ($t->objective === $objective) $score += 10;
            if ($t->platform === $platform) $score += 5;
            if ($t->content_type === $contentType && $contentType) $score += 5;
            if ($t->is_default) $score += 2;

            if ($score > $highestScore) {
                $highestScore = $score;
                $bestMatch = $t;
            }
        }

        return response()->json([
            'success' => true,
            'data' => $bestMatch ? new ContentTemplateResource($bestMatch) : null
        ]);
    }
}
