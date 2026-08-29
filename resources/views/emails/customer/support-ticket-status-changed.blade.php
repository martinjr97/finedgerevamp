<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Support Ticket Update</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; font-size: 14px; color: #0f172a; background: #f8fafc; padding: 16px; }
        .card { max-width: 640px; margin: 0 auto; background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 20px; }
        .heading { font-size: 18px; font-weight: 600; margin-bottom: 8px; }
        .meta { font-size: 12px; color: #64748b; margin-bottom: 16px; }
        .status { display: inline-block; padding: 4px 10px; border-radius: 999px; background: #dbeafe; color: #1e40af; font-size: 12px; font-weight: 600; }
        .message { margin-top: 16px; padding: 12px; border-radius: 8px; background: #f1f5f9; white-space: pre-line; }
        .button { display: inline-block; margin-top: 16px; padding: 10px 16px; background: #2563eb; color: #fff !important; text-decoration: none; border-radius: 8px; font-weight: 600; }
    </style>
</head>
<body>
    <div class="card">
        <div class="heading">Support ticket #{{ $ticket->id }} updated</div>
        <div class="meta">{{ config('app.system_name') }}</div>

        <p>Hello {{ $customer->first_name }},</p>
        <p>Your support request <strong>{{ $ticket->subject }}</strong> has been updated.</p>

        <p>
            Status changed from
            <span class="status">{{ $previousStatusLabel }}</span>
            to
            <span class="status">{{ $newStatusLabel }}</span>
        </p>

        @if($staffComment)
            <div class="message">{{ $staffComment }}</div>
        @endif

        <p>You can view the full conversation in your account.</p>
        <a href="{{ $ticketUrl }}" class="button">View Support Ticket</a>
    </div>
</body>
</html>
