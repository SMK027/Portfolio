<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name' => Str::squish((string) $this->input('first_name')),
            'last_name'  => Str::squish((string) $this->input('last_name')),
            'email'      => Str::lower(trim((string) $this->input('email'))),
            'subject'    => Str::squish((string) $this->input('subject')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name'      => ['required', 'string', 'max:100'],
            'last_name'       => ['required', 'string', 'max:100'],
            'email'           => ['required', 'string', 'email:rfc', 'max:255'],
            'subject'         => ['required', 'string', 'min:3', 'max:150'],
            'message'         => ['required', 'string', 'min:10', 'max:5000'],
            'consent'         => ['accepted'],
            'recaptcha_token' => ['nullable', 'string', 'max:4000'],
            // Champ piège invisible : les robots le remplissent, les humains non.
            'website'         => ['prohibited'],
        ];
    }
}
