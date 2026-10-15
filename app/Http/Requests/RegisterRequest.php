<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string', 'same:password'],
        ];
    }

    /**
     * Provide matching password examples for Scribe's interactive request form.
     *
     * @return array<string, array<string, string>>
     */
    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'Nombre del usuario.',
                'example' => 'Ana Postman',
            ],
            'email' => [
                'description' => 'Correo único. Usa uno distinto si ya existe.',
                'example' => 'ana.postman@example.test',
            ],
            'password' => [
                'description' => 'Contraseña de al menos 8 caracteres.',
                'example' => 'Password123!',
            ],
            'password_confirmation' => [
                'description' => 'Debe ser idéntica a `password`.',
                'example' => 'Password123!',
            ],
        ];
    }
}
