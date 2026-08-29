@component('mail::message')
# Support ticket #{{ $ticket->id }} updated

Hello {{ $customer->first_name }},

Your support request **{{ $ticket->subject }}** has been updated.

**Status:** {{ $previousStatusLabel }} → **{{ $newStatusLabel }}**

@if($staffComment)
@component('mail::panel')
{{ $staffComment }}
@endcomponent
@endif

@component('mail::button', ['url' => $ticketUrl])
View Support Ticket
@endcomponent

Thanks,<br>
{{ $systemName }}
@endcomponent
