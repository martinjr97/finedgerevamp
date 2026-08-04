@props([])
{{--
    Opt-in admin listing table card.
    Wrap a simple-datatables table ([data-datatable]) or a Laravel-paginated table.
    Optional bulk UI: include [data-admin-table-bulk] inside the slot (page-specific only).
--}}
<div {{ $attributes->class(['admin-data-table']) }}>
    {{ $slot }}
</div>
