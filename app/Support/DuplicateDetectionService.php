<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Loan;
use Illuminate\Support\Collection;

class DuplicateDetectionService
{
    /**
     * Detect possible duplicate customers based on various criteria
     */
    public static function detectDuplicates(Customer $customer): array
    {
        // Get cleared duplicate alerts for this customer
        $clearedAlerts = \App\Models\DuplicateAlert::where('customer_id', $customer->id)
            ->whereNotNull('cleared_at')
            ->get()
            ->keyBy(function ($alert) {
                return $alert->duplicate_customer_id . '_' . $alert->match_type . '_' . ($alert->match_value ?? '');
            });

        $duplicates = [
            'same_nrc' => [],
            'same_phone' => [],
            'same_bank_account' => [],
            'same_device_ip' => [],
        ];

        // 1. Check for same NRC/National ID
        if ($customer->national_id) {
            $duplicates['same_nrc'] = Customer::where('id', '!=', $customer->id)
                ->where('national_id', $customer->national_id)
                ->whereNotNull('national_id')
                ->get()
                ->filter(function($c) use ($clearedAlerts) {
                    $key = $c->id . '_same_nrc_' . $customer->national_id;
                    return !$clearedAlerts->has($key);
                })
                ->map(fn($c) => self::formatCustomerForDisplay($c, 'Same NRC/National ID'))
                ->toArray();
        }

        // 2. Check for same phone number
        if ($customer->phone) {
            $duplicates['same_phone'] = Customer::where('id', '!=', $customer->id)
                ->where('phone', $customer->phone)
                ->whereNotNull('phone')
                ->get()
                ->filter(function($c) use ($clearedAlerts) {
                    $key = $c->id . '_same_phone_' . $customer->phone;
                    return !$clearedAlerts->has($key);
                })
                ->map(fn($c) => self::formatCustomerForDisplay($c, 'Same Phone Number'))
                ->toArray();
        }

        // 3. Check for same bank account (via loan disbursements)
        $duplicates['same_bank_account'] = self::detectSameBankAccount($customer);

        // 4. Check for same device/IP from login audits
        $duplicates['same_device_ip'] = self::detectSameDeviceOrIp($customer);

        // Calculate total duplicate count
        $totalDuplicates = collect($duplicates)->flatten(1)->unique('id')->count();

        return [
            'duplicates' => $duplicates,
            'total_count' => $totalDuplicates,
            'has_duplicates' => $totalDuplicates > 0,
        ];
    }

    /**
     * Detect customers with same bank account via loan disbursements
     */
    private static function detectSameBankAccount(Customer $customer): array
    {
        // Get cleared duplicate alerts for this customer
        $clearedAlerts = \App\Models\DuplicateAlert::where('customer_id', $customer->id)
            ->where('match_type', 'same_bank_account')
            ->whereNotNull('cleared_at')
            ->pluck('duplicate_customer_id')
            ->toArray();

        // Get all loans for this customer with bank/wallet disbursements
        $customerLoans = Loan::where('customer_id', $customer->id)
            ->whereNotNull('disbursed_via_type')
            ->whereNotNull('disbursed_via_id')
            ->get();

        if ($customerLoans->isEmpty()) {
            return [];
        }

        $duplicateCustomers = collect();

        foreach ($customerLoans as $loan) {
            // Find other loans disbursed to the same bank/wallet
            $otherLoans = Loan::where('customer_id', '!=', $customer->id)
                ->where('disbursed_via_type', $loan->disbursed_via_type)
                ->where('disbursed_via_id', $loan->disbursed_via_id)
                ->with('customer')
                ->get();

            foreach ($otherLoans as $otherLoan) {
                if ($otherLoan->customer && !in_array($otherLoan->customer->id, $clearedAlerts)) {
                    $duplicateCustomers->push($otherLoan->customer);
                }
            }
        }

        return $duplicateCustomers
            ->unique('id')
            ->map(fn($c) => self::formatCustomerForDisplay($c, 'Same Bank Account'))
            ->values()
            ->toArray();
    }

