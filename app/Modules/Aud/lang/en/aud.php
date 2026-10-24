<?php

return [
    'modul' => 'Audit Trail',
    'hanya_baca' => 'Audit records are read-only and cannot be changed or deleted.',
    'tidak_dijumpai' => 'Audit record not found.',
    'tiada_kebenaran_arkib' => 'You are not allowed to access the audit archive.',
    'integriti_lulus' => 'Integrity check passed: the audit chain is valid.',
    'integriti_gagal' => 'Integrity check FAILED: a mismatch was found in the audit chain.',
    'retensi_minimum' => 'The online period must be at least :bulan months.',
    'retensi_dikemas_kini' => 'Audit retention policy updated.',

    'kebenaran' => [
        'aud.rekod.lihat' => 'Search and view the audit trail',
        'aud.rekod.eksport' => 'Export audit trail packages',
        'aud.arkib.lihat' => 'Search and view the audit archive',
        'aud.integriti.lihat' => 'View audit trail integrity status',
        'aud.integriti.cipta' => 'Run an audit trail integrity check',
        'aud.laporan.lihat' => 'View automatic audit reports',
        'aud.retensi.lihat' => 'View the audit retention policy',
        'aud.retensi.kemas_kini' => 'Update the audit retention policy',
    ],
];
