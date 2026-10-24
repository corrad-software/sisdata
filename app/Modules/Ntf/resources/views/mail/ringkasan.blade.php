@extends('ntf::mail.layout', ['tajuk' => 'Ringkasan notifikasi'])

@section('isi')
    <p style="margin:0 0 16px;font-size:14px;">Assalamualaikum dan salam sejahtera, {{ $penerima->name }}.</p>
    <h1 style="font-family:Poppins,Inter,Arial,sans-serif;font-size:18px;font-weight:600;margin:0 0 12px;">
        Ringkasan notifikasi anda ({{ $senarai->count() }})
    </h1>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #E4E4E7;border-radius:8px;">
        @foreach ($senarai as $item)
            <tr>
                <td style="padding:12px 16px;border-bottom:1px solid #E4E4E7;font-size:14px;line-height:20px;">
                    <a href="{{ $item->pautanPenuh() }}" style="color:#1D4ED8;font-weight:600;text-decoration:none;">{{ $item->subjek }}</a>
                    <div style="font-size:12px;color:#71717A;margin-top:2px;">{{ $item->created_at->timezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i') }}</div>
                </td>
            </tr>
        @endforeach
    </table>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0 0;">
        <tr>
            <td style="background:#2563EB;border-radius:8px;">
                <a href="{{ $pautanPeti }}" style="display:inline-block;padding:10px 18px;font-size:14px;font-weight:600;color:#FFFFFF;text-decoration:none;">Buka peti notifikasi</a>
            </td>
        </tr>
    </table>
@endsection
