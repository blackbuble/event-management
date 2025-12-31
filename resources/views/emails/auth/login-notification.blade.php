<x-mail::message>
# Halo, {{ $user->name ?? $user->email ?? $user->phone }}

Akun Anda baru saja berhasil masuk ke sistem kami. Jika ini adalah Anda, Anda dapat mengabaikan email ini.

**Detail Login:**
- **Waktu:** {{ $details['time'] }}
- **Alamat IP:** {{ $details['ip'] }}
- **Perangkat:** {{ $details['user_agent'] }}

Jika Anda tidak merasa melakukan aktivitas ini, kami sangat menyarankan Anda untuk segera mengonfirmasi keamanan akun Anda atau menghubungi tim dukungan kami.

Terima kasih,<br>
Tim Keamanan {{ config('app.name') }}
</x-mail::message>
