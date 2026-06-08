<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New Support Request</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            font-size: 14px;
            color: #0f172a;
            background-color: #f8fafc;
            padding: 16px;
        }
        .card {
            max-width: 640px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 16px 20px;
        }
        .heading {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 12px;
        }
        .meta {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 16px;
        }
        .label {
            font-weight: 600;
            color: #0f172a;
        }
        .value {
            color: #1e293b;
        }
        .row {
            margin-bottom: 8px;
        }
        .message-box {
            margin-top: 12px;
            padding: 10px 12px;
            border-radius: 8px;
            background-color: #f1f5f9;
            border: 1px solid #e2e8f0;
            white-space: pre-line;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="heading">New Support Request</div>
        <div class="meta">
            System: <strong>{{ config('app.system_name') }}</strong><br>
            Submitted at: {{ $ticket->created_at?->format('d M Y H:i') }}
        </div>

        <div class="row">
            <span class="label">From:</span>
            <span class="value">{{ $ticket->name }}</span>
        </div>
        @if($ticket->email)
            <div class="row">
                <span class="label">Email:</span>
                <span class="value">{{ $ticket->email }}</span>
            </div>
        @endif
        @if($ticket->phone)
            <div class="row">
                <span class="label">Phone:</span>
                <span class="value">{{ $ticket->phone }}</span>
            </div>
        @endif
        <div class="row">
            <span class="label">Subject:</span>
            <span class="value">{{ $ticket->subject }}</span>
        </div>

        <div class="message-box">
            {{ $ticket->message }}
        </div>
    </div>
</body>
</html>


