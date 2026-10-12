<?php

namespace App\Http\Resources\Api\V1;

use App\Services\Forms\FormField;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One field of the form version the member is looking at.
 *
 * @mixin FormField
 */
class FormFieldResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type->value,
            'label' => $this->label,
            'help' => $this->help,
            'required' => $this->required,
            'options' => $this->options,
            'min' => $this->setting('min'),
            'max' => $this->setting('max'),
        ];
    }
}