    /**
     * Detect customers with same device or IP from login audits
     */
    private static function detectSameDeviceOrIp(Customer $customer): array
    {
        // Get cleared duplicate alerts for this customer
        $clearedAlerts = \App\Models\DuplicateAlert::where('customer_id', $customer->id)
            ->where('match_type', 'same_device_ip')
            ->whereNotNull('cleared_at')
            ->get()
            ->keyBy(function ($alert) {
                return $alert->duplicate_customer_id . '_' . ($alert->match_value ?? '');
            });

        // Get all unique IPs and devices from customer login audits
        $customerLoginAudits = \App\Models\CustomerLoginAudit::where('customer_id', $customer->id)
            ->where('status', 'success')
            ->get();

        if ($customerLoginAudits->isEmpty()) {
            // Fallback to last_login_ip if no login audits
            if ($customer->last_login_ip) {
                return Customer::where('id', '!=', $customer->id)
                    ->where('last_login_ip', $customer->last_login_ip)
                    ->whereNotNull('last_login_ip')
                    ->get()
                    ->filter(function($c) use ($clearedAlerts, $customer) {
                        $key = $c->id . '_' . $customer->last_login_ip;
                        return !$clearedAlerts->has($key);
                    })
                    ->map(fn($c) => self::formatCustomerForDisplay($c, 'Same IP Address (from last login)'))
                    ->toArray();
            }
            return [];
        }

        // Get unique IPs and devices
        $customerIps = $customerLoginAudits->pluck('ip_address')->filter()->unique()->toArray();
        $customerDevices = $customerLoginAudits->whereNotNull('device_name')
            ->pluck('device_name')
            ->filter()
            ->unique()
            ->toArray();
        $customerDeviceTypes = $customerLoginAudits->whereNotNull('device_type')
            ->pluck('device_type')
            ->filter()
            ->unique()
            ->toArray();

        $duplicateCustomers = collect();

        // Find customers with same IPs
        if (!empty($customerIps)) {
            $sameIpCustomers = \App\Models\CustomerLoginAudit::whereIn('ip_address', $customerIps)
                ->where('customer_id', '!=', $customer->id)
                ->where('status', 'success')
                ->with('customer')
                ->get()
                ->pluck('customer')
                ->filter()
                ->unique('id');

            foreach ($sameIpCustomers as $sameIpCustomer) {
                // Find the matching IP
                $matchingIp = \App\Models\CustomerLoginAudit::where('customer_id', $sameIpCustomer->id)
                    ->whereIn('ip_address', $customerIps)
                    ->where('status', 'success')
                    ->first();

                // Check if this match has been cleared
                $key = $sameIpCustomer->id . '_' . ($matchingIp?->ip_address ?? '');
                if ($clearedAlerts->has($key)) {
                    continue;
                }

                $locationParts = [];
                if ($matchingIp?->location_city) {
                    $locationParts[] = $matchingIp->location_city;
                }
                if ($matchingIp?->location_country) {
                    $locationParts[] = $matchingIp->location_country;
                }
                
                $duplicateCustomers->push([
                    'customer' => $sameIpCustomer,
                    'match_type' => 'ip',
                    'match_value' => $matchingIp?->ip_address,
                    'match_details' => [
                        'ip' => $matchingIp?->ip_address,
                        'location' => !empty($locationParts) ? implode(', ', $locationParts) : null,
                        'last_seen' => $matchingIp?->attempted_at?->format('Y-m-d H:i'),
                    ],
                ]);
            }
        }

        // Find customers with same devices
        if (!empty($customerDevices)) {
            $sameDeviceCustomers = \App\Models\CustomerLoginAudit::whereIn('device_name', $customerDevices)
                ->where('customer_id', '!=', $customer->id)
                ->where('status', 'success')
                ->with('customer')
                ->get()
                ->pluck('customer')
                ->filter()
                ->unique('id');

            foreach ($sameDeviceCustomers as $sameDeviceCustomer) {
                // Skip if already added via IP match
                if ($duplicateCustomers->contains(function ($item) use ($sameDeviceCustomer) {
                    return $item['customer']->id === $sameDeviceCustomer->id;
                })) {
                    continue;
                }

                // Find the matching device
                $matchingDevice = \App\Models\CustomerLoginAudit::where('customer_id', $sameDeviceCustomer->id)
                    ->whereIn('device_name', $customerDevices)
                    ->where('status', 'success')
                    ->first();

                // Check if this match has been cleared
                $key = $sameDeviceCustomer->id . '_' . ($matchingDevice?->device_name ?? '');
                if ($clearedAlerts->has($key)) {
                    continue;
                }

                $duplicateCustomers->push([
                    'customer' => $sameDeviceCustomer,
                    'match_type' => 'device',
                    'match_value' => $matchingDevice?->device_name,
                    'match_details' => [
                        'device_name' => $matchingDevice?->device_name,
                        'device_type' => $matchingDevice?->device_type,
                        'os' => $matchingDevice?->os,
                        'browser' => $matchingDevice?->browser,
                        'last_seen' => $matchingDevice?->attempted_at?->format('Y-m-d H:i'),
                    ],
                ]);
            }
        }

        return $duplicateCustomers
            ->unique(function ($item) {
                return $item['customer']->id;
            })
            ->map(function ($item) {
                $matchReason = $item['match_type'] === 'ip' 
                    ? 'Same IP Address: ' . $item['match_value']
                    : 'Same Device: ' . $item['match_value'];
                
                return array_merge(
                    self::formatCustomerForDisplay($item['customer'], $matchReason),
                    ['match_details' => $item['match_details']]
                );
            })
            ->values()
            ->toArray();
    }

