<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

class UpdateBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $brandId = $this->route('id'); // fixed parameter name

        return [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:brands,slug,' . $brandId,
            'industry' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:5000',
            'products_services' => 'nullable|string|max:5000',
            'target_audience' => 'nullable|string|max:3000',
            'tone' => 'nullable|in:professional,friendly,youthful,humorous,luxurious,inspirational',
            'slogan' => 'nullable|string|max:500',
            'default_cta' => 'nullable|string|max:1000',
            
            'brand_type' => 'nullable|string|max:50',
            'website' => 'nullable|url|max:255',
            'hotline' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            
            'service_areas' => 'nullable|array|max:20',
            'service_areas.*' => 'string|max:255',
            
            'positioning' => 'nullable|string|max:3000',
            'unique_value_proposition' => 'nullable|string|max:3000',
            'brand_story' => 'nullable|string|max:5000',
            'brand_personality' => 'nullable|string|max:500',
            
            'competitive_advantages' => 'nullable|array|max:20',
            'competitive_advantages.*' => 'string|max:500',
            
            'customer_pain_points' => 'nullable|array|max:20',
            'customer_pain_points.*' => 'string|max:500',
            
            'customer_desires' => 'nullable|array|max:20',
            'customer_desires.*' => 'string|max:500',
            
            'customer_objections' => 'nullable|array|max:20',
            'customer_objections.*' => 'string|max:500',
            
            'default_language' => 'nullable|string|max:50',
            'emoji_limit' => 'nullable|integer|min:0',
            'preferred_addressing' => 'nullable|string|max:255',
            
            'platform_rules' => 'nullable|array|max:20',
            'platform_rules.*' => 'string|max:500',

            'default_hashtags' => 'nullable|array|max:30',
            'default_hashtags.*' => 'string|max:100',
            
            'required_keywords' => 'nullable|array|max:30',
            'required_keywords.*' => 'string|max:100',
            
            'prohibited_terms' => 'nullable|array|max:50',
            'prohibited_terms.*' => 'string|max:300',
            
            'writing_rules' => 'nullable|array|max:50',
            'writing_rules.*' => 'string|max:500',
            
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'slug' => $this->input('slug') ? Str::slug($this->input('slug')) : Str::slug($this->input('name')),
            'name' => trim($this->input('name', '')),
        ]);

        $this->normalizeArray('default_hashtags', true);
        $this->normalizeArray('required_keywords');
        $this->normalizeArray('prohibited_terms');
        $this->normalizeArray('writing_rules');
        $this->normalizeArray('service_areas');
        $this->normalizeArray('competitive_advantages');
        $this->normalizeArray('customer_pain_points');
        $this->normalizeArray('customer_desires');
        $this->normalizeArray('customer_objections');
        $this->normalizeArray('platform_rules');
    }

    private function normalizeArray($field, $isHashtag = false)
    {
        $data = $this->input($field, []);
        if (is_array($data)) {
            $data = array_map('trim', $data);
            $data = array_filter($data);
            if ($isHashtag) {
                $data = array_map(function($tag) {
                    $tag = str_replace(' ', '', $tag);
                    return str_starts_with($tag, '#') ? $tag : '#' . $tag;
                }, $data);
            }
            $data = array_values(array_unique($data));
            $this->merge([$field => empty($data) ? null : $data]);
        } else {
            $this->merge([$field => null]);
        }
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Dữ liệu không hợp lệ.',
            'error_code' => 'VALIDATION_FAILED',
            'errors' => $validator->errors()
        ], 422));
    }
}
