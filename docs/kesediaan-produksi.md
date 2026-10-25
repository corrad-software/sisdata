# Senarai Semak Kesediaan Produksi — SISDATA

_Draf 2026-10-25. Disediakan daripada kod dalam repo ini (`app/`, `config/`, `tests/`, `infra/`, `.github/`, `frontend/`, `pws/`) serta `docs/srs/requirements.csv`, `docs/PLAN-SISDATA.md` (§11, §14, §15) dan `docs/RTM.md`. Dokumen ini tidak dijana oleh `php artisan srs:rtm`. Kemas kini dengan tangan apabila ujian baharu ditambah._

**Konvensyen.** Laluan ujian ditulis relatif kepada `tests/Modules/` melainkan dinyatakan lain (cth. `tests/Feature/...`). `Kelas::kaedah` menunjukkan ujian PHPUnit yang wujud (setiap nama telah disemak dengan grep pada 2026-10-25). Kebanyakan keperluan merentas belum mempunyai ujian bernama `test_SRS_<GEN|INT|NFR|SEC|INF>_*`. Oleh sebab itu RTM menunjukkan 0% bagi INF/INT/NFR/SEC. Lajur "Bukti" di bawah memetakannya kepada ujian modul yang sudah membuktikan tingkah laku yang sama.

**Status:** `Dipenuhi oleh kod` · `Perlu infrastruktur` · `Perlu proses/dokumen` · `Perlu input MAIS` · `Sebahagian` (gabungan dipisah dengan "+").

---

## Tindakan utama MAIS sebelum produksi

1. **Persekitaran AWS (SRS-INF-02/03/06):** sediakan DEV, SIT, UAT, PROD dan DR dalam akaun AWS MAIS. Komponen PROD:
   - RDS PostgreSQL 17 Multi-AZ dengan sambungan `postgis`, `vector` dan `pg_trgm` (dicipta oleh `rds_superuser`), serta satu replika baca;
   - ElastiCache Redis Multi-AZ;
   - S3 dengan SSE-KMS dan versioning;
   - ALB dengan sijil ACM (polisi TLS 1.3) dan AWS WAF;
   - sekurang-kurangnya 2 AZ bagi lapisan aplikasi.
2. **Sandaran & DR (SRS-INF-09..20):** AWS Backup dan RDS PITR untuk mencapai **RPO 15 minit / RTO 4 jam**, dengan retensi 30 hari / 12 minggu / 12 bulan / tahunan. Sediakan replika rentas rantau (RDS + S3 CRR) ke rantau DR. Sasaran ini perlu disahkan di Gate 1 (SRS-INF-20).
3. **SSO MAIS (SRS-SEC-07/08):** daftarkan SISDATA sebagai klien OIDC. Perkara yang diperlukan:
   - client ID/secret;
   - redirect URI `https://<domain>/auth/oidc/callback`;
   - tuntutan `bahagian`, `unit`, `jawatan` dan peranan;
   - `acr`/`amr` untuk MFA step-up.

   Selepas itu set `AUTH_PASSWORD_LOGIN=false`.
4. **Integrasi sebenar (SRS-INT-16, PLAN §11):** dapatkan spesifikasi dan akses bagi payment gateway (FPX/kad), GRP MAIS, e-Sewa, penyedia cukai berpusat (MBI/PBT/PTD), Pejabat Tanah, Portal Wakaf, e-Faraid, Sistem Aduan berpusat, sistem mahkamah/AGC, SMS dan WhatsApp. **Semuanya masih mock** (lihat §B).
5. **SIEM, e-mel & amaran (SRS-INF-22, SRS-ADM-27):** sediakan hos:port syslog/CEF SIEM (TLS), pelayan SMTP MAIS dan saluran amaran ICT yang dipersetujui.
6. **Pengurusan kunci (SRS-SEC-17):** gunakan KMS/Secrets Manager untuk `APP_KEY`, `DMS_ENCRYPTION_KEY`, kata laluan DB/Redis, rahsia OIDC/Metabase dan rahsia integrasi. Tetapkan jadual penggiliran dan audit CloudTrail.
7. **Data rujukan rasmi:**
   - sempadan mukim JUPEM/PTG (sempadan sekarang ialah anggaran Voronoi);
   - pengesahan 5 kod mukim yang diteka;
   - kalendar cuti umum Selangor bagi tahun selepas 2027;
   - kelulusan bertulis dan prosedur penyamaran bagi sebarang data sebenar di luar PROD (SRS-SEC-18).
8. **Angka dasar (§C):** SLA tiket S1–S4, kadar penalti CKI/PJK, formula agihan manfaat, dasar istibdal, had kelulusan kewangan, tempoh retensi dan polisi kata laluan.
9. **Keselamatan & pematuhan (SRS-SEC-01..06, 25..35):**
   - Dasar Keselamatan Siber/Polisi ICT MAIS untuk pemetaan kawalan;
   - NDA;
   - senarai kakitangan yang dibenarkan dan akses VPN;
   - pelantikan pihak ketiga bagi VAPT dan Source Code Review.
