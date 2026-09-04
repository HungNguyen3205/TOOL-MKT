<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateContentTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'objective' => 'required|string',
            'platform' => 'nullable|string',
            'content_type' => 'nullable|string',
            'opening_style' => 'nullable|string|max:1000',
            'body_structure' => 'nullable|array|max:20',
            'body_structure.*' => 'string|max:500',
            'cta_instruction' => 'nullable|string|max:1000',
            'hashtag_instruction' => 'nullable|string|max:1000',
            'additional_instruction' => 'nullable|string|max:3000',
            'example_content' => 'nullable|string|max:10000',
            'default_length' => 'nullable|string',
            'default_number_of_versions' => 'nullable|integer|min:1|max:10',
            'required_fields' => 'nullable|array',
            'required_fields.*' => 'string|max:100',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'name' => trim($this->input('name', '')),
        ]);

        $this->normalizeArray('body_structure');
        $this->normalizeArray('required_fields');
    }

    private function normalizeArray($field)
    {
        $data = $this->input($field, []);
        if (is_array($data)) {
            $data = array_map('trim', $data);
            $data = array_filter($data);
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