    /**
     * Format customer for display in duplicate alerts
     */
    private static function formatCustomerForDisplay(Customer $customer, string $matchReason): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->full_name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'national_id' => $customer->national_id,
            'status' => $customer->status,
            'created_at' => $customer->created_at?->format('Y-m-d'),
            'match_reason' => $matchReason,
        ];
    }

    /**
     * Get all customers with potential duplicates
     */
    public static function getAllCustomersWithDuplicates(): Collection
    {
        $customersWithDuplicates = collect();

        $customers = Customer::where('status', '!=', 'closed')->get();

        foreach ($customers as $customer) {
            $duplicateInfo = self::detectDuplicates($customer);
            
            if ($duplicateInfo['has_duplicates']) {
                $customersWithDuplicates->push([
                    'customer' => $customer,
                    'duplicate_info' => $duplicateInfo,
                ]);
            }
        }

        return $customersWithDuplicates->sortByDesc(function ($item) {
            return $item['duplicate_info']['total_count'];
        });
    }

    /**
     * Get duplicate statistics
     */
    public static function getStatistics(): array
    {
        $customers = Customer::where('status', '!=', 'closed')->get();
        
        $stats = [
            'total_customers' => $customers->count(),
            'customers_with_duplicates' => 0,
            'total_duplicate_matches' => 0,
            'by_type' => [
                'same_nrc' => 0,
                'same_phone' => 0,
                'same_bank_account' => 0,
                'same_device_ip' => 0,
            ],
        ];

        foreach ($customers as $customer) {
            $duplicateInfo = self::detectDuplicates($customer);
            
            if ($duplicateInfo['has_duplicates']) {
                $stats['customers_with_duplicates']++;
                $stats['total_duplicate_matches'] += $duplicateInfo['total_count'];
                
                foreach ($duplicateInfo['duplicates'] as $type => $matches) {
                    if (!empty($matches)) {
                        $stats['by_type'][$type] += count($matches);
                    }
                }
            }
        }

        return $stats;
    }
}

