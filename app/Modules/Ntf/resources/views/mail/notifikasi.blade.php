@extends('ntf::mail.layout', ['tajuk' => $mesej->subjek])

@section('isi')
    @php
        $warna = match ($mesej->keutamaan) {
            'kritikal' => ['#FEF2F2', '#B91C1C'],
            'tinggi' => ['#FFFBEB', '#B45309'],
            'biasa' => ['#EFF6FF', '#1D4ED8'],
            default => ['#F4F4F5', '#52525B'],
        };
    @endphp
    <p style="margin:0 0 16px;font-size:14px;">Assalamualaikum dan salam sejahtera, {{ $penerima->name }}.</p>

    <span style="display:inline-block;padding:2px 10px;border-radius:999px;font-size:12px;font-weight:600;background:{{ $warna[0] }};color:{{ $warna[1] }};">
        Keutamaan: {{ $keutamaan }}
    </span>

    <h1 style="font-family:Poppins,Inter,Arial,sans-serif;font-size:18px;line-height:26px;font-weight:600;margin:12px 0 8px;color:#18181B;">
        {{ $mesej->subjek }}
    </h1>

    <div style="font-size:14px;line-height:22px;color:#3F3F46;">{!! nl2br(e($mesej->kandungan)) !!}</div>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0 8px;">
        <tr>
            <td style="background:#2563EB;border-radius:8px;">
                <a href="{{ $pautan }}" style="display:inline-block;padding:10px 18px;font-size:14px;font-weight:600;color:#FFFFFF;text-decoration:none;">Lihat dalam SISDATA</a>
            </td>
        </tr>
    </table>
    <p style="margin:8px 0 0;font-size:12px;color:#71717A;word-break:break-all;">Atau salin pautan ini: {{ $pautan }}</p>
@endsection
