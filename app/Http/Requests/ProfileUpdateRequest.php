<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $applySellerVerification = $this->boolean('apply_seller_verification');
        $hasExistingDocument = (bool) ($this->user()?->verification_document_path);

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'apply_seller_verification' => ['nullable', 'boolean'],
            'verification_document_type' => [
                'nullable',
                'string',
                'max:100',
                Rule::requiredIf(fn (): bool => $applySellerVerification || $this->hasFile('verification_document')),
            ],
            'verification_document_number' => [
                'nullable',
                'string',
                'max:120',
                Rule::requiredIf(fn (): bool => $applySellerVerification || $this->hasFile('verification_document')),
            ],
            'verification_document' => [
                'nullable',
                Rule::requiredIf(fn (): bool => $applySellerVerification && ! $hasExistingDocument),
                'file',
                'max:10240',
                'mimes:jpg,jpeg,png,webp,pdf',
            ],
        ];
    }
}
