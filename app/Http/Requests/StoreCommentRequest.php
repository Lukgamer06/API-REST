<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'author' => ['sometimes', 'string', 'max:255'],
            'content' => ['required', 'string'],
        ];
    }

    /**
     * Describe the fields used by Scribe's interactive request form.
     *
     * @return array<string, array<string, string>>
     */
    public function bodyParameters(): array
    {
        return [
            'content' => [
                'description' => 'Contenido del comentario. El autor se toma del usuario autenticado.',
                'example' => 'Comentario de prueba desde la documentación.',
            ],
        ];
    }
}
