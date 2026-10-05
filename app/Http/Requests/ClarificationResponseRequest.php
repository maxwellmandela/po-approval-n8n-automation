<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClarificationResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'response' => ['required', 'string'],
            'response_documents.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:4096'],
        ];
    }
}
