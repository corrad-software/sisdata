# RTM — SISDATA

_Dijana oleh `php artisan srs:rtm` pada 2026-10-24 14:15:29. Jangan sunting dengan tangan._

| Modul | Keperluan | Ada ujian | Liputan |
| --- | --- | --- | --- |
| ADM | 29 | 28 | 96% |
| ADU | 27 | 0 | 0% |
| AST | 26 | 0 | 0% |
| AUD | 26 | 26 | 100% |
| CKI | 24 | 0 | 0% |
| DAT | 9 | 3 | 33% |
| DMS | 26 | 26 | 100% |
| DSH | 25 | 0 | 0% |
| GEN | 9 | 1 | 11% |
| GIS | 35 | 0 | 0% |
| HKM | 22 | 0 | 0% |
| INF | 30 | 0 | 0% |
| INT | 18 | 0 | 0% |
| KBA | 22 | 0 | 0% |
| KES | 24 | 0 | 0% |
| LAP | 23 | 0 | 0% |
| NFR | 26 | 0 | 0% |
| NTF | 24 | 24 | 100% |
| PER | 30 | 0 | 0% |
| PJK | 28 | 0 | 0% |
| PRO | 30 | 0 | 0% |
| PTN | 24 | 0 | 0% |
| PWS | 28 | 0 | 0% |
| RBC | 27 | 24 | 88% |
| SEC | 35 | 0 | 0% |
| TKT | 21 | 0 | 0% |
| TSU | 27 | 0 | 0% |
| WFE | 24 | 21 | 87% |

## Butiran

