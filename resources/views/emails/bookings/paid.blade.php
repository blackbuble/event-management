<x-mail::message>
@if ($locale === 'id')
# Pembayaran Berhasil, {{ $recipientName }}!

Terima kasih. Pembayaran untuk **{{ $eventName }}** telah kami terima. Tiket Anda **terkonfirmasi**.

**Detail Booking:**
- **Nomor Booking:** {{ $bookingNumber }}
- **Total:** Rp {{ $totalAmount }}
- **Metode Pembayaran:** {{ $paymentMethod }}

**QR Tiket (terlampir di email ini):**
@foreach ($tickets as $ticket)
- {{ $ticket['ticket_name'] }} — {{ $ticket['attendee_name'] ?? $recipientName }} — Kode: **{{ $ticket['code'] }}**
@endforeach

Tunjukkan QR code yang terlampir saat masuk ke venue. QR juga dapat dikirim melalui WhatsApp bila nomor Anda terdaftar.

<x-mail::button :url="$showUrl">
Lihat Detail Booking
</x-mail::button>

Terima kasih,<br>
Tim {{ config('app.name') }}
@else
# Payment Successful, {{ $recipientName }}!

Thank you. We've received your payment for **{{ $eventName }}**. Your tickets are **confirmed**.

**Booking Details:**
- **Booking Number:** {{ $bookingNumber }}
- **Total:** Rp {{ $totalAmount }}
- **Payment Method:** {{ $paymentMethod }}

**Ticket QR (attached to this email):**
@foreach ($tickets as $ticket)
- {{ $ticket['ticket_name'] }} — {{ $ticket['attendee_name'] ?? $recipientName }} — Code: **{{ $ticket['code'] }}**
@endforeach

Present the attached QR codes at the venue entrance. QRs can also be sent via WhatsApp if your number is on file.

<x-mail::button :url="$showUrl">
View Booking Details
</x-mail::button>

Thank you,<br>
The {{ config('app.name') }} Team
@endif
</x-mail::message>
