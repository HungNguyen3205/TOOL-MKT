<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'content' => 'required|string|max:20000',
            'cta' => 'nullable|string|max:1000',
            'hashtags' => 'nullable|array|max:30',
            'hashtags.*' => 'string|max:100',
            'objective' => 'nullable|in:sales,introduction,promotion,engagement,education,event',
            'tone' => 'nullable|in:professional,friendly,youthful,humorous,luxurious,inspirational',
            'content_length' => 'nullable|in:short,medium,long',
            'source' => 'required|in:manual,ai_generated,ai_edited',
            'status' => 'required|in:draft,in_review,changes_requested,approved,ready,generating_content,generating_image,scheduled,publishing,published,failed,image_failed,cancelled',
            'ai_model' => 'nullable|string|max:255',
            'ai_provider' => 'nullable|string|max:100',
            'selected_version' => 'nullable|integer|min:1|max:5',
            'source_input' => 'nullable|array',
            'design_format' => 'nullable|string|max:255',
            'design_title' => 'nullable|string|max:255',
            'design_layout' => 'nullable|string|max:255',
            'design_visual' => 'nullable|string|max:255',
            'design_color' => 'nullable|string|max:255',
            'design_suggestion' => 'nullable|string',
            'image_prompt' => 'nullable|string|max:5000',
        ];
    }

    protected function prepareForValidation()
    {
        $hashtags = $this->input('hashtags', []);
        if (is_array($hashtags)) {
            $hashtags = array_map('trim', $hashtags);
            $hashtags = array_filter($hashtags);
            $hashtags = array_map(function($tag) {
                return str_starts_with($tag, '#') ? $tag : '#' . $tag;
            }, $hashtags);
            $hashtags = array_values(array_unique($hashtags));
            $this->merge(['hashtags' => $hashtags]);
        }

        $this->merge([
            'title' => trim($this->input('title', '')),
            'content' => trim($this->input('content', '')),
            'cta' => trim($this->input('cta', '')),
        ]);
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
