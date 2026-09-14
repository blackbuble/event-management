<?php

namespace App\Http\Requests\Web;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWhatsAppTopUpRequest extends FormRequest
{
    /**
     * Only admins/organizers manage WhatsApp quota.
     */
    public function authorize(): bool
    {
        return $this->user()->hasRole(['admin', 'organizer']);
    }

    public function rules(): array
    {
        return [
            'package' => ['required', Rule::in(array_keys(config('whatsapp.packages', [])))],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
        ];
    }
}
