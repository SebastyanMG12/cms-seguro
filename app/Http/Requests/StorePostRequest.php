<?php

namespace App\Http\Requests;

use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Post::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('title')) {
            $this->merge(['title' => strip_tags(trim((string) $this->input('title')))]);
        }
    }

    public function rules(): array
    {
        $allowedStatuses = ['draft'];
        if ($this->user()?->can('publish', Post::class)) {
            $allowedStatuses[] = 'published';
        }

        return [
            'title' => ['required', 'string', 'min:3', 'max:150'],
            'body' => ['required', 'string', 'min:10', 'max:20000'],
            'status' => ['required', 'string', Rule::in($allowedStatuses)],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'No tiene permisos para publicar directamente. Solo puede guardar en estado borrador.',
        ];
    }
}
