<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $tajuk ?? 'SISDATA' }}</title>
</head>
<body style="margin:0;padding:0;background:#F4F4F5;font-family:Inter,'Segoe UI',Arial,sans-serif;color:#18181B;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F4F4F5;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#FFFFFF;border:1px solid #E4E4E7;border-radius:12px;overflow:hidden;">
                <tr>
                    <td style="background:#18181B;color:#FFFFFF;font-size:11px;padding:6px 24px;">
                        Laman rasmi Majlis Agama Islam Selangor
                    </td>
                </tr>
                <tr>
                    <td style="background:#2563EB;padding:20px 24px;">
                        <div style="font-family:Poppins,Inter,Arial,sans-serif;font-size:20px;font-weight:600;color:#FFFFFF;">SISDATA</div>
                        <div style="font-size:12px;color:#DBEAFE;margin-top:2px;">Sistem Pengurusan Data Hartanah MAIS</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:24px;">
                        @yield('isi')
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 24px;border-top:1px solid #E4E4E7;font-size:12px;line-height:18px;color:#71717A;">
                        Ini adalah notifikasi automatik daripada SISDATA. Jangan balas e-mel ini.<br>
                        Kandungan notifikasi hanya ringkasan; maklumat terperinci boleh dicapai melalui pautan selepas log masuk mengikut kebenaran anda.<br>
                        Tetapan notifikasi boleh diubah di <em>Notifikasi &rsaquo; Tetapan</em>.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
