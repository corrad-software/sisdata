<?php

return [
    'disimpan' => 'Saved.',
    'aliran_tiada' => 'Approval flow :kod is not active in the Workflow module. Contact the system administrator.',

    'urusan' => [
        'didaftar' => 'Land matter :no registered.',
        'penyatuan_dua_lot' => 'Amalgamation needs at least two lots.',
        'lot_tiada' => 'Lot not found or outside your access scope.',
        'lot_arkib' => 'Lot :no_lot is archived and cannot be used for a new matter.',
        'urusan_wujud' => 'This lot already has an open matter of the same type.',
        'templat_tiada' => 'No active process template for this matter type.',
        'dikunci' => 'The matter is closed and locked — the record cannot change.',
        'tidak_terbuka' => 'This matter is no longer in progress.',
        'belum_selesai' => 'A matter can only be closed when all stages are completed, rejected or cancelled.',
        'aliran_berjalan' => 'An approval is running in the Workflow module — withdraw it first.',
        'ditutup' => 'Matter closed and locked as an audit record.',
        'dibatalkan' => 'Matter cancelled.',
    ],

    'peringkat' => [
        'bukan_aktif' => 'The current stage is not in progress.',
        'dokumen_wajib' => 'Mandatory documents missing: :senarai.',
        'dihantar_kelulusan' => 'Stage sent for approval through the Workflow module.',
        'selesai' => 'Stage completed; the next stage has started.',
    ],

    'syarat' => [
        'lantikan' => 'No licensed surveyor appointment recorded yet.',
        'hasil_ukur' => 'No accepted survey result yet.',
        'rujukan_etanah' => 'e-Tanah reference number not recorded.',
        'rujukan_ptd' => 'Land Office reference number not recorded.',
        'kos' => 'Some costs of this stage are still unpaid.',
        'penilaian' => 'A current JPPH valuation report (with value) is missing or expired.',
        'pemindahan' => 'Record transfer to the new lots is not yet confirmed.',
        'lot_baharu' => 'No accepted survey result with a new lot number to create lots from.',
    ],

    'pelupusan' => [
        'justifikasi_wajib' => 'A written disposal justification is required before approval.',
        'penilaian_wajib' => 'A current JPPH valuation report must be attached before disposal approval.',
        'tiada_aliran_tertinggi' => 'Approval not recorded: no decision of the highest approval stage found.',
        'mfa_wajib' => 'Approval not recorded: the highest approval stage must be confirmed with MFA.',
        'justifikasi_pelulus_wajib' => 'Approval not recorded: the highest approver must give a written justification.',
    ],

    'keputusan' => [
        'lulus' => 'Approved', 'tolak' => 'Rejected', 'ditarik_balik' => 'Withdrawn', 'dibatalkan' => 'Cancelled', 'tidak_sah' => 'Approval invalid',
        'tutup' => 'Matter closed', 'batal' => 'Matter cancelled', 'lot_baharu_dicipta' => 'New lots created', 'diterima' => 'Accepted', 'ditolak' => 'Rejected',
        'kembali' => 'Returned', 'tangguh' => 'Deferred', 'selesai' => 'Completed',
    ],

    'kesan' => [
        'sebab_syarat' => 'Change of conditions approved and registered (matter :no).',
        'sebab_lupus' => 'Lot disposed (matter :no).',
        'sebab_lot_baharu' => ':jenis approved (matter :no).',
        'sebab_keluasan' => 'New area from the verified survey result (matter :no).',
        'cadangan_hkm' => 'Proposal from MOD-TSU: :jenis :no.',
    ],

    'pemindahan' => [
        'tertunda' => 'Some record transfers are not yet confirmed.',
        'sedang_disahkan' => 'The mapping is awaiting confirmation and cannot be changed.',
        'tiada_lot_baharu' => 'No new lots have been created for this matter yet.',
        'pemetaan_tidak_sah' => 'Invalid mapping: original lot / new lot / record type does not match the matter.',
        'rekod_tiada' => 'Record not found on the original lot.',
        'tiada_draf' => 'No draft mapping to submit.',
        'dua_pengesah' => 'A transfer needs two different confirming officers, neither of whom made the mapping.',
        'disimpan' => ':n record mappings saved as draft.',
        'dihantar' => 'Mapping submitted for two-level confirmation.',
    ],

    'ukur' => [
        'srid_tidak_disokong' => 'Coordinate system EPSG::srid is not supported. Choose one of: :senarai.',
        'shp_zip' => 'A Shapefile must be uploaded as a ZIP (.shp, .shx, .dbf, .prj).',
        'format_tidak_disokong' => 'Unsupported file format (DXF, Shapefile or GeoJSON only).',
        'fail_tidak_sah' => 'The :format file is invalid or unreadable.',
        'crs_tidak_sepadan' => 'The file\'s coordinate system (EPSG::fail) does not match the declared one (EPSG::diisytihar).',
        'tiada_poligon' => 'The file contains no polygon.',
        'dxf_tiada_poligon' => 'The DXF file contains no closed polyline (LWPOLYLINE / POLYLINE).',
        'lot_bukan_urusan' => 'The lot is not part of this matter.',
        'lantikan_bukan_urusan' => 'The appointment does not belong to this matter.',
        'geometri_tidak_sah' => 'Invalid geometry.',
        'luar_malaysia' => 'Coordinates fall outside Malaysia — check the declared coordinate system (EPSG::srid).',
        'sudah_disahkan' => 'This survey result has already been verified.',
        'pengesah_sama' => 'The officer who imported a survey result cannot verify it.',
        'luar_toleransi_justifikasi' => 'Area deviation exceeds the tolerance — a justification is required to accept.',
        'pelan_bukan_urusan' => 'The certified plan document is not attached to this matter.',
        'bukan_lantikan_anda' => 'You may only update progress of your own appointment.',
        'lantikan_tidak_aktif' => 'The appointment is not active.',
        'peratus_menurun' => 'Percent complete cannot be lower than the last report (:semasa%).',
        'kemajuan_lewat' => 'Progress recorded — the survey is behind schedule; officer and head of division notified.',
        'kemajuan_direkod' => 'Survey progress recorded.',
        'diimport' => ':n survey results imported, awaiting verification.',
        'disahkan_diterima' => 'Survey result accepted; the surveyor\'s fee invoice is recorded for Finance.',
        'disahkan_ditolak' => 'Survey result rejected.',
    ],

    'sebutharga' => [
        'jurukur_tidak_layak' => 'Only active panel surveyors with a valid licence can be invited.',
        'minimum' => 'At least :n panel surveyors must be invited.',
        'tarikh_tutup' => 'The closing date must be in the future.',
        'sudah_tutup' => 'The quotation is closed — offers are no longer accepted.',
        'terkawal' => 'Offer values are kept confidential until the closing date.',
        'belum_tutup' => 'Evaluation is only possible after the closing date.',
        'pengasingan_penilai' => 'Segregation of duties: the matter registrar cannot evaluate the quotation.',
        'tawaran_tidak_sah' => 'The recommended offer is invalid.',
        'pengasingan_pelulus' => 'Segregation of duties: the registrar or evaluator cannot approve the appointment.',
        'belum_lulus' => 'The surveyor selection is not approved yet.',
        'sudah_dilantik' => 'An appointment is already recorded for this quotation.',
        'dijemput' => 'Quotation invitation :no generated.',
        'tawaran_direkod' => 'Offer recorded.',
        'dihantar_kelulusan' => 'Recommendation sent to the authorised officer for approval.',
        'dilantik' => 'Surveyor appointment recorded.',
    ],

    'kos' => [
        'peringkat_bukan_urusan' => 'The stage does not belong to this matter.',
        'sudah_dibayar' => 'The amount of a paid cost cannot change.',
        'resit_wajib' => 'Receipt number and payment date are required for a paid cost.',
        'dokumen_bukan_urusan' => 'The receipt document is not attached to this matter.',
        'direkod' => 'Cost recorded.',
    ],

    'dokumen' => [
        'penilaian_rahsia' => 'Valuation reports are Secret — you may not upload one.',
        'nilai_wajib' => 'Valuation amount and report date are required for a JPPH valuation report.',
        'dimuat_naik' => 'Document uploaded to the Digital Document module.',
        'tiada_capaian' => 'You have no access to this document.',
    ],

    'ptd' => [
        'tidak_tersedia' => 'The Land Office integration provider is unavailable for this district.',
        'sudah_dihantar' => 'The application has already been sent to the Land Office.',
        'guna_manual' => 'Land Office API unavailable / outside coverage — record the reference and status manually.',
        'dalam_liputan' => 'This district is covered by the central API — status updates automatically.',
        'luar_liputan' => 'This district is outside the central API coverage — use the manual update.',
        'dihantar' => 'Application sent to the Land Office (reference :no).',
        'dikemas_kini' => 'Land Office status updated.',
        'tiada_perubahan' => 'No status change at the Land Office.',
    ],

    'templat' => [
        'versi_baharu' => 'Template version :versi activated. Existing matters keep their original stages.',
    ],
];
