<x-mail::message>
@if ($locale === 'id')
# Halo, {{ $recipientName }}

Berikut adalah tautan untuk mengikuti event online **{{ $eventName }}**.

**Detail Event:**
- **Nama Event:** {{ $eventName }}
- **Mulai:** {{ $eventStart }}
- **Tautan Meeting:** [{{ $meetingLink }}]({{ $meetingLink }})

Simpan email ini dan gunakan tautan di atas untuk bergabung saat event dimulai.

Terima kasih,<br>
Tim {{ config('app.name') }}
@else
# Hi, {{ $recipientName }}

Here is the link to join the online event **{{ $eventName }}**.

**Event Details:**
- **Event Name:** {{ $eventName }}
- **Starts:** {{ $eventStart }}
- **Meeting Link:** [{{ $meetingLink }}]({{ $meetingLink }})

Keep this email and use the link above to join when the event starts.

Thank you,<br>
The {{ config('app.name') }} Team
@endif
</x-mail::message>
