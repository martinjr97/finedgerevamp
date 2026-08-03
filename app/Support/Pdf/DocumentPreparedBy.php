<?php

namespace App\Support\Pdf;

use App\Models\Admin;
use Illuminate\Contracts\Auth\Authenticatable;

class DocumentPreparedBy
{
    /**
     * @return array{name: string|null, role: string|null}
     */
    public static function fromAuth(?Authenticatable $user = null): array
    {
        $user ??= auth('admin')->user();

        if (! $user instanceof Admin) {
            return [
                'name' => $user?->email ?? null,
                'role' => null,
            ];
        }

        $role = null;
        if (method_exists($user, 'getRoleNames')) {
            $roleNames = $user->getRoleNames();
            $role = $roleNames->isNotEmpty()
                ? (string) $roleNames->map(fn ($name) => str_replace(['-', '_'], ' ', (string) $name))->map(fn ($name) => ucwords($name))->implode(', ')
                : null;
        }

        return [
            'name' => $user->full_name !== '' ? $user->full_name : $user->email,
            'role' => $role,
        ];
    }
}
