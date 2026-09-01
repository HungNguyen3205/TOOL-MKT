<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

class StoreBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:brands,slug',
            'industry' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:5000',
            'products_services' => 'nullable|string|max:5000',
            'target_audience' => 'nullable|string|max:3000',
            'tone' => 'nullable|in:professional,friendly,youthful,humorous,luxurious,inspirational',
            'slogan' => 'nullable|string|max:500',
            'default_cta' => 'nullable|string|max:1000',
            
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
