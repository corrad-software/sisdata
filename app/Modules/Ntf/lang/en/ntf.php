<?php

return [
    'modul' => 'Notifications',

    'keutamaan' => [
        'kritikal' => 'Critical',
        'tinggi' => 'High',
        'biasa' => 'Normal',
        'maklumat' => 'Information',
    ],

    'saluran' => [
        'dalam_sistem' => 'In-app',
        'emel' => 'E-mail',
        'sms' => 'SMS',
        'whatsapp' => 'WhatsApp',
    ],

    'status' => [
        'beratur' => 'Queued',
        'ditahan' => 'Held for digest',
        'dihantar' => 'Sent',
        'diterima' => 'Received',
        'dibaca' => 'Read',
        'gagal' => 'Failed (will retry)',
        'gagal_muktamad' => 'Permanently failed',
    ],

    'kekerapan' => [
        'serta_merta' => 'Immediately',
        'ringkasan' => 'Daily digest',
    ],

    'sasaran' => [
        'peranan' => 'Role',
        'kebenaran' => 'Permission',
        'bahagian' => 'Division',
        'daerah' => 'District',
        'pengguna' => 'User',
    ],

    'dikemas_kini' => 'Notifications updated.',
    'jenis_dikemas_kini' => 'Notification type updated.',
    'templat_disimpan' => 'Template version :versi saved and activated.',
    'templat_diaktifkan' => 'Template version :versi activated.',
    'medan_tidak_dikenali' => 'Field {{:medan}} is not declared for this notification type.',
    'medan_sensitif_templat' => 'Field {{:medan}} is flagged confidential and cannot be used in templates.',
    'wajib_dikunci' => 'Notification type ":nama" is mandatory and cannot be turned off.',
    'ringkasan_tidak_dibenarkan' => 'Digests are only for Normal and Information priority notifications.',
    'saluran_kosong' => 'Choose at least one channel, or turn this notification type off.',
    'keutamaan_disimpan' => 'Notification settings saved.',
    'peraturan_ditambah' => 'Recipient rule added.',
    'peraturan_dipadam' => 'Recipient rule removed.',
    'tiada_penerima' => 'No active recipients found for the selected groups.',
    'pengumuman_dihantar' => 'Announcement sent to :count recipients.',
    'cuba_semula' => 'Delivery re-queued.',
    'tidak_boleh_cuba_semula' => 'Only failed deliveries can be retried.',
];