10. **Keputusan teknikal yang belum muktamad:**
    - SuperMap atau kekal GeoServer;
    - hos dan hardening WordPress (`pws/`, PLAN §14 #7);
    - instance untuk Ollama/LLM tempatan;
    - Metabase OSS;
    - token API legap (sekarang) atau JWT (SRS-INT-03).

---

## A. Pemetaan keperluan merentas SRS

### A.1 Keperluan umum modul — SRS-GEN-01..09 (SRS §5.2)

| Kod | Ringkasan keperluan (pendek) | Status | Bukti / tindakan |
| --- | --- | --- | --- |
| SRS-GEN-01 | Kebenaran melalui RBAC pusat pada peringkat model | Dipenuhi oleh kod (ujian: lihat §Ujian baharu) | `App\Modules\Rbc` (Gate → `AccessControl`, trait `ScopedByAccess`). Ujian: `Rbc/AccessControlTest::test_SRS_GEN_01_every_rbc_and_user_route_authorises_through_the_gate`, `Pro/LotMasterTest::test_SRS_GEN_01_user_without_permission_is_refused`, `Ref/ReferenceApiTest::test_SRS_GEN_01_ref_api_requires_login_and_the_view_permission`, `Tsu/UrusanTemplatTest::test_SRS_GEN_01_endpoints_require_permission_and_respect_district_scope`, `Rbc/AccessControlTest::test_SRS_RBC_08_gate_and_can_middleware_delegate_to_access_control`. Ujian merentas semua laluan modul dicadangkan. |
| SRS-GEN-02 | Semua CRUD, paparan sensitif, kelulusan dan perubahan status diaudit secara pusat | Dipenuhi oleh kod (ujian: lihat §Ujian baharu) | Pendengar global MOD-AUD (`Aud/Services/TrailAuditLogger`, `AuditWriter`). Ujian: `Aud/AuditRecordingTest::test_SRS_AUD_01_create_update_delete_of_any_model_is_recorded`, `::test_SRS_AUD_09_global_listener_covers_a_model_with_no_audit_code_at_all`, `::test_SRS_AUD_02_explicit_entries_for_view_download_print_approval_and_ai_prompt`, `::test_SRS_AUD_18_workflow_decision_entries_keep_approver_decision_and_stage`. |
| SRS-GEN-03 | Kelulusan hanya melalui enjin workflow pusat | Dipenuhi oleh kod | MOD-WFE (`WorkflowEngine`). Ujian: `Kes/SampelAliranTest::test_SRS_GEN_03_kes_flows_seeded_active_in_the_real_workflow_engine`, `Hkm/WorkflowIntegrationTest::test_SRS_HKM_19_seeded_flow_has_two_mfa_stages_and_applies_only_after_both`, `Cki/BayaranTest::test_SRS_CKI_14_value_threshold_decides_approval_level_through_the_central_workflow`, `Adm/ParameterTest::test_SRS_ADM_18_uses_the_workflow_engine_when_available`. |
| SRS-GEN-04 | Dokumen disimpan/dicapai melalui API DMS pusat | Dipenuhi oleh kod (ujian: lihat §Ujian baharu) | Semua modul memanggil `Dms\Contracts\DocumentStore::store()`, contohnya `Pjk/Services/ResitService.php`, `Per/Services/CaseDocuments.php` dan `Adu/Services/AduanService.php`. Ujian: `Per/DokumenDanPeringkatTest::test_SRS_PER_16_documents_go_through_dms_as_sulit_with_owner_pemerolehan`. **Pengecualian:** fail eksport laporan LAP ditulis terus ke `LAP_DISK` (`Lap/Services/Penjana.php:202`). Ia hasil jana sementara, bukan dokumen rekod, dan perlu didokumenkan. |
| SRS-GEN-05 | Carian global & terperinci (lot, mukim, daerah, status, hak milik, pemilik, kata kunci) | Dipenuhi oleh kod | `Adm/Services/PendaftaranCarian` + `SearchProvider` setiap modul. Ujian: `Adm/CarianTest::test_SRS_ADM_21_global_search_by_lot_number_returns_records_from_several_modules`, `::test_SRS_ADM_21_detailed_search_by_filters_uses_filterable_providers_only`, `Pro/ProfileApiTest::test_SRS_GEN_05_lots_join_global_and_detailed_search_within_scope`, `Kes/IntegrasiTest::test_SRS_GEN_05_global_search_for_legal_users_only`. |
| SRS-GEN-06 | UI patuh MyDS, WCAG 2.1 AA, desktop/tablet/telefon | Sebahagian + Perlu proses/dokumen | `@govtechmy/myds-style` (Tailwind 3.4) dan pustaka `frontend/components/ui`. Tiada ujian axe automatik; Playwright hanya projek `Desktop Chrome` (`frontend/playwright.config.ts`). DSH-20 diuji melalui pelayar sahaja. Tindakan: tambah ujian e2e axe dan saiz paparan 375 px (§Ujian baharu, bahagian frontend). Audit WCAG oleh pihak ketiga juga diperlukan. |
| SRS-GEN-07 | Borang menyimpan draf secara automatik | Sebahagian | Draf pelayan: `Dsh/PortalInboxTest::test_SRS_DSH_22_drafts_are_saved_reopened_edited_and_discarded_by_their_owner_only`. Autosimpan `localStorage` hanya ada pada `frontend/app/(dashboard)/admin/pjk/kontrak/baharu/page.tsx` dan `.../ast/aset/baru/page.tsx`. Tindakan: jadikan corak ini sebuah hook kongsi untuk semua borang kemasukan data. |
| SRS-GEN-08 | Bantuan dalam talian berkonteks + panduan | Dipenuhi oleh kod + Perlu input MAIS | `Dsh/Services/Bantuan.php`, `frontend/components/tkt/BantuanTiket.tsx`. Ujian: `Dsh/PortalInboxTest::test_SRS_DSH_25_online_help_has_guides_faq_and_manual_for_the_current_screen`, `Tkt/IntakeTest::test_SRS_TKT_02_context_is_captured_and_personal_data_masked`. Parameter `dsh.url_manual` masih kosong: perlukan URL manual pengguna rasmi. |
| SRS-GEN-09 | UI, label, mesej & laporan dalam BM | Sebahagian (ujian: lihat §Ujian baharu) | `APP_LOCALE=ms`, `lang/ms.json`, `lang/ms/*.php`, `app/Modules/*/lang/ms`. Ujian (belum bernama SRS): `tests/Feature/LocalizationTest::test_malay_translations_cover_every_english_key`, `::test_json_strings_are_translated`, `::test_validation_messages_and_attributes_are_in_malay`. PLAN §15 (F0 #7) mencatat sebahagian label frontend masih dalam BI — disahkan semasa UAT: kerangka asal (halaman log masuk/daftar: "Log in to your account", "Email", "Password", "Remember me", "Register"; menu pengguna "Log Out"; halaman Users & Settings) masih BI, dan kotak "Remember me" tidak berfungsi (sengaja diabaikan oleh SRS-RBC-18). Perlu diterjemah sebelum UAT MAIS (ujian e2e `auth.spec.ts`/`settings.spec.ts` perlu dikemas kini serentak). Ujian baharu: `test_SRS_GEN_09_*` (bahagian akhir). |

### A.2 Integrasi — SRS-INT-01..18 (SRS §11)

| Kod | Ringkasan keperluan (pendek) | Status | Bukti / tindakan |
| --- | --- | --- | --- |
| SRS-INT-01 | Semua panggilan masuk/keluar melalui satu API Gateway | Sebahagian + Perlu infrastruktur | Gateway logik dalam aplikasi: semua adapter luar membaca tetapan, had kadar dan log melalui `Adm\Contracts\Integrations` (`allow()`, `settings()`, `log()`). Ujian: `Adm/KesihatanIntegrasiTest::test_SRS_ADM_04_integration_settings_go_through_approval_and_are_readable_by_modules`. Lapisan fizikal perlukan AWS API Gateway atau ALB + WAF di hadapan `/api/v1/*`, serta egress melalui NAT/proksi keluar. |
| SRS-INT-02 | Kontrak OpenAPI 3.0 berversi, versi lama disokong | Sebahagian + Perlu proses/dokumen (ujian: lihat §Ujian baharu) | Prefiks `/api/v1` (`bootstrap/app.php`). `docs/api/pro-openapi.yaml` (OpenAPI 3.0.3, dihidangkan di `/api/v1/pro/openapi.yaml`). Ujian: `Pro/ProfileApiTest::test_SRS_PRO_29_expired_key_is_refused_and_openapi_document_is_served`. `dedoc/scramble` dipasang (`composer.json`) tetapi tidak dikonfigurasi dan tiada ujian; laluan lalai `/docs/api` hanya dibuka dalam `local`. API awam PWS (`/api/v1/pws/awam/*`) tiada spesifikasi statik. Polisi penamatan versi perlu didokumenkan. |
| SRS-INT-03 | OAuth 2.0 + JWT, tempoh luput boleh dikonfigurasi | Sebahagian + Perlu input MAIS | OAuth2 `client_credentials` → token Bearer legap (`Pws/Http/Controllers/Awam/TokenController.php`, TTL `PWS_TOKEN_MINUTES`=15). Kunci API PRO (Sanctum, tarikh luput per kunci). OIDC/JWT hanya untuk SSO (Keycloak). `config/sanctum.php` `expiration => null` (token pentadbir ADM-05 tidak luput secara lalai). MAIS perlu putuskan: terima token legap, atau JWT dikeluarkan oleh API Gateway/SSO MAIS. |
| SRS-INT-04 | Had kadar per kunci API & per titik akhir, boleh dikonfigurasi | Dipenuhi oleh kod | Had per kunci: `Pro/ProfileApiTest::test_SRS_PRO_29_scoped_api_keys_with_expiry_rate_limit_revocation_and_usage_log`. Per klien: `Pws/GatewayKeselamatanTest::test_SRS_PWS_24_per_client_rate_limit`. Per titik akhir: `throttle:*` dalam `app/Modules/*/routes.php` dan `RateLimiter::for('gis-tiles'/'gis-ogc')` (`GisServiceProvider.php:98`). Per integrasi: `kadar_capaian` (`Integrations::allow`). Nota: tiada had lalai global bagi semua laluan `api` yang disahkan. Cadangkan had berlapis di WAF. |
| SRS-INT-05 | Penggiliran kunci tanpa gangguan + senarai IP dibenarkan | Dipenuhi oleh kod | `Pws/GatewayKeselamatanTest::test_SRS_PWS_23_rotation_keeps_the_old_secret_for_a_grace_period_and_revocation_is_immediate`, `::test_SRS_PWS_23_ip_allowlist_applies_to_token_and_calls`, `Tkt/IntegrasiTest::test_SRS_TKT_14_api_requires_integration_account_and_respects_ip_allowlist`. Tempoh tangguh: `PWS_ROTATION_GRACE_HOURS`=24. Kunci API PRO hanya cipta/batal, tiada tempoh tangguh; penggiliran dibuat dengan mencipta kunci baharu sebelum membatalkan yang lama. |
| SRS-INT-06 | Log setiap panggilan API (status, masa, saiz, rujukan) | Dipenuhi oleh kod | `Adm/Models/LogIntegrasi`, `Pws/Services/LogIntegrasi`, `Cki/Models/LogIntegrasiCukai`, `Tkt/Models/LogIntegrasi`. Ujian: `Adm/KesihatanIntegrasiTest::test_SRS_ADM_04_test_connection_logs_the_exchange_and_permissions_apply`, `Cki/IntegrasiTest::test_SRS_CKI_10_every_call_is_logged_retried_masked_and_persistent_failure_escalated`, `Pws/GatewayKeselamatanTest::test_SRS_PWS_22_every_call_is_logged_with_a_masked_payload_summary`, `Adm/LogTest::test_SRS_ADM_14_application_and_integration_logs_export_by_period`. |
| SRS-INT-07 | Circuit breaker + cuba semula dengan backoff + eskalasi | Sebahagian | Cuba semula, backoff dan eskalasi dilaksana per adapter: `Cki/Services/Panggilan.php` (`cki.cuba_semula`, `cki.backoff_ms`, `cki.ambang_eskalasi`). Ujian: `Cki/IntegrasiTest::test_SRS_CKI_10_every_call_is_logged_retried_masked_and_persistent_failure_escalated`, `Ntf/DeliveryRetryTest::test_SRS_NTF_17_job_retries_at_least_three_times_with_increasing_backoff`, `Tkt/IntegrasiTest::test_SRS_TKT_16_failed_push_is_retried_and_listed_for_reconciliation`, `Adm/KesihatanIntegrasiTest::test_SRS_ADM_17_an_integration_switched_off_alerts_after_repeated_failures`. Tiada komponen circuit breaker generik (keadaan buka/separuh-buka) yang dikongsi semua adapter. Tambah satu apabila adapter Live dibina. |
| SRS-INT-08 | Rekonsiliasi kewangan harian + laporan sisihan | Dipenuhi oleh kod + Perlu input MAIS | Jadual `pjk:rekonsiliasi --sumber=semua` (06:00) dan `cki:rekonsiliasi` (06:30). Ujian: `Pjk/RekonsiliasiTest::test_SRS_PJK_18_daily_run_against_the_gateway_settlement_statement`, `::test_SRS_PJK_21_grp_and_esewa_receipts_reconciled_and_differences_reported`, `Cki/PemantauanTest::test_SRS_CKI_20_reconciliation_lists_the_three_deviation_cases`. Format sebenar fail penyelesaian gateway dan GRP diperlukan. |
| SRS-INT-09 | Data luaran = cadangan menunggu pengesahan | Dipenuhi oleh kod | `Hkm/ProposalAndReminderTest::test_SRS_HKM_18_external_proposal_lands_as_draft_and_never_writes_the_master`, `Per/PortalDanLaporanTest::test_SRS_PER_25_portal_submission_lands_as_pending_wakaf_case_without_touching_master`, `::test_SRS_PER_12_portal_adapter_pull_creates_pending_cases`, `Pws/PermohonanPortalTest::test_SRS_PWS_21_waqf_registration_lands_in_PER_as_a_pending_case_with_the_same_reference`. |
| SRS-INT-10 | Mod sandaran manual + segerak semula automatik | Dipenuhi oleh kod | `Cki/IntegrasiTest::test_SRS_CKI_08_outage_switches_to_fallback_notifies_officers_and_recovers_automatically`, `Cki/DaftarBilTest::test_SRS_CKI_24_agencies_without_api_service_are_handled_by_structured_manual_fallback`, `Pjk/GatewayTest::test_SRS_PJK_14_gateway_outage_falls_back_to_counter_payment`, `Tsu/TukarSyaratKosPtdTest::test_SRS_TSU_22_land_office_status_by_central_api_or_structured_manual_update_with_follow_up`. Simulasi kegagalan: `adu.mock_pusat_gagal`, `tkt.mock_pusat_gagal`, `kes.mock_mahkamah_gagal`. |
| SRS-INT-11 | Setiap integrasi dalam adapter tersendiri | Dipenuhi oleh kod (ujian: lihat §Ujian baharu) | Satu kontrak bagi setiap sistem luar: `Pjk\Contracts\{PaymentGateway,GrpMais,ESewa}`, `Cki\Contracts\{PenyediaCukaiBerpusat,GrpPembayaran}`, `Per\Contracts\{WakafPortal,EFaraid}`, `Tsu\Contracts\PejabatTanahGateway`, `Adu\Contracts\{CentralComplaintSystem,CaptchaVerifier}`, `Tkt\Contracts\CentralTicketSystem`, `Kes\Contracts\{CourtFilingSystem,AttorneyGeneralChambers}`, `Ntf\Contracts\{SmsGateway,WhatsAppGateway}`, `Kba\Contracts\{LlmGateway,EmbeddingProvider}`. Sempadan dikuatkuasa oleh `tests/Architecture/ModuleBoundaryTest`. |
| SRS-INT-12 | Papan pemuka kesihatan integrasi | Dipenuhi oleh kod | `Adm/Services/Kesihatan/PemantauKesihatan.php`. Ujian: `Adm/KesihatanIntegrasiTest::test_SRS_ADM_16_single_health_screen_shows_services_queue_integrations_storage_database_and_errors`, `::test_SRS_ADM_16_default_probes_cover_every_platform_service`, `Ntf/AdminApiTest::test_SRS_NTF_23_monitoring_dashboard_shows_queue_success_rate_and_recent_failures`. Probe `config/adm.php` (MinIO `/minio/health/live`, Mailpit `/livez`) perlu ditukar kepada titik akhir PROD. |
| SRS-INT-13 | Pengguna jana kunci API dengan skop, had, luput & batal serta-merta | Dipenuhi oleh kod | `Pro/ProfileApiTest::test_SRS_PRO_29_scoped_api_keys_with_expiry_rate_limit_revocation_and_usage_log`, `::test_SRS_PRO_29_api_key_cannot_exceed_its_owners_permissions`, `Adm/KeselamatanTest::test_SRS_ADM_05_tokens_can_be_listed_and_revoked`. |
| SRS-INT-14 | Dokumentasi API dijana automatik per kunci, boleh dikongsi | Sebahagian | Satu dokumen OpenAPI statik (`docs/api/pro-openapi.yaml`). Ia tidak dijana per kunci dan tiada pautan kongsi bertapis skop. Tindakan: jana dokumen yang ditapis mengikut skop kunci, atau aktifkan Scramble dengan gate. |
| SRS-INT-15 | Perkhidmatan peta OGC (WMS, WFS, WMTS) | Sebahagian + Perlu infrastruktur | `Gis/Services/OgcProxy.php` (WMS/WFS dengan skop disuntik sebagai CQL_FILTER). Ujian: `Gis/PerkhidmatanPetaTest::test_SRS_GIS_25_wms_getmap_proxied_with_scope_injected_as_cql_filter`, `::test_SRS_GIS_25_wfs_getfeature_access_audited_and_sulit_levels_follow_permission`, `::test_SRS_GIS_32_ogc_proxy_refuses_filter_injection_unpublished_layers_and_unauthorised_users`. **WMTS sengaja tidak ditawarkan** bagi lapisan berskop (cache dikongsi akan membocorkan skop); MVT berskop digunakan (`Gis/LayersTest::test_SRS_GIS_08_vector_tiles_are_served_via_st_asmvt_and_scoped`). Perlu persetujuan MAIS. Perlu pelayan GeoServer/SuperMap PROD. |
| SRS-INT-16 | Payment gateway (cukai, sewaan, portal) + resit + rekonsiliasi GRP | Sebahagian + Perlu input MAIS | Kod lengkap atas `MockPaymentGateway`: `Pjk/GatewayTest::test_SRS_PJK_14_acceptance_2_fpx_and_card_payments_credit_ledger_and_email_serial_receipts`, `Pws/BayaranPortalTest::test_SRS_PWS_12_success_only_after_server_side_confirmation_never_on_redirect`, `::test_SRS_PWS_16_no_card_or_bank_data_is_stored`, `Cki/BayaranTest::test_SRS_CKI_15_direct_api_payment_saves_receipt_to_dms_updates_ledger_and_creates_grp_reconciliation_record`. Perlu akaun pedagang dan API gateway sebenar (lihat §B). |
| SRS-INT-17 | Pelan Integrasi & Spesifikasi Integrasi | Perlu proses/dokumen | Belum ditulis. Asas: PLAN §11 dan senarai kontrak SRS-INT-11. |
| SRS-INT-18 | SOP pemantauan & penyelesaian masalah integrasi | Perlu proses/dokumen | Belum ditulis. Rujuk skrin `/admin/adm/kesihatan` dan log integrasi. |

### A.3 Keperluan bukan fungsian — SRS-NFR-01..26 (SRS §12)

| Kod | Ringkasan keperluan (pendek) | Status | Bukti / tindakan |
| --- | --- | --- | --- |
| SRS-NFR-01 | Ketersediaan 99.5%/bulan | Perlu infrastruktur | Multi-AZ (ALB, ≥2 instance API/UI, RDS Multi-AZ, ElastiCache Multi-AZ) dan pemantau uptime luaran pada `/up` (`bootstrap/app.php` `health: '/up'`). Laporan bulanan juga diperlukan. |
| SRS-NFR-02 | Skrin transaksi ≤3 s (p95) | Perlu infrastruktur + Perlu proses/dokumen | Ujian beban (k6/JMeter) pada UAT dan pemantauan pengguna sebenar (RUM). Lihat bahagian "Semakan prestasi ringkas". |
| SRS-NFR-03 | API ≤1.5 s (p95) | Perlu infrastruktur + Perlu proses/dokumen (ujian: lihat §Ujian baharu) | Metrik log API Gateway/ALB. Had saiz halaman melindungi masa respons. **Jurang:** `GET /api/v1/pro/arkib` (`Pro/Http/Controllers/ArkibApiController.php:33`) dan `GET /api/v1/pro/import` (`Pro/Http/Controllers/ImportController.php:83`) menerima `per_page` tanpa had. |
| SRS-NFR-04 | 200 pengguna serentak | Perlu infrastruktur + Perlu proses/dokumen | Ujian beban berperingkat. Saiz FPM, Horizon (`maxProcesses` produksi = 10, `config/horizon.php`) dan sambungan RDS (pertimbangkan RDS Proxy). |
| SRS-NFR-05 | Carian portfolio ≤5 s | Sebahagian + Perlu proses/dokumen | Indeks `pg_trgm`/GiST (`docs/data/model-fizikal.md`). Ujian prestasi dengan isi padu data sebenar diperlukan. |
| SRS-NFR-06 | Fail 25 MB dimuat naik + diindeks ≤30 s | Perlu infrastruktur | Had DMS sudah dalam kod: `Dms/UploadValidationTest::test_SRS_DMS_05_category_size_limit_with_clear_message_and_admin_change_without_release`. Konfigurasi PROD yang diperlukan:<br>• PHP `upload_max_filesize`/`post_max_size` ≥ 50 MB (fail spatial sehingga 250 MB, PLAN §15 G0);<br>• nginx `client_max_body_size`;<br>• ClamAV `StreamMaxLength`;<br>• ALB idle timeout.<br>OCR berlaku di belakang tabir (`DMS_OCR_MAX_PAGES`). |
| SRS-NFR-07 | Laporan piawai ≤10 s; laporan besar di latar belakang + notifikasi | Dipenuhi oleh kod + Perlu infrastruktur | `Lap/EksportJadualTest::test_SRS_LAP_07_large_export_runs_in_background_and_notifies_the_requester`. Had `lap.had_segerak`=5000 dan `lap.had_masa_saat`=30. Masa ≤10 s perlu diukur pada UAT. |
| SRS-NFR-08 | Peta satu daerah ≤5 s; jubin dicache | Sebahagian + Perlu infrastruktur | `Gis/LayersTest::test_SRS_GIS_08_tiles_are_cached_per_scope_signature` (`gis.tile_cache_saat`=300). Ujian prestasi GIS dan CDN untuk peta asas diperlukan. |
| SRS-NFR-09 | Dashboard ≤15 min ketinggalan + cap masa | Dipenuhi oleh kod + Perlu infrastruktur | `Dsh/KpiRegistryTest::test_SRS_DSH_13_results_are_cached_per_scope_signature_with_a_ttl_of_at_most_15_minutes`, `Dsh/SusunAturEksportTest::test_SRS_DSH_12_every_widget_and_the_dashboard_carry_a_data_timestamp`. Lag replika baca mesti dipantau (CloudWatch `ReplicaLag`). |
| SRS-NFR-10 | Prestasi kekal pada 3× isi padu data | Perlu proses/dokumen | Ujian beban dengan data sintetik berskala (penjana `Ref/Services/SampleLotGenerator`, arahan `Ref/Console/SampleLotsCommand`). |
| SRS-NFR-11 | Skala menegak & mendatar tanpa ubah kod | Sebahagian + Perlu infrastruktur | Komponen yang sudah tanpa keadaan: sesi `database`, cache/queue Redis, dokumen S3 (`dokumen`), dan tugasan berjadual `onOneServer()` (perlukan kunci Redis kongsi). **Sebelum >1 instance:**<br>• `LAP_DISK` (lalai `local`) perlu ditukar ke S3;<br>• fail `storage_path()` (`AUD_SIEM_LALUAN`, `ADM_SIEM_FAIL`, `aud.laluan_spool`, `aud.laluan_insiden`) perlu dihantar ke storan/log berpusat;<br>• `APP_MAINTENANCE_DRIVER=file` perlu ditukar ke `cache`. |
| SRS-NFR-12 | Beban bacaan berat ke replika baca | Dipenuhi oleh kod (ujian: lihat §Ujian baharu) + Perlu infrastruktur | `Lap/Services/Baca.php` (sambungan `lap_baca`, `LAP_DB_READ_*`) dan `Dsh/Services/Baca.php` (`dsh_baca`, `DSH_DB_READ_*`). Peranan baca sahaja: `infra/bin/sisdata-bi-setup.sh` (`sisdata_lap`, `sisdata_bi`). Perlu RDS read replica. |
| SRS-NFR-13 | Proses tak segerak melalui baris gilir | Dipenuhi oleh kod + Perlu infrastruktur | `Ntf/EngineTest::test_SRS_NTF_21_delivery_goes_through_a_persistent_queue`, `Lap/EksportJadualTest::test_SRS_LAP_07_large_export_runs_in_background_and_notifies_the_requester`, `Adm/TugasanTest::test_SRS_ADM_06_run_now_is_queued_and_recorded`. Perlu Horizon + Redis PROD. |
| SRS-NFR-14 | Patuh MyDS (grid, token, komponen) | Sebahagian + Perlu proses/dokumen | `@govtechmy/myds-style` dan Kitchen Sink `/admin/kitchen-sink`. Justifikasi bertulis bagi komponen tersuai (cth. editor aliran WFE, peta MapLibre) perlu diserahkan. |
| SRS-NFR-15 | WCAG 2.1 AA | Sebahagian + Perlu proses/dokumen | Tiada ujian axe automatik dalam `frontend/e2e/` (hanya `auth.spec.ts`, `settings.spec.ts`). Tema PWS ada asas WCAG (`pws/README.md`). Lihat §Ujian baharu (frontend) dan audit manual. |
| SRS-NFR-16 | Chrome, Edge, Firefox, Safari (terkini + 3 versi) | Perlu proses/dokumen | `frontend/playwright.config.ts` hanya `Desktop Chrome`. Tambah projek `firefox`, `webkit` dan `msedge`, serta matriks ujian manual. |
| SRS-NFR-17 | Responsif desktop/tablet/telefon | Sebahagian + Perlu proses/dokumen | DoD PLAN §13 (tiada skrol mendatar pada 375 px). DSH-20 diuji melalui pelayar sahaja. Tiada ujian automatik saiz paparan mudah alih. |
| SRS-NFR-18 | Modul lapangan luar talian + segerak automatik | Dipenuhi oleh kod | `frontend/lib/ptn-offline.ts`, `frontend/lib/ast-offline.ts` (IndexedDB tersulit). Ujian: `Ptn/LapanganTest::test_SRS_PTN_05_offline_visit_syncs_idempotently_without_losing_records`, `Ast/PemeriksaanTest::test_SRS_AST_20_offline_inspection_syncs_idempotently_without_losing_records`, `::test_SRS_AST_20_older_offline_inspection_does_not_overwrite_newer_condition`. |
| SRS-NFR-19 | Inbox tugasan per pengguna + baki SLA | Dipenuhi oleh kod | `Wfe/DecisionTest::test_SRS_WFE_14_task_board_shows_only_my_tasks_with_sla_remaining`, `Dsh/PortalInboxTest::test_SRS_DSH_23_inbox_shows_my_tasks_deadlines_sla_status_and_notifications`. |
| SRS-NFR-20 | Draf automatik & pemulihan selepas gangguan sesi | Sebahagian | Sama seperti SRS-GEN-07. |
| SRS-NFR-21 | Bantuan berkonteks, FAQ, panduan | Dipenuhi oleh kod + Perlu input MAIS | `Dsh/PortalInboxTest::test_SRS_DSH_25_online_help_has_guides_faq_and_manual_for_the_current_screen`, `Tkt/CadanganTest::test_SRS_TKT_06_kb_suggestions_shown_before_submit_with_at_least_three_articles`. Kandungan FAQ/manual daripada MAIS diperlukan. |
| SRS-NFR-22 | Struktur modular | Dipenuhi oleh kod | `app/Modules/*`, `config/modules.php`, `app/Support/Fallbacks`. Ujian: `tests/Architecture/ModuleBoundaryTest::test_module_only_uses_other_modules_through_contracts_or_events`, `::test_every_enabled_module_lives_under_app_modules`. |
| SRS-NFR-23 | Konfigurasi tanpa ubah kod sumber | Dipenuhi oleh kod | `Adm/ParameterTest::test_SRS_ADM_01_a_parameter_change_takes_effect_on_the_next_read_without_restart`, `Adm/KodRujukanTest::test_SRS_ADM_02_admin_adds_a_code_and_it_is_usable_immediately`, `Rbc/RoleManagementTest::test_SRS_RBC_06_admin_creates_and_edits_a_role_with_permissions_and_scopes`, `Adm/TemplatTest::test_SRS_ADM_03_admin_manages_report_template_content_fields_format_and_versions`, `Wfe/DefinitionTest::test_SRS_WFE_03_admin_adds_threshold_stage_without_code_and_running_cases_keep_old_version`. |
| SRS-NFR-24 | Secure coding + DevSecOps (SAST, SCA) setiap gabungan | Sebahagian + Perlu proses/dokumen | `.github/workflows/ci.yml`: Pint, Larastan, PHPUnit atas PostGIS, `composer audit`, `npm audit --audit-level=high`, gitleaks dan Semgrep. Ujian: `Adm/DevSecOpsTest::test_SRS_ADM_23_pipeline_runs_sast_sca_and_secret_scanning`, `::test_SRS_ADM_24_quality_gate_fails_the_build_on_high_or_critical_findings`. CI **belum pernah dijalankan di GitHub** (PLAN §15 F0) dan DAST belum ada. Dokumen piawai secure coding diperlukan. |
| SRS-NFR-25 | Tiada kunci vendor: eksport penuh, dokumentasi, pelan pemindahan | Sebahagian + Perlu proses/dokumen | Tumpuan sumber terbuka (Laravel, PostgreSQL, GeoServer, Metabase OSS, Ollama). DDL dan kamus data dijana: `Pro/GovernanceTest::test_SRS_PRO_04_executable_ddl_is_generated_from_the_live_schema`, `::test_SRS_PRO_05_data_dictionary_is_generated_from_schema_merged_with_business_metadata`. Eksport KB: `Kba/EksportSokonganTest::test_SRS_KBA_20_whole_knowledge_base_exports_to_markdown_and_html`. Pelan pemindahan perlu ditulis. |
| SRS-NFR-26 | Kod sumber & artifak milik MAIS, tiada kotak hitam | Perlu proses/dokumen | Serahan repo, skrip `infra/`, `pws/` dan dokumentasi. Semak lesen pihak ketiga (cth. ODbL bagi data OSM terbitan, `app/Modules/Ref/Database/data/MANIFEST.md`). |

### A.4 Keselamatan — SRS-SEC-01..35 (SRS §13)

| Kod | Ringkasan keperluan (pendek) | Status | Bukti / tindakan |
| --- | --- | --- | --- |
| SRS-SEC-01 | Patuh Dasar Keselamatan Siber & Polisi ICT MAIS + pemetaan kawalan | Perlu input MAIS + Perlu proses/dokumen | Dokumen dasar MAIS diperlukan untuk menulis matriks pemetaan kawalan. |
| SRS-SEC-02 | Patuh PDPA 2010 | Sebahagian + Perlu proses/dokumen | Kawalan dalam kod:<br>• penyamaran: `Per/PihakDanCapaianTest::test_SRS_PER_23_personal_data_masked_without_sulit_permission`, `Aud/AuditRecordingTest::test_SRS_AUD_25_passwords_tokens_and_ic_numbers_are_masked`, `Adm/LogTest::test_SRS_ADM_15_logs_never_store_passwords_tokens_keys_or_full_ic_numbers`;<br>• penyulitan identiti: `Adu/PrivasiPengaduTest::test_SRS_ADU_21_identity_is_encrypted_at_rest_and_every_access_is_logged_with_purpose`;<br>• persetujuan: `Tkt/IntakeTest::test_SRS_TKT_03_screenshot_requires_explicit_consent_and_goes_through_dms`.<br>Notis privasi portal, DPIA dan daftar pemprosesan masih perlu disediakan. |
| SRS-SEC-03 | Ambil kira Akta Jenayah Komputer 1997 | Perlu proses/dokumen | Bukti sokongan dalam kod: penafian capaian direkod (`Rbc/AccessControlTest::test_SRS_RBC_08_denied_requests_dispatch_access_denied`) dan jejak audit tidak boleh diubah (SEC-22). Notis amaran log masuk dan dasar penggunaan perlu disediakan. |
| SRS-SEC-04 | Selaras ISO/IEC 27001 | Perlu proses/dokumen | Pemetaan kawalan Annex A. |
| SRS-SEC-05 | OWASP Top 10 + Secure SDLC + SAST + SCR pihak ketiga | Sebahagian + Perlu proses/dokumen (ujian: lihat §Ujian baharu) | Kawalan sedia ada:<br>• CSRF melalui Sanctum `statefulApi()` (`bootstrap/app.php`; `config/sanctum.php` `validate_csrf_token`);<br>• suntikan formula CSV: `Lap/EksportJadualTest::test_SRS_LAP_04_csv_text_cells_are_protected_from_formula_injection`;<br>• open redirect: `Wfe/MfaStepUpTest::test_SRS_WFE_19_return_path_cannot_be_an_open_redirect`;<br>• suntikan penapis OGC: `Gis/PerkhidmatanPetaTest::test_SRS_GIS_32_ogc_proxy_refuses_filter_injection_unpublished_layers_and_unauthorised_users`;<br>• escape templat: `Adm/TemplatTest::test_SRS_ADM_03_preview_and_render_escape_dynamic_values`;<br>• Semgrep dalam CI.<br>**Jurang:** tiada middleware pengepala keselamatan untuk respons API atau Next.js. `X-Content-Type-Options`/`X-Frame-Options` hanya ada pada respons fail DMS (`Dms/Services/DocumentFiles.php:117`), CORS PWS dan portal WordPress. Tiada CSP/HSTS untuk UI utama (`frontend/next.config.ts` tiada `headers()`). SCR pihak ketiga perlu dilantik. |
| SRS-SEC-06 | NDA & Surat Akuan sebelum capaian | Perlu proses/dokumen | Pentadbiran kontrak. |
| SRS-SEC-07 | Log masuk SSO MAIS (OIDC); tiada pangkalan kata laluan dalaman | Dipenuhi oleh kod + Perlu input MAIS | `App\Http\Controllers\Auth\OidcController`. Ujian: `tests/Feature/Auth/OidcLoginTest::test_SRS_RBC_01_oidc_callback_logs_in_existing_user`, `::test_SRS_RBC_01_password_login_disabled_returns_403`, `Rbc/PasswordAndLockoutTest::test_SRS_RBC_11_sso_accounts_keep_no_password_and_cannot_use_password_login`, `Rbc/SsoMappingTest::test_SRS_RBC_11_sso_provisioned_user_has_no_password`. PROD: `AUTH_PASSWORD_LOGIN=false` dan klien OIDC SSO MAIS sebenar. |
| SRS-SEC-08 | MFA bagi tindakan kritikal | Sebahagian + Perlu input MAIS | Step-up OIDC (`Wfe/Services/StepUp.php`). Ujian: `Wfe/MfaStepUpTest::test_SRS_WFE_19_critical_stage_cannot_be_approved_without_mfa` (dan 8 ujian WFE_19 lain), `Tsu/PelupusanTest::test_SRS_TSU_05_disposal_approval_is_not_recorded_without_mfa_stage_and_written_justification`, `Hkm/WorkflowIntegrationTest::test_SRS_HKM_19_seeded_flow_has_two_mfa_stages_and_applies_only_after_both`, `Kes/AksesTest::test_SRS_KES_14_records_are_rahsia_and_need_mfa_through_sso`, `Pws/GatewayKeselamatanTest::test_SRS_PWS_23_admin_client_management_needs_permission_and_mfa`. **Jurang:**<br>• pengurusan kunci API PRO (`/api/v1/pro/kunci-api`, `Pro/routes.php:113-117`) tiada MFA;<br>• MFA hanya diuji dengan Keycloak tempatan (PLAN §15 G0).<br>SSO MAIS mesti menghantar `acr`/`amr` (`wfe.mfa_acr_diterima`). |
| SRS-SEC-09 | Polisi kata laluan akaun bukan-SSO | Dipenuhi oleh kod | `Rbc/Services/SecurityPolicy.php`. Ujian: `Rbc/PasswordAndLockoutTest::test_SRS_RBC_12_policy_accepts_12_chars_with_three_classes`, `::test_SRS_RBC_12_password_expires_after_90_days`, `::test_SRS_RBC_12_last_five_passwords_cannot_be_reused`, `::test_SRS_RBC_13_account_locks_after_five_consecutive_failures`, `::test_SRS_RBC_14_passwords_are_stored_as_salted_bcrypt_hashes`. Ujian kos bcrypt dicadangkan (§Ujian baharu). |
| SRS-SEC-10 | Kitar hayat akaun; nyahaktif = capaian tamat serta-merta | Dipenuhi oleh kod | `Rbc/AccountStatusTest::test_SRS_RBC_15_non_aktif_accounts_hold_no_permissions`, `::test_SRS_RBC_16_account_expires_on_its_access_end_date`, `::test_SRS_RBC_16_account_is_suspended_after_90_days_without_login`, `Rbc/SessionTest::test_SRS_RBC_17_suspending_a_user_ends_their_sessions_and_tokens_immediately`. |
| SRS-SEC-11 | Tamat sesi tidak aktif, had sesi serentak, tamat paksa | Dipenuhi oleh kod | `Rbc/SessionTest::test_SRS_RBC_18_session_ends_after_default_15_minutes_idle`, `::test_SRS_RBC_18_timeout_is_changed_by_admin_without_a_release`, `::test_SRS_RBC_19_concurrent_session_limit_ends_the_oldest_session`, `::test_SRS_RBC_19_admin_lists_and_force_terminates_a_session`, `::test_SRS_RBC_20_session_cookie_is_httponly_samesite_and_secure_over_https`, `::test_SRS_RBC_20_session_id_is_regenerated_on_login`. PROD: `SESSION_SECURE_COOKIE=true`. |
| SRS-SEC-12 | Kebenaran di peringkat model; tidak dipintas melalui API, eksport, BI, GIS | Dipenuhi oleh kod | `Rbc/AccessControlTest::test_SRS_RBC_07_officer_of_district_a_cannot_see_district_b`, `Lap/KeselamatanBarisTest::test_SRS_LAP_08_direct_query_on_view_enforces_the_session_district_scope`, `::test_SRS_LAP_08_bi_role_reads_only_aggregate_views`, `Lap/AiBiTest::test_SRS_LAP_01_metabase_embed_is_signed_with_the_users_district_locked`, `Gis/LayersTest::test_SRS_GIS_32_sulit_layer_refused_on_direct_request_and_attempt_audited`, `Pro/ProfileApiTest::test_SRS_PRO_29_api_key_cannot_exceed_its_owners_permissions`. |
| SRS-SEC-13 | Semakan capaian berkala (recertification) | Dipenuhi oleh kod + Perlu input MAIS | `Rbc/RecertificationTest::test_SRS_RBC_27_schedule_opens_cycles_per_division_and_closes_overdue_ones`, `::test_SRS_RBC_27_owner_confirms_or_withdraws_and_withdrawal_takes_effect`. Kitaran lalai `rbc.recert_kitaran_hari`=180; MAIS perlu tetapkan pemilik data setiap bahagian. |
| SRS-SEC-14 | TLS 1.3 pada setiap hop, termasuk dalaman | Perlu infrastruktur | Konfigurasi yang diperlukan:<br>• ALB/CloudFront dengan polisi TLS 1.3 dan ACM;<br>• RDS `rds.force_ssl=1` dan `DB_SSLMODE=verify-full` (kini lalai `prefer`, `config/database.php`);<br>• ElastiCache in-transit encryption (`REDIS_URL` `tls://`);<br>• S3 melalui HTTPS (`DOKUMEN_S3_ENDPOINT`);<br>• Keycloak/SSO, GeoServer, Metabase, Ollama dan SMTP (STARTTLS) melalui TLS;<br>• `KBA_OLLAMA_URL` hanya alamat peribadi.<br>(RBC-23 ditangguh ke infrastruktur, PLAN §15.) |
| SRS-SEC-15 | AES-256 at-rest (DB, dokumen, sandaran, log) | Sebahagian + Perlu infrastruktur | Dokumen disulit per fail AES-256-GCM (`Dms/Services/Crypto/EnvelopeEncryptor.php`). Ujian: `Dms/AntivirusTest::test_SRS_DMS_20_documents_are_encrypted_at_rest_with_per_file_keys_and_sha256`, `::test_SRS_DMS_20_tampering_and_wrong_keys_are_detected`. Infrastruktur: RDS/EBS/S3/AWS Backup/CloudWatch Logs dengan KMS CMK. |
| SRS-SEC-16 | Penyulitan peringkat lajur bagi medan sensitif | Sebahagian + Perlu input MAIS | Cast `encrypted` sedia ada:<br>• `Adu/Models/Pengadu` (nama, telefon, e-mel, alamat);<br>• `Kes/Models/Pihak`, `Peguam` dan `PanelPeguam`;<br>• `Per/Models/PihakBerkaitan` dan `Pemerolehan.butiran_peribadi`;<br>• `Tsu/Models/SebutHargaTawaran.harga_tawaran`;<br>• `Pws/Models/Permohonan.kandungan`.<br>Ujian: `Adu/PrivasiPengaduTest::test_SRS_ADU_21_identity_is_encrypted_at_rest_and_every_access_is_logged_with_purpose`, `Kes/DaftarKesTest::test_SRS_KES_03_parties_by_role_encrypted_and_masked`.<br>**Jurang:** nilai penilaian (`urusan_tanah.nilai_penilaian`, `Per/Models/Pemerolehan.nilai_pasaran`, `Ast/Models/AsetNilaiSejarah`) dan butiran kes mahkamah hanya dikawal capaian (Rahsia), tidak disulit lajur. MAIS perlu sahkan senarai medan. |
| SRS-SEC-17 | Kunci diurus oleh KMS, digilir, diaudit; bukan dalam kod/konfig | Sebahagian + Perlu infrastruktur | `APP_KEY` dan `DMS_ENCRYPTION_KEY` dibaca daripada env. Rahsia integrasi dalam stor tersulit ADM (`php artisan adm:secret`): `Adm/KesihatanIntegrasiTest::test_SRS_ADM_28_secrets_are_encrypted_at_rest_and_never_returned`, `::test_SRS_ADM_28_artisan_adm_secret_stores_an_encrypted_value`. `APP_PREVIOUS_KEYS` menyokong penggiliran. Perlu AWS KMS + Secrets Manager/SSM yang menyuntik env, jadual penggiliran dan CloudTrail. |
| SRS-SEC-18 | Tiada data sebenar di bukan-PROD tanpa kelulusan; masking | Sebahagian + Perlu proses/dokumen (ujian: lihat §Ujian baharu) | Data contoh sintetik (`Ref/Services/SyntheticPeople.php`: No. KP kod tempat lahir `00`). Ujian (belum bernama SRS): `Ref/SampleLotsTest::test_all_owner_data_is_synthetic_and_ic_numbers_cannot_be_real`. Prosedur masking untuk salinan UAT/latihan perlu disediakan. |
| SRS-SEC-19 | Had saiz, senarai putih jenis, imbasan antivirus; fail berisiko ditolak & direkod | Dipenuhi oleh kod + Perlu infrastruktur | `Dms/UploadValidationTest::test_SRS_DMS_03_default_whitelist_accepts_every_listed_type`, `::test_SRS_DMS_04_renamed_file_is_rejected_by_content_signature`, `::test_SRS_DMS_05_category_size_limit_with_clear_message_and_admin_change_without_release`, `::test_SRS_DMS_09_executables_scripts_macro_documents_and_extensionless_files_are_blocked`, `Dms/AntivirusTest::test_SRS_DMS_07_infected_file_is_not_stored_but_quarantined_encrypted_and_audited`, `::test_SRS_DMS_07_scanner_outage_fails_closed`, `::test_SRS_DMS_07_real_clamd_detects_eicar`. PROD: clamd + `freshclam`, `DMS_SCANNER=clamd`. |
| SRS-SEC-20 | Rekod log masuk berjaya/gagal + IP, peranti, lokasi | Dipenuhi oleh kod + Perlu infrastruktur | `Rbc/LoginMonitoringTest::test_SRS_RBC_21_successful_and_failed_logins_are_recorded_with_context`, `Aud/AuditRecordingTest::test_SRS_AUD_19_password_login_success_and_failure_are_recorded_with_ip_device_location`. **Lokasi masih mock:** `Rbc/Services/MockGeoIpLocator`, `Aud/Services/Geo/MockGeoIpResolver`. Perlu pangkalan GeoIP (cth. MaxMind GeoLite2, lesen) dan adapter Live. |
| SRS-SEC-21 | Amaran log masuk mencurigakan / capaian tidak sah | Dipenuhi oleh kod | `Rbc/LoginMonitoringTest::test_SRS_RBC_22_repeated_failures_raise_an_alert`, `::test_SRS_RBC_22_login_from_new_device_or_location_raises_alerts`, `::test_SRS_RBC_22_repeated_unauthorised_access_attempts_raise_an_alert`, `Rbc/DirectoryTest::test_SRS_RBC_22_suspicious_login_activity_notifies_system_administrators`. |
| SRS-SEC-22 | Audit append-only di peringkat DB + semakan integriti berkala | Dipenuhi oleh kod + Perlu infrastruktur | `Aud/AuditIntegrityTest::test_SRS_AUD_06_records_form_a_gapless_sha256_chain_over_content_and_field_changes`, `::test_SRS_AUD_08_app_database_account_cannot_update_delete_or_truncate_audit_records`, `::test_SRS_AUD_08_triggers_reject_changes_even_when_privileges_are_regranted`, `::test_SRS_AUD_07_direct_tampering_is_detected_and_alerts`, `::test_SRS_AUD_07_integrity_check_is_scheduled`. PROD: peranan DB berasingan `sisdata_owner`/`sisdata_app` (`app/Modules/Aud/Database/sql/peranan-pengeluaran.sql`) dan `AUD_DB_APP_ROLE=sisdata_app`. |
| SRS-SEC-23 | Audit boleh dicari & dieksport CSV/Excel/JSON/PDF + integriti | Dipenuhi oleh kod | `Aud/AuditSearchExportTest::test_SRS_AUD_12_export_in_all_four_formats_with_checksum_and_metadata`, `::test_SRS_AUD_13_export_history_lists_checksums`, `::test_SRS_AUD_10_combined_user_module_action_and_period_filters`. |
| SRS-SEC-24 | Integrasi WAF + IDS/IPS | Perlu infrastruktur | AWS WAF (OWASP managed rules, had kadar), GuardDuty dan Network Firewall/IPS. Dalam kod: IP pelanggan sebenar hanya daripada proksi dipercayai (`adm.proksi_dipercayai`): `Adm/KeselamatanTest::test_SRS_ADM_26_real_client_ip_comes_from_x_forwarded_for_only_behind_a_trusted_proxy`. Peristiwa keselamatan ke SIEM: `::test_SRS_ADM_27_security_events_are_shipped_to_the_siem_stream`. |
| SRS-SEC-25 | SOP Pengurusan Insiden Keselamatan | Perlu proses/dokumen | Rujuk log insiden AUD (`aud.laluan_insiden`) dan amaran ADM-17/RBC-22. |
| SRS-SEC-26 | Maklum segera pelanggaran/kebocoran | Perlu proses/dokumen | Klausa kontrak + SOP. |
| SRS-SEC-27 | Pulang data & padam selamat pada tamat kontrak | Perlu proses/dokumen | Eksport penuh: `pg_dump`, S3 dan arkib audit. Sijil pelupusan. |
| SRS-SEC-28 | Hanya kakitangan dinamakan & akaun individu | Perlu proses/dokumen | IAM Identity Center/SSO per individu, tiada akaun dikongsi. |
| SRS-SEC-29 | Least privilege; capaian luar premis melalui VPN | Perlu infrastruktur + Perlu proses/dokumen | AWS Client VPN/Site-to-Site atau SSM Session Manager; tiada SSH awam. RBAC aplikasi: `Rbc/AccessControlTest::test_SRS_RBC_09_new_role_starts_with_no_permissions`. |
| SRS-SEC-30 | Perubahan kakitangan dimaklum; capaian ditamatkan serta-merta | Perlu proses/dokumen | Dalam aplikasi: `Rbc/SessionTest::test_SRS_RBC_17_suspending_a_user_ends_their_sessions_and_tokens_immediately`. Proses offboarding diperlukan. |
| SRS-SEC-31 | Capaian kakitangan Syarikat direkod & boleh diaudit | Perlu infrastruktur + Perlu proses/dokumen | Lapisan aplikasi diaudit oleh MOD-AUD. Lapisan infra: CloudTrail, log sesi SSM dan log audit RDS (`pgaudit`). |
| SRS-SEC-32 | VAPT pihak ketiga (sebelum Go-Live + dalam jaminan) | Perlu proses/dokumen | Lantikan pihak ketiga; ADM-25 juga ditangguh (PLAN §15). |
| SRS-SEC-33 | Skop penilaian (WAS, DB, host/cloud, SCR, prestasi) | Perlu proses/dokumen | Termasuk portal WordPress `pws/` sebagai sasaran berasingan (PLAN §14 #7). |
| SRS-SEC-34 | SCR menyeluruh termasuk kod berbantu AI | Perlu proses/dokumen | Seluruh repo dibina dengan bantuan AI; skop SCR mesti merangkumi semua kod. |
| SRS-SEC-35 | Tiada penemuan kritikal/tinggi terbuka pada Go-Live | Perlu proses/dokumen | Get kualiti CI (`Adm/DevSecOpsTest::test_SRS_ADM_24_quality_gate_fails_the_build_on_high_or_critical_findings`) dan laporan VAPT selepas pembetulan. |

### A.5 Infrastruktur, sandaran & DR, operasi — SRS-INF-01..30 (SRS §14)

| Kod | Ringkasan keperluan (pendek) | Status | Bukti / tindakan |
| --- | --- | --- | --- |
| SRS-INF-01 | Cadangan infrastruktur terperinci + justifikasi sizing | Perlu proses/dokumen | Petunjuk saiz dalam §E. |
| SRS-INF-02 | Dihoskan dalam AWS sedia ada MAIS | Perlu infrastruktur + Perlu input MAIS | Akaun, VPC dan rantau ditentukan MAIS (cadangan `ap-southeast-5` Malaysia jika tersedia). |
| SRS-INF-03 | DEV, SIT, UAT, PROD, DR + latihan | Perlu infrastruktur | Pangkalan `_test` hanya untuk PHPUnit (`tests/TestCase.php`). Setiap persekitaran perlu `.env` tersendiri (§D). |
| SRS-INF-04 | Rajah Topologi Deployment | Perlu proses/dokumen | Perlu menunjukkan ALB/TLS, API, UI, Horizon, scheduler, RDS, Redis, S3, ClamAV, GeoServer, Keycloak/SSO, Ollama, Metabase dan WordPress. |
| SRS-INF-05 | Anggaran kos cloud bulanan | Perlu proses/dokumen | — |
| SRS-INF-06 | Tiada SPOF; komponen kritikal merentas ≥2 AZ | Perlu infrastruktur | ASG/ECS ≥2 tugas API dan UI, RDS Multi-AZ, ElastiCache Multi-AZ dan S3. ClamAV/GeoServer/Ollama juga perlu ≥2 atau mod gagal-tertutup didokumenkan (DMS gagal tertutup: `Dms/AntivirusTest::test_SRS_DMS_07_scanner_outage_fails_closed`). |
| SRS-INF-07 | Failover DB & aplikasi berdokumen dan diuji | Perlu infrastruktur + Perlu proses/dokumen | Failover RDS Multi-AZ dan prosedur failback. |
| SRS-INF-08 | Pengurusan kapasiti dengan amaran ambang | Sebahagian + Perlu infrastruktur | Dalam aplikasi: `adm.ambang_storan_peratus`=85, `adm.ambang_baris_gilir`=1000, `dms:semak-kuota` setiap jam. Ujian: `Adm/KesihatanIntegrasiTest::test_SRS_ADM_17_service_down_queue_and_storage_thresholds_alert_ict`. Infra: alarm CloudWatch (CPU, memori, storan RDS, IOPS, sambungan). |
| SRS-INF-09 | Skop sandaran: DB, spatial, S3, konfig, log & audit | Perlu infrastruktur | AWS Backup bagi RDS, S3 (versioning + CRR), direktori data GeoServer, konfigurasi IaC/SSM dan log/arkib audit (`aud:arkib`). |
| SRS-INF-10 | Penuh mingguan, tokokan harian, log transaksi 15 min | Perlu infrastruktur | Snapshot RDS harian (tokokan), salinan penuh mingguan dan arkib WAL PITR (≤5 min). |
| SRS-INF-11 | PITR bagi DB PROD | Perlu infrastruktur | RDS PITR (retensi 35 hari). |
| SRS-INF-12 | Retensi 30 hari / 12 minggu / 12 bulan / tahunan | Perlu infrastruktur + Perlu input MAIS | Kitaran hayat pelan AWS Backup dan Vault Lock. Arkib tahunan mengikut keperluan undang-undang MAIS. |
| SRS-INF-13 | Sandaran disulit (transit & rehat), lokasi berasingan | Perlu infrastruktur | KMS dan salinan rentas akaun/rantau. |
| SRS-INF-14 | Ujian pemulihan suku tahunan | Perlu proses/dokumen | — |
| SRS-INF-15 | RTO 4 jam | Perlu infrastruktur + Perlu proses/dokumen | IaC untuk bina semula di rantau DR dan runbook. |
| SRS-INF-16 | RPO 15 minit | Perlu infrastruktur | Replika rentas rantau RDS dan S3 CRR (RTC 15 min). |
| SRS-INF-17 | Replikasi berterusan ke DR | Perlu infrastruktur | Seperti di atas. Termasuk imej kontena/AMI dan rahsia (Secrets Manager replica). |
| SRS-INF-18 | Pelan Pemulihan Bencana lengkap | Perlu proses/dokumen | — |
| SRS-INF-19 | ≥1 ujian/simulasi DR sebelum penyerahan | Perlu proses/dokumen | — |
| SRS-INF-20 | RTO/RPO disahkan di Gate 1 | Perlu input MAIS | — |
| SRS-INF-21 | Pantau perkhidmatan, CPU/memori/storan/rangkaian, DB, integrasi, GIS, tugasan berjadual … | Sebahagian + Perlu infrastruktur | Skrin kesihatan ADM: `Adm/KesihatanIntegrasiTest::test_SRS_ADM_16_single_health_screen_shows_services_queue_integrations_storage_database_and_errors`. Sejarah tugasan: `Adm/TugasanTest::test_SRS_ADM_08_run_history_has_start_end_duration_status_records_and_error`. Horizon `/horizon`. Metrik CPU/memori/rangkaian dan query perlahan: CloudWatch dan RDS Performance Insights. |
| SRS-INF-22 | Amaran automatik ke ICT MAIS | Sebahagian + Perlu input MAIS | `Adm/KesihatanIntegrasiTest::test_SRS_ADM_17_service_down_queue_and_storage_thresholds_alert_ict`, `Adm/TugasanTest::test_SRS_ADM_10_final_failure_notifies_system_administrators`. Amaran dihantar melalui MOD-NTF (in-app/e-mel). Saluran yang dipersetujui (e-mel/SMS/SNS) perlu ditetapkan. |
| SRS-INF-23 | Papan pemuka pemantauan infra + prosedur eskalasi | Sebahagian + Perlu proses/dokumen | Skrin `/admin/adm/kesihatan` dan papan pemuka CloudWatch. Prosedur eskalasi bertulis diperlukan. |
| SRS-INF-24 | SOP Pemantauan + Laporan Kapasiti & Prestasi berkala | Perlu proses/dokumen | — |
| SRS-INF-25 | Log ralat berpusat boleh dicari + retensi | Dipenuhi oleh kod + Perlu infrastruktur | `Adm/LogTest::test_SRS_ADM_11_errors_are_logged_with_module_severity_code_user_ip_and_trace`, `::test_SRS_ADM_11_search_filters_by_period_module_severity_code_and_user`, `::test_SRS_ADM_14_retention_policy_prunes_old_rows_per_configured_period`, `::test_SRS_ADM_15_structured_json_log_file_is_redacted_and_carries_request_id`. Log fail JSON (`ADM_LOG_JSON`) perlu dihantar ke CloudWatch Logs/SIEM. |
| SRS-INF-26 | CI/CD berpagar (ujian, SAST, SCA, rahsia) | Sebahagian + Perlu infrastruktur | `.github/workflows/ci.yml` dan `Adm/DevSecOpsTest` (ADM-23/24). CI belum dijalankan di GitHub. Tiada CD/promosi antara persekitaran (perlu GitHub Environments + kelulusan, atau CodePipeline). |
| SRS-INF-27 | Dokumen CI/CD, Release Management, Rollback, Rajah Deployment | Perlu proses/dokumen | — |
| SRS-INF-28 | Nota keluaran setiap pelepasan | Perlu proses/dokumen | Versi dalam `tkt.versi_sistem` dan `NEXT_PUBLIC_APP_VERSION` perlu dikemas kini setiap pelepasan. |
| SRS-INF-29 | Pelan Rollback diuji sebelum Go-Live | Perlu proses/dokumen | Migrasi perlu ada `down()`. Jadual audit append-only tidak boleh di-rollback secara destruktif. |
| SRS-INF-30 | Dokumen pemindahan operasi ke ICT MAIS | Perlu proses/dokumen | — |

### A.6 Ringkasan kiraan status

Kiraan ialah bilangan baris yang mengandungi label tersebut. Satu baris boleh berbilang status, jadi jumlah melebihi 118.

| Status | GEN (9) | INT (18) | NFR (26) | SEC (35) | INF (30) | Jumlah |
| --- | --- | --- | --- | --- | --- | --- |
| Dipenuhi oleh kod | 6 | 9 | 9 | 11 | 1 | **36** |
| Sebahagian | 3 | 7 | 9 | 7 | 5 | **31** |
| Perlu infrastruktur | 0 | 2 | 11 | 9 | 16 | **38** |
| Perlu proses/dokumen | 1 | 3 | 12 | 18 | 14 | **48** |
| Perlu input MAIS | 1 | 3 | 1 | 5 | 4 | **14** |

Daripada 36 baris "Dipenuhi oleh kod", 5 bergantung pada ujian baharu (GEN-01, GEN-02, GEN-04, INT-11, NFR-12). Tiga baris "Sebahagian" turut dicadangkan ujian baharu: GEN-09, INT-02 dan SEC-18 (SEC-05 dan NFR-03 masih jurang kod).

### A.7 Jurang kod ditemui semasa semakan (perlu dibaiki sebelum produksi)

1. ✔ **DIBAIKI (25 Okt 2026)** — `App\Http\Middleware\SecurityHeaders` (API) + `headers()` dalam `frontend/next.config.ts` (UI): `nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`, `Permissions-Policy`, HSTS atas HTTPS. CSP penuh masih belum (perlu senarai sumber MapLibre/Metabase). Asal: **Pengepala keselamatan HTTP tiada** bagi `/api/*` (Laravel) dan UI Next.js (`frontend/next.config.ts` tiada `headers()`): HSTS, CSP, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`. Ia hanya ada pada respons fail DMS, CORS PWS dan WordPress. (SRS-SEC-05)
2. ✔ **DIBAIKI** — kedua-dua endpoint kini `min(200, …)` (ujian `test_SRS_NFR_05_list_endpoints_cap_the_page_size`). Asal: **`per_page` tanpa had:**
   - `Pro/Http/Controllers/ArkibApiController.php:33` (`GET /api/v1/pro/arkib`, boleh dicapai dengan kunci API);
   - `Pro/Http/Controllers/ImportController.php:83` (`GET /api/v1/pro/import`).

   Endpoint lain sudah mengehadkan nilai, cth. `min(100, …)` atau `max:200`. (SRS-NFR-03, SEC-05)
3. ✔ **DIBAIKI** — gate kini `$user->can('rbc.pentadbiran.akses')`. Asal: **Gate Horizon** guna medan lama `$user?->role === 'admin'` (`app/Providers/HorizonServiceProvider.php:30`), bukan kebenaran MOD-RBC.
4. **`AUD_GEOIP` diabaikan:** `AudServiceProvider.php:46` sentiasa mengikat `MockGeoIpResolver`, dan `RbcServiceProvider.php:91` sentiasa mengikat `MockGeoIpLocator`. (SRS-SEC-20)
5. **Tiada MFA pada pengurusan kunci API PRO** (`Pro/routes.php:113-117`); MFA hanya ada pada klien PWS. (SRS-SEC-08)
6. **Token tidak luput secara lalai:** `config/sanctum.php` `expiration => null`. Tetapkan nilai bagi token pentadbir ADM-05. (SRS-INT-03)
7. **Fail tempatan menghalang skala mendatar:** `LAP_DISK=local`, laluan SIEM/spool/insiden dalam `storage_path()`, dan `APP_MAINTENANCE_DRIVER=file`. (SRS-NFR-11)
8. **`.env.example` tidak sepadan dengan stack sebenar:**
   - `DB_CONNECTION=sqlite`;
   - tiada `DB_SSLMODE`, `SESSION_SECURE_COOKIE`, `DMS_ENCRYPTION_KEY`, `AUD_*`, `KBA_*`, `LAP_*`, `DSH_*` atau `METABASE_*`.

   Sediakan `.env.production.example` (lihat §D).
9. **Timeout Horizon terlalu pendek:** `timeout` 60 s (`config/horizon.php`) lebih pendek daripada `KBA_LLM_TIMEOUT`=90 s dan kerja OCR/eksport besar. Asingkan baris gilir atau naikkan timeout per kerja.
10. **Probe kesihatan `config/adm.php` masih untuk persekitaran tempatan:** MinIO `/minio/health/live`, Mailpit `/livez`, soket ClamAV laluan Homebrew.

---

## Ujian baharu dicadangkan

**Status:** #1 dilaksana (`tests/Architecture/RouteSecurityTest.php`), #4 (`tests/Feature/CrossCutting/TerjemahanTest.php`), #8 sebahagian, #9–#12 dilaksana dalam bentuk ringkas (`tests/Feature/CrossCutting/MerentasModulTest.php`) — lihat bahagian "Ujian SRS merentas yang ditambah". Selebihnya kekal cadangan.

Semua ujian di bawah murah untuk ditulis. Kebanyakannya membuktikan kod sedia ada. Ujian bertanda **(gagal sehingga dibaiki)** memerlukan pembetulan kod dahulu (§A.7).

| # | Nama ujian | Fail | Apa yang perlu ditegaskan |
| --- | --- | --- | --- |
| 1 | `test_SRS_GEN_01_every_module_api_route_requires_authentication_or_is_declared_public` | `tests/Architecture/RouteSecurityTest.php` (baharu, extends `Tests\TestCase`) | Lelar `Route::getRoutes()` bagi URI `api/v1/*`. Setiap laluan mesti mempunyai `auth:sanctum` atau middleware klien PWS (`AuthenticateKlien`), kecuali laluan dalam senarai putih eksplisit: login/register/settings, `pws/awam/token`, `pjk/gateway/panggilan-balik`, `adu/awam/*`, `ast/awam/qr/*`, `pro/openapi.yaml`. Setiap laluan bertulis (POST/PUT/PATCH/DELETE) yang disahkan juga mesti ada middleware `can:` atau `*.boleh:`, atau tercatat dalam senarai pengecualian yang dikaji. |
| 2 | `test_SRS_GEN_02_business_module_writes_are_audited_without_module_code` | `tests/Modules/Aud/AuditRecordingTest.php` | Cipta, kemas kini dan padam satu model modul perniagaan yang tiada kod audit, cth. `App\Modules\Ast\Models\AsetKategori`. Tegaskan 3 baris `audit_rekod` (modul `ast`, tindakan cipta/kemas_kini/hapus). Tegaskan juga `config('aud.jadual_dikecualikan')` hanya mengandungi jadual rangka kerja/audit, bukan jadual modul. |
| 3 | `test_SRS_GEN_04_modules_store_files_only_through_the_dms_api` | `tests/Architecture/ModuleBoundaryTest.php` | Imbas statik `app/Modules/**.php` selain `Dms/`. Tiada `Storage::disk(`, `Storage::put`, `->store(`/`->storeAs(` pada `UploadedFile` atau `move_uploaded_file`. Senarai putih: `Lap/Services/Penjana.php` dan `Lap/Http/Controllers/LogController.php` (eksport jana, dikecualikan dengan justifikasi). |
| 4 | `test_SRS_GEN_09_every_module_message_has_a_malay_translation` | `tests/Feature/LocalizationTest.php` | Bagi setiap `app/Modules/*/lang/en/*.php`, kunci yang sama wujud dalam `lang/ms/*.php` dengan nilai bukan kosong. `config('app.locale') === 'ms'`. Boleh juga namakan semula 4 ujian sedia ada dengan prefiks `test_SRS_GEN_09_` supaya RTM mengiranya. |
| 5 | `test_SRS_INT_02_openapi_contract_is_versioned_openapi_3_and_matches_routes` | `tests/Modules/Pro/ProfileApiTest.php` | Parse `docs/api/pro-openapi.yaml` dengan `Symfony\Component\Yaml\Yaml` (ada dalam `vendor/symfony/yaml`). Tegaskan `openapi` bermula `3.0`, `info.version` semver, dan URL `servers` mengandungi `/api/v1`. Setiap `paths` wujud dalam senarai laluan `api/v1/pro/*`. |
| 6 | `test_SRS_INT_11_every_external_system_is_reached_through_a_bound_contract` | `tests/Architecture/IntegrationAdapterTest.php` (baharu, extends `Tests\TestCase`) | Bagi setiap kontrak dalam §B, `app()->bound()` benar dan `app(X)` ialah `instanceof X`. Imbasan statik: `Http::` hanya digunakan dalam fail adapter yang disenaraiputih, iaitu `*/Integration/*`, `*/Integrasi/*`, `Kba/Ai/*`, `Gis/Services/OgcProxy.php`, `Gis/Services/Geocoding/*`, `Lap/Services/Metabase.php`, `Adm/Services/Kesihatan/*`, `Ref/Services/OverpassClient.php`, `Dsh/Insight/*` dan `Gis/Console/GeoServerSetupCommand.php`. |
| 7 | `test_SRS_NFR_12_reporting_and_dashboard_use_the_read_replica_when_configured` | `tests/Modules/Lap/BacaReplikaTest.php` (baharu) | `config(['lap.baca.host' => 'replika.dalaman', 'lap.baca.username' => 'sisdata_lap'])`, panggil `App\Modules\Lap\Services\Baca::configure()`. Tegaskan `Baca::name() === 'lap_baca'`, `Baca::replica()` benar, dan `config('database.connections.lap_baca.host') === 'replika.dalaman'`. Ulang bagi `App\Modules\Dsh\Services\Baca` (`dsh_baca`, `dsh.baca.host`). Tanpa host, `name()` ialah `null`. |
| 8 | `test_SRS_NFR_03_list_endpoints_cap_page_size` **(gagal sehingga dibaiki)** | `tests/Feature/Api/PaginationLimitTest.php` (baharu) | Data provider bagi `/api/v1/pro/lot`, `/pro/arkib`, `/pro/import`, `/wfe/tugasan`, `/cki/bil`, `/ntf/inbox`, `/adu/aduan`, `/hkm/hak-milik`, `/ptn/jadual` dengan `per_page=100000` dan `per_halaman=100000`. Respons mesti 422 atau `meta.per_page ≤ 200`. Pentadbir perlu ada semua kebenaran. |
| 9 | `test_SRS_SEC_05_spa_requests_are_csrf_protected` | `tests/Feature/Api/SecurityBaselineTest.php` (baharu) | Kumpulan middleware `api` mengandungi `Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful`. `config('sanctum.middleware.validate_csrf_token') === ValidateCsrfToken::class`. Untuk 419 sebenar, ikat subkelas `ValidateCsrfToken` yang mengembalikan `runningUnitTests() = false`, kemudian `POST /api/v1/login` dengan `Referer: http://localhost:3000` tanpa `X-XSRF-TOKEN` → 419. |
| 10 | `test_SRS_SEC_05_api_responses_carry_security_headers` **(gagal sehingga dibaiki)** | `tests/Feature/Api/SecurityBaselineTest.php` | `GET /api/v1/settings` mesti ada `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: no-referrer` dan `Strict-Transport-Security` (apabila `app.url` https). Respons yang disahkan juga mesti ada `Cache-Control: no-store`. Memerlukan middleware baharu. |
| 11 | `test_SRS_SEC_09_password_hashing_uses_bcrypt_with_at_least_12_rounds` | `tests/Modules/Rbc/PasswordAndLockoutTest.php` | `config('hashing.driver')` ialah `bcrypt` atau `argon2id`. Bagi bcrypt, `Hash::info(Hash::make('x'))['options']['cost'] >= 12`. Melengkapkan `test_SRS_RBC_14_passwords_are_stored_as_salted_bcrypt_hashes`. |
| 12 | `test_SRS_SEC_11_session_cookie_is_secure_httponly_and_samesite_in_production_config` | `tests/Modules/Rbc/SessionTest.php` | Dengan `config(['session.secure' => true, 'app.url' => 'https://sisdata.test'])`, log masuk → kuki sesi `isSecure()`, `isHttpOnly()` dan SameSite `lax`/`strict`. Juga `config('session.http_only') === true`. Mengukuhkan `test_SRS_RBC_20_session_cookie_is_httponly_samesite_and_secure_over_https` bagi konfigurasi PROD eksplisit. |
| 13 | `test_SRS_SEC_18_sample_owner_data_is_synthetic_and_ic_numbers_cannot_be_real` | `tests/Modules/Ref/SampleLotsTest.php` | Namakan semula `test_all_owner_data_is_synthetic_and_ic_numbers_cannot_be_real`, atau tambah ujian SRS yang memanggil tegasan yang sama: semua baris `sintetik = true` dan No. KP berkod tempat lahir `00`. |
| 14 | `test_SRS_SEC_20_geoip_driver_follows_configuration` **(gagal sehingga dibaiki)** | `tests/Modules/Aud/AuditRecordingTest.php` | Dengan `config(['aud.geoip' => 'mock'])`, `app(GeoIpResolver::class)` ialah `MockGeoIpResolver`. Selepas adapter Live wujud, pemacu lain memulangkan kelas Live. Kini pembekal mengabaikan konfigurasi. |

**Ujian frontend (Playwright, di luar RTM PHP):**
- `frontend/e2e/a11y.spec.ts` "SRS-NFR-15 halaman utama lulus axe WCAG 2.1 AA": tambah `@axe-core/playwright`, imbas `/login`, `/admin`, satu senarai dan satu borang. Tiada pelanggaran `serious`/`critical`.
- "SRS-NFR-17 tiada skrol mendatar pada 375 px": `page.setViewportSize({width: 375, height: 812})`, kemudian `document.documentElement.scrollWidth <= 375`.
- Tambah projek `firefox` dan `webkit` dalam `frontend/playwright.config.ts` (SRS-NFR-16).

---

## B. Penyesuai mock untuk diganti dengan integrasi sebenar

**Penting:** kebanyakan mock diikat dengan `bindIf`/`singletonIf` dalam pembekal modul. **Tiada kunci env untuk menukarnya.** Integrasi sebenar memerlukan:
- kelas `Live…` yang melaksanakan kontrak yang sama;
- ikatan dalam pembekal yang didaftarkan **sebelum** pembekal modul (cth. `App\Providers\AppServiceProvider::register()`), atau satu suis config/env baharu.

Endpoint, timeout dan rujukan rahsia setiap integrasi diurus melalui skrin integrasi ADM (`Adm\Contracts\Integrations::settings()`, kelulusan ADM-04). Rahsia disimpan dengan `php artisan adm:secret <kunci>`.

| # | Sistem luar | Kontrak → mock semasa | Suis / config | Keperluan daripada MAIS/agensi untuk integrasi sebenar |
| --- | --- | --- | --- | --- |
| 1 | Payment gateway FPX / kad (PJK, PWS, CKI) | `Pjk\Contracts\PaymentGateway` → `Pjk\Integration\MockPaymentGateway` (`PjkServiceProvider.php:100`) | Tiada (ikatan kod). Rahsia webhook `pjk.gateway_rahsia` (`adm:secret`). Simulasi portal `PWS_GATEWAY_TEST` (lalai `false` apabila `APP_ENV=production`). Kod integrasi `pjk_gateway`. Parameter `pjk.gateway_minit_semak`/`pjk.gateway_minit_tamat_masa`. | Akaun pedagang FPX (PayNet melalui bank/agregator) dan kad. Spesifikasi API, kunci/sijil tandatangan, URL panggilan balik yang disenaraiputih, format fail penyelesaian harian untuk rekonsiliasi (PJK-18) dan persekitaran sandbox. |
| 2 | GRP MAIS (PJK) | `Pjk\Contracts\GrpMais` → `Pjk\Integration\MockGrpMais` | Tiada. Kod `pjk_grp`. | API/format fail GRP (resit, invois, vot), kaedah pengesahan dan jadual pertukaran. |
| 3 | e-Sewa (PJK) | `Pjk\Contracts\ESewa` → `Pjk\Integration\MockESewa` | Tiada. Kod `pjk_esewa`. | API e-Sewa dan pemetaan kontrak/penyewa. |
| 4 | GRP MAIS — bayaran cukai (CKI) | `Cki\Contracts\GrpPembayaran` → `Cki\Integration\MockGrpPembayaran` (`CkiServiceProvider.php:77`) | Tiada. Kod `cki_grp`. Parameter `cki.kod_vot_grp`. | Kod vot sebenar, aliran permohonan bayaran EFT/draf bank dan status balik. |
| 5 | Penyedia cukai berpusat MBI / PBT / PTD (CKI) | `Cki\Contracts\PenyediaCukaiBerpusat` → `Cki\Integration\MockPenyediaMbi` (`CkiServiceProvider.php:76`) | Tiada. Kod `cki_mbi`. Parameter `cki.cuba_semula`, `cki.backoff_ms`, `cki.ambang_eskalasi`. | Akses API, senarai agensi yang diliputi dan format bil/resit. |
| 6 | Pejabat Tanah / PTG (TSU) | `Tsu\Contracts\PejabatTanahGateway` → `Tsu\Integrasi\PejabatTanahMock` (`TsuServiceProvider.php:85`) | Tiada. Kod `tsu_pejabat_tanah`. Parameter `tsu.daerah_liputan_api` (kini `1001,1002,1008`, andaian) dan `tsu.mock_ptd_hari_per_status`. | API status permohonan berpusat, daerah yang diliputi dan kod status rasmi. |
| 7 | Portal Wakaf (PER) | `Per\Contracts\WakafPortal` → `Per\Integration\MockWakafPortal`, dihias oleh `Pws\Integrasi\PwsWakafPortal` (`PwsServiceProvider.php:62`) | Tiada. Kod `portal_wakaf`. Jadual `per:portal-wakaf` setiap 15 minit. | Jika portal wakaf luaran sedia ada wujud: API/format; jika tidak, portal PWS sendiri menjadi sumber. |
| 8 | e-Faraid (PER/HKM) | `Per\Contracts\EFaraid` → `Per\Integration\MockEFaraid` | Tiada. Kod `e_faraid`. | Akses API JKSM/e-Faraid dan dasar pengesahan waris. |
| 9 | Sistem Aduan berpusat (ADU) | `Adu\Contracts\CentralComplaintSystem` → `Adu\Integrasi\MockSistemAduanPusat` (`AduServiceProvider.php:69`) | Tiada. Kod `adu.sistem_aduan_pusat`. Simulasi gagal `adu.mock_pusat_gagal`. | API sistem aduan MAIS dan pemetaan kategori/status. |
| 10 | Sistem tiket/aduan berpusat (TKT) | `Tkt\Contracts\CentralTicketSystem` → `Tkt\Integrasi\MockSistemAduanPusat` (`TktServiceProvider.php:64`) | Tiada. Kod `tkt.sistem_aduan_pusat`. Parameter `tkt.integrasi_ip_dibenarkan` dan `tkt.mock_pusat_gagal`. Pemetaan `Tkt/Support/Lalai::PEMETAAN`. | API dan senarai IP sistem berpusat. |
| 11 | Sistem mahkamah (e-Filing) & Jabatan Peguam Negara (KES) | `Kes\Contracts\CourtFilingSystem` → `MockSistemMahkamah`; `Kes\Contracts\AttorneyGeneralChambers` → `MockPeguamNegara` (`KesServiceProvider.php:89-90`) | Tiada. Parameter `kes.mock_mahkamah_gagal`. | Sama ada akses API wujud. Jika tidak, mod manual berstruktur dikekalkan. |
| 12 | CAPTCHA portal awam (ADU, PWS) | `Adu\Contracts\CaptchaVerifier` → `Adu\Integrasi\MockCaptcha` (`AduServiceProvider.php:70`) | Tiada. Token ujian `adu.captcha_token_ujian`=`lulus-captcha`. **Mesti diganti sebelum PROD.** | Pilihan penyedia (reCAPTCHA/hCaptcha/Turnstile) serta kunci laman dan rahsia. PWS juga belum ada CAPTCHA sebenar (PLAN §15 G5). |
| 13 | SMS & WhatsApp (NTF) | `Ntf\Contracts\SmsGateway` → `Ntf\Services\Channels\LogSmsGateway`; `Ntf\Contracts\WhatsAppGateway` → `LogWhatsAppGateway` (`NtfServiceProvider.php:63-64`, hanya menulis log) | Tiada. | Penyedia SMS yang diluluskan MAIS (agregator), ID penghantar, akaun WhatsApp Business API dan templat yang diluluskan. |
| 14 | E-mel | Mailpit tempatan | `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | Gateway e-mel MAIS (SMTP + TLS), alamat penghantar rasmi, SPF/DKIM. Buang probe `mailpit` dalam `config/adm.php`. |
| 15 | SIEM (jejak audit) | `Aud\Services\Siem\SiemSink` → `JsonLinesSiemSink` (lalai), `CefSiemSink` atau `NullSiemSink` (`AudServiceProvider.php:48-52`) | `AUD_SIEM` (`jsonl`\|`cef`\|lain = null), `AUD_SIEM_SYSLOG`, `AUD_SIEM_LALUAN`, `AUD_SIEM_LALUAN_CEF` | Hos/port syslog SIEM (TLS), format CEF dan ruang storan. Dev kini `AUD_SIEM=null` kerana fail JSONL membesar (710 MB, PLAN §15). |
| 16 | SIEM (peristiwa keselamatan ADM) | `Adm/Services/Siem/PenghantarFail` / `PenghantarSyslog` | Parameter `adm.siem_pemacu` (`fail`\|`syslog`\|`tiada`), `adm.siem_hos`; env `ADM_SIEM_FAIL` | Seperti #15. |
| 17 | GeoIP (lokasi log masuk) | `Rbc\Contracts\GeoIpLocator` → `Rbc\Services\MockGeoIpLocator` (`RbcServiceProvider.php:91`); `Aud\Services\Geo\GeoIpResolver` → `MockGeoIpResolver` (`AudServiceProvider.php:46`) | `AUD_GEOIP` wujud dalam `config/aud.php` tetapi **diabaikan** | Lesen pangkalan GeoIP (MaxMind GeoLite2/GeoIP2) dan adapter Live. |
| 18 | Antivirus | `Dms\Services\Scanning\VirusScanner` → `ClamdScanner` (lalai) atau `FakeVirusScanner` | `DMS_SCANNER` (`clamd`\|`fake`), `CLAMAV_SOCKET`, `CLAMAV_TIMEOUT` | clamd PROD (Linux: cth. `/var/run/clamav/clamd.ctl` atau TCP) dan `freshclam` berjadual. Pastikan `DMS_SCANNER` ≠ `fake`. |
| 19 | AI tempatan — KBA (RAG) | `Kba\Contracts\EmbeddingProvider` → `OllamaEmbeddingProvider` / `HashEmbeddingProvider`; `Kba\Contracts\LlmGateway` → `OllamaLlmGateway` / `TiadaLlm` | `KBA_EMBEDDING_PEMACU` (`ollama`\|`cincangan`), `KBA_LLM_PEMACU` (`ollama`\|`tiada`), `KBA_OLLAMA_URL`, `KBA_EMBEDDING_MODEL` (`nomic-embed-text`), `KBA_LLM_MODEL` (`qwen2.5:1.5b`), `KBA_*_TIMEOUT`, `KBA_LLM_KONTEKS` | Instance Ollama dalam VPC peribadi; hanya alamat loopback/peribadi diterima (`Kba/AiTempatanTest::test_SRS_KBA_15_only_loopback_or_private_ai_endpoints_are_accepted`). Kelulusan model yang digunakan. |
| 20 | AI tempatan — DSH insight | `Dsh\Contracts\InsightGenerator` → `Dsh\Insight\PeraturanInsight` (lalai) / `OllamaInsight` | `DSH_AI_PEMACU` (`peraturan`\|`ollama`), `DSH_AI_OLLAMA_URL`, `DSH_AI_MODEL` (`llama3.2`), `DSH_AI_TIMEOUT` | Seperti #19. |
| 21 | Geocoder | `Gis\Contracts\Geocoder` → `CompositeGeocoder` (`LocalGeocoder` + `NominatimGeocoder`) | Parameter `gis.geocoder` (`tempatan`\|`nominatim`), `gis.nominatim_url` | Pelayan Nominatim peribadi atau perkhidmatan geocoder MyGDI (jika dibenarkan). |
| 22 | Peta asas satelit | — | Parameter `gis.peta_asas_satelit_url`, `gis.peta_asas_satelit_atribusi` (kosong) | Lesen imej satelit/ortofoto (JUPEM/MyGDI). |
| 23 | GeoServer → SuperMap; lapisan JUPEM/JPBD/MyGDI/MyLand | `Gis/Services/OgcProxy.php` (proksi OGC) + GeoServer tempatan | `GEOSERVER_URL`, `GEOSERVER_ADMIN_USER`, `GEOSERVER_ADMIN_PASSWORD` (lalai `geoserver`!), `GIS_GEOSERVER_WORKSPACE`, `GIS_GEOSERVER_PROXY_USER`, `GIS_GEOSERVER_PROXY_PASSWORD`, `GIS_GEOSERVER_TIMEOUT` | Keputusan SuperMap iServer (lesen) atau GeoServer. Data lapisan rasmi (kini fail contoh sahaja, PLAN §11). |
| 24 | SSO MAIS (gantian Keycloak tempatan) | `App\Http\Controllers\Auth\OidcController` + `socialiteproviders/keycloak`; realm contoh `infra/keycloak/realm-mais.json` | `KEYCLOAK_BASE_URL`, `KEYCLOAK_REALM`, `KEYCLOAK_CLIENT_ID`, `KEYCLOAK_CLIENT_SECRET`, `KEYCLOAK_REDIRECT_URI`, `KEYCLOAK_POST_LOGOUT_REDIRECT_URI`, `KEYCLOAK_AFTER_LOGIN_REDIRECT_URI`, `AUTH_PASSWORD_LOGIN` | Pendaftaran klien OIDC. Tuntutan peranan/bahagian/unit/jawatan untuk peraturan pemetaan RBC-02. `acr`/`amr` untuk MFA (`wfe.mfa_acr_diterima`, `wfe.mfa_acr_values`). URL end-session. Ujian MFA step-up dengan SSO sebenar. |
| 25 | Metabase (BI) | `Lap/Services/Metabase.php` (embed JWT berkunci daerah) | `METABASE_SITE_URL`, `METABASE_EMBED_SECRET`, `METABASE_EMBED_MINUTES` | Pelayan Metabase OSS + DB aplikasi `metabase_app`, peranan `sisdata_bi` (`infra/bin/sisdata-bi-setup.sh`). SSO/sandbox Metabase berbayar (PLAN §15 G5). |
| 26 | Data rujukan rasmi (Ref) | `Ref/Database/data/*.json`, `sempadan_mukim_anggaran.geojson` | `php artisan` (Ref `SeedRefCommand`) | Sempadan mukim rasmi JUPEM/PTG dan pengesahan 5 kod mukim (§C). |
| 27 | Laporan penilaian JPPH (TSU/AST) | Tiada adapter: muat naik manual (`tsu.rahsia.kemas_kini`) | — | Jika API JPPH dikehendaki, kontrak dan adapter baharu diperlukan. |

Nota: `app/Support/Fallbacks/*` (cth. `FallbackNotifier`, `FallbackAuditLogger`) **bukan** mock sistem luar. Ia pengganti apabila modul dalaman dinyahaktif dalam `config/modules.php`. PROD mesti mengaktifkan semua 22 modul + Ref + Platform.

---

## C. Angka & dasar andaian (perlu pengesahan MAIS)

Nilai dalam lajur "Nilai semasa" dalam jadual di bawah ialah andaian pembangun. Kebanyakannya boleh diubah oleh pentadbir tanpa pelepasan: parameter `Parameters->declare(...)` diubah di `/admin/adm/parameter`, dan parameter bertanda *kritikal* perlu kelulusan pentadbir kedua (ADM-18).

| Perkara | Nilai semasa (andaian) | Di mana | Rujukan SRS/PLAN |
| --- | --- | --- | --- |
| SLA tiket S1–S4 (minit masa perkhidmatan) | S1: respons 30, selesai 240, 24x7, eskalasi L2 60 / L3 240<br>S2: 120 / 1080, waktu pejabat<br>S3: 240 / 2700<br>S4: 540 / 10800<br>Amaran pada 75–80% | `app/Modules/Tkt/Support/Lalai.php::sla()` (disemai; diubah melalui UI TKT-08). Matriks keterukan `tkt.matriks_keterukan`. | PLAN §14 #9; SRS §18.2 tiada angka |
| Tutup automatik tiket | Buka semula 10 hari; peringatan 3 hari; tutup automatik 5 hari; 2 hari selepas disahkan | `tkt.hari_buka_semula`, `tkt.hari_peringatan_pengesahan`, `tkt.hari_tutup_automatik`, `tkt.hari_tutup_selepas_disahkan` (`TktServiceProvider.php:109-112`) | SRS-TKT-12 |
| Penalti lewat cukai (CKI) | 10.00% (kritikal); minimum RM10.00 | `cki.denda_peratus`, `cki.denda_minimum` (`CkiServiceProvider.php:124-125`) | PLAN §15 G3: "jadual penalti sebenar perlu input MAIS" |
| Had kelulusan bayaran cukai | ≤RM5,000 kewangan; ≤RM50,000 ketua bahagian; lebih → pengurusan atasan | `cki.had_kelulusan` (lalai `Cki/Support/Aliran::HAD_LALAI`) | SRS-CKI-14 |
| Unjuran obligasi cukai | +3.00% setahun | `cki.kadar_anggaran_peratus` | SRS-CKI-22 |
| Kod vot GRP | `B29000-CUKAI` (CKI), `B-PERUNDANGAN` (KES) | `cki.kod_vot_grp`, `kes.kod_vot_grp` | — |
| Penalti lewat sewa (PJK) | 1.00% sebulan (kritikal) selepas tangguh 30 hari | `pjk.penalti_peratus_bulanan`, `pjk.penalti_hari_tangguh` (`PjkServiceProvider.php:160-161`) | — |
| Tempoh bayar sewa / ambang tunggakan | 14 hari; notifikasi tunggakan 30/60/90 hari; hampir tamat kontrak 90 hari | `pjk.hari_tempoh_bayar`, `pjk.ambang_tunggakan_hari`, `pjk.hari_hampir_tamat` | — |
| Keutamaan agihan bayaran separa | Susunan kod caj `penalti` → `sewa` → `lain` → `deposit`, tertua dahulu | `pjk.keutamaan_agihan` (`BayaranService::KEUTAMAAN_LALAI`) | SRS-PJK-20 |
| **Formula agihan manfaat** | `amaun_agihan = hasil_kutipan × kadar_agihan%`; setiap penerima dapat `peratus` daripada amaun; baki pembundaran kepada penerima terakhir; deposit dikecualikan | `app/Modules/Pjk/Services/AgihanService.php` (docblock baris 29-31); kadar per kategori × status perolehan diluluskan melalui WFE `pjk.dasar_agihan` | PLAN §14 #10 — **dasar MAIS diperlukan** |
| Sasaran hasil sewa / pulangan | 5.00% nilai pasaran | `pjk.sasaran_hasil_peratus`, `lap.kadar_pulangan_sewa_peratus` | — |
| **Istibdal (tukaran tanah wakaf)** | Kaedah pelupusan `tukaran` dengan peringkat "Semakan Bahagian Wakaf (Syariah / istibdal)" (SLA 10 hari) hanya apabila `bahagian_pemilik = wakaf`; tiada peraturan nilai/syarat istibdal | `app/Modules/Tsu/Support/AliranTsu.php` (aliran pelupusan), `Tsu/Support/Lalai::KAEDAH_PELUPUSAN` | PLAN §15 G4: "Istibdal wakaf perlu dasar MAIS" |
| Penilaian JPPH | Sah 12 bulan jika tiada tarikh luput | `tsu.tempoh_sah_penilaian_bulan` | SRS-TSU-05 |
| Sebut harga jurukur | Pemberat harga 60 / tempoh 25 / pengalaman 15; minimum 3 jurukur | `tsu.pemberat_sebutharga`, `tsu.minimum_jurukur_dijemput` | — |
| **Susut nilai aset** | Garis lurus; hayat guna lalai 50 tahun (lajur) atau nilai per kategori; nilai sisa 10% | `app/Modules/Ast/Support/SusutNilai.php`; `aset_kategori.hayat_guna_tahun`, `kadar_nilai_sisa` (`Ast/Database/migrations/2026_11_10_100100_seed_ast_defaults.php`) | SRS-AST-18 hanya nilai laporan penilaian; MPSAS 17 diandaikan |
| Had kelulusan aset | Perolehan ≥RM500,000; penyelenggaraan ≥RM20,000 → WFE | `ast.ambang_kelulusan_perolehan_rm`, `ast.ambang_kelulusan_penyelenggaraan_rm` | — |
| **Sempadan mukim** | Anggaran Voronoi daripada titik OSM, dipotong mengikut sempadan daerah OSM; `anggaran = true` | `app/Modules/Ref/Database/data/sempadan_mukim_anggaran.geojson`, `mukim_titik.json`, `MANIFEST.md` | PLAN §15 G0 |
| **Kod mukim diteka** | 5 kod `kod_disahkan = false`: 100603 Ampang, 100703 Ampang Pecah, 100708 Kuala Kalumpang, 100713 Sungai Tinggi, 100404 Hulu Tinggi | `app/Modules/Ref/Database/data/mukim.json`, `MANIFEST.md` baris 26 | Sahkan dengan JUPEM/PTG |
| Kod PBT | Singkatan rasmi (MBSA, MBPJ …); liputan daerah dipetakan sendiri | `app/Modules/Ref/Database/data/pbt.json` | — |
| SLA aduan (ADU) | Respons lalai 3 hari bekerja; selesai 30 hari bekerja; "hampir tamat" pada 80% | `adu.sla_respons_lalai`, `adu.sla_selesai_lalai`, `adu.ambang_hampir_tamat_peratus` (`AduServiceProvider.php:124-128`) + konfigurasi per kategori (ADU-12) | — |
| Had portal awam ADU | 5 aduan/IP/jam; 3/telefon/hari; 30 semakan/IP/jam; fail 10 MB × 5 | `adu.awam_*` | SRS-ADU-24 |
| SLA aliran kerja (WFE) | Per peringkat dalam seeder: 2–10 hari bekerja (cth. TSU 5/10); amaran 24 jam bekerja; tangguh lalai 5 hari; waktu kerja 08:00–17:00; hujung minggu Sabtu & Ahad; kalendar cuti Selangor 2026–2027 | `database/seeders/*AliranSeeder.php`, `Tsu/Support/AliranTsu.php`; `wfe.sla_amaran_jam`, `wfe.tangguh_hari_lalai`, `wfe.waktu_kerja_*`, `wfe.hari_hujung_minggu`, `wfe.negeri_kalendar` | SRS-WFE-06; kalendar perlu dikemas kini setiap tahun |
| Penyelia lalai | Peranan `admin` jika tiada hierarki; eskalasi ADU `pelulus` | `wfe.peranan_penyelia_lalai`, `adu.peranan_ketua_bahagian` | PLAN §15 G0: tiada hierarki penyelia sebenar |
| MFA step-up | Sah 10 minit; OTP tidak wajib; `acr` diterima `mfa`,`otp`,`2` | `wfe.mfa_sah_minit`, `wfe.mfa_wajib_otp`, `wfe.mfa_acr_diterima` | SRS-WFE-19, SEC-08 |
| **Pintasan MFA UAT** | `wfe.mfa_pintas_uat` (lalai `false`; hanya berkesan jika `APP_ENV=uat`, `Wfe/Services/StepUp.php:52`). `sisdata:demo-seed --pintas-mfa-uat` juga menetapkan `kes.mfa_wajib=false`. | `WfeServiceProvider.php:138`, `KesServiceProvider.php:139` | **PROD: sahkan `wfe.mfa_pintas_uat=false` dan `kes.mfa_wajib=true`.** Jangan salin DB UAT ke PROD. |
| **Polisi kata laluan & sesi** | Panjang min 12; **luput 90 hari**; sejarah 5; kunci selepas 5 gagal dalam 15 min selama 30 min; sesi tamat 15 min; maks 3 sesi serentak; ikat IP subnet /24; gantung selepas 90 hari tidak aktif; recert 180 hari / 14 hari semakan | `app/Modules/Rbc/Services/SecurityPolicy.php` (parameter `rbc.*`) | PLAN §14 #8 (90 hari bercanggah NIST, dikekalkan kerana RFP) |
| Retensi audit | ≥24 bulan dalam talian (dikuatkuasa CHECK DB); arkib selamanya | `audit_retensi_polisi` (`Aud/Database/migrations/2026_10_24_190000_create_audit_tables.php`), `aud.retensi_minimum_bulan` | SRS-AUD-20 |
| Retensi log ADM | Log ralat 180 hari; log integrasi 180 hari; sejarah tugasan 365 hari; semakan kesihatan 30 hari (semua kritikal) | `adm.log_ralat_simpan_hari`, `adm.log_integrasi_simpan_hari`, `adm.sejarah_tugasan_simpan_hari`, `adm.kesihatan_simpan_hari` | SRS-ADM-14, INF-25 |
| Retensi dokumen DMS (bulan aktif / arkib) | perjanjian 120/120; resit 84/12; surat rasmi 84/36; gambar tapak 60/24; laporan 84/36; lain 60/24; geran, pelan dan fail GIS kekal | `dokumen_kategori` (`Dms/Database/migrations/2026_10_24_300000_create_dms_tables.php:195-203`) | SRS-DMS-21; perlu Jadual Pelupusan Rekod MAIS/Arkib Negara |
| Retensi rekod lot | Jadual per kategori (diurus UI) | `jadual_retensi` (PRO), `pro:arkib-retensi` bulanan | SRS-DAT-06 |
| Ambang kesihatan | Storan 85%; baris gilir 1000; 3 kegagalan integrasi; lewat tugasan 15 min | `adm.ambang_*`, `adm.tugasan_toleransi_lewat_minit` | SRS-ADM-17 |
| Had AI | 10 prompt/minit, 300/hari per pengguna; awam 60/minit | `kba.had_prompt_seminit`, `kba.had_prompt_sehari`, `kba.had_prompt_awam_seminit` | SRS-KBA-19 |
| Skor risiko PTN | Cukai RM5,000 = skor penuh; aset bernilai tinggi RM1 juta; tidak diperiksa 365 hari | `ptn.risiko_cukai_rm_penuh`, `ptn.nilai_aset_tinggi_rm`, `ptn.hari_belum_diperiksa` | SRS-PTN-12 |
| Toleransi spatial | Keluasan PRO 10%; TSU 5%; pertindihan 5% / 1%; GPS 10 m; ketepatan 50 m; SRID lalai 3380 | `pro.toleransi_keluasan_peratus`, `tsu.toleransi_keluasan_peratus`, `pro.ambang_pertindihan_peratus`, `gis.ambang_pertindihan_peratus`, `ptn.toleransi_lingkungan_m`, `gis.toleransi_ketepatan_m`, `gis.import_srid_lalai` | — |
| Had import/eksport | Import lot 5,000 baris; GIS 50 MB / 20,000 ciri; eksport LAP 50,000; audit 50,000 | `pro.import_baris_maks`, `gis.import_saiz_maks_mb`, `gis.import_ciri_maks`, `lap.had_eksport`, `AUD_HAD_EKSPORT` | — |
| Waktu pejabat audit | 08:00–17:00, Isnin–Jumaat; cuti umum kosong | `config/aud.php` `waktu_pejabat` (`AUD_WAKTU_MULA`, `AUD_WAKTU_TAMAT`) | SRS-AUD-15 |
| Ambang aktiviti pukal audit | 100 tindakan/jam; eksport besar 1,000 baris | `AUD_AMBANG_KELOMPOK`, `AUD_AMBANG_EKSPORT` | — |

---

## D. Rahsia & pemboleh ubah persekitaran

Legenda: **R** = rahsia (simpan dalam Secrets Manager/SSM SecureString, jangan dalam fail). **P** = mesti berbeza di PROD (nilai di sebelah ialah nilai PROD yang disyorkan).

### D.1 Aplikasi teras (Laravel)

| Pemboleh ubah | R/P | Nilai PROD / catatan |
| --- | --- | --- |
| `APP_ENV` | P | `production`. Juga mematikan `PWS_GATEWAY_TEST` secara lalai. |
| `APP_DEBUG` | P | `false`. `ApiErrorRenderer` menyembunyikan butiran dalaman hanya jika `false`. |
| `APP_KEY` | R | Juga menyulit stor rahsia ADM, cast `encrypted` lajur dan kunci DMS jika `DMS_ENCRYPTION_KEY` kosong. **Kehilangan = data tidak boleh dinyahsulit.** |
| `APP_PREVIOUS_KEYS` | R | Untuk penggiliran `APP_KEY`. |
| `APP_URL` | P | `https://<domain-api>`. Menentukan kuki `secure` dalam ujian RBC-20. |
| `APP_NAME`, `APP_LOCALE` (`ms`), `APP_FALLBACK_LOCALE`, `APP_FAKER_LOCALE` | — | — |
| `APP_MAINTENANCE_DRIVER`, `APP_MAINTENANCE_STORE` | P | `cache` + `redis` untuk berbilang instance. |
| `BCRYPT_ROUNDS` | P | ≥12. |
| `LOG_CHANNEL`, `LOG_STACK`, `LOG_LEVEL`, `LOG_DAILY_DAYS`, `LOG_STDERR_FORMATTER` | P | `LOG_LEVEL=warning`/`info`. Hantar stderr/JSON ke CloudWatch. |
| `ADM_LOG_ARAS` (`warning`), `ADM_LOG_JSON` (`true`), `ADM_KESIHATAN_TAMAT_MASA` | — | — |
| `FRONTEND_URL` | P | `https://<domain-ui>`. Digunakan untuk CORS, redirect OIDC dan pautan QR. |

### D.2 Pangkalan data, cache, baris gilir

| Pemboleh ubah | R/P | Nilai PROD / catatan |
| --- | --- | --- |
| `DB_CONNECTION` | P | `pgsql` (`.env.example` tersilap `sqlite`). |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` | P | Pengguna aplikasi `sisdata_app`; migrasi dijalankan sebagai `sisdata_owner`. |
| `DB_PASSWORD` | R | — |
| `DB_SSLMODE` | P | `verify-full` (lalai `prefer`). |
| `DB_URL` | R | Jika digunakan. |
| `AUD_DB_APP_ROLE` | P | `sisdata_app` semasa migrasi (`app/Modules/Aud/Database/sql/peranan-pengeluaran.sql`). |
| `DSH_DB_READ_HOST`, `DSH_DB_READ_PORT`, `DSH_DB_READ_USERNAME` | P | Replika baca. |
| `DSH_DB_READ_PASSWORD` | R | — |
| `LAP_DB_READ_HOST`, `LAP_DB_READ_PORT`, `LAP_DB_READ_DATABASE`, `LAP_DB_READ_USERNAME` (`sisdata_lap`) | P | Replika baca. |
| `LAP_DB_READ_PASSWORD` | R | — |
| `SISDATA_BI_PASSWORD`, `SISDATA_LAP_PASSWORD`, `METABASE_DB_PASSWORD` | R | Hanya untuk skrip `infra/bin/sisdata-bi-setup.sh`. Lalai dev mesti diganti. |
| `REDIS_CLIENT` | P | `phpredis` (pasang sambungan) atau `predis`. |
| `REDIS_HOST`, `REDIS_PORT`, `REDIS_URL`, `REDIS_USERNAME`, `REDIS_DB`, `REDIS_CACHE_DB`, `REDIS_PREFIX` | P | ElastiCache dengan TLS. |
| `REDIS_PASSWORD` | R | AUTH token. |
| `QUEUE_CONNECTION` (`redis`), `CACHE_STORE` (`redis`), `REDIS_QUEUE_RETRY_AFTER` | — | `retry_after` mesti lebih besar daripada timeout kerja terpanjang. |
| `HORIZON_DOMAIN`, `HORIZON_PATH`, `HORIZON_NAME` | — | Gate `viewHorizon` perlu dibaiki (§A.7 #3). |
| `SESSION_DRIVER` (`database`), `SESSION_LIFETIME`, `SESSION_ENCRYPT`, `SESSION_PATH`, `SESSION_DOMAIN`, `SESSION_COOKIE` | P | `SESSION_ENCRYPT=true` disyorkan. `SESSION_DOMAIN` ialah domain UI. `SESSION_COOKIE` mesti sama dengan `SESSION_COOKIE_NAME` frontend. |
| `SESSION_SECURE_COOKIE` | P | `true`. |
| `SESSION_SAME_SITE` (`lax`), `SESSION_HTTP_ONLY` (`true`), `SESSION_PARTITIONED_COOKIE`, `SESSION_EXPIRE_ON_CLOSE` | — | — |
| `SANCTUM_STATEFUL_DOMAINS` | P | Domain UI PROD sahaja. Buang `localhost`/`127.0.0.1`. |
| `SANCTUM_TOKEN_PREFIX` | — | Cth. `sisdata_` untuk pengesanan rahsia oleh gitleaks. |

### D.3 Pengesahan / SSO

| Pemboleh ubah | R/P | Nilai PROD / catatan |
| --- | --- | --- |
| `AUTH_PASSWORD_LOGIN` | P | `false`. Hanya SSO MAIS untuk pengguna dalaman (SRS-SEC-07). |
| `KEYCLOAK_BASE_URL`, `KEYCLOAK_REALM`, `KEYCLOAK_CLIENT_ID` | P | Nilai SSO MAIS. |
| `KEYCLOAK_CLIENT_SECRET` | R | Nilai dev dalam `.env.example` mesti diganti. |
| `KEYCLOAK_REDIRECT_URI`, `KEYCLOAK_POST_LOGOUT_REDIRECT_URI`, `KEYCLOAK_AFTER_LOGIN_REDIRECT_URI` | P | Berasaskan `FRONTEND_URL` https. |
| `AUTH_GUARD`, `AUTH_MODEL`, `AUTH_PASSWORD_BROKER`, `AUTH_PASSWORD_TIMEOUT`, `AUTH_PASSWORD_RESET_TOKEN_TABLE` | — | Lalai. |

### D.4 Storan, dokumen, antivirus

| Pemboleh ubah | R/P | Nilai PROD / catatan |
| --- | --- | --- |
| `DOKUMEN_S3_ENDPOINT` | P | Kosong/AWS (bukan MinIO). |
| `DOKUMEN_S3_PATH_STYLE` | P | `false` untuk AWS S3. |
| `DOKUMEN_S3_REGION`, `DOKUMEN_S3_BUCKET` | P | — |
| `DOKUMEN_S3_KEY`, `DOKUMEN_S3_SECRET` | R | Lebih baik kosong dan guna IAM role tugas/instance. |
| `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_URL`, `AWS_ENDPOINT`, `AWS_USE_PATH_STYLE_ENDPOINT` | R/P | Hanya jika cakera `s3` lalai digunakan. |
| `FILESYSTEM_DISK` | P | — |
| `LAP_DISK` | P | Tukar daripada `local` kepada cakera S3 (§A.7 #7). |
| `DMS_DISK` (`dokumen`) | — | — |
| `DMS_ENCRYPTION_KEY` | R | 32 bait, base64. **Wajib di PROD** (jika kosong, kunci diterbitkan daripada `APP_KEY`). |
| `DMS_SCANNER` | P | `clamd`, tidak pernah `fake`. |
| `CLAMAV_SOCKET`, `CLAMAV_TIMEOUT` | P | Laluan Linux/TCP. |
| `DMS_PDFTOTEXT`, `DMS_PDFTOPPM`, `DMS_TESSERACT`, `DMS_OCR_MAX_PAGES`, `DMS_PREVIEW_MAX_PAGES` | — | Binari poppler + tesseract (`msa`/`eng`) dalam imej. |
| `GIS_OGR2OGR`, `GIS_OGRINFO` | — | GDAL dalam imej. |

### D.5 Audit, SIEM, pemantauan

| Pemboleh ubah | R/P | Nilai PROD / catatan |
| --- | --- | --- |
| `AUD_SIEM` | P | `cef` (dengan `AUD_SIEM_SYSLOG=true`) atau `jsonl` ke storan yang dihantar. |
| `AUD_SIEM_LALUAN`, `AUD_SIEM_LALUAN_CEF` | P | Laluan dikongsi/dihantar oleh ejen log. |
| `ADM_SIEM_FAIL` | P | Seperti di atas. Parameter `adm.siem_pemacu`/`adm.siem_hos` ditetapkan di UI. |
| `AUD_GEOIP` | — | Belum berkesan (§A.7 #4). |
| `AUD_ZON_WAKTU`, `AUD_WAKTU_MULA`, `AUD_WAKTU_TAMAT`, `AUD_AMBANG_KELOMPOK`, `AUD_AMBANG_EKSPORT`, `AUD_HAD_EKSPORT`, `AUD_CRON_INTEGRITI`, `AUD_CRON_ARKIB`, `AUD_CRON_LAPORAN` | — | Lalai munasabah. |
| `MAILPIT_UI_URL` | P | Buang/ubah probe `mailpit` dalam `config/adm.php`. |

### D.6 E-mel

| Pemboleh ubah | R/P | Nilai PROD / catatan |
| --- | --- | --- |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_SCHEME`, `MAIL_USERNAME`, `MAIL_EHLO_DOMAIN` | P | Gateway SMTP MAIS dengan TLS. |
| `MAIL_PASSWORD` | R | — |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | P | Alamat rasmi (bukan `hello@example.com`). |
| `POSTMARK_API_KEY`, `RESEND_API_KEY`, `SLACK_BOT_USER_OAUTH_TOKEN` | R | Tidak digunakan; biar kosong. |

### D.7 GIS, BI, AI, portal

| Pemboleh ubah | R/P | Nilai PROD / catatan |
| --- | --- | --- |
| `GEOSERVER_URL` | P | Hos dalaman GeoServer/SuperMap. |
| `GEOSERVER_ADMIN_USER` | P | — |
| `GEOSERVER_ADMIN_PASSWORD` | R | Lalai `geoserver` — **mesti diganti**. |
| `GIS_GEOSERVER_PROXY_USER` | P | — |
| `GIS_GEOSERVER_PROXY_PASSWORD` | R | Lalai `sisdata_proxy_dev` — **mesti diganti**. |
| `GIS_GEOSERVER_WORKSPACE`, `GIS_GEOSERVER_TIMEOUT` | — | — |
| `METABASE_SITE_URL` | P | — |
| `METABASE_EMBED_SECRET` | R | — |
| `METABASE_EMBED_MINUTES` | — | — |
| `KBA_EMBEDDING_PEMACU`, `KBA_LLM_PEMACU` | P | `ollama` jika pelayan disediakan; jika tidak, `cincangan`/`tiada` (jawapan ekstraktif). |
| `KBA_OLLAMA_URL`, `KBA_EMBEDDING_MODEL`, `KBA_LLM_MODEL`, `KBA_EMBEDDING_TIMEOUT`, `KBA_LLM_TIMEOUT`, `KBA_LLM_KONTEKS` | P | Alamat peribadi sahaja. |
| `DSH_AI_PEMACU`, `DSH_AI_OLLAMA_URL`, `DSH_AI_MODEL`, `DSH_AI_TIMEOUT` | P | — |
| `PWS_PORTAL_ORIGINS` | P | `https://<domain-portal>` sahaja. |
| `PWS_GATEWAY_TEST` | P | `false` (atau biarkan; lalai `false` di `production`). |
| `PWS_TOKEN_MINUTES` (15), `PWS_ROTATION_GRACE_HOURS` (24), `PWS_INTEGRATION_ACCOUNT`, `PWS_GATEWAY_TEST_ACCOUNT` | — | — |

### D.8 Rahsia yang disimpan dalam stor ADM (bukan env)

Disimpan dengan `php artisan adm:secret <kunci>` atau `printf '%s' "$NILAI" | php artisan adm:secret <kunci> --stdin` (`app/Modules/Adm/Console/TetapkanRahsia.php`). Rahsia ini disulit dengan `APP_KEY`.

- `pjk.gateway_rahsia`: rahsia HMAC webhook gateway pembayaran. Tanpanya, nilai diterbitkan daripada `APP_KEY`; **tetapkan di PROD**.
- Rahsia setiap integrasi dirujuk oleh medan `id_rahsia` dalam tetapan integrasi ADM, cth. `integrasi.jupem.token` (seperti contoh dalam arahan), dan token GRP, MBI, SMS dan CAPTCHA apabila adapter Live dibina.

### D.9 Frontend (Next.js) dan portal

| Pemboleh ubah | R/P | Nilai PROD / catatan |
| --- | --- | --- |
| `INTERNAL_API_URL` | P | URL dalaman API Laravel untuk rewrite `/api`, `/sanctum`, `/auth` dan panggilan pelayan (`frontend/next.config.ts`, `frontend/lib/auth-server.ts`, `frontend/lib/settings-server.ts`). |
| `FRONTEND_URL` | P | Sama dengan nilai Laravel. |
| `SESSION_COOKIE_NAME` | P | Mesti sama dengan `SESSION_COOKIE` Laravel (`frontend/proxy.ts`). |
| `NEXT_PUBLIC_APP_VERSION` | P | Versi pelepasan (dihantar dalam konteks tiket TKT). |
| `NEXT_PUBLIC_MAPLIBRE_WORKER_URL` | — | Jika worker MapLibre dihoskan sendiri. |
| `NODE_ENV` | P | `production`. |
| `TEST_ADMIN_EMAIL`, `TEST_ADMIN_PASSWORD`, `CI` | — | Ujian e2e sahaja. **Jangan** tetapkan di PROD. |
| WordPress `pws/` | R | Kata laluan DB, client id/secret API dan garam WP ditulis ke `~/sisdata-services/pws-wordpress.env` dan blok terurus `wp-config.php` (`pws/README.md`). PROD: Secrets Manager. Putaran: `PWS_ROTATE=1 infra/bin/pws-setup.sh`. |
| MinIO `MINIO_ROOT_USER`/`MINIO_ROOT_PASSWORD` | R | Dev sahaja (`infra/bin/sisdata-services.sh`); tidak digunakan di AWS. |

---

## E. Perkhidmatan untuk dideploy

| Perkhidmatan | Peranan | Nota PROD / saiz |
| --- | --- | --- |
| **API Laravel (php-fpm + nginx)** | `/api/v1/*`, `/auth/oidc/*`, `/sanctum/*`, `/up` | PHP 8.5 (CI). Sambungan: `pgsql`, `redis` (atau predis), `bcmath`, `gd`/`imagick`, `zip`, `intl`, `sodium`. Binari: poppler-utils, tesseract, GDAL (`ogr2ogr`). `upload_max_filesize`/`post_max_size` ≥ 50 MB (spatial 250 MB); `memory_limit` ≥ 512 MB (suite ujian perlu 1 GB, PLAN §15). ≥2 tugas merentas AZ. |
| **Pekerja baris gilir (Horizon)** | Notifikasi, eksport/laporan latar, indeks KBA, OCR, imbasan | `php artisan horizon`; `maxProcesses` 10 di produksi (`config/horizon.php`). Timeout 60 s perlu dinaikkan atau baris gilir dipisahkan (§A.7 #9). Sekurang-kurangnya 1 tugas per AZ. |
| **Penjadual** | `php artisan schedule:run` setiap minit (satu sahaja, atau `onOneServer()` dengan kunci Redis) | Senarai di bawah. |
| **UI Next.js 16** | `frontend/` (`next build && next start`), port 3000 | Node 22 (CI). Di belakang ALB yang sama dengan API supaya kuki sesi kekal satu origin (`frontend/next.config.ts` rewrite). ≥2 tugas. |
| **PostgreSQL 17 + PostGIS 3.5/3.6 + pgvector + pg_trgm** | DB utama, spatial, vektor KBA, carian kabur | RDS Multi-AZ + replika baca (LAP/DSH). Sambungan dicipta oleh `rds_superuser` (`infra/bin/sisdata-db-setup.sh`, migrasi extension). Peranan `sisdata_owner`, `sisdata_app` (`peranan-pengeluaran.sql`), `sisdata_lap`, `sisdata_bi` dan `metabase` (`sisdata-bi-setup.sh`). Saiz DB dev kini: ~58k rekod audit untuk 500 lot sintetik (PLAN §15 G4). Unjur 3× isi padu sebenar (NFR-10). |
| **Redis** | Cache, baris gilir, kunci `withoutOverlapping`/`onOneServer`, had kadar | ElastiCache Multi-AZ, TLS, AUTH. |
| **Storan objek S3** (MinIO tempatan) | Cakera `dokumen` (fail tersulit AES-GCM), eksport LAP | SSE-KMS, versioning, CRR ke DR, Block Public Access. Bucket dicipta dengan `php artisan sisdata:bucket` (`routes/console.php`). |
| **ClamAV (clamd + freshclam)** | Imbasan setiap muat naik DMS; gagal tertutup | Tugas/sidecar ≥2 dengan RAM ≥2–3 GB (pangkalan tandatangan). `StreamMaxLength` ≥ had muat naik. |
| **GeoServer → SuperMap** | WMS/WFS di belakang `OgcProxy`; tetapan `php artisan` `GeoServerSetupCommand` | Tempatan Jetty `-Xmx1g` (`infra/bin/sisdata-services.sh`). PROD ≥2 GB heap, direktori data pada EFS dan disandarkan. Ruang kerja ditutup kepada akses tanpa nama. |
| **SSO MAIS** (Keycloak 26 tempatan) | OIDC + MFA (TOTP) | Tempatan: `infra/bin/keycloak-dev.sh`, realm `infra/keycloak/realm-mais.json`. PROD: SSO MAIS sedia ada (bukan dideploy oleh projek). |
| **Ollama / LLM tempatan** | Embedding `nomic-embed-text` (768 dimensi), jawapan `qwen2.5:1.5b`, insight DSH pilihan `llama3.2` | Instance peribadi dalam VPC (tiada IP awam). CPU 4–8 vCPU / 8–16 GB untuk model 1.5B, GPU disyorkan untuk masa respons. Jika tiada, KBA kembali ke mod ekstraktif (`Kba/AiTempatanTest::test_SRS_KBA_15_extractive_mode_when_model_is_down_or_failing`). |
| **Metabase OSS** | BI atas view agregat `laporan.bi_*` | Tempatan port 3030 (`infra/bin/metabase-dev.sh`). JVM 2–4 GB; DB aplikasi `metabase_app`. |
| **SMTP** (Mailpit tempatan) | E-mel NTF, resit, laporan berjadual | Gateway e-mel MAIS. |
| **Portal WordPress** (`pws/`) | Portal Hartanah Wakaf Selangor; memanggil `/api/v1/pws/awam/*` | Pelayan/domain berasingan (SRS-PWS-02), PHP + MySQL/MariaDB (RDS MySQL). Pemalam `sisdata-connector`, tema `hartanah-wakaf-selangor`, hardening (`pws/wp-content/plugins/sisdata-connector/includes/hardening.php`). VAPT berasingan. Tempatan: `php -S` + `upload_max_filesize=6M`/`post_max_size=32M`. |
| **Lain-lain** | WAF, ALB, ACM, KMS, Secrets Manager, CloudWatch, AWS Backup, VPN/SSM, CloudTrail, GuardDuty | Lihat §A.4/§A.5. |

### E.1 Tugasan berjadual (perlu `schedule:run` setiap minit)

Sumber: `routes/console.php` (tiada jadual; hanya arahan `sisdata:bucket`) dan `callAfterResolving(Schedule::class, …)` dalam pembekal modul. Tugasan ADM didaftarkan daripada jadual `adm_tugasan_berjadual` (`Adm/Database/migrations/2026_10_25_100100_create_adm_tugasan_tables.php`).

| Modul | Arahan | Jadual |
| --- | --- | --- |
| ADM | `adm:semak-kesihatan` | setiap 5 minit (DB) |
| ADM | `adm:semak-tugasan` | `*/10 * * * *` (DB) |
| ADM | `adm:pangkas-log` | 02:30 harian (DB) |
| AUD | `aud:semak-integriti` | `AUD_CRON_INTEGRITI` (`0 * * * *`) |
| AUD | `aud:arkib` | `AUD_CRON_ARKIB` (`30 2 * * *`) |
| AUD | `aud:laporan` | `AUD_CRON_LAPORAN` (`0 7 * * *`) |
| AUD | `aud:ulang-spool` | setiap 5 minit |
| RBC | `rbc:tamat-sesi` | setiap minit |
| RBC | `rbc:semak-status` | setiap jam |
| RBC | `rbc:recert` | 01:00 |
| NTF | `ntf:peringatan`, `ntf:ringkasan` | harian (masa daripada tetapan NTF) |
| NTF | `ntf:eskalasi` | 5 minit |
| NTF | `ntf:proses-tertunggak` | 10 minit |
| WFE | `wfe:semak-sla` | 15 minit |
| DMS | `dms:semak-luput` | 06:00 |
| DMS | `dms:arkib-auto` | 01:30 |
| DMS | `dms:semak-kuota` | setiap jam |
| PRO | `pro:laporan-kualiti` | Isnin 06:30 |
| PRO | `pro:kesan-duplikasi` | 02:30 |
| PRO | `pro:arkib-retensi` | 1hb 03:00 |
| PER | `per:eskalasi` | 07:30 |
| PER | `per:pautkan-lot` | setiap jam |
| PER | `per:portal-wakaf` | 15 minit |
| GIS | `gis:semak-kualiti` | Isnin 05:30 |
| DSH | `dsh:ringkasan-berkala` | 07:00 |
| PJK | `pjk:harian` | 00:30 |
| PJK | `pjk:semak-gateway` | 5 minit |
| PJK | `pjk:rekonsiliasi --sumber=semua` | 06:00 |
| CKI | `cki:semak-bil` | 02:00 |
| CKI | `cki:semak-bil --hanya-sandaran` | setiap jam |
| CKI | `cki:harian` | 03:00 |
| CKI | `cki:semak-grp` | 30 minit |
| CKI | `cki:rekonsiliasi` | 06:30 |
| PTN | `ptn:kira-risiko` | 02:30 |
| PTN | `ptn:semak-tertunggak` | 07:00 |
| PTN | `ptn:jana-jadual` | 1 Disember 03:00 |
| ADU | `adu:semak-sla` | 15 minit |
| ADU | `adu:segerak-pusat` | 30 minit |
| KES | `kes:semak-eskalasi` | 07:30 |
| TSU | `tsu:semak-tempoh` | 07:15 |
| AST | `ast:tarik-keperluan-ptn` | 05:30 |
| AST | `ast:semak-harian` | 06:30 |
| KBA | `kba:ringkasan-jurang` | Isnin 08:00 |
| KBA | `kba:analitik` | 01:30 |
| LAP | `lap:jadual` | 5 minit |
| LAP | `lap:analitik` | 02:30 |
| LAP | `lap:insight-eksekutif` | 06:30 |
| TKT | `tkt:semak-sla` | 5 minit |
| TKT | `tkt:segerak-pusat` | 10 minit |
| TKT | `tkt:laporan-bulanan` | 1hb 07:00 (Asia/Kuala_Lumpur) |
| PWS | `pws:cuba-semula` | 15 minit |
| PWS | `pws:segerak-status` | 30 minit |
| HKM | Peringatan HKM-07/17 | dijalankan melalui `ntf:peringatan` harian (`HkmServiceProvider.php:94`) |

Selain jadual: `freshclam` (sistem) dan tugas sandaran AWS.

### E.2 Langkah deploy sekali (bootstrap)

1. Cipta sambungan DB (superuser/DBA).
2. Jalankan `peranan-pengeluaran.sql`.
3. `php artisan migrate --force` sebagai `sisdata_owner` dengan `AUD_DB_APP_ROLE=sisdata_app`.
4. `db:seed` bagi data rujukan dan aliran WFE: `G1AliranSeeder`, `HkmAliranSeeder`, `*AliranSeeder` dan `PwsSeeder`. **Jangan** gunakan seeder lot/orang sintetik atau `php artisan sisdata:demo-seed` di PROD. `sisdata:demo-seed` menolak `APP_ENV=production`; kata laluan demo ialah `Sisdata@123` dan akaun lalai `admin@example.com` / `password` dicipta oleh `DatabaseSeeder`. Jika `DatabaseSeeder` dijalankan, nyahaktif atau tukar akaun `admin@example.com` serta-merta.
5. `php artisan sisdata:bucket`.
6. Jalankan `GeoServerSetupCommand`.
7. Jalankan `infra/bin/sisdata-bi-setup.sh`.
8. Tetapkan rahsia dengan `php artisan adm:secret …`.
9. Tukar lalai parameter yang ditanda dalam §C selepas MAIS mengesahkannya.

---

## Semakan prestasi ringkas (UAT DB, 500 lot)

Diukur 25 Okt 2026 pada DB `myds_laravel_uat` (dibenihkan oleh `sisdata:demo-seed`: 500 lot, 483 hak milik, 2,277 cukai, 205 aduan, 120 kontrak, 300 aset, ~67,700 rekod audit). Pelayan: `php artisan serve` (6 pekerja PHP CLI, satu MacBook, tiada OPcache/php-fpm) + PostgreSQL 17 lokal, log masuk sebagai Pentadbir Sistem (skop penuh). 5 panggilan berturut setiap endpoint, terus ke API :8021 (tanpa proksi Next).

| Endpoint | HTTP | Purata (s) | Maks (s) |
| --- | --- | --- | --- |
| `/pro/lot` | 200 | 0.048 | 0.082 |
| `/pro/lot?per_page=100` | 200 | 0.044 | 0.056 |
| `/pro/lot?kod_daerah=1008&q=1` | 200 | 0.035 | 0.040 |
| `/pro/lot/01a170ff-75d6-7169-afd2-5653728222b5` | 200 | 0.031 | 0.033 |
| `/pro/lot/01a170ff-75d6-7169-afd2-5653728222b5/profil` | 200 | 0.067 | 0.071 |
| `/hkm/hak-milik` | 200 | 0.034 | 0.038 |
| `/per/kes` | 200 | 0.052 | 0.059 |
| `/pjk/premis` | 200 | 0.032 | 0.034 |
| `/cki/bil` | 200 | 0.029 | 0.032 |
| `/adu/aduan` | 200 | 0.090 | 0.104 |
| `/kes/dashboard` | 200 | 0.055 | 0.071 |
| `/tsu/urusan` | 200 | 0.056 | 0.069 |
| `/ast/aset` | 200 | 0.037 | 0.042 |
| `/tkt/tiket` | 200 | 0.037 | 0.069 |
| `/dsh/kpi` | 200 | 0.284 | 0.410 |
| `/dsh/dashboard` | 200 | 0.185 | 0.206 |
| `/adm/carian?q=petaling` | 200 | 0.138 | 0.369 |
| `/gis/cari?q=lot` | 200 | 0.031 | 0.033 |
| `/aud/rekod` | 200 | 0.021 | 0.022 |
| `/dms/dokumen` | 200 | 0.025 | 0.031 |
| `/wfe/tugasan` | 200 | 0.026 | 0.027 |

Serentak: 100 panggilan `GET /api/v1/pro/lot?q=1`, 10 serentak → `n=100 p50=0.049590 p95=0.123174 max=0.142553` saat.

Jubin vektor GIS `gis.lot` (z11, kawasan Petaling): 0.03–0.04 s, 2.6–3.4 KB; halaman SSR Next (`/admin/pro/lot`, profil lot, `/admin/gis`): 0.03–0.04 s TTFB (`next start`).

**Berbanding SRS §12.1:** NFR-03 (API ≤ 1.5 s p95) — semua endpoint < 0.3 s; NFR-02 (skrin ≤ 3 s) — TTFB jauh di bawah sasaran; NFR-05 (carian ≤ 5 s) — carian global `adm/carian` 0.08 s; NFR-08 (peta ≤ 5 s) — jubin < 0.05 s. Endpoint paling berat ialah `dsh/kpi` (0.22 s) dan profil 360° (0.10 s).

**Had ukuran ini:** isi padu data hanya 500 lot (portfolio sebenar MAIS dijangka puluhan ribu — NFR-10 menuntut 3× isi padu sebenar), seorang pengguna, mesin pembangunan, tanpa rangkaian/WAF/TLS. Ini semakan kewarasan sahaja, **bukan** Ujian Prestasi Bab 16: ujian beban (200 pengguna serentak, NFR-04) dengan data berskala (cth. `ref:sample-lots --count=50000`) pada infrastruktur setara produksi masih diperlukan.


## Ujian SRS merentas yang ditambah

Ditambah oleh Ketua QA (25 Okt 2026). Semua lulus atas `myds_laravel_test*`.

| Ujian | Fail | Membuktikan |
| --- | --- | --- |
| `test_SRS_GEN_01_every_api_route_requires_an_authenticated_principal_or_is_reviewed_public` | `tests/Architecture/RouteSecurityTest.php` | Setiap laluan `/api/v1` memerlukan sesi/kunci API PRO/klien PWS, kecuali 12 endpoint awam yang dikaji |
| `test_SRS_GEN_01_public_endpoints_still_exist_and_are_rate_limited` | sama | Endpoint awam (aduan awam, QR, token portal, webhook FPX…) dihadkan kadar |
| `test_SRS_GEN_09_every_module_message_key_has_a_malay_translation` | `tests/Feature/CrossCutting/TerjemahanTest.php` | Setiap kunci `lang/en` modul ada terjemahan BM bukan kosong |
| `test_SRS_GEN_09_system_messages_are_in_malay` | `tests/Feature/CrossCutting/MerentasModulTest.php` | Locale `ms`; mesej ralat & pengesahan API dalam BM |
| `test_SRS_SEC_05_api_responses_carry_browser_hardening_headers`, `test_SRS_SEC_05_hsts_is_sent_only_over_https` | sama | Pengepala keselamatan baharu (middleware `SecurityHeaders`) |
| `test_SRS_SEC_09_local_passwords_are_stored_as_adaptive_hashes` | sama | Kata laluan akaun bukan SSO disimpan sebagai bcrypt/argon |
| `test_SRS_SEC_11_session_cookie_is_http_only_and_same_site` | sama | Kuki sesi HttpOnly + SameSite (Secure dipaksa dalam produksi oleh `RbcServiceProvider::bootSessionHardening`) |
| `test_SRS_SEC_12_state_changing_spa_request_without_csrf_token_is_refused` | sama | Permintaan SPA tanpa token XSRF → 419 |
| `test_SRS_NFR_05_list_endpoints_cap_the_page_size` | sama | `per_page` dihadkan (422 atau dikepit ke 200) |
| `test_SRS_ADM_13_unauthenticated_api_call_without_json_accept_header_is_a_401_not_a_500` | `tests/Feature/Api/ErrorFormatTest.php` | Pepijat dibaiki: panggilan API tanpa `Accept: application/json` dahulu memberi 500 (`Route [login] not defined`) |
| `test_SRS_RBC_07_seeded_district_unit_mapping_uses_ref_district_codes` | `tests/Feature/Console/SisdataDemoSeedTest.php` | Pepijat dibaiki: pemetaan tuntutan SSO unit PAID memberi skop `petaling`/`klang` (nama) yang tidak sepadan dengan `kod_daerah` → pegawai daerah tidak nampak sebarang rekod |
| `test_SRS_WFE_19_uat_bypass_parameter_is_ignored_outside_the_uat_environment`, `test_SRS_WFE_19_uat_bypass_is_off_by_default_even_in_uat` | `tests/Modules/Wfe/MfaStepUpTest.php` | Pintasan MFA UAT hanya berkesan apabila `APP_ENV=uat` **dan** parameter kritikal dihidupkan |
| `test_demo_seed_*` (3) | `tests/Feature/Console/SisdataDemoSeedTest.php` | `sisdata:demo-seed` menolak produksi, DB yang sudah ada lot, dan `--pintas-mfa-uat` di luar UAT |

Senario E2E merentas modul (Playwright, `frontend/e2e/uat/`) dipetakan dalam `docs/uat/senario-uat.md` § "Senario merentas modul".

