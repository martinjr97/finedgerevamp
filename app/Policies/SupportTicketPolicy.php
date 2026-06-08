<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\SupportTicket;
use App\Support\PermissionMatrix;

class SupportTicketPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return true;
    }

    public function create(Admin $admin): bool
    {
        return true;
    }

    public function view(Admin $admin, SupportTicket $ticket): bool
    {
        return $this->canAccessTicket($admin, $ticket);
    }

    public function assign(Admin $admin, SupportTicket $ticket): bool
    {
        if (! $this->canAccessTicket($admin, $ticket)) {
            return false;
        }

        return $this->canManageAll($admin);
    }

    public function comment(Admin $admin, SupportTicket $ticket): bool
    {
        if (! $this->canAccessTicket($admin, $ticket)) {
            return false;
        }

        if ($this->canManageAll($admin)) {
            return true;
        }

        return (int) $ticket->assigned_to_id === (int) $admin->id;
    }

    public function updateStatus(Admin $admin, SupportTicket $ticket): bool
    {
        return $this->comment($admin, $ticket);
    }

    public function viewAsCustomer(Customer $customer, SupportTicket $ticket): bool
    {
        return $ticket->customer_id !== null
            && (int) $ticket->customer_id === (int) $customer->id;
    }

    public function commentAsCustomer(Customer $customer, SupportTicket $ticket): bool
    {
        return $this->viewAsCustomer($customer, $ticket) && $ticket->canCustomerComment();
    }

    protected function canManageAll(Admin $admin): bool
    {
        return $admin->hasRole(PermissionMatrix::SUPER_ADMIN_ROLE)
            || $admin->isPrimaryCompanyAdmin();
    }

    protected function canAccessTicket(Admin $admin, SupportTicket $ticket): bool
    {
        if ($this->canManageAll($admin)) {
            return true;
        }

        if ((int) $ticket->assigned_to_id === (int) $admin->id) {
            return true;
        }

        $companyFilterId = $admin->getCompanyFilterId();

        if ($companyFilterId === null) {
            return true;
        }

        if ($ticket->customer_id === null) {
            return true;
        }

        return (int) optional($ticket->customer)->company_id === (int) $companyFilterId;
    }
}
