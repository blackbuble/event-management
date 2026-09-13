<?php

namespace App\Http\Requests\Web;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEventRequest extends FormRequest
{
    /**
     * Authorization: only the event owner (or admin) may update.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('event'));
    }

    /**
     * Same schema contract as creation, minus slug (not editable) —
     * past events stay editable (schedule rule is looser than create).
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'image' => ['nullable', 'image', 'max:2048'],

            'type' => ['required', 'in:online,offline,hybrid'],
            'venue_name' => ['nullable', 'required_unless:type,online', 'string', 'max:255'],
            'venue_address' => ['nullable', 'required_unless:type,online', 'string', 'max:1000'],
            'meeting_link' => ['nullable', 'url', 'max:255'],

            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],

            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],

            'capacity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'status' => ['required', 'in:draft,published'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(
            array_map(
                fn ($value) => $value === '' ? null : $value,
                $this->only(['meeting_link', 'latitude', 'longitude', 'capacity', 'image'])
            )
        );
    }
}
