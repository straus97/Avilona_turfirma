<?php

namespace App\Http\Requests\Contact;

use Illuminate\Foundation\Http\FormRequest;

class SendContactRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required',
            'email' => 'required|email',
            // E2-A6-I1: «Тема» остаётся необязательным полем (посетитель может
            // оставить его пустым), но теперь детерминированно ограничено по
            // длине, чтобы свободный текст не попадал без границ в тему письма.
            'subject' => 'nullable|string|max:150',
            'message' => 'required|min:50',
            'captcha' => 'required|captcha',
            'agree' => 'accepted',
            'personal_data_consent' => 'accepted',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'agree.accepted' => 'Необходимо принять условия Пользовательского соглашения.',
            'personal_data_consent.accepted' => 'Необходимо дать согласие на обработку персональных данных.',
        ];
    }
}