| Keperluan | Keutamaan | Ujian | Status |
| --- | --- | --- | --- |
| SRS-ADM-01 | Wajib | `tests/Modules/Adm/ParameterTest.php::test_SRS_ADM_01_parameters_are_listed_grouped_by_module_with_type_and_critical_flag` | LULUS |
| SRS-ADM-01 | Wajib | `tests/Modules/Adm/ParameterTest.php::test_SRS_ADM_01_a_parameter_change_takes_effect_on_the_next_read_without_restart` | LULUS |
| SRS-ADM-01 | Wajib | `tests/Modules/Adm/ParameterTest.php::test_SRS_ADM_01_values_are_validated_against_the_declared_type` | LULUS |
| SRS-ADM-01 | Wajib | `tests/Modules/Adm/ParameterTest.php::test_SRS_ADM_01_reset_returns_to_the_declared_default` | LULUS |
| SRS-ADM-01 | Wajib | `tests/Modules/Adm/ParameterTest.php::test_SRS_ADM_01_user_without_permission_cannot_view_or_change_parameters` | LULUS |
| SRS-ADM-01 | Wajib | `tests/Modules/Adm/ParameterTest.php::test_SRS_ADM_01_parameters_fall_back_to_defaults_when_the_cache_is_down` | LULUS |
| SRS-ADM-02 | Wajib | `tests/Modules/Adm/KodRujukanTest.php::test_SRS_ADM_02_admin_adds_a_code_and_it_is_usable_immediately` | LULUS |
| SRS-ADM-02 | Wajib | `tests/Modules/Adm/KodRujukanTest.php::test_SRS_ADM_02_duplicate_codes_in_a_type_are_refused` | LULUS |
| SRS-ADM-02 | Wajib | `tests/Modules/Adm/KodRujukanTest.php::test_SRS_ADM_02_amendments_are_versioned_with_history` | LULUS |
| SRS-ADM-02 | Wajib | `tests/Modules/Adm/KodRujukanTest.php::test_SRS_ADM_02_deactivated_code_stays_valid_for_historical_records` | LULUS |
| SRS-ADM-02 | Wajib | `tests/Modules/Adm/KodRujukanTest.php::test_SRS_ADM_02_search_by_type_text_status_and_validity_date` | LULUS |
| SRS-ADM-02 | Wajib | `tests/Modules/Adm/KodRujukanTest.php::test_SRS_ADM_02_mukim_parent_must_be_an_existing_daerah` | LULUS |
| SRS-ADM-02 | Wajib | `tests/Modules/Adm/KodRujukanTest.php::test_SRS_ADM_02_permissions_are_enforced_per_action` | LULUS |
| SRS-ADM-03 | Wajib | `tests/Modules/Adm/TemplatTest.php::test_SRS_ADM_03_admin_manages_report_template_content_fields_format_and_versions` | LULUS |
| SRS-ADM-03 | Wajib | `tests/Modules/Adm/TemplatTest.php::test_SRS_ADM_03_placeholders_must_be_declared_fields` | LULUS |
| SRS-ADM-03 | Wajib | `tests/Modules/Adm/TemplatTest.php::test_SRS_ADM_03_preview_and_render_escape_dynamic_values` | LULUS |
| SRS-ADM-03 | Wajib | `tests/Modules/Adm/TemplatTest.php::test_SRS_ADM_03_template_management_requires_permission` | LULUS |
| SRS-ADM-04 | Wajib | `tests/Modules/Adm/KesihatanIntegrasiTest.php::test_SRS_ADM_04_integration_settings_go_through_approval_and_are_readable_by_modules` | LULUS |
| SRS-ADM-04 | Wajib | `tests/Modules/Adm/KesihatanIntegrasiTest.php::test_SRS_ADM_04_test_connection_logs_the_exchange_and_permissions_apply` | LULUS |
| SRS-ADM-05 | Perlu | `tests/Modules/Adm/KeselamatanTest.php::test_SRS_ADM_05_admin_scoped_api_token_can_automate_configuration` | LULUS |
| SRS-ADM-05 | Perlu | `tests/Modules/Adm/KeselamatanTest.php::test_SRS_ADM_05_tokens_without_the_admin_ability_or_permission_are_refused` | LULUS |
| SRS-ADM-05 | Perlu | `tests/Modules/Adm/KeselamatanTest.php::test_SRS_ADM_05_tokens_can_be_listed_and_revoked` | LULUS |
| SRS-ADM-05 | Perlu | `tests/Modules/Adm/KodRujukanTest.php::test_SRS_ADM_05_bulk_import_validates_each_row_and_reports_rejections` | LULUS |
| SRS-ADM-05 | Perlu | `tests/Modules/Adm/KodRujukanTest.php::test_SRS_ADM_05_bulk_import_from_csv_with_upsert` | LULUS |
| SRS-ADM-06 | Wajib | `tests/Modules/Adm/TugasanTest.php::test_SRS_ADM_06_admin_defines_a_cron_job_and_it_is_registered_in_the_scheduler` | LULUS |
| SRS-ADM-06 | Wajib | `tests/Modules/Adm/TugasanTest.php::test_SRS_ADM_06_fixed_interval_jobs_and_disabled_jobs` | LULUS |
| SRS-ADM-06 | Wajib | `tests/Modules/Adm/TugasanTest.php::test_SRS_ADM_06_invalid_cron_and_unsafe_commands_are_refused` | LULUS |
| SRS-ADM-06 | Wajib | `tests/Modules/Adm/TugasanTest.php::test_SRS_ADM_06_run_now_is_queued_and_recorded` | LULUS |
| SRS-ADM-06 | Wajib | `tests/Modules/Adm/TugasanTest.php::test_SRS_ADM_06_enable_disable_and_permissions` | LULUS |
| SRS-ADM-06 | Wajib | `tests/Modules/Adm/TugasanTest.php::test_SRS_ADM_06_other_modules_scheduled_jobs_are_listed_and_adoptable` | LULUS |
| SRS-ADM-07 | Wajib | `tests/Modules/Adm/TugasanTest.php::test_SRS_ADM_07_job_categories_cover_the_required_kinds` | LULUS |
| SRS-ADM-08 | Wajib | `tests/Modules/Adm/TugasanTest.php::test_SRS_ADM_08_run_history_has_start_end_duration_status_records_and_error` | LULUS |
| SRS-ADM-09 | Perlu | `tests/Modules/Adm/TugasanTest.php::test_SRS_ADM_09_the_same_job_never_runs_concurrently` | LULUS |
| SRS-ADM-09 | Perlu | `tests/Modules/Adm/TugasanTest.php::test_SRS_ADM_09_failed_jobs_are_retried_as_configured` | LULUS |
| SRS-ADM-10 | Perlu | `tests/Modules/Adm/TugasanTest.php::test_SRS_ADM_10_final_failure_notifies_system_administrators` | LULUS |
| SRS-ADM-10 | Perlu | `tests/Modules/Adm/TugasanTest.php::test_SRS_ADM_10_a_missed_schedule_alerts_and_stuck_runs_are_closed` | LULUS |
| SRS-ADM-10 | Perlu | `tests/Modules/Adm/TugasanTest.php::test_SRS_ADM_10_failure_of_an_adopted_framework_job_alerts` | LULUS |
| SRS-ADM-11 | Wajib | `tests/Modules/Adm/LogTest.php::test_SRS_ADM_11_errors_are_logged_with_module_severity_code_user_ip_and_trace` | LULUS |
| SRS-ADM-11 | Wajib | `tests/Modules/Adm/LogTest.php::test_SRS_ADM_11_search_filters_by_period_module_severity_code_and_user` | LULUS |
| SRS-ADM-11 | Wajib | `tests/Modules/Adm/LogTest.php::test_SRS_ADM_11_log_access_is_restricted_and_audited` | LULUS |
| SRS-ADM-12 | Perlu | `tests/Feature/Api/ErrorFormatTest.php::test_SRS_ADM_12_every_response_carries_a_request_id` | LULUS |
| SRS-ADM-12 | Perlu | `tests/Feature/Api/ErrorFormatTest.php::test_SRS_ADM_12_a_sane_incoming_request_id_is_kept` | LULUS |
| SRS-ADM-12 | Perlu | `tests/Feature/Api/ErrorFormatTest.php::test_SRS_ADM_12_a_malformed_incoming_request_id_is_replaced` | LULUS |
| SRS-ADM-12 | Perlu | `tests/Modules/Adm/LogTest.php::test_SRS_ADM_12_the_reference_shown_to_the_user_finds_the_log_entry` | LULUS |
| SRS-ADM-13 | Wajib | `tests/Feature/Api/ErrorFormatTest.php::test_SRS_ADM_13_server_errors_hide_internals_and_return_request_id` | LULUS |
| SRS-ADM-13 | Wajib | `tests/Feature/Api/ErrorFormatTest.php::test_SRS_ADM_13_unauthenticated_and_not_found_use_the_same_shape` | LULUS |
| SRS-ADM-13 | Wajib | `tests/Feature/Api/ErrorFormatTest.php::test_SRS_ADM_13_validation_errors_keep_field_errors` | LULUS |
| SRS-ADM-14 | Wajib | `tests/Modules/Adm/LogTest.php::test_SRS_ADM_14_application_and_integration_logs_export_by_period` | LULUS |
| SRS-ADM-14 | Wajib | `tests/Modules/Adm/LogTest.php::test_SRS_ADM_14_retention_policy_prunes_old_rows_per_configured_period` | LULUS |
| SRS-ADM-15 | Wajib | `tests/Modules/Adm/LogTest.php::test_SRS_ADM_15_logs_never_store_passwords_tokens_keys_or_full_ic_numbers` | LULUS |
| SRS-ADM-15 | Wajib | `tests/Modules/Adm/LogTest.php::test_SRS_ADM_15_redactor_patterns` | LULUS |
| SRS-ADM-15 | Wajib | `tests/Modules/Adm/LogTest.php::test_SRS_ADM_15_structured_json_log_file_is_redacted_and_carries_request_id` | LULUS |
| SRS-ADM-16 | Wajib | `tests/Modules/Adm/KesihatanIntegrasiTest.php::test_SRS_ADM_16_single_health_screen_shows_services_queue_integrations_storage_database_and_errors` | LULUS |
| SRS-ADM-16 | Wajib | `tests/Modules/Adm/KesihatanIntegrasiTest.php::test_SRS_ADM_16_default_probes_cover_every_platform_service` | LULUS |
| SRS-ADM-16 | Wajib | `tests/Modules/Adm/KesihatanIntegrasiTest.php::test_SRS_ADM_16_health_screen_requires_permission` | LULUS |
| SRS-ADM-17 | Wajib | `tests/Modules/Adm/KesihatanIntegrasiTest.php::test_SRS_ADM_17_service_down_queue_and_storage_thresholds_alert_ict` | LULUS |
| SRS-ADM-17 | Wajib | `tests/Modules/Adm/KesihatanIntegrasiTest.php::test_SRS_ADM_17_an_integration_switched_off_alerts_after_repeated_failures` | LULUS |
| SRS-ADM-18 | Wajib | `tests/Modules/Adm/ParameterTest.php::test_SRS_ADM_18_critical_change_stays_pending_until_a_second_admin_approves` | LULUS |
| SRS-ADM-18 | Wajib | `tests/Modules/Adm/ParameterTest.php::test_SRS_ADM_18_rejected_change_is_never_applied_and_duplicates_are_refused` | LULUS |
| SRS-ADM-18 | Wajib | `tests/Modules/Adm/ParameterTest.php::test_SRS_ADM_18_application_admin_cannot_change_security_settings` | LULUS |
| SRS-ADM-18 | Wajib | `tests/Modules/Adm/ParameterTest.php::test_SRS_ADM_18_requester_can_cancel_and_approval_needs_permission` | LULUS |
| SRS-ADM-18 | Wajib | `tests/Modules/Adm/ParameterTest.php::test_SRS_ADM_18_uses_the_workflow_engine_when_available` | LULUS |
| SRS-ADM-18 | Wajib | `tests/Modules/Adm/ParameterTest.php::test_SRS_ADM_18_workflow_approval_by_the_requester_is_refused` | LULUS |
| SRS-ADM-18 | Wajib | `tests/Modules/Adm/ParameterTest.php::test_SRS_ADM_18_falls_back_to_built_in_approval_when_the_flow_is_not_configured` | LULUS |
| SRS-ADM-19 | Wajib | `tests/Modules/Adm/ParameterTest.php::test_SRS_ADM_19_change_record_keeps_before_after_justification_requester_ip_and_approver` | LULUS |
| SRS-ADM-19 | Wajib | `tests/Modules/Adm/ParameterTest.php::test_SRS_ADM_19_pending_and_decided_changes_can_be_listed_and_filtered` | LULUS |
| SRS-ADM-20 | Wajib | `tests/Modules/Adm/CarianTest.php::test_SRS_ADM_20_weights_synonyms_fields_and_enable_switch_are_admin_configurable` | LULUS |
| SRS-ADM-20 | Wajib | `tests/Modules/Adm/CarianTest.php::test_SRS_ADM_20_reindex_is_triggered_and_tracked` | LULUS |
| SRS-ADM-21 | Wajib | `tests/Modules/Adm/CarianTest.php::test_SRS_ADM_21_global_search_by_lot_number_returns_records_from_several_modules` | LULUS |
| SRS-ADM-21 | Wajib | `tests/Modules/Adm/CarianTest.php::test_SRS_ADM_21_results_outside_the_users_scope_are_not_returned` | LULUS |
| SRS-ADM-21 | Wajib | `tests/Modules/Adm/CarianTest.php::test_SRS_ADM_21_detailed_search_by_filters_uses_filterable_providers_only` | LULUS |
| SRS-ADM-22 | Wajib | `tests/Modules/Adm/CarianTest.php::test_SRS_ADM_22_deep_link_resolves_to_the_source_record_with_access_enforced` | LULUS |
| SRS-ADM-22 | Wajib | `tests/Modules/Adm/CarianTest.php::test_SRS_ADM_22_search_result_links_open_the_right_record` | LULUS |
| SRS-ADM-23 | Wajib | `tests/Modules/Adm/DevSecOpsTest.php::test_SRS_ADM_23_pipeline_runs_sast_sca_and_secret_scanning` | LULUS |
| SRS-ADM-24 | Wajib | `tests/Modules/Adm/DevSecOpsTest.php::test_SRS_ADM_24_quality_gate_fails_the_build_on_high_or_critical_findings` | LULUS |
| SRS-ADM-25 | Wajib | — | tiada ujian |
| SRS-ADM-26 | Wajib | `tests/Modules/Adm/KeselamatanTest.php::test_SRS_ADM_26_real_client_ip_comes_from_x_forwarded_for_only_behind_a_trusted_proxy` | LULUS |
| SRS-ADM-26 | Wajib | `tests/Modules/Adm/KeselamatanTest.php::test_SRS_ADM_26_loopback_is_always_trusted_for_the_nextjs_rewrite` | LULUS |
| SRS-ADM-27 | Wajib | `tests/Modules/Adm/KeselamatanTest.php::test_SRS_ADM_27_security_events_are_shipped_to_the_siem_stream` | LULUS |
| SRS-ADM-27 | Wajib | `tests/Modules/Adm/KeselamatanTest.php::test_SRS_ADM_27_syslog_adapter_emits_rfc5424_with_cef_payload` | LULUS |
| SRS-ADM-28 | Wajib | `tests/Modules/Adm/KesihatanIntegrasiTest.php::test_SRS_ADM_28_secrets_are_encrypted_at_rest_and_never_returned` | LULUS |
| SRS-ADM-28 | Wajib | `tests/Modules/Adm/KesihatanIntegrasiTest.php::test_SRS_ADM_28_artisan_adm_secret_stores_an_encrypted_value` | LULUS |
| SRS-ADM-29 | Baik-ada | `tests/Modules/Adm/KeselamatanTest.php::test_SRS_ADM_29_maintenance_mode_shows_notice_blocks_new_transactions_and_lets_admins_in` | LULUS |
| SRS-ADM-29 | Baik-ada | `tests/Modules/Adm/KeselamatanTest.php::test_SRS_ADM_29_only_authorised_admins_switch_maintenance_mode` | LULUS |
| SRS-ADU-01 | Wajib | — | tiada ujian |
| SRS-ADU-02 | Wajib | — | tiada ujian |
| SRS-ADU-03 | Wajib | — | tiada ujian |
| SRS-ADU-04 | Wajib | — | tiada ujian |
| SRS-ADU-05 | Wajib | — | tiada ujian |
| SRS-ADU-06 | Wajib | — | tiada ujian |
| SRS-ADU-07 | Wajib | — | tiada ujian |
| SRS-ADU-08 | Wajib | — | tiada ujian |
| SRS-ADU-09 | Wajib | — | tiada ujian |
| SRS-ADU-10 | Perlu | — | tiada ujian |
| SRS-ADU-11 | Wajib | — | tiada ujian |
| SRS-ADU-12 | Wajib | — | tiada ujian |
| SRS-ADU-13 | Perlu | — | tiada ujian |
| SRS-ADU-14 | Wajib | — | tiada ujian |
| SRS-ADU-15 | Wajib | — | tiada ujian |
| SRS-ADU-16 | Wajib | — | tiada ujian |
| SRS-ADU-17 | Wajib | — | tiada ujian |
| SRS-ADU-18 | Wajib | — | tiada ujian |
| SRS-ADU-19 | Wajib | — | tiada ujian |
| SRS-ADU-20 | Wajib | — | tiada ujian |
| SRS-ADU-21 | Wajib | — | tiada ujian |
| SRS-ADU-22 | Wajib | — | tiada ujian |
| SRS-ADU-23 | Wajib | — | tiada ujian |
| SRS-ADU-24 | Wajib | — | tiada ujian |
| SRS-ADU-25 | Wajib | — | tiada ujian |
| SRS-ADU-26 | Wajib | — | tiada ujian |
| SRS-ADU-27 | Perlu | — | tiada ujian |
| SRS-AST-01 | Wajib | — | tiada ujian |
| SRS-AST-02 | Wajib | — | tiada ujian |
| SRS-AST-03 | Wajib | — | tiada ujian |
| SRS-AST-04 | Wajib | — | tiada ujian |
| SRS-AST-05 | Wajib | — | tiada ujian |
| SRS-AST-06 | Wajib | — | tiada ujian |
| SRS-AST-07 | Wajib | — | tiada ujian |
| SRS-AST-08 | Wajib | — | tiada ujian |
| SRS-AST-09 | Wajib | — | tiada ujian |
| SRS-AST-10 | Perlu | — | tiada ujian |
| SRS-AST-11 | Perlu | — | tiada ujian |
| SRS-AST-12 | Wajib | — | tiada ujian |
| SRS-AST-13 | Wajib | — | tiada ujian |
| SRS-AST-14 | Wajib | — | tiada ujian |
| SRS-AST-15 | Wajib | — | tiada ujian |
| SRS-AST-16 | Wajib | — | tiada ujian |
| SRS-AST-17 | Wajib | — | tiada ujian |
| SRS-AST-18 | Wajib | — | tiada ujian |
| SRS-AST-19 | Wajib | — | tiada ujian |
| SRS-AST-20 | Wajib | — | tiada ujian |
| SRS-AST-21 | Wajib | — | tiada ujian |
| SRS-AST-22 | Perlu | — | tiada ujian |
| SRS-AST-23 | Wajib | — | tiada ujian |
| SRS-AST-24 | Wajib | — | tiada ujian |
| SRS-AST-25 | Wajib | — | tiada ujian |
| SRS-AST-26 | Wajib | — | tiada ujian |
| SRS-AUD-01 | Wajib | `tests/Modules/Aud/AuditRecordingTest.php::test_SRS_AUD_01_create_update_delete_of_any_model_is_recorded` | LULUS |
| SRS-AUD-02 | Wajib | `tests/Modules/Aud/AuditRecordingTest.php::test_SRS_AUD_02_explicit_entries_for_view_download_print_approval_and_ai_prompt` | LULUS |
| SRS-AUD-02 | Wajib | `tests/Modules/Aud/AuditRecordingTest.php::test_SRS_AUD_02_role_assignment_changes_are_recorded` | LULUS |
| SRS-AUD-03 | Wajib | `tests/Modules/Aud/AuditRecordingTest.php::test_SRS_AUD_03_reason_from_scope_helper_and_from_request_header` | LULUS |
| SRS-AUD-03 | Wajib | `tests/Modules/Aud/AuditRecordingTest.php::test_SRS_AUD_03_rolled_back_change_is_recorded_once_as_failed` | LULUS |
| SRS-AUD-03 | Wajib | `tests/Modules/Aud/AuditRecordingTest.php::test_SRS_AUD_03_change_inside_committed_transaction_is_recorded_after_commit` | LULUS |
| SRS-AUD-04 | Wajib | `tests/Modules/Aud/AuditRecordingTest.php::test_SRS_AUD_04_records_session_device_channel_entity_and_correlation_id` | LULUS |
| SRS-AUD-04 | Wajib | `tests/Modules/Aud/AuditRecordingTest.php::test_SRS_AUD_04_console_work_is_recorded_as_system_channel` | LULUS |
| SRS-AUD-05 | Wajib | `tests/Modules/Aud/AuditRecordingTest.php::test_SRS_AUD_05_single_field_change_records_field_old_new_user_role_ip_module_time` | LULUS |
| SRS-AUD-06 | Wajib | `tests/Modules/Aud/AuditIntegrityTest.php::test_SRS_AUD_06_records_form_a_gapless_sha256_chain_over_content_and_field_changes` | LULUS |
| SRS-AUD-07 | Wajib | `tests/Modules/Aud/AuditIntegrityTest.php::test_SRS_AUD_07_direct_tampering_is_detected_and_alerts` | LULUS |
| SRS-AUD-07 | Wajib | `tests/Modules/Aud/AuditIntegrityTest.php::test_SRS_AUD_07_deleted_or_altered_field_change_and_truncated_tail_are_detected` | LULUS |
| SRS-AUD-07 | Wajib | `tests/Modules/Aud/AuditIntegrityTest.php::test_SRS_AUD_07_removed_records_break_the_sequence` | LULUS |
| SRS-AUD-07 | Wajib | `tests/Modules/Aud/AuditIntegrityTest.php::test_SRS_AUD_07_integrity_check_is_scheduled` | LULUS |
| SRS-AUD-07 | Wajib | `tests/Modules/Aud/AuditIntegrityTest.php::test_SRS_AUD_07_integrity_status_api_and_manual_check` | LULUS |
| SRS-AUD-08 | Wajib | `tests/Modules/Aud/AuditIntegrityTest.php::test_SRS_AUD_08_app_database_account_cannot_update_delete_or_truncate_audit_records` | LULUS |
| SRS-AUD-08 | Wajib | `tests/Modules/Aud/AuditIntegrityTest.php::test_SRS_AUD_08_triggers_reject_changes_even_when_privileges_are_regranted` | LULUS |
| SRS-AUD-08 | Wajib | `tests/Modules/Aud/AuditIntegrityTest.php::test_SRS_AUD_08_application_interface_rejects_update_and_delete_for_all_roles` | LULUS |
| SRS-AUD-09 | Wajib | `tests/Modules/Aud/AuditRecordingTest.php::test_SRS_AUD_09_global_listener_covers_a_model_with_no_audit_code_at_all` | LULUS |
| SRS-AUD-09 | Wajib | `tests/Modules/Aud/AuditRecordingTest.php::test_SRS_AUD_09_framework_and_audit_tables_are_not_audited` | LULUS |
| SRS-AUD-10 | Wajib | `tests/Modules/Aud/AuditSearchExportTest.php::test_SRS_AUD_10_combined_user_module_action_and_period_filters` | LULUS |
| SRS-AUD-11 | Wajib | `tests/Modules/Aud/AuditSearchExportTest.php::test_SRS_AUD_11_record_chronology_spans_modules_in_time_order` | LULUS |
| SRS-AUD-12 | Wajib | `tests/Modules/Aud/AuditSearchExportTest.php::test_SRS_AUD_12_export_in_all_four_formats_with_checksum_and_metadata` | LULUS |
| SRS-AUD-13 | Perlu | `tests/Modules/Aud/AuditSearchExportTest.php::test_SRS_AUD_13_export_history_lists_checksums` | LULUS |
| SRS-AUD-14 | Wajib | `tests/Modules/Aud/AuditSearchExportTest.php::test_SRS_AUD_14_search_view_chronology_and_export_of_the_trail_are_audited` | LULUS |
| SRS-AUD-15 | Wajib | `tests/Modules/Aud/AuditRecordingTest.php::test_SRS_AUD_15_classification_is_taken_from_model` | LULUS |
| SRS-AUD-15 | Wajib | `tests/Modules/Aud/AuditSearchExportTest.php::test_SRS_AUD_15_automatic_reports` | LULUS |
| SRS-AUD-15 | Wajib | `tests/Modules/Aud/AuditSearchExportTest.php::test_SRS_AUD_15_bulk_activity_report_flags_users_above_threshold` | LULUS |
| SRS-AUD-16 | Wajib | `tests/Modules/Aud/AuditRecordingTest.php::test_SRS_AUD_16_document_activity_entries` | LULUS |
| SRS-AUD-17 | Wajib | `tests/Modules/Aud/AuditRecordingTest.php::test_SRS_AUD_17_database_notifications_are_recorded_automatically` | LULUS |
| SRS-AUD-18 | Wajib | `tests/Modules/Aud/AuditRecordingTest.php::test_SRS_AUD_18_workflow_decision_entries_keep_approver_decision_and_stage` | LULUS |
| SRS-AUD-18 | Wajib | `tests/Modules/Wfe/DecisionTest.php::test_SRS_AUD_18_decisions_are_explicit_audit_entries_with_justification` | LULUS |
| SRS-AUD-19 | Wajib | `tests/Modules/Aud/AuditRecordingTest.php::test_SRS_AUD_19_password_login_success_and_failure_are_recorded_with_ip_device_location` | LULUS |
| SRS-AUD-19 | Wajib | `tests/Modules/Aud/AuditRecordingTest.php::test_SRS_AUD_19_rbc_events_are_recorded` | LULUS |
| SRS-AUD-20 | Wajib | `tests/Modules/Aud/AuditRetentionSiemTest.php::test_SRS_AUD_20_records_past_online_retention_are_archived_by_the_scheduled_job` | LULUS |
| SRS-AUD-20 | Wajib | `tests/Modules/Aud/AuditRetentionSiemTest.php::test_SRS_AUD_20_policy_is_configurable_but_never_below_24_months` | LULUS |
| SRS-AUD-21 | Wajib | `tests/Modules/Aud/AuditRetentionSiemTest.php::test_SRS_AUD_21_archived_records_stay_searchable_and_archive_access_is_audited` | LULUS |
| SRS-AUD-22 | Wajib | `tests/Modules/Aud/AuditRetentionSiemTest.php::test_SRS_AUD_22_records_are_streamed_to_siem_as_json_lines` | LULUS |
| SRS-AUD-22 | Wajib | `tests/Modules/Aud/AuditRetentionSiemTest.php::test_SRS_AUD_22_cef_format_for_syslog_siem` | LULUS |
| SRS-AUD-23 | Wajib | `tests/Modules/Aud/AuditSearchExportTest.php::test_SRS_AUD_23_access_is_limited_to_audit_permissions_and_read_only` | LULUS |
| SRS-AUD-24 | Perlu | `tests/Modules/Aud/AuditRetentionSiemTest.php::test_SRS_AUD_24_timestamps_are_stored_in_utc_and_malaysia_time` | LULUS |
| SRS-AUD-25 | Wajib | `tests/Modules/Aud/AuditRecordingTest.php::test_SRS_AUD_25_passwords_tokens_and_ic_numbers_are_masked` | LULUS |
| SRS-AUD-26 | Perlu | `tests/Modules/Aud/AuditRecordingTest.php::test_SRS_AUD_26_audit_failure_does_not_fail_the_business_transaction` | LULUS |
| SRS-CKI-01 | Wajib | — | tiada ujian |
| SRS-CKI-02 | Wajib | — | tiada ujian |
| SRS-CKI-03 | Wajib | — | tiada ujian |
| SRS-CKI-04 | Wajib | — | tiada ujian |
| SRS-CKI-05 | Wajib | — | tiada ujian |
| SRS-CKI-06 | Wajib | — | tiada ujian |
| SRS-CKI-07 | Wajib | — | tiada ujian |
| SRS-CKI-08 | Wajib | — | tiada ujian |
| SRS-CKI-09 | Wajib | — | tiada ujian |
| SRS-CKI-10 | Wajib | — | tiada ujian |
| SRS-CKI-11 | Wajib | — | tiada ujian |
| SRS-CKI-12 | Wajib | — | tiada ujian |
| SRS-CKI-13 | Wajib | — | tiada ujian |
| SRS-CKI-14 | Wajib | — | tiada ujian |
| SRS-CKI-15 | Wajib | — | tiada ujian |
| SRS-CKI-16 | Wajib | — | tiada ujian |
| SRS-CKI-17 | Wajib | — | tiada ujian |
| SRS-CKI-18 | Wajib | — | tiada ujian |
| SRS-CKI-19 | Perlu | — | tiada ujian |
| SRS-CKI-20 | Wajib | — | tiada ujian |
| SRS-CKI-21 | Wajib | — | tiada ujian |
| SRS-CKI-22 | Perlu | — | tiada ujian |
| SRS-CKI-23 | Wajib | — | tiada ujian |
| SRS-CKI-24 | Wajib | — | tiada ujian |
| SRS-DAT-01 | Wajib | `tests/Modules/Ref/SampleLotsTest.php::test_SRS_DAT_01_no_duplicate_lot_number_within_a_mukim` | LULUS |
| SRS-DAT-01 | Wajib | `tests/Modules/Ref/SampleLotsTest.php::test_SRS_DAT_01_lot_fields_respect_format_and_valid_ranges` | LULUS |
| SRS-DAT-02 | Wajib | — | tiada ujian |
| SRS-DAT-03 | Wajib | — | tiada ujian |
| SRS-DAT-04 | Wajib | — | tiada ujian |
| SRS-DAT-05 | Wajib | `tests/Modules/Ref/ReferenceApiTest.php::test_SRS_DAT_05_api_lists_districts_with_their_codes` | LULUS |
| SRS-DAT-05 | Wajib | `tests/Modules/Ref/ReferenceApiTest.php::test_SRS_DAT_05_api_filters_mukim_and_pbt_by_district` | LULUS |
| SRS-DAT-05 | Wajib | `tests/Modules/Ref/ReferenceApiTest.php::test_SRS_DAT_05_api_exposes_versions_and_as_of_dates` | LULUS |
| SRS-DAT-05 | Wajib | `tests/Modules/Ref/ReferenceCodesTest.php::test_SRS_DAT_05_seeds_all_selangor_districts_mukims_and_local_authorities` | LULUS |
| SRS-DAT-05 | Wajib | `tests/Modules/Ref/ReferenceCodesTest.php::test_SRS_DAT_05_starter_lists_cover_land_use_title_types_and_classification` | LULUS |
| SRS-DAT-05 | Wajib | `tests/Modules/Ref/ReferenceCodesTest.php::test_SRS_DAT_05_database_rejects_a_second_current_row_for_the_same_code` | LULUS |
| SRS-DAT-05 | Wajib | `tests/Modules/Ref/ReferenceCodesTest.php::test_SRS_DAT_05_database_rejects_duplicate_names_within_a_district_but_allows_them_across_districts` | LULUS |
| SRS-DAT-05 | Wajib | `tests/Modules/Ref/ReferenceCodesTest.php::test_SRS_DAT_05_a_change_creates_a_new_version_and_keeps_the_old_values` | LULUS |
| SRS-DAT-05 | Wajib | `tests/Modules/Ref/ReferenceCodesTest.php::test_SRS_DAT_05_reseeding_unchanged_data_is_a_no_op` | LULUS |
| SRS-DAT-05 | Wajib | `tests/Modules/Ref/ReferenceCodesTest.php::test_SRS_DAT_05_a_code_removed_from_the_source_is_closed_not_deleted` | LULUS |
| SRS-DAT-06 | Wajib | — | tiada ujian |
| SRS-DAT-07 | Wajib | — | tiada ujian |
| SRS-DAT-08 | Wajib | `tests/Modules/Ref/ReferenceDataFilesTest.php::test_SRS_DAT_08_manifest_records_source_licence_date_and_count_for_every_osm_dataset` | LULUS |
| SRS-DAT-08 | Wajib | `tests/Modules/Ref/SampleLotsTest.php::test_SRS_DAT_08_every_staging_row_records_its_lineage_and_load_run` | LULUS |
| SRS-DAT-08 | Wajib | `tests/Modules/Ref/SampleLotsTest.php::test_SRS_DAT_08_rerunning_the_same_seed_replaces_its_rows_with_identical_keys` | LULUS |
| SRS-DAT-09 | Perlu | — | tiada ujian |
| SRS-DMS-01 | Wajib | `tests/Modules/Dms/FilesAndAuditTest.php::test_SRS_DMS_01_single_repository_for_every_module` | LULUS |
| SRS-DMS-02 | Wajib | `tests/Modules/Dms/FilesAndAuditTest.php::test_SRS_DMS_02_pdf_and_images_preview_inline_in_the_browser` | LULUS |
| SRS-DMS-02 | Wajib | `tests/Modules/Dms/UploadValidationTest.php::test_SRS_DMS_02_batch_upload_of_twenty_files_reports_each_file` | LULUS |
| SRS-DMS-02 | Wajib | `tests/Modules/Dms/UploadValidationTest.php::test_SRS_DMS_02_batch_limit_is_enforced` | LULUS |
| SRS-DMS-03 | Wajib | `tests/Modules/Dms/UploadValidationTest.php::test_SRS_DMS_03_default_whitelist_accepts_every_listed_type` | LULUS |
| SRS-DMS-03 | Wajib | `tests/Modules/Dms/UploadValidationTest.php::test_SRS_DMS_03_type_removed_from_whitelist_by_admin_is_rejected` | LULUS |
| SRS-DMS-03 | Wajib | `tests/Modules/Dms/UploadValidationTest.php::test_SRS_DMS_03_category_can_narrow_allowed_types` | LULUS |
| SRS-DMS-03 | Wajib | `tests/Modules/Dms/UploadValidationTest.php::test_SRS_DMS_03_shapefile_zip_must_be_a_complete_shapefile_set` | LULUS |
| SRS-DMS-04 | Wajib | `tests/Modules/Dms/UploadValidationTest.php::test_SRS_DMS_04_renamed_file_is_rejected_by_content_signature` | LULUS |
| SRS-DMS-05 | Wajib | `tests/Modules/Dms/UploadValidationTest.php::test_SRS_DMS_05_category_size_limit_with_clear_message_and_admin_change_without_release` | LULUS |
| SRS-DMS-05 | Wajib | `tests/Modules/Dms/UploadValidationTest.php::test_SRS_DMS_05_spatial_files_get_the_larger_default_limit` | LULUS |
| SRS-DMS-06 | Perlu | `tests/Modules/Dms/UploadValidationTest.php::test_SRS_DMS_06_cumulative_transaction_limit_across_requests` | LULUS |
| SRS-DMS-07 | Wajib | `tests/Modules/Dms/AntivirusTest.php::test_SRS_DMS_07_infected_file_is_not_stored_but_quarantined_encrypted_and_audited` | LULUS |
| SRS-DMS-07 | Wajib | `tests/Modules/Dms/AntivirusTest.php::test_SRS_DMS_07_scan_happens_before_anything_is_persisted_and_clean_scans_are_recorded` | LULUS |
| SRS-DMS-07 | Wajib | `tests/Modules/Dms/AntivirusTest.php::test_SRS_DMS_07_scanner_outage_fails_closed` | LULUS |
| SRS-DMS-07 | Wajib | `tests/Modules/Dms/AntivirusTest.php::test_SRS_DMS_07_admin_lists_and_destroys_quarantined_files` | LULUS |
| SRS-DMS-07 | Wajib | `tests/Modules/Dms/AntivirusTest.php::test_SRS_DMS_07_real_clamd_detects_eicar` | LULUS |
| SRS-DMS-08 | Perlu | `tests/Modules/Dms/AntivirusTest.php::test_SRS_DMS_08_quarantine_notifies_system_administrators_and_the_uploader` | LULUS |
| SRS-DMS-09 | Wajib | `tests/Modules/Dms/UploadValidationTest.php::test_SRS_DMS_09_executables_scripts_macro_documents_and_extensionless_files_are_blocked` | LULUS |
| SRS-DMS-09 | Wajib | `tests/Modules/Dms/UploadValidationTest.php::test_SRS_DMS_09_macro_inside_a_docx_named_file_is_detected` | LULUS |
| SRS-DMS-09 | Wajib | `tests/Modules/Dms/UploadValidationTest.php::test_SRS_DMS_09_archives_nested_more_than_one_level_are_blocked` | LULUS |
| SRS-DMS-10 | Wajib | `tests/Modules/Dms/MetadataAndLinkingTest.php::test_SRS_DMS_10_structured_metadata_is_stored` | LULUS |
| SRS-DMS-10 | Wajib | `tests/Modules/Dms/MetadataAndLinkingTest.php::test_SRS_DMS_10_metadata_update_keeps_extra_fields_and_resets_expiry_reminder` | LULUS |
| SRS-DMS-11 | Wajib | `tests/Modules/Dms/MetadataAndLinkingTest.php::test_SRS_DMS_11_admin_adds_changes_and_deactivates_metadata_fields_without_code` | LULUS |
| SRS-DMS-12 | Wajib | `tests/Modules/Dms/AccessAndSearchTest.php::test_SRS_DMS_12_full_text_search_over_pdf_content_and_metadata_filters` | LULUS |
| SRS-DMS-12 | Wajib | `tests/Modules/Dms/AccessAndSearchTest.php::test_SRS_DMS_12_scanned_pdf_is_ocrd_and_found_by_its_words_but_not_by_unauthorised_users` | LULUS |
| SRS-DMS-12 | Wajib | `tests/Modules/Dms/AccessAndSearchTest.php::test_SRS_DMS_12_office_and_spatial_text_is_extracted` | LULUS |
| SRS-DMS-13 | Wajib | `tests/Modules/Dms/AccessAndSearchTest.php::test_SRS_DMS_13_search_results_and_counts_exclude_documents_out_of_scope` | LULUS |
| SRS-DMS-13 | Wajib | `tests/Modules/Dms/AccessAndSearchTest.php::test_SRS_DMS_13_php_and_sql_access_rules_agree` | LULUS |
| SRS-DMS-14 | Wajib | `tests/Modules/Dms/VersioningTest.php::test_SRS_DMS_14_new_version_keeps_the_previous_one_and_both_are_retrievable` | LULUS |
| SRS-DMS-14 | Wajib | `tests/Modules/Dms/VersioningTest.php::test_SRS_DMS_14_versions_can_be_compared` | LULUS |
| SRS-DMS-14 | Wajib | `tests/Modules/Dms/VersioningTest.php::test_SRS_DMS_14_new_versions_go_through_the_same_checks` | LULUS |
| SRS-DMS-15 | Wajib | `tests/Modules/Dms/LifecycleTest.php::test_SRS_DMS_15_expiry_reminder_goes_to_the_process_owner_once_and_expired_documents_become_tamat` | LULUS |
| SRS-DMS-16 | Wajib | `tests/Modules/Dms/MetadataAndLinkingTest.php::test_SRS_DMS_16_documents_attach_to_any_record_and_list_from_the_record` | LULUS |
| SRS-DMS-17 | Wajib | `tests/Modules/Dms/MetadataAndLinkingTest.php::test_SRS_DMS_17_document_shows_its_parent_with_an_access_checked_deep_link` | LULUS |
| SRS-DMS-18 | Wajib | `tests/Modules/Dms/FilesAndAuditTest.php::test_SRS_DMS_18_api_rejects_users_without_permission` | LULUS |
| SRS-DMS-18 | Wajib | `tests/Modules/Dms/MetadataAndLinkingTest.php::test_SRS_DMS_18_other_modules_use_the_document_store_contract` | LULUS |
| SRS-DMS-18 | Wajib | `tests/Modules/Dms/MetadataAndLinkingTest.php::test_SRS_DMS_18_document_store_rejects_unknown_category_in_malay` | LULUS |
| SRS-DMS-19 | Wajib | `tests/Modules/Dms/AccessAndSearchTest.php::test_SRS_DMS_19_sulit_and_rahsia_need_explicit_permission` | LULUS |
| SRS-DMS-19 | Wajib | `tests/Modules/Dms/AccessAndSearchTest.php::test_SRS_DMS_19_restricted_upload_and_reclassification_need_the_clearance` | LULUS |
| SRS-DMS-20 | Wajib | `tests/Modules/Dms/AntivirusTest.php::test_SRS_DMS_20_documents_are_encrypted_at_rest_with_per_file_keys_and_sha256` | LULUS |
| SRS-DMS-20 | Wajib | `tests/Modules/Dms/AntivirusTest.php::test_SRS_DMS_20_tampering_and_wrong_keys_are_detected` | LULUS |
| SRS-DMS-21 | Wajib | `tests/Modules/Dms/LifecycleTest.php::test_SRS_DMS_21_retention_policy_is_configurable_per_category` | LULUS |
| SRS-DMS-22 | Wajib | `tests/Modules/Dms/FilesAndAuditTest.php::test_SRS_DMS_22_signed_links_are_short_lived_user_bound_and_tamper_proof` | LULUS |
| SRS-DMS-22 | Wajib | `tests/Modules/Dms/LifecycleTest.php::test_SRS_DMS_22_documents_past_active_retention_are_archived_automatically_and_stay_searchable` | LULUS |
| SRS-DMS-23 | Wajib | `tests/Modules/Dms/LifecycleTest.php::test_SRS_DMS_23_archived_documents_cannot_be_deleted_or_disposed_without_two_stage_approval` | LULUS |
| SRS-DMS-23 | Wajib | `tests/Modules/Dms/LifecycleTest.php::test_SRS_DMS_23_approved_disposal_destroys_content_keeps_metadata_and_audits_justification` | LULUS |
| SRS-DMS-23 | Wajib | `tests/Modules/Dms/LifecycleTest.php::test_SRS_DMS_23_requester_cannot_be_the_approver` | LULUS |
| SRS-DMS-23 | Wajib | `tests/Modules/Dms/LifecycleTest.php::test_SRS_DMS_23_disposal_without_workflow_engine_is_refused` | LULUS |
| SRS-DMS-23 | Wajib | `tests/Modules/Dms/LifecycleTest.php::test_SRS_DMS_23_active_documents_are_only_logically_deleted` | LULUS |
| SRS-DMS-24 | Wajib | `tests/Modules/Dms/FilesAndAuditTest.php::test_SRS_DMS_24_upload_download_preview_print_view_archive_are_audited` | LULUS |
| SRS-DMS-25 | Perlu | `tests/Modules/Dms/FilesAndAuditTest.php::test_SRS_DMS_25_quota_usage_is_shown_and_80_percent_alerts_admins` | LULUS |
| SRS-DMS-26 | Baik-ada | `tests/Modules/Dms/FilesAndAuditTest.php::test_SRS_DMS_26_sulit_preview_and_print_carry_a_dynamic_watermark` | LULUS |
| SRS-DMS-26 | Baik-ada | `tests/Modules/Dms/FilesAndAuditTest.php::test_SRS_DMS_26_watermarker_handles_pdfs_the_free_parser_cannot_read` | LULUS |
| SRS-DSH-01 | Wajib | — | tiada ujian |
| SRS-DSH-02 | Wajib | — | tiada ujian |
| SRS-DSH-03 | Wajib | — | tiada ujian |
| SRS-DSH-04 | Wajib | — | tiada ujian |
| SRS-DSH-05 | Wajib | — | tiada ujian |
| SRS-DSH-06 | Wajib | — | tiada ujian |
| SRS-DSH-07 | Wajib | — | tiada ujian |
| SRS-DSH-08 | Wajib | — | tiada ujian |
| SRS-DSH-09 | Wajib | — | tiada ujian |
| SRS-DSH-10 | Perlu | — | tiada ujian |
| SRS-DSH-11 | Wajib | — | tiada ujian |
| SRS-DSH-12 | Wajib | — | tiada ujian |
| SRS-DSH-13 | Wajib | — | tiada ujian |
| SRS-DSH-14 | Wajib | — | tiada ujian |
| SRS-DSH-15 | Perlu | — | tiada ujian |
| SRS-DSH-16 | Perlu | — | tiada ujian |
| SRS-DSH-17 | Wajib | — | tiada ujian |
| SRS-DSH-18 | Wajib | — | tiada ujian |
| SRS-DSH-19 | Wajib | — | tiada ujian |
| SRS-DSH-20 | Wajib | — | tiada ujian |
| SRS-DSH-21 | Wajib | — | tiada ujian |
| SRS-DSH-22 | Wajib | — | tiada ujian |
| SRS-DSH-23 | Wajib | — | tiada ujian |
| SRS-DSH-24 | Wajib | — | tiada ujian |
| SRS-DSH-25 | Wajib | — | tiada ujian |
| SRS-GEN-01 | Wajib | `tests/Modules/Rbc/AccessControlTest.php::test_SRS_GEN_01_every_rbc_and_user_route_authorises_through_the_gate` | LULUS |
| SRS-GEN-01 | Wajib | `tests/Modules/Ref/ReferenceApiTest.php::test_SRS_GEN_01_ref_api_requires_login_and_the_view_permission` | LULUS |
| SRS-GEN-02 | Wajib | — | tiada ujian |
| SRS-GEN-03 | Wajib | — | tiada ujian |
| SRS-GEN-04 | Wajib | — | tiada ujian |
| SRS-GEN-05 | Wajib | — | tiada ujian |
| SRS-GEN-06 | Wajib | — | tiada ujian |
| SRS-GEN-07 | Perlu | — | tiada ujian |
| SRS-GEN-08 | Perlu | — | tiada ujian |
| SRS-GEN-09 | Wajib | — | tiada ujian |
| SRS-GIS-01 | Wajib | — | tiada ujian |
| SRS-GIS-02 | Wajib | — | tiada ujian |
| SRS-GIS-03 | Wajib | — | tiada ujian |
| SRS-GIS-04 | Wajib | — | tiada ujian |
| SRS-GIS-05 | Wajib | — | tiada ujian |
| SRS-GIS-06 | Wajib | — | tiada ujian |
| SRS-GIS-07 | Wajib | — | tiada ujian |
| SRS-GIS-08 | Wajib | — | tiada ujian |
| SRS-GIS-09 | Wajib | — | tiada ujian |
| SRS-GIS-10 | Wajib | — | tiada ujian |
| SRS-GIS-11 | Wajib | — | tiada ujian |
| SRS-GIS-12 | Wajib | — | tiada ujian |
| SRS-GIS-13 | Wajib | — | tiada ujian |
| SRS-GIS-14 | Perlu | — | tiada ujian |
| SRS-GIS-15 | Wajib | — | tiada ujian |
| SRS-GIS-16 | Wajib | — | tiada ujian |
| SRS-GIS-17 | Wajib | — | tiada ujian |
| SRS-GIS-18 | Wajib | — | tiada ujian |
| SRS-GIS-19 | Wajib | — | tiada ujian |
| SRS-GIS-20 | Perlu | — | tiada ujian |
| SRS-GIS-21 | Baik-ada | — | tiada ujian |
| SRS-GIS-22 | Wajib | — | tiada ujian |
| SRS-GIS-23 | Wajib | — | tiada ujian |
| SRS-GIS-24 | Wajib | — | tiada ujian |
| SRS-GIS-25 | Wajib | — | tiada ujian |
| SRS-GIS-26 | Wajib | — | tiada ujian |
| SRS-GIS-27 | Wajib | — | tiada ujian |
| SRS-GIS-28 | Wajib | — | tiada ujian |
| SRS-GIS-29 | Wajib | — | tiada ujian |
| SRS-GIS-30 | Wajib | — | tiada ujian |
| SRS-GIS-31 | Wajib | — | tiada ujian |
| SRS-GIS-32 | Wajib | — | tiada ujian |
| SRS-GIS-33 | Wajib | — | tiada ujian |
| SRS-GIS-34 | Wajib | — | tiada ujian |
| SRS-GIS-35 | Wajib | — | tiada ujian |
| SRS-HKM-01 | Wajib | — | tiada ujian |
| SRS-HKM-02 | Wajib | — | tiada ujian |
| SRS-HKM-03 | Wajib | — | tiada ujian |
| SRS-HKM-04 | Wajib | — | tiada ujian |
| SRS-HKM-05 | Wajib | — | tiada ujian |
| SRS-HKM-06 | Wajib | — | tiada ujian |
| SRS-HKM-07 | Perlu | — | tiada ujian |
| SRS-HKM-08 | Wajib | — | tiada ujian |
| SRS-HKM-09 | Wajib | — | tiada ujian |
| SRS-HKM-10 | Perlu | — | tiada ujian |
| SRS-HKM-11 | Wajib | — | tiada ujian |
| SRS-HKM-12 | Wajib | — | tiada ujian |
| SRS-HKM-13 | Wajib | — | tiada ujian |
| SRS-HKM-14 | Wajib | — | tiada ujian |
| SRS-HKM-15 | Wajib | — | tiada ujian |
| SRS-HKM-16 | Wajib | — | tiada ujian |
| SRS-HKM-17 | Perlu | — | tiada ujian |
| SRS-HKM-18 | Perlu | — | tiada ujian |
| SRS-HKM-19 | Wajib | — | tiada ujian |
| SRS-HKM-20 | Baik-ada | — | tiada ujian |
| SRS-HKM-21 | Perlu | — | tiada ujian |
| SRS-HKM-22 | Wajib | — | tiada ujian |
| SRS-INF-01 | Wajib | — | tiada ujian |
| SRS-INF-02 | Wajib | — | tiada ujian |
| SRS-INF-03 | Wajib | — | tiada ujian |
| SRS-INF-04 | Wajib | — | tiada ujian |
| SRS-INF-05 | Perlu | — | tiada ujian |
| SRS-INF-06 | Wajib | — | tiada ujian |
| SRS-INF-07 | Wajib | — | tiada ujian |
| SRS-INF-08 | Wajib | — | tiada ujian |
| SRS-INF-09 | Wajib | — | tiada ujian |
| SRS-INF-10 | Wajib | — | tiada ujian |
| SRS-INF-11 | Wajib | — | tiada ujian |
| SRS-INF-12 | Wajib | — | tiada ujian |
| SRS-INF-13 | Wajib | — | tiada ujian |
| SRS-INF-14 | Wajib | — | tiada ujian |
| SRS-INF-15 | Wajib | — | tiada ujian |
| SRS-INF-16 | Wajib | — | tiada ujian |
| SRS-INF-17 | Wajib | — | tiada ujian |
| SRS-INF-18 | Wajib | — | tiada ujian |
| SRS-INF-19 | Wajib | — | tiada ujian |
| SRS-INF-20 | Wajib | — | tiada ujian |
| SRS-INF-21 | Wajib | — | tiada ujian |
| SRS-INF-22 | Wajib | — | tiada ujian |
| SRS-INF-23 | Wajib | — | tiada ujian |
| SRS-INF-24 | Wajib | — | tiada ujian |
| SRS-INF-25 | Wajib | — | tiada ujian |
| SRS-INF-26 | Wajib | — | tiada ujian |
| SRS-INF-27 | Wajib | — | tiada ujian |
| SRS-INF-28 | Wajib | — | tiada ujian |
| SRS-INF-29 | Wajib | — | tiada ujian |
| SRS-INF-30 | Wajib | — | tiada ujian |
| SRS-INT-01 | Wajib | — | tiada ujian |
| SRS-INT-02 | Wajib | — | tiada ujian |
| SRS-INT-03 | Wajib | — | tiada ujian |
| SRS-INT-04 | Wajib | — | tiada ujian |
| SRS-INT-05 | Wajib | — | tiada ujian |
| SRS-INT-06 | Wajib | — | tiada ujian |
| SRS-INT-07 | Wajib | — | tiada ujian |
| SRS-INT-08 | Wajib | — | tiada ujian |
| SRS-INT-09 | Wajib | — | tiada ujian |
| SRS-INT-10 | Wajib | — | tiada ujian |
| SRS-INT-11 | Perlu | — | tiada ujian |
| SRS-INT-12 | Wajib | — | tiada ujian |
| SRS-INT-13 | Wajib | — | tiada ujian |
| SRS-INT-14 | Perlu | — | tiada ujian |
| SRS-INT-15 | Wajib | — | tiada ujian |
| SRS-INT-16 | Wajib | — | tiada ujian |
| SRS-INT-17 | Wajib | — | tiada ujian |
| SRS-INT-18 | Wajib | — | tiada ujian |
| SRS-KBA-01 | Wajib | — | tiada ujian |
| SRS-KBA-02 | Wajib | — | tiada ujian |
| SRS-KBA-03 | Wajib | — | tiada ujian |
| SRS-KBA-04 | Wajib | — | tiada ujian |
| SRS-KBA-05 | Wajib | — | tiada ujian |
| SRS-KBA-06 | Wajib | — | tiada ujian |
| SRS-KBA-07 | Wajib | — | tiada ujian |
| SRS-KBA-08 | Wajib | — | tiada ujian |
| SRS-KBA-09 | Wajib | — | tiada ujian |
| SRS-KBA-10 | Perlu | — | tiada ujian |
| SRS-KBA-11 | Wajib | — | tiada ujian |
| SRS-KBA-12 | Wajib | — | tiada ujian |
| SRS-KBA-13 | Wajib | — | tiada ujian |
| SRS-KBA-14 | Wajib | — | tiada ujian |
| SRS-KBA-15 | Wajib | — | tiada ujian |
| SRS-KBA-16 | Wajib | — | tiada ujian |
| SRS-KBA-17 | Perlu | — | tiada ujian |
| SRS-KBA-18 | Perlu | — | tiada ujian |
| SRS-KBA-19 | Wajib | — | tiada ujian |
| SRS-KBA-20 | Wajib | — | tiada ujian |
| SRS-KBA-21 | Perlu | — | tiada ujian |
| SRS-KBA-22 | Wajib | — | tiada ujian |
| SRS-KES-01 | Wajib | — | tiada ujian |
| SRS-KES-02 | Wajib | — | tiada ujian |
| SRS-KES-03 | Wajib | — | tiada ujian |
| SRS-KES-04 | Wajib | — | tiada ujian |
| SRS-KES-05 | Wajib | — | tiada ujian |
| SRS-KES-06 | Perlu | — | tiada ujian |
| SRS-KES-07 | Wajib | — | tiada ujian |
| SRS-KES-08 | Wajib | — | tiada ujian |
| SRS-KES-09 | Perlu | — | tiada ujian |
| SRS-KES-10 | Wajib | — | tiada ujian |
| SRS-KES-11 | Perlu | — | tiada ujian |
| SRS-KES-12 | Wajib | — | tiada ujian |
| SRS-KES-13 | Wajib | — | tiada ujian |
| SRS-KES-14 | Wajib | — | tiada ujian |
| SRS-KES-15 | Wajib | — | tiada ujian |
| SRS-KES-16 | Wajib | — | tiada ujian |
| SRS-KES-17 | Wajib | — | tiada ujian |
| SRS-KES-18 | Wajib | — | tiada ujian |
| SRS-KES-19 | Perlu | — | tiada ujian |
| SRS-KES-20 | Wajib | — | tiada ujian |
| SRS-KES-21 | Wajib | — | tiada ujian |
| SRS-KES-22 | Wajib | — | tiada ujian |
| SRS-KES-23 | Wajib | — | tiada ujian |
| SRS-KES-24 | Perlu | — | tiada ujian |
| SRS-LAP-01 | Wajib | — | tiada ujian |
| SRS-LAP-02 | Wajib | — | tiada ujian |
| SRS-LAP-03 | Wajib | — | tiada ujian |
| SRS-LAP-04 | Wajib | — | tiada ujian |
| SRS-LAP-05 | Perlu | — | tiada ujian |
| SRS-LAP-06 | Wajib | — | tiada ujian |
| SRS-LAP-07 | Wajib | — | tiada ujian |
| SRS-LAP-08 | Wajib | — | tiada ujian |
| SRS-LAP-09 | Wajib | — | tiada ujian |
| SRS-LAP-10 | Wajib | — | tiada ujian |
| SRS-LAP-11 | Wajib | — | tiada ujian |
| SRS-LAP-12 | Wajib | — | tiada ujian |
| SRS-LAP-13 | Perlu | — | tiada ujian |
| SRS-LAP-14 | Perlu | — | tiada ujian |
| SRS-LAP-15 | Perlu | — | tiada ujian |
| SRS-LAP-16 | Perlu | — | tiada ujian |
| SRS-LAP-17 | Perlu | — | tiada ujian |
| SRS-LAP-18 | Perlu | — | tiada ujian |
| SRS-LAP-19 | Perlu | — | tiada ujian |
| SRS-LAP-20 | Perlu | — | tiada ujian |
| SRS-LAP-21 | Wajib | — | tiada ujian |
| SRS-LAP-22 | Perlu | — | tiada ujian |
| SRS-LAP-23 | Baik-ada | — | tiada ujian |
| SRS-NFR-01 | 99.5% setiap bulan dalam waktu operasi, tidak termasuk tetingkap penyelenggaraan berjadual yang dimaklumkan 5 hari bekerja lebih awal | — | tiada ujian |
| SRS-NFR-02 | ≤ 3 saat bagi 95% permintaan di bawah beban biasa | — | tiada ujian |
| SRS-NFR-03 | ≤ 1.5 saat bagi 95% panggilan dalaman | — | tiada ujian |
| SRS-NFR-04 | 200 pengguna aktif serentak tanpa penurunan melebihi 20% daripada sasaran masa tindak balas | — | tiada ujian |
| SRS-NFR-05 | ≤ 5 saat bagi carian merentas keseluruhan portfolio lot dengan tapisan gabungan | — | tiada ujian |
| SRS-NFR-06 | Fail 25 MB selesai dimuat naik dan diindeks dalam ≤ 30 saat | — | tiada ujian |
| SRS-NFR-07 | Laporan piawai ≤ 10 saat; laporan berskala besar dijana di latar belakang dengan notifikasi apabila siap, tanpa menjejaskan prestasi transaksi | — | tiada ujian |
| SRS-NFR-08 | Lapisan asas dengan satu lapisan tematik bagi kawasan satu daerah dipaparkan dalam ≤ 5 saat; jubin dicache | — | tiada ujian |
| SRS-NFR-09 | Data transaksi dipaparkan tidak melebihi 15 minit ketinggalan, dengan penunjuk masa kemas kini terakhir | — | tiada ujian |
| SRS-NFR-10 | Sistem hendaklah mengekalkan sasaran prestasi di atas sehingga tiga kali ganda isi padu data permulaan tanpa perubahan seni bina | — | tiada ujian |
| SRS-NFR-11 | Wajib | — | tiada ujian |
| SRS-NFR-12 | Wajib | — | tiada ujian |
| SRS-NFR-13 | Wajib | — | tiada ujian |
| SRS-NFR-14 | Wajib | — | tiada ujian |
| SRS-NFR-15 | Wajib | — | tiada ujian |
| SRS-NFR-16 | Wajib | — | tiada ujian |
| SRS-NFR-17 | Wajib | — | tiada ujian |
| SRS-NFR-18 | Wajib | — | tiada ujian |
| SRS-NFR-19 | Wajib | — | tiada ujian |
| SRS-NFR-20 | Perlu | — | tiada ujian |
| SRS-NFR-21 | Perlu | — | tiada ujian |
| SRS-NFR-22 | Wajib | — | tiada ujian |
| SRS-NFR-23 | Wajib | — | tiada ujian |
| SRS-NFR-24 | Wajib | — | tiada ujian |
| SRS-NFR-25 | Wajib | — | tiada ujian |
| SRS-NFR-26 | Wajib | — | tiada ujian |
| SRS-NTF-01 | Wajib | `tests/Modules/Ntf/CommandTest.php::test_SRS_NTF_01_ntf_test_command_sends_a_notification` | LULUS |
| SRS-NTF-01 | Wajib | `tests/Modules/Ntf/EngineTest.php::test_SRS_NTF_01_one_send_delivers_in_app_and_email` | LULUS |
| SRS-NTF-02 | Wajib | `tests/Modules/Ntf/EngineTest.php::test_SRS_NTF_02_single_engine_is_bound_to_the_contracts` | LULUS |
| SRS-NTF-02 | Wajib | `tests/Modules/Ntf/EngineTest.php::test_SRS_NTF_02_unknown_type_is_rejected` | LULUS |
| SRS-NTF-02 | Wajib | `tests/Modules/Ntf/EngineTest.php::test_SRS_NTF_02_inactive_type_and_inactive_users_are_skipped` | LULUS |
| SRS-NTF-03 | Wajib | `tests/Modules/Ntf/AdminApiTest.php::test_SRS_NTF_03_permissions_are_declared_to_rbc` | LULUS |
| SRS-NTF-03 | Wajib | `tests/Modules/Ntf/AdminApiTest.php::test_SRS_NTF_03_admin_configures_channels_priority_frequency_and_recipients_without_code` | LULUS |
| SRS-NTF-03 | Wajib | `tests/Modules/Ntf/AdminApiTest.php::test_SRS_NTF_03_configuration_requires_ntf_permissions` | LULUS |
| SRS-NTF-04 | Wajib | `tests/Modules/Ntf/EngineTest.php::test_SRS_NTF_04_sms_and_whatsapp_go_through_adapter_interfaces` | LULUS |
| SRS-NTF-05 | Wajib | `tests/Modules/Ntf/AdminApiTest.php::test_SRS_NTF_05_template_edit_creates_a_new_active_version_used_by_next_notification` | LULUS |
| SRS-NTF-05 | Wajib | `tests/Modules/Ntf/EngineTest.php::test_SRS_NTF_05_dynamic_fields_are_filled_in_malay_template` | LULUS |
| SRS-NTF-06 | Wajib | `tests/Modules/Ntf/EngineTest.php::test_SRS_NTF_06_event_based_triggers` | LULUS |
| SRS-NTF-06 | Wajib | `tests/Modules/Ntf/SchedulerTest.php::test_SRS_NTF_06_scheduler_registers_the_ntf_jobs` | LULUS |
| SRS-NTF-07 | Wajib | `tests/Modules/Ntf/SchedulerTest.php::test_SRS_NTF_07_scheduled_reminder_runs_on_configured_days_through_both_channels` | LULUS |
| SRS-NTF-07 | Wajib | `tests/Modules/Ntf/SchedulerTest.php::test_SRS_NTF_07_admin_changes_reminder_days_and_template_without_release` | LULUS |
| SRS-NTF-08 | Wajib | `tests/Modules/Ntf/AdminApiTest.php::test_SRS_NTF_08_type_priorities_are_validated` | LULUS |
| SRS-NTF-08 | Wajib | `tests/Modules/Ntf/EngineTest.php::test_SRS_NTF_08_priority_is_stored_and_orders_the_list` | LULUS |
| SRS-NTF-09 | Wajib | `tests/Modules/Ntf/PreferenceApiTest.php::test_SRS_NTF_09_user_chooses_channels_or_opts_out_of_optional_types` | LULUS |
| SRS-NTF-10 | Wajib | `tests/Modules/Ntf/PreferenceApiTest.php::test_SRS_NTF_10_mandatory_types_cannot_be_turned_off_and_are_shown_locked` | LULUS |
| SRS-NTF-11 | Wajib | `tests/Modules/Ntf/InboxApiTest.php::test_SRS_NTF_11_mark_read_and_unread_individually_and_in_bulk_and_filter` | LULUS |
| SRS-NTF-11 | Wajib | `tests/Modules/Ntf/InboxApiTest.php::test_SRS_NTF_11_users_only_see_and_change_their_own_notifications` | LULUS |
| SRS-NTF-11 | Wajib | `tests/Modules/Ntf/InboxApiTest.php::test_SRS_NTF_11_bell_summary_returns_unread_count_and_latest` | LULUS |
| SRS-NTF-12 | Wajib | `tests/Modules/Ntf/InboxApiTest.php::test_SRS_NTF_12_logical_delete_hides_item_but_keeps_delivery_log` | LULUS |
| SRS-NTF-13 | Wajib | `tests/Modules/Ntf/InboxApiTest.php::test_SRS_NTF_13_archive_folder_is_kept_and_searchable` | LULUS |
| SRS-NTF-14 | Perlu | `tests/Modules/Ntf/InboxApiTest.php::test_SRS_NTF_14_filter_by_type_priority_module_read_status_and_date` | LULUS |
| SRS-NTF-15 | Wajib | `tests/Modules/Ntf/AdminApiTest.php::test_SRS_NTF_15_delivery_log_for_auditors_shows_channel_times_status_and_error` | LULUS |
| SRS-NTF-15 | Wajib | `tests/Modules/Ntf/EngineTest.php::test_SRS_NTF_15_every_delivery_is_audited` | LULUS |
| SRS-NTF-16 | Wajib | `tests/Modules/Ntf/EngineTest.php::test_SRS_NTF_16_hantar_terima_baca_are_timestamped` | LULUS |
| SRS-NTF-17 | Perlu | `tests/Modules/Ntf/DeliveryRetryTest.php::test_SRS_NTF_17_job_retries_at_least_three_times_with_increasing_backoff` | LULUS |
| SRS-NTF-17 | Perlu | `tests/Modules/Ntf/DeliveryRetryTest.php::test_SRS_NTF_17_failed_email_is_retried_then_marked_final_and_admin_alerted` | LULUS |
| SRS-NTF-17 | Perlu | `tests/Modules/Ntf/DeliveryRetryTest.php::test_SRS_NTF_17_queue_failed_hook_marks_final` | LULUS |
| SRS-NTF-17 | Perlu | `tests/Modules/Ntf/DeliveryRetryTest.php::test_SRS_NTF_17_alert_failures_never_alert_again` | LULUS |
| SRS-NTF-18 | Wajib | `tests/Modules/Ntf/SchedulerTest.php::test_SRS_NTF_18_unread_kritikal_escalates_to_supervisor_once_and_is_logged` | LULUS |
| SRS-NTF-18 | Wajib | `tests/Modules/Ntf/SchedulerTest.php::test_SRS_NTF_18_supervisor_resolution_is_a_replaceable_contract` | LULUS |
| SRS-NTF-18 | Wajib | `tests/Modules/Ntf/SchedulerTest.php::test_SRS_NTF_18_fallback_supervisor_role_is_an_adm_parameter` | LULUS |
| SRS-NTF-19 | Wajib | `tests/Modules/Ntf/EngineTest.php::test_SRS_NTF_19_sensitive_fields_are_refused` | LULUS |
| SRS-NTF-19 | Wajib | `tests/Modules/Ntf/EngineTest.php::test_SRS_NTF_19_only_summary_and_internal_link_are_kept` | LULUS |
| SRS-NTF-20 | Perlu | `tests/Modules/Ntf/SchedulerTest.php::test_SRS_NTF_20_biasa_and_maklumat_bundled_into_one_digest` | LULUS |
| SRS-NTF-20 | Perlu | `tests/Modules/Ntf/SchedulerTest.php::test_SRS_NTF_20_kritikal_never_waits_for_a_digest` | LULUS |
| SRS-NTF-21 | Perlu | `tests/Modules/Ntf/EngineTest.php::test_SRS_NTF_21_delivery_goes_through_a_persistent_queue` | LULUS |
| SRS-NTF-22 | Perlu | `tests/Modules/Ntf/EngineTest.php::test_SRS_NTF_22_same_event_to_same_recipient_is_sent_once_in_quiet_period` | LULUS |
| SRS-NTF-23 | Perlu | `tests/Modules/Ntf/AdminApiTest.php::test_SRS_NTF_23_monitoring_dashboard_shows_queue_success_rate_and_recent_failures` | LULUS |
| SRS-NTF-23 | Perlu | `tests/Modules/Ntf/DeliveryRetryTest.php::test_SRS_NTF_23_admin_can_retry_a_failed_delivery` | LULUS |
| SRS-NTF-24 | Baik-ada | `tests/Modules/Ntf/AnnouncementApiTest.php::test_SRS_NTF_24_admin_announces_to_groups_by_role_bahagian_and_daerah` | LULUS |
| SRS-NTF-24 | Baik-ada | `tests/Modules/Ntf/AnnouncementApiTest.php::test_SRS_NTF_24_validation_empty_group_and_rate_limit` | LULUS |
| SRS-PER-01 | Wajib | — | tiada ujian |
| SRS-PER-02 | Wajib | — | tiada ujian |
| SRS-PER-03 | Wajib | — | tiada ujian |
| SRS-PER-04 | Wajib | — | tiada ujian |
| SRS-PER-05 | Wajib | — | tiada ujian |
| SRS-PER-06 | Wajib | — | tiada ujian |
| SRS-PER-07 | Wajib | — | tiada ujian |
| SRS-PER-08 | Wajib | — | tiada ujian |
| SRS-PER-09 | Wajib | — | tiada ujian |
| SRS-PER-10 | Wajib | — | tiada ujian |
| SRS-PER-11 | Wajib | — | tiada ujian |
| SRS-PER-12 | Wajib | — | tiada ujian |
| SRS-PER-13 | Wajib | — | tiada ujian |
| SRS-PER-14 | Wajib | — | tiada ujian |
| SRS-PER-15 | Wajib | — | tiada ujian |
| SRS-PER-16 | Wajib | — | tiada ujian |
| SRS-PER-17 | Wajib | — | tiada ujian |
| SRS-PER-18 | Wajib | — | tiada ujian |
| SRS-PER-19 | Perlu | — | tiada ujian |
| SRS-PER-20 | Wajib | — | tiada ujian |
| SRS-PER-21 | Wajib | — | tiada ujian |
| SRS-PER-22 | Wajib | — | tiada ujian |
| SRS-PER-23 | Wajib | — | tiada ujian |
| SRS-PER-24 | Perlu | — | tiada ujian |
| SRS-PER-25 | Perlu | — | tiada ujian |
| SRS-PER-26 | Perlu | — | tiada ujian |
| SRS-PER-27 | Wajib | — | tiada ujian |
| SRS-PER-28 | Perlu | — | tiada ujian |
| SRS-PER-29 | Wajib | — | tiada ujian |
| SRS-PER-30 | Wajib | — | tiada ujian |
| SRS-PJK-01 | Wajib | — | tiada ujian |
| SRS-PJK-02 | Wajib | — | tiada ujian |
| SRS-PJK-03 | Wajib | — | tiada ujian |
| SRS-PJK-04 | Wajib | — | tiada ujian |
| SRS-PJK-05 | Wajib | — | tiada ujian |
| SRS-PJK-06 | Wajib | — | tiada ujian |
| SRS-PJK-07 | Perlu | — | tiada ujian |
| SRS-PJK-08 | Wajib | — | tiada ujian |
| SRS-PJK-09 | Wajib | — | tiada ujian |
| SRS-PJK-10 | Wajib | — | tiada ujian |
| SRS-PJK-11 | Wajib | — | tiada ujian |
| SRS-PJK-12 | Wajib | — | tiada ujian |
| SRS-PJK-13 | Wajib | — | tiada ujian |
| SRS-PJK-14 | Wajib | — | tiada ujian |
| SRS-PJK-15 | Wajib | — | tiada ujian |
| SRS-PJK-16 | Wajib | — | tiada ujian |
| SRS-PJK-17 | Wajib | — | tiada ujian |
| SRS-PJK-18 | Wajib | — | tiada ujian |
| SRS-PJK-19 | Wajib | — | tiada ujian |
| SRS-PJK-20 | Perlu | — | tiada ujian |
| SRS-PJK-21 | Wajib | — | tiada ujian |
| SRS-PJK-22 | Wajib | — | tiada ujian |
| SRS-PJK-23 | Wajib | — | tiada ujian |
| SRS-PJK-24 | Wajib | — | tiada ujian |
| SRS-PJK-25 | Perlu | — | tiada ujian |
| SRS-PJK-26 | Wajib | — | tiada ujian |
| SRS-PJK-27 | Wajib | — | tiada ujian |
| SRS-PJK-28 | Wajib | — | tiada ujian |
| SRS-PRO-01 | Wajib | — | tiada ujian |
| SRS-PRO-02 | Wajib | — | tiada ujian |
| SRS-PRO-03 | Wajib | — | tiada ujian |
| SRS-PRO-04 | Wajib | — | tiada ujian |
| SRS-PRO-05 | Wajib | — | tiada ujian |
| SRS-PRO-06 | Wajib | — | tiada ujian |
| SRS-PRO-07 | Wajib | — | tiada ujian |
| SRS-PRO-08 | Wajib | — | tiada ujian |
| SRS-PRO-09 | Wajib | — | tiada ujian |
| SRS-PRO-10 | Wajib | — | tiada ujian |
| SRS-PRO-11 | Wajib | — | tiada ujian |
| SRS-PRO-12 | Wajib | — | tiada ujian |
| SRS-PRO-13 | Wajib | — | tiada ujian |
| SRS-PRO-14 | Wajib | — | tiada ujian |
| SRS-PRO-15 | Wajib | — | tiada ujian |
| SRS-PRO-16 | Wajib | — | tiada ujian |
| SRS-PRO-17 | Wajib | — | tiada ujian |
| SRS-PRO-18 | Wajib | — | tiada ujian |
| SRS-PRO-19 | Wajib | — | tiada ujian |
| SRS-PRO-20 | Wajib | — | tiada ujian |
| SRS-PRO-21 | Wajib | — | tiada ujian |
| SRS-PRO-22 | Wajib | — | tiada ujian |
| SRS-PRO-23 | Wajib | — | tiada ujian |
| SRS-PRO-24 | Wajib | — | tiada ujian |
| SRS-PRO-25 | Wajib | — | tiada ujian |
| SRS-PRO-26 | Wajib | — | tiada ujian |
| SRS-PRO-27 | Perlu | — | tiada ujian |
| SRS-PRO-28 | Perlu | — | tiada ujian |
| SRS-PRO-29 | Perlu | — | tiada ujian |
| SRS-PRO-30 | Perlu | — | tiada ujian |
| SRS-PTN-01 | Wajib | — | tiada ujian |
| SRS-PTN-02 | Perlu | — | tiada ujian |
| SRS-PTN-03 | Wajib | — | tiada ujian |
| SRS-PTN-04 | Wajib | — | tiada ujian |
| SRS-PTN-05 | Wajib | — | tiada ujian |
| SRS-PTN-06 | Wajib | — | tiada ujian |
| SRS-PTN-07 | Wajib | — | tiada ujian |
| SRS-PTN-08 | Wajib | — | tiada ujian |
| SRS-PTN-09 | Wajib | — | tiada ujian |
| SRS-PTN-10 | Wajib | — | tiada ujian |
| SRS-PTN-11 | Wajib | — | tiada ujian |
| SRS-PTN-12 | Wajib | — | tiada ujian |
| SRS-PTN-13 | Wajib | — | tiada ujian |
| SRS-PTN-14 | Wajib | — | tiada ujian |
| SRS-PTN-15 | Wajib | — | tiada ujian |
| SRS-PTN-16 | Wajib | — | tiada ujian |
| SRS-PTN-17 | Wajib | — | tiada ujian |
| SRS-PTN-18 | Perlu | — | tiada ujian |
| SRS-PTN-19 | Wajib | — | tiada ujian |
| SRS-PTN-20 | Perlu | — | tiada ujian |
| SRS-PTN-21 | Perlu | — | tiada ujian |
| SRS-PTN-22 | Wajib | — | tiada ujian |
| SRS-PTN-23 | Perlu | — | tiada ujian |
| SRS-PTN-24 | Wajib | — | tiada ujian |
| SRS-PWS-01 | Wajib | — | tiada ujian |
| SRS-PWS-02 | Wajib | — | tiada ujian |
| SRS-PWS-03 | Wajib | — | tiada ujian |
| SRS-PWS-04 | Perlu | — | tiada ujian |
| SRS-PWS-05 | Wajib | — | tiada ujian |
| SRS-PWS-06 | Wajib | — | tiada ujian |
| SRS-PWS-07 | Wajib | — | tiada ujian |
| SRS-PWS-08 | Wajib | — | tiada ujian |
| SRS-PWS-09 | Wajib | — | tiada ujian |
| SRS-PWS-10 | Wajib | — | tiada ujian |
| SRS-PWS-11 | Wajib | — | tiada ujian |
| SRS-PWS-12 | Wajib | — | tiada ujian |
| SRS-PWS-13 | Wajib | — | tiada ujian |
| SRS-PWS-14 | Wajib | — | tiada ujian |
| SRS-PWS-15 | Wajib | — | tiada ujian |
| SRS-PWS-16 | Wajib | — | tiada ujian |
| SRS-PWS-17 | Wajib | — | tiada ujian |
| SRS-PWS-18 | Wajib | — | tiada ujian |
| SRS-PWS-19 | Perlu | — | tiada ujian |
| SRS-PWS-20 | Wajib | — | tiada ujian |
| SRS-PWS-21 | Wajib | — | tiada ujian |
| SRS-PWS-22 | Wajib | — | tiada ujian |
| SRS-PWS-23 | Wajib | — | tiada ujian |
| SRS-PWS-24 | Wajib | — | tiada ujian |
| SRS-PWS-25 | Wajib | — | tiada ujian |
| SRS-PWS-26 | Perlu | — | tiada ujian |
| SRS-PWS-27 | Perlu | — | tiada ujian |
| SRS-PWS-28 | Wajib | — | tiada ujian |
| SRS-RBC-01 | Wajib | `tests/Feature/Auth/OidcLoginTest.php::test_SRS_RBC_01_oidc_redirect_sends_user_to_mais_realm_authorize_endpoint` | LULUS |
| SRS-RBC-01 | Wajib | `tests/Feature/Auth/OidcLoginTest.php::test_SRS_RBC_01_oidc_callback_logs_in_existing_user` | LULUS |
| SRS-RBC-01 | Wajib | `tests/Feature/Auth/OidcLoginTest.php::test_SRS_RBC_01_oidc_callback_matches_user_by_sub_before_email` | LULUS |
| SRS-RBC-01 | Wajib | `tests/Feature/Auth/OidcLoginTest.php::test_SRS_RBC_01_oidc_callback_creates_user_on_first_login` | LULUS |
| SRS-RBC-01 | Wajib | `tests/Feature/Auth/OidcLoginTest.php::test_SRS_RBC_01_oidc_callback_rejects_email_linked_to_another_subject` | LULUS |
| SRS-RBC-01 | Wajib | `tests/Feature/Auth/OidcLoginTest.php::test_SRS_RBC_01_oidc_callback_rejects_inactive_user` | LULUS |
| SRS-RBC-01 | Wajib | `tests/Feature/Auth/OidcLoginTest.php::test_SRS_RBC_01_oidc_callback_with_idp_error_redirects_to_login` | LULUS |
| SRS-RBC-01 | Wajib | `tests/Feature/Auth/OidcLoginTest.php::test_SRS_RBC_01_oidc_logout_returns_idp_end_session_url` | LULUS |
| SRS-RBC-01 | Wajib | `tests/Feature/Auth/OidcLoginTest.php::test_SRS_RBC_01_password_user_logout_still_returns_204` | LULUS |
| SRS-RBC-01 | Wajib | `tests/Feature/Auth/OidcLoginTest.php::test_SRS_RBC_01_password_login_disabled_returns_403` | LULUS |
| SRS-RBC-01 | Wajib | `tests/Feature/Auth/OidcLoginTest.php::test_SRS_RBC_01_password_registration_disabled_returns_403` | LULUS |
| SRS-RBC-01 | Wajib | `tests/Feature/Auth/OidcLoginTest.php::test_SRS_RBC_01_settings_expose_password_login_flag` | LULUS |
| SRS-RBC-01 | Wajib | `tests/Modules/Rbc/SsoMappingTest.php::test_SRS_RBC_01_sso_login_is_logged_and_opens_a_server_session` | LULUS |
| SRS-RBC-02 | Wajib | `tests/Modules/Rbc/SsoMappingTest.php::test_SRS_RBC_02_claims_map_to_roles_and_scopes_on_sso_login` | LULUS |
| SRS-RBC-02 | Wajib | `tests/Modules/Rbc/SsoMappingTest.php::test_SRS_RBC_02_sso_roles_are_resynced_each_login_and_manual_roles_kept` | LULUS |
| SRS-RBC-02 | Wajib | `tests/Modules/Rbc/SsoMappingTest.php::test_SRS_RBC_02_admin_manages_mapping_rules_and_previews_them` | LULUS |
| SRS-RBC-02 | Wajib | `tests/Modules/Rbc/SsoMappingTest.php::test_SRS_RBC_02_realm_sends_bahagian_unit_and_jawatan_claims` | LULUS |
| SRS-RBC-03 | Wajib | `tests/Feature/Auth/OidcLoginTest.php::test_SRS_RBC_03_mock_idp_realm_configures_totp_mfa_and_role_claims` | LULUS |
| SRS-RBC-04 | Wajib | `tests/Modules/Rbc/AccessControlTest.php::test_SRS_RBC_04_permission_is_enforced_on_the_api` | LULUS |
| SRS-RBC-04 | Wajib | `tests/Modules/Rbc/AccessControlTest.php::test_SRS_RBC_04_pengguna_paparan_cannot_modify_data_through_any_endpoint` | LULUS |
| SRS-RBC-04 | Wajib | `tests/Modules/Rbc/DirectoryTest.php::test_SRS_RBC_04_rbc_binds_the_directories_other_modules_defined` | LULUS |
| SRS-RBC-04 | Wajib | `tests/Modules/Rbc/DirectoryTest.php::test_SRS_RBC_04_recipients_by_role_permission_scope_and_user` | LULUS |
| SRS-RBC-04 | Wajib | `tests/Modules/Rbc/DirectoryTest.php::test_SRS_RBC_04_inactive_roles_grant_no_membership` | LULUS |
| SRS-RBC-04 | Wajib | `tests/Modules/Rbc/DirectoryTest.php::test_SRS_RBC_04_approvers_by_role_and_jawatan_with_fallback_supervisors` | LULUS |
| SRS-RBC-04 | Wajib | `tests/Modules/Rbc/RoleManagementTest.php::test_SRS_RBC_04_user_roles_and_scopes_are_assigned_and_reported` | LULUS |
| SRS-RBC-04 | Wajib | `tests/Modules/Rbc/RoleManagementTest.php::test_SRS_RBC_04_user_list_filters_by_status_and_role` | LULUS |
| SRS-RBC-05 | Wajib | `tests/Modules/Rbc/AccessControlTest.php::test_SRS_RBC_05_seven_base_roles_exist_as_initial_configuration` | LULUS |
| SRS-RBC-05 | Wajib | `tests/Modules/Rbc/AccessControlTest.php::test_SRS_RBC_05_base_role_scopes_follow_rfp_11_2_3` | LULUS |
| SRS-RBC-05 | Wajib | `tests/Modules/Rbc/AccessControlTest.php::test_SRS_RBC_05_a_newly_registered_module_gets_base_role_defaults_once` | LULUS |
| SRS-RBC-05 | Wajib | `tests/Modules/Rbc/AccessControlTest.php::test_SRS_RBC_05_legacy_admin_account_is_pentadbir_sistem` | LULUS |
| SRS-RBC-06 | Wajib | `tests/Modules/Rbc/AccessControlTest.php::test_SRS_RBC_06_new_scoped_role_takes_effect_immediately` | LULUS |
| SRS-RBC-06 | Wajib | `tests/Modules/Rbc/RoleManagementTest.php::test_SRS_RBC_06_admin_creates_and_edits_a_role_with_permissions_and_scopes` | LULUS |
| SRS-RBC-06 | Wajib | `tests/Modules/Rbc/RoleManagementTest.php::test_SRS_RBC_06_unknown_permissions_and_scope_types_are_rejected` | LULUS |
| SRS-RBC-06 | Wajib | `tests/Modules/Rbc/RoleManagementTest.php::test_SRS_RBC_06_deactivated_role_grants_nothing_until_reactivated` | LULUS |
| SRS-RBC-06 | Wajib | `tests/Modules/Rbc/RoleManagementTest.php::test_SRS_RBC_06_role_is_cloned_with_its_permissions_and_scopes` | LULUS |
| SRS-RBC-06 | Wajib | `tests/Modules/Rbc/RoleManagementTest.php::test_SRS_RBC_06_base_roles_cannot_be_deleted_and_used_roles_are_protected` | LULUS |
| SRS-RBC-06 | Wajib | `tests/Modules/Rbc/RoleManagementTest.php::test_SRS_RBC_06_role_management_requires_permission` | LULUS |
| SRS-RBC-06 | Wajib | `tests/Modules/Rbc/RoleManagementTest.php::test_SRS_RBC_06_admins_cannot_grant_permissions_they_do_not_hold` | LULUS |
| SRS-RBC-07 | Wajib | `tests/Modules/Rbc/AccessControlTest.php::test_SRS_RBC_07_officer_of_district_a_cannot_see_district_b` | LULUS |
| SRS-RBC-07 | Wajib | `tests/Modules/Rbc/AccessControlTest.php::test_SRS_RBC_07_scopes_combine_across_dimensions` | LULUS |
| SRS-RBC-07 | Wajib | `tests/Modules/Rbc/AccessControlTest.php::test_SRS_RBC_07_role_scope_and_user_scope_intersect` | LULUS |
| SRS-RBC-07 | Wajib | `tests/Modules/Rbc/AccessControlTest.php::test_SRS_RBC_07_unrestricted_roles_and_console_jobs_see_everything` | LULUS |
| SRS-RBC-08 | Wajib | `tests/Modules/Rbc/AccessControlTest.php::test_SRS_RBC_08_gate_and_can_middleware_delegate_to_access_control` | LULUS |
| SRS-RBC-08 | Wajib | `tests/Modules/Rbc/AccessControlTest.php::test_SRS_RBC_08_denied_requests_dispatch_access_denied` | LULUS |
| SRS-RBC-08 | Wajib | `tests/Modules/Rbc/AccessControlTest.php::test_SRS_RBC_08_admin_alias_middleware_is_permission_based` | LULUS |
| SRS-RBC-09 | Perlu | `tests/Modules/Rbc/AccessControlTest.php::test_SRS_RBC_09_new_role_starts_with_no_permissions` | LULUS |
| SRS-RBC-09 | Perlu | `tests/Modules/Rbc/AccessControlTest.php::test_SRS_RBC_09_user_without_roles_has_no_access` | LULUS |
| SRS-RBC-10 | Wajib | `tests/Modules/Rbc/AccessReportTest.php::test_SRS_RBC_10_matrix_shows_roles_permissions_and_scopes` | LULUS |
| SRS-RBC-10 | Wajib | `tests/Modules/Rbc/AccessReportTest.php::test_SRS_RBC_10_matrix_excel_export_matches_configuration` | LULUS |
| SRS-RBC-10 | Wajib | `tests/Modules/Rbc/AccessReportTest.php::test_SRS_RBC_10_matrix_pdf_export_matches_configuration` | LULUS |
| SRS-RBC-10 | Wajib | `tests/Modules/Rbc/AccessReportTest.php::test_SRS_RBC_10_matrix_export_requires_permission` | LULUS |
| SRS-RBC-11 | Wajib | `tests/Modules/Rbc/PasswordAndLockoutTest.php::test_SRS_RBC_11_sso_accounts_keep_no_password_and_cannot_use_password_login` | LULUS |
| SRS-RBC-11 | Wajib | `tests/Modules/Rbc/PasswordAndLockoutTest.php::test_SRS_RBC_11_admin_creates_sso_account_without_password` | LULUS |
| SRS-RBC-11 | Wajib | `tests/Modules/Rbc/SsoMappingTest.php::test_SRS_RBC_11_sso_provisioned_user_has_no_password` | LULUS |
| SRS-RBC-12 | Wajib | `tests/Modules/Rbc/PasswordAndLockoutTest.php::test_SRS_RBC_12_weak_passwords_are_rejected_on_registration` | LULUS |
| SRS-RBC-12 | Wajib | `tests/Modules/Rbc/PasswordAndLockoutTest.php::test_SRS_RBC_12_policy_accepts_12_chars_with_three_classes` | LULUS |
| SRS-RBC-12 | Wajib | `tests/Modules/Rbc/PasswordAndLockoutTest.php::test_SRS_RBC_12_admin_rejects_weak_password_when_creating_user` | LULUS |
| SRS-RBC-12 | Wajib | `tests/Modules/Rbc/PasswordAndLockoutTest.php::test_SRS_RBC_12_admin_set_password_must_be_changed_at_first_login` | LULUS |
| SRS-RBC-12 | Wajib | `tests/Modules/Rbc/PasswordAndLockoutTest.php::test_SRS_RBC_12_password_expires_after_90_days` | LULUS |
| SRS-RBC-12 | Wajib | `tests/Modules/Rbc/PasswordAndLockoutTest.php::test_SRS_RBC_12_password_not_expired_at_89_days` | LULUS |
| SRS-RBC-12 | Wajib | `tests/Modules/Rbc/PasswordAndLockoutTest.php::test_SRS_RBC_12_last_five_passwords_cannot_be_reused` | LULUS |
| SRS-RBC-12 | Wajib | `tests/Modules/Rbc/PasswordAndLockoutTest.php::test_SRS_RBC_12_change_password_requires_current_password` | LULUS |
| SRS-RBC-13 | Wajib | `tests/Modules/Rbc/PasswordAndLockoutTest.php::test_SRS_RBC_13_account_locks_after_five_consecutive_failures` | LULUS |
| SRS-RBC-13 | Wajib | `tests/Modules/Rbc/PasswordAndLockoutTest.php::test_SRS_RBC_13_failures_outside_the_window_do_not_accumulate` | LULUS |
| SRS-RBC-13 | Wajib | `tests/Modules/Rbc/PasswordAndLockoutTest.php::test_SRS_RBC_13_success_resets_the_consecutive_count` | LULUS |
| SRS-RBC-13 | Wajib | `tests/Modules/Rbc/PasswordAndLockoutTest.php::test_SRS_RBC_13_threshold_and_lock_duration_are_configurable` | LULUS |
| SRS-RBC-13 | Wajib | `tests/Modules/Rbc/PasswordAndLockoutTest.php::test_SRS_RBC_13_admin_can_unlock_an_account` | LULUS |
| SRS-RBC-14 | Wajib | `tests/Modules/Rbc/PasswordAndLockoutTest.php::test_SRS_RBC_14_passwords_are_stored_as_salted_bcrypt_hashes` | LULUS |
| SRS-RBC-15 | Wajib | `tests/Modules/Rbc/AccountStatusTest.php::test_SRS_RBC_15_only_aktif_accounts_can_log_in_with_password` | LULUS |
| SRS-RBC-15 | Wajib | `tests/Modules/Rbc/AccountStatusTest.php::test_SRS_RBC_15_non_aktif_accounts_hold_no_permissions` | LULUS |
| SRS-RBC-15 | Wajib | `tests/Modules/Rbc/AccountStatusTest.php::test_SRS_RBC_15_status_change_is_logged_and_dispatched` | LULUS |
| SRS-RBC-15 | Wajib | `tests/Modules/Rbc/AccountStatusTest.php::test_SRS_RBC_15_status_change_requires_permission_and_a_valid_status` | LULUS |
| SRS-RBC-15 | Wajib | `tests/Modules/Rbc/AccountStatusTest.php::test_SRS_RBC_15_admin_cannot_suspend_themself` | LULUS |
| SRS-RBC-15 | Wajib | `tests/Modules/Rbc/AccountStatusTest.php::test_SRS_RBC_15_legacy_is_active_flag_maps_to_status` | LULUS |
| SRS-RBC-15 | Wajib | `tests/Modules/Rbc/SsoMappingTest.php::test_SRS_RBC_15_suspended_account_is_refused_on_sso_login` | LULUS |
| SRS-RBC-16 | Perlu | `tests/Modules/Rbc/AccountStatusTest.php::test_SRS_RBC_16_account_expires_on_its_access_end_date` | LULUS |
| SRS-RBC-16 | Perlu | `tests/Modules/Rbc/AccountStatusTest.php::test_SRS_RBC_16_account_is_suspended_after_90_days_without_login` | LULUS |
| SRS-RBC-16 | Perlu | `tests/Modules/Rbc/AccountStatusTest.php::test_SRS_RBC_16_inactivity_period_is_configurable` | LULUS |
| SRS-RBC-16 | Perlu | `tests/Modules/Rbc/AccountStatusTest.php::test_SRS_RBC_16_reactivated_account_is_not_suspended_again_immediately` | LULUS |
| SRS-RBC-16 | Perlu | `tests/Modules/Rbc/AccountStatusTest.php::test_SRS_RBC_16_expiry_is_applied_at_login_even_before_the_schedule_runs` | LULUS |
| SRS-RBC-16 | Perlu | `tests/Modules/Rbc/AccountStatusTest.php::test_SRS_RBC_16_command_is_scheduled` | LULUS |
| SRS-RBC-17 | Wajib | `tests/Modules/Rbc/SessionTest.php::test_SRS_RBC_17_suspending_a_user_ends_their_sessions_and_tokens_immediately` | LULUS |
| SRS-RBC-17 | Wajib | `tests/Modules/Rbc/SessionTest.php::test_SRS_RBC_17_status_change_outside_the_service_still_logs_the_session_out` | LULUS |
| SRS-RBC-17 | Wajib | `tests/Modules/Rbc/SessionTest.php::test_SRS_RBC_17_admin_can_end_all_sessions_of_a_user` | LULUS |
| SRS-RBC-17 | Wajib | `tests/Modules/Rbc/SessionTest.php::test_SRS_RBC_17_reactivated_user_can_log_in_again` | LULUS |
| SRS-RBC-18 | Wajib | `tests/Modules/Rbc/SessionTest.php::test_SRS_RBC_18_session_ends_after_default_15_minutes_idle` | LULUS |
| SRS-RBC-18 | Wajib | `tests/Modules/Rbc/SessionTest.php::test_SRS_RBC_18_timeout_is_changed_by_admin_without_a_release` | LULUS |
| SRS-RBC-18 | Wajib | `tests/Modules/Rbc/SessionTest.php::test_SRS_RBC_18_status_poll_reports_remaining_time_without_extending_it` | LULUS |
| SRS-RBC-18 | Wajib | `tests/Modules/Rbc/SessionTest.php::test_SRS_RBC_18_extend_button_resets_the_idle_timer` | LULUS |
| SRS-RBC-18 | Wajib | `tests/Modules/Rbc/SessionTest.php::test_SRS_RBC_18_scheduled_sweep_closes_idle_sessions` | LULUS |
| SRS-RBC-19 | Wajib | `tests/Modules/Rbc/SessionTest.php::test_SRS_RBC_19_concurrent_session_limit_ends_the_oldest_session` | LULUS |
| SRS-RBC-19 | Wajib | `tests/Modules/Rbc/SessionTest.php::test_SRS_RBC_19_admin_lists_and_force_terminates_a_session` | LULUS |
| SRS-RBC-19 | Wajib | `tests/Modules/Rbc/SessionTest.php::test_SRS_RBC_19_logout_ends_the_session_on_the_server` | LULUS |
| SRS-RBC-19 | Wajib | `tests/Modules/Rbc/SessionTest.php::test_SRS_RBC_19_user_without_permission_cannot_see_or_end_sessions` | LULUS |
| SRS-RBC-20 | Wajib | `tests/Modules/Rbc/SessionTest.php::test_SRS_RBC_20_session_id_is_regenerated_on_login` | LULUS |
| SRS-RBC-20 | Wajib | `tests/Modules/Rbc/SessionTest.php::test_SRS_RBC_20_session_cookie_is_httponly_samesite_and_secure_over_https` | LULUS |
| SRS-RBC-20 | Wajib | `tests/Modules/Rbc/SessionTest.php::test_SRS_RBC_20_session_is_bound_to_the_browser_fingerprint` | LULUS |
| SRS-RBC-20 | Wajib | `tests/Modules/Rbc/SessionTest.php::test_SRS_RBC_20_session_is_bound_to_the_ip_range` | LULUS |
| SRS-RBC-20 | Wajib | `tests/Modules/Rbc/SessionTest.php::test_SRS_RBC_20_ip_binding_can_be_made_exact_or_switched_off` | LULUS |
| SRS-RBC-21 | Wajib | `tests/Modules/Rbc/LoginMonitoringTest.php::test_SRS_RBC_21_successful_and_failed_logins_are_recorded_with_context` | LULUS |
| SRS-RBC-21 | Wajib | `tests/Modules/Rbc/LoginMonitoringTest.php::test_SRS_RBC_21_logout_dispatches_event` | LULUS |
| SRS-RBC-21 | Wajib | `tests/Modules/Rbc/LoginMonitoringTest.php::test_SRS_RBC_21_login_log_is_searchable_and_exportable` | LULUS |
| SRS-RBC-22 | Wajib | `tests/Modules/Ntf/EngineTest.php::test_SRS_RBC_22_suspicious_activity_alerts_system_administrators` | LULUS |
| SRS-RBC-22 | Wajib | `tests/Modules/Rbc/DirectoryTest.php::test_SRS_RBC_22_suspicious_login_activity_notifies_system_administrators` | LULUS |
| SRS-RBC-22 | Wajib | `tests/Modules/Rbc/LoginMonitoringTest.php::test_SRS_RBC_22_repeated_failures_raise_an_alert` | LULUS |
| SRS-RBC-22 | Wajib | `tests/Modules/Rbc/LoginMonitoringTest.php::test_SRS_RBC_22_login_from_new_device_or_location_raises_alerts` | LULUS |
| SRS-RBC-22 | Wajib | `tests/Modules/Rbc/LoginMonitoringTest.php::test_SRS_RBC_22_repeated_unauthorised_access_attempts_raise_an_alert` | LULUS |
| SRS-RBC-22 | Wajib | `tests/Modules/Rbc/LoginMonitoringTest.php::test_SRS_RBC_22_admin_reviews_alerts` | LULUS |
| SRS-RBC-23 | Wajib | — | tiada ujian |
| SRS-RBC-24 | Wajib | — | tiada ujian |
| SRS-RBC-25 | Wajib | — | tiada ujian |
| SRS-RBC-26 | Wajib | `tests/Modules/Rbc/AccessReportTest.php::test_SRS_RBC_26_access_report_counts_users_by_status_role_and_division` | LULUS |
| SRS-RBC-26 | Wajib | `tests/Modules/Rbc/AccessReportTest.php::test_SRS_RBC_26_access_report_exports_to_excel_and_pdf` | LULUS |
| SRS-RBC-26 | Wajib | `tests/Modules/Rbc/AccessReportTest.php::test_SRS_RBC_26_policy_settings_are_listed_and_validated` | LULUS |
| SRS-RBC-27 | Perlu | `tests/Modules/Rbc/RecertificationTest.php::test_SRS_RBC_27_cycle_snapshots_the_divisions_role_assignments` | LULUS |
| SRS-RBC-27 | Perlu | `tests/Modules/Rbc/RecertificationTest.php::test_SRS_RBC_27_owner_confirms_or_withdraws_and_withdrawal_takes_effect` | LULUS |
| SRS-RBC-27 | Perlu | `tests/Modules/Rbc/RecertificationTest.php::test_SRS_RBC_27_owner_cannot_review_another_division_or_themself` | LULUS |
| SRS-RBC-27 | Perlu | `tests/Modules/Rbc/RecertificationTest.php::test_SRS_RBC_27_closing_withdraws_unconfirmed_access_per_policy` | LULUS |
| SRS-RBC-27 | Perlu | `tests/Modules/Rbc/RecertificationTest.php::test_SRS_RBC_27_closing_keeps_unconfirmed_access_when_policy_says_so` | LULUS |
| SRS-RBC-27 | Perlu | `tests/Modules/Rbc/RecertificationTest.php::test_SRS_RBC_27_schedule_opens_cycles_per_division_and_closes_overdue_ones` | LULUS |
| SRS-RBC-27 | Perlu | `tests/Modules/Rbc/RecertificationTest.php::test_SRS_RBC_27_recertification_requires_permission` | LULUS |
| SRS-SEC-01 | Wajib | — | tiada ujian |
| SRS-SEC-02 | Wajib | — | tiada ujian |
| SRS-SEC-03 | Wajib | — | tiada ujian |
| SRS-SEC-04 | Wajib | — | tiada ujian |
| SRS-SEC-05 | Wajib | — | tiada ujian |
| SRS-SEC-06 | Wajib | — | tiada ujian |
| SRS-SEC-07 | Wajib | — | tiada ujian |
| SRS-SEC-08 | Wajib | — | tiada ujian |
| SRS-SEC-09 | Wajib | — | tiada ujian |
| SRS-SEC-10 | Wajib | — | tiada ujian |
| SRS-SEC-11 | Wajib | — | tiada ujian |
| SRS-SEC-12 | Wajib | — | tiada ujian |
| SRS-SEC-13 | Wajib | — | tiada ujian |
| SRS-SEC-14 | Wajib | — | tiada ujian |
| SRS-SEC-15 | Wajib | — | tiada ujian |
| SRS-SEC-16 | Wajib | — | tiada ujian |
| SRS-SEC-17 | Wajib | — | tiada ujian |
| SRS-SEC-18 | Wajib | — | tiada ujian |
| SRS-SEC-19 | Wajib | — | tiada ujian |
| SRS-SEC-20 | Wajib | — | tiada ujian |
| SRS-SEC-21 | Wajib | — | tiada ujian |
| SRS-SEC-22 | Wajib | — | tiada ujian |
| SRS-SEC-23 | Wajib | — | tiada ujian |
| SRS-SEC-24 | Wajib | — | tiada ujian |
| SRS-SEC-25 | Wajib | — | tiada ujian |
| SRS-SEC-26 | Wajib | — | tiada ujian |
| SRS-SEC-27 | Wajib | — | tiada ujian |
| SRS-SEC-28 | Wajib | — | tiada ujian |
| SRS-SEC-29 | Wajib | — | tiada ujian |
| SRS-SEC-30 | Wajib | — | tiada ujian |
| SRS-SEC-31 | Wajib | — | tiada ujian |
| SRS-SEC-32 | Wajib | — | tiada ujian |
| SRS-SEC-33 | Wajib | — | tiada ujian |
| SRS-SEC-34 | Wajib | — | tiada ujian |
| SRS-SEC-35 | Wajib | — | tiada ujian |
| SRS-TKT-01 | Wajib | — | tiada ujian |
| SRS-TKT-02 | Wajib | — | tiada ujian |
| SRS-TKT-03 | Wajib | — | tiada ujian |
| SRS-TKT-04 | Wajib | — | tiada ujian |
| SRS-TKT-05 | Wajib | — | tiada ujian |
| SRS-TKT-06 | Wajib | — | tiada ujian |
| SRS-TKT-07 | Baik-ada | — | tiada ujian |
| SRS-TKT-08 | Wajib | — | tiada ujian |
| SRS-TKT-09 | Wajib | — | tiada ujian |
| SRS-TKT-10 | Perlu | — | tiada ujian |
| SRS-TKT-11 | Wajib | — | tiada ujian |
| SRS-TKT-12 | Wajib | — | tiada ujian |
| SRS-TKT-13 | Wajib | — | tiada ujian |
| SRS-TKT-14 | Wajib | — | tiada ujian |
| SRS-TKT-15 | Wajib | — | tiada ujian |
| SRS-TKT-16 | Wajib | — | tiada ujian |
| SRS-TKT-17 | Wajib | — | tiada ujian |
| SRS-TKT-18 | Wajib | — | tiada ujian |
| SRS-TKT-19 | Wajib | — | tiada ujian |
| SRS-TKT-20 | Wajib | — | tiada ujian |
| SRS-TKT-21 | Wajib | — | tiada ujian |
| SRS-TSU-01 | Wajib | — | tiada ujian |
| SRS-TSU-02 | Wajib | — | tiada ujian |
| SRS-TSU-03 | Wajib | — | tiada ujian |
| SRS-TSU-04 | Wajib | — | tiada ujian |
| SRS-TSU-05 | Wajib | — | tiada ujian |
| SRS-TSU-06 | Wajib | — | tiada ujian |
| SRS-TSU-07 | Wajib | — | tiada ujian |
| SRS-TSU-08 | Wajib | — | tiada ujian |
| SRS-TSU-09 | Wajib | — | tiada ujian |
| SRS-TSU-10 | Wajib | — | tiada ujian |
| SRS-TSU-11 | Wajib | — | tiada ujian |
| SRS-TSU-12 | Wajib | — | tiada ujian |
| SRS-TSU-13 | Wajib | — | tiada ujian |
| SRS-TSU-14 | Wajib | — | tiada ujian |
| SRS-TSU-15 | Perlu | — | tiada ujian |
| SRS-TSU-16 | Wajib | — | tiada ujian |
| SRS-TSU-17 | Wajib | — | tiada ujian |
| SRS-TSU-18 | Wajib | — | tiada ujian |
| SRS-TSU-19 | Wajib | — | tiada ujian |
| SRS-TSU-20 | Wajib | — | tiada ujian |
| SRS-TSU-21 | Wajib | — | tiada ujian |
| SRS-TSU-22 | Wajib | — | tiada ujian |
| SRS-TSU-23 | Wajib | — | tiada ujian |
| SRS-TSU-24 | Wajib | — | tiada ujian |
| SRS-TSU-25 | Wajib | — | tiada ujian |
| SRS-TSU-26 | Wajib | — | tiada ujian |
| SRS-TSU-27 | Wajib | — | tiada ujian |
| SRS-WFE-01 | Wajib | `tests/Modules/Wfe/DecisionTest.php::test_SRS_WFE_01_engine_contract_status_and_active_instance` | LULUS |
| SRS-WFE-01 | Wajib | `tests/Modules/Wfe/DefinitionTest.php::test_SRS_WFE_01_flow_stages_differ_by_financial_threshold` | LULUS |
| SRS-WFE-02 | Wajib | `tests/Modules/Wfe/DefinitionTest.php::test_SRS_WFE_02_branch_on_district_and_land_category` | LULUS |
| SRS-WFE-02 | Wajib | `tests/Modules/Wfe/DefinitionTest.php::test_SRS_WFE_02_parallel_stage_all_any_and_quorum` | LULUS |
| SRS-WFE-02 | Wajib | `tests/Modules/Wfe/DefinitionTest.php::test_SRS_WFE_02_condition_model_is_data_only_and_safe` | LULUS |
| SRS-WFE-02 | Wajib | `tests/Modules/Wfe/DefinitionTest.php::test_SRS_WFE_02_invalid_graphs_are_rejected` | LULUS |
| SRS-WFE-03 | Wajib | `tests/Modules/Wfe/DefinitionTest.php::test_SRS_WFE_03_admin_adds_threshold_stage_without_code_and_running_cases_keep_old_version` | LULUS |
| SRS-WFE-03 | Wajib | `tests/Modules/Wfe/DefinitionTest.php::test_SRS_WFE_03_activation_needs_a_second_administrator` | LULUS |
| SRS-WFE-03 | Wajib | `tests/Modules/Wfe/DefinitionTest.php::test_SRS_WFE_03_definition_endpoints_require_permission` | LULUS |
| SRS-WFE-03 | Wajib | `tests/Modules/Wfe/DemoFlowTest.php::test_SRS_WFE_03_contracts_bound_and_catalogue_declared` | LULUS |
| SRS-WFE-04 | Wajib | `tests/Modules/Wfe/DefinitionTest.php::test_SRS_WFE_04_template_library_copy_as_new_flow` | LULUS |
| SRS-WFE-05 | Wajib | `tests/Modules/Wfe/DefinitionTest.php::test_SRS_WFE_05_approver_by_role_position_individual_and_group` | LULUS |
| SRS-WFE-06 | Wajib | `tests/Modules/Wfe/SlaTest.php::test_SRS_WFE_06_working_days_skip_selangor_weekend_and_public_holidays` | LULUS |
| SRS-WFE-06 | Wajib | `tests/Modules/Wfe/SlaTest.php::test_SRS_WFE_06_working_hours_sla_counts_office_hours_only` | LULUS |
| SRS-WFE-06 | Wajib | `tests/Modules/Wfe/SlaTest.php::test_SRS_WFE_06_seeded_selangor_calendar_2026_2027_with_source` | LULUS |
| SRS-WFE-06 | Wajib | `tests/Modules/Wfe/SlaTest.php::test_SRS_WFE_06_admin_updates_calendar_and_sla_follows` | LULUS |
| SRS-WFE-07 | Wajib | `tests/Modules/Wfe/SlaTest.php::test_SRS_WFE_07_overdue_task_escalates_level_one_then_level_two` | LULUS |
| SRS-WFE-07 | Wajib | `tests/Modules/Wfe/SlaTest.php::test_SRS_WFE_07_sla_check_is_scheduled` | LULUS |
| SRS-WFE-08 | Wajib | `tests/Modules/Wfe/SlaTest.php::test_SRS_WFE_08_escalation_levels_are_configurable_and_validated` | LULUS |
| SRS-WFE-09 | Wajib | `tests/Modules/Wfe/DefinitionTest.php::test_SRS_WFE_09_visual_editor_and_form_save_the_same_definition` | LULUS |
| SRS-WFE-10 | Perlu | — | tiada ujian |
| SRS-WFE-11 | Perlu | — | tiada ujian |
| SRS-WFE-12 | Wajib | `tests/Modules/Wfe/DefinitionTest.php::test_SRS_WFE_12_versions_are_kept_logged_and_never_deleted` | LULUS |
| SRS-WFE-12 | Wajib | `tests/Modules/Wfe/DefinitionTest.php::test_SRS_WFE_12_moving_a_running_instance_to_a_new_version_needs_admin_approval` | LULUS |
| SRS-WFE-13 | Wajib | `tests/Modules/Wfe/MonitoringTest.php::test_SRS_WFE_13_dashboard_counts_match_test_transactions` | LULUS |
| SRS-WFE-14 | Wajib | `tests/Modules/Wfe/DecisionTest.php::test_SRS_WFE_14_task_board_shows_only_my_tasks_with_sla_remaining` | LULUS |
| SRS-WFE-14 | Wajib | `tests/Modules/Wfe/DecisionTest.php::test_SRS_WFE_14_quick_actions_approve_reject_return_from_the_board` | LULUS |
| SRS-WFE-15 | Wajib | `tests/Modules/Wfe/MonitoringTest.php::test_SRS_WFE_15_drill_down_link_to_source_record` | LULUS |
| SRS-WFE-16 | Wajib | `tests/Modules/Wfe/DecisionTest.php::test_SRS_WFE_16_history_lists_every_stage_actor_decision_justification_and_time` | LULUS |
| SRS-WFE-16 | Wajib | `tests/Modules/Wfe/DecisionTest.php::test_SRS_WFE_16_role_at_time_of_action_is_recorded_not_current_role` | LULUS |
| SRS-WFE-17 | Wajib | `tests/Modules/Wfe/DecisionTest.php::test_SRS_WFE_17_reject_return_defer_need_written_justification` | LULUS |
| SRS-WFE-17 | Wajib | `tests/Modules/Wfe/DecisionTest.php::test_SRS_WFE_17_defer_pauses_sla_and_keeps_task_with_approver` | LULUS |
| SRS-WFE-17 | Wajib | `tests/Modules/Wfe/SlaTest.php::test_SRS_WFE_17_deferred_task_resumes_when_period_ends` | LULUS |
| SRS-WFE-18 | Wajib | `tests/Modules/Wfe/DecisionTest.php::test_SRS_WFE_18_applicant_cannot_approve_own_request` | LULUS |
| SRS-WFE-18 | Wajib | `tests/Modules/Wfe/DecisionTest.php::test_SRS_WFE_18_same_user_cannot_act_on_two_consecutive_stages` | LULUS |
| SRS-WFE-19 | Wajib | `tests/Modules/Wfe/MfaStepUpTest.php::test_SRS_WFE_19_critical_stage_cannot_be_approved_without_mfa` | LULUS |
| SRS-WFE-19 | Wajib | `tests/Modules/Wfe/MfaStepUpTest.php::test_SRS_WFE_19_stale_step_up_is_not_accepted` | LULUS |
| SRS-WFE-19 | Wajib | `tests/Modules/Wfe/MfaStepUpTest.php::test_SRS_WFE_19_fresh_step_up_allows_the_decision_and_is_recorded` | LULUS |
| SRS-WFE-19 | Wajib | `tests/Modules/Wfe/MfaStepUpTest.php::test_SRS_WFE_19_step_up_redirects_to_idp_with_forced_reauthentication` | LULUS |
| SRS-WFE-19 | Wajib | `tests/Modules/Wfe/MfaStepUpTest.php::test_SRS_WFE_19_callback_records_step_up_for_same_subject_with_fresh_auth_time` | LULUS |
| SRS-WFE-19 | Wajib | `tests/Modules/Wfe/MfaStepUpTest.php::test_SRS_WFE_19_callback_rejects_other_subject_or_old_authentication` | LULUS |
| SRS-WFE-19 | Wajib | `tests/Modules/Wfe/MfaStepUpTest.php::test_SRS_WFE_19_otp_evidence_can_be_required` | LULUS |
| SRS-WFE-19 | Wajib | `tests/Modules/Wfe/MfaStepUpTest.php::test_SRS_WFE_19_password_only_accounts_cannot_step_up` | LULUS |
| SRS-WFE-19 | Wajib | `tests/Modules/Wfe/MfaStepUpTest.php::test_SRS_WFE_19_return_path_cannot_be_an_open_redirect` | LULUS |
| SRS-WFE-20 | Wajib | `tests/Modules/Wfe/DecisionTest.php::test_SRS_WFE_20_final_outcome_dispatches_event_notifies_and_creates_follow_up` | LULUS |
| SRS-WFE-20 | Wajib | `tests/Modules/Wfe/DecisionTest.php::test_SRS_WFE_20_new_task_notifies_role_holders` | LULUS |
| SRS-WFE-20 | Wajib | `tests/Modules/Wfe/DemoFlowTest.php::test_SRS_WFE_20_seeded_demo_flow_runs_end_to_end` | LULUS |
| SRS-WFE-21 | Perlu | `tests/Modules/Wfe/DelegationTest.php::test_SRS_WFE_21_new_tasks_route_to_delegate_and_both_identities_are_recorded` | LULUS |
| SRS-WFE-21 | Perlu | `tests/Modules/Wfe/DelegationTest.php::test_SRS_WFE_21_delegate_acts_on_pool_tasks_of_the_absent_approver` | LULUS |
| SRS-WFE-21 | Perlu | `tests/Modules/Wfe/DelegationTest.php::test_SRS_WFE_21_delegation_only_inside_its_period_and_respects_segregation` | LULUS |
| SRS-WFE-21 | Perlu | `tests/Modules/Wfe/DelegationTest.php::test_SRS_WFE_21_only_admins_set_a_delegate_for_someone_else` | LULUS |
| SRS-WFE-22 | Baik-ada | `tests/Modules/Wfe/DecisionTest.php::test_SRS_WFE_22_applicant_withdraws_pending_request` | LULUS |
| SRS-WFE-22 | Baik-ada | `tests/Modules/Wfe/DecisionTest.php::test_SRS_WFE_22_return_from_first_stage_goes_back_to_applicant_who_resubmits` | LULUS |
| SRS-WFE-23 | Wajib | `tests/Modules/Wfe/MonitoringTest.php::test_SRS_WFE_23_performance_report_per_stage_bottleneck_escalation_backlog` | LULUS |
| SRS-WFE-24 | Perlu | — | tiada ujian |

**153 daripada 699 keperluan** mempunyai sekurang-kurangnya satu ujian.
