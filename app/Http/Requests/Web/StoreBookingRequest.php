<?php

namespace App\Http\Requests\Web;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Attendees can be captured one-by-one (individual) or delegated to a
     * single representative who holds every ticket in the order.
     */
    public function rules(): array
    {
        $individual = $this->input('attendee_mode') === 'individual';

        return [
            'attendee_mode' => ['required', Rule::in(['individual', 'representative'])],
            'contact' => ['required', 'array'],
            'contact.name' => ['required', 'string', 'max:255'],
            'contact.email' => ['required', 'email', 'max:255'],
            'contact.phone' => ['nullable', 'string', 'max:20'],
            'tickets' => ['required', 'array', 'min:1', 'max:20'],
            'tickets.*.ticket_id' => ['required', 'integer', 'exists:tickets,id'],
            'tickets.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],

            // `nullable` is required alongside `requiredIf`: the other mode's fields are
            // still posted (empty strings → null by ConvertEmptyStringsToNull) and would
            // otherwise trip the base `string` rule.
            'tickets.*.attendees' => [Rule::requiredIf($individual), 'array'],
            'tickets.*.attendees.*.name' => [Rule::requiredIf($individual), 'nullable', 'string', 'max:255'],
            'tickets.*.attendees.*.email' => ['nullable', 'email', 'max:255'],
            'tickets.*.attendees.*.phone' => ['nullable', 'string', 'max:20'],

            'representative' => [Rule::requiredIf(! $individual), 'array'],
            'representative.name' => [Rule::requiredIf(! $individual), 'nullable', 'string', 'max:255'],
            'representative.email' => ['nullable', 'email', 'max:255'],
            'representative.phone' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * When entering attendees individually, the number of names must match the
     * quantity ordered for each ticket type.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('attendee_mode') !== 'individual') {
                return;
            }

            foreach ((array) $this->input('tickets', []) as $index => $ticket) {
                if (! is_array($ticket)) {
                    continue;
                }

                $quantity = (int) ($ticket['quantity'] ?? 0);
                $provided = count($ticket['attendees'] ?? []);

                if ($quantity > 0 && $provided !== $quantity) {
                    $validator->errors()->add(
                        "tickets.{$index}.attendees",
                        app()->getLocale() === 'id'
                            ? "Jumlah nama peserta ({$provided}) harus sama dengan jumlah tiket ({$quantity})."
                            : "The number of attendee names ({$provided}) must match the ticket quantity ({$quantity})."
                    );
                }
            }
        });
    }
}
