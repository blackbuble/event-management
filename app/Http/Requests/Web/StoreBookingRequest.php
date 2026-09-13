<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tickets' => ['required', 'array', 'min:1'],
            'tickets.*.ticket_id' => ['required', 'integer', 'exists:tickets,id'],
            'tickets.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
