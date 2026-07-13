{{-- Inner content only — wrapped by nawasara-notification::mail.layout
     (full HTML shell + green header + footer). Inline styles only; email
     clients ignore <style>/classes. --}}
<p>Yth. <strong>{{ $pjName ?: 'Penanggung Jawab' }}</strong>,</p>

<p>Anda ditetapkan sebagai penanggung jawab untuk Zoom Meeting berikut. Meeting sudah dijadwalkan dan siap digunakan.</p>

<table cellpadding="0" cellspacing="0" style="width:100%; border-collapse:collapse; margin:16px 0; border:1px solid #e4e4e7; border-radius:8px;">
    <tr>
        <td style="padding:10px 14px; width:130px; color:#71717a; font-size:14px; border-bottom:1px solid #f4f4f5;">Topik</td>
        <td style="padding:10px 14px; font-size:14px; font-weight:600; border-bottom:1px solid #f4f4f5;">{{ $topic }}</td>
    </tr>
    <tr>
        <td style="padding:10px 14px; color:#71717a; font-size:14px; border-bottom:1px solid #f4f4f5;">Waktu</td>
        <td style="padding:10px 14px; font-size:14px; border-bottom:1px solid #f4f4f5;">{{ $tanggal }}</td>
    </tr>
    <tr>
        <td style="padding:10px 14px; color:#71717a; font-size:14px; {{ $agenda ? 'border-bottom:1px solid #f4f4f5;' : '' }}">Durasi</td>
        <td style="padding:10px 14px; font-size:14px; {{ $agenda ? 'border-bottom:1px solid #f4f4f5;' : '' }}">{{ $duration }} menit</td>
    </tr>
    @if ($agenda)
        <tr>
            <td style="padding:10px 14px; color:#71717a; font-size:14px; vertical-align:top;">Agenda</td>
            <td style="padding:10px 14px; font-size:14px;">{{ $agenda }}</td>
        </tr>
    @endif
</table>

@if ($joinUrl)
    <p style="margin:20px 0;">
        <a href="{{ $joinUrl }}" style="display:inline-block; background:#16a34a; color:#ffffff; text-decoration:none; padding:12px 24px; border-radius:6px; font-weight:600; font-size:14px;">
            Gabung ke Zoom Meeting
        </a>
    </p>
    <p style="font-size:13px; color:#71717a;">
        Atau salin tautan ini:<br>
        <a href="{{ $joinUrl }}" style="word-break:break-all;">{{ $joinUrl }}</a>
    </p>
@endif

@if ($password)
    <p style="font-size:14px;">Passcode meeting: <strong>{{ $password }}</strong></p>
@endif

<p style="margin-top:20px;">Mohon hadir tepat waktu. Terima kasih.</p>
