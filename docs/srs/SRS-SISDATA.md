# Spesifikasi Keperluan Sistem (SRS) — SISDATA

Oct 22, 2026 · @Ros

SRS ini memperincikan keperluan fungsian dan bukan fungsian bagi 22 modul Sistem Pengurusan Data Hartanah MAIS, dipetakan kepada 280 spesifikasi Lampiran H dan Seksyen 6.0–15.0 Terma Rujukan RFP MAIS Bil. 1/2026.

## 1. Pengenalan

Dokumen ini ialah System Requirement Specification (SRS) bagi Sistem Pengurusan Data Hartanah MAIS (SISDATA), disediakan bagi memenuhi Lampiran H A-2.04 dan A-2.05 RFP MAIS Bil. 1/2026.

### 1.1 Tujuan

SRS menetapkan keperluan fungsian dan bukan fungsian yang mengikat pembangunan SISDATA. Ia menjadi asas kepada System Design Specification (SDS), kes ujian, User Acceptance Test dan penerimaan akhir sistem. Setiap keperluan diberi kod unik yang kekal merentas BRS → SRS → SDS → kes ujian melalui Requirement Traceability Matrix di Bab 19.

### 1.2 Skop sistem

SISDATA ialah platform web berpusat yang menjadi sumber kebenaran tunggal bagi setiap lot tanah milik MAIS. Sistem merangkumi 22 modul: 19 modul yang ditetapkan Seksyen 6.0 Terma Rujukan, ditambah Portal Hartanah Wakaf Selangor sebagai domain awam berasingan, Tiket Sokongan Sistem dan Pangkalan Pengetahuan Berbantu AI.

Skop meliputi kitar hayat penuh maklumat tanah — pemerolehan, pendaftaran hak milik, penilaian, pemanfaatan melalui pajakan dan sewaan, pemantauan fizikal, cukai, penguatkuasaan, urusan Pejabat Tanah, kes perundangan dan pelupusan — berserta lapisan kepintaran spatial, integrasi dengan sistem dalaman MAIS dan agensi kerajaan, migrasi data sedia ada, dan pemindahan pengetahuan kepada Bahagian ICT MAIS.

Di luar skop: pembangunan sistem kewangan baharu (SISDATA berintegrasi dengan GRP MAIS sedia ada), penggantian sistem e-Faraid dan e-Sewa, dan pembekalan perkakasan selain workstation GIS yang dinyatakan dalam Bab 9.

### 1.3 Definisi dan akronim

| Istilah | Maksud |
| --- | --- |
| ADR | Architecture Decision Record — rekod keputusan seni bina berserta justifikasi dan alternatif |
| ARB | Amanah Raya Berhad |
| BRS | Business Requirement Specification |
| DMS | Document Management System |
| DR | Disaster Recovery — pemulihan bencana |
| GIS | Geographic Information System |
| JKPTGS | Jabatan Ketua Pengarah Tanah dan Galian Selangor |
| JPBD | Jabatan Perancangan Bandar dan Desa |
| JUPEM | Jabatan Ukur dan Pemetaan Malaysia |
| KRISA | Panduan Kejuruteraan Sistem Aplikasi Sektor Awam, Jabatan Digital Negara |
| LOA | Letter of Authorization daripada principal perisian |
| MBI | Menteri Besar Incorporated Selangor |
| MMKN | Mesyuarat Majlis Mesyuarat Kerajaan Negeri |
| MyDS | Malaysia Government Design System |
| OIDC | OpenID Connect |
| PAT | Production Acceptance Test |
| PBT | Pihak Berkuasa Tempatan |
| PDPA | Akta Perlindungan Data Peribadi 2010 |
| PTG | Pejabat Tanah dan Galian |
| PWA | Progressive Web Application |
| RBAC | Role-Based Access Control |
| RPO | Recovery Point Objective — kehilangan data maksimum yang boleh diterima |
| RTM | Requirement Traceability Matrix |
| RTO | Recovery Time Objective — tempoh pemulihan maksimum yang boleh diterima |
| SIT | System Integration Test |
| SLA | Service Level Agreement |
| SPA | Security Posture Assessment |
| SRID | Spatial Reference System Identifier |
| SSOT | Single Source of Truth |
| UAT | User Acceptance Test |
| VAPT | Vulnerability Assessment and Penetration Testing |
| WCAG | Web Content Accessibility Guidelines |

### 1.4 Dokumen rujukan

| Rujukan | Dokumen |
| --- | --- |
| R1 | Dokumen RFP MAIS Bil. 1/2026 — Terma Rujukan Pelawaan Cadangan, Seksyen 1.0–25.0 |
| R2 | Lampiran H — Jadual Pematuhan Spesifikasi Teknikal, 280 spesifikasi dalam 26 kategori |
| R3 | Lampiran E — Jadual Cadangan Kos, Item 1–7 |
| R4 | Panduan Kejuruteraan Sistem Aplikasi Sektor Awam (KRISA), Jabatan Digital Negara |
| R5 | Panduan Pengurusan Projek ICT Sektor Awam (PPrISA) |
| R6 | Malaysia Government Design System (MyDS), GovTech Malaysia |
| R7 | Dasar Keselamatan Siber MAIS dan Polisi ICT MAIS |
| R8 | Akta Perlindungan Data Peribadi 2010 \[Akta 709\] |
| R9 | Akta Jenayah Komputer 1997 \[Akta 563\] |
| R10 | ISO/IEC 27001 — Information Security Management Systems |
| R11 | OWASP Top 10 dan OWASP Application Security Verification Standard |
| R12 | WCAG 2.1 Level AA |

### 1.5 Konvensyen dokumen

Setiap keperluan fungsian diberi kod berbentuk `SRS-<kod modul>-<nombor>` dan dinyatakan dalam bentuk “Sistem hendaklah …” supaya boleh diuji secara objektif. Keutamaan dinyatakan sebagai **Wajib** apabila RFP menuntutnya secara tersurat, **Perlu** apabila ia diperlukan untuk kesempurnaan operasi, dan **Baik-ada** apabila ia nilai tambah yang dicadangkan Syarikat.

Lajur Sumber memetakan setiap keperluan kepada kod spesifikasi Lampiran H, klausa Terma Rujukan atau kod URS asal. Keperluan bertanda `Jurang` ialah keperluan yang dikesan semasa semakan silang RFP dan tiada dalam dokumen cadangan asal.

## 2. Huraian Keseluruhan

SISDATA menggantikan keadaan semasa di mana rekod hartanah MAIS bertaburan antara SISDATA sedia ada berasaskan Drupal, e-Faraid, Portal Wakaf, e-Sewa, hamparan Excel bahagian dan fail manual tanpa integrasi, menyebabkan MAIS tidak dapat menjawab soalan asas mengenai asetnya sendiri tanpa panggilan telefon antara bahagian.

### 2.1 Perspektif sistem

Sistem beroperasi sebagai sumber kebenaran tunggal bagi data hartanah MAIS. Setiap rekod operasi — cukai, pajakan, pemantauan, aduan, kes mahkamah, dokumen dan aset — berlabuh pada satu rekod lot induk melalui kunci asing. Sistem lain terus wujud dan berintegrasi melalui API; SISDATA tidak menggantikan sistem kewangan GRP, e-Faraid atau e-Sewa.

Data daripada sumber luaran tidak pernah menulis terus ke rekod induk. Ia masuk sebagai cadangan perubahan yang menunggu pengesahan pegawai MAIS yang diberi kuasa.

### 2.2 Fungsi utama

1. Mengekalkan rekod induk setiap lot tanah MAIS berserta profil lengkap dalam satu skrin — identiti dan hak milik, kedudukan kewangan, pemanfaatan, risiko dan isu, ruang dan aset, dokumen dan jejak audit.
2. Merekod pemerolehan hartanah mengikut sebelas saluran pewujudan hak milik yang diiktiraf, setiap satu dengan medan dan dokumen sokongan tersendiri.
3. Mengurus kitar hayat pajakan, sewaan dan kontrak dengan lejar transaksi bertaraf kewangan.
4. Memantau cukai tanah, cukai petak dan cukai taksiran PBT merentas seluruh portfolio melalui integrasi berpusat.
5. Menjadualkan dan merekod pemantauan fizikal tapak, termasuk kerja lapangan luar talian.
6. Mengurus aduan awam dan dalaman, kes perundangan, urusan Pejabat Tanah dan inventori aset fizikal.
7. Memaparkan keseluruhan portfolio secara spatial melalui sepuluh lapisan peta tematik dengan keupayaan analisis spatial.
8. Menyediakan dashboard berbilang aras, pelaporan dan analitik dengan keupayaan menggerudi hingga ke rekod asal.
9. Membuka data melalui API berversi kepada sistem MAIS lain dan portal awam.

### 2.3 Kategori pengguna

Struktur peranan mengambil kira tujuh kategori yang ditetapkan Seksyen 11.2.3 Terma Rujukan, dilaksanakan sebagai peranan dinamik yang boleh ditambah, dipinda atau dinyahaktif tanpa perubahan kod.

| Bil. | Peranan | Skop capaian umum |
| --- | --- | --- |
| 1 | Pentadbir Sistem / Super Administrator | Capaian pentadbiran penuh kepada konfigurasi sistem, pengurusan pengguna, tetapan keselamatan, parameter dan fungsi teknikal yang dibenarkan |
| 2 | Pentadbir Aplikasi | Mengurus pengguna, peranan, parameter aplikasi, kod rujukan, konfigurasi modul dan laporan pentadbiran mengikut skop diluluskan |
| 3 | Pegawai Operasi | Kemasukan data, kemas kini rekod, pengurusan dokumen, semakan maklumat, penyediaan laporan operasi dan tindakan harian mengikut bidang tugas |
| 4 | Pelulus | Menyemak, mengesahkan dan meluluskan transaksi, permohonan dan perubahan status mengikut kuasa yang ditetapkan |
| 5 | Pengguna Paparan | Melihat rekod, laporan dan dashboard tanpa kebenaran mengubah data |
| 6 | Juruaudit | Mencapai rekod audit, log sistem, sejarah perubahan data dan maklumat pematuhan bagi tujuan semakan |
| 7 | Pemegang Taruh Terhad | Mencapai portal atau maklumat tertentu yang dibenarkan — status permohonan, dokumen, bayaran atau komunikasi berkaitan |

Peranan dipetakan kepada unit organisasi MAIS: Bahagian Pengurusan Hartanah, Bahagian Wakaf, Bahagian Baitulmal, Bahagian Perundangan, Bahagian Kewangan, Bahagian Penguatkuasaan, Bahagian ICT, Pejabat Daerah, pengurusan tertinggi, serta pengguna agensi dan anak syarikat yang dibenarkan.

Kebenaran dinilai pada empat dimensi: peranan × daerah × kategori data × jenis tindakan. Pegawai daerah tidak boleh melihat lot daerah lain walaupun melalui panggilan API terus, eksport laporan atau lapisan GIS.

### 2.4 Kekangan reka bentuk

| Kekangan | Implikasi |
| --- | --- |
| Sistem menumpang persekitaran AWS sedia ada MAIS | Tiada infrastruktur baharu; kos langganan ditanggung MAIS; reka bentuk mesti muat dalam kawalan rangkaian dan keselamatan sedia ada |
| Log masuk melalui SSO MAIS (OIDC) | Polisi kata laluan dan kitar hayat akaun sebahagiannya diwarisi daripada penyedia identiti MAIS |
| Bahasa Malaysia sebagai bahasa utama antara muka | Menu, label, mesej sistem, laporan dan manual dalam Bahasa Malaysia dengan istilah teknikal ICT yang sesuai |
| Pematuhan MyDS | Reka bentuk antara muka menggunakan sistem grid, token warna, tipografi dan komponen MyDS; komponen tersuai memerlukan justifikasi bertulis |
| Model AI dalam VPC persendirian MAIS | Tiada data hartanah, prompt atau jawapan dihantar ke perkhidmatan AI awam |
| Platform GIS dibekal vendor khusus | Geometri kekal dalam pangkalan data induk SISDATA sebagai rekod tuan; vendor membaca, tidak memiliki data |
| Integrasi pembayaran melalui penyedia berpusat | Liputan bergantung kepada perjanjian pihak ketiga; setiap modul yang bergantung padanya memerlukan mod sandaran manual berstruktur |

### 2.5 Andaian dan kebergantungan

| Bil. | Andaian atau kebergantungan | Pemilik | Kesan jika tidak dipenuhi |
| --- | --- | --- | --- |
| A1 | Capaian kepada persekitaran AWS MAIS dan data sedia ada disediakan dalam minggu pertama projek | MAIS | Persediaan infrastruktur dan trek data tergelincir; Gate 3 lewat |
| A2 | Pemilik proses bagi setiap bahagian MAIS dinamakan pada Bulan 1 | MAIS | Sesi pengesahan keperluan tidak dapat dibaselinekan |
| A3 | Tempoh semakan dan kelulusan MAIS bagi setiap serahan ialah lima (5) hari bekerja | MAIS | Setiap kelewatan seminggu memindahkan Go-Live seminggu |
| A4 | Permohonan akses API agensi luar dimulakan oleh MAIS pada Bulan 1 | MAIS | Modul bergantung beroperasi dalam mod sandaran manual pada Go-Live |
| A5 | Perjanjian perkongsian data dengan pemilik data GIS kerajaan diluluskan sebelum Bulan 4 | MAIS dengan sokongan teknikal Syarikat | Lapisan peta rujukan luaran tidak tersedia pada Go-Live |
| A6 | Perjanjian dengan penyedia integrasi pembayaran berpusat dimuktamadkan sebelum Bulan 4 | MAIS | Modul Cukai beroperasi dalam mod sandaran manual |
| A7 | Kakitangan Syarikat menandatangani NDA MAIS dan Surat Akuan Pematuhan Dasar Keselamatan Siber MAIS sebelum capaian diberi | Syarikat | Capaian kepada persekitaran dan data MAIS tidak boleh diberikan |
| A8 | Data SISDATA sedia ada boleh dieksport dalam bentuk berstruktur | MAIS | Skop migrasi dan usaha pembersihan meningkat |

## 3. Seni Bina Sistem

SISDATA dibina sebagai monolit modular berasaskan web di atas persekitaran AWS sedia ada MAIS, dengan sempadan modul yang dikuatkuasakan pada peringkat kod dan satu pangkalan data induk yang memegang rekod transaksi, geometri spatial dan indeks carian semantik.

### 3.1 Lapisan seni bina

| Lapisan | Komponen | Peranan |
| --- | --- | --- |
| 1 · Persembahan | Aplikasi web responsif; peta interaktif; Portal Hartanah Wakaf Selangor; PWA pemantauan lapangan | Pegawai MAIS mencapai kesemua modul melalui satu aplikasi. Semua panggilan keluar sebagai HTTPS/JSON |
| 2 · Gerbang dan kawalan capaian | API Gateway | Log masuk SSO MAIS (OIDC) → token JWT → MFA bagi modul kritikal → had kadar → log capaian → rekonsiliasi harian. Setiap permintaan dinilai kebenarannya di titik yang sama |
| 3 · Aplikasi | Monolit modular: Perkhidmatan Platform, Domain Teras, Domain Operasi, Lapisan Wawasan | Logik perniagaan dalam bounded context setiap modul. Lapisan Wawasan membaca sahaja daripada teras dan tidak menulis rekod induk |
| 4 · Data dan storan | Pangkalan data induk dengan sambungan spatial dan vektor; replika baca; pelayan peta OGC; storan objek disulit; cache dan baris gilir | Rekod induk dan geometri disimpan bersama. Laporan berat dilaksanakan pada replika baca tanpa menjejaskan transaksi |
| 5 · Infrastruktur | Persekitaran AWS sedia ada MAIS — DEV, SIT, UAT, PROD dan DR | Tiada infrastruktur baharu diperlukan selain konfigurasi. CI/CD berpagar, sandaran berjadual, pemantauan berterusan |

### 3.2 Keputusan seni bina teras

| Kod | Keputusan | Justifikasi |
| --- | --- | --- |
| ADR-01 | Profil Hartanah ialah teras, bukan satu modul antara banyak | Setiap rekod — cukai, pajakan, aduan, kes, dokumen, aset — berlabuh pada satu `lot_id`. Tiada modul dibenarkan menyimpan salinan tuan maklumat lot. Apabila nombor lot berubah selepas pecah sempadan, ia berubah di satu tempat sahaja |
| ADR-02 | API-first tanpa pengecualian | Antara muka pengguna memanggil API yang sama yang dibuka kepada portal awam dan sistem MAIS lain. Kontrak bertanda versi dijana sebagai OpenAPI 3.0 |
| ADR-03 | Monolit modular, bukan mikroperkhidmatan | Memberi pengasingan modul yang dituntut tanpa kos operasi 19 perkhidmatan berasingan — pertimbangan utama untuk pemindahan penyelenggaraan kepada Bahagian ICT MAIS |
| ADR-04 | Satu pangkalan data untuk rekod, spatial dan AI | Geometri lot disimpan sebagai jenis spatial asli dalam jadual yang sama dengan atribut lot. Indeks vektor untuk Pangkalan Pengetahuan berada dalam pangkalan data yang sama. Tiada sistem berasingan untuk diselaraskan |
| ADR-05 | Workflow, notifikasi dan audit sebagai perkhidmatan platform | Tiga enjin merentas dibina sekali pada Gelombang 0 dan digunakan oleh kesemua modul operasi. Modul baharu mewarisinya tanpa kerja tambahan |
| ADR-06 | Kebenaran dinilai pada lapisan domain | RBAC dikuatkuasakan pada peringkat model, bukan peringkat laluan atau paparan. Capaian tidak boleh dipintas melalui panggilan API terus, eksport laporan atau lapisan GIS |
| ADR-07 | Trek data bermula Bulan 1 | Data profiling dijalankan serentak dengan sesi pengesahan keperluan. Kualiti data yang buruk ditemui awal masih boleh dipulihkan; ditemui lewat ia menjadi punca kelewatan Go-Live |

### 3.3 Persekitaran

| Persekitaran | Tujuan | Data |
| --- | --- | --- |
| DEV | Pembangunan harian dan ujian unit | Data sintetik sahaja |
| SIT | Ujian integrasi sistem dan ujian regresi automatik | Data sintetik dan subset disamarkan |
| UAT | Pengesahan pengguna MAIS, ujian prestasi, trial migration | Data sebenar dengan penyamaran medan sensitif dan kelulusan bertulis MAIS |
| PROD | Operasi sebenar | Data sebenar |
| DR | Pemulihan bencana mengikut Bab 14 | Replika data PROD |

Promosi antara persekitaran melalui saluran CI/CD berpagar: ujian unit dan integrasi lulus, analisis kod statik tanpa penemuan kritikal, imbasan kebergantungan dan pengesanan rahsia bersih. Tiada kod mencapai PROD tanpa melalui pagar ini.

## 4. Model Data dan Tadbir Urus Data

Model data berpusat pada jadual lot tanah sebagai induk, dengan entiti satelit yang setiap satunya memegang kunci asing kepada `lot_id`. Tiada rekod hartanah wujud tanpa lot induknya, dan geometri disimpan dalam jadual induk yang sama, bukan dalam sistem berasingan.

### 4.1 Katalog entiti teras

| Entiti | Hubungan | Medan kritikal | Klasifikasi |
| --- | --- | --- | --- |
| `lot_tanah` | Induk | `lot_id`, no\_lot, mukim, daerah, negeri, keluasan, kategori\_guna\_tanah, zoning, syarat\_nyata, geometri POLYGON | Dalaman |
| `hak_milik` | 1 : N lot | no\_hak\_milik, jenis (Geran/PM/TOL/Pajakan Kerajaan/Rizab), tarikh\_daftar, tarikh\_tamat, sekatan\_kepentingan, lokasi\_simpanan\_fizikal | Sulit |
| `pemerolehan` | 1 : N lot | saluran (sebelas jenis), tarikh, status, pihak\_berkaitan, dokumen\_sokongan, peringkat\_semasa | Sulit |
| `penilaian` | 1 : N lot | tarikh, nilai\_pasaran, nilai\_setara, kaedah, penilai, versi | Rahsia |
| `kontrak_pajakan` | 1 : N lot | penyewa, tarikh\_mula, tarikh\_tamat, kadar, deposit, terma, rekod\_bayaran | Sulit |
| `cukai` | 1 : N lot | jenis (tanah/petak/taksiran PBT), no\_bil, jumlah, tarikh\_akhir, status\_bayaran, kaedah\_bayaran | Dalaman |
| `dokumen` | Polimorfik | metadata, kunci\_storan, jenis, versi, checksum, kawalan\_akses, sejarah\_capaian, tarikh\_retensi | Mengikut rekod induk |
| `aduan` | 1 : N lot | kategori, tarikh, pelapor, status, pegawai, tindakan\_susulan, koordinat | Dalaman |
| `kes_mahkamah` | N : N lot | no\_kes, jenis, mahkamah, status, tarikh\_penting, perintah, kos\_guaman | Rahsia |
| `data_spatial` | 1 : N lot | geometri, koordinat, sistem\_unjuran (SRID), sumber\_data, tarikh\_kemaskini, status\_pengesahan, versi, pegawai\_pengesah | Dalaman |

Entiti sokongan: `aset_fizikal` (1 : N lot), `permohonan_tanah` (1 : N lot, bagi urusan Pejabat Tanah), `pemantauan` (1 : N lot), dan `audit_trail` yang merentas kesemua jadual.

Entiti `data_spatial` dinyatakan sebagai entiti penuh mengikut Seksyen 7.1.1(j) Terma Rujukan. Ia memegang metadata spatial yang tidak boleh disimpan dalam medan geometri sahaja — sumber data, tarikh kemas kini dan status pengesahan — dan menjadi asas kepada kawalan kualiti data GIS di Bab 9.

### 4.2 Klasifikasi data

Empat aras klasifikasi dikuatkuasakan pada peringkat medan dan jadual, bukan hanya pada peringkat paparan.

| Aras | Maksud | Contoh |
| --- | --- | --- |
| Awam | Boleh dipaparkan kepada orang awam | Senarai hartanah tersedia untuk disewa, status projek wakaf yang diluluskan untuk paparan |
| Dalaman | Pegawai MAIS mengikut skop peranan | Rekod lot, cukai, aduan, pemantauan |
| Sulit | Pegawai yang diberi kuasa khusus | Hak milik, pemerolehan, kontrak pajakan |
| Rahsia | Capaian terhad dan diaudit setiap paparan | Nilai penilaian, kes mahkamah |

### 4.3 Pemilikan dan pengawasan data

Setiap komponen data utama mempunyai pemilik data (data owner) dan pengawas data (data steward) yang dinamakan, mengikut Seksyen 7.2.1 Terma Rujukan.

| Komponen data | Pemilik data | Pengawas data | Tanggungjawab pengawas |
| --- | --- | --- | --- |
| Lot tanah dan geometri | Bahagian Pengurusan Hartanah | Pegawai Hartanah dinamakan | Ketepatan no\_lot, mukim, keluasan, sempadan |
| Hak milik dan pemerolehan | Bahagian Pengurusan Hartanah | Pegawai Hartanah dinamakan | Kelengkapan dokumen, status pendaftaran |
| Wakaf | Bahagian Wakaf | Pegawai Wakaf dinamakan | Ketepatan rekod pewakaf dan syarat wakaf |
| Baitulmal | Bahagian Baitulmal | Pegawai Baitulmal dinamakan | Rekod serahan dan hibah |
| Pajakan dan sewaan | Bahagian Pengurusan Hartanah | Unit Sewaan | Ketepatan terma, kadar dan lejar |
| Cukai dan bayaran | Bahagian Kewangan | Pegawai Kewangan dinamakan | Rekonsiliasi dengan GRP |
| Kes perundangan | Bahagian Perundangan | Pegawai Perundangan dinamakan | Status kes dan tarikh prosiding |
| Data spatial | Bahagian Pengurusan Hartanah | GIS Specialist | Pengesahan sempadan dan metadata spatial |
| Pengguna dan peranan | Bahagian ICT | Pentadbir Sistem | Semakan capaian berkala |

### 4.4 Kawalan kualiti data

| Kod | Keperluan | Keutamaan | Sumber |
| --- | --- | --- | --- |
| SRS-DAT-01 | Sistem hendaklah menguatkuasakan peraturan kualiti data semasa cipta dan kemas kini: format medan, medan wajib, julat nilai yang sah, dan keunikan nombor lot per mukim. | Wajib | A-4.05 |
| SRS-DAT-02 | Sistem hendaklah mengesan kemungkinan duplikasi rekod lot dan mengemukakan cadangan padanan untuk keputusan pegawai, bukan menggabungkan secara automatik. | Wajib | A-4.05 |
| SRS-DAT-03 | Sistem hendaklah menyokong import data daripada fail Excel dan CSV dengan validasi baris demi baris, senarai ralat yang boleh dimuat turun, dan laporan keputusan import yang menyatakan bilangan rekod diterima, ditolak dan sebabnya. | Wajib | A-4.06 |
| SRS-DAT-04 | Sistem hendaklah menyimpan versi bagi setiap perubahan kepada entiti bersejarah, mengekalkan nilai lama dan membolehkan perbandingan sebelum dan selepas. | Wajib | A-4.09 |
| SRS-DAT-05 | Sistem hendaklah menyediakan fungsi Master Data Management bagi kod rujukan — daerah, mukim, kategori guna tanah, jenis hak milik, jenis dokumen, kategori aduan — dengan kawalan versi dan pencegahan duplikasi. | Wajib | A-4.10 |
| SRS-DAT-06 | Sistem hendaklah menyediakan polisi retensi yang boleh dikonfigurasi mengikut kategori data, dan mengarkibkan rekod yang melepasi tempoh retensi ke storan arkib dengan capaian terkawal dan direkod. | Wajib | A-4.11 |
| SRS-DAT-07 | Sistem hendaklah menyediakan model data fizikal yang didokumenkan termasuk indeks, view dan stored procedure, diserahkan sebagai sebahagian Kamus Data. | Wajib | A-4.03 |
| SRS-DAT-08 | Sistem hendaklah merekod salasilah data (data lineage) bagi setiap rekod yang dimigrasi — sistem sumber, jadual atau fail asal, dan kunci asal. | Wajib | A-4.12 |
| SRS-DAT-09 | Sistem hendaklah menyediakan laporan kualiti data berkala: rekod tidak lengkap mengikut entiti, rekod yatim, percanggahan rujukan silang, dan lot tanpa geometri. | Perlu | Jurang |

## 5. Senarai Modul dan Gelombang Penghantaran

Dua puluh dua modul disusun mengikut kebergantungan sebenar, bukan mengikut nombor dalam Terma Rujukan. Perkhidmatan platform dibina dahulu kerana kesemua modul operasi bergantung padanya; Modul 14 dan Modul 18 mesti siap sebelum Modul 5 boleh dibina.

| Bil. | Modul | Kod | Lapisan | Gelombang | Bulan | Bab |
| --- | --- | --- | --- | --- | --- | --- |
| 11 | Dokumen Digital (DMS) | MOD-DMS | Perkhidmatan Platform | G0 | 3.0–3.5 | 6 |
| 14 | Workflow dan Kelulusan | MOD-WFE | Perkhidmatan Platform | G0 | 3.0–3.5 | 6 |
| 15 | Notifikasi | MOD-NTF | Perkhidmatan Platform | G0 | 3.0–3.5 | 6 |
| 17 | Pentadbiran Sistem | MOD-ADM | Perkhidmatan Platform | G0 | 3.0–3.5 | 6 |
| 18 | Pengguna, Peranan dan Capaian | MOD-RBC | Perkhidmatan Platform | G0 | 3.0–3.5 | 6 |
| 19 | Audit Trail | MOD-AUD | Perkhidmatan Platform | G0 | 3.0–3.5 | 6 |
| 2 | Pangkalan Data Induk dan Profil Hartanah 360° | MOD-PRO | Domain Teras | G1 | 3.5–4.0 | 7 |
| 3 | Hak Milik Tanah | MOD-HKM | Domain Teras | G1 | 3.5–4.0 | 7 |
| 4 | Rekod Pemerolehan | MOD-PER | Domain Teras | G1 | 3.5–4.0 | 7 |
| 12 | GIS Mapping dan Kepintaran Spatial | MOD-GIS | Lapisan Wawasan | G2 | 4.0–4.5 | 9 |
| 1 | Dashboard Eksekutif | MOD-DSH | Lapisan Wawasan | G2 | 4.0–4.5 | 9 |
| 5 | Pajakan dan Kontrak | MOD-PJK | Domain Operasi | G3 | 4.0–4.5 | 8 |
| 6 | Pemantauan Cukai Tanah | MOD-CKI | Domain Operasi | G3 | 4.0–4.5 | 8 |
| 7 | Pemantauan Tanah | MOD-PTN | Domain Operasi | G3 | 4.0–4.5 | 8 |
| 8 | Aduan dan Isu Tanah | MOD-ADU | Domain Operasi | G3 | 4.0–4.5 | 8 |
| 9 | Kes Mahkamah dan Perundangan | MOD-KES | Domain Operasi | G4 | 4.5–5.0 | 8 |
| 10 | Tukar Syarat, Pelupusan dan Ukur | MOD-TSU | Domain Operasi | G4 | 4.5–5.0 | 8 |
| 13 | Inventori Aset Fizikal | MOD-AST | Domain Operasi | G4 | 4.5–5.0 | 8 |
| 16 | Laporan dan Analitik | MOD-LAP | Lapisan Wawasan | G5 | 5.0–5.5 | 9 |
| PWS | Portal Hartanah Wakaf Selangor | MOD-PWS | Domain Awam | G5 | 5.0–5.5 | 10 |
| T1 | Tiket Sokongan Sistem | MOD-TKT | Sokongan | G5 | 5.0–5.5 | 10 |
| K1 | Pangkalan Pengetahuan Berbantu AI | MOD-KBA | Sokongan | G5 | 5.0–5.5 | 10 |

### 5.1 Justifikasi susunan

| Gelombang | Modul | Sebab susunan ini |
| --- | --- | --- |
| G0 · Asas | 11, 14, 15, 17, 18, 19 | Setiap modul lain bergantung padanya. Dibina sekali, digunakan 19 kali. Modul baharu pada masa hadapan mewarisinya tanpa kerja tambahan |
| G1 · Teras | 2, 3, 4 | Rekod tuan yang menjadi tempat berlabuh semua rekod operasi. Tiada modul operasi boleh wujud sebelum lot induk wujud |
| G2 · Ruang | 12, 1 | Memerlukan data teras wujud dahulu sebelum ada apa-apa untuk dipetakan atau dipaparkan |
| G3 · Operasi | 5, 6, 7, 8 | Modul harian pengguna dengan isi padu transaksi tertinggi. Berjalan selari dengan G2 |
| G4 · Undang-undang dan aset | 9, 10, 13 | Isi padu lebih rendah; berjalan selari dengan G3 |
| G5 · Wawasan dan portal | 16, PWS, T1, K1 | Paling akhir kerana melaporkan dan menerbitkan tentang segala yang dibina sebelumnya |

### 5.2 Keperluan merentas modul

Keperluan berikut terpakai kepada kesemua 22 modul dan tidak diulang dalam bab modul.

| Kod | Keperluan | Keutamaan | Sumber |
| --- | --- | --- | --- |
| SRS-GEN-01 | Setiap modul hendaklah menguatkuasakan kebenaran melalui perkhidmatan RBAC pusat pada peringkat model, dan tidak melaksanakan kawalan capaian sendiri. | Wajib | A-16.03; ADR-06 |
| SRS-GEN-02 | Setiap tindakan cipta, kemas kini, hapus, lihat rekod sensitif, kelulusan, penolakan dan perubahan status hendaklah direkod dalam audit trail pusat tanpa modul boleh melangkaunya. | Wajib | A-16.09; A-3.09 |
| SRS-GEN-03 | Setiap modul yang memerlukan kelulusan hendaklah menggunakan enjin workflow pusat dan tidak melaksanakan logik kelulusan sendiri. | Wajib | A-3.06; ADR-05 |
| SRS-GEN-04 | Setiap modul hendaklah menyimpan dan mencapai dokumen melalui API DMS pusat, bukan storan fail sendiri. | Wajib | B-3.01; A-3.11 |
| SRS-GEN-05 | Setiap modul hendaklah menyokong carian global dan carian terperinci mengikut lot, mukim, daerah, status, jenis hak milik, nombor hak milik, pemilik dan kata kunci. | Wajib | A-3.12 |
| SRS-GEN-06 | Setiap modul hendaklah memaparkan antara muka yang mematuhi MyDS dan WCAG 2.1 Level AA, dan berfungsi pada desktop, tablet dan telefon pintar. | Wajib | A-3.03; A-3.04 |
| SRS-GEN-07 | Setiap borang kemasukan data hendaklah menyimpan draf secara automatik supaya kerja tidak hilang apabila sesi terputus. | Perlu | B-2.04 |
| SRS-GEN-08 | Setiap modul hendaklah menyediakan bantuan dalam talian berkonteks dan pautan kepada panduan langkah demi langkah. | Perlu | B-2.08 |
| SRS-GEN-09 | Antara muka, label, mesej sistem dan laporan setiap modul hendaklah dalam Bahasa Malaysia. | Wajib | A-1.08 |

## 6. Modul Perkhidmatan Platform

Bab ini menetapkan keperluan bagi enam modul perkhidmatan platform — Dokumen Digital, Workflow dan Kelulusan, Notifikasi, Pentadbiran Sistem, Pengurusan Pengguna/Peranan/Capaian serta Audit Trail — yang berfungsi sebagai lapisan asas yang digunakan bersama oleh semua modul operasi. Keenam-enamnya diserahkan dalam Gelombang G0 (Bulan 3.0–3.5) kerana setiap modul dalam gelombang berikutnya bergantung kepada lapisan ini untuk capaian, kelulusan, dokumen, notifikasi dan jejak audit.

### Modul 11 · Dokumen Digital (DMS)

**Kod:** MOD-DMS · **Gelombang:** G0 (Bulan 3.0–3.5) · **Rujukan:** RFP Seksyen 6.2 (Modul 11), 11.3.2, 11.4.2; Lampiran H B-3.01–B-3.08, A-3.11–A-3.13, A-16.07

Modul ini menyediakan repositori dokumen digital berpusat bagi semua rekod hartanah MAIS — geran, pelan, perjanjian, resit, surat rasmi, gambar tapak dan laporan — dengan metadata berstruktur, kawalan versi, carian teks penuh dan kawalan capaian berasaskan klasifikasi keselamatan. Ia merupakan satu-satunya saluran penyimpanan dokumen bagi keseluruhan SISDATA; tiada modul lain dibenarkan menyimpan fail secara berasingan.

**Kategori pengguna.** Pegawai Operasi (muat naik, kemas kini metadata); Pelulus (semakan dokumen sokongan); Pengguna Paparan (lihat dan muat turun mengikut kebenaran); Juruaudit (semakan log capaian); Pentadbir Aplikasi (kategori, polisi retensi, templat metadata); Pentadbir Sistem (pentadbiran platform, kuota storan); Pemegang Taruh Terhad (capaian dokumen tertentu melalui portal awam, baca sahaja).

**Keperluan fungsian**

| Kod | Keperluan | Keutamaan | Sumber |
| --- | --- | --- | --- |
| SRS-DMS-01 | Sistem hendaklah menyediakan repositori dokumen digital berpusat yang menyimpan semua rekod tanah, cukai, pajakan, kontrak, pelan, gambar, laporan dan surat rasmi dalam satu storan logikal tunggal. | Wajib | B-3.01; URS-DMS-01 |
| SRS-DMS-02 | Sistem hendaklah menyediakan antara muka pengurusan dokumen dalam Bahasa Malaysia yang menyokong muat naik seret-lepas berkelompok sekurang-kurangnya 20 fail serentak serta pratonton dokumen PDF, imej dan dokumen pejabat dalam pelayar tanpa muat turun. | Wajib | B-3.01; URS-DMS-02 |
| SRS-DMS-03 | Sistem hendaklah menerima muat naik hanya bagi jenis fail dalam senarai putih yang dikonfigurasi Pentadbir Aplikasi, dengan senarai lalai merangkumi PDF, JPG, JPEG, PNG, TIFF, DOCX, XLSX, PPTX, DWG, SHP, KML, KMZ dan GeoJSON. | Wajib | B-3.05; B-3.06 |
| SRS-DMS-04 | Sistem hendaklah mengesahkan jenis fail berdasarkan tandatangan kandungan fail (magic number) dan jenis MIME, bukan semata-mata sambungan nama fail; fail yang sambungannya tidak sepadan dengan kandungan hendaklah ditolak. | Wajib | B-3.06; RFP 11.3.2(c) |
| SRS-DMS-05 | Sistem hendaklah menguatkuasakan had saiz fail per muat naik yang boleh dikonfigurasi mengikut kategori dokumen, dengan nilai lalai 25 MB bagi dokumen pejabat dan imej serta 250 MB bagi fail spatial, dan memaparkan mesej ralat yang jelas apabila had dilampaui. | Wajib | B-3.06 |
| SRS-DMS-06 | Sistem hendaklah menguatkuasakan had saiz kumulatif per transaksi muat naik yang boleh dikonfigurasi, dengan nilai lalai 500 MB. | Perlu | Jurang |
| SRS-DMS-07 | Sistem hendaklah melaksanakan imbasan antivirus dan malware terhadap setiap fail sebelum disimpan ke repositori kekal; fail yang gagal imbasan dihalang daripada disimpan, diletakkan dalam kuarantin dan direkodkan dalam audit trail. | Wajib | B-3.06; RFP 11.3.2(c) |
| SRS-DMS-08 | Sistem hendaklah menghantar notifikasi kepada Pentadbir Sistem dan pengguna yang memuat naik apabila fail dikuarantin akibat kegagalan imbasan keselamatan. | Perlu | Jurang; A-3.14 |
| SRS-DMS-09 | Sistem hendaklah menyekat secara mutlak fail berisiko termasuk fail boleh laksana (.exe, .com, .bat, .cmd, .sh, .msi), skrip (.js, .vbs, .ps1), dokumen pejabat bermakro (.docm, .xlsm, .pptm), fail arkib bersarang melebihi satu aras, dan fail tanpa sambungan. | Wajib | B-3.06; RFP 11.3.2(c) |
| SRS-DMS-10 | Sistem hendaklah menyimpan metadata berstruktur bagi setiap dokumen merangkumi kategori, modul sumber, rekod induk berkaitan, daerah, mukim, tahun, status, pemilik proses, tarikh dokumen, klasifikasi keselamatan dan kata kunci bebas. | Wajib | B-3.03; URS-DMS-03 |
| SRS-DMS-11 | Sistem hendaklah membolehkan Pentadbir Aplikasi menambah, meminda atau menyahaktifkan medan metadata dan senarai nilai kategori dokumen tanpa pengubahsuaian kod sumber. | Wajib | B-3.03; A-3.10 |
| SRS-DMS-12 | Sistem hendaklah menyediakan carian dokumen berdasarkan mana-mana gabungan medan metadata serta carian teks penuh ke atas kandungan dokumen, termasuk hasil pengecaman aksara optik (OCR) bagi dokumen imbasan. | Wajib | B-3.04; A-3.12; URS-DMS-04 |
| SRS-DMS-13 | Sistem hendaklah menghadkan hasil carian kepada dokumen yang pengguna mempunyai kebenaran capaian; dokumen di luar skop tidak boleh dipaparkan dalam hasil carian mahupun dalam kiraan hasil. | Wajib | URS-DMS-04; A-16.03 |
| SRS-DMS-14 | Sistem hendaklah menyediakan kawalan versi automatik di mana muat naik versi baharu tidak menimpa versi terdahulu, setiap versi mengekalkan nombor versi, pemuat naik dan tarikh, serta perbandingan senarai versi tersedia. | Wajib | B-3.02; URS-DMS-05 |
| SRS-DMS-15 | Sistem hendaklah merekod status dokumen (aktif, tamat, diarkib, dilupus) dan tarikh luput, serta menjana notifikasi kepada pemilik proses sebelum tarikh luput mengikut tempoh amaran yang dikonfigurasi. | Wajib | B-3.02; A-3.14 |
| SRS-DMS-16 | Sistem hendaklah membenarkan lampiran satu atau lebih dokumen kepada setiap transaksi atau rekod dalam mana-mana modul, termasuk pelan tanah dan fail GIS, dengan pautan dua hala antara dokumen dan rekod induk. | Wajib | A-3.11; B-3.05 |
| SRS-DMS-17 | Sistem hendaklah menyediakan drill-down daripada senarai dokumen kepada rekod induk dan sebaliknya melalui pautan dalam yang menguatkuasakan kebenaran capaian. | Wajib | A-3.13 |
| SRS-DMS-18 | Sistem hendaklah mendedahkan semua fungsi dokumen melalui API yang disalurkan menerusi API Gateway SISDATA, dan semua modul lain hendaklah mencapai dokumen hanya melalui API tersebut. | Wajib | URS-DMS-06; A-15.01 |
| SRS-DMS-19 | Sistem hendaklah menguatkuasakan kawalan capaian dokumen berdasarkan klasifikasi keselamatan di mana dokumen berklasifikasi Sulit dan Rahsia memerlukan kebenaran eksplisit per pengguna atau per peranan. | Wajib | URS-DMS-07; A-16.03 |
| SRS-DMS-20 | Sistem hendaklah menyulitkan semua dokumen semasa penyimpanan menggunakan AES-256 atau algoritma setara yang diluluskan, dan semasa penghantaran menggunakan TLS 1.3. | Wajib | A-16.07; RFP 11.4.2 |
| SRS-DMS-21 | Sistem hendaklah menyediakan polisi retensi dokumen yang boleh dikonfigurasi berasingan bagi setiap kategori, meliputi tempoh retensi aktif, tempoh arkib dan tindakan selepas tempoh arkib. | Wajib | B-3.07; URS-DMS-08 |
| SRS-DMS-22 | Sistem hendaklah mengarkibkan dokumen secara automatik apabila tempoh retensi aktif tamat, memindahkannya ke storan arkib dan mengekalkan metadata serta keupayaan carian. | Wajib | B-3.07 |
| SRS-DMS-23 | Sistem hendaklah menghalang pelupusan dokumen yang diarkibkan tanpa kelulusan dua peringkat, dan merekodkan setiap pelupusan bersama justifikasi dalam audit trail. | Wajib | B-3.07; A-16.09 |
| SRS-DMS-24 | Sistem hendaklah merekod dalam audit trail setiap tindakan muat naik, muat turun, pratonton, cetak, pindaan metadata, perubahan versi, pemadaman dan pengarkiban dokumen, berserta ID pengguna, alamat IP, tarikh dan masa. | Wajib | B-3.08; A-16.09; URS-DMS-07 |
| SRS-DMS-25 | Sistem hendaklah memaparkan kuota dan penggunaan storan dokumen semasa kepada Pentadbir Sistem serta menjana amaran apabila penggunaan melebihi 80 peratus kapasiti yang diperuntukkan. | Perlu | Jurang; URS-ADM-04 |
| SRS-DMS-26 | Sistem hendaklah membubuh tanda air dinamik yang memuatkan ID pengguna, tarikh dan masa pada pratonton dan cetakan dokumen berklasifikasi Sulit dan Rahsia. | Baik-ada | Jurang |

**Aliran proses utama.**

1. Pengguna memilih rekod induk dalam modul operasi dan memulakan muat naik dokumen.
2. Sistem mengesahkan jenis fail terhadap senarai putih, mengesahkan tandatangan kandungan, dan menyemak had saiz; fail yang gagal ditolak dengan mesej ralat tertentu.
3. Sistem melaksanakan imbasan antivirus dan malware dalam storan sementara; fail yang gagal dikuarantin dan notifikasi dijana.
4. Pengguna melengkapkan metadata wajib; sistem mewarisi daerah, mukim dan rekod induk secara automatik.
5. Sistem menyimpan fail dengan penyulitan AES-256, memberikan nombor versi dan ID dokumen, serta mengindeks metadata dan teks penuh.
6. Sistem merekod tindakan dalam audit trail dan memaparkan dokumen dalam senarai dokumen rekod induk.
7. Apabila tempoh retensi aktif tamat, job scheduler mengarkibkan dokumen secara automatik.
8. Pelupusan selepas tempoh arkib hanya dilaksanakan selepas kelulusan dua peringkat, dengan justifikasi direkod.

**Entiti data.** `DOKUMEN` (id\_dokumen, no\_rujukan, kategori, modul\_sumber, id\_rekod\_induk, jenis\_entiti\_induk, nama\_fail, jenis\_mime, saiz\_bait, cincangan\_fail, klasifikasi\_keselamatan, status, tarikh\_dokumen, tarikh\_luput, pemilik\_proses, id\_pencipta, tarikh\_cipta); `DOKUMEN_VERSI`; `DOKUMEN_METADATA`; `DOKUMEN_TAG`; `DOKUMEN_KATEGORI` (kod\_kategori, nama, jenis\_fail\_dibenarkan, had\_saiz\_mb, tempoh\_retensi\_bulan, tempoh\_arkib\_bulan, tindakan\_selepas\_arkib); `DOKUMEN_KEBENARAN`; `DOKUMEN_IMBASAN` (id\_imbasan, id\_dokumen, enjin, versi\_tandatangan, keputusan, tarikh\_imbasan); `DOKUMEN_ARKIB`.

**Integrasi.** Semua modul operasi melalui API Gateway; enjin OCR bagi pengindeksan teks penuh; enjin antivirus untuk imbasan pra-simpan; storan objek bersulit dan storan arkib berperingkat; MOD-AUD bagi jejak audit; MOD-NTF bagi notifikasi luput dan amaran kuarantin; MOD-ADM bagi job scheduler dan polisi retensi; Modul GIS bagi fail spatial; portal awam bagi capaian dokumen terhad.

**Kawalan keselamatan.**

- Senarai putih jenis fail, pengesahan tandatangan kandungan dan sekatan mutlak fail berisiko
- Imbasan antivirus wajib sebelum penyimpanan kekal, dengan kuarantin bagi fail gagal
- Penyulitan at-rest AES-256 dan in-transit TLS 1.3
- Kawalan capaian berasaskan peranan dan klasifikasi dokumen, dikuatkuasakan juga pada lapisan API
- URL muat turun bertempoh untuk menghalang pengagihan pautan tanpa kebenaran
- Audit penuh bagi semua capaian dan pindaan, termasuk pratonton dan cetakan
- Pemadaman logikal sahaja; pemadaman fizikal hanya melalui proses pelupusan terkawal

**Laporan yang boleh dijana.** Inventori dokumen mengikut kategori, modul, daerah, tahun dan klasifikasi; dokumen hampir luput dan telah luput mengikut pemilik proses; dokumen tiba tempoh retensi dan menunggu pengarkiban atau pelupusan; capaian dokumen Sulit dan Rahsia mengikut pengguna dan tempoh; fail dikuarantin akibat kegagalan imbasan; penggunaan storan mengikut modul dan kategori; kelengkapan dokumen sokongan per rekod hartanah.

**Kriteria penerimaan.**

1. Muat naik fail `.exe`, `.docm` dan fail tanpa sambungan ditolak; muat naik fail yang sambungannya ditukar juga ditolak melalui pengesahan tandatangan kandungan.
2. Fail ujian yang mengandungi tandatangan malware ujian (EICAR) dihalang daripada disimpan, dikuarantin, dan notifikasi diterima Pentadbir Sistem dalam tempoh 5 minit.
3. Muat naik fail melebihi had saiz kategori ditolak dengan mesej ralat yang menyatakan had tersebut, dan had boleh diubah oleh Pentadbir Aplikasi tanpa pelepasan kod baharu.
4. Carian teks penuh ke atas dokumen imbasan PDF mengembalikan dokumen berdasarkan perkataan dalam kandungan, dan pengguna tanpa kebenaran tidak melihat dokumen tersebut dalam hasil carian.
5. Muat naik versi kedua mengekalkan versi pertama dan kedua-duanya boleh dicapai serta dibandingkan.
6. Dokumen yang tempoh retensinya tamat diarkibkan automatik, metadata kekal boleh dicari, dan percubaan pelupusan tanpa kelulusan dua peringkat ditolak.
7. Log audit memaparkan rekod lengkap bagi muat naik, muat turun, pratonton, cetak, pindaan metadata, pemadaman dan pengarkiban dokumen ujian, berserta alamat IP dan cap masa.

### Modul 14 · Workflow dan Kelulusan

**Kod:** MOD-WFE · **Gelombang:** G0 (Bulan 3.0–3.5) · **Rujukan:** RFP Seksyen 6.2 (Modul 14); Lampiran H A-3.05–A-3.09, A-16.02, A-16.09

Modul ini menyediakan enjin aliran kerja berpusat yang boleh dikonfigurasi bagi menyokong proses semakan, kelulusan, penugasan, eskalasi dan tindakan susulan merentas semua modul yang memerlukan kelulusan. Enjin mengasingkan definisi proses daripada kod aplikasi supaya perubahan dasar kelulusan MAIS dapat dilaksanakan melalui konfigurasi tanpa pengubahsuaian kod sumber.

**Kategori pengguna.** Pegawai Operasi (pemohon, pelaksana tugasan); Pelulus (semakan dan kelulusan mengikut peringkat); Pentadbir Aplikasi (reka bentuk dan konfigurasi aliran kerja); Pentadbir Sistem (pengesahan perubahan kritikal); Pengurusan Tertinggi (pemantauan papan pemuka); Juruaudit (semakan sejarah keputusan dan pematuhan pengasingan tugas).

**Keperluan fungsian**

| Kod | Keperluan | Keutamaan | Sumber |
| --- | --- | --- | --- |
| SRS-WFE-01 | Sistem hendaklah menyediakan aliran kerja pelbagai peringkat kelulusan yang boleh ditetapkan secara berbeza mengikut modul, peranan, unit organisasi, jenis transaksi dan nilai ambang kewangan. | Wajib | A-3.05; URS-WFE-01 |
| SRS-WFE-02 | Sistem hendaklah menyokong peringkat kelulusan bersiri dan selari, serta syarat cabangan berdasarkan nilai transaksi, kategori tanah, daerah atau medan rekod lain yang ditetapkan. | Wajib | A-3.05; URS-WFE-01 |
| SRS-WFE-03 | Sistem hendaklah menyediakan enjin workflow yang boleh dikonfigurasi sepenuhnya oleh Pentadbir Aplikasi secara no-code atau low-code tanpa pengubahsuaian kod sumber, meliputi definisi peringkat, peranan pelulus, syarat peralihan, syarat eskalasi dan templat aliran kerja. | Wajib | A-3.06; URS-WFE-01 |
| SRS-WFE-04 | Sistem hendaklah menyediakan pustaka templat aliran kerja yang boleh diguna semula dan disalin sebagai asas kepada aliran kerja baharu. | Wajib | A-3.06 |
| SRS-WFE-05 | Sistem hendaklah membolehkan penugasan peringkat kelulusan kepada peranan, jawatan atau individu tertentu, serta kepada kumpulan pelulus di mana mana-mana ahli boleh mengambil tindakan. | Wajib | A-3.06; URS-WFE-01 |
| SRS-WFE-06 | Sistem hendaklah membolehkan penetapan had masa tindakan dalam unit hari bekerja atau jam bagi setiap peringkat, dengan mengambil kira kalendar cuti umum Negeri Selangor yang boleh dikemas kini pentadbir. | Wajib | A-3.07; URS-WFE-01 |
| SRS-WFE-07 | Sistem hendaklah melaksanakan eskalasi automatik apabila tugasan melepasi had masa yang ditetapkan, dengan notifikasi kepada penyelia bertanggungjawab mengikut hierarki peranan yang dikonfigurasi. | Wajib | A-3.07; URS-WFE-01 |
| SRS-WFE-08 | Sistem hendaklah membolehkan pentadbir mengkonfigurasi aras eskalasi berperingkat sekurang-kurangnya dua aras, tempoh pencetus setiap aras dan penerima notifikasi bagi setiap aras. | Wajib | A-3.06; A-3.07 |
| SRS-WFE-09 | Sistem hendaklah memaparkan setiap aliran kerja sebagai rajah alir visual yang boleh disunting secara seret-lepas, dan perubahan melalui editor visual mengubah definisi aliran yang sama seperti kaedah konfigurasi lain. | Wajib | URS-WFE-03; A-3.06 |
| SRS-WFE-10 | Sistem hendaklah membolehkan pengguna berkuasa mencipta atau mengubah aliran kerja melalui arahan bahasa biasa yang diterjemahkan kepada definisi aliran dan dipaparkan untuk pengesahan sebelum diaktifkan. | Perlu | URS-WFE-02 |
| SRS-WFE-11 | Sistem hendaklah mewajibkan pengesahan dua peringkat bagi setiap perubahan aliran kerja yang dijana melalui arahan AI, meliputi pratonton simulasi menggunakan kes contoh dan kelulusan oleh pentadbir kedua, sebelum aliran baharu berkuat kuasa. | Perlu | URS-WFE-05 |
| SRS-WFE-12 | Sistem hendaklah menyimpan setiap definisi aliran kerja secara berversi di mana versi lama diarkibkan, kes yang sedang berjalan meneruskan versi asalnya kecuali dipindahkan dengan kelulusan pentadbir, dan setiap perubahan merekodkan pengguna, tarikh, masa serta arahan yang digunakan. | Wajib | URS-WFE-04; A-16.09 |
| SRS-WFE-13 | Sistem hendaklah menyediakan papan pemuka pemantauan workflow yang menunjukkan status semua aliran kerja aktif, bilangan tugasan tertangguh mengikut peringkat dan pelulus, rekod tindakan lepas, serta statistik masa penyelesaian purata mengikut jenis permohonan. | Wajib | A-3.08 |
| SRS-WFE-14 | Sistem hendaklah menyediakan papan tugasan per pengguna yang memaparkan tugasan menunggu tindakan, baki masa SLA, dan tindakan pantas lulus, tolak atau kembalikan. | Wajib | URS-WFE-07; A-3.08 |
| SRS-WFE-15 | Sistem hendaklah menyediakan drill-down daripada papan pemuka workflow kepada rekod transaksi sumber bagi membolehkan semakan punca data. | Wajib | A-3.13; A-3.08 |
| SRS-WFE-16 | Sistem hendaklah menyimpan rekod penuh sejarah workflow bagi setiap transaksi termasuk identiti pengguna yang meluluskan, menolak atau menangguhkan, tarikh dan masa setiap tindakan, peringkat berkenaan serta ulasan keputusan, sebagai sebahagian audit trail. | Wajib | A-3.09; URS-WFE-04 |
| SRS-WFE-17 | Sistem hendaklah mewajibkan justifikasi bertulis bagi setiap tindakan tolak, kembalikan atau tangguh, dan tidak membenarkan tindakan diteruskan tanpa justifikasi diisi. | Wajib | A-3.09; URS-WFE-07 |
| SRS-WFE-18 | Sistem hendaklah menguatkuasakan pengasingan tugas di mana pemohon tidak boleh meluluskan permohonan yang dimulakan sendiri, dan pengguna yang sama tidak boleh menduduki dua peringkat kelulusan berturutan dalam satu transaksi. | Wajib | URS-WFE-06; RFP 11.2.2(c) |
| SRS-WFE-19 | Sistem hendaklah mewajibkan MFA bagi tindakan kelulusan pada peringkat kritikal termasuk pelupusan, tukar syarat, perubahan hak milik dan perubahan konfigurasi aliran kerja. | Wajib | A-16.02; URS-WFE-06 |
| SRS-WFE-20 | Sistem hendaklah melaksanakan tindakan automatik selepas keputusan aliran kerja termasuk kemas kini status rekod, penjanaan notifikasi, penciptaan tugasan susulan dan pencetusan integrasi berkaitan. | Wajib | URS-WFE-01; A-3.14 |
| SRS-WFE-21 | Sistem hendaklah membolehkan pelulus menetapkan pemangku bagi tempoh tertentu, dengan tugasan baharu disalurkan kepada pemangku dan identiti pelulus asal serta pemangku direkod dalam audit trail. | Perlu | Jurang |
| SRS-WFE-22 | Sistem hendaklah membolehkan pemohon menarik semula permohonan yang belum menerima keputusan, dengan penarikan direkodkan sebagai peristiwa aliran kerja. | Baik-ada | Jurang |
| SRS-WFE-23 | Sistem hendaklah menjana laporan prestasi aliran kerja yang menunjukkan purata tempoh per peringkat, peringkat yang menjadi kesesakan, kadar eskalasi dan bilangan tugasan tertunggak mengikut pelulus. | Wajib | URS-WFE-08; A-3.08 |
| SRS-WFE-24 | Sistem hendaklah merekodkan setiap arahan AI bagi penjanaan atau pindaan aliran kerja berserta definisi yang terhasil dalam audit trail, dan model AI berkenaan berjalan dalam persekitaran AWS MAIS yang diluluskan. | Perlu | URS-WFE-09; A-16.09 |

**Aliran proses utama.**

1. Pentadbir Aplikasi mereka bentuk aliran kerja melalui editor visual atau arahan bahasa biasa, menetapkan peringkat, pelulus, syarat cabangan, SLA dan peraturan eskalasi.
2. Sistem memaparkan pratonton simulasi menggunakan kes contoh; perubahan yang dijana AI memerlukan kelulusan pentadbir kedua sebelum diaktifkan.
3. Pengguna dalam modul operasi memulakan transaksi; enjin mencetuskan instans aliran kerja berdasarkan jenis transaksi, nilai dan atribut rekod.
4. Enjin menugaskan tugasan kepada peringkat pertama, menghantar notifikasi dan memulakan pengiraan SLA.
5. Pelulus mengambil tindakan lulus, tolak, kembalikan atau tangguh; justifikasi wajib bagi tindakan selain lulus, dan MFA dikuatkuasakan bagi peringkat kritikal.
6. Enjin menilai syarat peralihan dan memindahkan instans ke peringkat berikutnya, atau menamatkan instans dan melaksanakan tindakan automatik.
7. Sekiranya SLA dilampaui, enjin mencetuskan eskalasi berperingkat kepada penyelia mengikut hierarki yang dikonfigurasi.
8. Setiap tindakan, peralihan dan eskalasi direkodkan dalam sejarah workflow dan audit trail, serta dipaparkan pada papan pemuka.

**Entiti data.** `WF_DEFINISI` (id\_definisi, kod\_aliran, nama, modul, jenis\_transaksi, no\_versi, status, tarikh\_berkuat\_kuasa, id\_pencipta, kaedah\_cipta); `WF_PERINGKAT` (id\_peringkat, id\_definisi, no\_urutan, nama\_peringkat, mod\_kelulusan, sla\_nilai, sla\_unit, perlu\_mfa, perlu\_justifikasi); `WF_PELULUS`; `WF_SYARAT`; `WF_ESKALASI`; `WF_INSTANS` (id\_instans, id\_definisi, no\_versi\_definisi, modul, id\_rekod, id\_pemohon, status, tarikh\_mula, tarikh\_tamat, peringkat\_semasa); `WF_TUGASAN`; `WF_TINDAKAN` (id\_tindakan, id\_tugasan, id\_pengguna, peranan\_semasa, jenis\_tindakan, justifikasi, alamat\_ip, tarikh\_masa); `WF_ESKALASI_LOG`; `WF_PEMANGKU`; `WF_PROMPT_LOG`.

**Integrasi.** Semua modul operasi yang memerlukan semakan atau kelulusan; MOD-NTF bagi notifikasi tugasan, peringatan SLA dan eskalasi; MOD-RBC bagi penyelesaian peranan pelulus, hierarki penyeliaan dan penguatkuasaan MFA; MOD-AUD bagi perekodan sejarah keputusan; MOD-ADM bagi job scheduler semakan SLA; MOD-DMS bagi dokumen sokongan tugasan; perkhidmatan AI dalam VPC MAIS bagi penjanaan definisi aliran.

**Kawalan keselamatan.**

- Pengasingan tugas dikuatkuasakan pada peringkat enjin, bukan pada antara muka sahaja
- MFA wajib bagi kelulusan peringkat kritikal
- Kelulusan pentadbir kedua bagi pengaktifan atau pindaan definisi aliran kerja
- Definisi aliran berversi dan tidak boleh dipadam; versi lama diarkibkan
- Setiap tindakan kelulusan merekodkan peranan semasa pengguna pada masa tindakan, bukan peranan terkini
- Justifikasi wajib bagi tindakan tolak, kembalikan dan tangguh
- Capaian papan tugasan dihadkan kepada tugasan yang ditugaskan kepada pengguna atau kumpulannya

**Laporan yang boleh dijana.** Status aliran kerja aktif mengikut modul, jenis transaksi, peringkat dan daerah; tugasan tertunggak dan melepasi SLA mengikut pelulus, bahagian dan tempoh; purata masa penyelesaian mengikut jenis permohonan dan peringkat; kesesakan proses; kadar dan sejarah eskalasi mengikut aras; sejarah keputusan per transaksi; perubahan definisi aliran mengikut tempoh dan pentadbir; pematuhan pengasingan tugas.

**Kriteria penerimaan.**

1. Pentadbir Aplikasi berjaya menambah satu peringkat kelulusan baharu bagi transaksi bernilai melebihi ambang yang ditetapkan tanpa pelepasan kod baharu, dan transaksi baharu melalui peringkat tersebut sementara kes yang sedang berjalan meneruskan versi asal.
2. Tugasan yang melepasi had masa SLA mencetuskan eskalasi aras pertama kepada penyelia dalam tempoh yang dikonfigurasi, dan eskalasi aras kedua apabila tempoh kedua dilampaui.
3. Percubaan pemohon meluluskan permohonan sendiri ditolak dengan mesej pengasingan tugas, termasuk apabila percubaan dibuat melalui API secara langsung.
4. Tindakan kelulusan pada peringkat kritikal tidak dapat diselesaikan tanpa pengesahan MFA yang berjaya.
5. Tindakan tolak tanpa justifikasi ditolak; tindakan tolak dengan justifikasi direkod dan dipaparkan dalam sejarah workflow transaksi.
6. Papan pemuka memaparkan bilangan tugasan aktif, tertangguh dan purata masa penyelesaian yang selaras dengan data transaksi ujian, dan drill-down membuka rekod sumber yang betul.
7. Laporan sejarah workflow bagi satu transaksi ujian memaparkan setiap peringkat, pelulus, keputusan, justifikasi, tarikh dan masa secara lengkap dan berurutan.

### Modul 15 · Notifikasi

**Kod:** MOD-NTF · **Gelombang:** G0 (Bulan 3.0–3.5) · **Rujukan:** RFP Seksyen 6.2 (Modul 15); Lampiran H A-3.14–A-3.18

Modul ini menyediakan enjin notifikasi berpusat yang menyalurkan peringatan, amaran, tugasan dan eskalasi kepada pengguna yang betul melalui saluran dalam sistem dan e-mel, berdasarkan peraturan yang boleh dikonfigurasi tanpa pengubahsuaian kod sumber. Ia juga menyediakan pengurusan notifikasi oleh pengguna serta jejak audit penghantaran bagi setiap notifikasi yang dijana.

**Kategori pengguna.** Semua kategori pengguna sebagai penerima; Pentadbir Aplikasi (peraturan, templat, senarai penerima, kekerapan); Pentadbir Sistem (saluran penghantaran, konfigurasi gateway e-mel, pemantauan baris gilir); Juruaudit (semakan log penghantaran); Pemegang Taruh Terhad (notifikasi status permohonan melalui portal awam).

**Keperluan fungsian**

| Kod | Keperluan | Keutamaan | Sumber |
| --- | --- | --- | --- |
| SRS-NTF-01 | Sistem hendaklah menjana notifikasi automatik melalui saluran dalam sistem dan e-mel bagi tindakan tertunggak, tamat tempoh, semakan, kelulusan, cukai, pajakan, kontrak dan isu risiko. | Wajib | A-3.14; URS-NTF-01 |
| SRS-NTF-02 | Sistem hendaklah menyediakan enjin notifikasi berpusat tunggal yang digunakan oleh semua modul; tiada modul dibenarkan menghantar notifikasi secara berasingan di luar enjin ini. | Wajib | A-3.15; URS-NTF-01 |
| SRS-NTF-03 | Sistem hendaklah membolehkan Pentadbir Aplikasi mengkonfigurasi bagi setiap jenis notifikasi: templat mesej, kekerapan penghantaran, saluran dan senarai penerima, tanpa pengubahsuaian kod sumber. | Wajib | A-3.15; A-3.10; URS-NTF-03 |
| SRS-NTF-04 | Sistem hendaklah menyokong saluran dalam sistem dan e-mel, serta menyediakan antara muka integrasi sedia pakai bagi saluran SMS dan WhatsApp sekiranya MAIS melanggan perkhidmatan berkenaan kemudian. | Wajib | A-3.14; URS-NTF-02 |
| SRS-NTF-05 | Sistem hendaklah menyediakan templat mesej dalam Bahasa Malaysia dengan medan dinamik — nombor lot, nama penyewa, tarikh tamat tempoh, baki hari — yang boleh disunting pentadbir melalui antara muka. | Wajib | A-3.15; URS-NTF-03 |
| SRS-NTF-06 | Sistem hendaklah menyokong pencetus notifikasi berasaskan peristiwa dan berasaskan jadual. | Wajib | A-3.15; A-3.18; URS-NTF-03 |
| SRS-NTF-07 | Sistem hendaklah menyediakan peringatan berjadual bagi sekurang-kurangnya cukai akan matang, pajakan hampir tamat, tarikh prosiding kes mahkamah, jadual pemantauan tanah, jadual penyelenggaraan aset dan tugasan hampir melepasi SLA, dengan jarak hari peringatan dan senarai penerima boleh dikonfigurasi bagi setiap jenis. | Wajib | A-3.14; URS-NTF-04 |
| SRS-NTF-08 | Sistem hendaklah menetapkan aras keutamaan bagi setiap jenis notifikasi (Kritikal, Tinggi, Biasa, Maklumat) yang mempengaruhi paparan, susunan senarai dan peraturan eskalasi. | Wajib | A-3.16 |
| SRS-NTF-09 | Sistem hendaklah membolehkan pengguna menetapkan keutamaan dan saluran penerimaan sendiri bagi jenis notifikasi yang tidak ditanda wajib oleh pentadbir. | Wajib | A-3.16; URS-NTF-07 |
| SRS-NTF-10 | Sistem hendaklah menghalang pengguna daripada mematikan jenis notifikasi yang ditanda wajib, dan memaparkannya sebagai tidak boleh diubah dalam tetapan pengguna. | Wajib | URS-NTF-07; A-3.15 |
| SRS-NTF-11 | Sistem hendaklah membolehkan pengguna menanda notifikasi sebagai sudah baca atau belum baca secara individu dan berkelompok, serta menapis senarai mengikut status bacaan. | Wajib | A-3.16 |
| SRS-NTF-12 | Sistem hendaklah membolehkan pengguna memadam notifikasi daripada senarai peribadinya sebagai pemadaman logikal yang tidak memadam rekod penghantaran dalam log audit. | Wajib | A-3.16; A-3.17 |
| SRS-NTF-13 | Sistem hendaklah membolehkan pengguna mengarkibkan notifikasi ke folder arkib peribadi yang kekal boleh dicapai dan dicari. | Wajib | A-3.16 |
| SRS-NTF-14 | Sistem hendaklah menyediakan carian dan tapisan senarai notifikasi berdasarkan jenis, keutamaan, modul, status bacaan dan tempoh tarikh. | Perlu | A-3.16; A-3.12 |
| SRS-NTF-15 | Sistem hendaklah merekodkan audit trail bagi setiap notifikasi yang dihantar termasuk jenis, penerima, saluran, tarikh dan masa penghantaran, serta status berjaya atau gagal berserta kod ralat. | Wajib | A-3.17 |
| SRS-NTF-16 | Sistem hendaklah merekodkan status hantar, terima dan baca bagi setiap notifikasi berserta cap masa bagi setiap peralihan status. | Wajib | A-3.17; URS-NTF-05 |
| SRS-NTF-17 | Sistem hendaklah mencuba semula penghantaran yang gagal secara automatik sekurang-kurangnya tiga kali dengan selang masa menaik, dan menanda notifikasi sebagai gagal muktamad serta memaklumkan Pentadbir Sistem selepas percubaan terakhir. | Perlu | A-3.17 |
| SRS-NTF-18 | Sistem hendaklah mengeskalasi notifikasi berkeutamaan Kritikal yang tidak dibaca dalam tempoh dikonfigurasi kepada penyelia penerima mengikut hierarki peranan. | Wajib | URS-NTF-05; A-3.07 |
| SRS-NTF-19 | Sistem hendaklah memastikan kandungan notifikasi tidak memuatkan data berklasifikasi Sulit atau Rahsia, dan hanya memuatkan ringkasan serta pautan kepada rekod yang menguatkuasakan kebenaran capaian sendiri. | Wajib | URS-NTF-06; A-16.03 |
| SRS-NTF-20 | Sistem hendaklah menyediakan pilihan notifikasi ringkasan berkala yang menggabungkan notifikasi berkeutamaan Biasa dan Maklumat ke dalam satu penghantaran. | Perlu | URS-NTF-07 |
| SRS-NTF-21 | Sistem hendaklah memproses penghantaran melalui baris gilir mesej kekal supaya notifikasi tidak hilang sekiranya perkhidmatan penghantaran tidak tersedia pada masa pencetusan. | Perlu | Jurang; A-3.18 |
| SRS-NTF-22 | Sistem hendaklah menghalang penghantaran berulang bagi peristiwa yang sama kepada penerima yang sama dalam tempoh pendiaman yang dikonfigurasi. | Perlu | Jurang |
| SRS-NTF-23 | Sistem hendaklah menyediakan papan pemuka pemantauan notifikasi kepada Pentadbir Sistem yang memaparkan bilangan dalam baris gilir, kadar kejayaan mengikut saluran dan senarai kegagalan terkini. | Perlu | A-3.17; URS-ADM-04 |
| SRS-NTF-24 | Sistem hendaklah membolehkan pentadbir menghantar notifikasi pengumuman secara manual kepada kumpulan penerima terpilih mengikut peranan, bahagian atau daerah. | Baik-ada | Jurang |

**Aliran proses utama.**

1. Modul operasi atau job scheduler mencetuskan peristiwa notifikasi kepada enjin berpusat.
2. Enjin memadankan peristiwa dengan peraturan yang berkenaan dan menyelesaikan senarai penerima berdasarkan peranan, daerah, bahagian atau individu.
3. Enjin menapis senarai penerima mengikut tetapan keutamaan peribadi, kecuali bagi jenis yang ditanda wajib.
4. Enjin menjana mesej daripada templat dengan mengisi medan dinamik, dan menyemak tiada data Sulit atau Rahsia dimuatkan dalam kandungan.
5. Enjin memasukkan mesej ke dalam baris gilir mengikut saluran dan merekodkan status hantar.
6. Perkhidmatan penghantaran menghantar mesej, merekodkan status, dan mencuba semula penghantaran yang gagal mengikut peraturan backoff.
7. Notifikasi dipaparkan dalam senarai pengguna; pengguna menanda baca, memadam atau mengarkib, dan setiap peralihan status direkodkan.
8. Notifikasi Kritikal yang belum dibaca dalam tempoh dikonfigurasi dieskalasi kepada penyelia dan direkodkan dalam log eskalasi.

**Entiti data.** `NTF_JENIS` (kod\_jenis, nama, modul, keutamaan, wajib, saluran\_lalai, tempoh\_eskalasi, tempoh\_pendiaman); `NTF_PERATURAN`; `NTF_TEMPLAT`; `NTF_PENERIMA_PERATURAN`; `NTF_MESEJ`; `NTF_PENGHANTARAN` (id\_penghantaran, id\_mesej, id\_penerima, saluran, status\_hantar, bilangan\_cubaan, kod\_ralat, tarikh\_hantar, tarikh\_terima, tarikh\_baca); `NTF_STATUS_PENGGUNA`; `NTF_KEUTAMAAN_PENGGUNA`; `NTF_ESKALASI_LOG`.

**Integrasi.** Semua modul operasi sebagai sumber peristiwa; MOD-WFE bagi notifikasi tugasan, SLA dan eskalasi; MOD-ADM bagi job scheduler dan konfigurasi templat; MOD-RBC bagi penyelesaian senarai penerima; MOD-AUD bagi jejak audit penghantaran; gateway e-mel rasmi MAIS; baris gilir mesej kekal; antara muka sedia pakai bagi gateway SMS dan WhatsApp; portal awam bagi notifikasi status permohonan.

**Kawalan keselamatan.**

- Tiada data Sulit atau Rahsia dalam kandungan notifikasi; hanya ringkasan dan pautan dalam
- Pautan dalam notifikasi menguatkuasakan pengesahan dan kebenaran capaian secara berasingan
- Penghantaran e-mel menggunakan sambungan TLS kepada gateway e-mel MAIS
- Log penghantaran tidak boleh dipinda atau dipadam oleh pengguna; pemadaman oleh pengguna hanya mempengaruhi paparan peribadi
- Konfigurasi peraturan dan templat dihadkan kepada peranan pentadbir dan direkodkan dengan nilai sebelum dan selepas
- Kawalan kadar penghantaran bagi menghalang penyalahgunaan saluran

**Laporan yang boleh dijana.** Penghantaran notifikasi mengikut jenis, saluran, tempoh dan status; kegagalan penghantaran mengikut saluran dan kod ralat; notifikasi kritikal belum dibaca dan yang dieskalasi; kadar bacaan mengikut penerima, bahagian dan jenis; peringatan berjadual yang dijana mengikut jenis; perubahan peraturan dan templat mengikut tempoh dan pentadbir.

**Kriteria penerimaan.**

1. Pentadbir Aplikasi mengubah templat mesej dan jarak hari peringatan bagi satu jenis notifikasi melalui antara muka, dan notifikasi seterusnya menggunakan templat serta jarak hari baharu tanpa pelepasan kod.
2. Peringatan pajakan hampir tamat dijana automatik oleh job scheduler pada jarak hari yang dikonfigurasi dan diterima oleh penerima yang betul melalui kedua-dua saluran.
3. Pengguna berjaya menanda baca, memadam dan mengarkibkan notifikasi daripada senarai peribadinya, manakala rekod penghantaran kekal dalam log audit.
4. Pengguna tidak dapat mematikan jenis notifikasi yang ditanda wajib; tetapan dipaparkan sebagai tidak boleh diubah.
5. Log audit notifikasi memaparkan bagi setiap notifikasi ujian: saluran, tarikh dan masa penghantaran, status hantar, terima dan baca serta kod ralat bagi kegagalan.
6. Penghantaran e-mel yang gagal akibat gateway tidak tersedia dicuba semula automatik sekurang-kurangnya tiga kali, dan Pentadbir Sistem dimaklumkan selepas kegagalan muktamad.
7. Notifikasi Kritikal yang tidak dibaca dalam tempoh dikonfigurasi dieskalasi kepada penyelia penerima dan eskalasi tersebut direkodkan.

### Modul 17 · Pentadbiran Sistem

**Kod:** MOD-ADM · **Gelombang:** G0 (Bulan 3.0–3.5) · **Rujukan:** RFP Seksyen 6.2 (Modul 17), 11.3; Lampiran H A-3.10, A-3.12, A-3.13, A-3.18–A-3.20, A-16.13–A-16.15

Modul ini menyediakan konsol pentadbiran berpusat yang membolehkan Bahagian ICT MAIS mengkonfigurasi dan mengurus keseluruhan sistem — parameter, kod rujukan, templat, tetapan integrasi, tugasan berjadual, log aplikasi dan pemantauan kesihatan sistem — tanpa pengubahsuaian kod sumber. Ia juga merangkumi kawalan kejuruteraan yang menyokong standard secure coding dan amalan DevSecOps.

**Kategori pengguna.** Pentadbir Sistem (semua fungsi termasuk tetapan keselamatan dan integrasi); Pentadbir Aplikasi (parameter aplikasi, kod rujukan, templat, konfigurasi modul dalam skop diluluskan); Ketua Bahagian (kelulusan kedua bagi konfigurasi kritikal); Juruaudit (semakan log konfigurasi dan log aplikasi, baca sahaja).

**Keperluan fungsian**

| Kod | Keperluan | Keutamaan | Sumber |
| --- | --- | --- | --- |
| SRS-ADM-01 | Sistem hendaklah membolehkan pentadbir mengkonfigurasi menu, parameter sistem, kod rujukan, peranan, hak akses, templat notifikasi dan tetapan sistem melalui antara muka tanpa mengubah kod sumber. | Wajib | A-3.10; URS-ADM-02 |
| SRS-ADM-02 | Sistem hendaklah menyediakan pengurusan kod rujukan yang menyokong cipta, pinda, nyahaktif dan carian bagi sekurang-kurangnya daerah, mukim, kategori tanah, jenis hak milik, jenis dokumen, kategori aduan, jenis urusan dan status transaksi, dengan kod yang dinyahaktifkan kekal sah bagi rekod sejarah. | Wajib | A-3.10; URS-ADM-02 |
| SRS-ADM-03 | Sistem hendaklah membolehkan pentadbir mengurus templat laporan dan notifikasi termasuk kandungan, medan dinamik, format output dan versi templat. | Wajib | A-3.10; URS-ADM-02 |
| SRS-ADM-04 | Sistem hendaklah membolehkan pentadbir mengurus tetapan integrasi termasuk endpoint, kelayakan capaian, tempoh tamat masa, kadar capaian dan jadual pertukaran data bagi setiap integrasi yang diluluskan. | Wajib | URS-ADM-02; A-15.01 |
| SRS-ADM-05 | Sistem hendaklah menyediakan API pentadbiran berskop pentadbir bagi semua fungsi konfigurasi utama supaya tugas rutin seperti penambahan kod rujukan berkelompok dan penjadualan semula integrasi boleh diautomasikan. | Perlu | URS-ADM-03 |
| SRS-ADM-06 | Sistem hendaklah menyediakan job scheduler yang membolehkan pentadbir mendefinisikan, mengaktifkan, menyahaktifkan dan melaksanakan secara manual tugasan berkala, dengan jadual dinyatakan dalam ungkapan cron atau selang masa tetap. | Wajib | A-3.18 |
| SRS-ADM-07 | Sistem hendaklah menyokong melalui job scheduler sekurang-kurangnya kategori tugasan berikut: pengemaskinian data, penghantaran notifikasi berjadual, penjanaan laporan berkala, pengarkiban dokumen dan audit, serta integrasi berjadual. | Wajib | A-3.18 |
| SRS-ADM-08 | Sistem hendaklah merekod dan memaparkan sejarah pelaksanaan setiap tugasan berjadual termasuk masa mula, masa tamat, tempoh, status, bilangan rekod diproses dan mesej ralat. | Wajib | A-3.18; A-3.19 |
| SRS-ADM-09 | Sistem hendaklah menghalang pelaksanaan serentak bagi instans tugasan berjadual yang sama dan mencuba semula tugasan yang gagal mengikut bilangan percubaan yang dikonfigurasi. | Perlu | A-3.18 |
| SRS-ADM-10 | Sistem hendaklah menghantar notifikasi kepada Pentadbir Sistem apabila tugasan berjadual gagal atau tidak dilaksanakan dalam tempoh jadual yang ditetapkan. | Perlu | A-3.18; A-3.14 |
| SRS-ADM-11 | Sistem hendaklah menyediakan sistem log ralat aplikasi yang boleh dirujuk pentadbir teknikal melalui antara muka, dengan carian dan tapisan mengikut tempoh, modul, aras keterukan, kod ralat, ID pengguna dan ID korelasi permintaan. | Wajib | A-3.19 |
| SRS-ADM-12 | Sistem hendaklah menetapkan ID korelasi unik bagi setiap permintaan supaya ralat yang dilaporkan pengguna dapat dipadankan dengan rekod log ralat yang berkenaan. | Perlu | A-3.19 |
| SRS-ADM-13 | Sistem hendaklah memastikan mesej ralat yang dipaparkan kepada pengguna akhir tidak mendedahkan struktur pangkalan data, laluan fail, konfigurasi sistem, kod dalaman atau jejak tindanan; maklumat terperinci hanya tersedia dalam log ralat kepada pentadbir teknikal. | Wajib | RFP 11.3.2(d); A-3.19 |
| SRS-ADM-14 | Sistem hendaklah menyediakan pengurusan log yang meliputi paparan, carian dan eksport log aplikasi serta log integrasi mengikut tempoh, dengan polisi pengekalan boleh dikonfigurasi. | Wajib | URS-ADM-06; A-3.19 |
| SRS-ADM-15 | Sistem hendaklah memastikan log aplikasi dan log integrasi tidak merekodkan kata laluan, token, kunci API, kunci penyulitan atau data peribadi penuh dalam bentuk teks biasa. | Wajib | RFP 11.3.2(f); A-16.16 |
| SRS-ADM-16 | Sistem hendaklah menyediakan skrin pemantauan kesihatan sistem tunggal yang memaparkan status perkhidmatan aplikasi, status baris gilir, status setiap integrasi, penggunaan ruang storan, penggunaan pangkalan data dan senarai ralat terkini. | Wajib | URS-ADM-04; RFP 6.2 |
| SRS-ADM-17 | Sistem hendaklah menjana amaran automatik kepada Bahagian ICT MAIS apabila perkhidmatan tidak tersedia, baris gilir melampaui ambang, integrasi gagal berulang atau ruang storan melampaui ambang yang dikonfigurasi. | Wajib | URS-ADM-04; A-3.14 |
| SRS-ADM-18 | Sistem hendaklah mewajibkan kelulusan oleh pentadbir kedua bagi perubahan konfigurasi kritikal termasuk tetapan keselamatan, polisi sesi, definisi peranan, tetapan integrasi dan polisi retensi. | Wajib | URS-ADM-05; A-16.09 |
| SRS-ADM-19 | Sistem hendaklah merekodkan setiap perubahan konfigurasi dalam audit trail berserta nilai sebelum, nilai selepas, ID pengguna, alamat IP, tarikh, masa dan justifikasi. | Wajib | URS-ADM-05; A-16.09; A-16.10 |
| SRS-ADM-20 | Sistem hendaklah menyediakan perkhidmatan carian platform berpusat yang boleh dikonfigurasi pentadbir, meliputi penetapan medan boleh dicari bagi setiap entiti, sinonim istilah, pemberat hasil dan pengurusan semula indeks. | Wajib | A-3.12 |
| SRS-ADM-21 | Sistem hendaklah menyokong carian global merentas modul dan carian terperinci berdasarkan lot, mukim, daerah, status tanah, jenis hak milik, nombor hak milik, pemilik, pajakan, cukai, lokasi dan kata kunci, dengan hasil dihadkan oleh kebenaran capaian pengguna. | Wajib | A-3.12; A-16.03 |
| SRS-ADM-22 | Sistem hendaklah menyediakan perkhidmatan penyelesaian pautan dalam yang membolehkan drill-down daripada papan pemuka dan hasil carian kepada rekod sumber dengan penguatkuasaan kebenaran capaian. | Wajib | A-3.13 |
| SRS-ADM-23 | Pembangunan sistem hendaklah mematuhi standard secure coding yang didokumenkan dan amalan DevSecOps yang merangkumi SAST, Software Composition Analysis, pengesanan rahsia dalam kod dan DAST dalam talian paip integrasi berterusan. | Wajib | A-3.20; RFP 11.3.1 |
| SRS-ADM-24 | Talian paip pembangunan hendaklah menguatkuasakan gate kualiti di mana pembinaan yang mengandungi kelemahan keselamatan beraras Kritikal atau Tinggi tidak boleh dilepaskan ke UAT atau PROD tanpa pengecualian bertulis yang diluluskan MAIS. | Wajib | A-3.20; A-16.15 |
| SRS-ADM-25 | Syarikat hendaklah melaksanakan Secure Code Review, Vulnerability Assessment dan Penetration Test ke atas sistem sebelum Go-Live, dan menyerahkan laporan ujian keselamatan berserta bukti pembetulan semua penemuan Kritikal dan Tinggi sebelum penerimaan akhir. | Wajib | A-16.14; A-16.15 |
| SRS-ADM-26 | Sistem hendaklah menyokong integrasi dengan Web Application Firewall dan IDS/IPS MAIS, termasuk penyediaan alamat IP pelanggan sebenar (X-Forwarded-For) kepada aplikasi dan keserasian dengan peraturan WAF tanpa menjejaskan fungsi muat naik fail dan API. | Wajib | A-16.13; RFP 11.3.2(e) |
| SRS-ADM-27 | Sistem hendaklah menyediakan keupayaan penghantaran log keselamatan dan log audit ke platform SIEM MAIS dalam format standard yang dipersetujui. | Wajib | A-16.13 |
| SRS-ADM-28 | Sistem hendaklah menyimpan semua rahsia sistem — kata laluan pangkalan data, token, kunci API, sijil digital dan kunci penyulitan — dalam penyimpanan rahsia bersulit, dan tidak dalam kod sumber, fail konfigurasi teks biasa atau repositori kod. | Wajib | RFP 11.3.2(f); A-16.07 |
| SRS-ADM-29 | Sistem hendaklah menyediakan mod penyelenggaraan yang memaparkan notis kepada pengguna, menghalang transaksi baharu dan membenarkan capaian pentadbir semasa tempoh penyelenggaraan berjadual. | Baik-ada | Jurang |

**Aliran proses utama.**

1. Pentadbir log masuk ke konsol pentadbiran melalui SSO MAIS dengan pengesahan MFA.
2. Pentadbir memilih kawasan konfigurasi dan membuat perubahan.
3. Bagi konfigurasi kritikal, sistem menyimpan perubahan dalam status menunggu kelulusan dan menjana tugasan kelulusan kepada pentadbir kedua melalui Workflow Engine.
4. Pentadbir kedua menyemak nilai sebelum dan selepas, kemudian meluluskan atau menolak; perubahan yang diluluskan berkuat kuasa dan direkodkan dalam audit trail.
5. Job scheduler melaksanakan tugasan berkala mengikut jadual, merekodkan sejarah pelaksanaan, dan menjana notifikasi kegagalan.
6. Skrin pemantauan kesihatan mengumpul metrik perkhidmatan, baris gilir, integrasi dan storan secara berterusan serta mencetuskan amaran apabila ambang dilampaui.
7. Pentadbir teknikal merujuk log ralat melalui carian ID korelasi atau tapisan modul dan tempoh.
8. Log keselamatan dan audit dihantar ke platform SIEM MAIS secara berterusan.

**Entiti data.** `ADM_PARAMETER`; `ADM_KOD_RUJUKAN` (id\_kod, jenis\_kod, kod, keterangan, kod\_induk, susunan, status, tarikh\_mula, tarikh\_tamat); `ADM_TEMPLAT`; `ADM_INTEGRASI` (id\_integrasi, nama, jenis, endpoint, id\_rahsia, tempoh\_tamat\_masa, kadar\_capaian, jadual, status); `ADM_KONFIG_KELULUSAN` (id\_permohonan, jenis\_konfig, kunci, nilai\_sebelum, nilai\_selepas, justifikasi, id\_pemohon, id\_pelulus, status); `ADM_JOB`; `ADM_JOB_LARIAN` (id\_larian, id\_job, tarikh\_mula, tarikh\_tamat, tempoh\_saat, status, bilangan\_rekod, mesej\_ralat); `ADM_LOG_RALAT` (id\_log, id\_korelasi, tarikh\_masa, aras\_keterukan, modul, kod\_ralat, mesej, jejak\_tindanan, id\_pengguna, alamat\_ip); `ADM_LOG_INTEGRASI`; `ADM_KESIHATAN`; `ADM_INDEKS_CARIAN`.

**Integrasi.** Semua modul bagi parameter, kod rujukan dan templat; MOD-WFE bagi kelulusan konfigurasi kritikal; MOD-NTF bagi amaran kesihatan dan kegagalan tugasan; MOD-AUD bagi perekodan perubahan konfigurasi; MOD-DMS bagi pencetusan job pengarkiban; enjin carian; platform pemantauan infrastruktur; WAF dan IDS/IPS MAIS; platform SIEM MAIS; penyimpanan rahsia; talian paip CI/CD.

**Kawalan keselamatan.**

- Capaian konsol pentadbiran dihadkan kepada peranan pentadbir dengan MFA wajib
- Pengasingan skop: Pentadbir Aplikasi tidak boleh mengubah tetapan keselamatan, polisi sesi atau pentadbiran kunci penyulitan
- Kelulusan pentadbir kedua bagi konfigurasi kritikal, dengan nilai sebelum dan selepas direkod
- Rahsia sistem disimpan dalam secret store bersulit dan tidak dipaparkan semula selepas disimpan
- Log ralat dan log integrasi tidak merekodkan kelayakan atau data peribadi penuh dalam teks biasa
- Mesej ralat pengguna akhir tidak mendedahkan maklumat struktur sistem
- Gate kualiti keselamatan dalam talian paip pelepasan
- Capaian log aplikasi oleh pentadbir turut direkodkan dalam audit trail

**Laporan yang boleh dijana.** Perubahan konfigurasi mengikut tempoh, pentadbir, jenis dan status kelulusan; senarai dan sejarah kod rujukan; pelaksanaan tugasan berjadual termasuk kadar kejayaan dan senarai kegagalan; ralat aplikasi mengikut modul, aras keterukan dan tempoh; kesihatan sistem dan ketersediaan perkhidmatan; status dan prestasi integrasi; penggunaan storan dan pertumbuhan pangkalan data; hasil ujian keselamatan dan status pembetulan penemuan.

**Kriteria penerimaan.**

1. Pentadbir Aplikasi menambah satu kod rujukan baharu dan mengubah satu parameter sistem melalui antara muka, dan perubahan berkuat kuasa tanpa pelepasan kod baharu atau mulaan semula perkhidmatan.
2. Perubahan tetapan keselamatan oleh seorang pentadbir kekal tidak berkuat kuasa sehingga diluluskan pentadbir kedua, dan audit trail memaparkan nilai sebelum serta selepas.
3. Pentadbir mendefinisikan satu tugasan berjadual baharu dengan ungkapan cron, tugasan dilaksanakan pada masa dijadualkan, sejarah pelaksanaan memaparkan status dan tempoh, dan kegagalan yang disengajakan mencetuskan notifikasi kepada Bahagian ICT.
4. Ralat yang dipaparkan kepada pengguna akhir tidak memuatkan jejak tindanan, nama jadual pangkalan data atau laluan fail, manakala rekod log ralat yang sepadan boleh dicari pentadbir teknikal menggunakan ID korelasi yang dipaparkan kepada pengguna.
5. Skrin pemantauan kesihatan memaparkan status semua perkhidmatan dan integrasi, dan amaran dijana apabila satu integrasi ujian dimatikan.
6. Carian global menggunakan nombor lot mengembalikan rekod daripada pelbagai modul, rekod di luar skop kebenaran tidak dipaparkan, dan pautan hasil carian membuka rekod sumber yang betul.
7. Laporan talian paip CI/CD membuktikan pelaksanaan SAST, SCA, secret scanning dan DAST bagi setiap pelepasan, dan percubaan melepaskan pembinaan dengan kelemahan Kritikal dihalang oleh gate kualiti.
8. Laporan Secure Code Review, Vulnerability Assessment dan Penetration Test diserahkan sebelum Go-Live berserta bukti pembetulan semua penemuan Kritikal dan Tinggi.

### Modul 18 · Pengurusan Pengguna, Peranan dan Capaian

**Kod:** MOD-RBC · **Gelombang:** G0 (Bulan 3.0–3.5) · **Rujukan:** RFP Seksyen 6.2 (Modul 18), 11.1.2, 11.2.1–11.2.4, 11.4.2; Lampiran H A-16.01–A-16.08, A-16.16

Modul ini menguatkuasakan kawalan capaian berasaskan peranan merentas seluruh SISDATA, mengurus kitar hayat akaun pengguna dan status akaun, serta mengintegrasikan pengesahan identiti dengan SSO MAIS. Ia merupakan satu-satunya titik penguatkuasaan kebenaran bagi semua antara muka pengguna, API, eksport data dan lapisan GIS.

**Kategori pengguna.** Tujuh kategori peranan asas Seksyen 11.2.3: Pentadbir Sistem, Pentadbir Aplikasi, Pegawai Operasi, Pelulus, Pengguna Paparan, Juruaudit dan Pemegang Taruh Terhad. Modul ini juga digunakan oleh pemilik data bahagian bagi pengesahan semula capaian berkala.

**Keperluan fungsian**

| Kod | Keperluan | Keutamaan | Sumber |
| --- | --- | --- | --- |
| SRS-RBC-01 | Sistem hendaklah mengintegrasikan pengesahan pengguna dengan SSO MAIS menggunakan protokol pengesahan standard industri yang diluluskan MAIS, dengan OpenID Connect sebagai protokol dicadangkan dan SAML 2.0 sebagai alternatif sekiranya ditetapkan MAIS. | Wajib | A-16.01; RFP 11.2.2(a); URS-RBC-04 |
| SRS-RBC-02 | Sistem hendaklah memetakan tuntutan identiti daripada SSO MAIS — pengenalan pengguna, bahagian, unit dan jawatan — kepada peranan dan skop capaian SISDATA berdasarkan peraturan pemetaan yang boleh dikonfigurasi pentadbir. | Wajib | A-16.01; A-16.03 |
| SRS-RBC-03 | Sistem hendaklah menyokong MFA bagi pengguna berisiko tinggi, pentadbir sistem dan modul kritikal termasuk kelulusan, pelupusan, perubahan hak milik, pengurusan pengguna, pentadbiran sistem dan konfigurasi keselamatan. | Wajib | A-16.02; RFP 11.2.2(b); URS-RBC-04 |
| SRS-RBC-04 | Sistem hendaklah melaksanakan RBAC yang menguatkuasakan kebenaran mengikut peranan, unit, bahagian, modul, transaksi, lokasi (daerah dan mukim) dan tahap kelulusan. | Wajib | A-16.03; RFP 11.2.2(c); URS-RBC-01 |
| SRS-RBC-05 | Sistem hendaklah menyediakan sebagai konfigurasi awal tujuh kategori peranan asas yang ditetapkan RFP, dengan skop capaian setiap kategori selaras Jadual Seksyen 11.2.3 dan disahkan dalam sesi pengesahan keperluan. | Wajib | RFP 11.2.3; URS-RBC-03 |
| SRS-RBC-06 | Sistem hendaklah membolehkan peranan ditambah, dipinda, dinyahaktifkan dan diklonkan melalui antara muka pentadbiran tanpa perubahan kod, dengan pemilihan modul, tindakan (lihat, cipta, kemas kini, lulus, eksport, padam), skop daerah dan kategori data. | Wajib | RFP 11.2.4; URS-RBC-02 |
| SRS-RBC-07 | Sistem hendaklah menyokong kawalan capaian peringkat rekod dan peringkat data termasuk sekatan mengikut daerah, bahagian, unit, kategori tanah, klasifikasi keselamatan dan parameter lain yang ditetapkan MAIS. | Wajib | RFP 11.2.2(d); A-16.03; URS-RBC-01 |
| SRS-RBC-08 | Sistem hendaklah menilai kebenaran capaian pada lapisan perkhidmatan bagi setiap permintaan, dan kebenaran tidak boleh dipintas melalui API langsung, fungsi eksport, papan pemuka, alat BI atau lapisan perkhidmatan GIS. | Wajib | URS-RBC-05; A-16.03 |
| SRS-RBC-09 | Sistem hendaklah menetapkan prinsip capaian minimum di mana peranan baharu bermula tanpa sebarang kebenaran dan setiap kebenaran diberikan secara eksplisit. | Perlu | RFP 11.4.2(c) |
| SRS-RBC-10 | Sistem hendaklah membolehkan matriks capaian (peranan × modul × tindakan × skop) dipaparkan dan dieksport dalam format Excel dan PDF pada bila-bila masa untuk semakan audit. | Wajib | URS-RBC-03; A-16.12 |
| SRS-RBC-11 | Sistem hendaklah mewarisi polisi kata laluan daripada SSO MAIS bagi semua pengguna yang disahkan melalui SSO, dan tidak menyimpan kata laluan pengguna tersebut dalam mana-mana storan SISDATA. | Wajib | RFP 11.2.2(e); A-16.01 |
| SRS-RBC-12 | Bagi akaun pengesahan dalaman yang dibenarkan MAIS, sistem hendaklah menguatkuasakan polisi kata laluan sendiri dengan panjang minimum 12 aksara, kompleksiti sekurang-kurangnya tiga daripada empat kelas aksara, tempoh luput 90 hari, larangan penggunaan semula lima kata laluan terakhir dan penukaran wajib pada log masuk pertama. | Wajib | RFP 11.2.2(e); Jurang |
| SRS-RBC-13 | Bagi akaun pengesahan dalaman, sistem hendaklah mengunci akaun selepas lima percubaan gagal berturutan dalam tempoh 15 minit, dengan tempoh kunci dan ambang percubaan boleh dikonfigurasi Pentadbir Sistem. | Wajib | RFP 11.2.2(e); Jurang |
| SRS-RBC-14 | Sistem hendaklah menyimpan kata laluan akaun pengesahan dalaman menggunakan fungsi cincangan sehala yang kuat berserta salt, dan tidak dalam bentuk teks biasa atau penyulitan boleh balik. | Wajib | A-16.07; RFP 11.3.2(f) |
| SRS-RBC-15 | Sistem hendaklah menyokong empat status akaun — Aktif, Gantung, Nyahaktif dan Tamat Tempoh — di mana hanya akaun Aktif boleh log masuk dan setiap perubahan status direkodkan dalam audit trail. | Wajib | RFP 6.2; Jurang |
| SRS-RBC-16 | Sistem hendaklah menukar status akaun secara automatik kepada Tamat Tempoh pada tarikh tamat capaian yang ditetapkan, dan kepada Gantung selepas tempoh tidak aktif yang dikonfigurasi dengan nilai lalai 90 hari tanpa log masuk. | Perlu | Jurang; A-16.04 |
| SRS-RBC-17 | Sistem hendaklah membolehkan Pentadbir Sistem menggantung, menamatkan atau menyahaktifkan capaian pengguna serta-merta, dengan kesan termasuk penamatan semua sesi aktif dan pembatalan token capaian. | Wajib | A-16.04; URS-RBC-06 |
| SRS-RBC-18 | Sistem hendaklah menamatkan sesi secara automatik selepas tempoh tidak aktif yang ditetapkan MAIS dengan nilai lalai 15 minit dan boleh dikonfigurasi, serta memaparkan amaran kepada pengguna sebelum sesi ditamatkan. | Wajib | A-16.06; RFP 11.2.2(f); URS-RBC-06 |
| SRS-RBC-19 | Sistem hendaklah menguatkuasakan had sesi serentak per pengguna yang boleh dikonfigurasi, membolehkan pentadbir menamatkan sesi secara paksa, dan menyediakan log keluar yang menamatkan sesi pada pelayan dan bukan hanya pada pelayar. | Wajib | RFP 11.2.2(f); URS-RBC-06 |
| SRS-RBC-20 | Sistem hendaklah melindungi sesi daripada rampasan dengan memperbaharui pengenal sesi selepas pengesahan berjaya, menggunakan cookie bertanda HttpOnly, Secure dan SameSite, serta mengikat sesi kepada sidik pelayar dan alamat IP dalam julat yang dibenarkan. | Wajib | RFP 11.2.2(f); A-16.08 |
| SRS-RBC-21 | Sistem hendaklah merekod semua percubaan log masuk berjaya dan gagal termasuk ID pengguna, alamat IP, maklumat peranti dan pelayar, lokasi anggaran berasaskan alamat IP, tarikh dan masa, serta sebab kegagalan. | Wajib | A-16.05; RFP 11.2.2(g) |
| SRS-RBC-22 | Sistem hendaklah menjana amaran kepada Pentadbir Sistem bagi aktiviti log masuk mencurigakan termasuk percubaan gagal berulang, log masuk daripada lokasi atau peranti luar biasa, dan percubaan capaian fungsi tanpa kebenaran. | Wajib | RFP 11.2.2(h); A-16.05 |
| SRS-RBC-23 | Sistem hendaklah menyulitkan data sensitif semasa penghantaran menggunakan TLS 1.3 dan semasa penyimpanan menggunakan AES-256 atau algoritma setara, meliputi pangkalan data, dokumen digital, sandaran dan storan arkib. | Wajib | A-16.07; RFP 11.4.2 |
| SRS-RBC-24 | Sistem hendaklah mematuhi OWASP ASVS dan OWASP Top 10 atau standard setara, termasuk perlindungan terhadap SQL Injection, Cross-Site Scripting, Cross-Site Request Forgery, Insecure Direct Object Reference dan kelemahan kawalan akses. | Wajib | A-16.08; RFP 11.3.2(a) |
| SRS-RBC-25 | Sistem hendaklah direka bentuk, dibangunkan dan dioperasikan selaras Dasar Keselamatan Siber MAIS dan Polisi ICT MAIS, Akta Perlindungan Data Peribadi 2010, Akta Jenayah Komputer 1997, prinsip ISO/IEC 27001 serta amalan keselamatan aplikasi OWASP. | Wajib | A-16.16; RFP 11.1.2 |
| SRS-RBC-26 | Sistem hendaklah menyediakan laporan capaian meliputi senarai pengguna aktif dan tidak aktif, kebenaran per peranan, percubaan log masuk gagal dan senarai pengguna mengikut status akaun. | Wajib | URS-RBC-07; A-16.05 |
| SRS-RBC-27 | Sistem hendaklah menyediakan proses pengesahan semula capaian berkala di mana pemilik data bahagian mengesahkan senarai pengguna dan kebenaran dalam skopnya mengikut kitaran yang dikonfigurasi, dengan keputusan direkodkan. | Perlu | URS-RBC-07; A-16.16 |

**Aliran proses utama.**

1. Pengguna mengakses SISDATA dan dialihkan ke SSO MAIS untuk pengesahan identiti; MFA dikuatkuasakan bagi pengguna berisiko tinggi dan modul kritikal.
2. Sistem menerima tuntutan identiti, memadankan pengguna dengan akaun SISDATA dan menyemak status akaun; akaun Gantung, Nyahaktif atau Tamat Tempoh ditolak.
3. Sistem merekodkan percubaan log masuk berserta ID pengguna, alamat IP, peranti, lokasi anggaran, tarikh dan masa.
4. Sistem memuatkan peranan, skop daerah, skop bahagian dan kategori data pengguna, serta membina konteks kebenaran bagi sesi tersebut.
5. Setiap permintaan dinilai pada lapisan perkhidmatan terhadap konteks kebenaran; permintaan tanpa kebenaran ditolak dan direkodkan.
6. Sesi ditamatkan automatik selepas tempoh tidak aktif, secara paksa oleh pentadbir, atau apabila status akaun berubah.
7. Pentadbir Sistem mengurus kitar hayat akaun dan setiap tindakan direkodkan dalam audit trail.
8. Pemilik data bahagian melaksanakan pengesahan semula capaian berkala; kebenaran yang tidak disahkan ditarik mengikut dasar yang ditetapkan.

**Entiti data.** `PENGGUNA` (id\_pengguna, id\_sso, no\_pekerja, nama, e\_mel, id\_bahagian, id\_unit, jawatan, status\_akaun, tarikh\_mula\_capaian, tarikh\_tamat\_capaian, tarikh\_log\_masuk\_akhir, mfa\_didaftar, jenis\_pengesahan); `PENGGUNA_KREDENSIAL`; `PENGGUNA_KATA_LALUAN_SEJARAH`; `PERANAN` (id\_peranan, kod\_peranan, nama, kategori\_rfp, keterangan, status, boleh\_padam); `PERANAN_KEBENARAN` (id\_peranan, modul, fungsi, tindakan, dibenarkan); `PENGGUNA_PERANAN`; `PENGGUNA_SKOP` (id\_pengguna, jenis\_skop, nilai\_skop); `STATUS_AKAUN_LOG`; `SESI` (id\_sesi, id\_pengguna, token\_rujukan, alamat\_ip, user\_agent, sidik\_peranti, tarikh\_mula, tarikh\_aktiviti\_akhir, tarikh\_tamat, kaedah\_tamat); `LOG_LOG_MASUK`; `POLISI_KESELAMATAN`; `RECERT_KITARAN`; `RECERT_KEPUTUSAN`.

**Integrasi.** SSO MAIS bagi pengesahan identiti; penyedia MFA MAIS; direktori pengguna MAIS bagi penyelarasan maklumat pegawai; MOD-WFE bagi peranan pelulus dan MFA peringkat kelulusan; MOD-AUD bagi perekodan log masuk dan perubahan peranan; MOD-NTF bagi amaran log masuk mencurigakan; MOD-ADM bagi konfigurasi polisi sesi dan pemetaan peranan; lapisan GIS, API Gateway dan alat BI bagi penguatkuasaan kebenaran yang sama; portal awam bagi pengesahan Pemegang Taruh Terhad.

**Kawalan keselamatan.**

- Pengesahan melalui SSO MAIS; kata laluan pengguna SSO tidak disimpan dalam SISDATA
- MFA wajib bagi pentadbir, pengguna berisiko tinggi dan modul kritikal
- Polisi kata laluan dan kawalan percubaan log masuk dikuatkuasakan bagi akaun pengesahan dalaman sahaja
- Akaun pemulihan kecemasan terhad bilangan, dilindungi MFA, dipantau, dan setiap penggunaannya menjana amaran serta-merta kepada Bahagian ICT
- Prinsip capaian minimum; peranan baharu tanpa kebenaran lalai
- Penguatkuasaan kebenaran pada lapisan perkhidmatan bagi semua saluran capaian
- Auto session timeout, had sesi serentak, pembaharuan pengenal sesi dan pengikatan sesi kepada peranti
- Penyulitan in-transit TLS 1.3 dan at-rest AES-256
- Log penuh percubaan log masuk termasuk alamat IP, peranti dan lokasi anggaran

**Laporan yang boleh dijana.** Senarai pengguna mengikut status akaun, bahagian, peranan dan daerah; matriks capaian dalam Excel dan PDF; pengguna tidak aktif melebihi tempoh dan akaun tamat tempoh; percubaan log masuk gagal mengikut pengguna, alamat IP dan tempoh; aktiviti log masuk mencurigakan; perubahan peranan dan kebenaran mengikut tempoh dan pentadbir; perubahan status akaun berserta sebab; sesi aktif semasa dan sejarah penamatan paksa; pengesahan semula capaian mengikut bahagian dan kitaran; pengguna dengan kebenaran berisiko tinggi.

**Kriteria penerimaan.**

1. Pengguna log masuk melalui SSO MAIS tanpa memasukkan kata laluan berasingan ke dalam SISDATA, dan capaian ke modul kritikal memerlukan pengesahan MFA yang berjaya.
2. Akaun yang ditukar kepada status Gantung, Nyahaktif atau Tamat Tempoh tidak dapat log masuk, dan semua sesi aktif ditamatkan dalam tempoh tidak melebihi satu minit selepas perubahan status.
3. Akaun pengesahan dalaman menolak kata laluan kurang daripada 12 aksara atau tanpa kompleksiti yang ditetapkan, dan dikunci selepas lima percubaan gagal berturutan.
4. Sesi ditamatkan automatik selepas tempoh tidak aktif yang dikonfigurasi, dan tempoh boleh diubah oleh Pentadbir Sistem tanpa pelepasan kod.
5. Log masuk berjaya dan gagal direkodkan dengan ID pengguna, alamat IP, maklumat peranti dan pelayar, lokasi anggaran, tarikh dan masa, dan rekod boleh dicari serta dieksport.
6. Pengguna berperanan Pengguna Paparan tidak dapat mengubah data melalui antara muka mahupun melalui panggilan API langsung, fungsi eksport atau lapisan perkhidmatan GIS.
7. Pentadbir Aplikasi mencipta satu peranan baharu dengan skop daerah terhad, peranan berkuat kuasa serta-merta, dan pengguna yang diberikan peranan tersebut hanya melihat rekod daerah dalam skopnya.
8. Matriks capaian semasa dieksport dalam Excel dan PDF, dan kandungannya selaras dengan konfigurasi kebenaran dalam sistem.
9. Kesemua tujuh kategori peranan asas RFP tersedia sebagai konfigurasi awal dengan skop capaian selaras Jadual Seksyen 11.2.3.

### Modul 19 · Audit Trail

**Kod:** MOD-AUD · **Gelombang:** G0 (Bulan 3.0–3.5) · **Rujukan:** RFP Seksyen 6.2 (Modul 19), 11.4.2; Lampiran H A-16.05, A-16.09–A-16.13, A-3.09, A-3.17, B-3.08

Modul ini merekodkan jejak audit lengkap dan tamper-evident bagi semua aktiviti penting dalam SISDATA — perubahan data medan demi medan, keputusan kelulusan, capaian dokumen, penghantaran notifikasi dan perubahan konfigurasi. Ia menyediakan antara muka carian dan eksport bagi Juruaudit serta penstriman log ke platform SIEM MAIS.

**Kategori pengguna.** Juruaudit dalaman (carian, semakan, eksport pakej audit); juruaudit luar yang diluluskan MAIS (baca sahaja, skop terhad dan bertempoh); Pentadbir Sistem (konfigurasi retensi, pemantauan integriti, penstriman SIEM); Pengurusan Tertinggi (laporan pematuhan dan audit ringkasan).

**Keperluan fungsian**

| Kod | Keperluan | Keutamaan | Sumber |
| --- | --- | --- | --- |
| SRS-AUD-01 | Sistem hendaklah menyediakan audit trail lengkap bagi semua transaksi tambah, pinda, padam, lulus, batal, muat naik, muat turun, import, eksport dan perubahan konfigurasi dalam semua modul. | Wajib | A-16.09; URS-AUD-01 |
| SRS-AUD-02 | Sistem hendaklah turut merekodkan aktiviti cipta, kemas kini, hapus, lihat rekod sensitif, muat turun, cetak, log masuk, log keluar, kelulusan, penolakan, perubahan status, penjanaan kunci API dan setiap arahan AI berserta hasilnya. | Wajib | RFP 6.2; URS-AUD-01 |
| SRS-AUD-03 | Setiap rekod audit hendaklah merekodkan nilai asal, nilai baharu, ID pengguna, alamat IP, tarikh dan masa, modul, status transaksi dan sebab perubahan sekiranya berkaitan. | Wajib | A-16.10; URS-AUD-02 |
| SRS-AUD-04 | Setiap rekod audit hendaklah turut merekodkan peranan semasa pengguna pada masa tindakan, ID sesi, maklumat peranti, nama entiti dan ID rekod, jenis tindakan serta saluran capaian (web, mudah alih atau API). | Wajib | URS-AUD-02; A-16.05 |
| SRS-AUD-05 | Sistem hendaklah merekodkan perubahan data secara medan demi medan di mana setiap medan yang berubah direkodkan sebagai satu entri berasingan dengan nilai sebelum dan selepas. | Wajib | A-16.10; URS-AUD-02 |
| SRS-AUD-06 | Audit trail hendaklah tamper-evident melalui storan hanya-tambah pada peringkat pangkalan data dan rantaian nilai cincangan antara rekod berturutan. | Wajib | A-16.11; URS-AUD-03 |
| SRS-AUD-07 | Sistem hendaklah melaksanakan semakan integriti rantaian audit secara berkala melalui job scheduler dan menjana amaran serta-merta kepada Pentadbir Sistem dan Juruaudit sekiranya percanggahan dikesan. | Wajib | A-16.11; URS-AUD-03 |
| SRS-AUD-08 | Audit trail tidak boleh dipinda atau dipadam oleh pengguna biasa, dan sistem hendaklah turut menghalang pindaan serta pemadaman oleh peranan pentadbir melalui antara muka aplikasi dan akaun aplikasi pangkalan data. | Wajib | A-16.11; URS-AUD-03 |
| SRS-AUD-09 | Perekodan audit hendaklah dilaksanakan pada lapisan infrastruktur aplikasi di luar kawalan kod modul individu supaya tiada modul boleh melangkau perekodan audit. | Wajib | URS-AUD-04 |
| SRS-AUD-10 | Sistem hendaklah menyediakan carian audit trail berdasarkan ID pengguna, peranan, modul, entiti, ID rekod, jenis tindakan, alamat IP, status transaksi dan tempoh tarikh, secara tunggal atau gabungan. | Wajib | A-16.12; URS-AUD-05 |
| SRS-AUD-11 | Sistem hendaklah menyediakan paparan kronologi per rekod yang memaparkan keseluruhan sejarah aktiviti ke atas satu rekod hartanah, kes, perjanjian atau dokumen dalam susunan masa. | Wajib | URS-AUD-05; A-3.09 |
| SRS-AUD-12 | Sistem hendaklah menyediakan eksport audit trail dalam format CSV, Excel, JSON dan PDF berdasarkan kriteria carian yang digunakan. | Wajib | A-16.12 |
| SRS-AUD-13 | Setiap eksport audit trail hendaklah disertakan nilai cincangan pakej eksport dan metadata eksport — kriteria carian, bilangan rekod, pengeksport, tarikh dan masa — bagi pengesahan integriti. | Perlu | URS-AUD-05; A-16.11 |
| SRS-AUD-14 | Sistem hendaklah merekodkan setiap capaian, carian dan eksport ke atas audit trail itu sendiri sebagai rekod audit. | Wajib | URS-AUD-06; A-16.09 |
| SRS-AUD-15 | Sistem hendaklah menjana laporan audit automatik bagi capaian data berklasifikasi Sulit dan Rahsia, aktiviti di luar waktu pejabat, aktiviti berkelompok luar biasa, eksport data bervolum besar dan capaian kepada rekod audit. | Wajib | URS-AUD-06; A-16.09 |
| SRS-AUD-16 | Sistem hendaklah merekodkan audit trail bagi muat naik, muat turun, pratonton, cetak, pindaan, pemadaman dan pengarkiban dokumen digital. | Wajib | B-3.08; A-16.09 |
| SRS-AUD-17 | Sistem hendaklah merekodkan audit trail bagi setiap notifikasi yang dihantar termasuk tarikh dan masa penghantaran, saluran, status penghantaran dan pengesahan penerimaan. | Wajib | A-3.17 |
| SRS-AUD-18 | Sistem hendaklah merekodkan audit trail bagi setiap tindakan aliran kerja termasuk identiti pelulus, keputusan, justifikasi, peringkat, tarikh dan masa, serta setiap perubahan definisi aliran kerja. | Wajib | A-3.09; URS-WFE-04 |
| SRS-AUD-19 | Sistem hendaklah merekodkan semua percubaan log masuk berjaya dan gagal dalam audit trail termasuk ID pengguna, alamat IP, peranti, lokasi anggaran, tarikh dan masa. | Wajib | A-16.05; RFP 11.2.2(g) |
| SRS-AUD-20 | Sistem hendaklah menyediakan polisi pengekalan audit trail yang boleh dikonfigurasi selaras keperluan undang-undang dan audit MAIS, dengan tempoh dalam talian tidak kurang daripada 24 bulan dan pengarkiban berperingkat selepas tempoh tersebut. | Wajib | URS-AUD-07; B-3.07 |
| SRS-AUD-21 | Sistem hendaklah mengekalkan keupayaan carian dan pengambilan semula ke atas audit trail yang telah diarkibkan, dan merekodkan setiap capaian kepada arkib audit. | Wajib | URS-AUD-07 |
| SRS-AUD-22 | Sistem hendaklah menyediakan keupayaan penghantaran atau penstriman rekod audit ke platform SIEM MAIS dalam format standard yang dipersetujui. | Wajib | A-16.13 |
| SRS-AUD-23 | Sistem hendaklah menghadkan capaian audit trail kepada peranan Juruaudit, Pentadbir Sistem dan peranan lain yang diluluskan MAIS secara baca sahaja, tanpa sebarang kebenaran tulis ke atas rekod audit. | Wajib | RFP 11.2.3; A-16.11 |
| SRS-AUD-24 | Sistem hendaklah menggunakan sumber masa yang diselaraskan melalui NTP dan merekodkan cap masa audit dalam zon waktu Malaysia (UTC+8) berserta nilai UTC. | Perlu | Jurang; A-16.10 |
| SRS-AUD-25 | Sistem hendaklah memastikan rekod audit tidak menyimpan kata laluan, token, kunci API, kunci penyulitan atau nombor dokumen pengenalan peribadi dalam bentuk teks biasa; medan sedemikian ditanda sensitif dan nilainya ditapis. | Wajib | Jurang; A-16.16 |
| SRS-AUD-26 | Sistem hendaklah memastikan perekodan audit tidak menyebabkan kegagalan transaksi operasi, dan kegagalan perekodan audit dicatat sebagai insiden serta dimaklumkan kepada Pentadbir Sistem. | Perlu | Jurang; A-3.19 |

**Aliran proses utama.**

1. Modul operasi melaksanakan transaksi; lapisan infrastruktur aplikasi memintas peristiwa transaksi tanpa campur tangan kod modul.
2. Lapisan audit membina rekod dengan mengumpul konteks pengguna (ID, peranan semasa, ID sesi, alamat IP, peranti, saluran capaian) dan konteks transaksi (modul, entiti, ID rekod, jenis tindakan, status).
3. Bagi perubahan data, lapisan audit membandingkan keadaan sebelum dan selepas serta menjana satu entri bagi setiap medan yang berubah; medan sensitif ditapis.
4. Rekod audit ditulis ke storan hanya-tambah dengan nilai cincangan yang merantai rekod kepada rekod sebelumnya.
5. Rekod audit distrim ke platform SIEM MAIS dalam format yang dipersetujui.
6. Job scheduler melaksanakan semakan integriti rantaian cincangan secara berkala dan menjana amaran sekiranya percanggahan dikesan.
7. Juruaudit mencari audit trail, menyemak kronologi per rekod, dan mengeksport pakej audit berserta checksum.
8. Selepas tempoh retensi dalam talian, rekod diarkibkan berperingkat; capaian kepada arkib kekal tersedia dan direkodkan.

**Entiti data.** `AUDIT_REKOD` (id\_audit, no\_urutan, cincangan\_rekod, cincangan\_sebelum, tarikh\_masa\_utc, tarikh\_masa\_tempatan, id\_pengguna, id\_peranan\_semasa, id\_sesi, alamat\_ip, user\_agent, jenis\_peranti, lokasi\_anggaran, saluran\_capaian, modul, nama\_entiti, id\_rekod, jenis\_tindakan, status\_transaksi, sebab\_perubahan, id\_korelasi); `AUDIT_PERUBAHAN_MEDAN` (id\_audit, nama\_medan, nilai\_asal, nilai\_baharu, sensitif\_ditapis); `AUDIT_LOG_MASUK`; `AUDIT_DOKUMEN`; `AUDIT_NOTIFIKASI`; `AUDIT_WORKFLOW`; `AUDIT_KONFIGURASI`; `AUDIT_PROMPT_AI`; `AUDIT_EKSPORT` (id\_eksport, id\_pengguna, kriteria\_carian, format, bilangan\_rekod, checksum, tarikh\_masa); `AUDIT_INTEGRITI_SEMAKAN`; `AUDIT_ARKIB`; `AUDIT_RETENSI_POLISI`.

**Integrasi.** Semua modul melalui lapisan peristiwa aplikasi; MOD-RBC bagi log masuk dan perubahan peranan; MOD-WFE bagi sejarah keputusan kelulusan; MOD-DMS bagi jejak capaian dan pindaan dokumen; MOD-NTF bagi log penghantaran dan amaran integriti; MOD-ADM bagi job scheduler semakan integriti dan polisi retensi; platform SIEM MAIS; storan arkib berperingkat; perkhidmatan NTP.

**Kawalan keselamatan.**

- Storan hanya-tambah pada peringkat pangkalan data; akaun aplikasi tiada kebenaran UPDATE atau DELETE ke atas jadual audit
- Rantaian nilai cincangan antara rekod berturutan dan semakan integriti berkala berjadual
- Capaian audit trail baca sahaja, dihadkan kepada Juruaudit, Pentadbir Sistem dan peranan yang diluluskan MAIS
- Capaian, carian dan eksport ke atas audit trail turut direkodkan sebagai rekod audit
- Medan sensitif ditapis sebelum ditulis; tiada kata laluan, token atau kunci dalam rekod audit
- Pakej eksport disertai checksum dan metadata bagi pengesahan integriti
- Penyulitan at-rest AES-256 bagi jadual audit, pakej eksport dan storan arkib
- Penstriman ke SIEM MAIS menyediakan salinan luaran yang bebas daripada kawalan pentadbir SISDATA

**Laporan yang boleh dijana.** Kronologi aktiviti per rekod; aktiviti pengguna mengikut pengguna, peranan, modul dan tempoh; capaian data Sulit dan Rahsia; aktiviti di luar waktu pejabat dan hari cuti; anomali aktiviti berkelompok; percubaan log masuk gagal dan capaian tanpa kebenaran; perubahan konfigurasi dengan nilai sebelum dan selepas; sejarah keputusan kelulusan; penghantaran notifikasi dan status bacaan; semakan integriti audit trail; capaian kepada rekod audit dan senarai eksport; pakej audit untuk juruaudit luar berserta checksum.

**Kriteria penerimaan.**

1. Pindaan satu medan pada rekod hartanah menjana rekod audit yang memaparkan nama medan, nilai asal, nilai baharu, ID pengguna, peranan semasa, alamat IP, modul dan cap masa.
2. Percubaan mengemas kini atau memadam rekod audit melalui antara muka aplikasi ditolak bagi semua peranan termasuk Pentadbir Sistem, dan percubaan melalui akaun aplikasi pangkalan data gagal kerana kekurangan kebenaran.
3. Pengubahan rekod audit secara langsung pada pangkalan data dikesan oleh semakan integriti berjadual dan mencetuskan amaran kepada Pentadbir Sistem serta Juruaudit.
4. Carian audit trail mengikut gabungan pengguna, modul, jenis tindakan dan tempoh mengembalikan hasil yang betul, dan hasil boleh dieksport dalam keempat-empat format.
5. Pakej eksport audit disertai checksum dan metadata, dan tindakan eksport muncul sebagai rekod audit baharu.
6. Paparan kronologi bagi satu lot ujian memaparkan semua aktiviti merentas modul dalam susunan masa yang betul.
7. Rekod audit distrim ke platform SIEM MAIS dalam format yang dipersetujui dan boleh dicari pada platform tersebut.
8. Rekod audit melebihi tempoh retensi dalam talian diarkibkan oleh job scheduler, kekal boleh dicari dan diambil semula, dan setiap capaian kepada arkib direkodkan.
9. Rekod audit yang melibatkan medan kata laluan atau token memaparkan nilai yang ditapis dan bukan nilai sebenar.

## 7. Modul Domain Teras

Bab ini memperincikan tiga modul yang membentuk teras rekod SISDATA — Pangkalan Data Induk dan Profil Hartanah 360° (MOD-PRO), Hak Milik Tanah (MOD-HKM) dan Rekod Pemerolehan (MOD-PER) — sebagai pelaksanaan RFP Seksyen 6.0 (Modul 2, 3 dan 4), Seksyen 7.0 serta Lampiran H A-4.00, A-5.00 dan A-6.00. Ketiga-tiganya dilaksanakan serentak dalam Gelombang G1 kerana setiap modul lain membaca rekod induk, rekod hak milik dan rekod pemerolehan sebagai sumber rujukan tunggal.

### Modul 2 · Pangkalan Data Induk dan Profil Hartanah 360°

**Kod:** MOD-PRO · **Gelombang:** G1 (Bulan 3.5–4.0) · **Rujukan:** RFP Seksyen 6.1 (Modul 2), 7.1.1, 7.1.2, 7.1.3, 7.2.1; Lampiran H A-4.01–A-4.12

Modul ini menjadi rekod tuan tunggal bagi setiap hartanah MAIS dengan menyatukan maklumat tanah, hak milik, pemerolehan, penilaian, kontrak, cukai, dokumen, data spatial, aduan dan kes mahkamah dalam satu skema pangkalan data bersepadu yang mematuhi prinsip ACID. Modul ini turut memaparkan setiap lot sebagai satu Profil Hartanah 360° dan menguatkuasakan kerangka tadbir urus data bagi keseluruhan SISDATA.

**Kategori pengguna.**

- Pentadbir Pangkalan Data dan Pegawai Teknikal Bahagian ICT MAIS — pengurusan skema, indeks, view dan stored procedure
- Data Owner peringkat bahagian: Ketua Bahagian Pengurusan Hartanah, Ketua Bahagian Baitulmal, Ketua Bahagian Kewangan dan wakil Bahagian Wakaf
- Data Steward operasi di setiap bahagian dan sembilan (9) daerah Selangor
- Pegawai Hartanah Daerah dan pegawai operasi — cipta dan kemas kini rekod induk dalam skop kebenaran
- Pengurusan tertinggi MAIS dan Ketua Bahagian — capaian baca Profil Hartanah 360° dan laporan kualiti data
- Juruaudit Dalam MAIS — capaian baca audit data dan laporan kualiti data

**Keperluan fungsian**

| Kod | Keperluan | Keutamaan | Sumber |
| --- | --- | --- | --- |
| SRS-PRO-01 | Sistem hendaklah dibangunkan di atas pangkalan data hubungan berpiawaian industri yang menyokong transaksi ACID sepenuhnya, termasuk transaksi bersarang, kawalan concurrency dan rollback automatik apabila transaksi gagal. | Wajib | A-4.01; RFP 7.1.3 |
| SRS-PRO-02 | Sistem hendaklah disertakan dengan dokumen justifikasi pangkalan data yang menyatakan jenis dan nama produk, versi tepat, model dan bilangan lesen, keperluan server (CPU, memori, storan, IOPS), kaedah backup, reka bentuk HA dan DR serta justifikasi pemilihan berbanding alternatif. | Wajib | A-4.02 |
| SRS-PRO-03 | Sistem hendaklah disertakan dengan model data logikal (ERD ternormalisasi) bagi semua modul SISDATA, menunjukkan setiap entiti, atribut, kardinaliti dan hubungan antara entiti. | Wajib | A-4.03 |
| SRS-PRO-04 | Sistem hendaklah disertakan dengan model data fizikal yang menyenaraikan setiap jadual, medan, jenis data, saiz, nilai lalai, primary key, foreign key, unique constraint, check constraint, indeks (termasuk indeks komposit dan spatial), view, materialized view dan stored procedure, beserta skrip DDL yang boleh dilaksanakan. | Wajib | A-4.03 |
| SRS-PRO-05 | Sistem hendaklah disertakan dengan kamus data bagi setiap medan merangkumi nama perniagaan, nama teknikal, definisi, jenis data, format, nilai dibenarkan, status wajib, data owner, data steward dan klasifikasi kerahsiaan, serta boleh dieksport sebagai dokumen dan dipaparkan dalam sistem. | Wajib | A-4.03; RFP 7.2.1a |
| SRS-PRO-06 | Sistem hendaklah melaksanakan skema pangkalan data bersepadu merangkumi sekurang-kurangnya sepuluh (10) entiti data utama RFP — Lot Tanah, Hak Milik, Pemerolehan, Penilaian, Kontrak dan Pajakan, Cukai, Dokumen Digital, Aduan dan Isu, Kes Mahkamah serta Data Spatial/GIS — setiap satu sebagai entiti penuh yang mempunyai jadual, kunci dan kitaran hayat rekod tersendiri. | Wajib | RFP 7.1.1 |
| SRS-PRO-07 | Sistem hendaklah melaksanakan entiti Data Spatial/GIS sebagai entiti penuh yang merekodkan geometri polygon, koordinat, sistem unjuran (SRID), sumber data spatial, tarikh kemas kini dan status pengesahan data spatial bagi setiap lot; entiti ini tidak boleh digantikan atau digabungkan dengan entiti inventori aset fizikal. | Wajib | RFP 7.1.1(j); A-4.04 |
| SRS-PRO-08 | Sistem hendaklah menyediakan model data GIS/spatial yang menyimpan koordinat, geometri, layer peta, lot tanah, sempadan dan atribut berkaitan melalui teknologi spatial yang diluluskan MAIS, serta berupaya menyimpan, mengurus, memproses dan menganalisis data geospatial secara cekap dan berskala. | Wajib | A-4.04; RFP 7.1.2 |
| SRS-PRO-09 | Sistem hendaklah merekodkan sumber data spatial bagi setiap geometri yang dimuat masuk — antara lain JUPEM, PTG/e-Tanah, PBT, ukuran juruukur berlesen, lakaran dalaman atau digitasi imej satelit — beserta tarikh perolehan data, tarikh kemas kini terakhir dan pegawai yang mengesahkan. | Wajib | RFP 7.1.1(j); A-14.07 |
| SRS-PRO-10 | Sistem hendaklah menguatkuasakan status pengesahan data spatial bagi setiap geometri lot dengan sekurang-kurangnya nilai belum disahkan, disahkan juruukur, disahkan PTG dan bertindih/perlu semakan, serta menandakan lot yang geometrinya belum disahkan pada senarai, profil lot dan peta. | Wajib | RFP 7.1.1(j); Jurang |
| SRS-PRO-11 | Sistem hendaklah menetapkan dan merekodkan data owner dan data steward bagi setiap komponen data utama dalam satu daftar stewardship data dalam sistem, termasuk nama jawatan, bahagian, peranan dan tanggungjawab memastikan ketepatan, kesahihan dan kemas kini data. | Wajib | RFP 7.2.1a |
| SRS-PRO-12 | Sistem hendaklah menghalakan rekod yang gagal kawalan kualiti data atau memerlukan pengesahan kepada data steward yang bertanggungjawab melalui senarai tugasan dan notifikasi, serta merekodkan tindakan pembetulan yang diambil. | Wajib | RFP 7.2.1a; A-4.12 |
| SRS-PRO-13 | Sistem hendaklah menguatkuasakan peraturan kualiti data pada peringkat cipta dan kemas kini rekod induk meliputi format medan, had nilai, medan wajib, keunikan rekod (keunikan nombor lot bagi setiap mukim dan keunikan nombor hak milik), pengesahan input dan pencegahan pertindihan data, dengan mesej ralat yang menyatakan medan dan sebab kegagalan. | Wajib | RFP 7.2.1b; URS-PRO-10 |
| SRS-PRO-14 | Sistem hendaklah mengelaskan setiap komponen data mengikut tahap kerahsiaan awam, dalaman, sulit atau rahsia selaras dengan klasifikasi data MAIS, dan menguatkuasakan kawalan akses, penyulitan semasa penyimpanan dan penghantaran serta pengurusan hak capaian berdasarkan klasifikasi tersebut. | Wajib | A-4.05; RFP 7.2.1c |
| SRS-PRO-15 | Sistem hendaklah menyediakan kemudahan import data daripada fail Excel dan CSV serta format standard lain yang dipersetujui, menggunakan templat import yang boleh dimuat turun bagi setiap entiti teras dan pemetaan medan yang boleh dikonfigurasikan. | Wajib | A-4.06 |
| SRS-PRO-16 | Sistem hendaklah melaksanakan validasi dan semakan ralat bagi setiap baris data yang diimport sebelum data dikomit — semakan jenis data, medan wajib, nilai rujukan, keunikan rekod dan pengesanan duplikasi terhadap rekod sedia ada — serta memaparkan pratonton keputusan semakan sebelum pengesahan. | Wajib | A-4.06 |
| SRS-PRO-17 | Sistem hendaklah menjana laporan keputusan import bagi setiap sesi yang menyatakan bilangan baris dibaca, berjaya, ditolak dan diabaikan, senarai baris ditolak beserta nombor baris dan sebab penolakan, pengguna, tarikh dan masa, serta membenarkan laporan dieksport dan fail import asal dikekalkan untuk rujukan audit. | Wajib | A-4.06; A-4.08 |
| SRS-PRO-18 | Sistem hendaklah menyediakan fungsi eksport data dalam format Excel, CSV, PDF dan JSON serta format lain yang dipersetujui MAIS, dengan skop medan yang dieksport tertakluk kepada peranan dan klasifikasi data pengguna. | Wajib | A-4.07 |
| SRS-PRO-19 | Sistem hendaklah merekodkan audit data bagi setiap transaksi tambah, pinda, padam, sah, lulus, batal, arkib dan pemulihan data, dengan rekod audit mengandungi nilai asal, nilai baharu, ID pengguna, alamat IP, modul, tarikh, masa, status transaksi dan sebab perubahan. | Wajib | A-4.08; A-16.10 |
| SRS-PRO-20 | Sistem hendaklah mengesan duplikasi rekod tanah, lot, hak milik, penyewa dan dokumen berdasarkan kriteria padanan yang boleh dikonfigurasikan MAIS, memaparkan skor padanan dan membolehkan pegawai mengesahkan, menggabungkan atau menolak cadangan duplikasi dengan rekod keputusan disimpan. | Wajib | A-4.09; URS-PRO-10 |
| SRS-PRO-21 | Sistem hendaklah menyediakan metadata bagi setiap dokumen dan rekod — kategori, lokasi, tahun, status, pemilik proses, tarikh luput, versi dan tag carian — serta membolehkan metadata digunakan sebagai kriteria carian dan tapisan. | Wajib | A-4.10 |
| SRS-PRO-22 | Sistem hendaklah melaksanakan versioning bagi rekod yang mengalami perubahan — hak milik, penilaian, kontrak dan pajakan — dengan nilai lama dikekalkan, perbandingan sebelum dan selepas boleh dipaparkan, dan perubahan kritikal memerlukan pengesahan pegawai kedua. | Wajib | RFP 7.2.1d; URS-PRO-11 |
| SRS-PRO-23 | Sistem hendaklah menyediakan mekanisme arkib data dan dokumen mengikut tempoh retensi yang ditetapkan MAIS, dengan jadual retensi boleh dikonfigurasikan bagi setiap kategori, pencetus arkib automatik, kelulusan pegawai sebelum pengarkiban, dan rekod yang diarkib kekal boleh dicari serta dipulihkan oleh peranan yang dibenarkan. | Wajib | A-4.11; RFP 7.2.1e |
| SRS-PRO-24 | Sistem hendaklah menyediakan laporan kualiti data merangkumi sekurang-kurangnya rekod tidak lengkap, rekod bertindih, dokumen tamat tempoh, cukai tertunggak dan data yang memerlukan pengesahan, dengan tapisan mengikut daerah, mukim, bahagian, entiti dan data steward. | Wajib | A-4.12 |
| SRS-PRO-25 | Sistem hendaklah melaksanakan Master Data Management melalui senarai rujukan berpusat bagi daerah, mukim, kategori guna tanah, zoning, jenis hak milik, saluran pemerolehan, jenis dokumen dan jenis cukai, dengan semua modul menggunakan senarai yang sama dan perubahan direkod berversi. | Wajib | RFP 7.2.1f |
| SRS-PRO-26 | Sistem hendaklah memaparkan satu skrin Profil Hartanah 360° bagi setiap lot yang menyatukan identiti dan hak milik, kedudukan kewangan, pemanfaatan, risiko dan isu, ruang dan aset, serta dokumen dan jejak audit dalam satu paparan bertab. | Wajib | A-5.07; URS-PRO-01 |
| SRS-PRO-27 | Sistem hendaklah memaparkan garis masa kronologi bagi setiap lot daripada tarikh pemerolehan, pendaftaran hak milik, setiap penilaian, setiap pajakan, setiap urusan Pejabat Tanah, aduan dan kes mahkamah sehingga status semasa, dengan pautan terus kepada rekod asal setiap peristiwa. | Perlu | URS-PRO-03; A-5.07 |
| SRS-PRO-28 | Sistem hendaklah memaparkan graf hasil dan kos bagi setiap lot meliputi hasil sewaan terkumpul, kos cukai dibayar, kos penyelenggaraan aset dan kos guaman secara bulanan dan tahunan beserta pengiraan pulangan bersih. | Perlu | URS-PRO-04 |
| SRS-PRO-29 | Sistem hendaklah menyediakan semua data profil hartanah melalui REST API berversi berdokumentasi OpenAPI 3.0 — senarai lot, butiran lot, geometri GeoJSON dan kedudukan kewangan — dengan autentikasi selamat, skop kebenaran per kunci, tarikh luput, had kadar dan keupayaan pembatalan serta-merta. | Perlu | URS-PRO-06; URS-PRO-08; A-15.02 |
| SRS-PRO-30 | Sistem hendaklah merekodkan setiap capaian kepada profil lot (lihat, eksport, cetak) sebagai log akses yang boleh dipaparkan dalam tab Dokumen dan Jejak kepada peranan yang dibenarkan. | Perlu | URS-PRO-05; A-4.08 |

**Aliran proses utama.**

1. Pentadbir Bahagian ICT menetapkan senarai rujukan induk dan merekodkan data owner serta data steward bagi setiap komponen data utama dalam daftar stewardship.
2. Pegawai Hartanah Daerah mencipta rekod lot tanah baharu, sama ada satu demi satu melalui borang atau secara pukal melalui import Excel/CSV menggunakan templat sistem.
3. Sistem melaksanakan validasi setiap baris, memaparkan pratonton keputusan, dan hanya mengomit baris yang lulus selepas pengesahan pengguna; laporan keputusan import dijana dan disimpan.
4. Sistem melaksanakan semakan duplikasi terhadap rekod sedia ada dan memaparkan cadangan padanan kepada data steward untuk keputusan sah, gabung atau tolak.
5. Geometri spatial lot dimuat naik atau dipautkan beserta sumber data, sistem unjuran dan tarikh kemas kini; status pengesahan ditetapkan sebagai belum disahkan.
6. Pegawai yang diberi kuasa mengesahkan geometri dan menukar status pengesahan kepada disahkan juruukur atau disahkan PTG, dengan tindakan direkod dalam audit trail spatial.
7. Rekod lot yang lengkap menjadi rekod induk aktif dan dipaparkan sebagai Profil Hartanah 360°; semua modul lain memaut kepada rekod induk ini.
8. Setiap perubahan rekod induk dijana sebagai versi baharu; perubahan kritikal dihantar untuk pengesahan pegawai kedua sebelum berkuat kuasa.
9. Sistem menjana laporan kualiti data secara berjadual dan menghalakan rekod bermasalah kepada data steward berkenaan.
10. Apabila tempoh retensi dicapai, sistem menjana senarai cadangan arkib untuk kelulusan, mengarkibkan rekod yang diluluskan, dan mengekalkannya dalam keadaan boleh dicari serta dipulihkan.

**Entiti data.**

- `lot_tanah` — id\_lot (PK), no\_lot, id\_mukim (FK), id\_daerah (FK), negeri, keluasan, unit\_keluasan, kategori\_guna\_tanah, zoning, syarat\_nyata, koordinat\_gps\_pusat, bahagian\_pemilik\_mais, status\_lot, id\_data\_owner, id\_data\_steward, tarikh\_cipta, tarikh\_kemas\_kini, versi\_rekod. Unique constraint (no\_lot, id\_mukim)
- `data_spatial` — id\_spatial (PK), id\_lot (FK), geometri\_polygon, sistem\_unjuran\_srid, keluasan\_dikira, sumber\_data\_spatial, rujukan\_fail\_sumber, tarikh\_perolehan\_data, tarikh\_kemas\_kini, status\_pengesahan\_spatial, id\_pegawai\_pengesah, tarikh\_pengesahan, catatan\_pertindihan. Indeks spatial pada geometri\_polygon
- `daftar_stewardship_data` — id\_stewardship (PK), komponen\_data, entiti\_berkaitan, jawatan\_data\_owner, bahagian\_data\_owner, jawatan\_data\_steward, bahagian\_data\_steward, peranan\_tanggungjawab, tarikh\_berkuat\_kuasa, status
- `peraturan_kualiti_data` — id\_peraturan (PK), entiti, medan, jenis\_peraturan, ungkapan\_peraturan, mesej\_ralat, tahap\_keterukan, status\_aktif
- `klasifikasi_data` — id\_klasifikasi (PK), komponen\_data, aras\_kerahsiaan, keperluan\_encryption, peranan\_dibenarkan, tarikh\_semakan
- `sesi_import` — id\_sesi (PK), entiti\_sasaran, nama\_fail, format\_fail, id\_pengguna, tarikh\_masa, bil\_baris\_dibaca, bil\_berjaya, bil\_ditolak, bil\_diabaikan, lokasi\_fail\_asal, status\_sesi
- `ralat_import` — id\_ralat (PK), id\_sesi (FK), no\_baris, medan, nilai\_diberi, kod\_ralat, sebab\_penolakan
- `versi_rekod_induk` — id\_versi (PK), entiti, id\_rekod, no\_versi, nilai\_sebelum, nilai\_selepas, id\_pengguna, tarikh\_masa, justifikasi, id\_pengesah\_kedua, status\_pengesahan
- `audit_data` — id\_audit (PK), entiti, id\_rekod, jenis\_transaksi, nilai\_asal, nilai\_baharu, id\_pengguna, alamat\_ip, modul, tarikh\_masa, status\_transaksi, sebab\_perubahan
- `jadual_retensi` — id\_retensi (PK), kategori\_rekod, tempoh\_retensi\_tahun, tindakan\_selepas\_retensi, rujukan\_dasar\_mais, memerlukan\_kelulusan, id\_pelulus
- `rekod_arkib` — id\_arkib (PK), entiti, id\_rekod, tarikh\_arkib, id\_pelulus\_arkib, lokasi\_storan\_arkib, tarikh\_layak\_lupus, status\_arkib
- `rujukan_induk` — id\_rujukan (PK), jenis\_senarai, kod, keterangan\_bm, keterangan\_en, susunan\_paparan, status\_aktif, versi, tarikh\_berkuat\_kuasa
- `cadangan_duplikasi` — id\_cadangan (PK), entiti, id\_rekod\_a, id\_rekod\_b, kriteria\_padanan, skor\_padanan, keputusan, id\_data\_steward, tarikh\_keputusan
- `log_akses_profil` — id\_log (PK), id\_lot (FK), id\_pengguna, jenis\_capaian, tarikh\_masa, alamat\_ip
- View dan stored procedure utama: `vw_profil_hartanah_360`, `vw_kualiti_data_rekod_tidak_lengkap`, `vw_lot_spatial_belum_disahkan`, `sp_import_validasi_lot`, `sp_kesan_duplikasi_lot`, `sp_arkib_mengikut_retensi`, `sp_jana_versi_rekod_induk`

**Integrasi.**

- Modul GIS Mapping — pembekalan dan penggunaan geometri daripada entiti `data_spatial` bagi semua lapisan peta
- Sumber data spatial luaran JUPEM, PTG/e-Tanah dan PBT bagi muat masuk geometri dan sempadan lot
- Modul Dokumen Digital — metadata dokumen, kawalan versi dan log capaian
- Modul Laporan dan Dashboard Eksekutif — view dan enjin pertanyaan dikongsi
- Penyedia integrasi berpusat dan Pejabat Tanah Daerah — pengesahan silang maklumat lot dan hak milik
- GRP MAIS — rekod cukai dibayar dan hasil sewaan bagi graf hasil dan kos
- Portal Hartanah Wakaf Selangor — pertukaran maklumat aset dan ketersediaan hartanah
- REST API berskop layan diri bagi sistem MAIS lain

**Kawalan keselamatan.**

- RBAC mengikut peranan, bahagian, daerah, modul dan transaksi, dikuatkuasakan sehingga peringkat medan bagi medan berklasifikasi sulit dan rahsia
- Penyulitan data sensitif semasa penghantaran (TLS 1.3) dan penyimpanan (AES-256 atau setara)
- MFA bagi pentadbir pangkalan data, penjanaan kunci API dan pengesahan perubahan rekod induk kritikal
- Audit trail tidak boleh diubah bagi semua transaksi data, import, eksport, arkib dan pemulihan
- Pengasingan tugas antara pencipta rekod, data steward pengesah dan pelulus perubahan kritikal
- Kunci API berskop dengan tarikh luput, had kadar, penggiliran kunci dan pembatalan serta-merta
- Log akses per lot bagi setiap paparan, eksport dan cetakan Profil Hartanah 360°

**Laporan yang boleh dijana.**

- Laporan kualiti data: rekod tidak lengkap mengikut entiti, medan dan data steward
- Laporan rekod bertindih dan keputusan penyelesaian duplikasi
- Laporan dokumen tamat tempoh dan hampir tamat tempoh
- Laporan data yang memerlukan pengesahan, termasuk geometri spatial belum disahkan
- Laporan keputusan import bagi setiap sesi
- Laporan daftar stewardship data
- Laporan status pengesahan data spatial mengikut daerah dan mukim
- Laporan audit data mengikut pengguna, modul, jenis transaksi dan tempoh
- Laporan rekod yang layak diarkib dan yang telah diarkib
- Laporan inventori portfolio hartanah mengikut daerah, mukim, kategori guna tanah dan status lot
- Laporan penggunaan kunci API mengikut kunci, sistem pemanggil dan tempoh
- Kamus data dan model data fizikal yang dieksport sebagai dokumen

**Kriteria penerimaan.**

1. Skema pangkalan data mengandungi kesepuluh entiti data utama RFP Seksyen 7.1.1 sebagai jadual berasingan, termasuk entiti Data Spatial/GIS dengan medan geometri polygon, koordinat, sistem unjuran, sumber data, tarikh kemas kini dan status pengesahan, yang boleh ditunjukkan dalam skrip DDL dan dalam antara muka sistem.
2. Model data logikal, model data fizikal dan kamus data diserahkan, diterima MAIS, dan konsisten dengan pangkalan data yang dilaksanakan apabila disemak silang.
3. Daftar stewardship data lengkap bagi semua komponen data utama ditunjukkan dalam sistem, dan rekod gagal kualiti data terbukti dihalakan kepada data steward yang betul.
4. Ujian import satu fail Excel dan satu fail CSV bercampur baris sah dan bermasalah menunjukkan hanya baris sah dikomit, dan laporan keputusan menyenaraikan setiap baris ditolak beserta nombor baris dan sebab.
5. Ujian pengesanan duplikasi terhadap set data ujian mengembalikan kesemua pasangan bertindih yang diketahui, dan keputusan data steward direkod dalam audit trail.
6. Ujian arkib menunjukkan rekod yang mencapai tempoh retensi dikenal pasti, diarkibkan selepas kelulusan, kekal boleh dicari, dan boleh dipulihkan dengan kedua-dua tindakan direkod.
7. Profil Hartanah 360° bagi satu lot ujian memaparkan hak milik, lokasi GIS, cukai, pajakan, dokumen, tindakan, risiko dan sejarah transaksi dalam satu paparan, dan setiap capaian direkod dalam log akses.

## 8. Modul Domain Operasi

Bab ini memperincikan tujuh modul yang menyokong urusan harian pengurusan hartanah MAIS selepas rekod asas hak milik dan pemerolehan diwujudkan. Setiap modul dinyatakan dengan objektif, kategori pengguna, keperluan fungsian yang boleh diuji, aliran proses, entiti data, integrasi, kawalan keselamatan, laporan dan kriteria penerimaan, selaras dengan RFP Seksyen 6.2 dan spesifikasi Lampiran H.

### Modul 5 · Pajakan dan Kontrak

**Kod:** MOD-PJK · **Gelombang:** G3 (Bulan 4.0–4.5) · **Rujukan:** RFP Seksyen 6.1 (Modul 5), 6.6, 6.7; Lampiran H A-8.01–A-8.11, A-15.03

Modul ini mengurus keseluruhan hayat perjanjian pajakan, sewaan dan kontrak hartanah MAIS — daripada tawaran premis kosong, permohonan, kelulusan, penyediaan kontrak, kutipan bayaran dalam talian, sehingga pembaharuan atau penamatan — dengan rekod kewangan bertaraf lejar bagi setiap perjanjian. Ia juga menjadi tempat kadar Dasar Agihan Manfaat direkod, dikira dan dipaparkan mengikut kategori hartanah selaras RFP Seksyen 6.7.

**Kategori pengguna.**

- Unit Sewaan, Bahagian Pengurusan Hartanah — pendaftar perjanjian, penyedia kontrak
- Pegawai Daerah — semakan premis, pengesahan status fizikal
- Bahagian Kewangan MAIS — pengesahan bayaran, rekonsiliasi, pelepasan deposit
- Bahagian Wakaf dan Bahagian Baitulmal — pengesahan kategori hartanah dan dasar agihan manfaat
- Pegawai Pelulus Berlapis — Ketua Bahagian dan Pengurusan Atasan menurut had nilai
- Penyewa dan orang awam — melalui Portal Hartanah Wakaf Selangor sahaja, capaian tidak langsung
- Pentadbir Sistem — konfigurasi kadar, templat surat, parameter agihan manfaat

**Keperluan fungsian**

| Kod | Keperluan | Keutamaan | Sumber |
| --- | --- | --- | --- |
| SRS-PJK-01 | Sistem hendaklah menyediakan pendaftaran perjanjian sewaan atau pajakan tanah dan premis milik MAIS yang merekod sekurang-kurangnya maklumat penyewa (nama, jenis entiti, nombor pengenalan atau pendaftaran syarikat, alamat, telefon, e-mel), lot atau premis terlibat, tempoh perjanjian, kadar sewaan, cagaran, terma khas, kategori hartanah dan status perjanjian. | Wajib | A-8.01; URS-PJK-01 |
| SRS-PJK-02 | Sistem hendaklah menyokong status perjanjian yang merangkumi draf, dalam kelulusan, aktif, hampir tamat, tamat tempoh, diperbaharui dan ditamatkan, serta merekod tarikh dan pegawai bagi setiap peralihan status. | Wajib | A-8.01; URS-PJK-01 |
| SRS-PJK-03 | Sistem hendaklah merekod status premis atau tanah — kosong, telah disewa, dalam penyelenggaraan, dalam tindakan penguatkuasaan atau tidak tersedia — dan mengemas kini status secara automatik apabila perjanjian diaktifkan atau ditamatkan. | Wajib | A-8.02 |
| SRS-PJK-04 | Sistem hendaklah menyediakan katalog premis dan tanah yang tersedia untuk tawaran, boleh ditapis mengikut daerah, mukim, kategori hartanah, keluasan dan julat kadar, dan disalurkan kepada portal awam melalui API bagi paparan orang ramai. | Wajib | A-8.02; RFP 6.6 |
| SRS-PJK-05 | Sistem hendaklah menyediakan aliran kerja permohonan, semakan, kelulusan, penyediaan kontrak, pembaharuan, penamatan dan tindakan susulan melalui Modul Workflow dengan peringkat pelulus yang boleh dikonfigurasi menurut nilai dan kategori perjanjian. | Wajib | A-8.03; URS-PJK-06 |
| SRS-PJK-06 | Sistem hendaklah menerima permohonan sewaan daripada portal awam melalui API dua hala dan memasukkannya ke dalam aliran kerja yang sama dengan permohonan kaunter, serta mengembalikan status permohonan kepada portal. | Wajib | RFP 6.6; URS-PJK-06 |
| SRS-PJK-07 | Sistem hendaklah menyokong pembaharuan perjanjian dengan fungsi semakan kadar yang memaparkan kadar semasa berbanding nilai pasaran atau penilaian terkini sebagai asas justifikasi kelulusan. | Perlu | A-8.03; URS-PJK-06 |
| SRS-PJK-08 | Sistem hendaklah menjana notifikasi tamat tempoh kontrak sekurang-kurangnya 6 bulan, 3 bulan dan 1 bulan sebelum tarikh tamat, dengan tempoh notifikasi boleh dikonfigurasi tanpa perubahan kod. | Wajib | A-8.04; URS-PJK-05 |
| SRS-PJK-09 | Sistem hendaklah menyediakan templat surat yang boleh dikonfigurasi — surat tawaran, peringatan bayaran, tamat tempoh, teguran dan penamatan — dengan medan gabungan automatik daripada rekod perjanjian dan penomboran rujukan surat. | Wajib | A-8.05 |
| SRS-PJK-10 | Sistem hendaklah menyimpan dokumen kontrak beserta kawalan versi, di mana setiap pindaan terma, pembaharuan atau penamatan direkod dengan tarikh, pegawai, justifikasi dan salinan dokumen sebelum serta selepas pindaan dalam DMS. | Wajib | A-8.10; URS-PJK-02 |
| SRS-PJK-11 | Sistem hendaklah menyediakan lejar transaksi bagi setiap perjanjian yang merekod setiap caj dan bayaran dengan tarikh, jenis transaksi, amaun, kaedah bayaran, rujukan resit dan baki berjalan, serta boleh dipaparkan mengikut tempoh dan dieksport sebagai penyata akaun penyewa. | Wajib | A-8.06; URS-PJK-03 |
| SRS-PJK-12 | Sistem hendaklah menjana jadual caj secara automatik daripada terma perjanjian dan mengira tunggakan secara automatik dengan analisis umur hutang pada kumpulan 1–30, 31–60, 61–90 dan melebihi 90 hari. | Wajib | A-8.06; URS-PJK-04 |
| SRS-PJK-13 | Sistem hendaklah merekod deposit dan cagaran berasingan daripada sewa, termasuk amaun, tarikh terima, status dan penyelarasan deposit semasa penamatan perjanjian dengan kelulusan Bahagian Kewangan. | Wajib | A-8.06; URS-PJK-05 |
| SRS-PJK-14 | Sistem hendaklah berintegrasi dengan payment gateway yang diluluskan MAIS bagi membolehkan penyewa membuat bayaran sewa, tunggakan dan deposit secara dalam talian menggunakan sekurang-kurangnya saluran FPX dan kad kredit atau debit. | Wajib | A-8.07; A-15.03; Jurang |
| SRS-PJK-15 | Sistem hendaklah menjana transaksi bayaran dalam talian dengan nombor rujukan unik yang dipautkan kepada perjanjian dan caj tertentu, serta merekod status transaksi sebagai menunggu, berjaya, gagal, dibatalkan atau tamat masa. | Wajib | A-8.07; Jurang |
| SRS-PJK-16 | Sistem hendaklah mengesahkan setiap bayaran melalui mekanisme dua arah — panggilan balik daripada payment gateway dan pertanyaan status transaksi berjadual bagi transaksi tanpa panggilan balik — dan hanya mengkreditkan lejar selepas status berjaya disahkan gateway. | Wajib | A-8.07; Jurang |
| SRS-PJK-17 | Sistem hendaklah menjana resit rasmi secara automatik sebaik bayaran disahkan, dengan nombor siri berturutan yang tidak boleh diubah, menghantar resit kepada pembayar melalui e-mel dan menyimpan salinan dalam DMS yang dipautkan kepada perjanjian. | Wajib | A-8.07; A-8.06; Jurang |
| SRS-PJK-18 | Sistem hendaklah melaksanakan rekonsiliasi bayaran berjadual harian dengan membandingkan penyata penyelesaian payment gateway terhadap rekod lejar, dan menjana senarai sisihan untuk tindakan Bahagian Kewangan. | Wajib | A-8.07; URS-PJK-07 |
| SRS-PJK-19 | Sistem hendaklah merekod bayaran yang diterima di luar payment gateway termasuk kaunter, EFT, pindahan bank dan cek, dengan muat naik bukti bayaran, dan membenarkan padanan manual kepada caj dalam lejar oleh pegawai yang diberi kuasa. | Wajib | A-8.06; Jurang |
| SRS-PJK-20 | Sistem hendaklah menyokong bayaran separa dan ansuran, dengan agihan automatik amaun diterima kepada caj tertunggak mengikut susunan keutamaan yang boleh dikonfigurasi. | Perlu | A-8.06; Jurang |
| SRS-PJK-21 | Sistem hendaklah menyelaraskan rekod bayaran dengan GRP MAIS dan e-Sewa MAIS, dan melaporkan sisihan secara automatik kepada Bahagian Kewangan untuk tindakan pembetulan. | Wajib | A-8.07; URS-PJK-07 |
| SRS-PJK-22 | Sistem hendaklah membolehkan kadar Dasar Agihan Manfaat direkod dan diselenggara mengikut kategori hartanah sekurang-kurangnya premis, premis perniagaan, premis kediaman, tanah dan bazar, dengan setiap kadar mempunyai tarikh mula berkuat kuasa, tarikh tamat, rujukan kelulusan MAIS dan versi dasar. | Wajib | RFP 6.7; A-8.11 |
| SRS-PJK-23 | Sistem hendaklah merekod kadar agihan manfaat yang berbeza bagi hartanah diwakafkan berbanding dibeli, dan menentukan kadar yang terpakai bagi setiap tempat secara automatik berdasarkan saluran pemerolehan yang direkod dalam Modul Rekod Pemerolehan. | Wajib | A-8.11; RFP 6.7 |
| SRS-PJK-24 | Sistem hendaklah mengira agihan manfaat bagi setiap tempat dan tempoh perakaunan berdasarkan hasil yang dikutip dan kadar dasar yang terpakai, memaparkan pecahan mengikut penerima manfaat, dan menyimpan hasil kiraan sebagai rekod boleh audit yang dipautkan kepada versi dasar yang digunakan. | Wajib | A-8.11; RFP 6.7 |
| SRS-PJK-25 | Sistem hendaklah memaparkan kiraan agihan manfaat mengikut tempat, kategori hartanah, daerah dan tempoh, membolehkan simulasi kiraan sebelum kadar baharu diluluskan, serta menyalurkan maklumat yang disahkan kepada portal awam melalui API. | Perlu | A-8.11; RFP 6.7 |
| SRS-PJK-26 | Sistem hendaklah menyediakan dashboard penghunian dan kekosongan bagi aset tanah dan premis merangkumi kadar penghunian mengikut daerah dan kategori, tempoh kosong purata, senarai premis kosong melebihi ambang dan hasil berpotensi yang belum dijana. | Wajib | A-8.09 |
| SRS-PJK-27 | Sistem hendaklah menyediakan laporan sewaan dan pajakan mengikut status perjanjian, lokasi, kategori aset, penyewa, jumlah hasil, tunggakan dan tempoh kontrak, dengan eksport dalam PDF dan Excel. | Wajib | A-8.08 |
| SRS-PJK-28 | Sistem hendaklah merekod setiap tindakan pada perjanjian dan lejar dalam audit trail, dan sebarang pembatalan atau pelarasan transaksi lejar memerlukan kelulusan pegawai kedua beserta justifikasi bertulis sebelum berkesan. | Wajib | A-8.10; URS-PJK-08 |

**Aliran proses utama.**

1. Pegawai Unit Sewaan mendaftarkan premis dengan status kosong dan kategori hartanah; premis muncul dalam katalog tawaran dan disalurkan ke portal awam.
2. Permohonan diterima sama ada melalui portal atau kaunter, disemak kelengkapan dokumen, dan dijana nombor rujukan.
3. Permohonan melalui semakan kadar dan kelulusan berlapis menurut nilai perjanjian.
4. Selepas kelulusan, kontrak dijana daripada templat, ditandatangani, dimuat naik ke DMS dan perjanjian diaktifkan; status premis bertukar kepada telah disewa.
5. Jadual caj dijana automatik daripada terma perjanjian dan caj pertama dikeluarkan beserta notis bayaran.
6. Penyewa membuat bayaran melalui payment gateway, bayaran disahkan melalui panggilan balik dan pertanyaan status, lejar dikreditkan dan resit automatik dihantar.
7. Bayaran luar talian direkod dengan bukti bayaran dan dipadankan secara manual kepada caj dalam lejar.
8. Rekonsiliasi harian dijalankan terhadap penyata penyelesaian gateway, GRP MAIS dan e-Sewa; sisihan dilaporkan.
9. Hasil kutipan diproses oleh enjin Dasar Agihan Manfaat mengikut kategori hartanah dan status wakaf atau belian.
10. Notifikasi tamat tempoh dijana pada 6, 3 dan 1 bulan; proses pembaharuan atau penamatan dimulakan, deposit diselaraskan dan status premis dikemas kini.

**Entiti data.**

- `premis_aset` — id\_premis, id\_lot, jenis\_aset, kategori\_hartanah, saluran\_pemerolehan, keluasan, alamat, koordinat, status\_premis, tarikh\_status, kadar\_tawaran
- `kontrak_pajakan` — no\_kontrak, id\_premis, jenis\_perjanjian, id\_penyewa, tarikh\_mula, tarikh\_tamat, kadar, kitaran\_caj, amaun\_deposit, terma\_khas, status\_kontrak, id\_versi\_semasa
- `penyewa` — id\_penyewa, jenis\_entiti, nama, no\_pengenalan, no\_pendaftaran\_syarikat, alamat, telefon, emel, klasifikasi\_data
- `versi_kontrak` — id\_versi, no\_kontrak, no\_versi, jenis\_pindaan, tarikh\_pindaan, id\_pegawai, justifikasi, id\_dokumen\_sebelum, id\_dokumen\_selepas
- `lejar_transaksi` — id\_transaksi, no\_kontrak, tarikh\_transaksi, jenis, kod\_caj, amaun, baki\_berjalan, kaedah\_bayaran, no\_resit, id\_transaksi\_gateway, status\_pelarasan
- `jadual_caj` — id\_caj, no\_kontrak, tempoh, tarikh\_caj, tarikh\_akhir, amaun, amaun\_dibayar, status\_caj, umur\_tunggakan
- `transaksi_gateway` — id\_transaksi\_gateway, no\_kontrak, id\_caj, saluran, amaun, status, masa\_mula, masa\_pengesahan, kod\_respons, rujukan\_gateway, id\_fail\_penyelesaian
- `deposit_cagaran` — id\_deposit, no\_kontrak, amaun, tarikh\_terima, status, amaun\_tolakan, justifikasi\_tolakan, tarikh\_pulangan
- `dasar_agihan_manfaat` — id\_dasar, versi\_dasar, kategori\_hartanah, status\_perolehan, kadar\_agihan, penerima\_manfaat, tarikh\_mula\_kuasa, tarikh\_tamat, rujukan\_kelulusan
- `kiraan_agihan_manfaat` — id\_kiraan, id\_premis, no\_kontrak, tempoh\_perakaunan, hasil\_kutipan, id\_dasar\_digunakan, amaun\_agihan, pecahan\_penerima, status\_pengesahan
- `templat_surat` — id\_templat, jenis\_surat, kandungan, medan\_gabungan, versi, status\_aktif

**Integrasi.** Payment gateway yang diluluskan MAIS (permulaan transaksi, panggilan balik, pertanyaan status, fail penyelesaian harian); GRP MAIS bagi penyelarasan invois, resit dan jurnal hasil; e-Sewa MAIS bagi pertukaran rekod sewaan dan status bayaran; portal awam melalui API dua hala; Modul Workflow bagi aliran kelulusan; DMS bagi kontrak, resit dan bukti bayaran; Modul GIS bagi lapisan pajakan dan status penghunian; Modul Notifikasi; Modul Rekod Pemerolehan bagi penentuan status wakaf atau belian.

**Kawalan keselamatan.**

- Klasifikasi Sulit bagi rekod perjanjian dan maklumat penyewa; nombor pengenalan ditutup sebahagian kecuali bagi peranan yang dibenarkan
- Capaian berasaskan peranan dan penapisan mengikut daerah
- Pengasingan tugas antara pendaftar perjanjian, pelulus kadar dan pengesah bayaran
- Pelarasan atau pembatalan transaksi lejar memerlukan kelulusan pegawai kedua dengan justifikasi
- Komunikasi dengan payment gateway melalui TLS dengan pengesahan tandatangan digital pada setiap panggilan balik; kunci API disimpan dalam pengurusan kunci terkawal
- Maklumat kad kredit atau debit tidak disimpan dalam SISDATA; pengumpulan maklumat kad dilaksanakan sepenuhnya pada laman payment gateway
- Nombor siri resit tidak boleh diubah atau dipadam; pembatalan dilaksanakan melalui resit kredit berasingan
- Audit trail penuh bagi capaian, penciptaan, pindaan, kelulusan dan muat turun dokumen kontrak

**Laporan yang boleh dijana.** Sewaan dan pajakan mengikut status, lokasi, kategori aset, penyewa, hasil, tunggakan dan tempoh kontrak; tunggakan dengan analisis umur hutang mengikut daerah dan penyewa; penyata akaun penyewa; kutipan hasil mengikut kaedah bayaran; rekonsiliasi payment gateway dan senarai sisihan; penghunian dan kekosongan mengikut daerah dan kategori; kontrak hampir tamat tempoh; deposit dipegang, ditolak dan dipulangkan; kiraan Dasar Agihan Manfaat mengikut tempat, kategori, status wakaf atau belian dan tempoh; pindaan kontrak dan jejak audit pelarasan lejar.

**Kriteria penerimaan.**

1. Satu perjanjian baharu boleh didaftarkan daripada permohonan portal awam sehingga pengaktifan kontrak melalui aliran kelulusan berlapis, dan status premis berubah automatik daripada kosong kepada telah disewa.
2. Satu bayaran melalui FPX dan satu melalui kad berjaya diproses pada persekitaran ujian payment gateway, lejar dikreditkan dengan baki berjalan yang betul, dan resit bernombor siri dijana serta dihantar automatik melalui e-mel.
3. Transaksi bayaran yang tidak menerima panggilan balik dikenal pasti oleh proses pertanyaan status berjadual dan diselesaikan ke status akhir yang betul tanpa pendua kredit dalam lejar.
4. Proses rekonsiliasi harian terhadap fail penyelesaian ujian menghasilkan senarai sisihan yang tepat bagi tiga kes ujian: transaksi berjaya tanpa rekod lejar, rekod lejar tanpa penyelesaian, dan perbezaan amaun.
5. Notifikasi tamat tempoh dijana tepat pada 6, 3 dan 1 bulan sebelum tarikh tamat bagi set data ujian, dan tempoh boleh diubah tanpa perubahan kod.
6. Kadar Dasar Agihan Manfaat boleh direkod bagi kelima-lima kategori hartanah dengan kadar berbeza bagi hartanah diwakafkan berbanding dibeli, dan kiraan bagi sekurang-kurangnya lima tempat ujian menghasilkan amaun yang sama dengan kiraan manual yang disahkan MAIS.
7. Pelarasan transaksi lejar tidak boleh disempurnakan oleh pegawai yang sama yang merekodkannya, dan jejak audit memaparkan pegawai pelulus, tarikh dan justifikasi.

## 9. Modul Lapisan Wawasan

Lapisan Wawasan merangkumi tiga modul yang menukar rekod operasi SISDATA kepada gambaran keputusan: Dashboard Eksekutif (MOD-DSH) sebagai antaramuka utama pengguna dalaman, GIS Mapping dan Kepintaran Spatial (MOD-GIS) sebagai lapisan ruang, dan Laporan dan Analitik (MOD-LAP) sebagai lapisan pelaporan dan analisis. Kesemua modul dalam lapisan ini membaca sahaja daripada entiti domain teras melalui replika baca dan perkhidmatan peta, dan tidak menulis rekod induk hartanah; satu-satunya data yang dijana oleh lapisan ini ialah objek paparan milik pengguna seperti definisi dashboard, definisi laporan, senario analisis spatial, draf transaksi dan log audit penjanaan.

### Modul 1 · Dashboard Eksekutif

**Kod:** MOD-DSH · **Gelombang:** G2 (Bulan 4.0–4.5) · **Rujukan:** RFP Seksyen 6.0 (Modul 1), Seksyen 9.1.1(b); Lampiran H B-1.01–B-1.08, B-1.12, B-2.01–B-2.08, A-14.10

Objektif modul ini adalah memberikan setiap aras pengguna MAIS — pengurusan tertinggi, pengurusan bahagian dan pegawai operasi — paparan portfolio hartanah yang sepadan dengan bidang tugasnya secara masa nyata, lengkap dengan peta GIS interaktif, penapis bersepadu dan keupayaan menggerudi hingga ke rekod asal. Modul ini juga berfungsi sebagai portal pengguna dalaman SISDATA yang menyatukan inbox tugasan, draf transaksi, carian rujukan dan bantuan dalam talian dalam satu antaramuka responsif.

**Kategori pengguna.**

- Pengurusan tertinggi MAIS (Setiausaha, Ketua Pegawai Eksekutif dan ahli Majlis) — aras Eksekutif
- Ketua Bahagian dan Ketua Unit — aras Pengurusan
- Pegawai Daerah dan pegawai operasi hartanah — aras Operasi
- Pegawai lapangan melalui paparan mudah alih responsif
- Pentadbir sistem (Bahagian ICT MAIS) — konfigurasi widget, ambang indikator dan kebenaran paparan

**Keperluan fungsian**

| Kod | Keperluan | Keutamaan | Sumber |
| --- | --- | --- | --- |
| SRS-DSH-01 | Sistem hendaklah menyediakan tiga (3) aras dashboard, iaitu aras Eksekutif (KPI portfolio seluruh negeri), aras Pengurusan (prestasi bahagian dan daerah) dan aras Operasi (tugasan, SLA dan tindakan harian pegawai), dan aras yang dipaparkan hendaklah ditentukan secara automatik berdasarkan peranan pengguna yang log masuk. | Wajib | B-1.01; URS-DSH-01 |
| SRS-DSH-02 | Sistem hendaklah memaparkan pada dashboard utama sekurang-kurangnya indikator jumlah tanah/aset, jumlah mengikut daerah dan mukim, status tanah, status cukai, status sewaan/pajakan, tindakan tertunggak, indikator risiko, nilai portfolio semasa, hasil sewaan terkumpul tahun semasa, jumlah tunggakan cukai, bilangan pajakan aktif/hampir tamat/tamat tempoh, bilangan aduan terbuka dan bilangan kes mahkamah aktif. | Wajib | B-1.01; URS-DSH-02 |
| SRS-DSH-03 | Sistem hendaklah menyediakan dashboard peta GIS yang memaparkan taburan aset tanah MAIS secara interaktif dengan keupayaan zoom, pan, carian lokasi dan klik untuk paparan atribut lot. | Wajib | B-1.02; A-14.10; URS-DSH-02 |
| SRS-DSH-04 | Sistem hendaklah memaparkan pada peta dashboard lapisan status cukai, status pajakan dan risiko hartanah dengan pewarnaan tematik serta legenda, dan lapisan tersebut hendaklah boleh ditapis serentak dengan indikator dashboard yang lain. | Wajib | URS-DSH-08; B-1.02 |
| SRS-DSH-05 | Sistem hendaklah menyediakan dashboard cukai yang memaparkan jumlah cukai dibayar, cukai tertunggak, cukai hampir jatuh tempo, penalti dan trend bayaran mengikut tempoh yang dipilih pengguna. | Wajib | B-1.03 |
| SRS-DSH-06 | Sistem hendaklah menyediakan dashboard sewaan/pajakan yang memaparkan bilangan premis dan tanah kosong, pajakan aktif, pajakan hampir tamat kontrak, tunggakan sewaan dan hasil sewaan terkumpul. | Wajib | B-1.04 |
| SRS-DSH-07 | Sistem hendaklah menyediakan dashboard pemantauan tanah yang memaparkan jadual pemantauan, isu tapak, bilangan tindakan terbuka dan tertutup serta risk rating bagi setiap lot atau kawasan. | Wajib | B-1.05 |
| SRS-DSH-08 | Sistem hendaklah menyediakan penapis yang boleh digabungkan mengikut tahun, daerah, mukim, kategori tanah, status, pegawai bertanggungjawab dan modul, dan hendaklah mengekalkan penapis yang dipilih apabila pengguna berpindah antara widget dalam dashboard yang sama. | Wajib | B-1.06; URS-DSH-06 |
| SRS-DSH-09 | Sistem hendaklah menyokong jenis visualisasi berikut pada dashboard, iaitu carta bar, pie chart, line chart, progress bar, heatmap, peta dan traffic light indicator. | Wajib | B-1.07 |
| SRS-DSH-10 | Sistem hendaklah membenarkan pentadbir mengkonfigurasi ambang nilai bagi traffic light indicator dan heatmap bagi setiap indikator tanpa perubahan kod. | Perlu | Jurang |
| SRS-DSH-11 | Sistem hendaklah membolehkan pengguna membuat drill-down daripada setiap indikator dashboard kepada senarai rekod dan seterusnya kepada rekod terperinci (Profil Hartanah 360°) dalam tidak melebihi tiga (3) klik. | Wajib | B-1.08; URS-DSH-07 |
| SRS-DSH-12 | Sistem hendaklah memaparkan timestamp kemas kini data terakhir pada setiap dashboard dan setiap laporan yang dijana daripada dashboard. | Wajib | B-1.12; URS-DSH-09 |
| SRS-DSH-13 | Sistem hendaklah membaca data dashboard daripada replika baca dengan ketinggalan data transaksi tidak melebihi lima belas (15) minit, dan tidak menulis apa-apa rekod induk hartanah. | Wajib | URS-DSH-09 |
| SRS-DSH-14 | Sistem hendaklah membolehkan hasil tapisan dieksport dalam format PDF dan Excel serta disimpan sebagai paparan kegemaran peribadi pengguna. | Wajib | URS-DSH-06; B-1.09 |
| SRS-DSH-15 | Sistem hendaklah menyediakan medan AI prompt yang membolehkan pengguna bertanya dalam bahasa biasa (Bahasa Melayu atau Bahasa Inggeris) dan menjana jawapan dalam bentuk carta, jadual atau nilai angka. | Perlu | URS-DSH-03; B-4.03 |
| SRS-DSH-16 | Sistem hendaklah membolehkan pengguna mengubah suai susun atur dan kandungan widget dashboard melalui prompt bahasa biasa yang sama tanpa bantuan pentadbir sistem, dan susun atur peribadi hendaklah disimpan bagi setiap pengguna. | Perlu | URS-DSH-04 |
| SRS-DSH-17 | Sistem hendaklah menghadkan setiap pertanyaan AI kepada skop RBAC pengguna supaya pengguna tidak memperoleh data di luar bidang kuasanya walaupun bertanya secara eksplisit, dan setiap prompt bersama jawapannya hendaklah direkod dalam audit trail. | Wajib | URS-DSH-05 |
| SRS-DSH-18 | Sistem hendaklah melaksanakan model AI bagi fungsi prompt dalam persekitaran awan persendirian MAIS (VPC) tanpa menghantar sebarang pertanyaan atau data hartanah kepada perkhidmatan AI awam. | Wajib | URS-DSH-10 |
| SRS-DSH-19 | Sistem hendaklah menyediakan portal pengguna dalaman yang membolehkan pegawai MAIS melaksanakan kemasukan data, semakan, kelulusan, carian dan pelaporan rekod tanah mengikut peranan dan bidang tugas masing-masing. | Wajib | B-2.01 |
| SRS-DSH-20 | Sistem hendaklah menyediakan reka bentuk responsif yang boleh digunakan melalui komputer meja, komputer riba, tablet dan telefon pintar tanpa pemasangan aplikasi klien khusus, dan paparan mudah alih hendaklah menyokong sekurang-kurangnya semakan rekod, kelulusan tugasan, paparan dashboard, laporan ringkas dan notifikasi. | Wajib | B-2.02; RFP 9.1.1(b) |
| SRS-DSH-21 | Sistem hendaklah mengesahkan identiti pengguna melalui Single Sign-On MAIS (OIDC) atau kaedah pengesahan lain yang diluluskan MAIS, dengan MFA dikuatkuasakan bagi tindakan kelulusan. | Wajib | B-2.03 |
| SRS-DSH-22 | Sistem hendaklah membolehkan pengguna menyimpan draf transaksi sebelum dihantar untuk semakan atau kelulusan, dan draf tersebut hendaklah boleh dibuka semula, disunting atau dibuang oleh pemiliknya tanpa menjejaskan rekod induk. | Wajib | B-2.04 |
| SRS-DSH-23 | Sistem hendaklah menyediakan inbox tugasan pengguna yang memaparkan tindakan yang perlu dibuat, tarikh akhir dan status tugasan, serta menyampaikan notifikasi berkaitan tugasan, semakan, kelulusan, teguran, tamat tempoh dan isu tertunggak. | Wajib | B-2.05; B-2.06 |
| SRS-DSH-24 | Sistem hendaklah menyediakan fungsi carian dokumen dan rujukan mengikut kategori, nombor lot, mukim, tahun, pemilik dan kata kunci. | Wajib | B-2.07 |
| SRS-DSH-25 | Sistem hendaklah menyediakan bantuan dalam talian yang merangkumi FAQ, panduan langkah demi langkah dan pautan kepada manual pengguna, serta boleh diakses daripada setiap skrin utama. | Wajib | B-2.08 |

**Aliran proses utama.**

1. Pengguna log masuk melalui SSO MAIS dan sistem menentukan peranan serta skop data (daerah/bahagian) pengguna.
2. Sistem memuatkan aras dashboard yang bersesuaian dengan peranan tersebut berserta susun atur peribadi pengguna jika ada.
3. Sistem membaca nilai indikator daripada replika baca dan paparan peta daripada perkhidmatan peta MOD-GIS, kemudian memaparkan timestamp kemas kini data terakhir.
4. Pengguna menggunakan penapis gabungan atau medan AI prompt untuk menyempitkan skop paparan; sistem mengesahkan skop RBAC sebelum melaksanakan pertanyaan.
5. Pengguna mengklik indikator untuk drill-down kepada senarai rekod dan seterusnya kepada Profil Hartanah 360°.
6. Pengguna mengeksport hasil tapisan atau menyimpan paparan sebagai kegemaran; sistem merekod penjanaan dan eksport dalam audit trail.
7. Pengguna beralih ke inbox tugasan untuk mengambil tindakan atau menyambung draf transaksi yang disimpan sebelum ini.

**Entiti data.** Modul ini tidak memiliki entiti induk; ia membaca `lot_tanah`, `hak_milik`, `pemerolehan`, `pajakan_kontrak`, `cukai_tanah`, `pemantauan_tanah`, `aduan_tanah`, `kes_perundangan`, `aset_fizikal` dan `penilaian_hartanah` melalui replika baca. Entiti milik modul: `dsh_definisi_widget` (kod\_widget, jenis\_visual, pertanyaan\_sumber, aras\_dashboard), `dsh_susun_atur_pengguna` (id\_pengguna, kod\_widget, posisi, saiz), `dsh_ambang_indikator` (kod\_indikator, nilai\_hijau, nilai\_kuning, nilai\_merah), `dsh_paparan_kegemaran` (id\_pengguna, nama\_paparan, parameter\_penapis), `dsh_draf_transaksi` (id\_draf, id\_pengguna, modul, muatan\_json, tarikh\_simpan), `dsh_inbox_tugasan` (id\_tugasan, id\_pengguna, modul, rujukan\_rekod, tarikh\_akhir, status) dan `dsh_log_prompt_ai` (id\_pengguna, teks\_prompt, pertanyaan\_dijana, skop\_rbac, cap\_masa).

**Integrasi.**

- MOD-GIS — perkhidmatan peta OGC (WMS/WFS/WMTS) bagi widget peta portfolio dan lapisan status tematik
- MOD-LAP — enjin pertanyaan dan enjin AI prompt yang dikongsi serta pautan kepada laporan penuh
- Modul Pangkalan Data Induk Hartanah — pautan drill-down kepada Profil Hartanah 360°
- Modul Workflow dan Kelulusan serta Modul Notifikasi — sumber item inbox tugasan dan notifikasi
- Modul Pengurusan Pengguna, Peranan dan Capaian serta SSO MAIS (OIDC) — pengesahan identiti dan penentuan skop RBAC
- Modul Audit Trail — perekodan paparan, eksport dan prompt AI
- API pihak ketiga (MBI) — status cukai terkini bagi dashboard cukai, tertakluk kepada kelulusan MAIS

**Kawalan keselamatan.**

- Skop RBAC dikuatkuasakan pada peringkat pertanyaan (row-level security), bukan pada peringkat paparan sahaja
- Setiap prompt AI, jawapan yang dijana dan skop RBAC yang dikenakan direkod dalam audit trail
- Model AI dilaksanakan dalam VPC persendirian MAIS tanpa penghantaran data ke perkhidmatan AI awam
- Eksport PDF/Excel direkod bersama parameter penapis, pengguna dan cap masa; dokumen eksport diberi tanda air identiti pengguna
- Data berklasifikasi SULIT/RAHSIA tidak dipaparkan pada widget ringkasan tanpa kebenaran khusus
- Sesi pengguna dihadkan oleh dasar tamat sesi MAIS dan MFA dikuatkuasakan bagi tindakan kelulusan daripada inbox tugasan

**Laporan yang boleh dijana.** Ringkasan portfolio hartanah eksekutif mengikut daerah, mukim dan kategori tanah; ringkasan status cukai tanah termasuk tunggakan, hampir jatuh tempo dan penalti; ringkasan status pajakan dan sewaan termasuk premis kosong dan kontrak hampir tamat; ringkasan pemantauan tanah dan risk rating mengikut daerah; ringkasan tindakan tertunggak mengikut pegawai bertanggungjawab dan tarikh akhir; eksport hasil tapisan dashboard (PDF/Excel) berserta parameter penapis dan timestamp data.

**Kriteria penerimaan.**

1. Pengguna dengan peranan Eksekutif, Pengurusan dan Operasi masing-masing dipaparkan aras dashboard yang berbeza secara automatik tanpa konfigurasi manual.
2. Kesemua tujuh (7) jenis visualisasi B-1.07 — bar, pie chart, line chart, progress bar, heatmap, peta dan traffic light indicator — dapat ditunjukkan berfungsi pada dashboard semasa UAT.
3. Drill-down daripada mana-mana indikator KPI sampai kepada rekod terperinci dalam tidak melebihi tiga (3) klik.
4. Pegawai daerah yang bertanya melalui AI prompt mengenai daerah lain menerima jawapan terhad kepada skopnya, dan percubaan tersebut kelihatan dalam audit trail.
5. Timestamp kemas kini data terakhir dipaparkan pada setiap dashboard dan ketinggalan data transaksi yang diukur tidak melebihi lima belas (15) minit.
6. Dashboard, inbox tugasan, draf transaksi dan bantuan dalam talian dapat digunakan tanpa skrol mendatar pada paparan desktop, tablet dan telefon pintar.

## 10. Modul Portal dan Sokongan

Bab ini memperincikan tiga modul yang melengkapkan ekosistem SISDATA di luar sempadan pengguna dalaman, iaitu Portal Hartanah Wakaf Selangor sebagai saluran awam berasingan, Tiket Sokongan Sistem sebagai saluran bantuan dalam aplikasi, dan Pangkalan Pengetahuan Berbantu AI sebagai repositori pengetahuan projek yang boleh dirujuk melalui arahan bahasa biasa. Ketiga-tiga modul dihantar dalam Gelombang G5 dan berkongsi tiga prinsip reka bentuk yang sama, iaitu SISDATA kekal sebagai Single Source of Truth bagi semua rekod aset, hartanah dan transaksi, semua pertukaran data melintasi API Gateway tanpa capaian terus ke pangkalan data, dan setiap transaksi direkod dalam audit trail yang lengkap.

### Modul PWS · Portal Hartanah Wakaf Selangor

**Kod:** MOD-PWS · **Gelombang:** G5 (Bulan 5.0–5.5) · **Rujukan:** RFP Seksyen 6.5–6.7; Lampiran H C-1.01–C-1.08

Membangunkan Portal Hartanah Wakaf Selangor sebagai sistem awam yang berasingan sepenuhnya daripada SISDATA bagi kegunaan pewakaf dan penyewa awam, merangkumi permohonan hasrat wakaf, permohonan sewaan/pajakan, pemantauan status permohonan, bayaran secara dalam talian dan paparan Dasar Agihan Manfaat. Portal berintegrasi dua hala dengan SISDATA melalui API Gateway supaya SISDATA kekal sebagai Single Source of Truth bagi rekod aset, hartanah, status sewaan/pajakan dan transaksi kewangan berkaitan.

**Kategori pengguna.**

- Pewakaf awam — individu atau organisasi yang mengemukakan permohonan hasrat wakaf
- Penyewa/pemajak awam — individu atau syarikat yang memohon menyewa atau memajak premis dan tanah MAIS
- Orang awam umum (tanpa akaun) — pelawat yang menyemak maklumat hartanah tersedia, kadar agihan manfaat dan hebahan projek wakaf
- Pentadbir kandungan Bahagian Wakaf MAIS — menguruskan kandungan CMS, hebahan dan soal-jawab portal
- Pegawai pemproses Bahagian Wakaf MAIS — memproses permohonan yang diterima, dalam SISDATA (bukan dalam portal)
- Pentadbir teknikal Bahagian Teknologi Maklumat (BTM) MAIS — menguruskan konfigurasi, tampalan dan pemantauan portal

**Keperluan fungsian**

| Kod | Keperluan | Keutamaan | Sumber |
| --- | --- | --- | --- |
| SRS-PWS-01 | Sistem hendaklah menggunakan nama rasmi "Portal Hartanah Wakaf Selangor" secara konsisten pada semua tajuk halaman, logo, menu, tajuk e-mel keluar, templat notifikasi, resit, dokumen serahan dan nama domain, menggantikan sepenuhnya penggunaan istilah "Portal Wakaf" atau "PWS" sebagai nama paparan awam; singkatan PWS hanya boleh digunakan sebagai kod rujukan dalaman projek. | Wajib | C-1.01 |
| SRS-PWS-02 | Sistem hendaklah dibangunkan sebagai sistem/aplikasi awam yang berasingan daripada SISDATA, ditempatkan pada domain dan pelayan berasingan, dengan kitaran keluaran dan kawalan capaian yang bebas daripada persekitaran dalaman SISDATA. | Wajib | C-1.01; RFP 6.5; URS-PWS-07 |
| SRS-PWS-03 | Sistem hendaklah dibina di atas platform CMS WordPress dengan tema tersuai responsif yang menepati identiti korporat MAIS, menyokong paparan dwi bahasa (Bahasa Melayu dan Bahasa Inggeris) dan mematuhi keperluan kebolehcapaian asas WCAG. | Wajib | URS-PWS-01; A-3.03 |
| SRS-PWS-04 | Sistem hendaklah menyediakan pengurusan kandungan berbantu AI yang membolehkan pegawai MAIS menjana draf artikel, hebahan projek wakaf, soal-jawab dan kandungan promosi melalui arahan (prompt), dengan syarat setiap draf mesti disemak dan diterbitkan secara manual oleh pegawai yang diberi kuasa. | Perlu | URS-PWS-02; B-4.02 |
| SRS-PWS-05 | Sistem hendaklah menyediakan pendaftaran dan pengurusan akaun pengguna awam termasuk pengesahan alamat e-mel dan nombor telefon, penetapan semula kata laluan secara selamat, dasar kata laluan minimum, auto session timeout dan pilihan pengesahan dua faktor bagi akaun pengguna awam. | Wajib | Jurang; A-16.06 |
| SRS-PWS-06 | Sistem hendaklah memaparkan senarai aset/hartanah dan premis yang tersedia untuk disewa atau dipajak beserta gambar, lokasi pada peta, keluasan, kategori guna dan kadar sewaan, serta status projek pembangunan wakaf yang telah diluluskan untuk paparan awam, dengan data diperoleh daripada SISDATA melalui API baca sahaja. | Wajib | C-1.02; URS-PWS-03; A-8.02 |
| SRS-PWS-07 | Sistem hendaklah menyediakan borang dalam talian bagi permohonan hasrat wakaf dan permohonan sewaan/pajakan, lengkap dengan pengesahan medan, muat naik dokumen sokongan, simpanan draf sebelum penghantaran dan pengesahan penghantaran kepada pemohon. | Wajib | C-1.02; URS-PWS-04 |
| SRS-PWS-08 | Sistem hendaklah memaparkan nombor rujukan unik yang dijana oleh SISDATA bagi setiap permohonan yang berjaya dihantar, dan nombor rujukan tersebut hendaklah digunakan sebagai rujukan tunggal merentas portal, e-mel notifikasi dan SISDATA. | Wajib | C-1.03; URS-PWS-04 |
| SRS-PWS-09 | Sistem hendaklah menyediakan papan pemuka akaun yang membolehkan pengguna awam menyemak, mengurus dan memantau status semua permohonan hasrat wakaf dan permohonan sewaan/pajakan, rekod urusan berkaitan serta memuat turun dokumen berkaitan melalui akaun masing-masing. | Wajib | C-1.03 |
| SRS-PWS-10 | Sistem hendaklah menyediakan fungsi bayaran secara dalam talian melalui payment gateway yang diluluskan MAIS bagi urusan sewaan/pajakan termasuk deposit/cagaran, sewa bulanan, bayaran tertunggak dan caj berkaitan, dengan jumlah yang perlu dibayar dijana daripada rekod SISDATA dan bukan dimasukkan sendiri oleh pengguna. | Wajib | C-1.03; A-8.07 |
| SRS-PWS-11 | Sistem hendaklah menyokong saluran bayaran sekurang-kurangnya FPX (perbankan internet individu dan korporat) dan kad kredit/kad debit, serta boleh dikembangkan kepada e-wallet atau saluran lain yang diluluskan MAIS tanpa pengubahsuaian kod sumber aplikasi. | Wajib | C-1.03; A-7.04 |
| SRS-PWS-12 | Sistem hendaklah mengesahkan setiap bayaran melalui mekanisme pengesahan sisi pelayan (callback/webhook atau pertanyaan status) daripada payment gateway, dan status bayaran hanya boleh ditanda berjaya selepas pengesahan tersebut diterima; pengalihan semula (redirect) pelayar sahaja tidak boleh dijadikan asas pengesahan bayaran. | Wajib | C-1.03; A-15.08 |
| SRS-PWS-13 | Sistem hendaklah menjana resit digital bernombor dalam format PDF bagi setiap bayaran yang berjaya, membolehkan resit dimuat turun dari akaun pengguna awam dan menghantar salinan melalui e-mel, serta menyimpan salinan resit ke repositori dokumen digital SISDATA. | Wajib | C-1.03; B-3.01 |
| SRS-PWS-14 | Sistem hendaklah melaksanakan rekonsiliasi bayaran secara berjadual (sekurang-kurangnya harian) antara rekod transaksi payment gateway, rekod transaksi SISDATA dan sistem kewangan/GRP MAIS, serta menjana laporan percanggahan (unmatched transaction) untuk tindakan pegawai kewangan. | Wajib | A-7.03; A-8.07; A-15.05 |
| SRS-PWS-15 | Sistem hendaklah merekod setiap transaksi bayaran dalam SISDATA sebagai rekod rasmi, merangkumi nombor rujukan permohonan, kaedah bayaran, jumlah, mata wang, rujukan gateway, cap masa, status dan nombor resit, dan mengemas kini lejar sewaan/pajakan serta baki tunggakan yang berkaitan. | Wajib | C-1.04; A-8.06; A-15.13 |
| SRS-PWS-16 | Sistem hendaklah memastikan tiada data penuh kad pembayaran atau kelayakan perbankan disimpan, diproses atau direkod di dalam portal; semua pengendalian data kad hendaklah dilaksanakan di persekitaran payment gateway yang mematuhi PCI-DSS dan portal hanya menyimpan token atau rujukan transaksi. | Wajib | Jurang; A-16.07 |
| SRS-PWS-17 | Sistem hendaklah memaparkan fungsi Dasar Agihan Manfaat kepada pengguna awam, iaitu kadar agihan manfaat mengikut kategori hartanah yang merangkumi premis perniagaan, premis kediaman, tanah dan bazar, dengan kiraan dan kadar dibaca daripada SISDATA melalui API baca sahaja. | Wajib | RFP 6.7; A-8.11; URS-PWS-05 |
| SRS-PWS-18 | Sistem hendaklah membezakan dan memaparkan secara jelas perbezaan kadar agihan manfaat bagi hartanah yang diwakafkan berbanding hartanah yang dibeli, bagi setiap kategori hartanah yang dipaparkan. | Wajib | RFP 6.7; A-8.11; URS-PWS-05 |
| SRS-PWS-19 | Sistem hendaklah memaparkan versi dasar, tarikh berkuat kuasa dan cap masa kemas kini terakhir bagi setiap kadar agihan manfaat yang dipaparkan, beserta penafian bahawa kadar adalah tertakluk kepada dasar yang diluluskan oleh MAIS. | Perlu | Jurang; B-1.12 |
| SRS-PWS-20 | Sistem hendaklah memastikan portal tidak menyimpan rekod tuan (master record): semua rekod aset, hartanah, perjanjian, status permohonan yang telah dihantar dan transaksi kewangan kekal dalam SISDATA, manakala portal hanya menyimpan kandungan CMS, akaun pengguna awam dan draf permohonan yang belum dihantar. | Wajib | C-1.04; URS-PWS-06 |
| SRS-PWS-21 | Sistem hendaklah melaksanakan integrasi dua hala dengan SISDATA yang merangkumi sekurang-kurangnya (i) penghantaran maklumat aset/hartanah tersedia daripada SISDATA ke portal untuk paparan awam, (ii) penghantaran permohonan hasrat wakaf dan permohonan sewaan/pajakan daripada portal ke SISDATA untuk diproses oleh pegawai MAIS, dan (iii) kemas kini status permohonan dua hala antara kedua-dua sistem. | Wajib | C-1.05 |
| SRS-PWS-22 | Sistem hendaklah menggunakan kaedah API/web service selamat selaras dengan keperluan integrasi Modul A-15, termasuk REST/JSON di atas HTTPS/TLS, audit trail integrasi, log transaksi inbound/outbound dengan ringkasan payload dan ralat, serta mekanisme retry, queue dan reconciliation bagi transaksi yang gagal. | Wajib | C-1.06; A-15.02; A-15.04; A-15.05 |
| SRS-PWS-23 | Sistem hendaklah memastikan portal tidak mempunyai capaian terus kepada pangkalan data SISDATA; semua pertukaran data hendaklah melintasi API Gateway menggunakan kunci API berskop, token bertempoh, putaran kunci berkala dan IP allowlist. | Wajib | C-1.07; A-15.12; URS-PWS-07 |
| SRS-PWS-24 | Sistem hendaklah melaksanakan kawalan keselamatan aplikasi awam pada setiap borang dan endpoint awam termasuk CAPTCHA, had kadar (rate limiting) per IP dan per akaun, pengesahan input sisi pelayan selaras OWASP Top 10, serta kawalan saiz fail, jenis fail dan imbasan antivirus bagi setiap dokumen yang dimuat naik. | Wajib | C-1.07; A-16.08; B-3.06 |
| SRS-PWS-25 | Sistem hendaklah diperkeras (hardening) pada peringkat CMS, pelayan web dan sistem pengoperasian, ditampal mengikut jadual tampalan yang dipersetujui, serta menjalani imbasan kerentanan berkala dan Vulnerability Assessment/Penetration Test sebelum Go-Live dengan semua finding kritikal dan tinggi ditutup. | Wajib | C-1.07; A-16.14; A-16.15; URS-PWS-07 |
| SRS-PWS-26 | Sistem hendaklah menghantar notifikasi automatik kepada pengguna awam melalui e-mel dan/atau SMS bagi penerimaan permohonan, perubahan status permohonan, permintaan dokumen tambahan, keputusan permohonan, peringatan bayaran dan pengesahan bayaran berjaya. | Perlu | Jurang; A-3.14 |
| SRS-PWS-27 | Sistem hendaklah menyediakan analitik portal yang memaparkan bilangan lawatan, permohonan mengikut jenis dan status, kadar penyempurnaan borang, transaksi bayaran mengikut saluran dan kandungan paling popular, untuk penilaian keberkesanan oleh Bahagian Wakaf. | Perlu | URS-PWS-08; B-4.01 |
| SRS-PWS-28 | Sistem hendaklah diserahkan bersama dokumentasi reka bentuk portal, spesifikasi integrasi dengan SISDATA, manual pentadbir portal dan manual pengguna awam sebagai sebahagian hasil serahan projek. | Wajib | C-1.08; A-20.05 |

**Aliran proses utama.**

1. Pelawat awam memasuki Portal Hartanah Wakaf Selangor, menyemak senarai hartanah dan premis tersedia serta paparan Dasar Agihan Manfaat tanpa perlu akaun.
2. Pelawat mendaftar akaun pengguna awam, mengesahkan alamat e-mel dan nombor telefon, dan log masuk.
3. Pengguna memilih jenis permohonan (hasrat wakaf atau sewaan/pajakan), mengisi borang dalam talian, memuat naik dokumen sokongan dan menyimpan draf sekiranya belum lengkap.
4. Pengguna menghantar permohonan; portal menghantar permohonan ke SISDATA melalui API Gateway dan menerima nombor rujukan unik yang dijana oleh SISDATA.
5. Pegawai Bahagian Wakaf memproses permohonan sepenuhnya dalam SISDATA; setiap perubahan status dihantar semula ke portal melalui API dua hala.
6. Pengguna memantau status permohonan melalui papan pemuka akaun dan menerima notifikasi bagi setiap perubahan status.
7. Bagi permohonan sewaan/pajakan yang diluluskan, SISDATA menjana jumlah yang perlu dibayar (deposit/cagaran dan sewa) dan memaparkannya kepada pengguna di portal.
8. Pengguna memilih saluran bayaran (FPX atau kad kredit/debit) dan diarahkan ke payment gateway untuk melengkapkan bayaran.
9. Payment gateway mengembalikan pengesahan bayaran sisi pelayan; portal menandakan bayaran berjaya hanya selepas pengesahan tersebut diterima.
10. SISDATA merekod transaksi, mengemas kini lejar sewaan/pajakan, menjana resit digital dan menyimpan salinan resit ke repositori dokumen digital.
11. Pengguna memuat turun resit digital daripada akaun masing-masing dan menerima salinan melalui e-mel.
12. Proses rekonsiliasi berjadual membandingkan rekod gateway, rekod SISDATA dan GRP MAIS, dan menjana laporan percanggahan untuk tindakan pegawai kewangan.

**Entiti data.**

- `pws_akaun_awam` — id\_akaun, jenis\_pemohon (individu/organisasi), nama, no\_pengenalan (disulitkan), e-mel, telefon, status\_pengesahan, tarikh\_daftar, tarikh\_log\_masuk\_akhir
- `pws_draf_permohonan` — id\_draf, id\_akaun, jenis\_permohonan, kandungan\_borang (JSON), senarai\_lampiran, tarikh\_kemas\_kini, status\_draf
- `pws_kandungan_cms` — id\_kandungan, jenis (artikel/hebahan/soal-jawab/halaman), tajuk\_bm, tajuk\_bi, kandungan, status\_terbitan, dijana\_ai, id\_penyemak, tarikh\_terbit
- `pws_sesi_bayaran` — id\_sesi, nombor\_rujukan\_permohonan, jumlah, mata\_wang, saluran\_bayaran, rujukan\_gateway, token\_transaksi, status (dimulakan/menunggu/berjaya/gagal/tamat\_tempoh), cap\_masa\_mula, cap\_masa\_pengesahan
- `pws_log_integrasi` — id\_log, arah (inbound/outbound), endpoint, nombor\_rujukan, ringkasan\_payload, kod\_status, bilangan\_cuba\_semula, mesej\_ralat, cap\_masa
- `pws_analitik_lawatan` — id\_rekod, halaman, sumber\_rujukan, bilangan\_lawatan, tempoh\_purata, tarikh
- Rujukan baca sahaja ke SISDATA (bukan disimpan di portal) — `aset_hartanah`, `premis`, `status_sewaan`, `permohonan_wakaf`, `permohonan_sewaan`, `transaksi_bayaran`, `resit`, `dasar_agihan_manfaat`

**Integrasi.**

- SISDATA melalui API Gateway — API dua hala bagi maklumat hartanah tersedia (keluar), penghantaran permohonan (masuk), kemas kini status permohonan (dua hala) dan status pembayaran, selaras C-1.05, C-1.06 dan A-15.13
- Payment gateway yang diluluskan MAIS — pemulaan transaksi, pengesahan callback/webhook, pertanyaan status dan muat turun penyata harian untuk rekonsiliasi
- Sistem Kewangan/GRP MAIS — rekonsiliasi bayaran dan pengeposan hasil, melalui SISDATA (portal tidak berhubung terus dengan GRP)
- Repositori Dokumen Digital (DMS) SISDATA — penyimpanan resit digital dan dokumen sokongan permohonan
- Modul GIS SISDATA — koordinat dan geometri lot untuk paparan peta hartanah tersedia
- Enjin notifikasi berpusat SISDATA — penghantaran e-mel dan SMS kepada pengguna awam
- Modul Pangkalan Pengetahuan Berbantu AI — sumber kandungan soal-jawab awam portal (pilihan)

**Kawalan keselamatan.**

- Domain, pelayan dan segmen rangkaian berasingan daripada persekitaran dalaman SISDATA; tiada laluan rangkaian terus daripada portal ke pangkalan data SISDATA
- Semua pertukaran data melintasi API Gateway dengan kunci API berskop, token bertempoh, putaran kunci berkala dan IP allowlist
- CAPTCHA pada semua borang awam dan had kadar (rate limiting) per IP dan per akaun pada endpoint awam
- Pengesahan input sisi pelayan dan perlindungan terhadap kerentanan OWASP Top 10, dengan Web Application Firewall (WAF) di hadapan portal
- Hardening CMS, pelayan web dan sistem pengoperasian, serta tampalan keselamatan mengikut jadual yang dipersetujui MAIS
- Imbasan kerentanan berkala serta Secure Code Review, Vulnerability Assessment dan Penetration Test sebelum Go-Live
- Penyulitan data dalam penghantaran (TLS 1.3) dan dalam simpanan (AES-256 atau setara) bagi data peribadi pengguna awam
- Tiada data penuh kad pembayaran disimpan dalam portal; pengendalian data kad sepenuhnya di persekitaran payment gateway yang mematuhi PCI-DSS
- Imbasan antivirus, kawalan jenis dan saiz fail bagi setiap dokumen yang dimuat naik sebelum disimpan
- Audit trail bagi pendaftaran akaun, log masuk berjaya dan gagal, penghantaran permohonan, transaksi bayaran dan penerbitan kandungan
- Pematuhan PDPA bagi pengumpulan, penggunaan dan penyimpanan data peribadi pengguna awam, termasuk notis privasi dan perolehan persetujuan

**Laporan yang boleh dijana.** Permohonan hasrat wakaf mengikut tempoh, status dan daerah; permohonan sewaan/pajakan mengikut tempoh, status, kategori hartanah dan lokasi; transaksi bayaran dalam talian mengikut saluran bayaran, status dan tempoh; rekonsiliasi bayaran dan senarai percanggahan (unmatched transaction) dengan GRP MAIS; resit digital yang dijana mengikut tempoh dan jenis urusan; paparan Dasar Agihan Manfaat mengikut kategori hartanah dan versi dasar; analitik portal — lawatan, kandungan popular, kadar penyempurnaan borang dan corak penggunaan; log dan status integrasi portal–SISDATA termasuk transaksi gagal dan tindakan cuba semula; keselamatan portal — imbasan kerentanan, tampalan dilaksanakan dan percubaan log masuk gagal.

**Kriteria penerimaan.**

1. Imbasan menyeluruh ke atas semua antara muka, templat e-mel, resit dan dokumen serahan portal tidak menemui sebarang penggunaan "Portal Wakaf" sebagai nama paparan; semua merujuk "Portal Hartanah Wakaf Selangor".
2. Permohonan hasrat wakaf dan permohonan sewaan/pajakan yang dihantar dari portal muncul dalam SISDATA sebagai kes baharu dengan nombor rujukan yang sama, dan perubahan status yang dibuat dalam SISDATA dipaparkan dalam akaun pengguna awam dalam tempoh yang dipersetujui.
3. Transaksi bayaran ujian melalui FPX dan kad kredit/debit berjaya diselesaikan, status bayaran ditandakan berjaya hanya selepas pengesahan sisi pelayan diterima, resit digital dijana dan rekod transaksi wujud dalam SISDATA dengan nilai yang sepadan.
4. Laporan rekonsiliasi harian berjaya dijana dan sekurang-kurangnya satu transaksi percanggahan ujian dikenal pasti dan disenaraikan untuk tindakan.
5. Paparan Dasar Agihan Manfaat menunjukkan kadar bagi kategori premis perniagaan, premis kediaman, tanah dan bazar, dengan nilai berbeza bagi hartanah diwakafkan berbanding dibeli, dan nilai tersebut sepadan dengan rekod dalam SISDATA.
6. Pemeriksaan pangkalan data portal mengesahkan tiada rekod aset, hartanah, permohonan yang telah dihantar atau transaksi kewangan disimpan di portal, dan ujian penembusan mengesahkan tiada laluan capaian terus daripada portal ke pangkalan data SISDATA.
7. Laporan Vulnerability Assessment dan Penetration Test portal diserahkan dengan semua finding kritikal dan tinggi ditutup, dan ujian menunjukkan CAPTCHA serta had kadar berfungsi pada semua borang awam.
8. Dokumentasi reka bentuk, spesifikasi integrasi, manual pentadbir dan manual pengguna awam diserahkan dan diterima oleh MAIS.

### Modul 3 · Hak Milik Tanah

**Kod:** MOD-HKM · **Gelombang:** G1 (Bulan 3.5–4.0) · **Rujukan:** RFP Seksyen 6.1 (Modul 3), 7.1.1(b); Lampiran H A-5.01–A-5.08

Modul ini mengurus rekod hak milik tanah MAIS secara lengkap — hak milik sementara dan kekal, perubahan pemilik, status waris dan pemilik bersama, sekatan kepentingan dan bebanan, serta lokasi simpanan fizikal dokumen hak milik. Modul memastikan sejarah dan status pemilikan setiap lot boleh disahkan sepenuhnya daripada sistem tanpa perlu merujuk fail fizikal.

**Kategori pengguna.**

- Pegawai Hartanah Bahagian Pengurusan Hartanah MAIS — pendaftaran dan kemas kini hak milik
- Pegawai Hartanah Daerah bagi sembilan (9) daerah Selangor
- Pegawai Perundangan MAIS — sekatan kepentingan, bebanan, kes berkaitan hak milik
- Pegawai Bahagian Baitulmal dan Bahagian Wakaf
- Pegawai Penjaga Peti Besi / Pegawai Rekod — daftar peti besi, pengeluaran dan pemulangan dokumen asal
- Ketua Bahagian sebagai pelulus perubahan hak milik
- Pengurusan tertinggi MAIS dan Juruaudit Dalam — capaian baca

**Keperluan fungsian**

| Kod | Keperluan | Keutamaan | Sumber |
| --- | --- | --- | --- |
| SRS-HKM-01 | Sistem hendaklah menyediakan modul pendaftaran hak milik tanah yang merekodkan nombor hak milik, nombor lot, mukim, daerah, kategori tanah, syarat nyata, keluasan, pemilik dan status semasa bagi setiap hak milik. | Wajib | A-5.01 |
| SRS-HKM-02 | Sistem hendaklah merekodkan jenis hak milik termasuk Geran, Pajakan Kerajaan, Hak Milik Sementara (PM/QT), Permit Menduduki Sementara (TOL) dan Tanah Rizab, beserta tarikh pendaftaran, tarikh tamat tempoh jika berkaitan, pejabat pendaftaran dan dokumen hak milik berkaitan. | Wajib | A-5.01; RFP 7.1.1(b); URS-HKM-01 |
| SRS-HKM-03 | Sistem hendaklah merekodkan lokasi simpanan fizikal bagi setiap dokumen hak milik asal — nombor atau kod peti besi, lokasi peti besi (bangunan, tingkat, bilik), nombor rak, nombor fail, nombor kotak atau pengikat dan catatan lokasi. | Wajib | A-5.02 |
| SRS-HKM-04 | Sistem hendaklah menyediakan daftar peti besi yang menyenaraikan kesemua dokumen asal dalam setiap peti besi beserta pautan kepada rekod hak milik dan lot berkaitan, serta membolehkan daftar dicetak sebagai senarai inventori untuk pemeriksaan dan audit. | Wajib | A-5.02; A-6.02 |
| SRS-HKM-05 | Sistem hendaklah merekodkan status dokumen asal dengan sekurang-kurangnya nilai dalam simpanan, dikeluarkan, dalam proses di Pejabat Tanah, dipegang pihak ketiga, hilang dan diganti, serta menandakan dengan jelas hak milik yang dokumen asalnya tiada dalam simpanan. | Wajib | A-5.02 |
| SRS-HKM-06 | Sistem hendaklah merekodkan sejarah lengkap pengeluaran dan pemulangan dokumen asal — tarikh pengeluaran, pegawai yang mengeluarkan, penerima, tujuan, tarikh dijangka dipulangkan, tarikh pemulangan sebenar, pegawai yang menerima pemulangan dan keadaan dokumen semasa dipulangkan. | Wajib | A-5.02 |
| SRS-HKM-07 | Sistem hendaklah menjana notifikasi dan senarai tindakan bagi dokumen asal yang melepasi tarikh dijangka dipulangkan, kepada pegawai penjaga peti besi dan ketua bahagian berkenaan. | Perlu | A-5.02; Jurang |
| SRS-HKM-08 | Sistem hendaklah menyediakan fungsi pengemaskinian status tanah dengan sekurang-kurangnya nilai aktif, kosong, disewa, dipajak, dalam penyelenggaraan, dalam tindakan undang-undang, dalam proses tukar syarat dan dilupuskan, dengan senarai status boleh dikonfigurasikan MAIS. | Wajib | A-5.03 |
| SRS-HKM-09 | Sistem hendaklah merekodkan sejarah lengkap perubahan status setiap tanah dan hak milik — status sebelum, status selepas, tarikh berkuat kuasa, sebab perubahan, dokumen rujukan dan pegawai pelaksana — dan sejarah tersebut hendaklah tidak boleh dipinda. | Wajib | A-5.04 |
| SRS-HKM-10 | Sistem hendaklah memaparkan kronologi pemilikan setiap lot sebagai garis masa visual yang menunjukkan setiap pertukaran pemilik, tarikh, sebab pertukaran dan dokumen berkaitan, daripada pemilik terawal yang direkodkan sehingga MAIS. | Perlu | URS-HKM-02; A-5.04 |
| SRS-HKM-11 | Sistem hendaklah menyediakan fungsi lampiran dokumen sokongan bagi setiap hak milik — geran, carian rasmi, pelan, surat kelulusan, bil cukai, resit, laporan JPPH dan dokumen mahkamah — dengan metadata dan kawalan versi melalui integrasi DMS. | Wajib | A-5.05 |
| SRS-HKM-12 | Sistem hendaklah merekodkan status waris dan pemilik bersama bagi setiap hak milik termasuk nama, hubungan, pecahan bahagian dan sumber pengesahan, serta menandakan lot yang pemilikannya belum selesai, antara lain yang menunggu pembahagian faraid. | Wajib | URS-HKM-04; RFP 3.2.1(d) |
| SRS-HKM-13 | Sistem hendaklah merekodkan sekatan kepentingan dan bebanan ke atas hak milik — kaveat, gadaian, lien, sekatan kepentingan dan syarat nyata yang menghadkan penggunaan — beserta tarikh, pihak terlibat, nombor rujukan dan status, serta menandakan lot berkenaan pada profil lot dan peta. | Wajib | URS-HKM-05; A-5.01 |
| SRS-HKM-14 | Sistem hendaklah menyokong carian hak milik berdasarkan nombor hak milik, nombor lot, mukim, daerah, jenis tanah, kategori kegunaan, status, keluasan dan kata kunci, dengan gabungan kriteria dan keputusan carian boleh dieksport. | Wajib | A-5.06 |
| SRS-HKM-15 | Sistem hendaklah menyediakan paparan profil tanah 360 darjah bagi setiap hak milik dan lot merangkumi maklumat hak milik, lokasi GIS, cukai, pajakan, dokumen, tindakan, risiko dan sejarah transaksi. | Wajib | A-5.07 |
| SRS-HKM-16 | Sistem hendaklah menyokong pengurusan permohonan hak milik kekal — pendaftaran permohonan, senarai semak dokumen, status kelulusan setiap peringkat, rekod bayaran, penerimaan hak milik baharu dan pendaftaran semula dalam SISDATA dengan pautan kepada hak milik sementara yang digantikan. | Wajib | A-5.08 |
| SRS-HKM-17 | Sistem hendaklah menjana notifikasi automatik bagi hak milik sementara yang hampir tamat tempoh pada 180, 90 dan 30 hari sebelum tarikh tamat, TOL yang perlu diperbaharui, dan lot tanpa hak milik disahkan yang melebihi tempoh sasaran MAIS. | Perlu | URS-HKM-06; A-5.03 |
| SRS-HKM-18 | Sistem hendaklah menerima cadangan perubahan status hak milik daripada integrasi luaran sebagai draf yang menunggu pengesahan pegawai, dan tidak menulis terus ke rekod induk hak milik. | Perlu | URS-HKM-07; A-15.03 |
| SRS-HKM-19 | Sistem hendaklah melaksanakan perubahan hak milik melalui aliran kelulusan dua peringkat dengan pengesahan MFA, dan setiap perubahan direkod berversi beserta justifikasi dan dokumen rujukan. | Wajib | URS-HKM-08; A-4.08 |
| SRS-HKM-20 | Sistem hendaklah menggunakan pewarnaan yang konsisten pada senarai, profil lot, peta dan laporan mengikut jenis hak milik dan kategori guna tanah, dengan legenda warna yang seragam di seluruh sistem. | Baik-ada | URS-HKM-03 |
| SRS-HKM-21 | Sistem hendaklah merekodkan pejabat pendaftaran hak milik, nombor perserahan dan tarikh perserahan bagi setiap urusan pendaftaran atau pindaan di Pejabat Tanah, bagi membolehkan status urusan dijejaki sehingga selesai. | Perlu | A-5.01; A-5.08 |
| SRS-HKM-22 | Sistem hendaklah menyediakan audit trail lengkap bagi setiap transaksi pada rekod hak milik, daftar peti besi dan rekod pengeluaran dokumen, merekodkan nilai asal, nilai baharu, ID pengguna, alamat IP, tarikh, masa dan sebab perubahan. | Wajib | A-4.08; A-5.04 |

**Aliran proses utama.**

1. Pegawai Hartanah mendaftarkan hak milik bagi satu lot dengan memasukkan nombor hak milik, jenis, mukim, daerah, kategori tanah, syarat nyata, keluasan, pemilik dan status semasa.
2. Pegawai memuat naik dokumen sokongan ke DMS dan memautkannya kepada rekod hak milik.
3. Pegawai Penjaga Peti Besi merekodkan penerimaan dokumen asal, menetapkan nombor peti besi, lokasi rak, nombor fail dan status dalam simpanan; rekod dimasukkan ke dalam daftar peti besi.
4. Apabila dokumen asal diperlukan, pegawai memohon pengeluaran; sistem merekodkan tarikh, penerima, tujuan dan tarikh dijangka pulang, serta menukar status kepada dikeluarkan.
5. Apabila dokumen dipulangkan, sistem merekodkan tarikh sebenar, pegawai penerima dan keadaan dokumen; dokumen yang melepasi tarikh jangkaan dinotifikasikan.
6. Perubahan pemilik, status tanah atau sekatan kepentingan dimulakan oleh pegawai pendaftar, dihantar untuk kelulusan dua peringkat dengan MFA, dan hanya berkuat kuasa selepas kelulusan.
7. Cadangan perubahan daripada integrasi luaran masuk sebagai draf dan disenaraikan untuk pengesahan pegawai sebelum dikomit ke rekod induk.
8. Bagi hak milik sementara, sistem memantau tarikh tamat tempoh dan menjana notifikasi 180, 90 dan 30 hari sebelum tamat.
9. Permohonan hak milik kekal didaftarkan, dokumen dilengkapkan mengikut senarai semak, status dijejaki, bayaran direkod, dan hak milik baharu didaftarkan semula serta dipautkan kepada hak milik sementara yang digantikan.
10. Semua transaksi hak milik, daftar peti besi dan pengeluaran dokumen direkodkan dalam audit trail dan boleh dilaporkan.

**Entiti data.**

- `hak_milik` — id\_hak\_milik (PK), id\_lot (FK), no\_hak\_milik, jenis\_hak\_milik, kategori\_tanah, syarat\_nyata, keluasan, id\_mukim (FK), id\_daerah (FK), pemilik\_berdaftar, tarikh\_pendaftaran, tarikh\_tamat\_tempoh, pejabat\_pendaftaran, status\_semasa, id\_hak\_milik\_digantikan, versi\_rekod. Unique constraint pada no\_hak\_milik
- `peti_besi` — id\_peti\_besi (PK), kod\_peti\_besi, lokasi\_bangunan, tingkat, bilik, id\_pegawai\_penjaga, kapasiti, status\_peti\_besi, tarikh\_pemeriksaan\_akhir
- `daftar_peti_besi` — id\_daftar (PK), id\_peti\_besi (FK), id\_hak\_milik (FK), id\_lot (FK), jenis\_dokumen\_asal, no\_fail, lokasi\_rak, no\_kotak\_pengikat, status\_dokumen\_asal, tarikh\_masuk\_simpanan, id\_pegawai\_terima, catatan\_lokasi
- `pengeluaran_dokumen` — id\_pengeluaran (PK), id\_daftar (FK), tarikh\_pengeluaran, id\_pegawai\_keluar, penerima\_nama, penerima\_jawatan, tujuan\_pengeluaran, tarikh\_jangka\_pulang, tarikh\_pulang\_sebenar, id\_pegawai\_terima\_pulang, keadaan\_dokumen\_pulang, status\_pengeluaran
- `sejarah_status_tanah` — id\_sejarah (PK), id\_lot (FK), id\_hak\_milik (FK), status\_sebelum, status\_selepas, tarikh\_berkuat\_kuasa, sebab\_perubahan, id\_dokumen\_rujukan, id\_pegawai, tarikh\_masa\_rekod
- `kronologi_pemilikan` — id\_kronologi (PK), id\_hak\_milik (FK), id\_lot (FK), pemilik\_sebelum, pemilik\_selepas, tarikh\_pertukaran, sebab\_pertukaran, id\_pemerolehan (FK), id\_dokumen\_rujukan, susunan\_kronologi
- `waris_pemilik_bersama` — id\_waris (PK), id\_hak\_milik (FK), nama, hubungan, pecahan\_bahagian\_pembilang, pecahan\_bahagian\_penyebut, sumber\_pengesahan, no\_kes\_faraid, status\_pengesahan
- `sekatan_kepentingan` — id\_sekatan (PK), id\_hak\_milik (FK), jenis\_sekatan, no\_rujukan\_sekatan, pihak\_terlibat, tarikh\_masuk, tarikh\_lupus, status\_sekatan, id\_dokumen\_rujukan, id\_kes\_mahkamah (FK)
- `permohonan_hak_milik_kekal` — id\_permohonan (PK), id\_hak\_milik\_sementara (FK), id\_lot (FK), no\_permohonan, tarikh\_permohonan, pejabat\_tanah, senarai\_semak\_dokumen, peringkat\_semasa, status\_kelulusan, jumlah\_bayaran, tarikh\_bayaran, no\_resit, tarikh\_terima\_hak\_milik\_baharu, id\_hak\_milik\_baharu (FK), tempoh\_proses\_hari
- `urusan_pendaftaran_pt` — id\_urusan (PK), id\_hak\_milik (FK), jenis\_urusan, pejabat\_pendaftaran, no\_perserahan, tarikh\_perserahan, status\_urusan, tarikh\_selesai
- View dan stored procedure utama: `vw_daftar_peti_besi_inventori`, `vw_dokumen_asal_belum_dipulangkan`, `vw_hak_milik_hampir_tamat_tempoh`, `vw_lot_tanpa_hak_milik_disahkan`, `sp_jana_kronologi_pemilikan`, `sp_notifikasi_tamat_tempoh_hak_milik`

**Integrasi.**

- Penyedia integrasi berpusat, Pejabat Tanah Daerah dan e-Tanah — status hak milik, pindah milik, carian rasmi dan status perserahan
- e-Faraid MAIS — maklumat waris dan pecahan bahagian bagi hak milik yang menunggu pembahagian pusaka
- Modul Dokumen Digital — simpanan, metadata dan kawalan versi dokumen hak milik
- Modul GIS Mapping — lapisan hak milik beserta pewarnaan mengikut jenis dan kategori guna tanah
- Modul Pangkalan Data Induk — rekod lot induk, senarai rujukan dan versioning
- Modul Rekod Pemerolehan — pewujudan hak milik baharu hasil kes pemerolehan yang selesai
- Modul Kes Mahkamah — pautan kaveat, perintah mahkamah dan sekatan kepentingan
- Modul Tukar Syarat, Pelupusan dan Ukur — permohonan hak milik kekal dan urusan Pejabat Tanah

**Kawalan keselamatan.**

- Klasifikasi rekod hak milik sebagai sekurang-kurangnya Sulit, dengan capaian terhad kepada peranan yang dibenarkan
- Kelulusan dua peringkat beserta MFA bagi setiap perubahan hak milik, pemilik, sekatan kepentingan dan status tanah
- Pengasingan tugas antara pegawai pendaftar, pegawai penjaga peti besi dan pegawai pelulus
- Kawalan akses khusus bagi daftar peti besi dan fungsi pengeluaran dokumen asal
- Audit trail tidak boleh diubah bagi rekod hak milik, daftar peti besi, pengeluaran dan pemulangan dokumen
- Sejarah status dan kronologi pemilikan bersifat append-only
- Penyulitan dokumen hak milik yang diimbas semasa penyimpanan dan penghantaran
- Draf perubahan daripada integrasi luaran diasingkan daripada rekod induk sehingga disahkan pegawai

**Laporan yang boleh dijana.**

- Senarai hak milik mengikut daerah, mukim, jenis hak milik, kategori tanah dan status
- Hak milik sementara hampir tamat tempoh (180/90/30 hari) dan yang telah tamat
- Lot tanpa hak milik disahkan melebihi tempoh sasaran
- Inventori daftar peti besi mengikut peti besi, rak dan nombor fail
- Dokumen asal yang dikeluarkan dan belum dipulangkan, termasuk yang melepasi tarikh jangkaan
- Sejarah pengeluaran dan pemulangan dokumen mengikut tempoh, pegawai dan tujuan
- Dokumen asal berstatus hilang, diganti atau dipegang pihak ketiga
- Sejarah perubahan status tanah dan kronologi pemilikan bagi satu lot atau hak milik
- Hak milik yang mempunyai sekatan kepentingan atau bebanan aktif
- Status waris dan pemilik bersama, termasuk lot yang menunggu pembahagian faraid
- Status permohonan hak milik kekal mengikut peringkat, pejabat tanah dan tempoh proses
- Audit perubahan rekod hak milik mengikut pengguna dan tempoh

**Kriteria penerimaan.**

1. Rekod hak milik ujian memaparkan kesemua medan A-5.01 — maklumat hak milik, nombor lot, nombor hak milik, mukim, daerah, kategori tanah, syarat nyata, keluasan, pemilik dan status semasa.
2. Bagi satu hak milik ujian, sistem memaparkan nombor peti besi, nombor fail, lokasi rak dan status dokumen asal, dan daftar peti besi boleh dicetak sebagai senarai inventori yang betul.
3. Satu pusingan penuh pengeluaran dan pemulangan dokumen asal direkodkan lengkap, dan dokumen yang melepasi tarikh jangkaan muncul dalam laporan serta notifikasi.
4. Setiap perubahan status tanah menghasilkan satu baris sejarah yang menunjukkan status sebelum, selepas, tarikh berkuat kuasa, sebab dan pegawai, dan baris tersebut terbukti tidak boleh dipinda oleh pengguna biasa.
5. Carian hak milik berjaya dilaksanakan menggunakan kesembilan kriteria A-5.06 secara tunggal dan gabungan, dengan keputusan boleh dieksport.
6. Paparan profil tanah 360 darjah bagi satu lot ujian memaparkan hak milik, lokasi GIS, cukai, pajakan, dokumen, tindakan, risiko dan sejarah transaksi.
7. Satu kes permohonan hak milik kekal dijejaki daripada pendaftaran sehingga penerimaan dan pendaftaran semula hak milik baharu, dengan pautan kepada hak milik sementara yang digantikan kekal boleh dilihat.

### Modul 4 · Rekod Pemerolehan

**Kod:** MOD-PER · **Gelombang:** G1 (Bulan 3.5–4.0) · **Rujukan:** RFP Seksyen 1.3, 3.2.1, 6.1 (Modul 4), 7.1.1(c); Lampiran H A-6.01–A-6.12

Modul ini merekodkan dan memantau setiap pemerolehan hartanah MAIS mengikut sebelas (11) saluran pewujudan hak milik yang dinyatakan dalam Lampiran H A-6.01 hingga A-6.11, dengan setiap saluran mempunyai medan data khusus, senarai dokumen sokongan wajib, peringkat proses dan pegawai bertanggungjawab tersendiri. Modul memastikan setiap kes dijejaki daripada penerimaan dokumen sehingga pendaftaran hak milik dan simpanan dokumen dalam peti besi.

**Kategori pengguna.**

- Pegawai Bahagian Baitulmal MAIS — saluran Daftar Kematian, Perintah ARB, Perintah Mahkamah, Pusaka JKPTGS, Faraid
- Pegawai Bahagian Wakaf — saluran Wakaf
- Pegawai Bahagian Pengurusan Hartanah — saluran Pemberimilikan 5A, Serah Milik, Serahan Hakmilik, Pembelian Terus, Tanah Warta
- Pegawai Perundangan MAIS — semakan perintah mahkamah, perintah ARB dan dokumen pindah milik
- Pegawai Hartanah Daerah bagi sembilan (9) daerah Selangor
- Pegawai Bahagian Kewangan — bayaran notis 5A, harga belian, sumber dana
- Pegawai Penjaga Peti Besi — simpanan dokumen hak milik hasil pemerolehan
- Ketua Bahagian dan pengurusan tertinggi — pelulus dan pemantau

**Keperluan fungsian**

| Kod | Keperluan | Keutamaan | Sumber |
| --- | --- | --- | --- |
| SRS-PER-01 | Sistem hendaklah merekodkan pemerolehan hartanah MAIS mengikut sebelas (11) saluran pewujudan hak milik — Pemberimilikan Tanah (Seksyen 5A), Serah Milik, Faraid, Daftar Kematian, Perintah ARB, Serahan Hakmilik, Perintah Mahkamah (LA/Probet), Pusaka JKPTGS, Wakaf, Pembelian Terus dan Tanah Warta — dengan setiap saluran mempunyai borang, medan khusus, senarai dokumen sokongan wajib dan peringkat proses tersendiri. | Wajib | A-6.01–A-6.11; RFP 1.3 |
| SRS-PER-02 | Sistem hendaklah menyokong proses pemberimilikan tanah di bawah Seksyen 5A meliputi rekod permohonan e-Tanah, keputusan MMKN, notis 5A, bayaran notis 5A, penerimaan hak milik, pendaftaran dalam SISDATA dan simpanan dokumen hak milik. | Wajib | A-6.01 |
| SRS-PER-03 | Sistem hendaklah merekodkan butiran keputusan MMKN bagi setiap kes pemberimilikan — nombor dan tarikh mesyuarat, nombor kertas MMKN, jenis keputusan (lulus, lulus bersyarat, tangguh, tolak), syarat yang dikenakan, keluasan diluluskan dan premium yang ditetapkan. | Wajib | A-6.01 |
| SRS-PER-04 | Sistem hendaklah merekodkan butiran notis 5A — nombor notis, tarikh notis, tarikh terima, jumlah premium, cukai tanah tahun pertama, bayaran ukur, bayaran lain, tarikh akhir bayaran — serta menjana notifikasi kepada Bahagian Kewangan dan pegawai bertanggungjawab sebelum tarikh akhir bayaran. | Wajib | A-6.01 |
| SRS-PER-05 | Sistem hendaklah merekodkan bayaran notis 5A termasuk jumlah dibayar, tarikh, mod bayaran, nombor resit dan baki tertunggak, serta menghalang kes daripada diteruskan ke peringkat penerimaan hak milik selagi bayaran belum disahkan lengkap. | Wajib | A-6.01 |
| SRS-PER-06 | Sistem hendaklah menyokong proses serah milik dan serahan hak milik meliputi penerimaan memo, pendaftaran hak milik, pendaftaran dalam daftar peti besi dan simpanan dokumen hak milik. | Wajib | A-6.02 |
| SRS-PER-07 | Sistem hendaklah menyokong pemerolehan melalui saluran Daftar Kematian meliputi penerimaan maklumat pewaris, pengesahan status harta, pendaftaran rekod dan simpanan dokumen sokongan. | Wajib | A-6.03 |
| SRS-PER-08 | Sistem hendaklah menyokong pemerolehan melalui saluran Perintah ARB meliputi penerimaan perintah, pendaftaran rekod dan pengemaskinian status hak milik. | Wajib | A-6.04 |
| SRS-PER-09 | Sistem hendaklah menyokong pemerolehan melalui saluran Serahan Hakmilik meliputi rekod penyerah hak, dokumen pindah milik, tarikh serahan dan pengesahan pendaftaran di PTG. | Wajib | A-6.05 |
| SRS-PER-10 | Sistem hendaklah menyokong pemerolehan melalui saluran Perintah Mahkamah (Letter of Administration / Probet) meliputi penerimaan perintah, pendaftaran waris dan pengemaskinian rekod hak milik. | Wajib | A-6.06 |
| SRS-PER-11 | Sistem hendaklah menyokong pemerolehan melalui saluran Pusaka JKPTGS meliputi penerimaan sijil pusaka kecil, pendaftaran harta dan simpanan dokumen sokongan. | Wajib | A-6.07 |
| SRS-PER-12 | Sistem hendaklah menyokong pemerolehan melalui saluran Wakaf meliputi penerimaan ikrar wakaf, pengesahan syariah, pendaftaran di PTG dan integrasi dengan Portal Hartanah Wakaf Selangor. | Wajib | A-6.08 |
| SRS-PER-13 | Sistem hendaklah menyokong pemerolehan melalui saluran Faraid meliputi penerimaan rekod pembahagian pusaka, pengesahan maklumat waris dan integrasi dengan sistem e-Faraid MAIS. | Wajib | A-6.09 |
| SRS-PER-14 | Sistem hendaklah menyokong pemerolehan melalui saluran Pembelian Terus meliputi rekod perjanjian jual beli, dokumen pindah milik, bukti pembayaran dan pengesahan pendaftaran PTG. | Wajib | A-6.10 |
| SRS-PER-15 | Sistem hendaklah menyokong pemerolehan melalui saluran Tanah Warta meliputi perekodan jenis dan tujuan pewartaan beserta nombor dan tarikh warta kerajaan negeri. | Wajib | A-6.11 |
| SRS-PER-16 | Sistem hendaklah menguatkuasakan senarai semak dokumen sokongan wajib yang berbeza mengikut saluran, memaparkan status kelengkapan beserta senarai kekurangan, dan menghalang kes daripada dimajukan ke peringkat kelulusan selagi dokumen wajib belum lengkap. | Wajib | A-6.01–A-6.11; URS-PER-04 |
| SRS-PER-17 | Sistem hendaklah merekodkan peringkat proses bagi setiap kes dengan peringkat yang boleh dikonfigurasikan mengikut saluran — sekurang-kurangnya dokumen diterima, semakan, pengesahan, kelulusan, pendaftaran hak milik dan simpanan dokumen — serta menandakan peringkat semasa dengan jelas. | Wajib | URS-PER-02; A-6.01 |
| SRS-PER-18 | Sistem hendaklah merekodkan pegawai bertanggungjawab bagi setiap peringkat setiap kes, beserta tarikh masuk, tarikh keluar, tempoh peringkat dan tempoh sasaran peringkat. | Wajib | RFP 7.1.1(c); URS-PER-02 |
| SRS-PER-19 | Sistem hendaklah memaparkan garis masa proses pemerolehan bagi setiap kes daripada tarikh mula sehingga pendaftaran hak milik, menunjukkan setiap peringkat, pegawai dan tempoh secara kronologi. | Perlu | URS-PER-02 |
| SRS-PER-20 | Sistem hendaklah memautkan setiap kes kepada rekod lot tanah, atau kepada rekod lot dijangka bagi kes yang lotnya belum berdaftar, dan memindahkan pautan kepada rekod lot sebenar apabila lot didaftarkan. | Wajib | URS-PER-04; RFP 7.1.1(c) |
| SRS-PER-21 | Sistem hendaklah mencipta atau mengemas kini rekod hak milik dalam Modul Hak Milik Tanah apabila kes selesai, menutup kes dengan ringkasan tempoh keseluruhan berbanding sasaran, dan merekodkan hak milik baharu dalam daftar peti besi. | Wajib | URS-PER-08; A-6.01; A-6.02 |
| SRS-PER-22 | Sistem hendaklah merekodkan lokasi simpanan fizikal dokumen pemerolehan dan dokumen hak milik hasil pemerolehan — nombor peti besi, nombor fail, lokasi rak, status dokumen asal serta sejarah pengeluaran dan pemulangan. | Wajib | A-6.02; A-5.02 |
| SRS-PER-23 | Sistem hendaklah merekodkan pihak berkaitan bagi setiap kes mengikut peranan yang bersesuaian dengan saluran — pewakaf, si mati, waris, penyerah hak, penjual, pemegang amanah, pegawai mahkamah atau agensi — beserta hubungan, dokumen pengenalan dan status pengesahan. | Wajib | RFP 7.1.1(c); URS-PER-03 |
| SRS-PER-24 | Sistem hendaklah merekodkan nilai pemerolehan bagi setiap kes meliputi nilai pasaran, nilai pemerolehan, premium atau harga belian, sumber dana dan rujukan penilaian JPPH jika berkaitan. | Perlu | RFP 7.1.1(c); URS-PER-03 |
| SRS-PER-25 | Sistem hendaklah menerima permohonan pendaftaran wakaf daripada Portal Hartanah Wakaf Selangor sebagai kes pemerolehan saluran Wakaf dengan status awal permohonan awam menunggu semakan, tanpa menulis terus ke rekod induk hartanah. | Perlu | URS-PER-07; A-6.08; A-15.13 |
| SRS-PER-26 | Sistem hendaklah mengeskalasikan secara automatik kes yang tidak bergerak melebihi tempoh sasaran peringkat melalui Workflow Engine, dengan notifikasi kepada pegawai bertanggungjawab dan ketua bahagian. | Perlu | URS-PER-05 |
| SRS-PER-27 | Sistem hendaklah menjana laporan pemerolehan mengikut saluran, daerah, tempoh dan status bagi membolehkan MAIS memantau kadar pertambahan portfolio hartanah. | Wajib | A-6.12 |
| SRS-PER-28 | Sistem hendaklah memaparkan statistik pemerolehan mengikut saluran, tahun, daerah dan nilai sebagai carta dan peta taburan, dengan identiti visual setiap saluran dikekalkan konsisten di senarai, profil lot, peta dan laporan. | Perlu | URS-PER-06; URS-PER-01 |
| SRS-PER-29 | Sistem hendaklah melaksanakan kelulusan berlapis mengikut saluran dan nilai pemerolehan, dengan pengasingan tugas antara pegawai pendaftar kes dan pegawai pelulus. | Wajib | A-4.08; A-6.12 |
| SRS-PER-30 | Sistem hendaklah menyediakan audit trail lengkap bagi setiap transaksi pada kes pemerolehan — tambah, pinda, padam, sah, lulus, batal dan arkib — beserta nilai asal, nilai baharu, ID pengguna, tarikh, masa dan sebab perubahan. | Wajib | A-4.08; A-16.09 |

**Saluran pemerolehan dan keperluan data**

| Saluran | Medan khusus | Dokumen sokongan wajib | Sumber |
| --- | --- | --- | --- |
| 1. Pemberimilikan Tanah (Seksyen 5A) | no\_permohonan\_etanah, tarikh\_permohonan, pejabat\_tanah\_daerah, no\_mesyuarat\_mmkn, tarikh\_mmkn, no\_kertas\_mmkn, jenis\_keputusan\_mmkn, syarat\_mmkn, keluasan\_diluluskan, no\_notis\_5a, tarikh\_notis\_5a, jumlah\_premium, cukai\_tahun\_pertama, bayaran\_ukur, tarikh\_akhir\_bayaran, jumlah\_dibayar, no\_resit, baki\_tertunggak, tarikh\_terima\_hak\_milik | Salinan permohonan e-Tanah; keputusan/kertas MMKN; notis 5A asal; resit bayaran premium dan notis 5A; pelan pra-perhitungan atau pelan akui; hak milik yang diterima; memo penerimaan; rekod daftar peti besi | A-6.01; RFP 1.3 |
| 2. Serah Milik | no\_memo\_serah\_milik, tarikh\_memo, pihak\_penyerah, sebab\_serah\_milik, no\_hak\_milik\_asal, no\_hak\_milik\_baharu, tarikh\_pendaftaran, kod\_peti\_besi, no\_fail, lokasi\_rak | Memo serah milik; borang serah balik tanah; hak milik asal; hak milik baharu; surat kelulusan Pejabat Tanah; rekod daftar peti besi | A-6.02 |
| 3. Faraid | no\_kes\_faraid, rujukan\_mahkamah\_syariah, tarikh\_keputusan, nama\_si\_mati, tarikh\_kematian, senarai\_waris, pecahan\_bahagian, bahagian\_mais, status\_pengesahan\_waris, rujukan\_e\_faraid | Sijil faraid atau keputusan pembahagian pusaka; sijil kematian; senarai waris disahkan; surat pelepasan hak waris; carian rasmi; borang pindah milik | A-6.09; A-6.02 |
| 4. Daftar Kematian | no\_rujukan\_daftar\_kematian, nama\_si\_mati, tarikh\_kematian, daerah\_kematian, status\_waris, nama\_pewaris\_melepaskan, tarikh\_pelepasan\_hak, status\_pengesahan\_harta, pegawai\_pengesah | Sijil kematian; rekod Daftar Kematian; surat pengesahan tiada waris atau pelepasan hak; carian rasmi; laporan pengesahan status harta; borang pindah milik | A-6.03; RFP 1.3 |
| 5. Perintah ARB | no\_perintah\_arb, tarikh\_perintah, pejabat\_arb, nama\_si\_mati, no\_fail\_arb, jenis\_perintah, tarikh\_terima, status\_hak\_milik\_selepas\_perintah | Perintah ARB asal atau salinan diakui sah; surat iringan ARB; sijil kematian; carian rasmi; borang pindah milik; hak milik dikemas kini | A-6.04; RFP 1.3 |
| 6. Serahan Hakmilik | nama\_penyerah\_hak, tarikh\_serahan, sebab\_serahan, no\_borang\_pindah\_milik, tarikh\_perserahan\_ptg, no\_perserahan\_ptg, status\_pendaftaran\_ptg | Surat serahan hak milik; borang pindah milik (14A); hak milik asal; carian rasmi; resit perserahan PTG; pengesahan pendaftaran PTG | A-6.05; RFP 1.3 |
| 7. Perintah Mahkamah (LA/Probet) | no\_kes\_mahkamah, jenis\_perintah, nama\_mahkamah, tarikh\_perintah, tarikh\_terima, nama\_pentadbir\_harta, senarai\_waris, bahagian\_mais | Perintah mahkamah diakui sah; sijil kematian; senarai harta pusaka; dokumen pengesahan waris; carian rasmi; borang pindah milik | A-6.06; RFP 1.3 |
| 8. Pusaka JKPTGS | no\_sijil\_pusaka\_kecil, tarikh\_sijil, pejabat\_jkptgs, no\_fail\_pusaka, nama\_si\_mati, nilai\_harta\_pusaka, bahagian\_mais | Sijil pusaka kecil JKPTGS; perintah pembahagian pusaka kecil; sijil kematian; senarai harta; carian rasmi | A-6.07; RFP 1.3 |
| 9. Wakaf | nama\_pewakaf, jenis\_wakaf (am/khas/irsod), tujuan\_wakaf\_mawquf\_alaih, tarikh\_ikrar\_akad, tempat\_ikrar, saksi\_ikrar, status\_pengesahan\_syariah, pegawai\_syariah\_pengesah, rujukan\_portal, no\_perserahan\_ptg, nilai\_wakaf | Borang ikrar wakaf (akad); dokumen pengesahan syariah; hak milik asal pewakaf; carian rasmi; borang pindah milik kepada MAIS; laporan penilaian; pengesahan pendaftaran PTG | A-6.08; RFP 1.3; A-15.13 |
| 10. Pembelian Terus | no\_perjanjian\_jual\_beli, tarikh\_perjanjian, nama\_penjual, harga\_belian, deposit\_dibayar, baki\_harga\_belian, tarikh\_penyelesaian, sumber\_dana, rujukan\_kelulusan\_perolehan, rujukan\_penilaian\_jpph | Perjanjian jual beli; bukti pembayaran deposit dan baki; borang pindah milik (14A); laporan penilaian JPPH; kelulusan perolehan MAIS; hak milik asal penjual; pengesahan pendaftaran PTG | A-6.10; RFP 1.3 |
| 11. Tanah Warta | no\_warta\_kerajaan, tarikh\_warta, jenis\_pewartaan, tujuan\_pewartaan, pihak\_berkuasa\_mewartakan, keluasan\_diwartakan, syarat\_pewartaan | Petikan Warta Kerajaan Negeri Selangor; surat kelulusan kerajaan negeri; pelan kawasan diwartakan; carian rasmi; hak milik yang dikeluarkan | A-6.11; RFP 1.3 |

**Aliran proses utama.**

1. Pegawai bahagian mendaftarkan kes pemerolehan baharu dan memilih saluran; sistem memaparkan borang medan khusus dan senarai semak dokumen wajib bagi saluran tersebut.
2. Bagi saluran Wakaf, permohonan daripada Portal Hartanah Wakaf Selangor masuk secara automatik sebagai kes berstatus permohonan awam menunggu semakan.
3. Pegawai memuat naik dokumen sokongan ke DMS; sistem menyemak kelengkapan dan memaparkan status beserta senarai kekurangan.
4. Kes dimajukan melalui peringkat proses yang ditetapkan bagi saluran tersebut, dengan pegawai bertanggungjawab, tarikh masuk dan tarikh keluar setiap peringkat direkodkan.
5. Bagi saluran Pemberimilikan 5A, sistem merekodkan keputusan MMKN, menjana rekod notis 5A, menotifikasikan Bahagian Kewangan sebelum tarikh akhir, dan menyekat kemajuan kes sehingga bayaran disahkan lengkap.
6. Bagi saluran Wakaf, kes melalui peringkat pengesahan syariah sebelum boleh dimajukan ke pendaftaran PTG.
7. Bagi saluran berasaskan pusaka, sistem merekodkan maklumat si mati, waris dan bahagian MAIS, dan mengesahkan maklumat waris melalui integrasi e-Faraid jika berkaitan.
8. Kes yang lengkap dimajukan untuk kelulusan berlapis mengikut saluran dan nilai, dengan pengasingan tugas antara pendaftar kes dan pelulus.
9. Selepas kelulusan, pegawai merekodkan perserahan dan pendaftaran di PTG, dan sistem mencipta atau mengemas kini rekod hak milik.
10. Dokumen hak milik yang diterima direkodkan dalam daftar peti besi beserta kod peti besi, nombor fail, lokasi rak dan status dokumen asal.
11. Sistem menutup kes dengan ringkasan tempoh keseluruhan berbanding sasaran, dan mengemas kini statistik serta laporan.
12. Kes yang tidak bergerak melebihi tempoh sasaran dieskalasikan secara automatik.

**Entiti data.**

- `pemerolehan` — id\_pemerolehan (PK), no\_kes, id\_saluran (FK), id\_lot (FK), id\_lot\_dijangka, tarikh\_mula\_kes, tarikh\_pemerolehan, peringkat\_semasa, status\_pemerolehan, status\_kelengkapan\_dokumen, id\_bahagian\_pemilik\_proses, id\_pegawai\_bertanggungjawab, nilai\_pasaran, nilai\_pemerolehan, sumber\_dana, rujukan\_penilaian\_jpph, tarikh\_selesai, tempoh\_keseluruhan\_hari, tempoh\_sasaran\_hari, id\_hak\_milik\_dijana (FK)
- `saluran_pemerolehan` — id\_saluran (PK), kod\_saluran, nama\_saluran, rujukan\_lampiran\_h, ikon, warna, bahagian\_bertanggungjawab, tempoh\_sasaran\_hari, status\_aktif
- Sub-entiti khusus saluran: `pemerolehan_5a`, `pemerolehan_serah_milik`, `pemerolehan_faraid`, `pemerolehan_daftar_kematian`, `pemerolehan_arb`, `pemerolehan_serahan_hakmilik`, `pemerolehan_perintah_mahkamah`, `pemerolehan_pusaka_jkptgs`, `pemerolehan_wakaf`, `pemerolehan_pembelian`, `pemerolehan_tanah_warta` — setiap satu memegang medan khusus saluran seperti dalam jadual di atas
- `peringkat_pemerolehan` — id\_peringkat (PK), id\_pemerolehan (FK), kod\_peringkat, nama\_peringkat, susunan, id\_pegawai\_bertanggungjawab, tarikh\_masuk, tarikh\_keluar, tempoh\_hari, tempoh\_sasaran\_hari, status\_peringkat, status\_eskalasi
- `senarai_semak_dokumen_saluran` — id\_semak (PK), id\_saluran (FK), jenis\_dokumen, nama\_dokumen, status\_wajib, peringkat\_diperlukan, susunan\_paparan
- `dokumen_pemerolehan` — id\_dokumen (PK), id\_pemerolehan (FK), id\_semak (FK), jenis\_dokumen, rujukan\_dms, no\_versi, tarikh\_dokumen, tarikh\_terima, id\_pegawai\_terima, status\_dokumen, kod\_peti\_besi, no\_fail, lokasi\_rak, status\_dokumen\_asal
- `pihak_berkaitan_pemerolehan` — id\_pihak (PK), id\_pemerolehan (FK), peranan\_pihak, nama, hubungan, no\_dokumen\_pengenalan, status\_pengesahan
- `kelulusan_pemerolehan` — id\_kelulusan (PK), id\_pemerolehan (FK), aras\_kelulusan, id\_pegawai\_pelulus, jawatan\_pelulus, tindakan, tarikh\_masa, justifikasi
- View dan stored procedure utama: `vw_pemerolehan_mengikut_saluran`, `vw_pemerolehan_dokumen_kurang`, `vw_notis_5a_belum_dibayar`, `vw_kes_lewat_tempoh_sasaran`, `sp_jana_hak_milik_dari_pemerolehan`, `sp_semak_kelengkapan_dokumen_saluran`, `sp_eskalasi_kes_pemerolehan`

**Integrasi.**

- e-Tanah, PTG dan Pejabat Tanah Daerah — permohonan pemberimilikan, status perserahan dan pengesahan pendaftaran hak milik
- Portal Hartanah Wakaf Selangor — penerimaan permohonan pendaftaran wakaf dan pengemaskinian status kembali ke portal
- e-Faraid MAIS — pengesahan senarai waris, pecahan bahagian dan nombor kes faraid
- Amanah Raya Berhad — penerimaan dan pengesahan perintah ARB melalui pertukaran fail selamat atau API jika tersedia
- JKPTGS — penerimaan sijil pusaka kecil dan perintah pembahagian pusaka kecil
- Mahkamah Syariah dan Mahkamah Sivil — rujukan perintah LA/Probet melalui Modul Kes Mahkamah
- GRP MAIS — bayaran notis 5A, premium, deposit dan harga belian serta nombor resit
- JPPH — laporan penilaian bagi nilai pasaran dan nilai pemerolehan
- Modul Hak Milik Tanah — pewujudan dan pengemaskinian rekod hak milik serta daftar peti besi
- Modul Dokumen Digital — simpanan dokumen sokongan pemerolehan
- Workflow Engine — peringkat proses, kelulusan berlapis, eskalasi dan notifikasi

**Kawalan keselamatan.**

- Klasifikasi rekod pemerolehan sebagai sekurang-kurangnya Sulit, dengan medan maklumat peribadi si mati, waris, pewakaf dan penjual dikaburkan kepada peranan yang tidak dibenarkan
- Kelulusan berlapis mengikut saluran dan nilai, dengan pengasingan tugas antara pendaftar kes dan pelulus
- Kawalan akses mengikut bahagian dan saluran
- Kawalan kemajuan kes yang menyekat kes daripada dimajukan selagi dokumen wajib belum lengkap atau bayaran notis 5A belum disahkan
- MFA bagi kelulusan akhir dan penjanaan rekod hak milik baharu
- Kes daripada portal awam diasingkan dalam status menunggu semakan
- Audit trail tidak boleh diubah bagi semua transaksi kes, perubahan peringkat, kelulusan dan muat naik dokumen

**Laporan yang boleh dijana.**

- Laporan pemerolehan mengikut saluran, daerah, tempoh dan status
- Kadar pertambahan portfolio hartanah mengikut tahun, saluran dan daerah
- Statistik pemerolehan sebagai carta dan peta taburan
- Kes berstatus dokumen kurang beserta senarai kekurangan mengikut saluran
- Notis 5A belum dibayar dan hampir mencapai tarikh akhir
- Keputusan MMKN mengikut jenis keputusan, tarikh dan daerah
- Kes melebihi tempoh sasaran peringkat dan kes yang dieskalasikan
- Tempoh pemprosesan sebenar berbanding sasaran mengikut saluran dan peringkat
- Kes selesai dan hak milik yang dijana, beserta lokasi simpanan dokumen
- Permohonan wakaf daripada portal awam mengikut status semakan
- Nilai pemerolehan terkumpul mengikut saluran dan sumber dana
- Audit perubahan kes mengikut pengguna, saluran dan tempoh

**Kriteria penerimaan.**

1. Sistem memaparkan kesebelas saluran sebagai pilihan berasingan, dan memilih setiap saluran memaparkan borang medan khusus serta senarai semak dokumen wajib yang berbeza dan betul seperti dalam jadual saluran.
2. Satu kes ujian saluran Pemberimilikan 5A dijejaki daripada permohonan e-Tanah, keputusan MMKN, notis 5A dan bayaran sehingga penerimaan hak milik, pendaftaran dan rekod daftar peti besi; sistem terbukti menyekat kemajuan sebelum bayaran disahkan lengkap.
3. Satu kes ujian bagi setiap sepuluh saluran yang lain berjaya didaftarkan, dimajukan melalui peringkat proses dan ditutup, dengan medan khusus dan dokumen wajib direkodkan sepenuhnya.
4. Kes yang dokumen wajibnya tidak lengkap tidak boleh dimajukan ke peringkat kelulusan, dan senarai kekurangan dipaparkan dengan tepat.
5. Setiap kes ujian yang selesai menghasilkan atau mengemas kini satu rekod hak milik, dan dokumen hak milik tersebut muncul dalam daftar peti besi beserta kod peti besi, nombor fail, lokasi rak dan status dokumen asal.
6. Garis masa proses bagi satu kes memaparkan setiap peringkat, pegawai dan tempoh secara kronologi, dan kes melebihi tempoh sasaran terbukti dieskalasikan automatik dengan notifikasi kepada ketua bahagian.
7. Laporan pemerolehan boleh dijana dan ditapis mengikut saluran, daerah, tempoh dan status, dan jumlah yang dilaporkan sepadan dengan rekod pangkalan data.
8. Satu permohonan wakaf daripada portal awam muncul dalam SISDATA sebagai kes saluran Wakaf berstatus menunggu semakan tanpa sebarang perubahan kepada rekod induk hartanah.

### Modul 6 · Pemantauan Cukai Tanah

**Kod:** MOD-CKI · **Gelombang:** G3 (Bulan 4.0–4.5) · **Rujukan:** RFP Seksyen 6.1 (Modul 6), 10.2, 10.3; Lampiran H A-7.01–A-7.10

Modul ini memantau obligasi cukai tanah, cukai petak, cukai taksiran PBT, premium dan fi bagi seluruh portfolio hartanah MAIS, dengan semakan bil dan pembayaran dilaksanakan melalui integrasi API berpusat yang disediakan penyedia pihak ketiga bagi semua Pejabat Tanah daerah dan PBT di Selangor. Ia turut menyediakan mod sandaran manual berstruktur supaya operasi cukai tidak terhenti apabila API tidak tersedia atau lot berada di luar liputan.

**Kategori pengguna.**

- Bahagian Pengurusan Hartanah — pemilik proses cukai, pengesah rekod bil
- Pegawai Daerah — semakan bil lot dalam daerah, rekod manual, muat naik resit
- Bahagian Kewangan MAIS — pelulus bayaran menurut had nilai, rekonsiliasi GRP
- Pegawai Pelulus Berlapis — Ketua Bahagian dan Pengurusan Atasan
- Pengurusan Atasan — dashboard prestasi bayaran dan risiko kelewatan
- Pentadbir Sistem — konfigurasi parameter cukai, had nilai kelulusan, tetapan integrasi

**Keperluan fungsian**

| Kod | Keperluan | Keutamaan | Sumber |
| --- | --- | --- | --- |
| SRS-CKI-01 | Sistem hendaklah menyediakan pengurusan cukai tanah, cukai petak, cukai taksiran PBT, premium, fi dan bayaran berkaitan aset tanah, dengan setiap obligasi dipautkan kepada rekod lot atau premis dalam Pangkalan Data Induk. | Wajib | A-7.01 |
| SRS-CKI-02 | Sistem hendaklah membolehkan pengguna merekod bil cukai termasuk jenis cukai, pihak pengeluar bil, nombor akaun atau nombor bil, tahun taksiran, amaun pokok, denda, tarikh akhir bayaran dan status bayaran. | Wajib | A-7.02 |
| SRS-CKI-03 | Sistem hendaklah menyediakan semakan bil dan penyediaan jadual bayaran bagi tempoh perancangan yang ditetapkan, termasuk pengelompokan bil mengikut daerah, PBT dan tarikh akhir. | Wajib | A-7.02 |
| SRS-CKI-04 | Sistem hendaklah berintegrasi dengan API berpusat penyedia integrasi pihak ketiga yang diluluskan bagi dua perkhidmatan — semakan bil dan status cukai tanah serta cukai pintu atau taksiran, dan pembayaran terus dengan resit rasmi — meliputi semua PBT Selangor dan Pejabat Tanah mengikut daerah. | Wajib | URS-CKI-01; A-7.10 |
| SRS-CKI-05 | Sistem hendaklah menyemak status bil secara berjadual sekurang-kurangnya sekali sehari bagi semua lot dalam liputan API, dan mengemas kini rekod cukai secara automatik tanpa kemasukan manual bagi lot tersebut. | Wajib | URS-CKI-02; A-7.02 |
| SRS-CKI-06 | Sistem hendaklah menyelenggara daftar liputan integrasi yang menunjukkan bagi setiap PBT dan Pejabat Tanah sama ada perkhidmatan semakan dan bayaran tersedia, dan menandakan setiap lot sebagai dalam atau di luar liputan. | Wajib | URS-CKI-01; URS-CKI-05 |
| SRS-CKI-07 | Sistem hendaklah menyediakan mod sandaran manual berstruktur bagi lot di luar liputan atau apabila API tidak tersedia, membolehkan pegawai merekod bil menggunakan medan yang sama seperti rekod automatik, memuat naik imej bil dan resit, dan menandakan sumber rekod sebagai manual. | Wajib | URS-CKI-08; Jurang |
| SRS-CKI-08 | Sistem hendaklah mengesan ketersediaan API secara automatik, bertukar kepada mod sandaran apabila gangguan dikesan, memaklumkan pegawai bertanggungjawab, dan menyegerakkan semula rekod secara automatik apabila API pulih. | Wajib | URS-CKI-08; Jurang |
| SRS-CKI-09 | Sistem hendaklah mengendalikan konflik data semasa penyegerakan semula dengan memaparkan perbandingan antara rekod manual dan rekod API, memerlukan pengesahan pegawai sebelum rekod digantikan, dan menyimpan kedua-dua versi dalam jejak audit. | Wajib | URS-CKI-08; Jurang |
| SRS-CKI-10 | Sistem hendaklah merekod setiap panggilan API dalam log integrasi dengan masa, status, masa tindak balas, ringkasan payload, kod ralat dan rujukan transaksi, mencuba semula panggilan yang gagal, dan mengeskalasikan kegagalan berterusan kepada Pentadbir Sistem. | Wajib | URS-CKI-06; A-15.04 |
| SRS-CKI-11 | Sistem hendaklah menyokong permohonan bayaran melalui GRP MAIS atau kaedah integrasi lain yang dipersetujui, termasuk penjanaan dokumen permohonan, penghantaran kepada GRP dan penerimaan status pembayaran. | Wajib | A-7.03 |
| SRS-CKI-12 | Sistem hendaklah merekod kaedah bayaran bagi setiap transaksi termasuk EFT, bank draf, FPX, kaunter Pejabat Tanah daerah dan kaunter PBT, dengan medan khusus bagi setiap kaedah — nombor rujukan EFT, nombor dan bank penerbit bank draf, rujukan transaksi FPX, serta nombor resit dan nama kaunter. | Wajib | A-7.04 |
| SRS-CKI-13 | Sistem hendaklah merekod resit bagi setiap kaedah bayaran, sama ada resit elektronik daripada API atau resit yang dimuat naik manual, dan tiada transaksi boleh ditandakan selesai tanpa sekurang-kurangnya satu rekod resit atau bukti bayaran. | Wajib | A-7.04; A-7.05; Jurang |
| SRS-CKI-14 | Sistem hendaklah melaksanakan pembayaran hanya selepas melalui aliran kelulusan dalaman, di mana had nilai menentukan peringkat pegawai pelulus, dan had nilai boleh dikonfigurasi Pentadbir Sistem. | Wajib | URS-CKI-04; A-7.03 |
| SRS-CKI-15 | Sistem hendaklah menyimpan resit automatik ke dalam DMS selepas bayaran berjaya, mengemas kini lejar kewangan cukai, dan menjana rekod penyelarasan dengan GRP MAIS. | Wajib | URS-CKI-04; A-7.05 |
| SRS-CKI-16 | Sistem hendaklah membolehkan lampiran bil, baucar, resit, bukti bayaran dan dokumen kelulusan pada setiap rekod cukai, dengan metadata, pautan kepada rekod lot dan kawalan versi. | Wajib | A-7.05 |
| SRS-CKI-17 | Sistem hendaklah menyediakan peringatan automatik sebelum tarikh akhir bayaran pada sekurang-kurangnya 30, 14 dan 7 hari, dengan tempoh peringatan boleh dikonfigurasi. | Wajib | A-7.06; URS-CKI-05 |
| SRS-CKI-18 | Sistem hendaklah menjana amaran automatik bagi bil tertunggak, denda yang dikenakan, dan lot di luar liputan integrasi yang memerlukan tindakan manual. | Wajib | A-7.06; URS-CKI-05 |
| SRS-CKI-19 | Sistem hendaklah memaparkan garis masa pembayaran cukai bagi setiap lot merentas tahun, menunjukkan setiap bil, tarikh akhir, tarikh bayar, amaun, nombor resit dan denda, dalam bentuk garis masa visual dan jadual boleh dieksport. | Perlu | URS-CKI-03 |
| SRS-CKI-20 | Sistem hendaklah menyokong rekonsiliasi bayaran cukai berdasarkan rekod sistem, resit dan dokumen kewangan, dan menjana senarai sisihan bagi bil dibayar tanpa resit, resit tanpa bil, dan perbezaan amaun antara bil, resit dan rekod GRP. | Wajib | A-7.08 |
| SRS-CKI-21 | Sistem hendaklah menyediakan dashboard prestasi bayaran cukai yang memaparkan jumlah bil, telah dibayar, tertunggak, denda terkumpul dan risiko kelewatan, boleh ditapis mengikut daerah, mukim, PBT, jenis cukai dan tempoh. | Wajib | A-7.09; URS-CKI-07 |
| SRS-CKI-22 | Sistem hendaklah memaparkan ringkasan kedudukan cukai portfolio mengikut daerah, PBT, jenis cukai dan umur tunggakan, serta menjana anggaran obligasi cukai tahunan bagi perancangan bajet MAIS. | Perlu | URS-CKI-07; A-7.09 |
| SRS-CKI-23 | Sistem hendaklah menyediakan laporan cukai belum bayar, telah bayar, tertunggak, penalti, bayaran mengikut daerah atau mukim dan trend bayaran tahunan, dengan eksport dalam PDF dan Excel. | Wajib | A-7.07 |
| SRS-CKI-24 | Sistem hendaklah menyokong integrasi dengan PTG, Pejabat Tanah Daerah, PBT, LHDN atau sistem berkaitan lain tertakluk kepada kebenaran pemilik sistem dan ketersediaan API, dan integrasi yang tidak tersedia dikendalikan melalui mod sandaran manual berstruktur. | Wajib | A-7.10; RFP 10.3 |

**Aliran proses utama.**

1. Daftar liputan integrasi dikonfigurasi bagi setiap PBT dan Pejabat Tanah daerah, dan setiap lot ditanda sebagai dalam atau di luar liputan.
2. Proses berjadual harian memanggil API berpusat bagi semua lot dalam liputan untuk menyemak bil dan status, dan mengemas kini rekod cukai automatik.
3. Bagi lot di luar liputan, sistem menjana senarai tugasan rekod manual kepada pegawai daerah.
4. Jadual bayaran disediakan dan disemak, dan peringatan 30, 14 dan 7 hari dijana sebelum tarikh akhir.
5. Permohonan bayaran dihantar melalui aliran kelulusan dalaman menurut had nilai; bagi bayaran melalui GRP, dokumen permohonan dijana dan dihantar.
6. Bayaran dilaksanakan menurut kaedah yang dipilih — terus melalui API atau FPX, atau melalui EFT, bank draf dan bayaran kaunter.
7. Resit diterima automatik atau dimuat naik manual, disimpan ke DMS, lejar cukai dikemas kini dan rekod penyelarasan GRP dijana.
8. Apabila API terganggu, sistem bertukar ke mod sandaran, memaklumkan pegawai, dan selepas API pulih melaksanakan penyegerakan semula dengan penyelesaian konflik.
9. Rekonsiliasi dijalankan antara rekod sistem, resit dan GRP MAIS, dan senarai sisihan dikeluarkan.
10. Dashboard prestasi bayaran dan anggaran obligasi tahunan dikemas kini untuk rujukan pengurusan.

**Entiti data.**

- `obligasi_cukai` — id\_obligasi, id\_lot, id\_premis, jenis\_cukai, pihak\_pengeluar, kod\_pbt, kod\_ptd, no\_akaun\_cukai, status\_liputan\_api
- `bil_cukai` — id\_bil, id\_obligasi, tahun\_taksiran, no\_bil, amaun\_pokok, amaun\_denda, tarikh\_bil, tarikh\_akhir, status\_bayaran, sumber\_rekod, id\_dokumen\_bil
- `transaksi_bayaran_cukai` — id\_transaksi, id\_bil, kaedah\_bayaran, amaun, tarikh\_bayaran, no\_rujukan\_eft, no\_bank\_draf, bank\_penerbit, rujukan\_fpx, nama\_kaunter, no\_resit, id\_dokumen\_resit, status\_transaksi, id\_kelulusan
- `kelulusan_bayaran` — id\_kelulusan, id\_bil, amaun, peringkat\_pelulus, id\_pegawai\_pelulus, tarikh\_kelulusan, justifikasi, status
- `daftar_liputan_integrasi` — id\_liputan, kod\_pbt, kod\_ptd, nama\_agensi, perkhidmatan\_semakan, perkhidmatan\_bayaran, tarikh\_kemas\_kini
- `log_integrasi_cukai` — id\_log, jenis\_panggilan, id\_obligasi, masa\_panggilan, masa\_tindak\_balas, status\_http, kod\_ralat, ringkasan\_payload, rujukan\_transaksi, bilangan\_cuba\_semula
- `mod_operasi_integrasi` — id\_rekod, kod\_agensi, mod, tarikh\_mula, tarikh\_tamat, sebab, id\_pegawai\_maklum
- `konflik_penyegerakan` — id\_konflik, id\_bil, nilai\_manual, nilai\_api, medan\_berkonflik, status\_resolusi, id\_pegawai\_pengesah, tarikh\_resolusi
- `penyelarasan_grp_cukai` — id\_penyelarasan, id\_transaksi, rujukan\_grp, amaun\_sistem, amaun\_grp, status\_padanan, catatan\_sisihan

**Integrasi.** API berpusat penyedia integrasi pihak ketiga bagi semakan, pembayaran dan resit rasmi; GRP MAIS bagi permohonan bayaran, baucar, jurnal dan penyelarasan; payment gateway bagi saluran FPX yang tidak disokong API berpusat; PTG, e-Tanah, Pejabat Tanah Daerah, PBT dan LHDN tertakluk kepada kebenaran pemilik sistem; DMS bagi bil, baucar, resit dan dokumen kelulusan; Modul Workflow bagi kelulusan bayaran; Modul Notifikasi; Modul GIS bagi lapisan status cukai; Modul Pemantauan Tanah bagi tunggakan sebagai kategori risiko.

**Kawalan keselamatan.**

- Kelulusan berlapis bagi setiap pembayaran menurut had nilai, dengan pengasingan tugas antara penyedia permohonan dan pelulus
- Kunci dan kredensial API disimpan dalam pengurusan kunci terkawal dan bukan dalam kod sumber atau fail konfigurasi teks jelas
- Semua panggilan API melalui TLS dengan autentikasi selamat dan senarai putih alamat IP di peringkat API Gateway
- Audit trail penuh bagi setiap transaksi bayaran, kelulusan, pertukaran mod operasi dan resolusi konflik
- Rekod resit dan bil tidak boleh dipadam; pembetulan melalui rekod pelarasan berasingan dengan justifikasi
- Capaian rekod cukai ditapis mengikut daerah bagi pegawai daerah
- Log integrasi tidak merekod kredensial atau maklumat kewangan penuh dalam ringkasan payload

**Laporan yang boleh dijana.** Cukai belum bayar mengikut daerah, mukim, PBT dan jenis; cukai telah bayar beserta kaedah dan rujukan resit; cukai tertunggak dengan umur tunggakan dan denda; penalti mengikut punca kelewatan; bayaran mengikut daerah dan trend tahunan; anggaran obligasi tahunan bagi bajet; rekonsiliasi antara sistem, resit dan GRP beserta senarai sisihan; lot di luar liputan integrasi; prestasi integrasi API termasuk kadar kegagalan dan tempoh mod sandaran; garis masa pembayaran bagi lot terpilih.

**Kriteria penerimaan.**

1. Proses berjadual harian berjaya menyemak dan mengemas kini status bil bagi sekurang-kurangnya 100 lot ujian melalui API berpusat tanpa kemasukan manual, dan rekod menunjukkan sumber sebagai API.
2. Kelima-lima kaedah bayaran — EFT, bank draf, FPX, kaunter Pejabat Tanah daerah dan kaunter PBT — boleh direkodkan dengan medan khusus masing-masing, dan setiap satu memerlukan rekod resit atau bukti bayaran sebelum transaksi boleh ditandakan selesai.
3. Apabila API dimatikan dalam ujian, sistem bertukar automatik ke mod sandaran dalam tempoh yang dipersetujui, memaklumkan pegawai, dan membenarkan rekod bil serta resit dimasukkan manual.
4. Selepas API dipulihkan, penyegerakan semula automatik berjalan, konflik antara rekod manual dan API dipaparkan untuk pengesahan pegawai, dan kedua-dua versi kekal dalam jejak audit.
5. Bayaran melebihi had nilai yang dikonfigurasi tidak boleh dilaksanakan tanpa kelulusan peringkat yang betul, dan percubaan direkod dalam audit trail.
6. Rekonsiliasi terhadap set data ujian menjana senarai sisihan yang tepat bagi tiga kes: bil dibayar tanpa resit, resit tanpa bil, dan perbezaan amaun antara sistem dan GRP MAIS.
7. Peringatan tarikh akhir dijana tepat pada 30, 14 dan 7 hari bagi set data ujian, dan amaran denda dijana apabila tarikh akhir dilepasi.

### Modul 7 · Pemantauan Tanah

**Kod:** MOD-PTN · **Gelombang:** G3 (Bulan 4.0–4.5) · **Rujukan:** RFP Seksyen 6.1 (Modul 7), 8.1; Lampiran H A-10.01–A-10.10

Modul ini menjadikan pemantauan fizikal tanah MAIS satu proses yang berjadual, berstruktur dan berbukti, dengan setiap lawatan menggunakan senarai semak yang boleh dikonfigurasi, gambar bergeotag dan laporan lawatan yang dijana automatik. Ia turut mengira dan memaparkan tahap risiko setiap aset secara visual dengan kod warna hijau, kuning dan merah supaya pencerobohan, tanah terbiar dan kelewatan tindakan dikesan awal.

**Kategori pengguna.**

- Pegawai Pemantauan Lapangan — pelaksana lawatan, pengguna aplikasi mudah alih
- Pegawai Daerah — perancangan jadual, pengesahan laporan lawatan daerah
- Bahagian Pengurusan Hartanah — pemilik proses, penetapan keutamaan risiko
- Bahagian Penguatkuasaan — penerima penemuan kritikal, pelaksana tindakan pembetulan
- Pengurusan Atasan — dashboard risiko aset tanah
- Pentadbir Sistem — konfigurasi senarai semak, ambang warna status, kekerapan lawatan

**Keperluan fungsian**

| Kod | Keperluan | Keutamaan | Sumber |
| --- | --- | --- | --- |
| SRS-PTN-01 | Sistem hendaklah menyediakan perancangan pemantauan tanah yang merekod jadual, pegawai bertanggungjawab, lot terlibat, objektif pemantauan dan status pelaksanaan bagi setiap tugasan. | Wajib | A-10.01 |
| SRS-PTN-02 | Sistem hendaklah menjana jadual pemantauan automatik berdasarkan keutamaan risiko lot termasuk nilai aset, sejarah isu dan tempoh sejak lawatan terakhir, dengan kekerapan boleh dikonfigurasi mengikut kategori lot. | Perlu | URS-PTN-01; A-10.01 |
| SRS-PTN-03 | Sistem hendaklah membolehkan pegawai merekod hasil pemantauan termasuk keadaan tapak, isu pencerobohan, isu penyelenggaraan, gambar tapak, koordinat lokasi dan cadangan tindakan. | Wajib | A-10.02 |
| SRS-PTN-04 | Sistem hendaklah menyediakan senarai semak pemeriksaan berstruktur yang boleh dikonfigurasi, merangkumi sekurang-kurangnya keadaan fizikal tapak, tanda pencerobohan, penggunaan semasa berbanding kategori guna tanah, keadaan aset di atas lot, akses dan sempadan, dengan setiap item mempunyai status baik, isu atau kritikal beserta ruang catatan. | Wajib | URS-PTN-03; A-10.02 |
| SRS-PTN-05 | Sistem hendaklah menyediakan aplikasi mudah alih berasaskan PWA yang berfungsi luar talian, membolehkan pegawai lapangan mencapai senarai tugasan, mengisi borang pemeriksaan, mengambil gambar dan merekod koordinat GPS, dengan penyegerakan automatik apabila sambungan pulih. | Wajib | URS-PTN-02 |
| SRS-PTN-06 | Sistem hendaklah menyediakan muat naik gambar tapak dan tagging gambar kepada rekod tanah serta lapisan GIS berkaitan. | Wajib | A-10.07 |
| SRS-PTN-07 | Sistem hendaklah merekod geotag automatik bagi setiap gambar meliputi koordinat, tarikh, masa dan arah kamera, mengesahkan sama ada koordinat berada dalam lingkungan sempadan lot yang dilawat, dan menandakan gambar di luar lingkungan untuk semakan penyelia. | Wajib | URS-PTN-04; A-10.07 |
| SRS-PTN-08 | Sistem hendaklah menyokong pengemaskinian maklumat tanah dalam Pangkalan Data Induk selepas pemantauan, dengan perubahan direkod sebagai cadangan yang memerlukan pengesahan pegawai berkuasa sebelum rekod induk dipinda. | Wajib | A-10.03 |
| SRS-PTN-09 | Sistem hendaklah menjana laporan pemantauan automatik daripada borang dan gambar, dalam format piawai merangkumi peta lokasi, penemuan, gambar bertanda dan syor tindakan, untuk pengesahan pegawai dan simpanan dalam DMS. | Wajib | A-10.03; URS-PTN-05 |
| SRS-PTN-10 | Sistem hendaklah memastikan laporan lawatan yang telah disahkan tidak boleh diubah, dan sebarang pembetulan dilaksanakan melalui laporan tambahan yang dipautkan kepada laporan asal. | Wajib | URS-PTN-05; A-10.03 |
| SRS-PTN-11 | Sistem hendaklah menyediakan risk rating bagi setiap tanah berdasarkan kategori risiko termasuk pencerobohan, tunggakan cukai, pertikaian, dokumen tidak lengkap, tamat tempoh kontrak dan potensi hasil, dengan pemberat setiap kategori boleh dikonfigurasi. | Wajib | A-10.04 |
| SRS-PTN-12 | Sistem hendaklah mengira skor risiko setiap lot secara automatik dan berjadual dengan mengambil data daripada Modul Cukai, Modul Pajakan, Modul Kes Mahkamah, DMS dan penemuan lawatan. | Wajib | A-10.04; Jurang |
| SRS-PTN-13 | Sistem hendaklah membolehkan tindakan pembetulan diwujudkan daripada setiap isu pemantauan, dengan pegawai bertanggungjawab, tarikh sasaran, langkah tindakan, status kemajuan dan pengesahan penyelesaian, dipantau sehingga isu ditutup. | Wajib | A-10.05 |
| SRS-PTN-14 | Sistem hendaklah menyediakan paparan status tindakan dan tahap risiko secara kod warna — hijau bagi keadaan normal atau tindakan dalam tempoh, kuning bagi keadaan memerlukan perhatian atau hampir melepasi tarikh akhir, dan merah bagi keadaan kritikal atau telah melepasi tarikh akhir. | Wajib | A-10.06 |
| SRS-PTN-15 | Sistem hendaklah membenarkan ambang bagi setiap kod warna dikonfigurasi Pentadbir Sistem mengikut kategori risiko dan tempoh kelewatan tanpa perubahan kod. | Wajib | A-10.06; Jurang |
| SRS-PTN-16 | Sistem hendaklah memaparkan kod warna status secara konsisten pada senarai lot, kad rekod lot, lapisan risiko GIS dan dashboard risiko, serta menyediakan legenda warna dan label teks bagi memenuhi keperluan kebolehcapaian. | Wajib | A-10.06; RFP 9.4 |
| SRS-PTN-17 | Sistem hendaklah mencetuskan tindakan automatik apabila penemuan kritikal direkod — pembukaan rekod isu, notifikasi kepada Bahagian Penguatkuasaan dan Bahagian Pengurusan Hartanah, dan penandaan lot sebagai merah pada peta risiko. | Wajib | URS-PTN-06; A-10.05 |
| SRS-PTN-18 | Sistem hendaklah membolehkan isu pemantauan dieskalasikan sebagai aduan dalam Modul Aduan atau kes dalam Modul Kes Mahkamah, dengan pemindahan maklumat lot, penemuan, gambar dan laporan secara automatik tanpa kemasukan semula data. | Perlu | URS-PTN-06; A-11.05 |
| SRS-PTN-19 | Sistem hendaklah menyediakan notifikasi automatik bagi tindakan pembetulan yang hampir atau telah melepasi tarikh akhir, kepada pegawai bertanggungjawab dan penyelia, dengan tempoh boleh dikonfigurasi. | Wajib | A-10.09 |
| SRS-PTN-20 | Sistem hendaklah menyokong perbandingan lawatan dengan memaparkan gambar dan status item senarai semak lawatan semasa bersebelahan lawatan terdahulu bagi lot yang sama. | Perlu | URS-PTN-07 |
| SRS-PTN-21 | Sistem hendaklah merekod jadual dan keperluan penyelenggaraan yang dikenal pasti semasa pemantauan, dan memautkan rekod tersebut kepada Modul Inventori Aset Fizikal. | Perlu | A-10.02; A-10.05 |
| SRS-PTN-22 | Sistem hendaklah menyediakan laporan isu pemantauan mengikut lokasi, kategori risiko, status tindakan, pegawai bertanggungjawab dan tempoh penyelesaian. | Wajib | A-10.08 |
| SRS-PTN-23 | Sistem hendaklah melaporkan prestasi pemantauan termasuk peratusan lot dilawat mengikut jadual, senarai lawatan tertunggak, purata tempoh antara lawatan dan taburan penemuan mengikut kategori. | Perlu | URS-PTN-08; A-10.08 |
| SRS-PTN-24 | Sistem hendaklah menyediakan dashboard risiko aset tanah yang memaparkan taburan lot mengikut kod warna, senarai lot berisiko tinggi, bilangan isu terbuka, tindakan tertunggak dan trend risiko merentas tempoh. | Wajib | A-10.10 |

**Aliran proses utama.**

1. Bahagian Pengurusan Hartanah menetapkan keutamaan risiko dan kekerapan lawatan mengikut kategori lot; sistem menjana jadual pemantauan tahunan automatik.
2. Tugasan lawatan diagihkan kepada pegawai lapangan mengikut daerah dan dimuat turun ke aplikasi mudah alih untuk kegunaan luar talian.
3. Pegawai melaksanakan lawatan, mengisi senarai semak, mengambil gambar bergeotag dan merekod koordinat serta cadangan tindakan.
4. Data lawatan disegerakkan apabila sambungan pulih, dan sistem mengesahkan koordinat gambar berada dalam sempadan lot.
5. Laporan lawatan dijana automatik, disemak dan disahkan pegawai pengesah, dan dikunci daripada pindaan.
6. Penemuan berstatus isu atau kritikal membuka rekod isu; penemuan kritikal mencetuskan notifikasi dan penandaan lot merah.
7. Tindakan pembetulan diwujudkan bagi setiap isu dengan pegawai bertanggungjawab dan tarikh sasaran, dipantau melalui kod warna.
8. Skor risiko dikira semula berjadual dengan data daripada modul cukai, pajakan, kes mahkamah dan DMS.
9. Notifikasi dijana bagi tindakan hampir atau telah melepasi tarikh akhir, dan eskalasi dilaksanakan.
10. Dashboard risiko dan laporan prestasi pemantauan dikemas kini untuk rujukan pengurusan.

**Entiti data.**

- `jadual_pemantauan` — id\_jadual, id\_lot, tahun, kekerapan, tarikh\_sasaran, id\_pegawai\_bertugas, objektif, keutamaan\_risiko, status\_pelaksanaan
- `rekod_lawatan` — id\_lawatan, id\_jadual, id\_lot, tarikh\_lawatan, masa\_mula, masa\_tamat, id\_pegawai, koordinat\_lawatan, keadaan\_tapak, ringkasan\_penemuan, status\_lawatan, status\_pengesahan, id\_pegawai\_pengesah
- `item_senarai_semak` — id\_item, id\_lawatan, kod\_item, kategori\_item, status\_item, catatan, id\_templat\_senarai\_semak
- `templat_senarai_semak` — id\_templat, nama, kategori\_lot, senarai\_item, versi, status\_aktif
- `gambar_lawatan` — id\_gambar, id\_lawatan, laluan\_simpanan, koordinat\_gambar, tarikh\_masa, arah\_kamera, status\_pengesahan\_lingkungan, tag\_rekod\_tanah, tag\_lapisan\_gis
- `isu_pemantauan` — id\_isu, id\_lawatan, id\_lot, kategori\_isu, tahap\_keterukan, keterangan, tarikh\_dibuka, status\_isu, tarikh\_ditutup, id\_aduan\_berkaitan, id\_kes\_berkaitan
- `tindakan_pembetulan` — id\_tindakan, id\_isu, langkah\_tindakan, id\_pegawai\_bertanggungjawab, tarikh\_sasaran, tarikh\_siap, status\_kemajuan, kod\_warna\_status, justifikasi\_lanjutan
- `risk_rating_lot` — id\_rating, id\_lot, tarikh\_kiraan, skor\_pencerobohan, skor\_tunggakan\_cukai, skor\_pertikaian, skor\_dokumen, skor\_tamat\_kontrak, skor\_potensi\_hasil, skor\_keseluruhan, kod\_warna
- `konfigurasi_ambang_warna` — id\_konfigurasi, kategori\_risiko, ambang\_hijau, ambang\_kuning, ambang\_merah, tempoh\_kelewatan\_kuning, tempoh\_kelewatan\_merah, tarikh\_berkuat\_kuasa
- `laporan_lawatan` — id\_laporan, id\_lawatan, format, id\_dokumen\_dms, tarikh\_jana, tarikh\_sah, status\_kunci, id\_laporan\_asal

**Integrasi.** Modul GIS bagi lapisan pemantauan, lapisan risiko dan pengesahan koordinat terhadap sempadan polygon; Modul Aduan bagi penukaran isu kepada aduan; Modul Cukai bagi status tunggakan sebagai input risiko; Modul Pajakan bagi status kontrak; Modul Kes Mahkamah bagi status pertikaian; Modul Inventori Aset bagi rekod aset dan jadual penyelenggaraan; DMS bagi laporan, gambar dan dokumen; Modul Workflow bagi pengesahan laporan dan eskalasi; Modul Notifikasi.

**Kawalan keselamatan.**

- Capaian rekod pemantauan ditapis mengikut daerah; pegawai lapangan hanya melihat tugasan yang diumpukkan kepadanya
- Gambar lawatan disimpan dalam storan objek bersulit dengan capaian melalui URL bertempoh terhad
- Laporan lawatan yang disahkan dikunci daripada pindaan; pembetulan hanya melalui laporan tambahan yang dipautkan
- Data lawatan pada peranti mudah alih dalam mod luar talian disulitkan dan dipadam automatik selepas penyegerakan berjaya
- Geotag dan cap masa gambar tidak boleh diubah; gambar yang dimuat naik daripada galeri ditanda berasingan daripada gambar yang diambil dalam aplikasi
- Cadangan kemas kini rekod tanah daripada lawatan tidak boleh mengubah Pangkalan Data Induk tanpa kelulusan pegawai berkuasa
- Audit trail penuh bagi penciptaan lawatan, penyegerakan, pengesahan laporan, perubahan skor risiko dan perubahan ambang warna

**Laporan yang boleh dijana.** Laporan lawatan individu beserta peta lokasi, penemuan, gambar dan syor; isu pemantauan mengikut lokasi, kategori risiko, status tindakan dan tempoh penyelesaian; prestasi pemantauan termasuk peratusan lot dilawat dan lawatan tertunggak; tindakan pembetulan tertunggak mengikut kod warna; risk rating portfolio mengikut daerah dan kategori; penemuan pencerobohan mengikut daerah dan tempoh; perbandingan keadaan tapak antara lawatan berturutan; keperluan penyelenggaraan yang dikenal pasti.

**Kriteria penerimaan.**

1. Jadual pemantauan tahunan dijana automatik bagi set data ujian dengan kekerapan yang betul mengikut kategori risiko lot.
2. Satu lawatan lengkap boleh dilaksanakan sepenuhnya dalam mod luar talian termasuk senarai semak, gambar dan koordinat, dan data disegerakkan tanpa kehilangan rekod apabila talian dipulihkan.
3. Gambar yang diambil di luar sempadan polygon lot dikesan dan ditanda untuk semakan penyelia, dan gambar dalam sempadan diterima tanpa penandaan.
4. Laporan lawatan dijana automatik daripada borang dan gambar, dan selepas disahkan tidak boleh diubah melalui mana-mana antara muka sistem.
5. Kod warna hijau, kuning dan merah dipaparkan konsisten pada senarai lot, kad rekod, peta GIS dan dashboard risiko, dan perubahan ambang dicerminkan pada kesemua paparan tanpa penempatan semula kod.
6. Penemuan kritikal mencetuskan pembukaan rekod isu, notifikasi kepada Bahagian Penguatkuasaan dan penandaan merah pada peta risiko dalam tempoh yang dipersetujui.
7. Skor risiko dikira dengan betul bagi kesemua enam kategori berdasarkan data ujian, dan hasil kiraan sepadan dengan kiraan manual yang disahkan MAIS.

### Modul 8 · Aduan dan Isu Tanah

**Kod:** MOD-ADU · **Gelombang:** G3 (Bulan 4.0–4.5) · **Rujukan:** RFP Seksyen 6.1 (Modul 8), 10.2; Lampiran H A-11.01–A-11.10

Modul ini membuka saluran aduan berkaitan aset tanah MAIS kepada orang awam melalui portal yang tidak memerlukan akaun berdaftar, dan menguruskan setiap aduan — awam mahupun dalaman — melalui triaj automatik, penugasan, SLA mengikut kategori dan eskalasi sehingga penyelesaian yang disahkan. Ia turut menerima aduan daripada kaunter, e-mel, telefon dan Sistem Aduan berpusat MAIS supaya semua aduan hartanah diurus dalam satu barisan tunggal.

**Kategori pengguna.**

- Orang awam dan pengadu — melalui portal aduan awam tanpa akaun berdaftar
- Unit Aduan MAIS — penerima, pentriaj dan penyelaras aduan
- Pegawai Daerah — penyiasat aduan dalam daerah masing-masing
- Bahagian Penguatkuasaan — tindakan terhadap pencerobohan, pembinaan tanpa kebenaran dan penghuni haram
- Bahagian Pengurusan Hartanah dan Bahagian Perundangan — aduan melibatkan sengketa sempadan dan pertikaian hak milik
- Penyelia dan Ketua Bahagian — pelulus penutupan aduan, penerima eskalasi SLA
- Pengurusan Atasan — dashboard dan analitik aduan
- Pentadbir Sistem — konfigurasi kategori, SLA, peraturan triaj dan laluan eskalasi

**Keperluan fungsian**

| Kod | Keperluan | Keutamaan | Sumber |
| --- | --- | --- | --- |
| SRS-ADU-01 | Sistem hendaklah menyediakan pengurusan aduan berkaitan aset tanah MAIS yang membolehkan penerimaan, pengkategorian, penugasan, penjejakan dan penutupan aduan secara sistematik dalam satu barisan tunggal. | Wajib | A-11.01 |
| SRS-ADU-02 | Sistem hendaklah menyediakan portal aduan awam tanpa akaun berdaftar, di mana orang awam boleh melaporkan isu tanah MAIS dengan memilih lokasi pada peta atau memasukkan alamat, memilih kategori, menulis keterangan dan memuat naik gambar atau dokumen sokongan. | Wajib | URS-ADU-01; A-11.01; Jurang |
| SRS-ADU-03 | Sistem hendaklah menjana nombor rujukan aduan yang unik bagi setiap aduan tanpa mengira saluran penerimaan, dan memaparkannya kepada pengadu serta-merta selepas aduan dihantar. | Wajib | A-11.02; URS-ADU-01 |
| SRS-ADU-04 | Sistem hendaklah membolehkan pengadu menyemak status aduan pada bila-bila masa melalui portal menggunakan nombor rujukan beserta pengesahan e-mel atau nombor telefon yang digunakan semasa aduan dibuat. | Wajib | URS-ADU-02; Jurang |
| SRS-ADU-05 | Sistem hendaklah menghantar notifikasi kepada pengadu pada setiap perubahan status utama — aduan diterima, ditugaskan, siasatan dijalankan dan diselesaikan — melalui e-mel atau SMS mengikut maklumat hubungan yang diberikan. | Wajib | URS-ADU-02; A-11.08 |
| SRS-ADU-06 | Sistem hendaklah menyokong pelbagai saluran penerimaan aduan termasuk sistem, portal awam, e-mel dan telefon, dengan setiap aduan direkod bersama medan saluran penerimaan bagi tujuan analitik. | Wajib | A-11.02 |
| SRS-ADU-07 | Sistem hendaklah membolehkan aduan daripada kaunter, e-mel, telefon dan Sistem Aduan berpusat MAIS direkod atau diimport ke dalam barisan yang sama, dengan integrasi API dua hala bersama Sistem Aduan MAIS bagi pertukaran status penyelesaian. | Wajib | URS-ADU-07; A-11.02; RFP 10.2 |
| SRS-ADU-08 | Sistem hendaklah membolehkan pengkategorian aduan mengikut jenis termasuk pencerobohan tanah, kerosakan aset, sengketa sempadan, pembinaan tanpa kebenaran, kecurian dan isu penghuni haram, dengan senarai kategori dan subkategori boleh dikonfigurasi. | Wajib | A-11.03 |
| SRS-ADU-09 | Sistem hendaklah melaksanakan triaj automatik bagi setiap aduan masuk — padanan lokasi kepada lot tanah MAIS berdasarkan koordinat atau alamat, cadangan kategori berdasarkan kata kunci keterangan, dan semakan aduan berulang bagi lot yang sama dalam tempoh yang dikonfigurasi. | Wajib | URS-ADU-03; Jurang |
| SRS-ADU-10 | Sistem hendaklah mengenal pasti aduan yang lokasinya tidak berada dalam mana-mana lot MAIS, menandakannya sebagai bukan tanah MAIS, dan menjana jawapan automatik yang sopan beserta cadangan agensi berkaitan, tertakluk kepada semakan pegawai Unit Aduan. | Perlu | URS-ADU-03; Jurang |
| SRS-ADU-11 | Sistem hendaklah menyediakan aliran kerja penugasan aduan kepada pegawai secara automatik berdasarkan kategori, lokasi atau daerah lot dan struktur peranan, dengan pilihan penugasan manual oleh Unit Aduan. | Wajib | A-11.04 |
| SRS-ADU-12 | Sistem hendaklah menetapkan SLA bagi setiap aduan mengikut kategori, dengan tempoh respons dan penyelesaian boleh dikonfigurasi, dan memaparkan baki masa SLA dalam barisan tugasan pegawai. | Wajib | URS-ADU-04; Jurang |
| SRS-ADU-13 | Sistem hendaklah mengira SLA berdasarkan hari dan jam bekerja yang dikonfigurasi termasuk pengecualian hari kelepasan am Negeri Selangor dan persekutuan, supaya tempoh SLA tidak berjalan pada hari bukan bekerja. | Perlu | URS-ADU-04; Jurang |
| SRS-ADU-14 | Sistem hendaklah mengeskalasikan aduan secara automatik kepada penyelia dan seterusnya ketua bahagian apabila SLA hampir tamat dan apabila SLA telah dilepasi, menurut laluan eskalasi berperingkat yang boleh dikonfigurasi. | Wajib | URS-ADU-04; A-11.07 |
| SRS-ADU-15 | Sistem hendaklah mempautkan setiap aduan kepada rekod lot tanah atau profil hartanah berkaitan, dan memaparkan sejarah aduan mengikut lot pada kad rekod lot. | Wajib | A-11.05 |
| SRS-ADU-16 | Sistem hendaklah memaparkan lokasi aduan pada Modul GIS, dan menyediakan analisis corak serta kluster aduan mengikut kawasan, daerah dan kategori. | Wajib | A-11.06 |
| SRS-ADU-17 | Sistem hendaklah menyediakan tindakan susulan termasuk penugasan semula, eskalasi kepada penyelia, penjadualan lawatan tapak dan rekod tindakan yang diambil beserta tarikh dan pegawai bertindak. | Wajib | A-11.07 |
| SRS-ADU-18 | Sistem hendaklah merekod kronologi penuh bagi setiap aduan meliputi setiap tindakan, siasatan, komunikasi dengan pengadu, keputusan dan tarikh, dalam bentuk garis masa yang tidak boleh dipadam. | Wajib | URS-ADU-05; A-11.01 |
| SRS-ADU-19 | Sistem hendaklah membenarkan aduan ditutup hanya setelah resolusi direkodkan beserta kategori resolusi dan justifikasi, dengan kelulusan penyelia, dan bagi aduan awam setelah makluman penyelesaian dihantar kepada pengadu. | Wajib | URS-ADU-05; A-11.01 |
| SRS-ADU-20 | Sistem hendaklah melindungi maklumat identiti pengadu dengan memastikan hanya pegawai yang ditugaskan, penyelianya dan pegawai Unit Aduan boleh melihat nama, nombor telefon, e-mel dan alamat pengadu; pengguna lain hanya melihat nombor rujukan dan kandungan aduan. | Wajib | URS-ADU-06; Jurang |
| SRS-ADU-21 | Sistem hendaklah menyulitkan maklumat identiti pengadu semasa rehat dan merekod setiap capaian terhadapnya dalam audit trail capaian, termasuk identiti pegawai, tarikh, masa dan tujuan capaian. | Wajib | URS-ADU-06; A-16.07; Jurang |
| SRS-ADU-22 | Sistem hendaklah membenarkan aduan tanpa nama melalui portal awam, dengan fungsi terhad — tiada notifikasi perubahan status dan semakan status hanya melalui nombor rujukan — dan aduan tanpa nama ditanda jelas dalam barisan. | Wajib | URS-ADU-06; Jurang |
| SRS-ADU-23 | Sistem hendaklah menyediakan notifikasi automatik kepada pegawai bertanggungjawab apabila aduan baharu diterima atau ditugaskan, apabila tempoh tindakan hampir luput dan apabila aduan diselesaikan. | Wajib | A-11.08 |
| SRS-ADU-24 | Sistem hendaklah melindungi portal aduan awam daripada penyalahgunaan melalui had kadar penghantaran mengikut alamat IP dan nombor telefon, CAPTCHA, had saiz dan jenis fail muat naik, serta pengesanan aduan pendua berdasarkan lokasi dan kandungan. | Wajib | Jurang |
| SRS-ADU-25 | Sistem hendaklah menyediakan laporan aduan mengikut kategori, status, lokasi atau daerah, tempoh penyelesaian dan pegawai bertanggungjawab bagi pemantauan prestasi. | Wajib | A-11.09 |
| SRS-ADU-26 | Sistem hendaklah menyediakan dashboard aduan yang menunjukkan jumlah aduan aktif, tertangguh, diselesaikan dan masa purata penyelesaian, dengan penapis tempoh, daerah, kategori dan saluran. | Wajib | A-11.10 |
| SRS-ADU-27 | Sistem hendaklah menyediakan analitik aduan mengikut kategori, daerah, saluran penerimaan, prestasi SLA, aduan berulang bagi setiap lot dan peta taburan, bagi menyokong pengenalpastian isu sistemik. | Perlu | URS-ADU-08; A-11.09 |

**Aliran proses utama.**

1. Pengadu awam mengakses portal tanpa akaun berdaftar, memilih lokasi pada peta atau memasukkan alamat, memilih kategori, menulis keterangan dan memuat naik gambar.
2. Sistem melaksanakan kawalan had kadar dan CAPTCHA, menerima aduan, menjana nombor rujukan unik dan memaparkannya serta-merta.
3. Triaj automatik memadankan lokasi kepada lot MAIS, mencadangkan kategori, menyemak aduan berulang, dan menandakan aduan bukan tanah MAIS untuk jawapan automatik.
4. Aduan daripada kaunter, e-mel, telefon dan Sistem Aduan berpusat direkod atau diimport ke barisan yang sama dan melalui triaj serupa.
5. Aduan ditugaskan automatik berdasarkan kategori, daerah dan peranan, dan SLA dimulakan menurut kalendar hari bekerja.
6. Pegawai menjalankan siasatan, menjadualkan lawatan tapak jika perlu, dan merekod setiap tindakan serta komunikasi dalam kronologi.
7. Sistem memantau baki masa SLA dan mengeskalasikan kepada penyelia dan ketua bahagian pada ambang yang ditetapkan.
8. Aduan yang memerlukan tindakan penguatkuasaan atau perundangan dirujuk kepada bahagian berkenaan dengan pemindahan maklumat automatik.
9. Resolusi direkod beserta kategori dan justifikasi, diluluskan penyelia, makluman dihantar kepada pengadu, dan aduan ditutup.
10. Dashboard dan analitik dikemas kini, dan status penyelesaian disalurkan semula kepada Sistem Aduan berpusat MAIS bagi aduan yang berasal dari sistem tersebut.

**Entiti data.**

- `aduan` — no\_rujukan\_aduan, saluran\_penerimaan, tarikh\_terima, kategori, subkategori, keterangan, lokasi\_teks, koordinat\_aduan, id\_lot, status\_aduan, tahap\_keutamaan, status\_tanpa\_nama, status\_bukan\_tanah\_mais, id\_pegawai\_ditugaskan, id\_aduan\_sistem\_luaran
- `pengadu` — id\_pengadu, no\_rujukan\_aduan, nama, no\_telefon, emel, alamat, status\_penyulitan, status\_tanpa\_nama, klasifikasi\_data
- `kronologi_aduan` — id\_kronologi, no\_rujukan\_aduan, tarikh\_masa, jenis\_tindakan, keterangan\_tindakan, id\_pegawai, status\_sebelum, status\_selepas, id\_dokumen\_berkaitan
- `sla_kategori_aduan` — id\_sla, kategori, subkategori, tempoh\_respons, tempoh\_penyelesaian, unit\_masa, laluan\_eskalasi, tarikh\_berkuat\_kuasa
- `jejak_sla_aduan` — id\_jejak, no\_rujukan\_aduan, id\_sla, masa\_mula, masa\_sasaran\_respons, masa\_sasaran\_selesai, masa\_respons\_sebenar, masa\_selesai\_sebenar, status\_sla, bilangan\_eskalasi
- `eskalasi_aduan` — id\_eskalasi, no\_rujukan\_aduan, peringkat, id\_pegawai\_asal, id\_pegawai\_eskalasi, sebab\_eskalasi, tarikh\_eskalasi
- `lawatan_tapak_aduan` — id\_lawatan\_aduan, no\_rujukan\_aduan, tarikh\_jadual, tarikh\_lawatan, id\_pegawai, penemuan, id\_rekod\_lawatan\_ptn
- `dokumen_aduan` — id\_dokumen, no\_rujukan\_aduan, jenis\_dokumen, laluan\_simpanan, saiz, jenis\_fail, sumber\_muat\_naik
- `resolusi_aduan` — id\_resolusi, no\_rujukan\_aduan, kategori\_resolusi, keterangan\_resolusi, justifikasi, id\_pegawai\_penyelesai, id\_penyelia\_pelulus, tarikh\_tutup, status\_makluman\_pengadu
- `peraturan_triaj` — id\_peraturan, jenis\_peraturan, kata\_kunci, kategori\_cadangan, tempoh\_semakan\_berulang, status\_aktif
- `audit_capaian_pengadu` — id\_audit, no\_rujukan\_aduan, id\_pegawai, tarikh\_masa, medan\_dicapai, tujuan\_capaian, alamat\_ip

**Integrasi.** Portal aduan awam pada domain berasingan yang berkomunikasi melalui API sahaja; Sistem Aduan berpusat MAIS melalui API dua hala; Modul GIS bagi paparan lokasi dan kluster aduan; Modul Pemantauan Tanah bagi penukaran isu kepada aduan dan penjadualan lawatan; Modul Kes Mahkamah bagi aduan yang berkembang menjadi kes; Pangkalan Data Induk bagi padanan lot dan sejarah aduan; Modul Workflow bagi penugasan, eskalasi dan kelulusan penutupan; Modul Notifikasi; DMS bagi gambar dan dokumen sokongan.

**Kawalan keselamatan.**

- Portal aduan awam diasingkan daripada SISDATA dalaman, berkomunikasi melalui API Gateway dengan had kadar, CAPTCHA dan penapisan jenis serta saiz fail
- Maklumat identiti pengadu disulitkan semasa rehat dan penghantaran, dipaparkan hanya kepada pegawai yang ditugaskan, penyelia dan Unit Aduan
- Setiap capaian terhadap maklumat identiti pengadu direkod dalam audit trail capaian
- Aduan tanpa nama tidak menyimpan maklumat identiti, dan semakan status terhad kepada nombor rujukan
- Fail yang dimuat naik melalui portal awam diperiksa bagi perisian hasad sebelum disimpan, dan disimpan di luar laluan capaian awam langsung
- Kronologi aduan dan jejak SLA tidak boleh dipadam atau diubah; pembetulan direkod sebagai catatan tambahan
- Capaian aduan ditapis mengikut daerah dan kategori bagi pegawai daerah
- Penutupan aduan memerlukan kelulusan penyelia, dengan pengasingan tugas antara penyiasat dan pelulus

**Laporan yang boleh dijana.** Aduan mengikut kategori, status, daerah, tempoh penyelesaian dan pegawai; prestasi pencapaian SLA mengikut kategori, pegawai dan daerah; aduan tertangguh dan melepasi SLA beserta peringkat eskalasi; aduan berulang bagi setiap lot; aduan mengikut saluran penerimaan; aduan bukan tanah MAIS beserta agensi disyorkan; taburan dan kluster aduan dalam peta dan jadual; masa purata respons dan penyelesaian; resolusi mengikut kategori dan jenis tindakan; audit capaian maklumat identiti pengadu.

**Kriteria penerimaan.**

1. Satu aduan boleh dihantar melalui portal awam tanpa akaun berdaftar, dan nombor rujukan unik dipaparkan serta-merta serta dihantar melalui e-mel atau SMS.
2. Triaj automatik memadankan aduan ujian dengan koordinat dalam lot MAIS kepada lot yang betul, dan menandakan aduan dengan koordinat di luar semua lot MAIS sebagai bukan tanah MAIS beserta jawapan automatik.
3. SLA dimulakan automatik mengikut kategori, baki masa dipaparkan dengan betul, tempoh tidak berjalan pada hari kelepasan am yang dikonfigurasi, dan eskalasi berlaku automatik pada ambang yang ditetapkan.
4. Pegawai yang tidak ditugaskan kepada satu aduan tidak dapat melihat nama, nombor telefon, e-mel atau alamat pengadu, dan setiap capaian oleh pegawai yang dibenarkan direkod dalam audit trail capaian.
5. Aduan tanpa nama boleh dihantar, ditanda jelas dalam barisan, tidak menyimpan maklumat identiti, dan statusnya boleh disemak melalui nombor rujukan.
6. Aduan yang diterima daripada Sistem Aduan berpusat MAIS muncul dalam barisan yang sama, dan status penyelesaian disalurkan semula ke sistem tersebut secara automatik.
7. Dashboard aduan memaparkan jumlah aduan aktif, tertangguh, diselesaikan dan masa purata penyelesaian yang selaras dengan data ujian apabila disemak silang.

### Modul 9 · Kes Mahkamah dan Perundangan

**Kod:** MOD-KES · **Gelombang:** G4 (Bulan 4.5–5.0) · **Rujukan:** RFP Seksyen 6.1–6.2 (Modul 9); Lampiran H A-12.01–A-12.08

Modul ini menguruskan kitaran hayat setiap kes mahkamah dan perkara perundangan yang melibatkan lot tanah atau aset MAIS, daripada pemfailan sehingga keputusan dan tindakan susulan. Objektifnya memastikan tiada tarikh prosiding terlepas, setiap dokumen perundangan terkawal, kos guaman terjejak, dan pendedahan perundangan portfolio sentiasa jelas kepada pengurusan tertinggi.

**Kategori pengguna.**

- Pegawai Bahagian Perundangan MAIS — pemilik proses, pendaftaran dan kemas kini kes
- Ketua Bahagian Perundangan — semakan, pengesahan dan kelulusan tindakan susulan
- Pegawai kes yang ditugaskan — kemas kini prosiding dan pengemukaan dokumen
- Pegawai Daerah dan Bahagian Pengurusan Hartanah — capaian baca bagi kes yang melibatkan lot di bawah kawalan mereka
- Panel peguam luar MAIS — capaian terhad kepada kes yang ditugaskan, tertakluk kelulusan MAIS
- Pengurusan tertinggi MAIS — dashboard dan laporan pendedahan
- Pentadbir Sistem — konfigurasi jenis kes, peringkat status dan parameter notifikasi

**Keperluan fungsian**

| Kod | Keperluan | Keutamaan | Sumber |
| --- | --- | --- | --- |
| SRS-KES-01 | Sistem hendaklah menyokong sekurang-kurangnya jenis kes sivil, jenayah, faraid, wakaf, sengketa pemilikan, tuntutan milikan, pencerobohan, kaveat dan rayuan, dengan senarai jenis kes boleh dikonfigurasi tanpa perubahan kod. | Wajib | A-12.01; URS-KES-01 |
| SRS-KES-02 | Sistem hendaklah merekodkan maklumat kes secara lengkap termasuk nombor kes mahkamah, jenis kes, nama mahkamah, peringkat mahkamah, tarikh pemfailan, ringkasan tuntutan dan nilai tuntutan. | Wajib | A-12.02; URS-KES-01 |
| SRS-KES-03 | Sistem hendaklah merekodkan semua pihak yang terlibat mengikut peranan — plaintif, defendan, pihak ketiga, pencelah — beserta maklumat perhubungan dan status wakilan. | Wajib | A-12.02; URS-KES-01 |
| SRS-KES-04 | Sistem hendaklah merekodkan maklumat peguam bagi setiap pihak termasuk peguam atau panel yang mewakili MAIS, nama firma, pegawai penghubung dan maklumat perhubungan. | Wajib | A-12.02; URS-KES-01 |
| SRS-KES-05 | Sistem hendaklah membolehkan satu kes dipautkan kepada berbilang lot dan satu lot kepada berbilang kes, serta memaparkan sejarah kes mengikut lot pada profil hartanah. | Wajib | A-12.06; URS-KES-01 |
| SRS-KES-06 | Sistem hendaklah mengemas kini status lot yang mempunyai kes aktif kepada dalam tindakan undang-undang secara automatik, dan menanggalkan status tersebut apabila kesemua kes berkaitan ditandakan selesai. | Perlu | A-12.06; A-5.03 |
| SRS-KES-07 | Sistem hendaklah menyediakan penjejakan status kes berperingkat termasuk dalam bicara, tangguh, sebutan, keputusan, rayuan dan selesai, beserta rekod tarikh bagi setiap perubahan. | Wajib | A-12.03 |
| SRS-KES-08 | Sistem hendaklah merekodkan setiap prosiding dengan tarikh, jenis prosiding, hasil, arahan mahkamah dan tarikh prosiding berikutnya, serta memaparkannya sebagai kronologi kes mengikut susunan masa. | Wajib | A-12.03; URS-KES-02 |
| SRS-KES-09 | Sistem hendaklah memaparkan semua tarikh prosiding dalam satu kalendar perundangan peringkat bahagian yang boleh ditapis mengikut pegawai kes, jenis kes, mahkamah dan daerah. | Perlu | URS-KES-02 |
| SRS-KES-10 | Sistem hendaklah menghantar peringatan automatik kepada pegawai kes dan ketua bahagian pada 14, 7 dan 1 hari sebelum setiap tarikh perbicaraan, sebutan dan pengemukaan dokumen, memenuhi keperluan minimum tujuh hari. | Wajib | A-12.04; URS-KES-03 |
| SRS-KES-11 | Sistem hendaklah mengeskalasi kes kepada ketua bahagian apabila tiada tarikh prosiding berikutnya direkodkan dalam tempoh yang ditetapkan selepas sesuatu prosiding berlangsung. | Perlu | URS-KES-03 |
| SRS-KES-12 | Sistem hendaklah membolehkan lampiran dan pengurusan dokumen perundangan melalui DMS termasuk surat tuntutan, pliding, afidavit, perintah mahkamah, penghakiman, perjanjian penyelesaian dan laporan peguam, dengan kawalan versi dan metadata kes. | Wajib | A-12.05; URS-KES-04 |
| SRS-KES-13 | Sistem hendaklah mengenakan kawalan akses khusus perundangan ke atas rekod dan dokumen kes, serta merekodkan log capaian penuh bagi setiap paparan, muat turun dan cetakan. | Wajib | A-12.05; URS-KES-04 |
| SRS-KES-14 | Sistem hendaklah mengklasifikasikan rekod kes sebagai Rahsia dan mewajibkan MFA bagi capaian kepada rekod dan dokumen kes. | Wajib | Jurang |
| SRS-KES-15 | Sistem hendaklah merekodkan kos guaman mengikut kategori fi peguam, kos mahkamah dan kos berkaitan, mengira jumlah terkumpul per kes, dan menyalurkan kos tersebut kepada graf kos lot berkaitan. | Wajib | URS-KES-05 |
| SRS-KES-16 | Sistem hendaklah merekodkan keputusan mahkamah beserta implikasinya — menang, kalah, selesai, penyelesaian luar mahkamah atau ditolak — beserta tarikh keputusan dan dokumen penghakiman. | Wajib | A-12.07; URS-KES-06 |
| SRS-KES-17 | Sistem hendaklah mencetuskan tindakan susulan sebagai tugasan dalam Modul Workflow berdasarkan keputusan kes, termasuk pematuhan perintah mahkamah, penguatkuasaan keputusan, pemfailan rayuan dan pengemaskinian rekod hak milik. | Wajib | A-12.07; URS-KES-06 |
| SRS-KES-18 | Sistem hendaklah merekodkan setiap pengemaskinian rekod hak milik atau status tanah yang dibuat berdasarkan keputusan mahkamah dengan rujukan silang kepada nombor kes dan dokumen perintah yang menjadi asas tindakan. | Wajib | A-12.07 |
| SRS-KES-19 | Sistem hendaklah membolehkan rekod aduan dinaikkan menjadi kes perundangan, dengan pemindahan automatik pautan lot, dokumen dan kronologi tindakan aduan ke dalam rekod kes baharu. | Perlu | Jurang |
| SRS-KES-20 | Sistem hendaklah menugaskan setiap kes kepada seorang pegawai bertanggungjawab, menyimpan sejarah pertukaran pegawai beserta tarikh dan justifikasi, dan memaparkan beban kes semasa bagi setiap pegawai. | Wajib | Jurang |
| SRS-KES-21 | Sistem hendaklah menyediakan laporan kes yang boleh ditapis dan dikumpulkan mengikut status, jenis kes, mahkamah, **daerah dan mukim**, **kategori tanah**, **pegawai bertanggungjawab**, peguam serta tempoh tarikh. | Wajib | A-12.08; Jurang |
| SRS-KES-22 | Sistem hendaklah menyediakan dashboard ringkasan kes aktif yang memaparkan bilangan kes mengikut status dan jenis, kes dengan tarikh prosiding hampir, nilai lot terlibat dan kos guaman terkumpul. | Wajib | A-12.08; URS-KES-07 |
| SRS-KES-23 | Sistem hendaklah menyediakan laporan pendedahan perundangan portfolio yang memaparkan nilai lot dalam kes aktif, kos guaman terkumpul mengikut tahun, dan trend keputusan mengikut jenis kes. | Wajib | URS-KES-07 |
| SRS-KES-24 | Sistem hendaklah membolehkan semua laporan dan dashboard kes dieksport dalam PDF dan Excel dengan jejak audit bagi setiap penjanaan dan eksport. | Perlu | A-12.08 |

**Aliran proses utama.**

1. Pegawai Bahagian Perundangan mendaftarkan kes baharu dengan nombor kes, jenis, nama dan peringkat mahkamah serta tarikh pemfailan.
2. Pegawai merekodkan semua pihak terlibat, peguam masing-masing, dan memaut kes kepada satu atau lebih lot berkaitan.
3. Sistem mengemas kini status lot kepada dalam tindakan undang-undang dan memaparkan kes pada profil hartanah.
4. Ketua Bahagian menugaskan kes kepada pegawai bertanggungjawab; sistem merekodkan tugasan dan beban kes.
5. Pegawai merekodkan setiap prosiding beserta tarikh, jenis, hasil, arahan mahkamah dan tarikh berikutnya; sistem memaparkan kronologi dan memasukkan tarikh ke kalendar perundangan.
6. Sistem menghantar peringatan automatik 14, 7 dan 1 hari sebelum setiap tarikh prosiding dan pengemukaan dokumen.
7. Dokumen perundangan dimuat naik ke DMS dengan metadata kes; sistem merekodkan log capaian penuh.
8. Kos guaman direkodkan mengikut kategori sepanjang kes dan disalurkan kepada graf kos lot berkaitan.
9. Apabila keputusan dikeluarkan, pegawai merekodkan keputusan dan implikasinya; sistem mencetuskan tugasan tindakan susulan.
10. Tindakan susulan dilaksanakan; sebarang pengemaskinian rekod hak milik dibuat dengan rujukan silang kepada nombor kes dan perintah mahkamah.
11. Kes ditandakan selesai; status dalam tindakan undang-undang ditanggalkan sekiranya tiada kes aktif lain bagi lot berkenaan, dan rekod diarkibkan tanpa pemadaman.

**Entiti data.**

- `kes_mahkamah` — id\_kes, no\_kes\_mahkamah, jenis\_kes, nama\_mahkamah, peringkat\_mahkamah, tarikh\_pemfailan, ringkasan\_tuntutan, nilai\_tuntutan, status\_semasa, tarikh\_status, klasifikasi\_keselamatan, id\_pegawai\_bertanggungjawab, status\_arkib
- `kes_lot` — id\_kes, id\_lot, peranan\_lot, tarikh\_paut (jadual perantara N:N kepada `lot_tanah`)
- `kes_pihak` — id\_kes, nama\_pihak, jenis\_pihak, peranan, maklumat\_perhubungan
- `kes_peguam` — id\_kes, id\_pihak, nama\_peguam, nama\_firma, no\_telefon, emel, indikator\_panel\_mais
- `kes_prosiding` — id\_prosiding, id\_kes, tarikh\_prosiding, jenis\_prosiding, hasil, arahan\_mahkamah, tarikh\_berikutnya, catatan, id\_pegawai\_rekod
- `kes_status_sejarah` — id\_kes, status\_lama, status\_baharu, tarikh\_perubahan, id\_pengguna
- `kes_pegawai_sejarah` — id\_kes, id\_pegawai\_lama, id\_pegawai\_baharu, tarikh\_tukar, justifikasi
- `kos_guaman` — id\_kos, id\_kes, kategori\_kos, amaun, tarikh, no\_resit, id\_dokumen, status\_bayaran
- `kes_keputusan` — id\_kes, jenis\_keputusan, tarikh\_keputusan, ringkasan\_perintah, implikasi, id\_dokumen\_penghakiman
- `kes_tindakan_susulan` — id\_tindakan, id\_kes, jenis\_tindakan, id\_tugasan\_workflow, tarikh\_sasaran, status, id\_lot\_terjejas
- `kes_dokumen` — id\_kes, id\_dokumen\_dms, kategori\_dokumen, versi, tarikh\_muat\_naik, id\_pengguna
- `kes_log_capaian` — id\_kes, id\_dokumen, id\_pengguna, jenis\_tindakan, cap\_masa, alamat\_ip

**Integrasi.** DMS bagi penyimpanan, versi dan kawalan akses dokumen perundangan; Modul Workflow bagi tugasan tindakan susulan; Modul Hak Milik bagi pengemaskinian rekod dan status tanah; Modul Aduan bagi penaikan aduan menjadi kes; Modul GIS bagi lapisan lot dalam tindakan undang-undang; Modul Notifikasi bagi peringatan tarikh prosiding; Modul Laporan bagi dashboard pendedahan; Modul Audit Trail; MOD-RBC dan SSO MAIS bagi pengesahan dan MFA; GRP MAIS bagi pengesahan bayaran fi peguam dan kos mahkamah.

**Kawalan keselamatan.**

- Rekod dan dokumen kes diklasifikasikan Rahsia dengan capaian terhad kepada Bahagian Perundangan dan pengurusan yang diluluskan
- MFA diwajibkan bagi semua capaian kepada rekod dan dokumen kes
- Kawalan capaian berasaskan peranan dan rekod — panel peguam luar hanya melihat kes yang ditugaskan kepada mereka
- Log capaian penuh bagi setiap paparan, muat turun dan cetakan dokumen kes, termasuk alamat IP dan cap masa
- Penyulitan dokumen perundangan semasa penyimpanan dan penghantaran
- Rekod kes tidak boleh dipadam; penutupan dilaksanakan melalui pengarkiban berjejak audit
- Tanda air dinamik pada paparan dan cetakan dokumen perundangan yang mengandungi identiti pengguna dan cap masa

**Laporan yang boleh dijana.** Senarai kes aktif mengikut status, jenis dan mahkamah; kes mengikut daerah dan mukim; kes mengikut kategori tanah; kes mengikut pegawai bertanggungjawab beserta beban kes; kes mengikut peguam dan firma; kalendar prosiding bagi tempoh terpilih; kes tanpa tarikh prosiding berikutnya; kos guaman terkumpul mengikut kes, kategori dan tahun; keputusan kes mengikut jenis dan implikasi; pendedahan perundangan portfolio; log capaian dokumen kes.

**Kriteria penerimaan.**

1. Satu kes ujian boleh didaftarkan dengan kesemua medan A-12.02, dipaut kepada tiga lot berbeza, dan ketiga-tiga lot memaparkan kes tersebut pada profil hartanah masing-masing.
2. Status ketiga-tiga lot bertukar kepada dalam tindakan undang-undang apabila kes diaktifkan, dan kembali kepada status asal apabila kes ditandakan selesai.
3. Peringatan automatik diterima pegawai kes dan ketua bahagian tepat pada 14, 7 dan 1 hari sebelum tarikh prosiding ujian.
4. Pengguna tanpa peranan Bahagian Perundangan tidak dapat membuka rekod atau dokumen kes, dan capaian oleh pengguna yang dibenarkan memerlukan MFA serta direkod dalam log capaian beserta alamat IP dan cap masa.
5. Kos guaman yang direkodkan bagi satu kes muncul dalam graf kos lot yang dipaut kepada kes tersebut dengan amaun yang betul.
6. Perekodan keputusan kes mencetuskan tugasan tindakan susulan dalam Modul Workflow, dan pengemaskinian rekod hak milik hasil tindakan tersebut memaparkan rujukan silang kepada nombor kes dan dokumen perintah mahkamah.
7. Laporan kes boleh ditapis mengikut kesemua dimensi yang disenaraikan dalam SRS-KES-21 termasuk daerah, kategori tanah dan pegawai bertanggungjawab, dan boleh dieksport dalam PDF dan Excel.

### Modul 10 · Tukar Syarat, Pelupusan dan Ukur

**Kod:** MOD-TSU · **Gelombang:** G4 (Bulan 4.5–5.0) · **Rujukan:** RFP Seksyen 6.1–6.2 (Modul 10); Lampiran H A-9.01–A-9.10

Modul ini menguruskan setiap urusan tanah MAIS dengan Pejabat Tanah dan agensi berkaitan — tukar syarat, pelupusan, pecah sempadan, pecah bahagian, penyatuan, penyerahan balik dan pemberimilikan semula serta permohonan hak milik kekal — melalui templat proses berperingkat tersendiri bagi setiap jenis urusan. Objektifnya memastikan kedudukan setiap permohonan sentiasa jelas, kos setiap peringkat direkodkan terperinci, dan hasil kerja ukur diterima sebagai geometri GIS tepat yang terus mengemas kini rekod lot.

**Kategori pengguna.**

- Pegawai Bahagian Pengurusan Hartanah — pemilik proses, pendaftaran dan pemantauan urusan
- Pegawai Daerah — penyediaan dokumen, pemantauan urusan di Pejabat Tanah Daerah
- Pegawai perolehan MAIS — jemputan sebut harga, penilaian dan lantikan jurukur berlesen
- GIS Specialist — penerimaan, pengesahan dan integrasi geometri hasil ukur
- Pegawai Bahagian Kewangan — pengesahan dan pemprosesan bayaran fi, premium dan upah ukur
- Ketua Bahagian dan Pengurusan tertinggi — kelulusan, khususnya bagi urusan pelupusan
- Jurukur berlesen yang dilantik — capaian terhad untuk mengemas kini kemajuan kerja ukur
- Pentadbir Sistem — konfigurasi templat proses, peringkat dan kategori kos

**Keperluan fungsian**

| Kod | Keperluan | Keutamaan | Sumber |
| --- | --- | --- | --- |
| SRS-TSU-01 | Sistem hendaklah menyokong jenis urusan tanah berikut dan menyediakan templat proses berperingkat tersendiri bagi setiap satu: tukar syarat atau jenis kegunaan, pecah sempadan, pecah bahagian, penyatuan, pelupusan, penyerahan balik dan pemberimilikan semula, serta permohonan hak milik kekal. | Wajib | A-9.01; A-9.03; URS-TSU-01 |
| SRS-TSU-02 | Sistem hendaklah membolehkan Pentadbir Sistem mengkonfigurasi peringkat, susunan, tempoh sasaran, dokumen wajib dan kategori kos bagi setiap templat tanpa perubahan kod. | Wajib | A-9.01; URS-TSU-01 |
| SRS-TSU-03 | Sistem hendaklah merekodkan setiap peringkat proses dengan tindakan diperlukan, pihak bertanggungjawab (MAIS, Pejabat Tanah, jurukur, PBT, JPPH), tempoh sasaran, status dan dokumen berkaitan, serta memaparkannya sebagai garis masa dengan peringkat semasa ditanda jelas. | Wajib | A-9.02; URS-TSU-02 |
| SRS-TSU-04 | Sistem hendaklah menyokong proses pelupusan tanah secara menyeluruh termasuk permohonan kepada PTD, dokumen sokongan, lawatan tapak, invois bayaran, kelulusan, permohonan bayaran dan rekod laporan JPPH. | Wajib | A-9.01 |
| SRS-TSU-05 | Sistem hendaklah menyediakan workflow kelulusan pelupusan berdasarkan peranan dan tahap kelulusan MAIS, dengan aliran kelulusan tertinggi yang mewajibkan MFA, justifikasi bertulis dan lampiran dokumen penilaian terkini sebelum kelulusan direkodkan. | Wajib | A-9.02; URS-TSU-07 |
| SRS-TSU-06 | Sistem hendaklah mengarkibkan rekod lot yang telah dilupuskan beserta keseluruhan sejarah dan dokumennya, dan tidak membenarkan pemadaman rekod tersebut. | Wajib | A-9.09; URS-TSU-07 |
| SRS-TSU-07 | Sistem hendaklah menyokong proses tukar syarat guna tanah termasuk penyediaan pelan dan dokumen, permohonan melalui e-Tanah, penghantaran ke PTD, kelulusan, bayaran premium dan pendaftaran hak milik baharu. | Wajib | A-9.03 |
| SRS-TSU-08 | Sistem hendaklah menyokong proses jemputan sebut harga kerja ukur kepada panel jurukur berlesen, termasuk penjanaan dokumen jemputan, senarai jurukur dijemput, tarikh tutup dan perekodan setiap tawaran yang diterima. | Wajib | A-9.04; Jurang |
| SRS-TSU-09 | Sistem hendaklah menyediakan penilaian sebut harga berupa jadual perbandingan tawaran mengikut harga, tempoh siap dan pengalaman jurukur, beserta rekod pengesyoran, justifikasi pemilihan dan kelulusan pegawai berkuasa. | Wajib | A-9.04; Jurang |
| SRS-TSU-10 | Sistem hendaklah merekodkan lantikan jurukur berlesen termasuk nombor lesen, surat setuju terima, nilai kontrak, tarikh mula, tempoh siap dan skop kerja ukur. | Wajib | A-9.04; Jurang |
| SRS-TSU-11 | Sistem hendaklah menyediakan pemantauan kemajuan kerja ukur mengikut milestone atau peratusan siap yang dikemas kini berkala, dan menghantar notifikasi kelewatan apabila kemajuan terkebelakang daripada tempoh sasaran. | Wajib | A-9.04; Jurang |
| SRS-TSU-12 | Sistem hendaklah merekodkan status kerja ukur termasuk tarikh mula, tarikh siap, jurukur dilantik, kos, dokumen berkaitan dan keputusan pengesahan hasil ukur. | Wajib | A-9.05 |
| SRS-TSU-13 | Sistem hendaklah menyokong penerimaan pelan ukur, perekodan invois jurukur, pemprosesan bayaran dan penyimpanan pelan ukur atau pelan akui dalam repositori dokumen berpusat. | Wajib | A-9.04 |
| SRS-TSU-14 | Sistem hendaklah merekodkan kos setiap peringkat urusan secara terperinci mengikut kategori fi permohonan, premium, cukai tambahan, upah ukur, fi pelan, fi pendaftaran dan kos lain, dengan jumlah terkumpul per urusan, status bayaran dan pautan kepada resit. | Wajib | A-9.04; URS-TSU-03 |
| SRS-TSU-15 | Sistem hendaklah menyalurkan kesemua kos urusan kepada graf kos kumulatif lot berkaitan bagi membolehkan analisis kos pemilikan sepanjang hayat lot. | Perlu | URS-TSU-03 |
| SRS-TSU-16 | Sistem hendaklah menerima hasil ukur sebagai data geospatial tepat melalui import fail jurukur dalam format DXF, Shapefile dan GeoJSON, dengan pengesahan sistem rujukan koordinat sebelum data diterima. | Wajib | A-9.06; URS-TSU-05 |
| SRS-TSU-17 | Sistem hendaklah menyediakan pengesahan visual sebelah-menyebelah antara geometri baharu dengan sempadan asal, serta mengira keluasan automatik dan membandingkannya dengan keluasan dalam dokumen rasmi mengikut ambang toleransi yang boleh dikonfigurasi. | Wajib | A-9.06; URS-TSU-05 |
| SRS-TSU-18 | Sistem hendaklah memaparkan lokasi hasil ukur dalam Modul GIS dan memautkannya kepada lapisan spatial berkaitan seperti hak milik, guna tanah dan zoning. | Wajib | A-9.06 |
| SRS-TSU-19 | Sistem hendaklah mencipta lot baharu dengan geometri baharu secara automatik selepas urusan pecah sempadan, pecah bahagian atau penyatuan diluluskan dan geometri hasil ukur disahkan. | Wajib | URS-TSU-06 |
| SRS-TSU-20 | Sistem hendaklah memindahkan rekod berkaitan — pajakan dan kontrak, rekod cukai serta aset fizikal — daripada lot asal kepada lot baharu mengikut arahan pegawai melalui skrin pemetaan rekod, dengan pengesahan dua peringkat sebelum pemindahan dilaksanakan. | Wajib | URS-TSU-06 |
| SRS-TSU-21 | Sistem hendaklah mengekalkan pautan sejarah lot asal kepada lot baharu secara dua hala dalam garis masa kedua-dua lot, dan mengarkibkan lot asal tanpa pemadaman data. | Wajib | URS-TSU-06; A-9.09 |
| SRS-TSU-22 | Sistem hendaklah mengemas kini status permohonan di Pejabat Tanah melalui API berpusat mengikut daerah, dan bagi urusan di luar liputan menyediakan kemas kini manual berstruktur beserta peringatan susulan automatik. | Wajib | URS-TSU-04 |
| SRS-TSU-23 | Sistem hendaklah menyediakan notifikasi tindakan susulan bagi kelulusan, bayaran premium, pengesahan pelan dan tarikh luput dokumen, kepada pegawai bertanggungjawab dan ketua bahagian. | Wajib | A-9.08 |
| SRS-TSU-24 | Sistem hendaklah menyimpan sejarah lengkap keputusan dan dokumen setiap urusan pelupusan atau tukar syarat bagi tujuan audit, dengan rekod yang tidak boleh diubah selepas urusan ditutup. | Wajib | A-9.09 |
| SRS-TSU-25 | Sistem hendaklah menyokong lampiran laporan penilaian, laporan tapak, gambar lawatan, surat kelulusan dan dokumen rasmi lain melalui Modul Dokumen Digital. | Wajib | A-9.10 |
| SRS-TSU-26 | Sistem hendaklah menyediakan laporan kerja pelupusan, tukar syarat, ukur dan demarkasi mengikut status, daerah, jurukur, kos dan tempoh pelaksanaan. | Wajib | A-9.07 |
| SRS-TSU-27 | Sistem hendaklah menyediakan laporan pengurusan tambahan meliputi urusan aktif mengikut jenis, daerah dan peringkat; tempoh purata penyempurnaan mengikut jenis urusan; jumlah kos urusan tahunan; serta urusan tertunggak di pihak luar. | Wajib | A-9.07; URS-TSU-08 |

**Aliran proses utama.**

1. Pegawai mendaftarkan urusan baharu, memilih jenis urusan dan lot terlibat; sistem menjana peringkat proses berdasarkan templat jenis urusan.
2. Pegawai menyediakan dokumen dan pelan permulaan; sistem menyemak kelengkapan dokumen wajib sebelum peringkat boleh ditutup.
3. Bagi urusan yang memerlukan kerja ukur, pegawai perolehan menjana jemputan sebut harga kepada panel jurukur dan merekodkan setiap tawaran.
4. Sistem menjana jadual perbandingan tawaran; pegawai merekodkan pengesyoran dan justifikasi, dan pegawai berkuasa meluluskan pemilihan.
5. Lantikan jurukur direkodkan beserta nombor lesen, surat setuju terima, nilai kontrak dan tempoh siap.
6. Kemajuan kerja ukur dikemas kini mengikut milestone; notifikasi kelewatan dihantar sekiranya terkebelakang.
7. Jurukur menyerahkan hasil ukur; GIS Specialist mengimport fail, sistem mengesahkan sistem koordinat dan memaparkan perbandingan sebelah-menyebelah beserta pengiraan keluasan.
8. Pegawai menerima atau menolak hasil ukur; penerimaan mencetuskan perekodan invois, pemprosesan bayaran dan penyimpanan pelan akui.
9. Permohonan dihantar ke Pejabat Tanah; status dikemas kini melalui API berpusat atau manual berstruktur bagi daerah di luar liputan.
10. Kos setiap peringkat direkodkan dan disalurkan kepada graf kos lot; notifikasi dihantar bagi bayaran yang akan jatuh tempoh.
11. Bagi pelupusan, kelulusan dilaksanakan melalui aliran tertinggi dengan MFA, justifikasi bertulis dan dokumen penilaian terkini.
12. Selepas kelulusan diterima bagi pecah sempadan, pecah bahagian atau penyatuan, sistem mencipta lot baharu dengan geometri baharu.
13. Pegawai memetakan rekod pajakan, cukai dan aset daripada lot asal kepada lot baharu; pemindahan dilaksanakan selepas pengesahan dua peringkat.
14. Sistem mengekalkan pautan sejarah dua hala, mengarkibkan lot asal, dan mengemas kini lapisan GIS.
15. Urusan ditutup; sejarah keputusan dan dokumen dikekalkan sebagai rekod audit yang tidak boleh diubah.

**Entiti data.**

- `urusan_tanah` — id\_urusan, jenis\_urusan, id\_templat, no\_rujukan\_mais, no\_rujukan\_ptd, daerah, mukim, peringkat\_semasa, status, tarikh\_mula, tarikh\_sasaran\_siap, tarikh\_siap, id\_pegawai\_bertanggungjawab
- `urusan_lot` — id\_urusan, id\_lot, peranan\_lot, tarikh\_paut
- `templat_proses` — id\_templat, jenis\_urusan, versi, status\_aktif
- `templat_peringkat` — id\_templat, no\_urutan, nama\_peringkat, pihak\_bertanggungjawab, tempoh\_sasaran\_hari, senarai\_dokumen\_wajib, senarai\_kategori\_kos
- `urusan_peringkat` — id\_peringkat, id\_urusan, no\_urutan, nama\_peringkat, pihak\_bertanggungjawab, tarikh\_mula, tarikh\_sasaran, tarikh\_selesai, status
- `kos_urusan` — id\_kos, id\_urusan, id\_peringkat, kategori\_kos, amaun, status\_bayaran, tarikh\_bayaran, no\_resit, id\_dokumen
- `sebutharga_ukur` — id\_sebutharga, id\_urusan, no\_jemputan, tarikh\_jemputan, tarikh\_tutup, status, id\_dokumen\_jemputan
- `sebutharga_tawaran` — id\_tawaran, id\_sebutharga, id\_jurukur, harga\_tawaran, tempoh\_siap\_hari, catatan\_penilaian, markah, indikator\_disyorkan
- `panel_jurukur` — id\_jurukur, nama\_firma, no\_lesen\_jurukur, nama\_jurukur\_bertanggungjawab, maklumat\_perhubungan, status\_panel, tarikh\_tamat\_lesen
- `lantikan_jurukur` — id\_lantikan, id\_urusan, id\_jurukur, no\_surat\_setuju\_terima, nilai\_kontrak, tarikh\_mula, tempoh\_siap\_hari, skop\_kerja, status
- `kemajuan_kerja_ukur` — id\_kemajuan, id\_lantikan, milestone, peratus\_siap, tarikh\_lapor, catatan
- `hasil_ukur` — id\_hasil, id\_urusan, id\_lantikan, format\_fail, sistem\_koordinat, geometri\_baharu, keluasan\_dikira, keluasan\_dokumen, sisihan\_peratus, status\_pengesahan, id\_pegawai\_sah, tarikh\_sah, id\_dokumen\_pelan\_akui
- `lot_sejarah_pecahan` — id\_lot\_asal, id\_lot\_baharu, id\_urusan, jenis\_perubahan, tarikh\_berkuat\_kuasa
- `pemindahan_rekod` — id\_pemindahan, id\_urusan, id\_lot\_asal, id\_lot\_baharu, jenis\_rekod, id\_rekod, status\_pemindahan, id\_pengesah\_1, id\_pengesah\_2, tarikh\_pemindahan
- `urusan_dokumen` — id\_urusan, id\_peringkat, id\_dokumen\_dms, kategori\_dokumen, tarikh\_luput, tarikh\_muat\_naik
- `urusan_keputusan` — id\_urusan, id\_peringkat, jenis\_keputusan, tarikh\_keputusan, justifikasi, id\_pelulus, indikator\_mfa, id\_dokumen\_kelulusan

**Integrasi.** API berpusat Pejabat Tanah Daerah bagi kemas kini status permohonan; PTG, e-Tanah dan Pejabat Tanah bagi data hak milik dan status pemberimilikan; JPPH bagi laporan penilaian; Modul GIS bagi penerimaan geometri dan penciptaan lot baharu; GRP MAIS bagi invois, premium, upah ukur dan fi pendaftaran; Modul Workflow bagi kelulusan berperingkat; DMS bagi pelan, laporan dan surat kelulusan; Modul Hak Milik bagi pendaftaran hak milik baharu; Modul Pajakan, Cukai dan Inventori Aset bagi pemindahan rekod; Modul Notifikasi; Modul Audit Trail.

**Kawalan keselamatan.**

- Aliran kelulusan tertinggi bagi pelupusan mewajibkan MFA dan justifikasi bertulis
- Pengasingan tugas antara pegawai pendaftar urusan, penilai sebut harga dan pelulus lantikan jurukur
- Pengesahan dua peringkat sebelum pemindahan rekod pajakan, cukai atau aset kepada lot baharu
- Perubahan geometri lot dikawal oleh peranan GIS Specialist dan direkodkan dengan versi sebelum dan selepas
- Rekod lot dilupuskan dan lot asal selepas pecahan diarkibkan, tidak dipadam, dan kekal boleh dirujuk
- Capaian jurukur luar dihadkan kepada kemas kini kemajuan kerja bagi lantikan masing-masing
- Dokumen kelulusan dan pelan akui dilindungi dengan kawalan versi, cap masa dan jejak capaian penuh
- Maklumat kewangan urusan hanya boleh diubah oleh Bahagian Kewangan dengan jejak audit penuh
- Nilai sebut harga jurukur disimpan terkawal sebelum tarikh tutup bagi mengelakkan pendedahan awal

**Laporan yang boleh dijana.** Kerja pelupusan, tukar syarat, ukur dan demarkasi mengikut status; urusan mengikut daerah dan mukim; urusan aktif mengikut jenis dan peringkat semasa; tempoh purata penyempurnaan mengikut jenis urusan; jumlah kos urusan tahunan mengikut kategori kos; urusan tertunggak di pihak luar; prestasi jurukur mengikut tempoh siap dan kos; sejarah pecahan dan penyatuan lot; rekod yang dipindahkan antara lot.

**Kriteria penerimaan.**

1. Kesemua tujuh jenis urusan tersedia sebagai templat berasingan dengan peringkat, dokumen wajib dan kategori kos yang berbeza, dan templat boleh diubah oleh Pentadbir Sistem tanpa pelepasan kod.
2. Satu kitaran penuh perolehan jurukur dilaksanakan daripada jemputan sebut harga, penerimaan tawaran, jadual perbandingan, pengesyoran, kelulusan sehingga lantikan direkodkan dengan nombor lesen dan surat setuju terima.
3. Fail hasil ukur dalam format DXF, Shapefile dan GeoJSON berjaya diimport, sistem koordinat disahkan, dan keluasan yang dikira dibandingkan dengan keluasan dokumen dengan sisihan dipaparkan terhadap ambang toleransi.
4. Selepas satu urusan pecah sempadan ujian diluluskan, sistem mencipta lot baharu dengan geometri baharu, dan rekod pajakan, cukai dan aset dipindahkan hanya selepas pengesahan dua peringkat.
5. Pautan sejarah dua hala antara lot asal dan lot baharu dipaparkan pada garis masa kedua-dua lot, dan lot asal diarkibkan tanpa kehilangan data.
6. Kelulusan pelupusan tidak dapat disempurnakan tanpa MFA, justifikasi bertulis dan lampiran dokumen penilaian terkini.
7. Kos setiap peringkat direkodkan mengikut kategori dan jumlah terkumpul muncul dengan betul dalam graf kos lot berkaitan.

### Modul 13 · Inventori Aset Fizikal

**Kod:** MOD-AST · **Gelombang:** G4 (Bulan 4.5–5.0) · **Rujukan:** RFP Seksyen 6.1–6.2 (Modul 13); Lampiran H A-13.01–A-13.08

Modul ini merekodkan setiap aset fizikal di atas lot tanah MAIS — bangunan, premis, struktur, infrastruktur dan kemudahan — beserta lokasi, nilai, keadaan, gambar bertarikh dan sejarah penyelenggaraan. Objektifnya memastikan setiap aset dapat dikenal pasti di lapangan melalui imbasan QR code, nilai portfolio aset sentiasa terkini, dan penyelenggaraan berjadual dilaksanakan tepat pada masanya melalui notifikasi automatik.

**Kategori pengguna.**

- Pegawai Unit Aset MAIS — pemilik proses, pendaftaran dan pengemaskinian rekod
- Pegawai Daerah — pengemaskinian aset dan pemeriksaan di daerah masing-masing
- Pegawai lapangan — pemeriksaan aset melalui imbasan QR dan PWA
- Pegawai Bahagian Kewangan — pengemaskinian nilai semasa dan kos penyelenggaraan
- Pegawai bertanggungjawab aset — penerima notifikasi penyelenggaraan dan penilaian semula
- Kontraktor penyelenggaraan — capaian terhad untuk mengemas kini status kerja
- Pengurusan tertinggi MAIS — laporan nilai portfolio dan aset kritikal
- Pengimbas QR tanpa akaun sistem — paparan maklumat minimum sahaja

**Keperluan fungsian**

| Kod | Keperluan | Keutamaan | Sumber |
| --- | --- | --- | --- |
| SRS-AST-01 | Sistem hendaklah menyediakan inventori aset fizikal yang merekodkan semua bangunan, struktur, infrastruktur dan aset fizikal di atas lot tanah milik MAIS. | Wajib | A-13.01 |
| SRS-AST-02 | Sistem hendaklah merekodkan maklumat aset secara lengkap termasuk jenis, nama, lokasi dengan pautan kepada rekod lot, koordinat GPS, alamat, tahun pembinaan atau perolehan, saiz, nilai semasa, status kondisi dan pegawai bertanggungjawab. | Wajib | A-13.02; URS-AST-01 |
| SRS-AST-03 | Sistem hendaklah menyediakan pengelasan aset mengikut kategori termasuk bangunan pejabat, masjid dan surau, sekolah, kedai, kompleks, bangunan kediaman, kemudahan awam dan infrastruktur lain, dengan senarai kategori boleh dikonfigurasi. | Wajib | A-13.03 |
| SRS-AST-04 | Sistem hendaklah membolehkan satu lot mempunyai berbilang aset, dan menyokong hierarki aset induk kepada subaset bagi aset berkelompok seperti kompleks atau bazar. | Wajib | A-13.02; URS-AST-01 |
| SRS-AST-05 | Sistem hendaklah merekodkan status kondisi aset mengikut skala baik, perlu perhatian dan kritikal, beserta sejarah lengkap perubahan status, tarikh dan pegawai yang merekodkan. | Wajib | A-13.02; URS-AST-01 |
| SRS-AST-06 | Sistem hendaklah menjana QR code unik bagi setiap aset secara automatik semasa pendaftaran, dengan rujukan QR yang tidak berulang dan tidak boleh diguna semula walaupun selepas aset diarkibkan. | Wajib | URS-AST-02 |
| SRS-AST-07 | Sistem hendaklah membolehkan QR code dicetak sebagai label tahan cuaca melalui templat label berbilang saiz yang mengandungi kod QR, nama aset, nombor rujukan dan identiti MAIS, serta menyokong cetakan kelompok. | Wajib | URS-AST-02 |
| SRS-AST-08 | Sistem hendaklah membolehkan imbasan QR di lapangan menggunakan kamera telefon pintar standard tanpa pemasangan aplikasi khas, dan imbasan membuka profil aset secara terus. | Wajib | URS-AST-02 |
| SRS-AST-09 | Sistem hendaklah menentukan kandungan profil aset dan pilihan tindakan selepas imbasan mengikut kebenaran pengimbas — pengguna berdaftar melihat profil penuh, pengimbas tanpa kebenaran hanya melihat maklumat pengenalan minimum. | Wajib | URS-AST-02 |
| SRS-AST-10 | Sistem hendaklah merekodkan log setiap imbasan QR termasuk cap masa, identiti pengimbas jika berdaftar, dan koordinat GPS peranti jika kebenaran lokasi diberikan. | Perlu | Jurang |
| SRS-AST-11 | Sistem hendaklah menyokong tagging bebas dan berstruktur bagi setiap aset, dan membolehkan senarai aset ditapis serta dilaporkan berdasarkan tag tersebut. | Perlu | URS-AST-03 |
| SRS-AST-12 | Sistem hendaklah menyediakan galeri gambar aset yang menyokong berbilang gambar bertarikh disusun kronologi, termasuk gambar daripada lawatan pemantauan, beserta metadata tarikh, koordinat GPS dan pegawai yang merakam. | Wajib | URS-AST-04 |
| SRS-AST-13 | Sistem hendaklah memaparkan lokasi aset pada Modul GIS sebagai lapisan aset yang boleh ditapis mengikut kategori, status kondisi dan daerah, serta memautkan setiap titik aset kepada rekod lot berkaitan. | Wajib | A-13.04 |
| SRS-AST-14 | Sistem hendaklah menyediakan rekod sejarah penyelenggaraan aset termasuk jenis, tarikh, kos, syarikat pelaksana dan status penyelenggaraan. | Wajib | A-13.05 |
| SRS-AST-15 | Sistem hendaklah menjana jadual penyelenggaraan berjadual bagi setiap aset berdasarkan kitaran berulang mengikut jenis dan kategori aset, dan memaparkannya dalam kalendar penyelenggaraan unit aset. | Wajib | A-13.08; URS-AST-05 |
| SRS-AST-16 | Sistem hendaklah menghantar notifikasi kepada pegawai bertanggungjawab sebelum tarikh penyelenggaraan berjadual mengikut tempoh amaran yang boleh dikonfigurasi, dan mengeskalasi kepada ketua unit apabila tertunggak. | Wajib | A-13.08; URS-AST-05 |
| SRS-AST-17 | Sistem hendaklah merekodkan setiap kerja penyelenggaraan beserta kos, kontraktor, gambar sebelum dan selepas, serta menyalurkan kos kepada graf kos kumulatif lot berkaitan. | Wajib | A-13.05; URS-AST-05 |
| SRS-AST-18 | Sistem hendaklah membolehkan pengemaskinian nilai semasa aset secara berkala berasaskan laporan penilaian terkini, dan menyimpan sejarah nilai mengikut tahun beserta rujukan laporan yang menjadi asas. | Wajib | A-13.06 |
| SRS-AST-19 | Sistem hendaklah menghantar notifikasi kepada pegawai bertanggungjawab apabila nilai aset memerlukan penilaian semula berdasarkan tempoh ambang yang ditetapkan. | Wajib | A-13.08 |
| SRS-AST-20 | Sistem hendaklah membolehkan pemeriksaan aset di lapangan melalui PWA yang sama dengan Modul Pemantauan Tanah mengikut aliran imbas QR, isi borang dan kemas kini status, dengan sokongan luar talian dan penyegerakan automatik. | Wajib | URS-AST-06 |
| SRS-AST-21 | Sistem hendaklah merekodkan tarikh pemeriksaan terakhir bagi setiap aset dan mengenal pasti aset yang tidak diperiksa melebihi tempoh yang ditetapkan. | Wajib | URS-AST-07 |
| SRS-AST-22 | Sistem hendaklah menyokong lampiran dokumen sokongan aset termasuk pelan bangunan, perakuan siap dan pematuhan, permit, polisi insurans dan dokumen pemilikan melalui DMS. | Perlu | Jurang |
| SRS-AST-23 | Sistem hendaklah merekodkan jejak audit penuh bagi setiap perubahan nilai aset, status kondisi dan pegawai bertanggungjawab, termasuk nilai sebelum, nilai selepas, pengguna dan cap masa. | Wajib | Jurang |
| SRS-AST-24 | Sistem hendaklah menyediakan laporan inventori aset mengikut kategori, lokasi atau daerah, status kondisi dan nilai. | Wajib | A-13.07 |
| SRS-AST-25 | Sistem hendaklah menyediakan laporan nilai aset keseluruhan portfolio beserta pecahan mengikut kategori dan daerah, dan perbandingan nilai antara tahun. | Wajib | A-13.07 |
| SRS-AST-26 | Sistem hendaklah menyediakan laporan pengurusan tambahan meliputi senarai aset kritikal, kos penyelenggaraan tahunan mengikut aset dan kategori, serta senarai aset tanpa pemeriksaan melebihi tempoh yang ditetapkan. | Wajib | URS-AST-07 |

**Aliran proses utama.**

1. Pegawai Unit Aset mendaftarkan aset baharu dengan memilih lot induk, kategori, nama, tahun pembinaan, saiz, nilai semasa dan pegawai bertanggungjawab.
2. Sistem menjana nombor rujukan dan QR code unik automatik serta meletakkan titik aset pada lapisan aset dalam Modul GIS.
3. Pegawai mencetak label QR tahan cuaca melalui templat, secara individu atau kelompok, dan label dilekatkan pada aset.
4. Pegawai lapangan mengimbas QR menggunakan kamera telefon; sistem membuka profil dengan kandungan mengikut kebenaran pengimbas dan merekodkan log imbasan.
5. Pegawai melaksanakan pemeriksaan melalui PWA — mengisi borang, merakam gambar bertarikh dan mengemas kini status kondisi; data disimpan luar talian dan disegerakkan.
6. Sistem mengemas kini status kondisi, menambah gambar kepada galeri kronologi, dan merekodkan tarikh pemeriksaan terakhir.
7. Sistem menjana jadual penyelenggaraan berdasarkan kitaran kategori aset dan memaparkannya dalam kalendar.
8. Notifikasi dihantar sebelum tarikh penyelenggaraan; dieskalasi kepada ketua unit apabila tertunggak.
9. Kerja penyelenggaraan dilaksanakan dan direkodkan beserta jenis, tarikh, kos, kontraktor dan gambar sebelum serta selepas; kos disalurkan kepada graf kos lot.
10. Bahagian Kewangan mengemas kini nilai semasa berasaskan laporan penilaian; sistem menyimpan sejarah nilai dan merekodkan jejak audit.
11. Sistem menghantar notifikasi penilaian semula apabila tempoh ambang dicapai.
12. Laporan inventori, nilai portfolio dan aset kritikal dijana; aset yang dirobohkan atau dilupuskan diarkibkan tanpa pemadaman.

**Entiti data.**

- `aset_fizikal` — id\_aset, no\_rujukan\_aset, qr\_ref, id\_lot, id\_aset\_induk, kategori\_aset, jenis\_aset, nama\_aset, alamat, latitud, longitud, tahun\_pembinaan, tahun\_perolehan, saiz\_keluasan, unit\_ukuran, nilai\_semasa, tarikh\_nilai\_semasa, status\_kondisi, id\_pegawai\_bertanggungjawab, tarikh\_pemeriksaan\_terakhir, status\_arkib
- `aset_kategori` — id\_kategori, nama\_kategori, kitaran\_penyelenggaraan\_bulan, tempoh\_ambang\_penilaian\_bulan, status\_aktif
- `aset_qr` — qr\_ref, id\_aset, tarikh\_jana, pautan\_awam, status\_aktif, templat\_label, bilangan\_cetakan
- `aset_qr_log_imbasan` — id\_log, qr\_ref, id\_aset, cap\_masa, id\_pengguna, peranan\_pengimbas, latitud\_peranti, longitud\_peranti, jenis\_paparan
- `aset_tag` — id\_aset, nama\_tag, jenis\_tag, id\_pengguna, tarikh\_tambah
- `aset_gambar` — id\_gambar, id\_aset, id\_pemeriksaan, laluan\_fail, tarikh\_gambar, latitud, longitud, id\_pegawai\_rakam, kategori\_gambar, keterangan
- `aset_kondisi_sejarah` — id\_aset, status\_lama, status\_baharu, tarikh\_perubahan, id\_pemeriksaan, id\_pengguna, catatan
- `aset_nilai_sejarah` — id\_aset, tahun, nilai, kaedah\_penilaian, id\_laporan\_penilaian, tarikh\_rekod, id\_pengguna
- `jadual_penyelenggaraan` — id\_jadual, id\_aset, jenis\_penyelenggaraan, kitaran\_bulan, tarikh\_jatuh\_tempoh, tempoh\_amaran\_hari, status, id\_pegawai\_bertanggungjawab
- `rekod_penyelenggaraan` — id\_rekod, id\_aset, id\_jadual, jenis\_penyelenggaraan, tarikh\_mula, tarikh\_siap, kos, id\_kontraktor, nama\_syarikat\_pelaksana, status, id\_gambar\_sebelum, id\_gambar\_selepas
- `aset_pemeriksaan` — id\_pemeriksaan, id\_aset, tarikh\_pemeriksaan, id\_pegawai, kaedah, status\_kondisi\_dilapor, isu\_dikenalpasti, tindakan\_disyorkan, status\_penyegerakan
- `aset_dokumen` — id\_aset, id\_dokumen\_dms, kategori\_dokumen, tarikh\_luput, tarikh\_muat\_naik
- `aset_audit` — id\_aset, medan\_diubah, nilai\_sebelum, nilai\_selepas, id\_pengguna, cap\_masa

**Integrasi.** Modul GIS bagi lapisan aset dan pautan kepada geometri lot; Modul Pemantauan Tanah bagi PWA bersama dan gambar lawatan; Pangkalan Data Induk dan Modul Hak Milik bagi pautan kepada rekod lot; Modul Tukar Syarat bagi pemindahan rekod aset apabila lot dipecah atau disatukan; DMS bagi dokumen sokongan; GRP MAIS bagi nilai aset dan kos penyelenggaraan; JPPH bagi laporan penilaian; Modul Notifikasi; Modul Pajakan bagi pautan aset kepada premis disewa; Modul Laporan; Modul Audit Trail.

**Kawalan keselamatan.**

- Paparan selepas imbasan QR ditentukan mengikut kebenaran pengimbas; pengimbas tanpa kebenaran hanya melihat maklumat pengenalan minimum tanpa nilai aset atau maklumat pegawai
- Pautan QR awam tidak mendedahkan pengecam dalaman pangkalan data dan boleh dinyahaktifkan secara individu
- Log penuh bagi setiap imbasan QR termasuk cap masa, identiti pengimbas dan lokasi peranti
- Perubahan nilai semasa aset hanya boleh dilakukan oleh peranan Bahagian Kewangan atau Unit Aset yang diberi kuasa, dengan jejak audit nilai sebelum dan selepas
- Kawalan capaian berasaskan peranan dan daerah bagi pengemaskinian rekod aset
- Gambar dan borang pemeriksaan yang disimpan luar talian dalam PWA disulitkan pada peranti sebelum penyegerakan
- Rekod aset yang dilupuskan diarkibkan, tidak dipadam, dan kekal boleh dirujuk untuk audit

**Laporan yang boleh dijana.** Inventori aset mengikut kategori, daerah, status kondisi dan nilai; nilai aset keseluruhan portfolio beserta pecahan kategori dan daerah serta perbandingan antara tahun; senarai aset kritikal; kos penyelenggaraan tahunan mengikut aset dan kategori; aset tanpa pemeriksaan melebihi tempoh; jadual penyelenggaraan akan datang dan tertunggak; sejarah nilai aset mengikut tahun; log imbasan QR mengikut aset dan tempoh.

**Kriteria penerimaan.**

1. Pendaftaran satu aset ujian menjana nombor rujukan dan QR code unik automatik, dan titik aset muncul pada lapisan aset dalam Modul GIS pada koordinat yang betul.
2. Label QR boleh dicetak secara individu dan secara kelompok bagi sekurang-kurangnya 20 aset sekali gus menggunakan templat label yang disediakan.
3. Imbasan QR menggunakan kamera telefon pintar standard membuka profil aset tanpa pemasangan aplikasi, dan pengimbas tanpa akaun hanya melihat maklumat pengenalan minimum tanpa nilai aset.
4. Satu pemeriksaan aset boleh dilaksanakan sepenuhnya dalam mod luar talian melalui PWA, dan data disegerakkan tanpa kehilangan rekod apabila talian dipulihkan.
5. Jadual penyelenggaraan dijana automatik mengikut kitaran kategori aset, notifikasi dihantar pada tempoh amaran yang dikonfigurasi, dan eskalasi berlaku apabila penyelenggaraan tertunggak.
6. Kos penyelenggaraan yang direkodkan muncul dengan betul dalam graf kos kumulatif lot tanah yang berkaitan.
7. Perubahan nilai aset oleh pengguna tanpa peranan yang dibenarkan ditolak, dan perubahan oleh pengguna yang dibenarkan direkodkan dalam jejak audit dengan nilai sebelum dan selepas.

### Modul 12 · GIS Mapping dan Kepintaran Spatial

**Kod:** MOD-GIS · **Gelombang:** G2 (Bulan 4.0–4.5) · **Rujukan:** RFP Seksyen 8.0 (8.1.1–8.2.5), Seksyen 6.0 (Modul 12); Lampiran H A-14.01–A-14.14, B-4.04

Objektif modul ini adalah menyediakan platform GIS bersepadu yang memaparkan lokasi, sempadan dan status hartanah MAIS melalui sepuluh (10) lapisan peta tematik wajib serta lapisan tambahan bernilai tambah, dan menyokong lima (5) fungsi analisis spatial bagi menilai kedudukan, risiko dan potensi setiap lot. Modul ini juga menjadi sumber tunggal perkhidmatan peta bagi dashboard, PWA pemantauan lapangan, portal awam dan sistem luar, dengan geometri kekal sebagai hak milik MAIS.

**Kategori pengguna.**

- Semua pengguna dalaman MAIS — paparan peta dan carian lot mengikut lapisan yang dibenarkan
- GIS Specialist / Pegawai Data Spatial MAIS — pengurusan lapisan, import data, pengesahan sempadan dan metadata
- Pegawai Daerah dan pegawai lapangan — paparan peta dan koordinat melalui PWA pemantauan
- Pengurusan tertinggi — paparan taburan aset melalui dashboard
- Pentadbir sistem — kawalan akses lapisan dan konfigurasi perkhidmatan peta
- Sistem luar yang diluluskan MAIS melalui perkhidmatan peta OGC dan API Gateway

**Keperluan fungsian**

| Kod | Keperluan | Keutamaan | Sumber |
| --- | --- | --- | --- |
| SRS-GIS-01 | Sistem hendaklah menyediakan modul GIS/kepintaran spatial yang memaparkan lokasi tanah, lot, sempadan, daerah, mukim, kategori tanah dan status penggunaan pada peta digital. | Wajib | A-14.01; URS-GIS-01 |
| SRS-GIS-02 | Sistem hendaklah merangkumi komponen platform GIS iaitu pelayan GIS, pangkalan data spatial, perkhidmatan peta, antaramuka GIS berasaskan web, pengurusan lapisan data, fungsi carian lokasi, paparan atribut, tapisan maklumat, pengukuran asas, analisis spatial dan kemas kini data spatial. | Wajib | RFP 8.1.2 |
| SRS-GIS-03 | Sistem hendaklah menyimpan geometri hartanah MAIS dalam pangkalan data spatial PostgreSQL/PostGIS sebagai satu-satunya rekod tuan pada SRID 4326 dengan indeks GIST, dan menyokong penukaran unjuran kepada GDM2000/Cassini-Soldner bagi keperluan ukur rasmi. | Wajib | URS-GIS-01; RFP 8.1.2 |
| SRS-GIS-04 | Syarikat hendaklah membekalkan atau menyokong perisian GIS termasuk tetapi tidak terhad kepada SuperMap atau setara, dan hendaklah menyatakan dengan jelas model pelesenan yang dicadangkan (lesen perpetual atau model lesen lain), bilangan lesen, had penggunaan, tempoh sokongan dan kos pembaharuan jangka panjang. | Wajib | A-14.02; RFP 8.1.3 |
| SRS-GIS-05 | Syarikat hendaklah mengemukakan Letter of Authorization (LOA) daripada principal atau pengeluar bagi perisian GIS yang dicadangkan sebagai hasil serahan semasa penyerahan cadangan. | Wajib | A-14.03 |
| SRS-GIS-06 | Syarikat hendaklah mengemukakan brosur, spesifikasi teknikal dan bukti kemampuan perisian GIS yang dicadangkan sebagai hasil serahan semasa penyerahan cadangan. | Wajib | A-14.04 |
| SRS-GIS-07 | Syarikat hendaklah menyediakan workstation GIS dengan spesifikasi yang sesuai bagi kegunaan GIS Specialist MAIS sekiranya dinyatakan dalam skop pembekalan, termasuk spesifikasi pemproses, memori, kad grafik, storan, paparan dan perisian GIS yang dipasang. | Wajib | A-14.14 |
| SRS-GIS-08 | Sistem hendaklah menyokong layer peta, overlay data, zoom, pan, search by location, identify feature dan paparan atribut spatial. | Wajib | A-14.05; URS-GIS-03 |
| SRS-GIS-09 | Sistem hendaklah memaparkan atribut lot apabila pengguna mengklik geometri pada peta, berserta pautan terus kepada Profil Hartanah 360° lot berkenaan. | Wajib | A-14.05; URS-GIS-03 |
| SRS-GIS-10 | Sistem hendaklah menyediakan alat pengukuran asas di atas peta bagi mengukur jarak dan keluasan secara interaktif. | Wajib | RFP 8.1.2; URS-GIS-03 |
| SRS-GIS-11 | Sistem hendaklah menyediakan sepuluh (10) lapisan peta wajib seperti diperincikan dalam jadual Lapisan Peta Wajib dan Sumber Data, iaitu Lapisan Hak Milik, Kategori Guna Tanah, Zoning, Status Pajakan, Status Cukai, Pemantauan Tanah, Aduan dan Isu Tanah, Kes Mahkamah/Perundangan, Risiko Hartanah dan Aset Fizikal. | Wajib | RFP 8.1.4; A-14.01; URS-GIS-02 |
| SRS-GIS-12 | Sistem hendaklah memberikan setiap lapisan wajib pewarnaan tematik dan legenda yang selaras dengan modul sumbernya, serta membolehkan pengguna menghidupkan, mematikan, menyusun dan melaraskan ketelusan setiap lapisan. | Wajib | RFP 8.1.4; URS-GIS-02 |
| SRS-GIS-13 | Sistem hendaklah membolehkan paparan tanah berdasarkan status termasuk tetapi tidak terhad kepada kosong, disewa, wakaf, dalam tindakan, bermasalah, cukai tertunggak atau berpotensi dibangunkan. | Wajib | A-14.06 |
| SRS-GIS-14 | Sistem hendaklah menyediakan lapisan peta tambahan bernilai tambah iaitu lapisan analitik, lapisan potensi pembangunan, lapisan hasil sewaan, lapisan nilai tanah, lapisan sempadan pentadbiran dan lapisan kemudahan awam. | Perlu | RFP 8.1.5 |
| SRS-GIS-15 | Sistem hendaklah menyediakan fungsi Buffer Analysis yang menganalisis kawasan sekitar sesuatu lot tanah berdasarkan jarak tertentu yang ditetapkan oleh pengguna, dan menyenaraikan ciri yang terkandung dalam zon penimbal tersebut. | Wajib | RFP 8.1.6(a); A-14.09 |
| SRS-GIS-16 | Sistem hendaklah menyediakan fungsi Overlay Analysis yang menganalisis pertindihan antara dua (2) atau lebih lapisan data bagi mengenal pasti hubungan, risiko, kategori atau potensi sesuatu kawasan, dan menghasilkan senarai lot yang bertindih. | Wajib | RFP 8.1.6(b); A-14.09 |
| SRS-GIS-17 | Sistem hendaklah menyediakan fungsi Spatial Query yang membolehkan carian maklumat berdasarkan lokasi, kawasan, sempadan, jarak, kategori atau kriteria spatial lain yang berkaitan, termasuk pemilihan kawasan secara lukisan poligon di atas peta. | Wajib | RFP 8.1.6(c); A-14.09 |
| SRS-GIS-18 | Sistem hendaklah menyediakan fungsi Area Calculation yang mengira keluasan, perimeter dan ukuran asas lot tanah berdasarkan sistem unjuran yang tepat, dan memaparkan unit ukuran yang digunakan. | Wajib | RFP 8.1.6(d); A-14.09 |
| SRS-GIS-19 | Sistem hendaklah menyediakan fungsi Proximity Analysis yang menganalisis kedekatan sesuatu lot tanah dengan kawasan pembangunan, kemudahan awam, infrastruktur, jalan utama, kawasan risiko atau parameter lain yang berkaitan. | Wajib | RFP 8.1.6(e); A-14.09 |
| SRS-GIS-20 | Sistem hendaklah membolehkan hasil setiap fungsi analisis spatial disimpan sebagai senario bernama, dikongsi mengikut kebenaran pengguna dan dieksport sebagai senarai lot dalam format Excel atau CSV. | Perlu | Jurang |
| SRS-GIS-21 | Sistem hendaklah menyediakan analisis spatial lanjutan termasuk clustering dan heatmap potensi aset bagi menyokong pengenalpastian kelompok risiko dan kelompok potensi pembangunan. | Baik-ada | B-4.04 |
| SRS-GIS-22 | Sistem hendaklah membolehkan pengguna yang dibenarkan memuat naik atau mengimport fail spatial yang dipersetujui termasuk shapefile, GeoJSON, KML/KMZ dan DXF (pelan akui juruukur), dengan pengesahan skema, unjuran dan topologi sebelum data diterima. | Wajib | A-14.08; URS-GIS-04 |
| SRS-GIS-23 | Sistem hendaklah menyokong integrasi atau penggunaan data spatial daripada sumber kerajaan yang diluluskan, iaitu data hak milik dan lot tanah daripada PTG/e-Tanah, data pelan ukur dan sempadan rasmi daripada JUPEM, data rancangan pemajuan dan zoning daripada JPBD, data lot tanah kebangsaan MyLand, peta asas berlesen serta perkhidmatan geospatial kerajaan MyGDI/Sismap, tertakluk kepada kebenaran pemilik data dan kelulusan MAIS. | Wajib | RFP 8.2.2; A-14.07; URS-GIS-04 |
| SRS-GIS-24 | Syarikat hendaklah menyediakan input teknikal, keperluan integrasi, spesifikasi data, pelan keselamatan dan dokumentasi sokongan bagi membantu MAIS memperoleh kelulusan rasmi, kebenaran teknikal, perjanjian perkongsian data atau akses API daripada pemilik data luaran. | Wajib | RFP 8.2.3 |
| SRS-GIS-25 | Sistem hendaklah menerbitkan perkhidmatan peta mengikut piawaian OGC (WMS/WFS/WMTS) supaya boleh digunakan oleh dashboard pengurusan, PWA pemantauan lapangan, portal awam (lapisan terhad) dan sistem luar melalui API Gateway. | Wajib | URS-GIS-05; RFP 8.1.2 |
| SRS-GIS-26 | Sistem hendaklah memastikan data spatial hartanah MAIS kekal menjadi hak milik MAIS dan boleh dieksport, dipindahkan, disandarkan atau diintegrasikan dengan sistem lain secara penuh tanpa kebergantungan kepada format proprietari. | Wajib | RFP 8.2.4 |
| SRS-GIS-27 | Sistem hendaklah melaksanakan semakan ketepatan koordinat bagi setiap geometri yang dimasukkan atau dikemas kini, termasuk pengesanan koordinat di luar sempadan negeri Selangor, koordinat kosong dan toleransi ketepatan yang ditetapkan MAIS. | Wajib | RFP 8.2.5 |
| SRS-GIS-28 | Sistem hendaklah melaksanakan pengesahan sempadan yang mengesan geometri tidak sah, pertindihan antara lot, jurang antara lot dan ralat topologi, serta menjana laporan ralat untuk tindakan pembetulan oleh GIS Specialist. | Wajib | RFP 8.2.5 |
| SRS-GIS-29 | Sistem hendaklah menyimpan metadata bagi setiap lapisan dan setiap set data spatial, merangkumi rekod sumber data, pemilik data, tarikh kemas kini, status pengesahan, sistem unjuran, tahap ketepatan dan had penggunaan data. | Wajib | RFP 8.2.5 |
| SRS-GIS-30 | Sistem hendaklah menyediakan kawalan versi data spatial yang menyimpan setiap versi geometri lot, membolehkan versi terdahulu dirujuk dan dipulihkan, serta membandingkan dua (2) versi geometri bagi lot yang sama. | Wajib | RFP 8.2.5 |
| SRS-GIS-31 | Sistem hendaklah menyediakan audit trail bagi perubahan data spatial dan atribut GIS yang merekod pengguna, cap masa, jenis perubahan, nilai geometri dan atribut sebelum dan selepas perubahan, sumber data serta justifikasi perubahan. | Wajib | A-14.13; RFP 8.2.5 |
| SRS-GIS-32 | Sistem hendaklah menguatkuasakan kawalan akses kepada layer GIS mengikut peranan pengguna pada peringkat perkhidmatan peta dan bukan pada peringkat paparan sahaja, termasuk lapisan berklasifikasi SULIT dan RAHSIA yang tidak boleh dicapai melalui permintaan terus kepada perkhidmatan peta. | Wajib | A-14.12; URS-GIS-06 |
| SRS-GIS-33 | Sistem hendaklah menyediakan keupayaan mencetak peta atau mengeksport paparan peta bersama maklumat atribut dalam format PDF atau imej, lengkap dengan tajuk, skala, legenda, arah utara, sistem unjuran, cap masa penjanaan dan identiti pengguna yang menjana. | Wajib | A-14.11 |
| SRS-GIS-34 | Sistem hendaklah menyediakan paparan peta dalam dashboard pengurusan bagi menunjukkan taburan aset tanah MAIS mengikut daerah, mukim dan kategori. | Wajib | A-14.10; B-1.02 |
| SRS-GIS-35 | Syarikat hendaklah menyatakan skop, pelesenan, latihan dan sokongan vendor GIS dalam cadangan teknikal, dan Syarikat kekal bertanggungjawab sepenuhnya terhadap penyampaian keseluruhan platform GIS kepada MAIS. | Wajib | URS-GIS-07; RFP 8.1.3 |

**Lapisan peta wajib dan sumber data**

| Bil. | Lapisan | Kandungan | Sumber data | Pewarnaan tematik |
| --- | --- | --- | --- | --- |
| 1 | Lapisan Hak Milik | Sempadan lot, nombor lot, kategori hak milik, status hak milik dan maklumat berkaitan pemilikan | Modul Hak Milik Tanah; geometri `lot_tanah` (PostGIS); PTG/e-Tanah; pelan ukur JUPEM | Warna mengikut kategori hak milik (kekal, sementara, belum berdaftar); lot tanpa hak milik sah ditanda garis putus |
| 2 | Lapisan Kategori Guna Tanah | Kategori guna tanah pertanian, bangunan, industri, hutan, wakaf, baitulmal dan kategori berkaitan | Pangkalan Data Induk Hartanah (`lot_tanah.kategori_guna_tanah`); PTG | Palet kategori diskret (contoh hijau pertanian, kelabu bangunan, ungu industri, hijau tua hutan, biru wakaf, perang baitulmal) |
| 3 | Lapisan Zoning | Zon perancangan dan kawasan pembangunan berdasarkan rancangan pemajuan tempatan yang diluluskan | JPBD; rancangan tempatan PBT; MyGDI/Sismap | Palet zon perancangan mengikut piawaian rancangan tempatan, dengan sempadan zon bergaris tebal |
| 4 | Lapisan Status Pajakan | Status pajakan aktif, hampir tamat, tamat tempoh dan belum dipajak | Modul Pajakan dan Kontrak | Hijau aktif, kuning hampir tamat, merah tamat tempoh, kelabu belum dipajak |
| 5 | Lapisan Status Cukai | Status cukai selesai, tertunggak, belum dibayar atau memerlukan tindakan susulan | Modul Pemantauan Cukai Tanah; integrasi PBT/Pejabat Tanah | Hijau selesai, kuning belum dibayar, merah tertunggak, merah gelap tertunggak melebihi ambang MAIS |
| 6 | Lapisan Pemantauan Tanah | Jadual pemantauan, tarikh lawatan terakhir, status pemeriksaan, isu lapangan dan tindakan susulan | Modul Pemantauan Tanah; koordinat GPS daripada PWA lapangan | Gradasi mengikut kemunasabahan lawatan terakhir (hijau dalam tempoh, kuning hampir tempoh, merah tertunggak lawatan) |
| 7 | Lapisan Aduan dan Isu Tanah | Lokasi aduan, kategori isu, status tindakan dan rekod aduan berulang bagi lot yang sama | Modul Aduan dan Isu Tanah | Penanda titik mengikut kategori aduan; saiz penanda mengikut bilangan aduan berulang; merah untuk aduan terbuka melebihi SLA |
| 8 | Lapisan Kes Mahkamah / Perundangan | Lot yang terlibat kes perundangan, status kes dan tindakan susulan | Modul Kes Mahkamah / Perundangan | Corak hachure merah bagi lot berkes aktif; kelabu bagi kes selesai |
| 9 | Lapisan Risiko Hartanah | Indikator risiko tanah terbiar, pencerobohan, tunggakan cukai, pajakan tamat tempoh dan isu pemilikan | Analitik MOD-LAP merentas modul pemantauan, cukai, pajakan dan hak milik | Heatmap gradasi risiko (hijau rendah, kuning sederhana, oren tinggi, merah kritikal) |
| 10 | Lapisan Aset Fizikal | Lokasi bangunan, premis, infrastruktur dan aset fizikal lain di atas tanah MAIS | Modul Inventori Aset Fizikal; koordinat GPS aset | Ikon mengikut kategori aset; warna ikon mengikut status keadaan (baik, perlu perhatian, kritikal) |

**Aliran proses utama.**

1. GIS Specialist memuat naik atau mengimport fail spatial (shapefile, GeoJSON, KML/KMZ, DXF) atau menerima data daripada sumber kerajaan yang diluluskan.
2. Sistem melaksanakan semakan ketepatan koordinat, pengesahan sempadan dan pengesahan unjuran, kemudian menjana laporan ralat bagi geometri yang gagal.
3. GIS Specialist membetulkan ralat, melengkapkan metadata (sumber data, tarikh kemas kini, status pengesahan) dan mengesahkan set data untuk penerbitan.
4. Sistem menyimpan geometri yang disahkan dalam PostGIS, mencipta versi baharu data spatial dan merekod perubahan dalam audit trail.
5. Sistem menerbitkan lapisan melalui perkhidmatan peta OGC dengan kawalan akses mengikut peranan dan klasifikasi lapisan.
6. Pengguna membuka antaramuka peta, memilih lapisan, menapis atribut dan mengklik lot untuk melihat atribut serta Profil Hartanah 360°.
7. Pengguna melaksanakan fungsi analisis spatial (Buffer, Overlay, Spatial Query, Area Calculation, Proximity), menyimpan hasil sebagai senario dan mengeksport senarai lot.
8. Pengguna mencetak atau mengeksport paparan peta bersama atribut dalam format PDF atau imej; sistem merekod penjanaan dalam audit trail.

**Entiti data.** `lot_tanah` (no\_lot, mukim, daerah, keluasan, kategori\_guna\_tanah, zoning, geom POLYGON SRID 4326, indeks GIST) sebagai entiti induk geometri. Entiti modul: `gis_lapisan` (kod\_lapisan, nama, jenis\_geometri, klasifikasi\_keselamatan, status\_terbit, urutan\_paparan), `gis_metadata_lapisan` (kod\_lapisan, sumber\_data, pemilik\_data, tarikh\_kemas\_kini, status\_pengesahan, sistem\_unjuran, tahap\_ketepatan, had\_penggunaan), `gis_versi_geometri` (id\_lot, no\_versi, geom, tarikh\_berkuat\_kuasa, id\_pengguna, punca\_perubahan), `gis_log_import` (id\_import, nama\_fail, format, bilangan\_ciri, bilangan\_ralat, status, id\_pengguna), `gis_ralat_kualiti` (id\_ralat, id\_lot, jenis\_ralat, perihal, status\_pembetulan), `gis_senario_analisis` (id\_senario, jenis\_analisis, parameter\_json, id\_pengguna, kebenaran\_kongsi), `gis_aset_titik` (id\_aset, id\_lot, geom POINT, kategori, status\_keadaan) dan `gis_audit_spatial` (id\_audit, id\_lot, kod\_lapisan, jenis\_perubahan, nilai\_sebelum, nilai\_selepas, sumber, justifikasi, id\_pengguna, cap\_masa).

**Integrasi.**

- PTG/e-Tanah — data hak milik, lot tanah dan maklumat tanah, tertakluk kepada kebenaran pemilik data
- JUPEM — pelan ukur, sempadan rasmi dan maklumat ukur
- JPBD dan PBT — rancangan pemajuan, zoning, guna tanah dan perancangan pembangunan
- MyLand — data lot tanah kebangsaan
- MyGDI/Sismap — peta asas, topografi, infrastruktur dan sumber geospatial kerajaan yang diluluskan
- Perkhidmatan peta asas berlesen, tertakluk kepada terma penggunaan dan kelulusan MAIS
- Modul dalaman berlokasi — Pangkalan Data Induk, Hak Milik, Pajakan dan Kontrak, Pemantauan Cukai, Pemantauan Tanah, Aduan, Kes Perundangan, Inventori Aset Fizikal dan Tukar Syarat/Pelupusan/Ukur
- MOD-DSH dan MOD-LAP — perkhidmatan peta bagi widget dashboard dan lapisan analitik
- API Gateway — capaian sistem luar dan portal awam kepada lapisan terhad

**Kawalan keselamatan.**

- Kawalan akses lapisan mengikut peranan dikuatkuasakan pada peringkat perkhidmatan peta (WMS/WFS/WMTS), bukan pada peringkat antaramuka sahaja
- Lapisan berklasifikasi SULIT dan RAHSIA diasingkan daripada kumpulan lapisan awam yang digunakan portal aduan
- Audit trail penuh bagi setiap perubahan geometri dan atribut GIS, termasuk nilai sebelum dan selepas
- Kawalan versi data spatial menyokong pemulihan geometri terdahulu sebagai mekanisme pemulihan daripada perubahan tersilap
- Muat naik fail spatial dihadkan kepada peranan yang dibenarkan, disaring format dan saiz, dan diimbas sebelum diproses
- Eksport dan cetakan peta diberi cap masa serta identiti pengguna dan direkod dalam audit trail
- Had kadar permintaan dikenakan pada perkhidmatan peta bagi capaian luar melalui API Gateway

**Laporan yang boleh dijana.** Taburan aset tanah MAIS mengikut daerah, mukim, kategori guna tanah dan zoning; hasil analisis spatial (Buffer, Overlay, Spatial Query, Area Calculation, Proximity) berserta senarai lot terlibat; kualiti data spatial — ralat koordinat, ralat topologi, lot tanpa geometri dan lot belum disahkan; metadata lapisan termasuk sumber data, tarikh kemas kini dan status pengesahan setiap lapisan; audit perubahan data spatial dan atribut GIS mengikut tempoh, pengguna atau lot; import/eksport data spatial termasuk bilangan ciri berjaya dan gagal; cetakan peta tematik (PDF atau imej) bagi mana-mana lapisan atau gabungan lapisan berserta jadual atribut.

**Kriteria penerimaan.**

1. Kesepuluh (10) lapisan wajib RFP 8.1.4 dapat dipaparkan dengan pewarnaan tematik dan legenda masing-masing, dan boleh dihidupkan atau dimatikan secara berasingan semasa UAT.
2. Kelima-lima (5) fungsi analisis spatial RFP 8.1.6 — Buffer Analysis, Overlay Analysis, Spatial Query, Area Calculation dan Proximity Analysis — dapat dilaksanakan secara berasingan dan menghasilkan senarai lot yang boleh disemak.
3. Fail shapefile, GeoJSON, KML/KMZ dan DXF berjaya diimport, dan fail yang mengandungi geometri tidak sah atau koordinat di luar Selangor ditolak dengan laporan ralat yang menamakan ciri bermasalah.
4. Pengguna tanpa kebenaran lapisan SULIT tidak dapat memperoleh data lapisan tersebut walaupun memanggil perkhidmatan peta secara terus, dan percubaan tersebut direkod.
5. Paparan peta berserta jadual atribut berjaya dicetak dan dieksport dalam format PDF dan imej lengkap dengan skala, legenda, arah utara dan cap masa.
6. Perubahan geometri sesuatu lot menghasilkan versi baharu, mengekalkan versi terdahulu untuk rujukan, dan muncul dalam audit trail dengan nilai sebelum dan selepas.
7. LOA principal, brosur dan spesifikasi teknikal perisian GIS, serta spesifikasi workstation GIS diterima dan disahkan lengkap oleh MAIS.

### Modul 16 · Laporan dan Analitik

**Kod:** MOD-LAP · **Gelombang:** G5 (Bulan 5.0–5.5) · **Rujukan:** RFP Seksyen 6.0 (Modul 16); Lampiran H B-1.09–B-1.12, B-4.01–B-4.06

Objektif modul ini adalah menyediakan penjanaan laporan berkala dan ad hoc serta analitik lanjutan di atas platform BI sumber terbuka yang matang, dengan keupayaan drill-down hingga ke rekod asal dan eksport dalam format yang diluluskan MAIS. Modul ini juga menyediakan lapisan analitik dan AI bernilai tambah bagi mengenal pasti potensi hasil, risiko tunggakan, tanah tidak produktif dan tanah berpotensi dibangunkan, serta merumuskan isu utama untuk pengurusan.

**Kategori pengguna.**

- Pengurusan tertinggi MAIS — executive insight summary dan laporan portfolio
- Ketua Bahagian dan Ketua Unit — laporan prestasi bahagian, kewangan, operasi dan risiko
- Pegawai Daerah dan pegawai operasi — laporan ad hoc dalam skop daerah masing-masing
- Bahagian Kewangan MAIS — laporan hasil, kos dan tunggakan
- Unit Audit Dalam MAIS — laporan pematuhan dan jejak penjanaan laporan
- Pentadbir sistem — katalog laporan, definisi metrik, jadual penjanaan dan kebenaran laporan

**Keperluan fungsian**

| Kod | Keperluan | Keutamaan | Sumber |
| --- | --- | --- | --- |
| SRS-LAP-01 | Sistem hendaklah menggunakan platform BI sumber terbuka yang matang tanpa kos pelesenan, dengan cadangan utama Metabase dan alternatif Apache Superset bagi analitik lanjutan, dan pilihan akhir hendaklah disahkan bersama MAIS semasa CRP-2 melalui demonstrasi kedua-dua platform. | Wajib | URS-LAP-01 |
| SRS-LAP-02 | Sistem hendaklah menyediakan set laporan standard yang dibina dan disahkan semasa CRP-2, meliputi sekurang-kurangnya kategori portfolio, kewangan (hasil, kos dan tunggakan), operasi (SLA, pemantauan dan aduan), risiko dan pematuhan. | Wajib | B-1.09; URS-LAP-04 |
| SRS-LAP-03 | Sistem hendaklah menyediakan keupayaan laporan ad hoc yang membolehkan pengguna bukan teknikal memilih medan, penapis, pengumpulan, susunan dan tempoh tanpa pengaturcaraan, dan menyimpan definisi laporan tersebut untuk kegunaan semula. | Wajib | B-1.09; URS-LAP-02 |
| SRS-LAP-04 | Sistem hendaklah membolehkan setiap laporan standard dan ad hoc dieksport dalam format PDF, Excel dan CSV. | Wajib | B-1.09 |
| SRS-LAP-05 | Sistem hendaklah membolehkan carta dan dashboard dieksport sebagai imej bagi kegunaan pembentangan dan minit mesyuarat. | Perlu | URS-LAP-06 |
| SRS-LAP-06 | Sistem hendaklah menyokong penjanaan laporan berkala secara automatik mengikut jadual harian, mingguan, bulanan atau suku tahun, dan menghantar laporan tersebut melalui e-mel kepada senarai penerima yang ditetapkan. | Wajib | B-1.10; URS-LAP-04 |
| SRS-LAP-07 | Sistem hendaklah menjana laporan berskala besar di latar belakang dengan notifikasi kepada pengguna apabila laporan siap, dan memastikan tiada pertanyaan pelaporan menjejaskan prestasi sistem transaksi. | Wajib | URS-LAP-07 |
| SRS-LAP-08 | Sistem hendaklah melaksanakan semua pertanyaan pelaporan pada replika baca dengan skop RBAC dikuatkuasakan pada peringkat pertanyaan (row-level security), bukan pada peringkat paparan sahaja. | Wajib | URS-LAP-05; B-1.11 |
| SRS-LAP-09 | Sistem hendaklah menyediakan kawalan akses laporan berdasarkan peranan pengguna, dan dashboard atau laporan yang dikongsi hendaklah hanya boleh dibuka oleh pengguna yang mempunyai kebenaran terhadap data yang terkandung di dalamnya. | Wajib | B-1.11 |
| SRS-LAP-10 | Sistem hendaklah merekod setiap penjanaan dan eksport laporan dalam audit trail bersama parameter yang digunakan, identiti pengguna, cap masa dan bilangan rekod yang dikeluarkan. | Wajib | URS-LAP-06 |
| SRS-LAP-11 | Sistem hendaklah mencetak pada setiap laporan timestamp kemas kini data terakhir, tempoh data yang diliputi dan parameter penapis yang digunakan. | Wajib | B-1.12 |
| SRS-LAP-12 | Sistem hendaklah menyokong drill-down berperingkat pada setiap carta dan laporan, iaitu daripada nilai agregat kepada kumpulan, kepada senarai rekod dan seterusnya kepada rekod asal dalam SISDATA. | Wajib | URS-LAP-03; B-1.08 |
| SRS-LAP-13 | Sistem hendaklah membolehkan pengguna meminta laporan atau dashboard melalui AI prompt dalam bahasa biasa, dan hasil yang dijana hendaklah boleh disimpan, dikongsi mengikut kebenaran atau diubah suai lanjut. | Perlu | URS-LAP-02; B-4.03 |
| SRS-LAP-14 | Sistem hendaklah menyediakan carian pintar yang membolehkan pengguna mencari rekod hartanah menggunakan kata kunci semula jadi tanpa perlu mengetahui nama medan atau kod rujukan. | Perlu | B-4.03 |
| SRS-LAP-15 | Sistem hendaklah menyediakan analitik potensi hasil yang mengenal pasti lot dan premis yang berpotensi menjana hasil sewaan tambahan berdasarkan status pajakan, kadar pasaran dan lokasi. | Perlu | B-4.01 |
| SRS-LAP-16 | Sistem hendaklah menyediakan analitik risiko cukai tertunggak yang menilai kebarangkalian tunggakan berdasarkan sejarah pembayaran, tempoh tunggakan dan kategori pemilikan, serta menyenaraikan lot mengikut aras risiko. | Perlu | B-4.01 |
| SRS-LAP-17 | Sistem hendaklah menyediakan analitik tanah tidak produktif yang mengenal pasti lot terbiar atau tidak digunakan berdasarkan rekod pemantauan, status pajakan dan kategori guna tanah. | Perlu | B-4.01 |
| SRS-LAP-18 | Sistem hendaklah menyediakan analitik tanah berpotensi dibangunkan dan analisis corak penggunaan aset, dengan hasil analisis boleh dipaparkan sebagai lapisan analitik pada MOD-GIS. | Perlu | B-4.01; RFP 8.1.5 |
| SRS-LAP-19 | Sistem hendaklah menyediakan fungsi AI atau smart recommendation yang mencadangkan tindakan susulan berdasarkan status tanah, aras risiko dan data sejarah, dan setiap cadangan hendaklah boleh diterima atau ditolak oleh pegawai dengan justifikasi yang direkod. | Perlu | B-4.02 |
| SRS-LAP-20 | Sistem hendaklah menyediakan executive insight summary yang merumuskan isu utama, risiko dan tindakan segera untuk pengurusan, dan dijana secara automatik mengikut kekerapan yang ditetapkan MAIS. | Perlu | B-4.05 |
| SRS-LAP-21 | Sistem hendaklah melabelkan setiap keputusan analitik dan cadangan AI sebagai cadangan sistem yang tidak menggantikan keputusan rasmi MAIS, dan melaksanakan kesemua model analitik serta AI dalam persekitaran awan persendirian MAIS tanpa penghantaran data kepada perkhidmatan AI awam. | Wajib | Jurang; URS-DSH-10 |
| SRS-LAP-22 | Syarikat hendaklah mengemukakan cadangan pelan peningkatan sistem bagi tempoh tiga (3) hingga lima (5) tahun akan datang, merangkumi modul tambahan, integrasi baharu dan teknologi relevan yang disokong oleh analitik penggunaan sistem, dan pelan tersebut hendaklah dikemas kini setiap tahun dalam tempoh jaminan. | Perlu | B-4.06 |
| SRS-LAP-23 | Sistem hendaklah menyediakan katalog laporan berpusat yang menyenaraikan setiap laporan standard bersama definisi metrik, sumber data dan pemilik laporan sebagai data dictionary pelaporan. | Baik-ada | Jurang |

**Aliran proses utama.**

1. Pentadbir atau pengguna yang dibenarkan memilih laporan standard daripada katalog laporan, atau membina laporan ad hoc melalui pembina laporan atau AI prompt.
2. Sistem mengesahkan kebenaran pengguna dan mengenakan skop RBAC pada peringkat pertanyaan sebelum pertanyaan dilaksanakan.
3. Sistem melaksanakan pertanyaan pada replika baca; laporan berskala besar dimasukkan ke dalam baris gilir untuk penjanaan latar belakang.
4. Sistem memaparkan hasil dalam bentuk carta dan jadual, berserta timestamp kemas kini data terakhir dan parameter penapis yang digunakan.
5. Pengguna membuat drill-down daripada agregat hingga ke rekod asal, atau mengeksport laporan dalam format PDF, Excel, CSV atau imej.
6. Sistem merekod penjanaan dan eksport dalam audit trail bersama parameter dan bilangan rekod.
7. Bagi laporan berjadual, sistem menjana laporan pada masa yang ditetapkan dan mengedarkannya melalui e-mel kepada senarai penerima.
8. Enjin analitik menjalankan analisis berkala (potensi hasil, risiko tunggakan, tanah tidak produktif, potensi pembangunan), menerbitkan hasil kepada lapisan analitik MOD-GIS dan menjana executive insight summary untuk pengurusan.

**Entiti data.** Modul ini membaca kesemua entiti teras melalui replika baca dan tidak menulis rekod induk. Entiti modul: `lap_katalog_laporan` (kod\_laporan, nama, kategori, definisi\_metrik, sumber\_data, pemilik, peranan\_dibenarkan), `lap_definisi_laporan` (id\_definisi, kod\_laporan, pertanyaan, parameter\_lalai, id\_pencipta, status\_kongsi), `lap_jadual_penjanaan` (id\_jadual, id\_definisi, kekerapan, masa\_jalan, senarai\_penerima, status\_aktif), `lap_log_penjanaan` (id\_log, id\_definisi, parameter\_digunakan, format\_eksport, bilangan\_rekod, id\_pengguna, cap\_masa, status), `lap_hasil_analitik` (id\_hasil, jenis\_analitik, id\_lot, skor, aras\_risiko, tarikh\_kira, versi\_model), `lap_cadangan_ai` (id\_cadangan, id\_lot, jenis\_tindakan, asas\_cadangan, status\_keputusan, justifikasi\_pegawai, id\_pegawai) dan `lap_insight_eksekutif` (id\_insight, tempoh, isu\_utama, risiko, tindakan\_segera, tarikh\_jana).

**Integrasi.**

- Platform BI (Metabase atau Apache Superset) melalui SSO MAIS yang sama dengan SISDATA
- Replika baca pangkalan data SISDATA — sumber kesemua pertanyaan pelaporan dan analitik
- MOD-DSH — enjin AI prompt yang dikongsi dan pautan daripada widget dashboard kepada laporan penuh
- MOD-GIS — penerbitan hasil analitik sebagai lapisan analitik, lapisan potensi pembangunan, lapisan hasil sewaan dan lapisan nilai tanah
- Modul Notifikasi dan e-mel MAIS — edaran laporan berjadual dan notifikasi laporan siap
- Modul Audit Trail — perekodan penjanaan dan eksport laporan
- Modul Pengurusan Pengguna, Peranan dan Capaian — penentuan row-level security dan kebenaran laporan

**Kawalan keselamatan.**

- Row-level security dikuatkuasakan pada peringkat pertanyaan bagi semua laporan, dashboard dan hasil analitik
- Setiap penjanaan dan eksport laporan direkod dalam audit trail bersama parameter dan bilangan rekod
- Dashboard dan laporan yang dikongsi tetap menguatkuasakan kebenaran penerima dan bukan kebenaran pencipta
- Laporan yang mengandungi data berklasifikasi SULIT atau RAHSIA diberi tanda klasifikasi dan tanda air identiti pengguna pada eksport
- Kesemua model analitik dan AI dilaksanakan dalam persekitaran awan persendirian MAIS tanpa penghantaran data ke perkhidmatan AI awam
- Pertanyaan pelaporan dilaksanakan pada replika baca dengan had masa dan had saiz hasil bagi melindungi sistem transaksi

**Laporan yang boleh dijana.** Portfolio hartanah mengikut daerah, mukim, kategori guna tanah, zoning dan status hak milik; kewangan — hasil sewaan, kos penyelenggaraan, tunggakan cukai dan tunggakan sewaan; operasi — pencapaian SLA, pemantauan tanah, aduan dan tindakan tertunggak; risiko — lot berisiko tinggi, tanah terbiar, pencerobohan dan pajakan tamat tempoh; pematuhan dan audit — jejak penjanaan laporan, capaian data dan eksport; analitik potensi hasil, potensi pembangunan dan corak penggunaan aset; executive insight summary berkala untuk pengurusan tertinggi MAIS; cadangan pelan peningkatan sistem tiga (3) hingga lima (5) tahun sebagai dokumen serahan berkala.

**Kriteria penerimaan.**

1. Set laporan standard bagi kategori portfolio, kewangan, operasi, risiko dan pematuhan disahkan MAIS semasa CRP-2 dan berfungsi dalam UAT.
2. Setiap laporan berjaya dieksport dalam kesemua tiga (3) format PDF, Excel dan CSV dengan nilai yang konsisten antara format.
3. Laporan berjadual dijana pada masa yang ditetapkan dan diterima melalui e-mel oleh senarai penerima tanpa campur tangan manual.
4. Pengguna dengan skop daerah terhad yang membuka laporan negeri hanya menerima baris data dalam skopnya, dan ujian pertanyaan terus ke replika baca mengesahkan penguatkuasaan row-level security.
5. Drill-down daripada nilai agregat sampai kepada rekod asal SISDATA berfungsi pada setiap laporan standard.
6. Penjanaan laporan berskala besar tidak menyebabkan penurunan prestasi sistem transaksi melebihi ambang yang ditetapkan dalam ujian prestasi, dan pengguna menerima notifikasi apabila laporan siap.

### Modul T1 · Tiket Sokongan Sistem

**Kod:** MOD-TKT · **Gelombang:** G5 (Bulan 5.0–5.5) · **Rujukan:** RFP Seksyen 18.5; Lampiran H A-21.01–A-21.03, A-15.03

Menyediakan sistem tiket sokongan yang terbina dalam SISDATA supaya pengguna boleh melaporkan masalah tanpa keluar daripada aplikasi, dengan konteks skrin ditangkap secara automatik, SLA dikuatkuasakan mengikut tahap keterukan dan eskalasi dilakukan secara automatik. Setiap tiket yang diselesaikan menyumbang draf artikel kepada Modul K1, dan keseluruhan modul boleh diintegrasikan dua hala dengan Sistem Aduan berpusat MAIS melalui API Gateway.

**Kategori pengguna.**

- Semua pengguna SISDATA sebagai pelapor tiket
- Pasukan sokongan Syarikat semasa tempoh jaminan dan sokongan pasca pelaksanaan
- Pegawai Bahagian Teknologi Maklumat (BTM) MAIS sebagai pasukan sokongan selepas pemindahan teknologi
- Penyelia sokongan dan pengurus projek sebagai penerima eskalasi SLA
- Pentadbir sistem — konfigurasi kategori, keterukan, ambang SLA dan hierarki eskalasi
- Sistem Aduan berpusat MAIS sebagai pengguna sistem (system-to-system) melalui API

**Keperluan fungsian**

| Kod | Keperluan | Keutamaan | Sumber |
| --- | --- | --- | --- |
| SRS-TKT-01 | Sistem hendaklah menyediakan butang bantuan yang membolehkan pengguna membuka tiket sokongan dari mana-mana skrin SISDATA tanpa perlu keluar daripada aplikasi atau kehilangan kerja yang sedang dilakukan. | Wajib | URS-TKT-01; A-21.02 |
| SRS-TKT-02 | Sistem hendaklah menangkap konteks secara automatik apabila tiket dibuka, merangkumi skrin semasa, modul, URL/kod fungsi, peranan pengguna, ID pengguna, pelayar, versi sistem dan rujukan rekod yang sedang dibuka, supaya pasukan sokongan tidak perlu bertanya semula. | Wajib | URS-TKT-01 |
| SRS-TKT-03 | Sistem hendaklah membenarkan lampiran tangkapan skrin dan fail sokongan, dengan tangkapan skrin hanya dimuat naik selepas persetujuan eksplisit pengguna dan tertakluk kepada kawalan jenis fail, saiz fail dan imbasan keselamatan fail. | Wajib | URS-TKT-01; B-3.06 |
| SRS-TKT-04 | Sistem hendaklah menjana nombor rujukan tiket yang unik secara automatik dan merekod bagi setiap tiket: kategori (ralat/soalan/permintaan perubahan/capaian), tahap keterukan, modul berkaitan, penerangan, lampiran dan pelapor. | Wajib | URS-TKT-02; A-11.02 |
| SRS-TKT-05 | Sistem hendaklah menguruskan kitaran hayat tiket mengikut status baharu → disiasat → dalam tindakan → selesai → disahkan pengguna → tutup, dan merekod cap masa serta pegawai bertanggungjawab bagi setiap peralihan status. | Wajib | URS-TKT-02 |
| SRS-TKT-06 | Sistem hendaklah mencadangkan jawapan daripada Pangkalan Pengetahuan Berbantu AI secara automatik berdasarkan penerangan yang ditaip pengguna, **sebelum** tiket dihantar, dan memaparkan sekurang-kurangnya tiga artikel paling relevan dengan pautan terus kepada kandungan penuh. | Wajib | URS-TKT-04 |
| SRS-TKT-07 | Sistem hendaklah merekod sama ada pengguna meneruskan penghantaran tiket selepas cadangan Pangkalan Pengetahuan dipaparkan, bagi mengukur kadar penyelesaian kendiri (self-service deflection) dan mengenal pasti artikel yang tidak berkesan. | Baik-ada | Jurang |
| SRS-TKT-08 | Sistem hendaklah menguatkuasakan SLA mengikut tahap keterukan dengan ambang masa respons dan masa penyelesaian yang boleh dikonfigurasi oleh pentadbir tanpa pengubahsuaian kod sumber, selaras dengan tahap Severity 1 hingga Severity 4 dan sasaran SLA yang dipersetujui dalam kontrak. | Wajib | URS-TKT-03; A-21.01; RFP 18.5.2 |
| SRS-TKT-09 | Sistem hendaklah menghantar amaran automatik kepada pegawai bertanggungjawab apabila tiket menghampiri ambang SLA, dan melaksanakan eskalasi automatik kepada penyelia mengikut hierarki peranan yang dikonfigurasi apabila ambang SLA dilepasi. | Wajib | URS-TKT-03; A-3.07; A-3.14 |
| SRS-TKT-10 | Sistem hendaklah menugaskan tiket kepada pasukan atau juruteknik sokongan secara automatik berdasarkan modul, kategori dan tahap keterukan, serta membenarkan penugasan semula secara manual dengan rekod sebab penugasan semula. | Perlu | Jurang; A-11.04; A-11.07 |
| SRS-TKT-11 | Sistem hendaklah merekod kronologi lengkap setiap tiket termasuk semua tindakan, ulasan dalaman, komunikasi dengan pelapor, lampiran tambahan dan perubahan keterukan atau kategori, dengan pembezaan jelas antara catatan dalaman dan catatan yang kelihatan kepada pelapor. | Wajib | URS-TKT-08; A-16.10 |
| SRS-TKT-12 | Sistem hendaklah memerlukan pengesahan pelapor sebelum tiket ditutup, membenarkan tiket dibuka semula dalam tempoh yang dikonfigurasi selepas status selesai, dan menutup tiket secara automatik sekiranya tiada maklum balas pelapor selepas tempoh peringatan yang ditetapkan. | Wajib | URS-TKT-02 |
| SRS-TKT-13 | Sistem hendaklah menjana draf artikel penyelesaian ke Pangkalan Pengetahuan Berbantu AI bagi setiap tiket yang selesai, berstruktur masalah → punca → penyelesaian, dengan rujukan modul dan versi sistem, untuk semakan dan penerbitan oleh pasukan sokongan. | Wajib | URS-TKT-05 |
| SRS-TKT-14 | Sistem hendaklah menyediakan API tiket melalui API Gateway bagi integrasi dua hala dengan Sistem Aduan berpusat MAIS, membolehkan tiket dibuka daripada sistem berpusat dan status serta penyelesaian disegerakkan kembali secara automatik. | Wajib | URS-TKT-06; A-15.03 |
| SRS-TKT-15 | Sistem hendaklah menyediakan pemetaan medan, kategori dan tahap keterukan antara Modul T1 dan Sistem Aduan berpusat MAIS yang boleh dikonfigurasi, serta mengekalkan rujukan silang nombor tiket dan nombor aduan bagi kedua-dua sistem. | Wajib | URS-TKT-06; A-15.01 |
| SRS-TKT-16 | Sistem hendaklah memastikan penyegerakan dua hala bersifat idempoten bagi mengelakkan tiket atau kemas kini status bertindih, serta menyediakan audit trail integrasi, log transaksi, mekanisme retry dan rekonsiliasi bagi penyegerakan yang gagal. | Wajib | A-15.04; A-15.05; A-15.08 |
| SRS-TKT-17 | Sistem hendaklah menyediakan papan pemuka sokongan yang memaparkan tiket terbuka mengikut keterukan dan modul, prestasi pencapaian SLA, trend masalah berulang, modul dengan tiket tertinggi dan masa purata penyelesaian, dengan keupayaan drill-down kepada senarai dan rekod tiket. | Wajib | URS-TKT-07; B-1.08; A-11.10 |
| SRS-TKT-18 | Sistem hendaklah merekod keseluruhan kitaran hayat tiket dalam audit trail yang tamper-evident, merangkumi nilai asal dan nilai baharu, ID pengguna, alamat IP, cap masa dan modul bagi setiap perubahan. | Wajib | URS-TKT-08; A-16.09; A-16.11 |
| SRS-TKT-19 | Sistem hendaklah menjana laporan tiket bulanan secara automatik yang merangkumi jumlah isu, kategori, severity, status, pematuhan SLA dan tindakan pembetulan, sebagai sebahagian laporan sokongan berkala tempoh jaminan, dengan keupayaan eksport PDF dan Excel. | Wajib | URS-TKT-08; A-21.03; B-1.09 |
| SRS-TKT-20 | Sistem hendaklah mengawal capaian rekod tiket mengikut peranan dan klasifikasi data, iaitu pelapor hanya boleh melihat tiket sendiri, pasukan sokongan melihat tiket yang ditugaskan dan modul berkaitan, manakala tiket yang mengandungi data berklasifikasi atau isu keselamatan dihadkan kepada peranan yang dibenarkan. | Wajib | Jurang; A-16.03 |
| SRS-TKT-21 | Sistem hendaklah menyokong pemindahan fungsi sokongan daripada pasukan Syarikat kepada Bahagian Teknologi Maklumat MAIS selepas tempoh jaminan, tanpa kehilangan data historikal tiket, konfigurasi SLA, hierarki eskalasi dan pautan artikel Pangkalan Pengetahuan. | Wajib | Jurang; A-20.04 |

**Aliran proses utama.**

1. Pengguna menekan butang bantuan pada mana-mana skrin SISDATA; sistem membuka borang tiket dan menangkap konteks skrin, modul, peranan dan versi sistem secara automatik.
2. Pengguna menaip penerangan masalah; sistem mencari Pangkalan Pengetahuan secara serentak dan memaparkan artikel penyelesaian yang relevan.
3. Sekiranya artikel yang dipaparkan menyelesaikan masalah, pengguna membatalkan tiket dan sistem merekod kejadian sebagai penyelesaian kendiri.
4. Sekiranya masalah berterusan, pengguna memilih kategori dan tahap keterukan, melampirkan tangkapan skrin dengan persetujuan, dan menghantar tiket.
5. Sistem menjana nombor rujukan tiket, menugaskan tiket kepada pasukan atau juruteknik berdasarkan modul dan kategori, dan memulakan pengiraan SLA.
6. Juruteknik menyiasat tiket, merekod tindakan dalam kronologi dan berkomunikasi dengan pelapor melalui catatan yang kelihatan kepada pelapor.
7. Sistem menghantar amaran apabila tiket menghampiri ambang SLA dan melaksanakan eskalasi automatik kepada penyelia apabila ambang dilepasi.
8. Juruteknik menandakan tiket sebagai selesai; sistem meminta pengesahan pelapor sebelum tiket ditutup.
9. Selepas tiket ditutup, sistem menjana draf artikel penyelesaian berstruktur masalah → punca → penyelesaian dan menghantarnya ke Modul K1 untuk semakan pasukan sokongan.
10. Tiket yang berasal daripada Sistem Aduan berpusat MAIS diterima melalui API Gateway, diproses mengikut aliran yang sama, dan status serta penyelesaiannya disegerakkan kembali ke sistem berpusat secara automatik.
11. Penyegerakan yang gagal dimasukkan ke dalam queue cuba semula dan disenaraikan dalam laporan rekonsiliasi integrasi untuk tindakan pentadbir.
12. Papan pemuka sokongan dan laporan tiket bulanan dijana sebagai input kepada laporan SLA dan penyelenggaraan pencegahan.

**Entiti data.**

- `tkt_tiket` — id\_tiket, nombor\_rujukan, id\_pelapor, kategori, keterukan, modul, tajuk, penerangan, status, id\_juruteknik, tarikh\_buka, tarikh\_sasaran\_respons, tarikh\_sasaran\_selesai, tarikh\_selesai, tarikh\_tutup, punca\_akar, penyelesaian
- `tkt_konteks` — id\_tiket, skrin, kod\_fungsi, url, peranan\_pengguna, pelayar, versi\_sistem, rujukan\_rekod, cap\_masa\_tangkapan
- `tkt_kronologi` — id\_catatan, id\_tiket, jenis\_catatan (dalaman/kelihatan\_pelapor/perubahan\_status), id\_pengguna, kandungan, cap\_masa
- `tkt_lampiran` — id\_lampiran, id\_tiket, nama\_fail, jenis\_fail, saiz, status\_imbasan, lokasi\_storan, persetujuan\_tangkapan\_skrin
- `tkt_sla_konfigurasi` — id\_konfigurasi, keterukan, ambang\_respons, ambang\_penyelesaian, waktu\_perkhidmatan, hierarki\_eskalasi, tarikh\_berkuat\_kuasa
- `tkt_eskalasi` — id\_eskalasi, id\_tiket, peringkat, id\_penerima, sebab (hampir\_sla/lepas\_sla/manual), cap\_masa
- `tkt_cadangan_kb` — id\_cadangan, id\_tiket\_draf, id\_artikel\_kb, skor\_relevan, diklik, tiket\_diteruskan
- `tkt_pautan_aduan` — id\_pautan, id\_tiket, nombor\_aduan\_berpusat, arah\_asal (tempatan/berpusat), status\_penyegerakan, cap\_masa\_penyegerakan\_akhir
- `tkt_log_integrasi` — id\_log, id\_tiket, arah, endpoint, ringkasan\_payload, kod\_status, bilangan\_cuba\_semula, mesej\_ralat, cap\_masa

**Integrasi.**

- Sistem Aduan berpusat MAIS melalui API Gateway — integrasi dua hala bagi pembukaan tiket, kemas kini status dan penyegerakan penyelesaian, selaras A-15.03
- Modul K1 Pangkalan Pengetahuan Berbantu AI — carian cadangan jawapan sebelum penghantaran tiket dan penghantaran draf artikel penyelesaian selepas tiket ditutup
- Enjin notifikasi berpusat SISDATA — e-mel dan notifikasi dalam sistem bagi pembukaan tiket, perubahan status, amaran SLA dan eskalasi
- Modul pengurusan pengguna dan RBAC SISDATA — identiti pelapor, peranan dan hak capaian rekod tiket
- Repositori Dokumen Digital (DMS) SISDATA — penyimpanan lampiran dan tangkapan skrin tiket
- Modul Laporan dan Analitik SISDATA — papan pemuka sokongan dan laporan SLA bulanan
- Sistem pemantauan infrastruktur dan log ralat aplikasi — rujukan silang antara amaran sistem dan tiket yang dibuka

**Kawalan keselamatan.**

- Tangkapan skrin hanya dimuat naik selepas persetujuan eksplisit pengguna, dengan notis jelas mengenai kandungan yang akan dihantar
- Imbasan antivirus serta kawalan jenis dan saiz fail bagi setiap lampiran sebelum disimpan
- Kawalan capaian berasaskan peranan — pelapor melihat tiket sendiri sahaja; pasukan sokongan terhad kepada modul dan tiket yang ditugaskan
- Pengelasan data tiket dan penghadan tiket berklasifikasi atau tiket isu keselamatan kepada peranan yang dibenarkan sahaja
- Penyamaran atau penyulitan data peribadi dan data sensitif yang tersangkut dalam konteks skrin atau lampiran tiket
- Audit trail tamper-evident bagi setiap perubahan status, penugasan semula, perubahan keterukan dan capaian rekod tiket
- Kawalan keselamatan API tiket termasuk token bertempoh, kunci berskop, had kadar dan IP allowlist bagi integrasi dengan Sistem Aduan berpusat
- Pengasingan persekitaran — API tiket didedahkan hanya melalui API Gateway, bukan terus daripada pangkalan data SISDATA

**Laporan yang boleh dijana.** Tiket bulanan — jumlah isu, kategori, severity, status, pematuhan SLA dan tindakan pembetulan, sebagai sebahagian laporan SLA tempoh jaminan; prestasi SLA mengikut tahap keterukan, modul dan juruteknik; tiket terbuka dan tertangguh mengikut keterukan, modul dan tempoh tertunggak; masa purata respons dan masa purata penyelesaian mengikut tempoh dan modul; trend masalah berulang dan modul dengan tiket tertinggi sebagai input penyelenggaraan pencegahan; eskalasi — tiket yang melepasi SLA, peringkat eskalasi dan penerima; penyelesaian kendiri (deflection) — cadangan Pangkalan Pengetahuan dipaparkan berbanding tiket yang diteruskan; penyegerakan dengan Sistem Aduan berpusat MAIS termasuk transaksi gagal dan tindakan cuba semula; artikel Pangkalan Pengetahuan yang dijana daripada penyelesaian tiket.

**Kriteria penerimaan.**

1. Tiket boleh dibuka daripada sekurang-kurangnya sepuluh skrin berbeza merentas modul yang berbeza, dan setiap tiket yang dihasilkan mengandungi konteks skrin, modul, peranan dan versi sistem yang betul tanpa input manual pengguna.
2. Semasa menaip penerangan masalah, sistem memaparkan cadangan artikel Pangkalan Pengetahuan yang relevan sebelum tiket dihantar, dan tindakan pengguna (meneruskan atau membatalkan) direkod.
3. Tiket ujian bagi setiap tahap keterukan mencetuskan amaran SLA pada ambang yang dikonfigurasi dan eskalasi automatik kepada penyelia yang betul apabila ambang dilepasi.
4. Tiket yang dibuka dari Sistem Aduan berpusat MAIS muncul dalam Modul T1 dengan rujukan silang nombor aduan, dan kemas kini status dalam Modul T1 disegerakkan kembali ke sistem berpusat dalam tempoh yang dipersetujui; penyegerakan berulang tidak menghasilkan tiket bertindih.
5. Setiap tiket ujian yang ditutup menghasilkan satu draf artikel di Modul K1 berstruktur masalah → punca → penyelesaian dengan rujukan modul dan versi sistem.
6. Papan pemuka sokongan memaparkan tiket terbuka mengikut keterukan dan modul, prestasi SLA dan trend masalah berulang, dengan drill-down berfungsi hingga ke rekod tiket.
7. Laporan tiket bulanan dijana secara automatik dan boleh dieksport dalam format PDF dan Excel, serta memaparkan angka pematuhan SLA yang sepadan dengan data tiket.
8. Audit trail memaparkan kitaran hayat lengkap tiket ujian termasuk setiap perubahan status, penugasan semula dan capaian rekod, dan rekod audit tidak boleh dipinda atau dipadam oleh pengguna biasa.

### Modul K1 · Pangkalan Pengetahuan Berbantu AI

**Kod:** MOD-KBA · **Gelombang:** G5 (Bulan 5.0–5.5) · **Rujukan:** Lampiran H B-4.02–B-4.03, A-16.03, A-17.02, A-20.03, A-20.05, A-20.07

Menyimpan keseluruhan pengetahuan projek SISDATA — daripada dokumen baseline (URS, BRS, SRS, SDS), reka bentuk sistem, rajah aliran, manual, SOP dan nota keluaran, hinggalah kepada setiap artikel penyelesaian daripada tiket sokongan — dalam satu pangkalan pengetahuan tunggal. Pengguna merujuknya melalui arahan AI dalam bahasa biasa dan menerima jawapan bergrounding dengan petikan sumber yang boleh diklik, dengan tapisan kebenaran dikenakan pada peringkat capaian dan model AI berjalan sepenuhnya dalam persekitaran AWS MAIS.

**Kategori pengguna.**

- Semua pengguna SISDATA sebagai perujuk pengetahuan operasi dan panduan penggunaan
- Pasukan sokongan Syarikat dan juruteknik baharu — paparan khas reka bentuk sistem dan sejarah masalah setiap modul
- Pegawai Bahagian Teknologi Maklumat (BTM) MAIS — rujukan teknikal, konfigurasi dan integrasi selepas pemindahan teknologi
- Pentadbir kandungan Pangkalan Pengetahuan — semakan, penerbitan, pengkategorian dan penyelenggaraan artikel
- Pengurus projek dan pemilik proses MAIS — rujukan baseline keperluan dan keputusan reka bentuk
- Juru audit dalaman dan pegawai keselamatan ICT — semakan audit trail prompt, jawapan dan sumber yang dipetik

**Keperluan fungsian**

| Kod | Keperluan | Keutamaan | Sumber |
| --- | --- | --- | --- |
| SRS-KBA-01 | Sistem hendaklah mengindeks secara automatik dokumen baseline projek (URS, BRS, SRS, SDS), dokumen reka bentuk sistem dan rajah aliran, manual pengguna, manual pentadbir, manual operasi, manual teknikal, SOP, nota keluaran setiap versi, dan artikel penyelesaian daripada tiket sokongan. | Wajib | URS-KBA-01; A-20.03; A-20.05 |
| SRS-KBA-02 | Sistem hendaklah mengambil dokumen sumber daripada repositori dokumen digital (DMS) SISDATA dan melaksanakan pengindeksan inkremental secara automatik apabila dokumen baharu ditambah atau versi baharu diterbitkan, tanpa memerlukan pengindeksan semula keseluruhan korpus. | Wajib | URS-KBA-01; B-3.02; B-3.03 |
| SRS-KBA-03 | Sistem hendaklah membolehkan pengguna bertanya dalam bahasa biasa melalui AI prompt dalam Bahasa Melayu dan Bahasa Inggeris, termasuk soalan operasi, soalan capaian dan soalan aliran proses, tanpa memerlukan sintaks carian khas. | Wajib | URS-KBA-02; B-4.03 |
| SRS-KBA-04 | Sistem hendaklah memberikan jawapan bergrounding yang disertakan petikan sumber yang boleh diklik, memaparkan nama dokumen, nombor versi dan bahagian/seksyen yang dirujuk, dan membuka kandungan penuh sumber tersebut apabila diklik. | Wajib | URS-KBA-02 |
| SRS-KBA-05 | Sistem hendaklah menyatakan dengan jelas apabila tiada sumber yang mencukupi ditemui untuk menjawab soalan, dan tidak boleh menjana jawapan yang tidak disokong oleh mana-mana sumber yang diindeks. | Wajib | Jurang |
| SRS-KBA-06 | Sistem hendaklah menggunakan pendekatan Retrieval-Augmented Generation (RAG) di atas sambungan pgvector dalam pangkalan data PostgreSQL yang sama, menyimpan embedding, indeks carian semantik dan metadata kebenaran dalam satu pangkalan data tanpa sistem vektor berasingan. | Wajib | URS-KBA-03; A-4.01 |
| SRS-KBA-07 | Sistem hendaklah menapis kandungan mengikut kebenaran pengguna pada peringkat capaian (retrieval), iaitu sebelum konteks dihantar kepada model penjanaan, dan bukan dengan menapis jawapan selepas penjanaan. | Wajib | URS-KBA-04; A-16.03 |
| SRS-KBA-08 | Sistem hendaklah menyimpan metadata klasifikasi dan senarai peranan yang dibenarkan bagi setiap dokumen dan setiap segmen (chunk) yang diindeks, supaya kandungan berklasifikasi seperti reka bentuk keselamatan dan SOP pentadbir hanya boleh dicapai oleh peranan yang dibenarkan. | Wajib | URS-KBA-04; A-16.03 |
| SRS-KBA-09 | Sistem hendaklah menyediakan paparan khas bagi pasukan sokongan yang memaparkan maklumat reka bentuk sistem, aliran integrasi, parameter konfigurasi dan sejarah masalah bagi setiap modul, supaya juruteknik baharu boleh menyelesaikan tiket tanpa bergantung kepada pasukan pembangunan asal. | Wajib | URS-KBA-05 |
| SRS-KBA-10 | Sistem hendaklah menyediakan carian konvensional berasaskan kata kunci dan penapis (modul, jenis dokumen, versi, tarikh, status, pemilik) sebagai alternatif kepada AI prompt. | Perlu | Jurang; B-3.04 |
| SRS-KBA-11 | Sistem hendaklah menyimpan setiap artikel Pangkalan Pengetahuan secara berversi dengan status (draf/diterbitkan/lapuk), pemilik artikel, tarikh semakan terakhir dan tarikh semakan seterusnya. | Wajib | URS-KBA-06; B-3.02 |
| SRS-KBA-12 | Sistem hendaklah menanda secara automatik artikel yang merujuk versi sistem lama sebagai perlu disemak apabila keluaran versi baharu dilancarkan, dan menyenaraikan artikel tersebut dalam senarai tindakan pentadbir kandungan. | Wajib | URS-KBA-06 |
| SRS-KBA-13 | Sistem hendaklah menyediakan aliran kerja semakan dan penerbitan artikel, iaitu draf (termasuk draf yang dijana daripada penyelesaian tiket atau oleh AI) hendaklah disemak dan diterbitkan oleh pentadbir kandungan atau pemilik artikel yang diberi kuasa sebelum kelihatan kepada pengguna umum. | Wajib | URS-KBA-06; A-3.05 |
| SRS-KBA-14 | Sistem hendaklah menerima draf artikel penyelesaian daripada Modul T1 Tiket Sokongan Sistem secara automatik, berstruktur masalah → punca → penyelesaian, dan mengekalkan pautan dua hala antara artikel dan tiket asal. | Wajib | URS-KBA-01; A-15.01 |
| SRS-KBA-15 | Sistem hendaklah menjalankan model AI penjanaan dan model embedding sepenuhnya dalam persekitaran AWS MAIS di dalam VPC persendirian, tanpa menghantar kandungan dokumen, prompt atau jawapan kepada mana-mana perkhidmatan luar persekitaran tersebut. | Wajib | URS-KBA-07; A-17.02 |
| SRS-KBA-16 | Sistem hendaklah merekod setiap prompt, jawapan yang dijana dan senarai sumber yang dipetik dalam audit trail, bersama ID pengguna, peranan, alamat IP, cap masa, model yang digunakan dan segmen yang dicapai, dengan rekod audit bersifat tamper-evident. | Wajib | URS-KBA-07; A-16.09; A-16.11 |
| SRS-KBA-17 | Sistem hendaklah menyediakan mekanisme maklum balas pengguna bagi setiap jawapan AI (membantu/tidak membantu beserta ulasan), dan menyalurkan maklum balas tersebut kepada pentadbir kandungan sebagai input penambahbaikan artikel. | Perlu | Jurang; URS-KBA-08 |
| SRS-KBA-18 | Sistem hendaklah menyediakan analitik Pangkalan Pengetahuan yang memaparkan soalan paling kerap ditanya, soalan tanpa jawapan memuaskan (jurang kandungan), artikel paling dirujuk, artikel lapuk dan corak penggunaan mengikut modul dan peranan. | Perlu | URS-KBA-08; B-4.01 |
| SRS-KBA-19 | Sistem hendaklah mengenakan had kadar (rate limiting) ke atas penggunaan AI prompt per pengguna, serta kawalan terhadap prompt injection daripada kandungan dokumen yang diindeks supaya arahan terbenam dalam dokumen tidak boleh mengubah tingkah laku model atau memintas tapisan kebenaran. | Wajib | Jurang; A-15.12; A-16.08 |
| SRS-KBA-20 | Sistem hendaklah memastikan keseluruhan kandungan Pangkalan Pengetahuan termasuk artikel, metadata, versi dan pautan sumber adalah hak milik MAIS, dan boleh dieksport pada bila-bila masa dalam format terbuka (Markdown dan/atau HTML) tanpa kebergantungan kepada perkakasan atau perisian proprietari Syarikat. | Wajib | URS-KBA-09; A-20.07 |
| SRS-KBA-21 | Sistem hendaklah berkongsi infrastruktur LLM dan embedding yang sama dengan modul AI lain dalam SISDATA termasuk pengurusan kandungan berbantu AI Portal Hartanah Wakaf Selangor dan cadangan jawapan Modul T1, bagi mengelakkan pertindihan sumber pengkomputeran. | Perlu | URS-KBA-03; B-4.02 |
| SRS-KBA-22 | Sistem hendaklah diserahkan bersama dokumentasi reka bentuk pengindeksan, senarai sumber yang diindeks, pemetaan klasifikasi–peranan, manual pentadbir kandungan dan panduan pengguna Pangkalan Pengetahuan. | Wajib | A-20.03; A-20.05 |

**Aliran proses utama.**

1. Pentadbir kandungan mendaftarkan sumber dokumen (URS, BRS, SRS, SDS, reka bentuk, manual, SOP, nota keluaran) daripada repositori dokumen digital SISDATA, bersama klasifikasi dan senarai peranan yang dibenarkan.
2. Sistem memecah dokumen kepada segmen (chunk), menjana embedding menggunakan model dalam VPC MAIS, dan menyimpan embedding bersama metadata klasifikasi ke dalam pgvector.
3. Apabila dokumen baharu ditambah atau versi baharu diterbitkan di DMS, sistem melaksanakan pengindeksan inkremental secara automatik dan menanda segmen versi lama sebagai lapuk.
4. Pengguna memasukkan soalan dalam bahasa biasa melalui AI prompt, sama ada daripada menu Pangkalan Pengetahuan atau daripada butang bantuan Modul T1.
5. Sistem mengenal pasti peranan dan kebenaran pengguna, lalu melaksanakan carian semantik yang telah ditapis pada peringkat capaian supaya hanya segmen yang dibenarkan dimasukkan ke dalam konteks.
6. Model penjanaan dalam VPC MAIS menghasilkan jawapan bergrounding berdasarkan segmen yang dicapai sahaja; sekiranya tiada segmen yang mencukupi, sistem menyatakan ketiadaan sumber dan tidak menjana jawapan.
7. Sistem memaparkan jawapan bersama petikan sumber yang boleh diklik (dokumen, versi, seksyen) dan merekod prompt, jawapan, sumber dan metadata pengguna dalam audit trail.
8. Pengguna memberikan maklum balas membantu atau tidak membantu; maklum balas disalurkan kepada pentadbir kandungan.
9. Draf artikel penyelesaian daripada Modul T1 diterima secara automatik, disemak oleh pasukan sokongan, dan diterbitkan selepas kelulusan pentadbir kandungan.
10. Apabila keluaran versi baharu dilancarkan, sistem menanda artikel yang merujuk versi lama sebagai perlu disemak dan menyenaraikannya untuk tindakan pemilik artikel.
11. Analitik Pangkalan Pengetahuan dijana secara berkala untuk mengenal pasti jurang kandungan dan memacu penambahbaikan artikel serta latihan pengguna.
12. MAIS boleh mengeksport keseluruhan kandungan Pangkalan Pengetahuan dalam format Markdown dan/atau HTML pada bila-bila masa.

**Entiti data.**

- `kba_sumber_dokumen` — id\_sumber, id\_dokumen\_dms, jenis\_dokumen (URS/BRS/SRS/SDS/manual/SOP/nota\_keluaran/artikel\_tiket), tajuk, versi, pemilik, klasifikasi, senarai\_peranan\_dibenarkan, status\_indeks, tarikh\_indeks\_akhir
- `kba_segmen` — id\_segmen, id\_sumber, nombor\_seksyen, tajuk\_seksyen, kandungan\_teks, embedding (vector/pgvector), klasifikasi, senarai\_peranan\_dibenarkan, versi\_sistem\_dirujuk, status (aktif/lapuk)
- `kba_artikel` — id\_artikel, tajuk, kategori, modul, kandungan, status (draf/diterbitkan/lapuk), versi, id\_pemilik, id\_penyemak, tarikh\_terbit, tarikh\_semakan\_akhir, tarikh\_semakan\_seterusnya, sumber\_asal (manual/tiket/ai), id\_tiket\_asal
- `kba_versi_artikel` — id\_versi, id\_artikel, nombor\_versi, kandungan, id\_pengubah, sebab\_perubahan, cap\_masa
- `kba_log_prompt` — id\_log, id\_pengguna, peranan, alamat\_ip, prompt, jawapan, model\_digunakan, senarai\_id\_segmen\_dicapai, bilangan\_token, cap\_masa, status (berjaya/tiada\_sumber/ditolak)
- `kba_maklum_balas` — id\_maklum\_balas, id\_log\_prompt, penilaian (membantu/tidak\_membantu), ulasan, id\_pengguna, cap\_masa, status\_tindakan
- `kba_analitik` — id\_rekod, soalan\_dinormalkan, bilangan\_ditanya, kadar\_jawapan\_memuaskan, id\_artikel\_paling\_dirujuk, modul, tempoh
- `kba_pemetaan_kebenaran` — id\_pemetaan, klasifikasi, peranan, jenis\_capaian (baca/semak/terbit), tarikh\_berkuat\_kuasa

**Integrasi.**

- Repositori Dokumen Digital (DMS) SISDATA — sumber dokumen untuk pengindeksan automatik dan inkremental
- Modul T1 Tiket Sokongan Sistem — penerimaan draf artikel penyelesaian dan penyediaan cadangan jawapan sebelum tiket dihantar (pautan dua hala)
- Modul pengurusan pengguna dan RBAC SISDATA — identiti, peranan dan kebenaran yang digunakan untuk tapisan peringkat capaian
- Infrastruktur LLM dan embedding dalam VPC AWS MAIS — perkhidmatan penjanaan dan embedding yang dikongsi dengan modul AI lain
- Pangkalan data PostgreSQL dengan sambungan pgvector — storan embedding, indeks semantik dan metadata kebenaran
- Modul pengurusan keluaran dan versi sistem — pemicu penandaan automatik artikel yang merujuk versi lama
- Modul Portal Hartanah Wakaf Selangor — sumber kandungan soal-jawab awam yang diterbitkan (pilihan, kandungan awam sahaja)
- Platform log keselamatan/SIEM MAIS — penghantaran log prompt dan log capaian untuk pemantauan keselamatan

**Kawalan keselamatan.**

- Tapisan kebenaran dikenakan pada peringkat capaian (retrieval) sebelum konteks dihantar kepada model penjanaan, bukan selepas penjanaan
- Metadata klasifikasi dan senarai peranan dibenarkan disimpan pada peringkat dokumen dan peringkat segmen
- Model AI penjanaan dan model embedding berjalan sepenuhnya dalam VPC persendirian AWS MAIS; tiada kandungan, prompt atau jawapan dihantar ke luar persekitaran tersebut
- Audit trail tamper-evident bagi setiap prompt, jawapan, segmen yang dicapai dan sumber yang dipetik, termasuk ID pengguna, peranan, alamat IP dan cap masa
- Had kadar penggunaan AI prompt per pengguna dan per peranan bagi mengelakkan penyalahgunaan dan pengekstrakan kandungan secara pukal
- Kawalan terhadap prompt injection daripada kandungan dokumen yang diindeks, termasuk pengasingan arahan sistem daripada kandungan yang dicapai
- Kawalan capaian berasaskan peranan bagi fungsi semakan, penerbitan, pengarkiban dan eksport kandungan
- Penyulitan data dalam penghantaran (TLS 1.3) dan dalam simpanan (AES-256 atau setara) bagi kandungan dokumen, embedding dan log prompt
- Dasar retensi dan pengarkiban log prompt selaras dasar keselamatan ICT MAIS dan PDPA
- Kandungan Pangkalan Pengetahuan adalah hak milik MAIS dan boleh dieksport dalam format terbuka tanpa kebergantungan proprietari

**Laporan yang boleh dijana.** Soalan paling kerap ditanya mengikut modul, peranan dan tempoh; jurang kandungan — soalan tanpa jawapan memuaskan atau tanpa sumber ditemui; artikel paling dirujuk dan artikel tidak pernah dirujuk; status kandungan — bilangan artikel draf, diterbitkan dan lapuk, serta artikel melepasi tarikh semakan; artikel yang dijana daripada penyelesaian tiket Modul T1 dan status penerbitannya; liputan pengindeksan — senarai dokumen sumber, versi, status indeks dan tarikh pengindeksan akhir; audit prompt — senarai prompt, pengguna, peranan, sumber dipetik dan cap masa untuk tujuan semakan keselamatan; maklum balas pengguna ke atas jawapan AI dan tindakan penambahbaikan yang diambil; penggunaan infrastruktur AI — bilangan prompt, token dan masa respons purata.

**Kriteria penerimaan.**

1. Semua dokumen baseline projek (URS, BRS, SRS, SDS), manual pengguna dan pentadbir, SOP dan nota keluaran yang telah diserahkan berjaya diindeks, dan laporan liputan pengindeksan menunjukkan status indeks lengkap bagi setiap dokumen tersebut.
2. Sepuluh soalan ujian dalam Bahasa Melayu dan Bahasa Inggeris menghasilkan jawapan bergrounding dengan petikan sumber yang boleh diklik, dan setiap petikan membuka dokumen, versi dan seksyen yang betul.
3. Satu soalan ujian di luar lingkup korpus menghasilkan pernyataan jelas bahawa tiada sumber mencukupi ditemui, tanpa jawapan yang tidak disokong sumber.
4. Ujian dengan dua akaun berbeza peranan mengesahkan bahawa kandungan berklasifikasi tidak muncul dalam jawapan kepada peranan yang tidak dibenarkan, dan log capaian mengesahkan segmen berklasifikasi tidak pernah dimasukkan ke dalam konteks penjanaan bagi peranan tersebut.
5. Pemeriksaan konfigurasi dan log rangkaian mengesahkan model AI dan model embedding berjalan dalam VPC persendirian AWS MAIS tanpa panggilan keluar ke perkhidmatan luar.
6. Setiap prompt ujian menghasilkan satu rekod audit yang lengkap merangkumi prompt, jawapan, senarai sumber dipetik, ID pengguna, peranan dan cap masa, dan rekod tersebut tidak boleh dipinda atau dipadam oleh pengguna biasa.
7. Tiket ujian yang ditutup di Modul T1 menghasilkan draf artikel di Modul K1, dan artikel tersebut hanya kelihatan kepada pengguna umum selepas diterbitkan oleh pentadbir kandungan.
8. Keseluruhan kandungan Pangkalan Pengetahuan berjaya dieksport dalam format Markdown dan/atau HTML, dan fail eksport boleh dibuka serta dibaca tanpa perisian proprietari Syarikat.

## 11. Keperluan Integrasi dan Antara Muka

Semua trafik masuk dan keluar melalui satu API Gateway yang mengesahkan, menghadkan kadar, merekod dan menyelaraskan setiap panggilan. Prinsip yang tidak berubah walau siapa pun penyedianya: data luaran tidak pernah menulis terus ke rekod induk — ia masuk sebagai cadangan yang menunggu pengesahan pegawai MAIS.

### 11.1 Integrasi dalaman MAIS

| Sistem | Guna untuk | Corak |
| --- | --- | --- |
| Kewangan / GRP MAIS | Rekonsiliasi rekod bayaran, nilai aset, penyelarasan selepas bayaran cukai dan urusan Pejabat Tanah | REST segerak |
| e-Sewa MAIS | Rekonsiliasi rekod sewaan dan bayaran penyewa | REST segerak |
| Portal e-MAIS | Pertukaran data dalam aliran kerja pengguna | REST segerak |
| Sistem Aduan berpusat MAIS | Aduan dari kaunter, e-mel dan sistem berpusat masuk ke barisan yang sama; tiket sokongan dua hala | REST dua hala |
| e-Faraid | Maklumat waris dan kes pusaka | REST segerak |
| Portal Hartanah Wakaf Selangor | Permohonan wakaf dan sewaan masuk; senarai hartanah dan status keluar | REST dua hala dengan event |

### 11.2 Sumber luaran kerajaan

| Sumber | Guna untuk | Corak |
| --- | --- | --- |
| Penyedia integrasi berpusat (PBT Selangor dan Pejabat Tanah daerah) | Semak bil dan status cukai tanah, petak dan taksiran; bayaran dan resit rasmi; status hak milik dan pindah milik; status permohonan Pejabat Tanah | REST segerak dan semakan berjadual harian |
| PTG / e-Tanah | Status hak milik dan pendaftaran | Melalui penyedia berpusat |
| JPPH | Data penilaian hartanah | ETL berjadual |
| JUPEM | Pelan ukur dan sempadan rasmi | ETL berjadual dan import fail |
| JPBD | Rancangan pemajuan dan zoning | ETL berjadual |
| MyGDI / Sismap / MyLand | Peta asas, data lot kebangsaan, sempadan pentadbiran | ETL berjadual |
| LHDN / PBT | Rujukan cukai dan taksiran | REST segerak jika tersedia |

### 11.3 Keperluan fungsian integrasi

| Kod | Keperluan | Keutamaan | Sumber |
| --- | --- | --- | --- |
| SRS-INT-01 | Sistem hendaklah menyalurkan semua panggilan masuk dan keluar melalui satu API Gateway berpusat; tiada modul dibenarkan memanggil sistem luar secara terus. | Wajib | A-15.01 |
| SRS-INT-02 | Sistem hendaklah menerbitkan kontrak API dalam format OpenAPI 3.0 bertanda versi, dengan versi lama kekal disokong sehingga tempoh penamatan yang diumumkan. | Wajib | A-15.02 |
| SRS-INT-03 | Sistem hendaklah mengesahkan setiap panggilan API menggunakan OAuth 2.0 dan token JWT, dengan tempoh luput token yang boleh dikonfigurasi. | Wajib | A-15.12 |
| SRS-INT-04 | Sistem hendaklah menguatkuasakan had kadar per kunci API dan per titik akhir, dengan nilai yang boleh dikonfigurasi oleh pentadbir. | Wajib | A-15.12 |
| SRS-INT-05 | Sistem hendaklah menyokong penggiliran kunci API (key rotation) tanpa gangguan perkhidmatan, dan senarai IP yang dibenarkan bagi integrasi mesin ke mesin. | Wajib | A-15.12 |
| SRS-INT-06 | Sistem hendaklah merekod setiap panggilan API masuk dan keluar dalam log integrasi dengan status, masa tindak balas, saiz muatan dan rujukan transaksi. | Wajib | A-15.09 |
| SRS-INT-07 | Sistem hendaklah melaksanakan circuit breaker dan cuba semula automatik dengan backoff bagi integrasi luar, dan mengeskalasi kepada pentadbir apabila kegagalan berterusan melepasi ambang yang ditetapkan. | Wajib | A-15.10 |
| SRS-INT-08 | Sistem hendaklah menjalankan rekonsiliasi harian antara rekod SISDATA dan sistem sumber bagi setiap integrasi kewangan, dan menjana Laporan Rekonsiliasi yang menunjukkan sisihan. | Wajib | A-15.11 |
| SRS-INT-09 | Sistem hendaklah menerima data luaran sebagai cadangan perubahan berstatus menunggu pengesahan, dan hanya menulis ke rekod induk selepas pegawai MAIS yang diberi kuasa mengesahkannya. | Wajib | A-15.06 |
| SRS-INT-10 | Setiap modul yang bergantung kepada integrasi luar hendaklah menyediakan mod sandaran manual berstruktur yang membolehkan operasi diteruskan semasa gangguan, dengan penyegerakan semula automatik apabila perkhidmatan pulih. | Wajib | A-15.10 |
| SRS-INT-11 | Sistem hendaklah mengasingkan setiap integrasi pihak ketiga dalam adapter tersendiri supaya perubahan skema di pihak penyedia terkurung dalam satu komponen. | Perlu | Jurang |
| SRS-INT-12 | Sistem hendaklah menyediakan papan pemuka kesihatan integrasi yang menunjukkan status setiap sambungan, kadar kegagalan, masa tindak balas purata dan ralat terkini. | Wajib | A-15.09 |
| SRS-INT-13 | Sistem hendaklah membolehkan pengguna berkuasa menjana kunci API baharu dengan skop kebenaran, had kadar dan tarikh luput yang ditetapkan, serta membatalkannya serta-merta. | Wajib | URS-PRO-07; URS-PRO-08 |
| SRS-INT-14 | Sistem hendaklah menjana dokumentasi API secara automatik bagi setiap kunci — titik akhir, skema dan contoh panggilan — yang boleh dikongsi sebagai pautan. | Perlu | URS-PRO-09 |
| SRS-INT-15 | Sistem hendaklah menerbitkan perkhidmatan peta mengikut piawaian OGC (WMS, WFS, WMTS) untuk digunakan oleh dashboard, PWA dan sistem luar yang dibenarkan. | Wajib | A-14.08 |
| SRS-INT-16 | Sistem hendaklah menyokong integrasi payment gateway bagi bayaran cukai, sewaan dan permohonan portal awam, termasuk pengesahan bayaran, resit digital dan rekonsiliasi dengan GRP MAIS. | Wajib | A-8.07; A-15.03; C-1.03 |
| SRS-INT-17 | Sistem hendaklah menyediakan Pelan Integrasi Sistem dan Spesifikasi Integrasi yang menerangkan sistem sumber dan sasaran, kaedah, format data, jadual pertukaran, kawalan keselamatan dan audit log bagi setiap integrasi. | Wajib | A-2.08 |
| SRS-INT-18 | Sistem hendaklah menyediakan SOP pemantauan dan penyelesaian masalah integrasi untuk kegunaan Bahagian ICT MAIS selepas pemindahan. | Wajib | Jurang |

### 11.4 Corak pertukaran data

| Corak | Bila digunakan |
| --- | --- |
| REST segerak | Pertukaran yang memerlukan jawapan serta-merta dalam aliran kerja pengguna |
| REST dua hala dengan event | Data mengalir dua arah dan perubahan di satu pihak mesti disegerakkan ke pihak lain |
| Baris gilir tak segerak | Proses yang tidak boleh menghalang pengguna — notifikasi, laporan besar, pemprosesan dokumen; juga penimbal bagi sistem luar yang lambat |
| ETL berjadual | Data rujukan berkala yang tidak memerlukan kesegaran masa nyata |

## 12. Keperluan Bukan Fungsian

Sasaran prestasi dinyatakan sebagai angka yang boleh diukur dan diuji, mengikut Seksyen 9.2.2 Terma Rujukan yang menuntut sasaran “munasabah dan boleh diukur”. Angka di bawah dicadangkan Syarikat dan dimuktamadkan bersama MAIS semasa pengesahan keperluan; setiap satu menjadi kriteria lulus Ujian Prestasi di Bab 16.

### 12.1 Sasaran prestasi

| Kod | Parameter | Sasaran | Kaedah pengukuran |
| --- | --- | --- | --- |
| SRS-NFR-01 | Ketersediaan sistem | 99.5% setiap bulan dalam waktu operasi, tidak termasuk tetingkap penyelenggaraan berjadual yang dimaklumkan 5 hari bekerja lebih awal | Pemantauan uptime berterusan; laporan bulanan |
| SRS-NFR-02 | Masa tindak balas skrin transaksi | ≤ 3 saat bagi 95% permintaan di bawah beban biasa | Ujian beban dan pemantauan pengguna sebenar |
| SRS-NFR-03 | Masa tindak balas API | ≤ 1.5 saat bagi 95% panggilan dalaman | Log API Gateway |
| SRS-NFR-04 | Pengguna serentak | 200 pengguna aktif serentak tanpa penurunan melebihi 20% daripada sasaran masa tindak balas | Ujian beban berperingkat |
| SRS-NFR-05 | Carian dan tapisan | ≤ 5 saat bagi carian merentas keseluruhan portfolio lot dengan tapisan gabungan | Ujian prestasi dengan isi padu data sebenar |
| SRS-NFR-06 | Muat naik dokumen | Fail 25 MB selesai dimuat naik dan diindeks dalam ≤ 30 saat | Ujian prestasi |
| SRS-NFR-07 | Penjanaan laporan | Laporan piawai ≤ 10 saat; laporan berskala besar dijana di latar belakang dengan notifikasi apabila siap, tanpa menjejaskan prestasi transaksi | Ujian prestasi dan pemerhatian baris gilir |
| SRS-NFR-08 | Paparan peta GIS | Lapisan asas dengan satu lapisan tematik bagi kawasan satu daerah dipaparkan dalam ≤ 5 saat; jubin dicache | Ujian prestasi GIS |
| SRS-NFR-09 | Kesegaran data dashboard | Data transaksi dipaparkan tidak melebihi 15 minit ketinggalan, dengan penunjuk masa kemas kini terakhir | Pemerhatian replika baca |
| SRS-NFR-10 | Pertumbuhan data | Sistem hendaklah mengekalkan sasaran prestasi di atas sehingga tiga kali ganda isi padu data permulaan tanpa perubahan seni bina | Ujian beban dengan data sintetik berskala |

### 12.2 Kebolehskalaan

| Kod | Keperluan | Keutamaan | Sumber |
| --- | --- | --- | --- |
| SRS-NFR-11 | Sistem hendaklah menyokong penskalaan menegak (menambah sumber kepada instance sedia ada) dan penskalaan mendatar (menambah instance aplikasi di belakang pengimbang beban) tanpa perubahan kod. | Wajib | A-17.05 |
| SRS-NFR-12 | Sistem hendaklah memisahkan beban bacaan berat — laporan, analitik, dashboard — kepada replika baca supaya ia tidak menjejaskan prestasi transaksi. | Wajib | A-3.01 |
| SRS-NFR-13 | Sistem hendaklah melaksanakan proses yang tidak menghalang pengguna melalui baris gilir tak segerak. | Wajib | A-3.18 |

### 12.3 Kebolehgunaan dan kebolehcapaian

| Kod | Keperluan | Keutamaan | Sumber |
| --- | --- | --- | --- |
| SRS-NFR-14 | Antara muka hendaklah mematuhi Malaysia Government Design System — sistem grid 12-8-4, token warna, skala tipografi, token spacing, radius dan shadow, set ikon dan komponen standard. Komponen tersuai memerlukan justifikasi bertulis dan kelulusan MAIS. | Wajib | A-3.04; RFP 9.4.2 |
| SRS-NFR-15 | Antara muka hendaklah mematuhi WCAG 2.1 Level AA: nisbah kontras warna, label medan borang, susunan fokus papan kekunci, teks alternatif imej, dan mesej ralat yang boleh dibaca pembaca skrin. | Wajib | RFP 9.4 |
| SRS-NFR-16 | Sistem hendaklah menyokong Google Chrome, Microsoft Edge, Mozilla Firefox dan Safari — versi terkini dan tiga versi utama sebelumnya. | Wajib | A-3.02 |
| SRS-NFR-17 | Sistem hendaklah berfungsi pada komputer meja, komputer riba, tablet dan telefon pintar dengan reka bentuk responsif tanpa kehilangan fungsi utama. | Wajib | A-3.03 |
| SRS-NFR-18 | Modul pemantauan lapangan hendaklah berfungsi luar talian dan menyegerakkan data secara automatik apabila sambungan rangkaian pulih. | Wajib | URS-PTN-02 |
| SRS-NFR-19 | Sistem hendaklah menyediakan inbox tugasan per pengguna yang memaparkan tugasan menunggu, baki masa SLA dan tindakan pantas. | Wajib | B-2.05 |
| SRS-NFR-20 | Borang kemasukan data hendaklah menyimpan draf secara automatik dan memulihkannya selepas gangguan sesi. | Perlu | B-2.04 |
| SRS-NFR-21 | Sistem hendaklah menyediakan bantuan dalam talian berkonteks, FAQ dan panduan langkah demi langkah dalam aplikasi. | Perlu | B-2.08 |

### 12.4 Kebolehselenggaraan dan kebolehpindahan

| Kod | Keperluan | Keutamaan | Sumber |
| --- | --- | --- | --- |
| SRS-NFR-22 | Sistem hendaklah berstruktur modular supaya fungsi baharu boleh ditambah tanpa menjejaskan modul sedia ada atau memerlukan pembangunan semula menyeluruh. | Wajib | A-1.05 |
| SRS-NFR-23 | Pentadbir hendaklah boleh mengkonfigurasi menu, parameter, kod rujukan, peranan, hak akses, templat notifikasi dan tetapan sistem tanpa mengubah kod sumber. | Wajib | A-3.10 |
| SRS-NFR-24 | Sistem hendaklah mematuhi standard secure coding yang didokumenkan dan amalan DevSecOps, dengan analisis kod statik dan imbasan kebergantungan pada setiap penggabungan kod. | Wajib | A-3.20 |
| SRS-NFR-25 | Sistem hendaklah tidak mengikat MAIS kepada mana-mana vendor atau platform: kaedah eksport data penuh, dokumentasi konfigurasi lengkap dan pelan pemindahan hendaklah disediakan supaya MAIS boleh memindah atau menyambung operasi jika penyedia bertukar. | Wajib | RFP 12.8.3 |
| SRS-NFR-26 | Keseluruhan kod sumber, skrip, konfigurasi, dokumentasi dan artifak reka bentuk adalah hak milik MAIS dan diserahkan sepenuhnya; tiada komponen kotak hitam yang mengunci MAIS kepada Syarikat. | Wajib | RFP 19.9; A-20.07 |

## 13. Keperluan Keselamatan

Bab ini menetapkan keperluan keselamatan peringkat sistem. Keperluan fungsian modul pengguna, peranan dan capaian serta modul audit trail berada di Bab 6.

### 13.1 Pematuhan dasar dan perundangan

| Kod | Keperluan | Keutamaan | Sumber |
| --- | --- | --- | --- |
| SRS-SEC-01 | Reka bentuk keselamatan sistem hendaklah mematuhi Dasar Keselamatan Siber MAIS dan Polisi ICT MAIS, dengan pemetaan bertulis setiap kawalan kepada klausa dasar berkenaan. | Wajib | A-16.16; RFP 11.1.2 |
| SRS-SEC-02 | Sistem hendaklah mematuhi Akta Perlindungan Data Peribadi 2010 bagi pengumpulan, pemprosesan, penyimpanan dan pendedahan data peribadi pemilik, penyewa, pengadu dan pemohon. | Wajib | A-16.16; RFP 11.1.2 |
| SRS-SEC-03 | Reka bentuk dan operasi sistem hendaklah mengambil kira peruntukan Akta Jenayah Komputer 1997 berhubung capaian tanpa kebenaran dan pengubahan data. | Wajib | A-16.16 |
| SRS-SEC-04 | Kawalan keselamatan hendaklah diselaraskan dengan prinsip ISO/IEC 27001 bagi pengurusan keselamatan maklumat. | Wajib | A-16.16 |
| SRS-SEC-05 | Pembangunan hendaklah mematuhi OWASP Top 10 dan amalan Secure SDLC, disahkan melalui analisis kod statik pada setiap penggabungan kod dan Source Code Review pihak ketiga bebas. | Wajib | A-3.20; RFP 11.7 |
| SRS-SEC-06 | Syarikat, kakitangan dan subkontraktor hendaklah menandatangani NDA MAIS dan Surat Akuan Pematuhan Dasar Keselamatan Siber MAIS sebelum sebarang capaian kepada data, maklumat, premis atau kawasan terperingkat MAIS diberikan. | Wajib | RFP 21.1.3; 21.1.4 |

### 13.2 Identiti dan kawalan capaian

| Kod | Keperluan | Keutamaan | Sumber |
| --- | --- | --- | --- |
| SRS-SEC-07 | Log masuk hendaklah melalui SSO MAIS menggunakan OpenID Connect; sistem tidak menyelenggara pangkalan kata laluan berasingan bagi pengguna dalaman. | Wajib | A-16.01 |
| SRS-SEC-08 | MFA hendaklah diwajibkan bagi modul dan tindakan kritikal: kelulusan, pelupusan, perubahan hak milik, pengubahan nilai penilaian, pengurusan kunci API dan pengubahan aliran kerja. | Wajib | A-16.02 |
| SRS-SEC-09 | Bagi akaun yang tidak melalui SSO (pengguna portal awam dan akaun perkhidmatan), sistem hendaklah menguatkuasakan polisi kata laluan: panjang minimum, kompleksiti, tempoh luput, sejarah kata laluan dan penguncian selepas bilangan percubaan gagal yang ditetapkan. | Wajib | A-16.04 |
| SRS-SEC-10 | Sistem hendaklah mengurus kitar hayat akaun dengan status aktif, digantung, dinyahaktif dan tamat tempoh, serta menamatkan capaian serta-merta apabila akaun dinyahaktifkan. | Wajib | A-16.04 |
| SRS-SEC-11 | Sistem hendaklah menamatkan sesi secara automatik selepas tempoh tidak aktif yang boleh dikonfigurasi, menghadkan sesi serentak per pengguna, dan membenarkan pentadbir menamatkan sesi secara paksa. | Wajib | A-16.06 |
| SRS-SEC-12 | Kebenaran hendaklah dinilai pada peringkat model bagi setiap permintaan; capaian tidak boleh dipintas melalui panggilan API terus, eksport laporan, platform BI atau lapisan GIS. | Wajib | A-16.03 |
| SRS-SEC-13 | Sistem hendaklah menjalankan semakan capaian berkala (access recertification) di mana pemilik data mengesahkan senarai pengguna dan kebenaran mereka mengikut jadual yang ditetapkan. | Wajib | URS-RBC-07 |

### 13.3 Perlindungan data

| Kod | Keperluan | Keutamaan | Sumber |
| --- | --- | --- | --- |
| SRS-SEC-14 | Semua data dalam transit hendaklah disulitkan menggunakan TLS 1.3 atau lebih tinggi pada setiap hop, termasuk antara komponen dalaman. | Wajib | A-16.08 |
| SRS-SEC-15 | Data at-rest hendaklah disulitkan menggunakan AES-256 atau setara, meliputi pangkalan data, storan dokumen, sandaran dan log. | Wajib | A-16.07 |
| SRS-SEC-16 | Medan sensitif — nilai penilaian, maklumat kes mahkamah, maklumat peribadi pengadu — hendaklah disulitkan pada peringkat lajur sebagai tambahan kepada penyulitan storan. | Wajib | A-16.07 |
| SRS-SEC-17 | Kunci penyulitan hendaklah diurus melalui perkhidmatan pengurusan kunci dengan penggiliran berjadual dan capaian yang diaudit; kunci tidak disimpan dalam kod sumber atau fail konfigurasi. | Wajib | A-16.07 |
| SRS-SEC-18 | Data sebenar tidak dibenarkan dalam persekitaran bukan produksi kecuali dengan kelulusan bertulis MAIS, dan hendaklah melalui penyamaran data (masking) atau pengurangan medan sensitif. | Wajib | RFP 12.2.4 |
| SRS-SEC-19 | Muat naik fail hendaklah tertakluk kepada had saiz, senarai putih jenis fail yang dibenarkan, dan imbasan antivirus sebelum fail disimpan; fail berisiko ditolak dan percubaan direkod. | Wajib | B-3.06 |

### 13.4 Pemantauan, audit dan tindak balas insiden

| Kod | Keperluan | Keutamaan | Sumber |
| --- | --- | --- | --- |
| SRS-SEC-20 | Sistem hendaklah merekod setiap percubaan log masuk berjaya dan gagal berserta alamat IP, jenis peranti dan lokasi anggaran. | Wajib | A-16.05 |
| SRS-SEC-21 | Sistem hendaklah menghantar amaran automatik kepada pentadbir bagi aktiviti log masuk mencurigakan, percubaan log masuk berulang atau percubaan capaian tidak sah. | Wajib | A-16.05 |
| SRS-SEC-22 | Rekod audit hendaklah tidak boleh diubah atau dipadam oleh mana-mana peranan termasuk pentadbir, dilaksanakan pada peringkat pangkalan data secara append-only dengan semakan integriti berkala. | Wajib | A-16.09 |
| SRS-SEC-23 | Audit trail hendaklah boleh dicari dan dieksport dalam format CSV, Excel, JSON dan PDF dengan pengesahan integriti pakej eksport. | Wajib | A-16.12 |
| SRS-SEC-24 | Sistem hendaklah berintegrasi dengan Web Application Firewall dan sistem pengesanan pencerobohan (IDS/IPS) pada lapisan infrastruktur. | Wajib | A-16.13 |
| SRS-SEC-25 | Sistem hendaklah menyediakan SOP Pengurusan Insiden Keselamatan yang menetapkan pengesanan, pengelasan keterukan, pembendungan, pemakluman kepada MAIS, siasatan punca dan pemulihan. | Wajib | RFP 11.9.1(i) |
| SRS-SEC-26 | Syarikat hendaklah memaklumkan MAIS dengan segera apabila berlaku pelanggaran keselamatan atau kebocoran data, dan memberi kerjasama penuh dalam siasatan. | Wajib | RFP 21.6 |
| SRS-SEC-27 | Pada penamatan kontrak, Syarikat hendaklah memulangkan semua data MAIS, memadam secara selamat semua salinan kerja dan sandaran, serta mengemukakan pengesahan bertulis pemulangan dan pelupusan data. | Wajib | RFP 21.5 |

### 13.5 Kawalan capaian kakitangan Syarikat

| Kod | Keperluan | Keutamaan | Sumber |
| --- | --- | --- | --- |
| SRS-SEC-28 | Hanya kakitangan Syarikat yang dinamakan dan diluluskan MAIS dibenarkan mencapai persekitaran ICT MAIS, menggunakan akaun individu tanpa perkongsian. | Wajib | RFP 21.3.2 |
| SRS-SEC-29 | Capaian hendaklah mengikut prinsip capaian minimum (least privilege), dan capaian dari luar premis MAIS hanya melalui VPN atau sambungan selamat yang diluluskan. | Wajib | RFP 21.3.2 |
| SRS-SEC-30 | Syarikat hendaklah memaklumkan MAIS mengenai sebarang perubahan kakitangan untuk kelulusan, dan capaian kakitangan yang berhenti ditamatkan serta-merta. | Wajib | RFP 21.3.2 |
| SRS-SEC-31 | Semua capaian kakitangan Syarikat kepada persekitaran dan data MAIS hendaklah direkod dan boleh diaudit oleh MAIS pada bila-bila masa. | Wajib | RFP 21.3.2 |

### 13.6 Penilaian keselamatan bebas

| Kod | Keperluan | Keutamaan | Sumber |
| --- | --- | --- | --- |
| SRS-SEC-32 | Vulnerability Assessment dan Penetration Testing hendaklah dilaksanakan oleh pihak ketiga bebas yang dipersetujui MAIS — satu pusingan sebelum Go-Live dan satu pusingan dalam tempoh jaminan, dengan ujian post-assessment selepas setiap tindakan pengukuhan. | Wajib | RFP 11.7 |
| SRS-SEC-33 | Skop penilaian hendaklah meliputi Web Application Security Assessment (OWASP Top 10), Database Security Assessment, Host and Cloud Configuration Assessment, Source Code Review dan Performance Test. | Wajib | RFP 11.7 |
| SRS-SEC-34 | Source Code Review hendaklah meliputi keseluruhan kod sumber tanpa pengecualian, termasuk kod yang dijana dengan bantuan alat AI. | Wajib | RFP 11.7 |
| SRS-SEC-35 | Tiada penemuan berkeutamaan kritikal atau tinggi yang terbuka dibenarkan pada tarikh Go-Live. | Wajib | RFP 11.7 |

## 14. Keperluan Infrastruktur, Cloud dan Pemulihan Bencana

SISDATA menumpang persekitaran AWS sedia ada MAIS; kos langganan cloud ditanggung MAIS dan tidak termasuk dalam harga Syarikat. Yang termasuk ialah reka bentuk, konfigurasi, deployment, pengukuhan keselamatan, pemantauan dan pemindahan operasi kepada Bahagian ICT MAIS.

### 14.1 Seni bina infrastruktur

| Kod | Keperluan | Keutamaan | Sumber |
| --- | --- | --- | --- |
| SRS-INF-01 | Syarikat hendaklah menyediakan cadangan infrastruktur terperinci bagi setiap persekitaran: bilangan instance, CPU, memori, storan, sistem pengendalian, konfigurasi rangkaian, kumpulan keselamatan dan firewall, berserta justifikasi sizing berdasarkan isi padu data dan bilangan pengguna yang dijangka. | Wajib | A-17.01 |
| SRS-INF-02 | Sistem hendaklah dihoskan dalam persekitaran AWS sedia ada MAIS tanpa memerlukan pembelian infrastruktur baharu. | Wajib | A-17.02 |
| SRS-INF-03 | Syarikat hendaklah menyediakan lima persekitaran: DEV, SIT, UAT, PROD dan DR, serta persekitaran latihan yang boleh dibangunkan daripada UAT bagi tujuan latihan pengguna. | Wajib | A-17.03 |
| SRS-INF-04 | Syarikat hendaklah menyediakan Rajah Topologi Deployment yang menunjukkan setiap komponen, zon ketersediaan, laluan rangkaian, titik penamatan TLS dan sempadan keselamatan. | Wajib | RFP 22.4.3(c) |
| SRS-INF-05 | Syarikat hendaklah mengemukakan anggaran kos langganan cloud bulanan bagi kesemua persekitaran sebagai maklumat perancangan bajet MAIS, walaupun kos tersebut ditanggung MAIS. | Perlu | Jurang |

### 14.2 Ketersediaan tinggi

| Kod | Keperluan | Keutamaan | Sumber |
| --- | --- | --- | --- |
| SRS-INF-06 | Reka bentuk hendaklah mengurangkan titik kegagalan tunggal (single point of failure) pada lapisan aplikasi, pangkalan data dan storan, dengan komponen kritikal digandakan merentas sekurang-kurangnya dua zon ketersediaan. | Wajib | A-17.04 |
| SRS-INF-07 | Sistem hendaklah menyediakan mekanisme failover berdokumen bagi pangkalan data dan lapisan aplikasi, dengan prosedur failover dan failback yang diuji. | Wajib | A-17.12 |
| SRS-INF-08 | Syarikat hendaklah menyediakan mekanisme pengurusan kapasiti yang memberi amaran sebelum sumber mencapai ambang kritikal. | Wajib | RFP 12.3.2 |

### 14.3 Sandaran dan pemulihan

| Kod | Keperluan | Keutamaan | Sumber |
| --- | --- | --- | --- |
| SRS-INF-09 | Skop sandaran hendaklah meliputi pangkalan data aplikasi, pangkalan data spatial, dokumen dan fail dalam storan objek, konfigurasi sistem dan infrastruktur, serta log dan rekod audit. | Wajib | A-17.06 |
| SRS-INF-10 | Jadual sandaran hendaklah: sandaran penuh mingguan, sandaran tokokan (incremental) harian, dan sandaran log transaksi setiap 15 minit bagi pangkalan data PROD. | Wajib | A-17.06 |
| SRS-INF-11 | Sistem hendaklah menyokong pemulihan ke titik masa tertentu (point-in-time recovery) bagi pangkalan data PROD. | Wajib | A-17.07 |
| SRS-INF-12 | Polisi tempoh simpanan hendaklah: sandaran harian disimpan 30 hari, mingguan 12 minggu, bulanan 12 bulan, dan arkib tahunan mengikut keperluan undang-undang dan audit MAIS. | Wajib | A-17.06 |
| SRS-INF-13 | Semua sandaran hendaklah disulitkan semasa dalam transit dan semasa disimpan, dan disimpan di lokasi berasingan daripada sistem produksi. | Wajib | A-16.07 |
| SRS-INF-14 | Ujian pemulihan sandaran hendaklah dijalankan sekurang-kurangnya sekali setiap suku tahun sepanjang tempoh jaminan, dengan laporan keputusan diserahkan kepada MAIS. | Wajib | A-17.07 |

### 14.4 Pemulihan bencana

| Kod | Keperluan | Keutamaan | Sumber |
| --- | --- | --- | --- |
| SRS-INF-15 | Sasaran Recovery Time Objective (RTO) bagi sistem PROD ialah **4 jam** dari masa bencana diisytiharkan sehingga perkhidmatan teras dipulihkan. | Wajib | A-17.08 |
| SRS-INF-16 | Sasaran Recovery Point Objective (RPO) ialah **15 minit** kehilangan data maksimum bagi data transaksi. | Wajib | A-17.08 |
| SRS-INF-17 | Sistem hendaklah melaksanakan replikasi data ke persekitaran DR secara berterusan atau pada selang yang menyokong RPO yang ditetapkan. | Wajib | A-17.09 |
| SRS-INF-18 | Syarikat hendaklah menyediakan Pelan Pemulihan Bencana yang lengkap: senario bencana, kriteria pengisytiharan, peranan dan tanggungjawab, prosedur failover langkah demi langkah, prosedur pengesahan selepas pemulihan, prosedur failback, dan senarai hubungan eskalasi. | Wajib | A-17.09 |
| SRS-INF-19 | Sekurang-kurangnya satu (1) ujian atau simulasi pemulihan bencana hendaklah dijalankan sebelum penyerahan akhir, dengan Laporan Ujian Pemulihan Bencana yang menyatakan RTO dan RPO sebenar yang dicapai berbanding sasaran. | Wajib | A-17.10; RFP 12.5.3 |
| SRS-INF-20 | Sasaran RTO dan RPO di atas hendaklah disahkan bersama MAIS semasa Gate 1 dan dijadikan kriteria lulus ujian DR. | Wajib | RFP 12.5.2(b) |

### 14.5 Pemantauan dan operasi

| Kod | Keperluan | Keutamaan | Sumber |
| --- | --- | --- | --- |
| SRS-INF-21 | Sistem hendaklah memantau dan memaparkan: status perkhidmatan aplikasi, penggunaan CPU, memori, storan dan rangkaian, prestasi pangkalan data dan query perlahan, status setiap integrasi dan API, status perkhidmatan GIS, status tugasan berjadual, ruang storan dokumen, dan ralat aplikasi terkini. | Wajib | A-17.11 |
| SRS-INF-22 | Sistem hendaklah menghantar amaran automatik kepada Bahagian ICT MAIS apabila sebarang parameter melepasi ambang yang ditetapkan, melalui saluran yang dipersetujui. | Wajib | A-17.11 |
| SRS-INF-23 | Sistem hendaklah menyediakan papan pemuka pemantauan infrastruktur untuk kegunaan pentadbir MAIS, berserta prosedur eskalasi bertulis. | Wajib | RFP 12.7 |
| SRS-INF-24 | Syarikat hendaklah menyediakan SOP Pemantauan Sistem dan Infrastruktur serta Laporan Kapasiti dan Prestasi Infrastruktur berkala sepanjang tempoh jaminan. | Wajib | RFP 12.9.1(i); 12.9.1(j) |
| SRS-INF-25 | Sistem hendaklah menyediakan saluran log ralat aplikasi berpusat yang boleh dicari oleh pentadbir teknikal, dengan pengekalan mengikut polisi yang ditetapkan. | Wajib | A-3.19 |

### 14.6 CI/CD dan pengurusan pelepasan

| Kod | Keperluan | Keutamaan | Sumber |
| --- | --- | --- | --- |
| SRS-INF-26 | Saluran CI/CD hendaklah berpagar: ujian unit dan integrasi lulus, analisis kod statik tanpa penemuan kritikal, imbasan kebergantungan dan pengesanan rahsia bersih sebelum promosi antara persekitaran. | Wajib | A-3.20 |
| SRS-INF-27 | Syarikat hendaklah menyediakan dokumen CI/CD, Pelan Pengurusan Pelepasan (Release Management) dan Pelan Rollback, serta Rajah Proses Deployment. | Wajib | RFP 12.9.1(h); 22.4.3(h) |
| SRS-INF-28 | Setiap pelepasan hendaklah disertai nota keluaran (release note) yang menyenaraikan perubahan, pembetulan pepijat, kesan kepada pengguna dan langkah rollback. | Wajib | RFP 18.6 |
| SRS-INF-29 | Pelan Rollback hendaklah diuji atau disemak secara teknikal sebelum Go-Live, dengan keputusan direkod. | Wajib | RFP 17.5.3 |
| SRS-INF-30 | Syarikat hendaklah menyediakan dokumentasi pemindahan operasi infrastruktur kepada Bahagian ICT MAIS, termasuk konfigurasi, akaun pentadbir, prosedur rutin dan senarai kebergantungan. | Wajib | RFP 12.9.1(k) |

## 15. Migrasi Data

Migrasi data adalah risiko tunggal terbesar projek SISDATA. Rekod hartanah MAIS pada hari ini tersebar dalam fail Excel pelbagai versi, rekod salinan keras, sistem warisan dan fail peribadi pegawai, dengan nombor lot yang ditulis dalam format berbeza antara bahagian. Oleh itu ADR-07 menetapkan bahawa trek data bermula pada Bulan 1 projek, selari dengan pembangunan, bukan sebagai aktiviti akhir sebelum Go-Live.

### 15.1 Prinsip migrasi

1. **Trek data berjalan dari Bulan 1.** Pengumpulan, profiling dan pembersihan data bermula serentak dengan CRP-1 dan tidak menunggu modul siap.
2. **Lot tanah sebagai paksi.** Setiap rekod yang dimigrasikan mesti berjaya dipautkan kepada satu rekod `lot_tanah` yang sah; rekod tanpa padanan lot dimasukkan ke dalam baris gilir pengecualian, bukan dibuang.
3. **Tiada data dipadam.** Rekod sumber asal dikekalkan dalam jadual staging sepanjang projek dan tempoh jaminan sebagai rujukan audit.
4. **Pemilik data mengesahkan, bukan Syarikat.** Setiap set data memerlukan pengesahan bertulis daripada pemilik proses MAIS sebelum dimuatkan ke persekitaran produksi.
5. **Boleh ulang dan boleh balik.** Setiap larian migrasi adalah skrip berversi yang boleh dijalankan semula secara idempoten, dengan prosedur rollback yang diuji.
6. **Jejak audit penuh.** Setiap rekod produksi membawa rujukan kepada baris sumbernya, larian migrasi dan cap masa pemuatan.

### 15.2 Skop data untuk dimigrasikan

| Kategori data | Sumber lazim | Keutamaan | Gelombang sasaran |
| --- | --- | --- | --- |
| Rekod lot tanah dan hak milik | Fail Excel bahagian, salinan hakmilik, rekod PTG | Kritikal | G0–G1 |
| Geometri lot (sempadan) | Pelan ukur JUPEM, shapefile, DXF, koordinat manual | Kritikal | G1–G2 |
| Rekod pemerolehan dan 11 saluran | Minit MMKN, perintah mahkamah, rekod faraid, borang serahan | Kritikal | G1 |
| Kontrak pajakan dan sewaan aktif | Fail kontrak, lejar sewaan, rekod kewangan | Kritikal | G3 |
| Rekod cukai tanah dan tunggakan | Bil PBT, rekod bayaran, lejar GRP | Kritikal | G3 |
| Rekod aset fizikal | Senarai aset bahagian, laporan penilaian | Tinggi | G4 |
| Kes perundangan aktif | Fail kes, rekod peguam | Tinggi | G3–G4 |
| Aduan dan isu tanah terbuka | Rekod aduan bahagian, e-mel | Sederhana | G4 |
| Dokumen digital (imbasan) | Fail PDF/imej bahagian, pemfailan fizikal | Tinggi | G0–G5 (berterusan) |
| Rekod penilaian hartanah | Laporan JPPH, penilai swasta | Sederhana | G4 |
| Direktori pengguna dan peranan | Senarai pegawai, AD MAIS | Kritikal | G0 |

### 15.3 Metodologi enam fasa

**Fasa 1 — Inventori dan profiling (Bulan 1–2).** Mengenal pasti setiap sumber data, pemilik, format, saiz, julat tarikh dan tahap kebolehpercayaan. Menjalankan profiling automatik untuk mengukur kelengkapan medan, kadar nilai kosong, format nombor lot yang digunakan, rekod pendua dan percanggahan antara sumber. Hasil: Daftar Sumber Data dan Laporan Profiling Awal.

**Fasa 2 — Pemetaan dan peraturan transformasi (Bulan 2–3).** Memetakan setiap medan sumber kepada medan sasaran SISDATA, menetapkan peraturan penormalan (format nombor lot, nama mukim dan daerah, kategori guna tanah, jenis hak milik), peraturan penggabungan apabila beberapa sumber merujuk lot yang sama, dan peraturan keutamaan sumber apabila nilai bercanggah. Hasil: Dokumen Pemetaan Data (Data Mapping Document) yang disahkan pemilik proses.

**Fasa 3 — Pembersihan dan pengayaan (Bulan 2–5).** Membetulkan format, menyelesaikan pendua, melengkapkan medan yang hilang daripada sumber sekunder, dan menyelesaikan senarai pengecualian bersama pegawai bahagian. Kerja ini dijalankan oleh pasukan data bersama pegawai MAIS dalam sesi pembersihan berjadual, bukan secara automatik sepenuhnya.

**Fasa 4 — Migrasi percubaan (Bulan 3, 4 dan 5).** Sekurang-kurangnya tiga larian percubaan penuh ke persekitaran ujian, setiap satu diikuti pengesahan dan pembetulan. Larian pertama mengukur kadar kejayaan asas; larian kedua selepas pembersihan; larian ketiga sebagai gladi bersih (dress rehearsal) dengan masa larian diukur untuk perancangan tetingkap cutover.

**Fasa 5 — Pengesahan dan pengesahan pemilik (Bulan 4–5).** Pengesahan automatik (kiraan baris, jumlah kawalan kewangan, integriti rujukan, kesahan geometri) dan pengesahan sampel manual oleh pemilik proses. Pemilik proses menandatangani Borang Pengesahan Data bagi setiap set data.

**Fasa 6 — Cutover dan selari (Bulan 5–6).** Pemuatan akhir dalam tetingkap cutover yang dipersetujui, diikuti tempoh operasi selari di mana rekod baharu dimasukkan ke dalam SISDATA sementara rekod lama kekal boleh dirujuk. Rekonsiliasi harian sepanjang tempoh selari sebelum sistem lama dihentikan.

### 15.4 Peraturan kualiti dan pengesahan

| Bil. | Peraturan | Tindakan apabila gagal |
| --- | --- | --- |
| 1 | Nombor lot mesti menepati format standard dan unik dalam gabungan daerah–mukim–lot | Rekod ke baris gilir pengecualian untuk semakan manual |
| 2 | Setiap rekod anak (pajakan, cukai, aset, kes) mesti merujuk lot yang wujud | Rekod ditahan; tidak dimuatkan sebagai rekod terapung |
| 3 | Keluasan lot mesti bernilai positif dan dalam julat munasabah | Ditanda untuk pengesahan; dimuatkan dengan bendera semakan |
| 4 | Geometri mesti sah secara topologi dan berada dalam sempadan negeri Selangor | Ditolak dengan laporan ralat kepada GIS Specialist |
| 5 | Tarikh mesti logik (tarikh tamat selepas tarikh mula; tiada tarikh masa hadapan yang tidak munasabah) | Ditanda untuk pembetulan |
| 6 | Jumlah kawalan kewangan (tunggakan, hasil sewaan) mesti sepadan dengan lejar sumber | Migrasi set data dihentikan sehingga percanggahan diselesaikan |
| 7 | Tiada nilai kosong pada medan wajib yang ditetapkan dalam Dokumen Pemetaan | Rekod ke baris gilir pengecualian |
| 8 | Rekod pendua dikenal pasti melalui padanan fuzzy pada nombor lot, pemilik dan keluasan | Digabungkan mengikut peraturan keutamaan sumber, dengan rekod asal dikekalkan |

### 15.5 Pengendalian pengecualian

Rekod yang gagal pengesahan tidak dibuang dan tidak dimuatkan secara senyap. Setiap rekod tersebut dimasukkan ke dalam baris gilir pengecualian yang mengandungi baris sumber asal, peraturan yang gagal, perihal ralat dan pegawai bahagian yang bertanggungjawab. Baris gilir ini dipaparkan sebagai senarai tindakan dalam SISDATA, diselesaikan secara berperingkat oleh pegawai MAIS, dan kadar penyelesaiannya dilaporkan dalam Laporan Kemajuan Migrasi mingguan. Rekod yang tidak dapat diselesaikan sebelum Go-Live dimuatkan sebagai rekod berstatus *perlu pengesahan* supaya tiada data hilang, dan muncul dalam senarai tindakan selepas Go-Live.

### 15.6 Hasil serahan trek data

- Daftar Sumber Data dan Laporan Profiling Awal
- Dokumen Pemetaan Data (medan sumber → medan sasaran, peraturan transformasi, peraturan keutamaan sumber)
- Pelan Migrasi Data termasuk jadual, tetingkap cutover dan prosedur rollback
- Skrip migrasi berversi dan log larian bagi setiap larian percubaan
- Laporan Pengesahan Migrasi bagi setiap larian (kiraan baris, jumlah kawalan, senarai pengecualian)
- Borang Pengesahan Data yang ditandatangani pemilik proses bagi setiap set data
- Laporan Kemajuan Migrasi mingguan sepanjang trek data
- Laporan Migrasi Akhir dan Laporan Rekonsiliasi Tempoh Selari

### 15.7 Kriteria penerimaan migrasi

1. Setiap set data dalam skop mempunyai Borang Pengesahan Data yang ditandatangani pemilik proses MAIS.
2. Kadar kejayaan migrasi bagi data kritikal (lot tanah, hak milik, pemerolehan, pajakan, cukai) mencapai sekurang-kurangnya sasaran yang dipersetujui semasa CRP-1, dan setiap rekod yang tidak berjaya hadir dalam baris gilir pengecualian dengan sebab yang dinyatakan.
3. Jumlah kawalan kewangan dalam SISDATA sepadan dengan lejar sumber dan rekod GRP MAIS sehingga sen terakhir, atau setiap percanggahan dijelaskan dan diterima secara bertulis.
4. Setiap rekod anak dalam produksi merujuk lot yang wujud; pertanyaan integriti rujukan mengembalikan sifar rekod terapung.
5. Larian gladi bersih dilaksanakan dalam tetingkap masa yang tidak melebihi tetingkap cutover yang dipersetujui, dan prosedur rollback berjaya diuji.
6. Setiap rekod produksi boleh dijejaki kembali kepada baris sumbernya melalui rujukan larian migrasi dan cap masa pemuatan.
7. Rekonsiliasi harian sepanjang tempoh selari menunjukkan sifar percanggahan yang tidak dijelaskan sebelum sistem lama dihentikan.

## 16. Pengujian dan Penerimaan

Bab ini menetapkan aras pengujian, kriteria masuk dan keluar bagi setiap aras, klasifikasi kecacatan, dan syarat penerimaan setiap gelombang serta Go-Live. Setiap keperluan SRS dalam Bab 6 hingga Bab 10 mesti dipautkan kepada sekurang-kurangnya satu kes ujian melalui Matriks Kebolehjejakan dalam Bab 19.

### 16.1 Aras pengujian

| Aras | Skop | Dijalankan oleh | Persekitaran |
| --- | --- | --- | --- |
| Ujian unit | Fungsi dan kelas individu, logik domain, peraturan pengesahan | Pasukan pembangunan Syarikat | Pembangunan |
| Ujian integrasi modul | Interaksi antara modul dalam monolit modular, perkhidmatan platform (workflow, notifikasi, audit) | Pasukan pembangunan Syarikat | Pembangunan/SIT |
| Ujian integrasi sistem (SIT) | Integrasi dengan sistem luar — PTG/e-Tanah, JUPEM, JPBD, MyLand, MyGDI, GRP MAIS, PBT/MBI, payment gateway, SSO | Pasukan QA Syarikat bersama pentadbir sistem luar | SIT |
| Ujian penerimaan pengguna (UAT) | Aliran proses hujung ke hujung mengikut peranan pengguna sebenar | Pengguna MAIS, dipandu pasukan Syarikat | UAT |
| Ujian prestasi dan beban | Masa respons, keupayaan pengguna serentak, penjanaan laporan berskala besar, pemuatan peta | Pasukan QA Syarikat | Pra-produksi |
| Ujian keselamatan | Secure Code Review, Vulnerability Assessment, Penetration Test | Pihak bebas atau pasukan keselamatan bertauliah | Pra-produksi |
| Ujian kebolehcapaian | Pematuhan WCAG 2.1 AA dan MyDS | Pasukan QA Syarikat | UAT |
| Ujian regresi | Keseluruhan fungsi gelombang terdahulu selepas setiap keluaran baharu | Automasi + QA Syarikat | SIT/UAT |
| Ujian migrasi data | Kelengkapan, ketepatan dan integriti data yang dimigrasikan | Pasukan data Syarikat bersama pemilik proses MAIS | UAT |
| Ujian pemulihan bencana | Prosedur sandaran, pemulihan, RTO dan RPO | Pasukan infrastruktur Syarikat bersama BTM MAIS | Pra-produksi |

### 16.2 Kriteria masuk dan keluar

| Aras | Kriteria masuk | Kriteria keluar |
| --- | --- | --- |
| Ujian integrasi modul | Ujian unit lulus; liputan kod memenuhi sasaran yang dipersetujui | Semua kes ujian integrasi lulus; tiada kecacatan Severity 1 atau 2 terbuka |
| SIT | Persekitaran SIT siap; akaun ujian sistem luar diperoleh; data ujian dimuatkan | Setiap titik integrasi berjaya diuji dua hala; log integrasi dan mekanisme retry dibuktikan berfungsi |
| UAT | SIT lulus; manual pengguna draf diserahkan; latihan pengguna UAT selesai; data migrasi percubaan dimuatkan | Semua kes ujian UAT dilaksanakan; tiada kecacatan Severity 1 atau 2 terbuka; Severity 3 mempunyai pelan pembetulan bertarikh |
| Ujian prestasi | Persekitaran pra-produksi menyamai spesifikasi produksi; data volum realistik dimuatkan | Semua sasaran prestasi dalam Bab 12 dicapai atau pengecualian diterima secara bertulis |
| Ujian keselamatan | Kod lengkap bagi gelombang berkenaan; persekitaran pra-produksi siap | Semua finding kritikal dan tinggi ditutup dan disahkan melalui ujian semula |
| Go-Live | Semua di atas lulus; Borang Pengesahan Data lengkap; pelan rollback diuji; pasukan sokongan bersedia | Keputusan Go/No-Go diluluskan oleh Jawatankuasa Projek MAIS |

### 16.3 Klasifikasi kecacatan dan masa pembetulan sasaran

| Severity | Definisi | Contoh | Masa pembetulan sasaran semasa UAT |
| --- | --- | --- | --- |
| Severity 1 — Kritikal | Fungsi teras tidak boleh digunakan; data rosak atau hilang; pelanggaran keselamatan | Rekod lot tidak boleh disimpan; pengguna melihat data daerah lain | Pembetulan dalam 1 hari bekerja |
| Severity 2 — Tinggi | Fungsi penting gagal tanpa penyelesaian sementara yang munasabah | Kelulusan workflow tidak bergerak ke peringkat seterusnya | Pembetulan dalam 3 hari bekerja |
| Severity 3 — Sederhana | Fungsi gagal tetapi ada penyelesaian sementara | Eksport Excel kehilangan satu kolum | Pembetulan dalam 10 hari bekerja |
| Severity 4 — Rendah | Isu kosmetik atau cadangan penambahbaikan | Teks label tidak konsisten | Dijadualkan dalam keluaran berikutnya |

### 16.4 Pengujian mengikut gelombang

Setiap gelombang (G0 hingga G5) menjalani kitaran lengkap ujian unit, integrasi modul, SIT dan UAT bagi modul dalam gelombang tersebut, serta ujian regresi penuh ke atas semua gelombang terdahulu. Penerimaan gelombang memerlukan Laporan Ujian Gelombang yang ditandatangani, dan modul dalam gelombang yang diterima tidak dibuka semula untuk perubahan skop kecuali melalui proses permintaan perubahan.

### 16.5 Keperluan khusus yang mesti dibuktikan semasa UAT

- Kesemua tujuh (7) jenis visualisasi dashboard (B-1.07) berfungsi — bar, pie, line, progress bar, heatmap, peta dan traffic light
- Kesepuluh (10) lapisan peta wajib (RFP 8.1.4) dipaparkan dengan pewarnaan tematik dan legenda
- Kelima-lima (5) fungsi analisis spatial (RFP 8.1.6) menghasilkan senarai lot yang boleh disemak
- Kesebelas (11) saluran pemerolehan tanah (A-6.01–A-6.11) masing-masing boleh didaftarkan dengan medan dan dokumen khususnya
- Drill-down daripada mana-mana KPI dashboard kepada rekod terperinci dalam tidak melebihi tiga (3) klik
- Penguatkuasaan RBAC peringkat baris (row-level security) dibuktikan melalui pertanyaan terus ke replika baca, bukan hanya melalui paparan
- Pemantauan lapangan melalui PWA berfungsi sepenuhnya dalam mod luar talian dan disegerakkan tanpa kehilangan rekod
- Imbasan QR aset menggunakan kamera telefon pintar standard tanpa pemasangan aplikasi
- Bayaran portal ditanda berjaya hanya selepas pengesahan sisi pelayan daripada payment gateway
- Jejak audit tidak boleh dipinda atau dipadam oleh pengguna biasa, termasuk peranan pentadbir sistem
- Paparan responsif berfungsi tanpa skrol mendatar pada desktop, tablet dan telefon pintar
- Pematuhan WCAG 2.1 AA pada skrin utama dan borang

### 16.6 Hasil serahan pengujian

- Pelan Ujian Induk (Master Test Plan) dan Strategi Ujian
- Spesifikasi Kes Ujian bagi setiap aras, dipautkan kepada kod keperluan SRS
- Skrip ujian UAT berasaskan peranan dan senario operasi sebenar
- Daftar Kecacatan (Defect Log) dengan status, severity, pemilik dan tarikh penyelesaian
- Laporan Ujian bagi setiap aras dan setiap gelombang
- Laporan Ujian Prestasi berserta data mentah pengukuran
- Laporan Secure Code Review, Vulnerability Assessment dan Penetration Test berserta bukti penutupan finding
- Laporan Ujian Kebolehcapaian WCAG 2.1 AA
- Laporan Ujian Pemulihan Bencana berserta masa RTO dan RPO yang diukur
- Sijil Penerimaan Pengguna (UAT Sign-off) bagi setiap gelombang
- Keputusan Go/No-Go yang didokumenkan

## 17. Latihan, Dokumentasi dan Pemindahan Teknologi

Matlamat bab ini adalah memastikan MAIS mampu mengendalikan, menyelenggara dan membangunkan SISDATA secara berdikari selepas tempoh jaminan tamat, tanpa kebergantungan berterusan kepada Syarikat.

### 17.1 Program latihan

| Kumpulan sasaran | Kandungan | Kaedah | Bilangan sesi minimum |
| --- | --- | --- | --- |
| Pengurusan tertinggi MAIS | Dashboard eksekutif, laporan portfolio, executive insight summary, AI prompt | Taklimat bersemuka | 1 sesi |
| Ketua Bahagian dan Ketua Unit | Dashboard pengurusan, kelulusan, laporan bahagian, pemantauan SLA | Bengkel bersemuka | 2 sesi |
| Pegawai operasi dan Pegawai Daerah | Kemasukan data, 11 saluran pemerolehan, pajakan, cukai, aduan, kes, aset | Latihan hands-on mengikut modul | Mengikut gelombang (G1–G5) |
| Pegawai lapangan | PWA pemantauan, mod luar talian, imbasan QR aset, rakaman gambar bertarikh | Latihan di lapangan | 2 sesi + 1 sesi di tapak |
| GIS Specialist MAIS | Pengurusan lapisan, import data spatial, pengesahan sempadan, metadata, analisis spatial, workstation GIS | Latihan teknikal mendalam | 3 sesi |
| Pentadbir sistem dan BTM MAIS | Pentadbiran sistem, RBAC, konfigurasi workflow dan notifikasi, pemantauan, sandaran dan pemulihan | Latihan teknikal mendalam | 4 sesi |
| Pentadbir kandungan portal dan Pangkalan Pengetahuan | CMS portal, penerbitan kandungan, semakan artikel, analitik | Bengkel | 2 sesi |
| Juruaudit dalaman MAIS | Audit trail, laporan pematuhan, jejak penjanaan laporan | Bengkel | 1 sesi |
| Pasukan sokongan BTM MAIS | Modul tiket, SLA, eskalasi, diagnosis masalah lazim, rujukan Pangkalan Pengetahuan | Latihan teknikal + bayangan (shadowing) | Sepanjang tempoh jaminan |

### 17.2 Pendekatan latihan

Latihan dijalankan mengikut gelombang penghantaran supaya pengguna dilatih tepat sebelum modul mereka digunakan, bukan dalam satu sesi besar pada akhir projek. Setiap sesi menggunakan data ujian yang realistik, bukan data contoh generik. Latihan Pentadbir Sistem dan GIS Specialist dijalankan dalam format latih pelatih (train-the-trainer) supaya MAIS mempunyai kapasiti melatih pegawai baharu sendiri. Semua sesi dirakam dan rakaman diserahkan sebagai bahan rujukan kekal MAIS. Sesi penyegaran (refresher) disediakan dalam tempoh jaminan atas permintaan MAIS.

### 17.3 Dokumentasi yang diserahkan

| Dokumen | Kandungan utama |
| --- | --- |
| Dokumen Keperluan Pengguna (URS) | Keperluan pengguna yang disahkan semasa CRP-1 |
| Dokumen Keperluan Perniagaan (BRS) | Objektif perniagaan, proses dan pemilik proses |
| Spesifikasi Keperluan Sistem (SRS) | Dokumen ini — keperluan setiap modul, kod keperluan, keutamaan dan sumber |
| Spesifikasi Reka Bentuk Sistem (SDS) | Reka bentuk seni bina, komponen, skema pangkalan data, reka bentuk API |
| Kamus Data (Data Dictionary) | Setiap jadual, medan, jenis data, kekangan dan hubungan |
| Spesifikasi API | Setiap endpoint, parameter, muatan, kod respons dan contoh |
| Dokumen Pemetaan dan Pelan Migrasi Data | Bab 15 dan hasil serahan trek data |
| Manual Pengguna mengikut peranan | Panduan langkah demi langkah bagi setiap peranan dan modul |
| Manual Pentadbir Sistem | Konfigurasi, RBAC, workflow, notifikasi, ambang indikator dan parameter sistem |
| Manual Operasi | Prosedur harian, sandaran, pemulihan, pemantauan dan tindakan amaran |
| Manual Teknikal | Seni bina penggunaan, konfigurasi persekitaran, prosedur penggunaan versi (deployment) |
| Manual Pentadbir Portal dan Pengguna Awam | Pentadbiran CMS portal dan panduan pengguna awam |
| SOP operasi | Prosedur operasi standard bagi proses utama setiap modul |
| Nota Keluaran (Release Notes) | Perubahan setiap versi, pembetulan dan isu diketahui |
| Dokumen Pelan Pemulihan Bencana | Prosedur, RTO, RPO dan hasil ujian pemulihan |
| Laporan Ujian dan Sijil Penerimaan | Bab 16 |
| Pelan Peningkatan Sistem 3–5 Tahun | Cadangan modul tambahan, integrasi dan teknologi (B-4.06) |

Semua dokumentasi diserahkan dalam Bahasa Melayu, dalam format boleh sunting (Word/Markdown) dan PDF, dan diindeks ke dalam Pangkalan Pengetahuan Berbantu AI (Modul K1) supaya boleh dirujuk melalui arahan bahasa biasa.

### 17.4 Pemindahan teknologi

Pemindahan teknologi dilaksanakan secara berperingkat sepanjang projek, bukan sebagai penyerahan sehala pada akhir kontrak:

1. **Penyertaan pembangunan.** Pegawai BTM MAIS dijemput menyertai sesi semakan reka bentuk, demonstrasi gelombang dan semakan kod pada peringkat yang dipersetujui.
2. **Penyerahan kod sumber.** Keseluruhan kod sumber, skrip pangkalan data, skrip migrasi, konfigurasi infrastruktur sebagai kod (IaC) dan saluran CI/CD diserahkan kepada MAIS dalam repositori yang dikawal MAIS, bersama hak milik penuh.
3. **Penyerahan kelayakan dan kawalan.** Semua akaun pentadbir, kunci penyulitan, kunci API, akaun awan dan akaun perkhidmatan diserahkan kepada MAIS dengan prosedur putaran kredensial yang didokumenkan.
4. **Bayangan sokongan (shadowing).** Pasukan BTM MAIS membayangi pasukan sokongan Syarikat dalam pengendalian tiket sebenar sepanjang tempoh jaminan, dengan peralihan beransur-ansur daripada pemerhati kepada pelaksana utama.
5. **Kemahiran pembangunan dan penyelenggaraan.** Latihan teknikal meliputi struktur kod, aliran penggunaan versi, prosedur penambahan medan dan laporan baharu, serta prosedur penyelesaian masalah lazim.
6. **Pengesahan kesiapsiagaan.** Sebelum tempoh jaminan tamat, pasukan BTM MAIS membuktikan keupayaan mengendalikan tiket, melaksanakan penggunaan versi, memulihkan daripada sandaran dan mengkonfigurasi parameter sistem tanpa bantuan Syarikat.

### 17.5 Kriteria penerimaan

1. Setiap kumpulan sasaran dalam jadual 17.1 menerima bilangan sesi minimum yang ditetapkan, dengan senarai kehadiran dan penilaian sesi diserahkan.
2. Rakaman setiap sesi latihan dan bahan latihan diserahkan kepada MAIS dalam format boleh guna semula.
3. Kesemua dokumen dalam jadual 17.3 diserahkan dalam Bahasa Melayu, dalam format boleh sunting dan PDF, dan berjaya diindeks dalam Modul K1.
4. Kod sumber, skrip pangkalan data, skrip migrasi, IaC dan saluran CI/CD berjaya diserahkan ke repositori MAIS, dan pasukan BTM MAIS berjaya melaksanakan satu penggunaan versi ujian secara berdikari.
5. Semua kelayakan pentadbir dan kunci diserahkan kepada MAIS dengan prosedur putaran didokumenkan dan diuji.
6. Pasukan BTM MAIS berjaya mengendalikan sekurang-kurangnya satu tiket setiap tahap keterukan secara berdikari sebelum tempoh jaminan tamat.
7. Ujian pemulihan daripada sandaran berjaya dilaksanakan oleh pasukan BTM MAIS tanpa bantuan Syarikat.

## 18. SLA, Jaminan dan Sokongan

### 18.1 Tempoh jaminan

Tempoh jaminan bermula pada tarikh Penerimaan Akhir (Final Acceptance) dan berkuat kuasa selama tempoh yang ditetapkan dalam kontrak. Dalam tempoh ini Syarikat bertanggungjawab membetulkan semua kecacatan tanpa kos tambahan kepada MAIS, termasuk kecacatan yang hanya ditemui selepas Go-Live, dan menyediakan sokongan pengguna, penyelenggaraan pencegahan, tampalan keselamatan dan laporan berkala. Kecacatan yang dibetulkan tidak memendekkan tempoh jaminan bagi komponen berkenaan.

### 18.2 Tahap keterukan dan sasaran SLA

| Severity | Definisi operasi | Masa respons | Masa penyelesaian sasaran |
| --- | --- | --- | --- |
| Severity 1 — Kritikal | Sistem tidak boleh digunakan; kehilangan atau kerosakan data; pelanggaran keselamatan; fungsi teras terhenti untuk semua pengguna | Mengikut tempoh yang ditetapkan dalam kontrak — paling pantas antara semua aras | Penyelesaian atau penyelesaian sementara yang diterima dalam tempoh terpantas yang ditetapkan |
| Severity 2 — Tinggi | Fungsi penting gagal tanpa penyelesaian sementara munasabah; sebahagian besar pengguna atau satu bahagian terjejas | Tempoh kedua terpantas | Mengikut sasaran kontrak |
| Severity 3 — Sederhana | Fungsi gagal tetapi ada penyelesaian sementara; kesan terhad kepada sebilangan pengguna | Mengikut sasaran kontrak | Mengikut sasaran kontrak atau keluaran berjadual berikutnya |
| Severity 4 — Rendah | Isu kosmetik, permintaan maklumat atau cadangan penambahbaikan | Mengikut sasaran kontrak | Keluaran berjadual berikutnya |

Ambang masa respons dan masa penyelesaian bagi setiap aras dikonfigurasi dalam Modul T1 (SRS-TKT-08) supaya boleh dilaraskan oleh pentadbir apabila kontrak dikemas kini, tanpa perubahan kod. Pengiraan SLA mengikut waktu perkhidmatan yang dipersetujui, dan jam berhenti apabila tiket menunggu maklum balas pelapor.

### 18.3 Ketersediaan sistem

Sasaran ketersediaan sistem adalah seperti yang ditetapkan dalam Bab 12 dan kontrak, diukur bulanan dan dikecualikan bagi tetingkap penyelenggaraan berjadual yang dimaklumkan terlebih dahulu kepada MAIS. Masa henti tidak berjadual direkod dan dilaporkan dalam laporan sokongan bulanan beserta punca akar dan tindakan pencegahan.

### 18.4 Skop sokongan

**Termasuk dalam tempoh jaminan:**

- Pembetulan semua kecacatan sistem termasuk kecacatan yang ditemui selepas Go-Live
- Sokongan pengguna melalui Modul T1 Tiket Sokongan Sistem
- Penyelenggaraan pencegahan — pemantauan prestasi, pengoptimuman pertanyaan, penyelenggaraan indeks dan pembersihan log
- Tampalan keselamatan bagi sistem pengoperasian, pelayan aplikasi, pangkalan data, rangka kerja dan kebergantungan pihak ketiga
- Pemantauan sandaran dan ujian pemulihan berkala
- Pengemaskinian dokumentasi dan Pangkalan Pengetahuan selepas setiap keluaran
- Laporan sokongan bulanan dan laporan prestasi SLA
- Sesi latihan penyegaran atas permintaan MAIS
- Pengemaskinian Pelan Peningkatan Sistem 3–5 tahun setiap tahun (B-4.06)

**Tidak termasuk (memerlukan permintaan perubahan berasingan):**

- Penambahan modul atau fungsi baharu di luar skop SRS ini
- Integrasi dengan sistem luar yang tidak dinyatakan dalam Bab 14
- Perubahan proses perniagaan yang memerlukan reka bentuk semula modul
- Migrasi set data baharu di luar skop Bab 15

### 18.5 Pengurusan eskalasi

Eskalasi dilaksanakan secara automatik oleh Modul T1 apabila ambang SLA dihampiri atau dilepasi (SRS-TKT-09), mengikut hierarki peranan yang dikonfigurasi — daripada juruteknik kepada penyelia sokongan, kemudian kepada pengurus projek Syarikat, dan seterusnya kepada Jawatankuasa Projek MAIS bagi isu kritikal yang tidak diselesaikan. Setiap eskalasi direkod beserta peringkat, penerima, sebab dan cap masa, dan muncul dalam laporan eskalasi bulanan.

### 18.6 Laporan sokongan berkala

Syarikat menyerahkan laporan sokongan bulanan yang merangkumi jumlah tiket mengikut kategori dan severity, pencapaian SLA mengikut aras, senarai tiket yang melepasi SLA beserta sebab, masa henti sistem dan punca akar, tampalan keselamatan yang dilaksanakan, aktiviti penyelenggaraan pencegahan, trend masalah berulang dan tindakan pencegahan yang dicadangkan, serta status pemindahan teknologi kepada BTM MAIS. Laporan ini dijana daripada data tiket sebenar dalam Modul T1 (SRS-TKT-19), bukan disusun secara manual.

### 18.7 Peralihan selepas tempoh jaminan

Sebelum tempoh jaminan tamat, Syarikat melaksanakan sesi penyerahan sokongan yang merangkumi penyerahan keseluruhan sejarah tiket, konfigurasi SLA, hierarki eskalasi, pautan artikel Pangkalan Pengetahuan, senarai isu diketahui yang masih terbuka, dan senarai kebergantungan pihak ketiga beserta tarikh luput lesen dan sijil. Fungsi sokongan dipindahkan kepada BTM MAIS tanpa kehilangan data historikal (SRS-TKT-21). Sekiranya MAIS memilih memperbaharui sokongan secara komersial, terma tersebut dirundingkan secara berasingan dan tidak menjadi prasyarat kepada keupayaan MAIS mengendalikan sistem secara berdikari.

### 18.8 Kriteria penerimaan

1. Ambang SLA bagi kesemua empat (4) aras keterukan dikonfigurasi dalam Modul T1 dan sepadan dengan terma kontrak, serta boleh dilaraskan oleh pentadbir tanpa perubahan kod.
2. Laporan prestasi SLA dijana automatik daripada data tiket sebenar dan boleh dieksport dalam format PDF dan Excel.
3. Eskalasi automatik berfungsi pada setiap peringkat hierarki yang dikonfigurasi dan direkod dalam laporan eskalasi.
4. Laporan sokongan bulanan diserahkan tepat pada masa sepanjang tempoh jaminan dan mengandungi setiap elemen dalam seksyen 18.6.
5. Sesi penyerahan sokongan dilaksanakan sebelum tempoh jaminan tamat, dengan senarai isu diketahui dan senarai kebergantungan pihak ketiga diserahkan secara bertulis.
6. BTM MAIS mengesahkan secara bertulis bahawa fungsi sokongan telah diterima lengkap tanpa kehilangan data historikal tiket, konfigurasi SLA dan pautan artikel.

## 19. Matriks Kebolehjejakan Keperluan

Bab ini menyatukan kesemua keperluan dalam Bab 6 hingga Bab 10 supaya setiap satu dapat dijejaki kembali kepada sumbernya — spesifikasi Lampiran H RFP, seksyen badan RFP, kod URS, atau analisis jurang antara RFP dan cadangan teknikal. Matriks ini juga menjadi asas pemetaan kepada kes ujian dalam Bab 16: setiap kod keperluan mesti muncul sekurang-kurangnya sekali dalam Spesifikasi Kes Ujian.

### 19.1 Ringkasan keperluan mengikut modul

| Modul | Kod | Bab | Gelombang | Julat kod SRS | Jumlah | Wajib | Perlu | Baik-ada | Sumber utama |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Modul 11 · Dokumen Digital (DMS) | MOD-DMS | 6 | G0 | SRS-DMS-01–26 | 26 | 22 | 3 | 1 | B-3.01–B-3.08 |
| Modul 14 · Workflow dan Kelulusan | MOD-WFE | 6 | G0 | SRS-WFE-01–24 | 24 | 19 | 4 | 1 | A-3.05–A-3.09, A-3.13 |
| Modul 15 · Notifikasi | MOD-NTF | 6 | G0 | SRS-NTF-01–24 | 24 | 17 | 6 | 1 | A-3.14–A-3.19 |
| Modul 17 · Pentadbiran Sistem | MOD-ADM | 6 | G0 | SRS-ADM-01–29 | 29 | 24 | 4 | 1 | A-3.10–A-3.20, RFP 11.3 |
| Modul 18 · Pengurusan Pengguna, Peranan dan Capaian | MOD-RBC | 6 | G0 | SRS-RBC-01–27 | 27 | 24 | 3 | 0 | A-16.01–A-16.08, A-16.16, RFP 11.2 |
| Modul 19 · Audit Trail | MOD-AUD | 6 | G0 | SRS-AUD-01–26 | 26 | 23 | 3 | 0 | A-16.09–A-16.13 |
| Modul 2 · Pangkalan Data Induk dan Profil Hartanah 360° | MOD-PRO | 7 | G1 | SRS-PRO-01–30 | 30 | 26 | 4 | 0 | A-4.01–A-4.12, RFP 7.1–7.2 |
| Modul 3 · Hak Milik Tanah | MOD-HKM | 7 | G1 | SRS-HKM-01–22 | 22 | 16 | 5 | 1 | A-5.01–A-5.08 |
| Modul 4 · Rekod Pemerolehan | MOD-PER | 7 | G1 | SRS-PER-01–30 | 30 | 25 | 5 | 0 | A-6.01–A-6.12, RFP 7.1.1(c) |
| Modul 5 · Pajakan dan Kontrak | MOD-PJK | 8 | G3 | SRS-PJK-01–28 | 28 | 25 | 3 | 0 | A-8.01–A-8.11, RFP 6.6–6.7 |
| Modul 6 · Pemantauan Cukai Tanah | MOD-CKI | 8 | G3 | SRS-CKI-01–24 | 24 | 22 | 2 | 0 | A-7.01–A-7.10 |
| Modul 7 · Pemantauan Tanah | MOD-PTN | 8 | G3 | SRS-PTN-01–24 | 24 | 19 | 5 | 0 | A-10.01–A-10.10 |
| Modul 8 · Aduan dan Isu Tanah | MOD-ADU | 8 | G3 | SRS-ADU-01–27 | 27 | 24 | 3 | 0 | A-11.01–A-11.10 |
| Modul 9 · Kes Mahkamah dan Perundangan | MOD-KES | 8 | G4 | SRS-KES-01–24 | 24 | 19 | 5 | 0 | A-12.01–A-12.08 |
| Modul 10 · Tukar Syarat, Pelupusan dan Ukur | MOD-TSU | 8 | G4 | SRS-TSU-01–27 | 27 | 26 | 1 | 0 | A-9.01–A-9.10 |
| Modul 13 · Inventori Aset Fizikal | MOD-AST | 8 | G4 | SRS-AST-01–26 | 26 | 23 | 3 | 0 | A-13.01–A-13.08 |
| Modul 1 · Dashboard Eksekutif | MOD-DSH | 9 | G2 | SRS-DSH-01–25 | 25 | 22 | 3 | 0 | B-1.01–B-1.08, B-1.12, B-2.01–B-2.08 |
| Modul 12 · GIS Mapping dan Kepintaran Spatial | MOD-GIS | 9 | G2 | SRS-GIS-01–35 | 35 | 32 | 2 | 1 | A-14.01–A-14.14, RFP 8.0 |
| Modul 16 · Laporan dan Analitik | MOD-LAP | 9 | G5 | SRS-LAP-01–23 | 23 | 12 | 10 | 1 | B-1.09–B-1.12, B-4.01–B-4.06 |
| Modul PWS · Portal Hartanah Wakaf Selangor | MOD-PWS | 10 | G5 | SRS-PWS-01–28 | 28 | 24 | 4 | 0 | C-1.01–C-1.08, RFP 6.5–6.7 |
| Modul T1 · Tiket Sokongan Sistem | MOD-TKT | 10 | G5 | SRS-TKT-01–21 | 21 | 19 | 1 | 1 | A-21.01–A-21.03, RFP 18.5 |
| Modul K1 · Pangkalan Pengetahuan Berbantu AI | MOD-KBA | 10 | G5 | SRS-KBA-01–22 | 22 | 18 | 4 | 0 | B-4.02–B-4.03, A-20.03–A-20.07 |
| **Jumlah** | **22 modul** | **6–10** | **G0–G5** | — | **572** | **481** | **83** | **8** | — |

### 19.2 Ringkasan mengikut lapisan dan gelombang

| Lapisan | Bab | Modul | Keperluan | Gelombang |
| --- | --- | --- | --- | --- |
| Perkhidmatan platform | 6 | 6 modul (DMS, WFE, NTF, ADM, RBC, AUD) | 156 | G0 (Bulan 3.0–3.5) |
| Domain teras | 7 | 3 modul (PRO, HKM, PER) | 82 | G1 (Bulan 3.5–4.0) |
| Domain operasi | 8 | 7 modul (PJK, CKI, PTN, ADU, KES, TSU, AST) | 180 | G3–G4 (Bulan 4.0–5.0) |
| Lapisan wawasan | 9 | 3 modul (DSH, GIS, LAP) | 83 | G2 dan G5 |
| Portal dan sokongan | 10 | 3 modul (PWS, TKT, KBA) | 71 | G5 (Bulan 5.0–5.5) |
| **Jumlah** | **6–10** | **22 modul** | **572** | **G0–G5** |

### 19.3 Ringkasan mengikut sumber keperluan

| Jenis sumber | Bilangan keperluan merujuknya | Catatan |
| --- | --- | --- |
| Lampiran H RFP (kod A-, B-, C-) | 464 | Setiap kod Lampiran H yang terpakai dipetakan kepada sekurang-kurangnya satu keperluan SRS |
| Kod URS | 290 | Keperluan pengguna yang akan disahkan semula semasa CRP-1 |
| Seksyen badan RFP (contoh 7.1, 8.1.6, 11.2, 18.5) | Merentas semua modul | Dipetik secara eksplisit dalam kolum Sumber setiap keperluan |
| Analisis jurang (`Jurang`) | 68 | Keperluan yang dikenal pasti daripada perbandingan RFP–cadangan teknikal, bukan dinyatakan secara eksplisit dalam Lampiran H |

Satu keperluan boleh merujuk lebih daripada satu jenis sumber, jadi jumlah lajur kedua melebihi 572.

### 19.4 Keperluan daripada analisis jurang

Enam puluh lapan (68) keperluan dalam dokumen ini bertanda `Jurang` dalam kolum Sumber. Keperluan ini tidak dinyatakan secara eksplisit sebagai spesifikasi Lampiran H, tetapi dikenal pasti sebagai perlu melalui perbandingan antara keperluan RFP dan cadangan teknikal yang dikemukakan. Taburannya mengikut modul:

| Modul | Bilangan keperluan `Jurang` | Modul | Bilangan keperluan `Jurang` |
| --- | --- | --- | --- |
| MOD-ADU | 10 | MOD-NTF | 3 |
| MOD-PJK | 6 | MOD-AUD | 3 |
| MOD-TSU | 4 | MOD-AST | 3 |
| MOD-TKT | 4 | MOD-WFE | 2 |
| MOD-RBC | 4 | MOD-PTN | 2 |
| MOD-PWS | 4 | MOD-LAP | 2 |
| MOD-KES | 4 | MOD-PRO | 1 |
| MOD-KBA | 4 | MOD-HKM | 1 |
| MOD-DMS | 4 | MOD-GIS | 1 |
| MOD-CKI | 4 | MOD-DSH | 1 |
|  |  | MOD-ADM | 1 |

Kepadatan tertinggi berada pada MOD-ADU (Aduan dan Isu Tanah) dan MOD-PJK (Pajakan dan Kontrak) — dua modul yang paling banyak bersentuhan dengan proses operasi harian dan transaksi kewangan, dan yang paling kurang diperincikan dalam cadangan teknikal berbanding keperluan RFP. Kedua-dua modul ini perlu mendapat perhatian khusus semasa CRP-1 dan CRP-2.

### 19.5 Peraturan kebolehjejakan

1. **Setiap keperluan mempunyai kod unik** dalam format `SRS-<kod modul>-<nombor>` dan kod tersebut tidak diguna semula walaupun keperluan dibatalkan.
2. **Setiap keperluan mempunyai sekurang-kurangnya satu sumber** dalam kolum Sumber — kod Lampiran H, seksyen RFP, kod URS atau `Jurang`.
3. **Setiap keperluan dipetakan kepada sekurang-kurangnya satu kes ujian** dalam Spesifikasi Kes Ujian (Bab 16). Keperluan tanpa kes ujian tidak boleh diterima sebagai siap.
4. **Setiap keperluan `Wajib` mesti disiapkan sebelum Go-Live.** Keperluan `Perlu` disiapkan dalam gelombangnya kecuali ditangguhkan secara bertulis oleh MAIS. Keperluan `Baik-ada` dilaksanakan jika masa dan sumber membenarkan, dan yang tidak dilaksanakan dimasukkan ke dalam Pelan Peningkatan Sistem 3–5 tahun.
5. **Perubahan keperluan melalui proses permintaan perubahan.** Setiap penambahan, pengubahan atau pembatalan keperluan selepas pengesahan CRP-1 direkod sebagai permintaan perubahan dengan kesan kepada jadual, kos dan kes ujian dinyatakan.
6. **Matriks ini dikemas kini pada setiap keluaran versi SRS** dan versi terkini diindeks ke dalam Pangkalan Pengetahuan Berbantu AI (Modul K1) supaya boleh dirujuk melalui arahan bahasa biasa.

### 19.6 Hasil serahan kebolehjejakan

- Matriks Kebolehjejakan Keperluan penuh dalam format Excel — satu baris bagi setiap 572 keperluan, dengan kolum kod keperluan, modul, bab, gelombang, keutamaan, sumber Lampiran H, sumber URS, seksyen RFP, kes ujian berkaitan, status pelaksanaan dan status ujian
- Matriks Pematuhan Lampiran H — 280 spesifikasi Lampiran H dipetakan kepada kod keperluan SRS, bersama penandaan Y/T/S seperti dikehendaki RFP
- Laporan Jurang Keperluan — 68 keperluan bertanda `Jurang` beserta justifikasi dan rujukan RFP yang menjadi asas pengenalpastian
- Laporan Status Kebolehjejakan bagi setiap gelombang — bilangan keperluan siap, diuji dan diterima berbanding jumlah dalam gelombang tersebut
