<!-- @guards:admin @permissions:reports.view -->
# Report Extraction Guide

This guide explains how to generate and extract reports from the system.

## Where to Find Reports

Menu path:

- `Reports`

Main report URLs:

- [`/admin/reports/arrears`](/admin/reports/arrears)
- [`/admin/reports/disbursements`](/admin/reports/disbursements)
- [`/admin/reports/collections`](/admin/reports/collections)
- [`/admin/reports/collection-split`](/admin/reports/collection-split)
- [`/admin/reports/loan-book`](/admin/reports/loan-book)
- [`/admin/reports/loan-performance`](/admin/reports/loan-performance)
- [`/admin/reports/branches`](/admin/reports/branches)
- [`/admin/reports/risk-heatmap`](/admin/reports/risk-heatmap)

## Standard Extraction Steps

1. Open the required report screen.
2. Set filters (date range, branch, product, company, etc.).
   For Disbursements, you can now narrow by `Date From` + `Time From` and `Date To` + `Time To`.
3. Click the generate/search action to load results.
4. Use the export button on that report page.

## Common Export Endpoints

Depending on report type, export routes include:

- [`/admin/reports/arrears/export`](/admin/reports/arrears/export)
- [`/admin/reports/arrears/export-summary`](/admin/reports/arrears/export-summary)
- [`/admin/reports/disbursements/export`](/admin/reports/disbursements/export)
- [`/admin/reports/disbursements/export-summary`](/admin/reports/disbursements/export-summary)
- [`/admin/reports/collections/export`](/admin/reports/collections/export)
- [`/admin/reports/collections/export-summary`](/admin/reports/collections/export-summary)
- [`/admin/reports/collection-split/export`](/admin/reports/collection-split/export)
- [`/admin/reports/loan-book/export`](/admin/reports/loan-book/export)
- [`/admin/reports/loan-book/export-summary`](/admin/reports/loan-book/export-summary)
- [`/admin/reports/loan-performance/export`](/admin/reports/loan-performance/export)

## Data Quality Checklist Before Export

- confirm date range and branch filters
- verify product/company scope
- compare totals with dashboard for reasonableness
- export only for authorized recipients

## Troubleshooting

- If export is empty, confirm filters are not too restrictive.
- If Reports menu is missing, request `reports.view` permission.
