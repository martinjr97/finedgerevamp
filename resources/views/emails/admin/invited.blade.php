@component('mail::message')
# Welcome, {{ $admin->full_name }}

You’ve been granted administrator access to **{{ $company }}** on **{{ $systemName }}**.

@component('mail::panel')
**Your credentials**

- Portal: {{ $loginUrl }}
- Email: {{ $admin->email }}
- Temporary password: {{ $temporaryPassword }}
@endcomponent

@component('mail::button', ['url' => $loginUrl])
Launch Admin Portal
@endcomponent

## What to do next
- Sign in using the details above on **{{ $portalHost }}** only.
- Create a new, strong password when prompted. Use at least 12 characters with a mix of words, numbers, and symbols.
- Turn on device screen lock and keep this email private.
- If you didn’t expect this access, stop and contact support immediately.

## Security tips
- Never share your password or one‑time codes with anyone.
- Always verify the site address is **{{ $portalHost }}** before entering credentials.
- Log out after each session on shared devices.

## Need help?
Email: {{ $supportEmail }}  
Phone: {{ $supportPhone }}

Thanks,  
**{{ $appName }} Team**
@endcomponent
