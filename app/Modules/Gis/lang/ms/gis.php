<?php

return [
    'bbox_tidak_sah' => 'Kotak sempadan (bbox) tidak sah. Gunakan format minLng,minLat,maxLng,maxLat.',

    'kumpulan' => [
        'asas' => 'Peta Asas',
        'hartanah' => 'Hartanah',
        'operasi' => 'Operasi',
        'risiko' => 'Risiko & Analitik',
        'rujukan' => 'Rujukan & Sempadan',
    ],

    'peta_asas' => [
        'satelit' => 'Imej Satelit',
        'kosong' => 'Tiada peta asas',
        'perlu_lesen' => 'Memerlukan peta asas satelit berlesen (MyGDI / pembekal komersial) — belum dikonfigurasi.',
    ],

    'lapisan' => [
        'tidak_wujud' => 'Lapisan peta tidak wujud.',
        'ditolak' => 'Anda tiada kebenaran untuk mencapai lapisan peta ini.',
        'ditolak_audit' => 'Percubaan mencapai lapisan peta tanpa kebenaran',
        'tidak_boleh_rendahkan' => 'Klasifikasi lapisan tidak boleh lebih rendah daripada klasifikasi minimum (:minimum).',
        'awam_mesti_awam' => 'Hanya lapisan berklasifikasi Awam boleh dimasukkan dalam kumpulan lapisan awam.',
        'ogc_tidak_dikonfigurasi' => 'Lapisan ini belum dikonfigurasi dalam GeoServer untuk perkhidmatan OGC.',
    ],

    'tile' => [
        'luar_julat' => 'Koordinat jubin di luar julat.',
        'tidak_disokong' => 'Lapisan ini tidak disediakan sebagai jubin vektor; gunakan GeoJSON.',
    ],

    'analisis' => [
        'lot_tiada_geometri' => 'Lot tidak dijumpai atau tiada geometri dalam skop anda.',
        'geometri_tidak_sah' => 'Geometri yang diberikan tidak sah.',
        'sumber_diperlukan' => 'Pilih lot atau lukis geometri sebagai sumber analisis.',
        'kawasan_diperlukan' => 'Tetapkan kawasan carian: lukis poligon, pilih sempadan atau titik dengan jarak.',
        'jenis_tidak_sah' => 'Jenis analisis tidak sah.',
        'kluster' => 'Kluster :n',
    ],

    'lajur' => [
        'jarak_m' => 'Jarak (m)',
        'luas_tindih_m2' => 'Luas bertindih (m²)',
        'peratus' => 'Peratus (%)',
        'luas_m2' => 'Keluasan (m²)',
        'id_ciri_b' => 'ID ciri lapisan B',
        'label_b' => 'Ciri lapisan B',
        'lapisan_b' => 'Lapisan B',
        'sasaran_terdekat' => 'Sasaran terdekat',
        'id_sasaran' => 'ID sasaran',
        'bilangan' => 'Bilangan',
    ],

    'senario' => [
        'tidak_wujud' => 'Senario analisis tidak wujud atau tidak dikongsi dengan anda.',
        'bukan_pemilik' => 'Hanya pemilik senario boleh memadamnya.',
        'dipadam' => 'Senario dipadam.',
    ],

    'paparan' => [
        'dipadam' => 'Paparan tersimpan dipadam.',
    ],

    'import' => [
        'format_tidak_disokong' => 'Format fail ":format" tidak disokong. Format diterima: Shapefile (.zip), GeoJSON, KML/KMZ, DXF, GeoPackage.',
        'terlalu_besar' => 'Fail melebihi had saiz :had MB.',
        'srid_tidak_disokong' => 'Sistem koordinat tidak disokong. Pilih salah satu: :senarai.',
        'tidak_disimpan_dms' => 'Fail asal tidak disimpan dalam DMS: :sebab',
        'tiada_lapisan' => 'Fail tidak mengandungi sebarang lapisan spatial.',
        'srid_diandaikan' => 'Fail tiada maklumat sistem koordinat — diandaikan EPSG::srid.',
        'terlalu_banyak_ciri' => 'Fail melebihi had :had ciri bagi satu import.',
        'gagal_baca' => 'Fail :format tidak dapat dibaca. Pastikan fail lengkap dan tidak rosak.',
        'tiada_ciri' => 'Fail tidak mengandungi sebarang ciri.',
        'tidak_dipadan' => ':n ciri tidak dapat dipadankan dengan lot berdaftar (nombor lot / mukim).',
        'zip_rosak' => 'Fail ZIP rosak atau tidak dapat dibuka.',
        'tiada_shp' => 'Fail ZIP tidak mengandungi fail .shp.',
        'tidak_boleh_terima' => 'Hanya import yang lulus pengesahan (status Sedia) boleh diterima.',
        'tidak_wujud' => 'Rekod import tidak wujud.',
        'ralat' => [
            'kosong' => 'Geometri / koordinat kosong',
            'tidak_sah' => 'Geometri tidak sah: %s',
            'luar_selangor' => 'Koordinat di luar sempadan Selangor (%s, %s) melebihi toleransi %s m',
            'medan_wajib' => 'Medan wajib ":medan" kosong',
            'bertindih' => 'Bertindih dengan ciri #%s dalam fail yang sama',
        ],
    ],

    'eksport' => [
        'format_tidak_sah' => 'Format eksport tidak sah.',
        'srid_tidak_sah' => 'Sistem koordinat eksport tidak sah (4326, 3380 atau 3375).',
    ],

    'kualiti' => [
        'tidak_wujud' => 'Penemuan kualiti tidak wujud.',
        'tidak_dikesan_lagi' => 'Ditutup automatik — tidak dikesan lagi pada semakan terkini.',
        'perihal' => [
            'tidak_sah' => 'Lot %s: geometri tidak sah (%s)',
            'kosong' => 'Lot %s: geometri kosong / koordinat tidak lengkap',
            'tiada' => 'Lot %s: tiada geometri',
            'luar_selangor' => 'Lot %s: koordinat di luar sempadan Selangor (toleransi %s m)',
            'luar_mukim' => 'Lot %s: geometri di luar sempadan mukim %s',
            'pertindihan' => 'Lot %s bertindih dengan lot %s (%s m²)',
            'jurang' => 'Jurang di antara lot dalam mukim %s (%s m²)',
        ],
        'cadangan' => [
            'make_valid' => 'Betulkan dengan ST_MakeValid (perubahan keluasan %s%%) dan sahkan semula dengan pelan ukur.',
            'muat_naik' => 'Muat naik geometri lot daripada pelan akui / data JUPEM melalui halaman spatial lot.',
            'semak_srid' => 'Semak sistem koordinat fail sumber (GDM2000 / Cassini Selangor) dan muat naik semula.',
            'semak_mukim' => 'Semak kod mukim lot atau geometri; sempadan mukim ialah anggaran.',
            'pertindihan' => 'Semak kedua-dua lot berbanding pelan akui; betulkan sempadan lot yang salah.',
            'jurang' => 'Semak sama ada ruang ini tanah kerajaan / rizab atau ralat digitasi sempadan lot bersebelahan.',
        ],
    ],

    'aset' => [
        'lot_tidak_wujud' => 'Lot tidak wujud dalam skop anda.',
        'dipadam' => 'Titik aset dipadam.',
    ],

    'ogc' => [
        'servis_tidak_sah' => 'Perkhidmatan OGC tidak sah (WMS atau WFS sahaja).',
        'parameter_dilarang' => 'Parameter :parameter tidak dibenarkan melalui proksi peta.',
        'permintaan_tidak_sah' => 'Jenis permintaan OGC tidak dibenarkan.',
        'lapisan_diperlukan' => 'Nyatakan lapisan (LAYERS / TYPENAMES).',
        'tidak_tersedia' => 'Perkhidmatan peta (GeoServer) tidak tersedia buat masa ini.',
        'panduan_token' => 'Gunakan token API SISDATA sebagai pengepala "Authorization: Bearer <token>" (QGIS: Pengesahan → API Header). GeoServer tidak boleh dicapai terus.',
    ],

    'cetak' => [
        'imej_tidak_sah' => 'Imej peta tidak sah.',
        'jadual_atribut' => 'Jadual atribut',
        'skala' => 'Skala',
        'unjuran' => 'Sistem unjuran',
        'dijana_pada' => 'Dijana pada',
        'dijana_oleh' => 'Dijana oleh',
        'penafian' => 'Peta ini dijana daripada SISDATA untuk kegunaan dalaman MAIS. Sempadan lot bukan pengganti pelan akui JUPEM.',
        'direkod' => 'Eksport peta direkod dalam jejak audit.',
    ],

    'versi' => [
        'tidak_wujud' => 'Versi geometri tidak wujud.',
        'lot_tidak_wujud' => 'Lot tidak wujud dalam skop anda.',
    ],
];
