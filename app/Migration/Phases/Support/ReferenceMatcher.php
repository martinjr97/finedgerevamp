<?php

namespace App\Migration\Phases\Support;

use App\Models\Admin;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\FinancialInstitution;
use App\Models\WalletProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ReferenceMatcher
{
    /**
     * @param  array<string, mixed>  $legacyBank
     */
    public function matchFinancialInstitution(array $legacyBank): ?FinancialInstitution
    {
        $result = $this->resolveFinancialInstitution($legacyBank);

        return $result->target instanceof FinancialInstitution ? $result->target : null;
    }

    /**
     * @param  array<string, mixed>  $legacyBank
     */
    public function resolveFinancialInstitution(array $legacyBank): ReferenceMatchResult
    {
        $code = strtoupper(trim((string) ($legacyBank['bank_code'] ?? $legacyBank['code'] ?? '')));
        if ($code !== '') {
            $byCode = FinancialInstitution::query()->where('code', $code)->get();
            if ($byCode->count() > 1) {
                return ReferenceMatchResult::conflict('duplicate_fi_code_in_target', $byCode->pluck('id')->all());
            }
            if ($byCode->count() === 1) {
                return ReferenceMatchResult::matched($byCode->first(), 'code');
            }
        }

        $name = $this->normalize((string) ($legacyBank['bank_name'] ?? $legacyBank['name'] ?? ''));
        if ($name === '') {
            return ReferenceMatchResult::unmatched();
        }

        $exact = FinancialInstitution::query()->get()->filter(
            fn (FinancialInstitution $fi) => $this->normalize($fi->name) === $name
        );
        if ($exact->count() > 1) {
            return ReferenceMatchResult::conflict('ambiguous_fi_name', $exact->pluck('id')->all());
        }
        if ($exact->count() === 1) {
            return ReferenceMatchResult::matched($exact->first(), 'name_exact');
        }

        $fuzzy = FinancialInstitution::query()->get()->filter(function (FinancialInstitution $fi) use ($name) {
            $target = $this->normalize($fi->name);

            return $target !== '' && (str_contains($name, $target) || str_contains($target, $name));
        });
        if ($fuzzy->count() > 1) {
            return ReferenceMatchResult::conflict('ambiguous_fi_fuzzy_name', $fuzzy->pluck('id')->all());
        }
        if ($fuzzy->count() === 1) {
            return ReferenceMatchResult::matched($fuzzy->first(), 'name_fuzzy');
        }

        return ReferenceMatchResult::unmatched();
    }

    /**
     * @param  array<string, mixed>  $legacyWallet
     */
    public function matchWalletProvider(array $legacyWallet): ?WalletProvider
    {
        $result = $this->resolveWalletProvider($legacyWallet);

        return $result->target instanceof WalletProvider ? $result->target : null;
    }

    /**
     * @param  array<string, mixed>  $legacyWallet
     */
    public function resolveWalletProvider(array $legacyWallet): ReferenceMatchResult
    {
        $code = strtoupper(trim((string) ($legacyWallet['code'] ?? $legacyWallet['provider_code'] ?? '')));
        $codeAliases = [
            'AIRTEL' => 'AIRTEL_MONEY',
            'MTN' => 'MTN_MONEY',
            'ZAMTEL' => 'ZAMTEL_MONEY',
        ];
        $lookupCode = $codeAliases[$code] ?? $code;
        if ($lookupCode !== '') {
            $byCode = WalletProvider::query()->where('code', $lookupCode)->get();
            if ($byCode->count() > 1) {
                return ReferenceMatchResult::conflict('duplicate_wallet_code_in_target', $byCode->pluck('id')->all());
            }
            if ($byCode->count() === 1) {
                return ReferenceMatchResult::matched($byCode->first(), 'code');
            }
        }

        $name = $this->normalize((string) ($legacyWallet['name'] ?? $legacyWallet['wallet_name'] ?? ''));
        if ($name === '') {
            return ReferenceMatchResult::unmatched();
        }

        $exact = WalletProvider::query()->get()->filter(
            fn (WalletProvider $provider) => $this->normalize($provider->name) === $name
        );
        if ($exact->count() > 1) {
            return ReferenceMatchResult::conflict('ambiguous_wallet_name', $exact->pluck('id')->all());
        }
        if ($exact->count() === 1) {
            return ReferenceMatchResult::matched($exact->first(), 'name_exact');
        }

        $fuzzy = WalletProvider::query()->get()->filter(function (WalletProvider $provider) use ($name) {
            $target = $this->normalize($provider->name);

            return $target !== '' && (str_contains($name, $target) || str_contains($target, $name));
        });
        if ($fuzzy->count() > 1) {
            return ReferenceMatchResult::conflict('ambiguous_wallet_fuzzy_name', $fuzzy->pluck('id')->all());
        }
        if ($fuzzy->count() === 1) {
            return ReferenceMatchResult::matched($fuzzy->first(), 'name_fuzzy');
        }

        return ReferenceMatchResult::unmatched();
    }

    /**
     * @param  array<string, mixed>  $legacyBranch
     */
    public function resolveBranch(array $legacyBranch): ReferenceMatchResult
    {
        $legacyId = (string) ($legacyBranch['id'] ?? '');
        $code = strtoupper(trim((string) ($legacyBranch['code'] ?? '')));
        if ($code === '' && $legacyId !== '') {
            $code = 'LEG-B'.$legacyId;
        }

        if ($code !== '') {
            $byCode = Branch::query()->where('code', $code)->get();
            if ($byCode->count() > 1) {
                return ReferenceMatchResult::conflict('duplicate_branch_code_in_target', $byCode->pluck('id')->all());
            }
            if ($byCode->count() === 1) {
                return ReferenceMatchResult::matched($byCode->first(), 'code');
            }
        }

        $normalizedName = $this->normalize((string) ($legacyBranch['name'] ?? ''));
        if ($normalizedName === '') {
            return ReferenceMatchResult::unmatched();
        }

        $exact = Branch::query()->get()->filter(
            fn (Branch $branch) => $this->normalize($branch->name) === $normalizedName
        );
        if ($exact->count() > 1) {
            return ReferenceMatchResult::conflict('ambiguous_branch_name', $exact->pluck('id')->all());
        }
        if ($exact->count() === 1) {
            return ReferenceMatchResult::matched($exact->first(), 'name_exact');
        }

        $fuzzy = Branch::query()->get()->filter(function (Branch $branch) use ($normalizedName) {
            $target = $this->normalize($branch->name);

            return $target !== '' && (str_contains($normalizedName, $target) || str_contains($target, $normalizedName));
        });
        if ($fuzzy->count() > 1) {
            return ReferenceMatchResult::conflict('ambiguous_branch_fuzzy_name', $fuzzy->pluck('id')->all());
        }
        if ($fuzzy->count() === 1) {
            return ReferenceMatchResult::matched($fuzzy->first(), 'name_fuzzy');
        }

        return ReferenceMatchResult::unmatched();
    }

    /**
     * @param  array<string, mixed>  $legacyRm
     */
    public function resolveRelationshipManagerAdmin(array $legacyRm): ReferenceMatchResult
    {
        $email = strtolower(trim((string) ($legacyRm['email'] ?? '')));
        if ($email !== '') {
            $byEmail = Admin::query()->whereRaw('LOWER(email) = ?', [$email])->get();
            if ($byEmail->count() > 1) {
                return ReferenceMatchResult::conflict('duplicate_admin_email', $byEmail->pluck('id')->all());
            }
            if ($byEmail->count() === 1) {
                return ReferenceMatchResult::matched($byEmail->first(), 'email');
            }
        }

        $nrc = trim((string) ($legacyRm['nrc'] ?? ''));
        if ($nrc !== '') {
            $byNrc = Admin::query()->where('nrc', $nrc)->get();
            if ($byNrc->count() > 1) {
                return ReferenceMatchResult::conflict('duplicate_admin_nrc', $byNrc->pluck('id')->all());
            }
            if ($byNrc->count() === 1) {
                return ReferenceMatchResult::matched($byNrc->first(), 'nrc');
            }
        }

        $first = $this->normalizePersonName((string) ($legacyRm['first_name'] ?? ''));
        $last = $this->normalizePersonName((string) ($legacyRm['last_name'] ?? ''));
        if ($first !== '' && $last !== '') {
            $byName = Admin::query()
                ->where('is_relationship_manager', true)
                ->get()
                ->filter(function (Admin $admin) use ($first, $last) {
                    return $this->normalizePersonName($admin->first_name) === $first
                        && $this->normalizePersonName($admin->last_name) === $last;
                });
            if ($byName->count() > 1) {
                return ReferenceMatchResult::conflict('ambiguous_rm_name', $byName->pluck('id')->all());
            }
            if ($byName->count() === 1) {
                return ReferenceMatchResult::matched($byName->first(), 'name_exact');
            }
        }

        return ReferenceMatchResult::unmatched();
    }

    public function isTreasuryWallet(array $legacyWallet): bool
    {
        $name = strtolower((string) ($legacyWallet['name'] ?? $legacyWallet['wallet_name'] ?? ''));

        return str_contains($name, 'kazang') || str_contains($name, 'treasury') || str_contains($name, 'operator');
    }

    /**
     * @param  array<string, mixed>  $legacyBank
     */
    public function matchTreasuryBank(array $legacyBank): ?Bank
    {
        $code = strtoupper(trim((string) ($legacyBank['code'] ?? '')));

        return Bank::query()->get()->first(function (Bank $bank) use ($code, $legacyBank) {
            $bankName = strtoupper($bank->bank_name ?? $bank->name ?? '');
            if ($code === 'FNB' && str_contains($bankName, 'FNB')) {
                return true;
            }
            if ($code === 'ZICB' && str_contains($bankName, 'ZICB')) {
                return true;
            }

            return str_contains($bankName, strtoupper($legacyBank['name'] ?? ''));
        });
    }

    /**
     * @return list<array{id: string, name: string, code: string, address: string|null, inferred: bool}>
     */
    public function legacyBranchCatalog($legacy): array
    {
        $rows = collect();
        try {
            $rows = $legacy->table('branches')->get();
        } catch (\Throwable) {
            $rows = collect();
        }

        if ($rows->isNotEmpty()) {
            return $rows->map(function ($row) {
                $branch = (array) $row;

                return [
                    'id' => (string) ($branch['id'] ?? ''),
                    'name' => (string) ($branch['name'] ?? 'Legacy Branch'),
                    'code' => strtoupper((string) ($branch['code'] ?? ('LEG-B'.($branch['id'] ?? '')))),
                    'address' => $branch['address'] ?? null,
                    'inferred' => false,
                ];
            })->values()->all();
        }

        $catalog = [];
        foreach ($this->inferBranchesFromRelationshipManagers($legacy) as $inferred) {
            $catalog[] = $inferred;
        }

        return $catalog;
    }

    /**
     * @return Collection<int, array{id: string, name: string, code: string, address: string|null, inferred: bool}>
     */
    private function inferBranchesFromRelationshipManagers($legacy): Collection
    {
        $catalog = collect();
        try {
            $managers = $legacy->table('relationship_managers')->get();
        } catch (\Throwable) {
            return $catalog;
        }

        $cityHints = [
            'kitwe' => ['name' => 'Kitwe Branch', 'code' => 'KTW'],
            'ndola' => ['name' => 'Ndola Branch', 'code' => 'NDL'],
            'mongu' => ['name' => 'Mongu Branch', 'code' => 'MNG'],
            'lusaka' => ['name' => 'Lusaka Branch', 'code' => 'LSK'],
        ];

        foreach ($managers as $manager) {
            $address = strtolower((string) ($manager->address ?? ''));
            foreach ($cityHints as $keyword => $definition) {
                if (! str_contains($address, $keyword)) {
                    continue;
                }
                $catalog->put($definition['code'], [
                    'id' => 'inferred:'.$definition['code'],
                    'name' => $definition['name'],
                    'code' => $definition['code'],
                    'address' => $manager->address ?? null,
                    'inferred' => true,
                ]);
            }
        }

        if ($catalog->isEmpty()) {
            $catalog->put('HEAD_OFFICE', [
                'id' => 'inferred:HEAD_OFFICE',
                'name' => 'Head Office',
                'code' => 'HEAD_OFFICE',
                'address' => null,
                'inferred' => true,
            ]);
        }

        return $catalog;
    }

    public function inferBranchCodeFromAddress(?string $address): ?string
    {
        $normalized = strtolower(trim((string) $address));
        if ($normalized === '') {
            return null;
        }

        return match (true) {
            str_contains($normalized, 'kitwe') => 'KTW',
            str_contains($normalized, 'ndola'), str_contains($normalized, 'copperbelt') => 'NDL',
            str_contains($normalized, 'mongu') => 'MNG',
            str_contains($normalized, 'lusaka') => 'LSK',
            default => null,
        };
    }

    private function normalize(string $value): string
    {
        return strtoupper(preg_replace('/\s+/', '', $value) ?? '');
    }

    private function normalizePersonName(string $value): string
    {
        return strtoupper(trim(preg_replace('/\s+/', ' ', $value) ?? ''));
    }
}
