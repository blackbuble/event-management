<x-mail::message>
@if ($locale === 'id')
# Halo, {{ $recipientName }}

Kami sudah menerima pesanan tiket Anda untuk **{{ $eventName }}**. Booking ini **menunggu pembayaran**.

**Detail Booking:**
- **Nomor Booking:** {{ $bookingNumber }}
- **Nama Event:** {{ $eventName }}
- **Total:** Rp {{ $totalAmount }}

**Tiket:**
@foreach ($tickets as $ticket)
- {{ $ticket['ticket_name'] }} — {{ $ticket['attendee_name'] ?? $recipientName }}
@endforeach

Selesaikan pembayaran untuk mengonfirmasi tiket Anda. Setelah pembayaran berhasil, QR tiket akan dikirim ke email ini (dan dapat dikirim via WhatsApp).

<x-mail::button :url="$payUrl">
Selesaikan Pembayaran
</x-mail::button>

Terima kasih,<br>
Tim {{ config('app.name') }}
@else
# Hi, {{ $recipientName }}

We've received your ticket order for **{{ $eventName }}**. This booking is **awaiting payment**.

**Booking Details:**
- **Booking Number:** {{ $bookingNumber }}
- **Event Name:** {{ $eventName }}
- **Total:** Rp {{ $totalAmount }}

**Tickets:**
@foreach ($tickets as $ticket)
- {{ $ticket['ticket_name'] }} — {{ $ticket['attendee_name'] ?? $recipientName }}
@endforeach

Complete your payment to confirm your tickets. Once payment succeeds, your ticket QRs will be emailed to this address (and can be sent via WhatsApp).

<x-mail::button :url="$payUrl">
Complete Payment
</x-mail::button>

Thank you,<br>
The {{ config('app.name') }} Team
@endif
</x-mail::message>
