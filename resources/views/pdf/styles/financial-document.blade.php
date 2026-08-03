* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

@page {
    size: A4 portrait;
    margin: 18mm 15mm 18mm 15mm;
}

body {
    font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
    font-size: 8.5pt;
    line-height: 1.35;
    color: #1e293b;
    background: #ffffff;
}

.page {
    width: 100%;
}

.doc-header {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 10px;
    border-bottom: 2px solid #0f172a;
    padding-bottom: 6px;
}

.doc-header td {
    vertical-align: middle;
    padding: 0;
}

.doc-header .logo-cell {
    width: 54px;
    padding-right: 8px;
}

.doc-header .logo {
    max-width: 48px;
    max-height: 48px;
}

.doc-header .brand-name {
    font-size: 12pt;
    font-weight: 700;
    color: #0f172a;
    letter-spacing: 0.3px;
    text-transform: uppercase;
}

.doc-header .brand-descriptor {
    font-size: 8pt;
    color: #475569;
    margin-top: 1px;
}

.doc-header .meta-cell {
    width: 42%;
    text-align: right;
    font-size: 7.5pt;
    color: #334155;
    line-height: 1.4;
}

.doc-header .meta-cell .meta-strong {
    font-weight: 700;
    color: #0f172a;
}

.doc-title-block {
    text-align: center;
    margin: 8px 0 10px;
}

.doc-title-block h1 {
    font-size: 13pt;
    font-weight: 700;
    letter-spacing: 1.2px;
    color: #0f172a;
    text-transform: uppercase;
}

.doc-title-block .subtitle {
    font-size: 8.5pt;
    color: #475569;
    margin-top: 2px;
}

.status-badge {
    display: inline-block;
    margin-top: 4px;
    padding: 1px 7px;
    border-radius: 10px;
    font-size: 7pt;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    border: 1px solid #cbd5e1;
    color: #334155;
    background: #f8fafc;
}

.status-badge.active { color: #065f46; background: #ecfdf5; border-color: #a7f3d0; }
.status-badge.defaulted { color: #9f1239; background: #fff1f2; border-color: #fecdd3; }
.status-badge.closed { color: #1e3a8a; background: #eff6ff; border-color: #bfdbfe; }

.section-title {
    font-size: 8pt;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: #0f172a;
    margin: 0 0 4px;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 2px;
}

.identity-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 8px;
}

.identity-table td {
    width: 50%;
    vertical-align: top;
    padding: 0 6px 0 0;
}

.identity-table td + td {
    padding: 0 0 0 6px;
    border-left: 1px solid #e2e8f0;
}

.field-grid {
    width: 100%;
    border-collapse: collapse;
}

.field-grid td {
    width: 50%;
    vertical-align: top;
    padding: 0 4px 5px 0;
}

.field-label {
    font-size: 6.5pt;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: #64748b;
    display: block;
}

.field-value {
    font-size: 8pt;
    color: #0f172a;
    font-weight: 600;
}

.summary-strip {
    width: 100%;
    border-collapse: collapse;
    margin: 6px 0 10px;
    background: #f8fafc;
    border: 1px solid #cbd5e1;
}

.summary-strip th,
.summary-strip td {
    padding: 5px 6px;
    text-align: center;
    border-right: 1px solid #e2e8f0;
}

.summary-strip th:last-child,
.summary-strip td:last-child {
    border-right: none;
}

.summary-strip th {
    font-size: 6.5pt;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: #64748b;
    font-weight: 700;
    padding-bottom: 1px;
}

.summary-strip td {
    font-size: 8.5pt;
    font-weight: 700;
    color: #0f172a;
    padding-top: 0;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 8px;
    table-layout: fixed;
}

.data-table thead {
    display: table-header-group;
}

.data-table tfoot {
    display: table-row-group;
}

.data-table th {
    background: #0f172a;
    color: #ffffff;
    font-size: 7pt;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    font-weight: 700;
    padding: 5px 4px;
    border: 1px solid #0f172a;
    text-align: left;
}

.data-table td {
    font-size: 7.5pt;
    padding: 4px;
    border: 1px solid #e2e8f0;
    vertical-align: middle;
    color: #1e293b;
}

.data-table tbody tr:nth-child(even) td {
    background: #f8fafc;
}

.data-table .num {
    text-align: right;
    white-space: nowrap;
}

.data-table .center {
    text-align: center;
}

.data-table tfoot td {
    background: #e2e8f0;
    font-weight: 700;
    border-top: 1.5px solid #0f172a;
}

.repayment-row,
.summary-block,
.signature-block,
.signature-section,
.document-certification,
.loan-summary-block {
    page-break-inside: avoid;
}

.data-table tr {
    page-break-inside: avoid;
}

.status-pill {
    display: inline-block;
    padding: 1px 5px;
    border-radius: 8px;
    font-size: 6.5pt;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.2px;
}

.status-paid { color: #065f46; background: #d1fae5; }
.status-partial { color: #92400e; background: #fef3c7; }
.status-overdue { color: #9f1239; background: #ffe4e6; }
.status-upcoming { color: #334155; background: #e2e8f0; }
.status-waived { color: #5b21b6; background: #ede9fe; }
.status-cancelled { color: #475569; background: #f1f5f9; }

.note {
    font-size: 7pt;
    color: #475569;
    margin: 6px 0 8px;
    line-height: 1.35;
}

.signature-section {
    margin-top: 14px;
    page-break-inside: avoid;
}

.signature-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}

.signature-cell {
    width: 46%;
    vertical-align: bottom;
    border: 0;
    padding: 0;
}

.signature-spacer {
    width: 8%;
    border: 0;
}

.signature-right {
    text-align: right;
}

.signature-label {
    font-size: 7pt;
    font-weight: 700;
    color: #0f172a;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}

.signature-line {
    border-top: 1px solid #1f2937;
    margin-top: 28px;
    padding-top: 5px;
}

.signature-name {
    font-size: 8pt;
    font-weight: 600;
    color: #0f172a;
    margin-top: 2px;
}

.signature-meta {
    font-size: 6.5pt;
    color: #64748b;
    margin-top: 1px;
}

.document-certification {
    margin-top: 8px;
    padding-top: 6px;
    border-top: 1px solid #cbd5e1;
    font-size: 7pt;
    color: #475569;
}

.document-certification .cert-line {
    margin-bottom: 2px;
}

.doc-footer {
    margin-top: 8px;
    font-size: 6.5pt;
    color: #64748b;
    border-top: 1px solid #e2e8f0;
    padding-top: 4px;
}

.empty-state {
    padding: 16px;
    text-align: center;
    border: 1px dashed #cbd5e1;
    color: #64748b;
    font-size: 8pt;
    margin: 8px 0;
}

.muted {
    color: #64748b;
    font-weight: 400;
}

.desc-primary {
    font-weight: 600;
    color: #0f172a;
}

.desc-secondary {
    display: block;
    font-size: 6.5pt;
    color: #64748b;
    font-weight: 400;
    margin-top: 1px;
}

.datetime-primary {
    font-weight: 600;
}

.datetime-secondary {
    display: block;
    font-size: 6.5pt;
    color: #64748b;
}
