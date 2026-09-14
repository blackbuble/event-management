<?php

namespace App\Http\Requests\Web;

use App\Http\Requests\Web\Concerns\ValidatesTicketFields;
use App\Models\Event;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    use ValidatesTicketFields;

    /**
     * Authorization: only admin/organizer roles may create events (EventPolicy::create).
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Event::class);
    }

    /**
     * Rules mirror the `events` table schema plus the nested ticket settings
     * that are persisted atomically with the event.
     */
    public function rules(): array
    {
        return array_merge([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'image' => ['nullable', 'image', 'max:2048'],

            'type' => ['required', 'in:online,offline,hybrid'],
            'category' => ['required', 'string', 'exists:categories,slug'],
            'city' => ['nullable', 'string', 'max:100'],
            'venue_name' => ['nullable', 'required_unless:type,online', 'string', 'max:255'],
            'venue_address' => ['nullable', 'required_unless:type,online', 'string', 'max:1000'],
            'meeting_link' => ['nullable', 'url', 'max:255'],

            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],

            'start_date' => ['required', 'date', 'after:now'],
            'end_date' => ['required', 'date', 'after:start_date'],

            'capacity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'status' => ['required', 'in:draft,published'],
            'whatsapp_enabled' => ['boolean'],

            'tickets' => ['required', 'array', 'min:1', 'max:50'],
        ], $this->ticketFieldRules('tickets.*.'));
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->validateNestedTickets($validator));
    }

    /**
     * Empty strings from optional inputs must become null,
     * otherwise numeric/integer rules reject them.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(
            array_map(
                fn ($value) => $value === '' ? null : $value,
                $this->only(['meeting_link', 'latitude', 'longitude', 'capacity'])
            )
        );

        if (is_array($this->input('tickets'))) {
            $this->merge(['tickets' => $this->normalizeTicketInput($this->input('tickets'))]);
        }
    }
}
