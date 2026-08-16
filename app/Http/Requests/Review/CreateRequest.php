<?php

namespace App\Http\Requests\Review;

use Illuminate\Foundation\Http\FormRequest;

class CreateRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'consent_full_name' => 'required|string|max:255',
            'consent_email' => 'required|email|max:255',
            'message' => 'required|string|min:50',
            'publication_conditions' => 'nullable|string|max:2000',
            'captcha' => 'required|captcha',
            'user_agreement_accepted' => 'accepted',
            'personal_data_consent_accepted' => 'accepted',
            'review_publication_consent_accepted' => 'accepted',
        ];
    }
}
