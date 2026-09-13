<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateMeetingLinkRequest extends FormRequest
{
    /**
     * Authorization: only the event owner (or admin) may change the meeting link.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('event'));
    }

    public function rules(): array
    {
        return [
            'meeting_link' => ['required', 'url', 'max:255'],
        ];
    }

    /**
     * A meeting link is only meaningful for online/hybrid events.
     */
    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->route('event')?->type === 'offline') {
                $validator->errors()->add(
                    'meeting_link',
                    'Offline events cannot have a meeting link.'
                );
            }
        });
    }
}
