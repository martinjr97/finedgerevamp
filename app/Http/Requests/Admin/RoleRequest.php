<?php

namespace App\Http\Requests\Admin;

use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('admin')?->can('roles.update') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $roleId = $this->route('role')?->id ?? null;

        return [
            'name' => ['required', 'string', 'max:100', 'unique:roles,name,'.($roleId ?? 'null')],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(PermissionSeeder::defaultPermissions())],
            'admin_ids' => ['nullable', 'array'],
            'admin_ids.*' => ['integer', 'exists:admins,id'],
        ];
    }
}
