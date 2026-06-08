<?php

namespace App\Http\Requests\Admin;

use App\Models\Channel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        $channel = $this->route('channel');
        
        if ($this->isMethod('POST')) {
            return $this->user('admin')?->can('channels.create') ?? false;
        }
        
        $admin = $this->user('admin');

        return ($admin?->can('channels.update') || $admin?->can('channels.edit')) ?? false;
    }

    public function rules(): array
    {
        $channelId = $this->route('channel')?->id ?? null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('channels', 'code')->ignore($channelId)],
            'type' => ['required', 'string', 'max:32', Channel::typeValidationRule()],
            'description' => ['nullable', 'string', 'max:1000'],
            'can_disburse' => ['sometimes', 'boolean'],
            'can_repay' => ['sometimes', 'boolean'],
            'is_repayment_integrated' => ['sometimes', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['can_disburse', 'can_repay'] as $field) {
            if ($this->has($field)) {
                $merge[$field] = (bool) $this->input($field);
            } elseif ($this->isMethod('POST')) {
                $merge[$field] = false;
            }
        }

        if ($this->has('is_repayment_integrated')) {
            $merge['is_repayment_integrated'] = (bool) $this->input('is_repayment_integrated');
        } elseif ($this->isMethod('POST')) {
            $merge['is_repayment_integrated'] = true;
        }

        if ($this->has('is_active')) {
            $merge['is_active'] = (bool) $this->input('is_active');
        } elseif ($this->isMethod('POST')) {
            $merge['is_active'] = true;
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
