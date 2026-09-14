<?php

namespace App\Http\Requests\Web\Concerns;

use Illuminate\Contracts\Validation\Validator;

/**
 * Shared validation contract for ticket settings, reused by event create/update
 * (nested `tickets.*`) and standalone ticket endpoints (flat fields).
 */
trait ValidatesTicketFields
{
    /**
     * Field-level rules. Pass a prefix (`tickets.*.` or `''`) to reuse the same
     * schema for nested and flat payloads.
     */
    protected function ticketFieldRules(string $prefix = ''): array
    {
        return [
            "{$prefix}id" => ['nullable', 'integer'],
            "{$prefix}name" => ['required', 'string', 'max:255', 'distinct'],
            "{$prefix}description" => ['nullable', 'string', 'max:1000'],
            "{$prefix}price" => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            "{$prefix}quantity" => ['required', 'integer', 'min:1', 'max:1000000'],
            "{$prefix}sale_starts" => ['nullable', 'date'],
            "{$prefix}sale_ends" => ['nullable', 'date'],
            "{$prefix}min_per_order" => ['required', 'integer', 'min:1', 'max:1000000'],
            "{$prefix}max_per_order" => ['required', 'integer', 'min:1', 'max:1000000'],
            "{$prefix}is_active" => ['boolean'],
        ];
    }

    /**
     * Cross-field checks Laravel's rule strings cannot express per wildcard row:
     * max_per_order >= min_per_order and sale_ends > sale_starts.
     */
    protected function addTicketRangeErrors(Validator $validator, array $ticket, string $path): void
    {
        $isId = app()->getLocale() === 'id';

        $min = filter_var($ticket['min_per_order'] ?? null, FILTER_VALIDATE_INT);
        $max = filter_var($ticket['max_per_order'] ?? null, FILTER_VALIDATE_INT);

        if ($min !== false && $max !== false && $max < $min) {
            $validator->errors()->add(
                "{$path}max_per_order",
                $isId
                    ? 'Maksimum per order tidak boleh kurang dari minimum.'
                    : 'Max per order may not be less than min per order.'
            );
        }

        $starts = $ticket['sale_starts'] ?? null;
        $ends = $ticket['sale_ends'] ?? null;

        if ($starts && $ends && strtotime((string) $ends) <= strtotime((string) $starts)) {
            $validator->errors()->add(
                "{$path}sale_ends",
                $isId
                    ? 'Waktu berakhir penjualan harus setelah waktu mulai.'
                    : 'Sale end time must be after the sale start time.'
            );
        }
    }

    /**
     * Run cross-field checks across a nested `tickets.*` payload.
     */
    protected function validateNestedTickets(Validator $validator): void
    {
        foreach ((array) $this->input('tickets', []) as $index => $ticket) {
            if (is_array($ticket)) {
                $this->addTicketRangeErrors($validator, $ticket, "tickets.{$index}.");
            }
        }
    }

    /**
     * Normalise empty optional ticket inputs to null so date/nullable rules pass.
     *
     * @param  array<int, array<string, mixed>>  $tickets
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeTicketInput(array $tickets): array
    {
        foreach ($tickets as $index => $ticket) {
            if (! is_array($ticket)) {
                continue;
            }

            foreach (['id', 'description', 'sale_starts', 'sale_ends'] as $key) {
                if (array_key_exists($key, $ticket) && $ticket[$key] === '') {
                    $tickets[$index][$key] = null;
                }
            }
        }

        return $tickets;
    }
}
