@php
    $isAdmin = auth('admin')->check();
    $dashboardUrl = $isAdmin ? route('admin.dashboard') : route('customer.dashboard');
    $previousUrl = url()->previous();
    $docsBasePath = rtrim(url('/help/docs'), '/').'/';
    $sidebarDocument = $isAdmin ? '_sidebar.md' : '_sidebar-customer.md';
    $homeDocument = $isAdmin ? 'introduction.md' : 'introduction-customer.md';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>System User Manual</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/docsify@4/lib/themes/vue.css">
    <style>
        body {
            margin: 0;
            font-family: "Times New Roman", Times, serif;
            background: #f8fafc;
            color: #0f172a;
        }

        .help-topbar {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            justify-content: space-between;
            padding: 0.9rem 1.25rem;
            border-bottom: 1px solid #dbe4ef;
            background: #0a2540;
            color: #f8fafc;
            position: sticky;
            top: 0;
            z-index: 20;
        }

        .help-topbar h1 {
            margin: 0;
            font-size: 1rem;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .help-topbar-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .help-link,
        .help-button {
            border: 1px solid rgba(255, 255, 255, 0.24);
            background: rgba(255, 255, 255, 0.12);
            color: #f8fafc;
            text-decoration: none;
            border-radius: 0.55rem;
            padding: 0.45rem 0.7rem;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
        }

        .help-link:hover,
        .help-button:hover {
            background: rgba(255, 255, 255, 0.22);
        }

        #app {
            min-height: calc(100vh - 57px);
        }
    </style>
</head>
<body>
<header class="help-topbar">
    <h1>System User Manual</h1>
    <div class="help-topbar-actions">
        <button type="button" class="help-button" onclick="startTourFromHelp()">Start System Tour</button>
        <a href="{{ $dashboardUrl }}" class="help-link">Back to Dashboard</a>
    </div>
</header>

<div id="app"></div>

<script>
window.$docsify = {
    name: 'System User Manual',
    loadSidebar: @json($sidebarDocument),
    homepage: @json($homeDocument),
    basePath: @json($docsBasePath),
    subMaxLevel: 2,
    auto2top: true
};

function startTourFromHelp() {
    sessionStorage.setItem('startSystemTour', '1');

    var previousUrl = @json($previousUrl);
    var fallbackUrl = @json($dashboardUrl);

    try {
        if (previousUrl) {
            var previous = new URL(previousUrl, window.location.origin);
            if (previous.origin === window.location.origin && previous.pathname !== '/help') {
                window.location.href = previous.toString();
                return;
            }
        }
    } catch (e) {
    }

    window.location.href = fallbackUrl;
}
</script>
<script src="https://cdn.jsdelivr.net/npm/docsify@4/lib/docsify.min.js"></script>
</body>
</html>
