<?php

namespace App\Http\Requests\Web;

use App\Http\Requests\Web\Concerns\ValidatesTicketFields;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    use ValidatesTicketFields;

    /**
     * Authorization: only the event owner (or admin) may add tickets.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('event'));
    }

    public function rules(): array
    {
        return $this->ticketFieldRules('');
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->addTicketRangeErrors($validator, $this->all(), ''));
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizeTicketInput([$this->all()])[0] ?? []);
    }
}
