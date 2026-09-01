<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class GenerateContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        if (!$this->has('number_of_versions')) {
            $this->merge(['number_of_versions' => 3]);
        }
    }

    public function rules(): array
    {
        return [
            'brand_id' => 'nullable|integer|exists:brands,id',
            'content_template_id' => 'nullable|integer|exists:content_templates,id',
            'topic' => 'required|string|max:150',
            'main_information' => 'required|string|max:5000',
            'target_audience' => 'nullable|string|max:1000',
            'objective' => 'required|in:sales,introduction,promotion,engagement,education,event',
            'tone' => 'required|in:professional,friendly,youthful,humorous,luxurious,inspirational',
            'length' => 'required|in:short,medium,long',
            'required_keywords' => 'nullable|array|max:20',
            'required_keywords.*' => 'string|max:100',
            'excluded_content' => 'nullable|array|max:20',
            'excluded_content.*' => 'string|max:300',
            'number_of_versions' => 'required|integer|min:1|max:5',
        ];
    }

    protected function passedValidation()
    {
        // Normalize data
        $keywords = $this->input('required_keywords', []);
        if (is_array($keywords)) {
            $keywords = array_filter(array_map('trim', $keywords));
            $keywords = array_values(array_unique($keywords));
            $this->merge(['required_keywords' => $keywords]);
        }

        $excluded = $this->input('excluded_content', []);
        if (is_array($excluded)) {
            $excluded = array_filter(array_map('trim', $excluded));
            $excluded = array_values(array_unique($excluded));
            $this->merge(['excluded_content' => $excluded]);
        }
        
        $this->merge([
            'topic' => trim($this->input('topic')),
            'main_information' => trim($this->input('main_information')),
            'target_audience' => trim($this->input('target_audience', '')),
        ]);
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Dữ liệu cung cấp không hợp lệ.',
            'error_code' => 'VALIDATION_FAILED',
            'errors' => $validator->errors()
        ], 422));
    }
}
