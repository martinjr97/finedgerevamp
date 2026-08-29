<x-mail::message>
# Support ticket assigned to you

Hello {{ $notifiable->full_name ?? 'there' }},

**{{ $assignedByName }}** assigned support ticket **#{{ $ticket->id }}** to you on {{ config('app.system_name') }}.

**Subject:** {{ $ticket->subject }}

**Customer:** {{ $ticket->name }}

@if(filled($assignmentNote))
**Assignment note:** {{ $assignmentNote }}
@endif

<x-mail::button :url="$ticketUrl">
View Ticket
</x-mail::button>

You will also see this assignment highlighted on your dashboard when you sign in.

Thanks,<br>
{{ $systemName }}
</x-mail::message>
