<?php

namespace App\Http\Requests\Web;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    /**
     * Authorization is enforced by the `booking.access:pay` route middleware
     * (owner policy OR signed URL), so guests paying via a signed checkout link
     * are allowed without an account.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
        ];
    }
}
