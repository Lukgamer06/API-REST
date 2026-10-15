<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'images' => ['sometimes', 'array', 'max:10'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
        ];
    }

    /**
     * Describe the fields used by Scribe's interactive request form.
     *
     * @return array<string, array<string, string|bool>>
     */
    public function bodyParameters(): array
    {
        return [
            'title' => [
                'description' => 'Título de la publicación.',
                'example' => 'Mi primera publicación',
            ],
            'content' => [
                'description' => 'Contenido de la publicación.',
                'example' => 'Contenido de ejemplo creado desde la documentación.',
            ],
            'images' => [
                'description' => 'Opcional. Hasta 10 imágenes JPG, PNG, GIF o WEBP, de máximo 5 MB cada una.',
                'required' => false,
            ],
        ];
    }
}
