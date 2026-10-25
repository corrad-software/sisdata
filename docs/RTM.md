# RTM — SISDATA

_Dijana oleh `php artisan srs:rtm` pada 2026-10-25 02:17:12. Jangan sunting dengan tangan._

| Modul | Keperluan | Ada ujian | Liputan |
| --- | --- | --- | --- |
| ADM | 29 | 28 | 96% |
| ADU | 27 | 27 | 100% |
| AST | 26 | 26 | 100% |
| AUD | 26 | 26 | 100% |
| CKI | 24 | 24 | 100% |
| DAT | 9 | 9 | 100% |
| DMS | 26 | 26 | 100% |
| DSH | 25 | 23 | 92% |
| GEN | 9 | 3 | 33% |
| GIS | 35 | 29 | 82% |
| HKM | 22 | 22 | 100% |
| INF | 30 | 0 | 0% |
| INT | 18 | 0 | 0% |
| KBA | 22 | 21 | 95% |
| KES | 24 | 23 | 95% |
| LAP | 23 | 23 | 100% |
| NFR | 26 | 0 | 0% |
| NTF | 24 | 24 | 100% |
| PER | 30 | 30 | 100% |
| PJK | 28 | 28 | 100% |
| PRO | 30 | 30 | 100% |
| PTN | 24 | 24 | 100% |
| PWS | 28 | 22 | 78% |
| RBC | 27 | 24 | 88% |
| SEC | 35 | 0 | 0% |
| TKT | 21 | 21 | 100% |
| TSU | 27 | 24 | 88% |
| WFE | 24 | 21 | 87% |

## Butiran

| Keperluan | Keutamaan | Ujian | Status |
| --- | --- | --- | --- |
| SRS-ADM-01 | Wajib | `tests/Modules/Adm/ParameterTest.php::test_SRS_ADM_01_parameters_are_listed_grouped_by_module_with_type_and_critical_flag` | — |
| SRS-ADM-01 | Wajib | `tests/Modules/Adm/ParameterTest.php::test_SRS_ADM_01_a_parameter_change_takes_effect_on_the_next_read_without_restart` | — |
| SRS-ADM-01 | Wajib | `tests/Modules/Adm/ParameterTest.php::test_SRS_ADM_01_values_are_validated_against_the_declared_type` | — |
| SRS-ADM-01 | Wajib | `tests/Modules/Adm/ParameterTest.php::test_SRS_ADM_01_reset_returns_to_the_declared_default` | — |
| SRS-ADM-01 | Wajib | `tests/Modules/Adm/ParameterTest.php::test_SRS_ADM_01_user_without_permission_cannot_view_or_change_parameters` | — |
| SRS-ADM-01 | Wajib | `tests/Modules/Adm/ParameterTest.php::test_SRS_ADM_01_parameters_fall_back_to_defaults_when_the_cache_is_down` | — |
| SRS-ADM-02 | Wajib | `tests/Modules/Adm/KodRujukanTest.php::test_SRS_ADM_02_admin_adds_a_code_and_it_is_usable_immediately` | — |
| SRS-ADM-02 | Wajib | `tests/Modules/Adm/KodRujukanTest.php::test_SRS_ADM_02_duplicate_codes_in_a_type_are_refused` | — |
| SRS-ADM-02 | Wajib | `tests/Modules/Adm/KodRujukanTest.php::test_SRS_ADM_02_amendments_are_versioned_with_history` | — |
| SRS-ADM-02 | Wajib | `tests/Modules/Adm/KodRujukanTest.php::test_SRS_ADM_02_deactivated_code_stays_valid_for_historical_records` | — |
| SRS-ADM-02 | Wajib | `tests/Modules/Adm/KodRujukanTest.php::test_SRS_ADM_02_search_by_type_text_status_and_validity_date` | — |
| SRS-ADM-02 | Wajib | `tests/Modules/Adm/KodRujukanTest.php::test_SRS_ADM_02_mukim_parent_must_be_an_existing_daerah` | — |
| SRS-ADM-02 | Wajib | `tests/Modules/Adm/KodRujukanTest.php::test_SRS_ADM_02_permissions_are_enforced_per_action` | — |
| SRS-ADM-03 | Wajib | `tests/Modules/Adm/TemplatTest.php::test_SRS_ADM_03_admin_manages_report_template_content_fields_format_and_versions` | — |
| SRS-ADM-03 | Wajib | `tests/Modules/Adm/TemplatTest.php::test_SRS_ADM_03_placeholders_must_be_declared_fields` | — |
| SRS-ADM-03 | Wajib | `tests/Modules/Adm/TemplatTest.php::test_SRS_ADM_03_preview_and_render_escape_dynamic_values` | — |
| SRS-ADM-03 | Wajib | `tests/Modules/Adm/TemplatTest.php::test_SRS_ADM_03_template_management_requires_permission` | — |
| SRS-ADM-04 | Wajib | `tests/Modules/Adm/KesihatanIntegrasiTest.php::test_SRS_ADM_04_integration_settings_go_through_approval_and_are_readable_by_modules` | — |
| SRS-ADM-04 | Wajib | `tests/Modules/Adm/KesihatanIntegrasiTest.php::test_SRS_ADM_04_test_connection_logs_the_exchange_and_permissions_apply` | — |
| SRS-ADM-05 | Perlu | `tests/Modules/Adm/KeselamatanTest.php::test_SRS_ADM_05_admin_scoped_api_token_can_automate_configuration` | — |
| SRS-ADM-05 | Perlu | `tests/Modules/Adm/KeselamatanTest.php::test_SRS_ADM_05_tokens_without_the_admin_ability_or_permission_are_refused` | — |
| SRS-ADM-05 | Perlu | `tests/Modules/Adm/KeselamatanTest.php::test_SRS_ADM_05_tokens_can_be_listed_and_revoked` | — |
| SRS-ADM-05 | Perlu | `tests/Modules/Adm/KodRujukanTest.php::test_SRS_ADM_05_bulk_import_validates_each_row_and_reports_rejections` | — |
| SRS-ADM-05 | Perlu | `tests/Modules/Adm/KodRujukanTest.php::test_SRS_ADM_05_bulk_import_from_csv_with_upsert` | — |
| SRS-ADM-06 | Wajib | `tests/Modules/Adm/TugasanTest.php::test_SRS_ADM_06_admin_defines_a_cron_job_and_it_is_registered_in_the_scheduler` | — |
| SRS-ADM-06 | Wajib | `tests/Modules/Adm/TugasanTest.php::test_SRS_ADM_06_fixed_interval_jobs_and_disabled_jobs` | — |
| SRS-ADM-06 | Wajib | `tests/Modules/Adm/TugasanTest.php::test_SRS_ADM_06_invalid_cron_and_unsafe_commands_are_refused` | — |
| SRS-ADM-06 | Wajib | `tests/Modules/Adm/TugasanTest.php::test_SRS_ADM_06_run_now_is_queued_and_recorded` | — |
| SRS-ADM-06 | Wajib | `tests/Modules/Adm/TugasanTest.php::test_SRS_ADM_06_enable_disable_and_permissions` | — |
| SRS-ADM-06 | Wajib | `tests/Modules/Adm/TugasanTest.php::test_SRS_ADM_06_other_modules_scheduled_jobs_are_listed_and_adoptable` | — |
| SRS-ADM-07 | Wajib | `tests/Modules/Adm/TugasanTest.php::test_SRS_ADM_07_job_categories_cover_the_required_kinds` | — |
| SRS-ADM-08 | Wajib | `tests/Modules/Adm/TugasanTest.php::test_SRS_ADM_08_run_history_has_start_end_duration_status_records_and_error` | — |
| SRS-ADM-09 | Perlu | `tests/Modules/Adm/TugasanTest.php::test_SRS_ADM_09_the_same_job_never_runs_concurrently` | — |
| SRS-ADM-09 | Perlu | `tests/Modules/Adm/TugasanTest.php::test_SRS_ADM_09_failed_jobs_are_retried_as_configured` | — |
| SRS-ADM-10 | Perlu | `tests/Modules/Adm/TugasanTest.php::test_SRS_ADM_10_final_failure_notifies_system_administrators` | — |
| SRS-ADM-10 | Perlu | `tests/Modules/Adm/TugasanTest.php::test_SRS_ADM_10_a_missed_schedule_alerts_and_stuck_runs_are_closed` | — |
| SRS-ADM-10 | Perlu | `tests/Modules/Adm/TugasanTest.php::test_SRS_ADM_10_failure_of_an_adopted_framework_job_alerts` | — |
| SRS-ADM-11 | Wajib | `tests/Modules/Adm/LogTest.php::test_SRS_ADM_11_errors_are_logged_with_module_severity_code_user_ip_and_trace` | — |
| SRS-ADM-11 | Wajib | `tests/Modules/Adm/LogTest.php::test_SRS_ADM_11_search_filters_by_period_module_severity_code_and_user` | — |
| SRS-ADM-11 | Wajib | `tests/Modules/Adm/LogTest.php::test_SRS_ADM_11_log_access_is_restricted_and_audited` | — |
| SRS-ADM-12 | Perlu | `tests/Feature/Api/ErrorFormatTest.php::test_SRS_ADM_12_every_response_carries_a_request_id` | — |
| SRS-ADM-12 | Perlu | `tests/Feature/Api/ErrorFormatTest.php::test_SRS_ADM_12_a_sane_incoming_request_id_is_kept` | — |
| SRS-ADM-12 | Perlu | `tests/Feature/Api/ErrorFormatTest.php::test_SRS_ADM_12_a_malformed_incoming_request_id_is_replaced` | — |
| SRS-ADM-12 | Perlu | `tests/Modules/Adm/LogTest.php::test_SRS_ADM_12_the_reference_shown_to_the_user_finds_the_log_entry` | — |
| SRS-ADM-13 | Wajib | `tests/Feature/Api/ErrorFormatTest.php::test_SRS_ADM_13_server_errors_hide_internals_and_return_request_id` | — |
| SRS-ADM-13 | Wajib | `tests/Feature/Api/ErrorFormatTest.php::test_SRS_ADM_13_unauthenticated_and_not_found_use_the_same_shape` | — |
| SRS-ADM-13 | Wajib | `tests/Feature/Api/ErrorFormatTest.php::test_SRS_ADM_13_validation_errors_keep_field_errors` | — |
| SRS-ADM-14 | Wajib | `tests/Modules/Adm/LogTest.php::test_SRS_ADM_14_application_and_integration_logs_export_by_period` | — |
| SRS-ADM-14 | Wajib | `tests/Modules/Adm/LogTest.php::test_SRS_ADM_14_retention_policy_prunes_old_rows_per_configured_period` | — |
| SRS-ADM-15 | Wajib | `tests/Modules/Adm/LogTest.php::test_SRS_ADM_15_logs_never_store_passwords_tokens_keys_or_full_ic_numbers` | — |
| SRS-ADM-15 | Wajib | `tests/Modules/Adm/LogTest.php::test_SRS_ADM_15_redactor_patterns` | — |
| SRS-ADM-15 | Wajib | `tests/Modules/Adm/LogTest.php::test_SRS_ADM_15_structured_json_log_file_is_redacted_and_carries_request_id` | — |
| SRS-ADM-16 | Wajib | `tests/Modules/Adm/KesihatanIntegrasiTest.php::test_SRS_ADM_16_single_health_screen_shows_services_queue_integrations_storage_database_and_errors` | — |
| SRS-ADM-16 | Wajib | `tests/Modules/Adm/KesihatanIntegrasiTest.php::test_SRS_ADM_16_default_probes_cover_every_platform_service` | — |
| SRS-ADM-16 | Wajib | `tests/Modules/Adm/KesihatanIntegrasiTest.php::test_SRS_ADM_16_health_screen_requires_permission` | — |
| SRS-ADM-17 | Wajib | `tests/Modules/Adm/KesihatanIntegrasiTest.php::test_SRS_ADM_17_service_down_queue_and_storage_thresholds_alert_ict` | — |
| SRS-ADM-17 | Wajib | `tests/Modules/Adm/KesihatanIntegrasiTest.php::test_SRS_ADM_17_an_integration_switched_off_alerts_after_repeated_failures` | — |
| SRS-ADM-18 | Wajib | `tests/Modules/Adm/ParameterTest.php::test_SRS_ADM_18_critical_change_stays_pending_until_a_second_admin_approves` | — |
| SRS-ADM-18 | Wajib | `tests/Modules/Adm/ParameterTest.php::test_SRS_ADM_18_rejected_change_is_never_applied_and_duplicates_are_refused` | — |
| SRS-ADM-18 | Wajib | `tests/Modules/Adm/ParameterTest.php::test_SRS_ADM_18_application_admin_cannot_change_security_settings` | — |
| SRS-ADM-18 | Wajib | `tests/Modules/Adm/ParameterTest.php::test_SRS_ADM_18_requester_can_cancel_and_approval_needs_permission` | — |
| SRS-ADM-18 | Wajib | `tests/Modules/Adm/ParameterTest.php::test_SRS_ADM_18_uses_the_workflow_engine_when_available` | — |
| SRS-ADM-18 | Wajib | `tests/Modules/Adm/ParameterTest.php::test_SRS_ADM_18_workflow_approval_by_the_requester_is_refused` | — |
| SRS-ADM-18 | Wajib | `tests/Modules/Adm/ParameterTest.php::test_SRS_ADM_18_falls_back_to_built_in_approval_when_the_flow_is_not_configured` | — |
| SRS-ADM-19 | Wajib | `tests/Modules/Adm/ParameterTest.php::test_SRS_ADM_19_change_record_keeps_before_after_justification_requester_ip_and_approver` | — |
| SRS-ADM-19 | Wajib | `tests/Modules/Adm/ParameterTest.php::test_SRS_ADM_19_pending_and_decided_changes_can_be_listed_and_filtered` | — |
| SRS-ADM-20 | Wajib | `tests/Modules/Adm/CarianTest.php::test_SRS_ADM_20_weights_synonyms_fields_and_enable_switch_are_admin_configurable` | — |
| SRS-ADM-20 | Wajib | `tests/Modules/Adm/CarianTest.php::test_SRS_ADM_20_reindex_is_triggered_and_tracked` | — |
| SRS-ADM-21 | Wajib | `tests/Modules/Adm/CarianTest.php::test_SRS_ADM_21_global_search_by_lot_number_returns_records_from_several_modules` | — |
| SRS-ADM-21 | Wajib | `tests/Modules/Adm/CarianTest.php::test_SRS_ADM_21_results_outside_the_users_scope_are_not_returned` | — |
| SRS-ADM-21 | Wajib | `tests/Modules/Adm/CarianTest.php::test_SRS_ADM_21_detailed_search_by_filters_uses_filterable_providers_only` | — |
| SRS-ADM-22 | Wajib | `tests/Modules/Adm/CarianTest.php::test_SRS_ADM_22_deep_link_resolves_to_the_source_record_with_access_enforced` | — |
| SRS-ADM-22 | Wajib | `tests/Modules/Adm/CarianTest.php::test_SRS_ADM_22_search_result_links_open_the_right_record` | — |
| SRS-ADM-23 | Wajib | `tests/Modules/Adm/DevSecOpsTest.php::test_SRS_ADM_23_pipeline_runs_sast_sca_and_secret_scanning` | — |
| SRS-ADM-24 | Wajib | `tests/Modules/Adm/DevSecOpsTest.php::test_SRS_ADM_24_quality_gate_fails_the_build_on_high_or_critical_findings` | — |
| SRS-ADM-25 | Wajib | — | tiada ujian |
| SRS-ADM-26 | Wajib | `tests/Modules/Adm/KeselamatanTest.php::test_SRS_ADM_26_real_client_ip_comes_from_x_forwarded_for_only_behind_a_trusted_proxy` | — |
| SRS-ADM-26 | Wajib | `tests/Modules/Adm/KeselamatanTest.php::test_SRS_ADM_26_loopback_is_always_trusted_for_the_nextjs_rewrite` | — |
| SRS-ADM-27 | Wajib | `tests/Modules/Adm/KeselamatanTest.php::test_SRS_ADM_27_security_events_are_shipped_to_the_siem_stream` | — |
| SRS-ADM-27 | Wajib | `tests/Modules/Adm/KeselamatanTest.php::test_SRS_ADM_27_syslog_adapter_emits_rfc5424_with_cef_payload` | — |
| SRS-ADM-28 | Wajib | `tests/Modules/Adm/KesihatanIntegrasiTest.php::test_SRS_ADM_28_secrets_are_encrypted_at_rest_and_never_returned` | — |
| SRS-ADM-28 | Wajib | `tests/Modules/Adm/KesihatanIntegrasiTest.php::test_SRS_ADM_28_artisan_adm_secret_stores_an_encrypted_value` | — |
| SRS-ADM-29 | Baik-ada | `tests/Modules/Adm/KeselamatanTest.php::test_SRS_ADM_29_maintenance_mode_shows_notice_blocks_new_transactions_and_lets_admins_in` | — |
| SRS-ADM-29 | Baik-ada | `tests/Modules/Adm/KeselamatanTest.php::test_SRS_ADM_29_only_authorised_admins_switch_maintenance_mode` | — |
| SRS-ADU-01 | Wajib | `tests/Modules/Adu/CapaianKonfigurasiTest.php::test_SRS_ADU_01_every_endpoint_requires_permission_and_respects_district_scope` | — |
| SRS-ADU-01 | Wajib | `tests/Modules/Adu/IntakeTest.php::test_SRS_ADU_01_internal_intake_lands_in_the_single_queue_with_timeline_and_sla` | — |
| SRS-ADU-02 | Wajib | `tests/Modules/Adu/IntakeTest.php::test_SRS_ADU_02_field_intake_with_gps_and_photos_goes_to_dms` | — |
| SRS-ADU-02 | Wajib | `tests/Modules/Adu/PortalAwamTest.php::test_SRS_ADU_02_public_portal_accepts_a_complaint_without_an_account_and_shows_the_reference` | — |
| SRS-ADU-03 | Wajib | `tests/Modules/Adu/IntakeTest.php::test_SRS_ADU_03_reference_numbers_are_unique_across_channels` | — |
| SRS-ADU-03 | Wajib | `tests/Modules/Adu/PortalAwamTest.php::test_SRS_ADU_03_reference_is_returned_immediately_in_the_submit_response` | — |
| SRS-ADU-04 | Wajib | `tests/Modules/Adu/PortalAwamTest.php::test_SRS_ADU_04_status_check_needs_the_reference_plus_email_or_phone` | — |
| SRS-ADU-05 | Wajib | `tests/Modules/Adu/KitaranHayatTest.php::test_SRS_ADU_05_reporter_is_notified_on_each_main_status_change` | — |
| SRS-ADU-06 | Wajib | `tests/Modules/Adu/IntakeTest.php::test_SRS_ADU_06_channel_is_recorded_for_every_complaint_and_reported` | — |
| SRS-ADU-07 | Wajib | `tests/Modules/Adu/IntakeTest.php::test_SRS_ADU_07_central_system_complaints_join_the_queue_idempotently_and_status_flows_back` | — |
| SRS-ADU-07 | Wajib | `tests/Modules/Adu/IntakeTest.php::test_SRS_ADU_07_failed_status_push_is_kept_in_outbox_and_retried` | — |
| SRS-ADU-08 | Wajib | `tests/Modules/Adu/CapaianKonfigurasiTest.php::test_SRS_ADU_08_categories_and_subcategories_are_configurable` | — |
| SRS-ADU-09 | Wajib | `tests/Modules/Adu/TriajTest.php::test_SRS_ADU_09_coordinates_inside_a_mais_lot_are_matched_to_that_lot` | — |
| SRS-ADU-09 | Wajib | `tests/Modules/Adu/TriajTest.php::test_SRS_ADU_09_category_and_priority_are_suggested_from_keywords` | — |
| SRS-ADU-09 | Wajib | `tests/Modules/Adu/TriajTest.php::test_SRS_ADU_09_repeat_complaint_on_same_lot_within_window_is_flagged` | — |
| SRS-ADU-10 | Perlu | `tests/Modules/Adu/TriajTest.php::test_SRS_ADU_10_location_outside_every_mais_lot_is_flagged_with_polite_reply_and_agencies` | — |
| SRS-ADU-11 | Wajib | `tests/Modules/Adu/SlaPenugasanTest.php::test_SRS_ADU_11_assignment_is_automatic_by_category_and_district_rules` | — |
| SRS-ADU-11 | Wajib | `tests/Modules/Adu/SlaPenugasanTest.php::test_SRS_ADU_11_role_rule_picks_least_loaded_in_scope_officer_and_manual_assignment_works` | — |
| SRS-ADU-12 | Wajib | `tests/Modules/Adu/CapaianKonfigurasiTest.php::test_SRS_ADU_12_sla_and_escalation_path_are_configurable_with_versions` | — |
| SRS-ADU-12 | Wajib | `tests/Modules/Adu/SlaPenugasanTest.php::test_SRS_ADU_12_sla_starts_by_category_and_remaining_time_is_shown_in_the_queue` | — |
| SRS-ADU-13 | Perlu | `tests/Modules/Adu/SlaPenugasanTest.php::test_SRS_ADU_13_sla_does_not_run_on_weekends_or_configured_public_holidays` | — |
| SRS-ADU-14 | Wajib | `tests/Modules/Adu/SlaPenugasanTest.php::test_SRS_ADU_14_escalation_fires_per_tier_at_configured_thresholds` | — |
| SRS-ADU-15 | Wajib | `tests/Modules/Adu/KitaranHayatTest.php::test_SRS_ADU_15_complaint_is_linked_to_its_lot_and_listed_on_the_lot_profile` | — |
| SRS-ADU-16 | Wajib | `tests/Modules/Adu/LaporanTest.php::test_SRS_ADU_16_map_layer_shows_scoped_points_coloured_by_category` | — |
| SRS-ADU-17 | Wajib | `tests/Modules/Adu/AliranPenutupanTest.php::test_SRS_ADU_17_site_visit_reminder_goes_to_the_officer_the_day_before` | — |
| SRS-ADU-17 | Wajib | `tests/Modules/Adu/KitaranHayatTest.php::test_SRS_ADU_17_follow_up_actions_visits_referral_reassignment_and_escalation` | — |
| SRS-ADU-18 | Wajib | `tests/Modules/Adu/KitaranHayatTest.php::test_SRS_ADU_18_timeline_and_sla_trail_cannot_be_changed_or_deleted` | — |
| SRS-ADU-19 | Wajib | `tests/Modules/Adu/AliranPenutupanTest.php::test_SRS_ADU_19_seeded_flow_enforces_segregation_between_investigator_and_approver` | — |
| SRS-ADU-19 | Wajib | `tests/Modules/Adu/KitaranHayatTest.php::test_SRS_ADU_19_closure_needs_resolution_justification_and_supervisor_approval_then_reporter_notice` | — |
| SRS-ADU-20 | Wajib | `tests/Modules/Adu/PrivasiPengaduTest.php::test_SRS_ADU_20_only_assigned_officer_supervisor_and_unit_aduan_may_see_reporter_identity` | — |
| SRS-ADU-21 | Wajib | `tests/Modules/Adu/PrivasiPengaduTest.php::test_SRS_ADU_21_identity_is_encrypted_at_rest_and_every_access_is_logged_with_purpose` | — |
| SRS-ADU-22 | Wajib | `tests/Modules/Adu/PortalAwamTest.php::test_SRS_ADU_22_anonymous_complaint_stores_no_identity_gets_no_notices_and_is_checked_by_reference_only` | — |
| SRS-ADU-23 | Wajib | `tests/Modules/Adu/SlaPenugasanTest.php::test_SRS_ADU_23_officers_are_notified_on_new_assignment_and_resolution` | — |
| SRS-ADU-24 | Wajib | `tests/Modules/Adu/PortalAwamTest.php::test_SRS_ADU_24_captcha_rate_limits_and_file_limits_protect_the_portal` | — |
| SRS-ADU-24 | Wajib | `tests/Modules/Adu/PortalAwamTest.php::test_SRS_ADU_24_same_content_from_same_contact_is_detected_as_duplicate` | — |
| SRS-ADU-25 | Wajib | `tests/Modules/Adu/LaporanTest.php::test_SRS_ADU_25_reports_by_category_status_district_period_officer_and_export` | — |
| SRS-ADU-26 | Wajib | `tests/Modules/Adu/LaporanTest.php::test_SRS_ADU_26_dashboard_counts_match_the_test_data` | — |
| SRS-ADU-27 | Perlu | `tests/Modules/Adu/LaporanTest.php::test_SRS_ADU_27_analytics_repeat_lots_hotspots_and_trends` | — |
| SRS-AST-01 | Wajib | `tests/Modules/Ast/DaftarAsetTest.php::test_SRS_AST_01_02_registration_records_full_asset_details_linked_to_the_lot` | — |
| SRS-AST-01 | Wajib | `tests/Modules/Ast/DaftarAsetTest.php::test_SRS_AST_01_lot_outside_scope_or_missing_is_rejected` | — |
| SRS-AST-01 | Wajib | `tests/Modules/Ast/DaftarAsetTest.php::test_SRS_AST_01_high_value_acquisition_goes_through_wfe` | — |
| SRS-AST-01 | Wajib | `tests/Modules/Ast/DaftarAsetTest.php::test_SRS_AST_01_permission_and_district_scope_enforced` | — |
| SRS-AST-01 | Wajib | `tests/Modules/Ast/LaporanIntegrasiTest.php::test_SRS_AST_01_disposal_through_wfe_archives_without_deleting` | — |
| SRS-AST-02 | Wajib | `tests/Modules/Ast/DaftarAsetTest.php::test_SRS_AST_02_maintenance_schedules_generated_from_category` | — |
| SRS-AST-02 | Wajib | `tests/Modules/Ast/LaporanIntegrasiTest.php::test_SRS_AST_02_profile_section_search_and_timeline_respect_scope` | — |
| SRS-AST-03 | Wajib | `tests/Modules/Ast/DaftarAsetTest.php::test_SRS_AST_03_categories_are_configurable` | — |
| SRS-AST-04 | Wajib | `tests/Modules/Ast/DaftarAsetTest.php::test_SRS_AST_04_lot_holds_many_assets_and_parent_sub_asset_hierarchy` | — |
| SRS-AST-04 | Wajib | `tests/Modules/Ast/LaporanIntegrasiTest.php::test_SRS_AST_04_asset_linked_to_pjk_premise_without_duplication` | — |
| SRS-AST-05 | Wajib | `tests/Modules/Ast/KondisiNilaiTest.php::test_SRS_AST_05_condition_scale_with_full_history_date_and_officer` | — |
| SRS-AST-06 | Wajib | `tests/Modules/Ast/DaftarAsetTest.php::test_SRS_AST_06_unique_qr_reference_generated_and_never_reused` | — |
| SRS-AST-06 | Wajib | `tests/Modules/Ast/DaftarAsetTest.php::test_SRS_AST_06_archived_asset_qr_stays_reserved` | — |
| SRS-AST-06 | Wajib | `tests/Modules/Ast/QrTest.php::test_SRS_AST_06_qr_can_be_deactivated_individually` | — |
| SRS-AST-07 | Wajib | `tests/Modules/Ast/QrTest.php::test_SRS_AST_07_batch_label_print_of_at_least_20_assets_with_template` | — |
| SRS-AST-07 | Wajib | `tests/Modules/Ast/QrTest.php::test_SRS_AST_07_label_contains_scannable_qr_of_public_link` | — |
| SRS-AST-08 | Wajib | `tests/Modules/Ast/QrTest.php::test_SRS_AST_08_09_public_scan_without_account_shows_minimum_identification_only` | — |
| SRS-AST-09 | Wajib | `tests/Modules/Ast/QrTest.php::test_SRS_AST_09_registered_scanner_in_scope_gets_full_profile_actions` | — |
| SRS-AST-10 | Perlu | `tests/Modules/Ast/QrTest.php::test_SRS_AST_10_every_scan_is_logged_with_time_identity_and_device_location` | — |
| SRS-AST-11 | Perlu | `tests/Modules/Ast/MediaTagTest.php::test_SRS_AST_11_free_and_structured_tags_filter_and_report` | — |
| SRS-AST-12 | Wajib | `tests/Modules/Ast/MediaTagTest.php::test_SRS_AST_12_dated_photo_gallery_in_chronological_order_with_gps_and_officer` | — |
| SRS-AST-13 | Wajib | `tests/Modules/Ast/DaftarAsetTest.php::test_SRS_AST_13_registered_asset_appears_on_the_gis_layer_at_its_coordinates` | — |
| SRS-AST-13 | Wajib | `tests/Modules/Ast/DaftarAsetTest.php::test_SRS_AST_13_asset_without_gps_is_placed_on_its_lot` | — |
| SRS-AST-13 | Wajib | `tests/Modules/Ast/LaporanIntegrasiTest.php::test_SRS_AST_13_import_interim_gis_points_once` | — |
| SRS-AST-14 | Wajib | `tests/Modules/Ast/PenyelenggaraanTest.php::test_SRS_AST_14_maintenance_history_records_type_date_cost_contractor_status` | — |
| SRS-AST-14 | Wajib | `tests/Modules/Ast/PenyelenggaraanTest.php::test_SRS_AST_14_contractor_sees_and_updates_only_its_own_work` | — |
| SRS-AST-14 | Wajib | `tests/Modules/Ast/PenyelenggaraanTest.php::test_SRS_AST_14_ptn_maintenance_needs_become_work_orders_and_are_linked_back` | — |
| SRS-AST-14 | Wajib | `tests/Modules/Ast/PenyelenggaraanTest.php::test_SRS_AST_14_ptn_need_on_lot_with_several_assets_is_matched_manually` | — |
| SRS-AST-15 | Wajib | `tests/Modules/Ast/PenyelenggaraanTest.php::test_SRS_AST_15_schedule_generated_by_category_cycle_and_shown_in_calendar` | — |
| SRS-AST-15 | Wajib | `tests/Modules/Ast/PenyelenggaraanTest.php::test_SRS_AST_15_completion_rolls_the_schedule_forward` | — |
| SRS-AST-16 | Wajib | `tests/Modules/Ast/PendaftaranModulTest.php::test_SRS_AST_16_module_registers_permissions_notification_types_reminders_and_wfe_flows` | — |
| SRS-AST-16 | Wajib | `tests/Modules/Ast/PenyelenggaraanTest.php::test_SRS_AST_16_reminder_within_configured_warning_period_and_escalation_when_overdue` | — |
| SRS-AST-17 | Wajib | `tests/Modules/Ast/PenyelenggaraanTest.php::test_SRS_AST_17_cost_flows_into_the_lot_cumulative_cost_chart` | — |
| SRS-AST-17 | Wajib | `tests/Modules/Ast/PenyelenggaraanTest.php::test_SRS_AST_17_estimate_above_threshold_needs_wfe_approval` | — |
| SRS-AST-18 | Wajib | `tests/Modules/Ast/KondisiNilaiTest.php::test_SRS_AST_18_value_update_keeps_yearly_history_with_report_reference` | — |
| SRS-AST-18 | Wajib | `tests/Modules/Ast/KondisiNilaiTest.php::test_SRS_AST_18_book_value_by_straight_line_depreciation_in_decimal` | — |
| SRS-AST-19 | Wajib | `tests/Modules/Ast/KondisiNilaiTest.php::test_SRS_AST_19_revaluation_reminder_when_threshold_period_reached` | — |
| SRS-AST-20 | Wajib | `tests/Modules/Ast/PemeriksaanTest.php::test_SRS_AST_20_field_app_downloads_in_scope_assets_for_offline_use` | — |
| SRS-AST-20 | Wajib | `tests/Modules/Ast/PemeriksaanTest.php::test_SRS_AST_20_offline_inspection_syncs_idempotently_without_losing_records` | — |
| SRS-AST-20 | Wajib | `tests/Modules/Ast/PemeriksaanTest.php::test_SRS_AST_20_older_offline_inspection_does_not_overwrite_newer_condition` | — |
| SRS-AST-20 | Wajib | `tests/Modules/Ast/PemeriksaanTest.php::test_SRS_AST_20_out_of_scope_asset_cannot_be_synced` | — |
| SRS-AST-20 | Wajib | `tests/Modules/Ast/PemeriksaanTest.php::test_SRS_AST_20_inspection_after_qr_scan_can_raise_corrective_work` | — |
| SRS-AST-21 | Wajib | `tests/Modules/Ast/PemeriksaanTest.php::test_SRS_AST_21_last_inspection_date_and_assets_not_inspected_beyond_period` | — |
| SRS-AST-22 | Perlu | `tests/Modules/Ast/MediaTagTest.php::test_SRS_AST_22_supporting_documents_through_dms_with_expiry` | — |
| SRS-AST-22 | Perlu | `tests/Modules/Ast/MediaTagTest.php::test_SRS_AST_22_insurance_and_warranty_with_expiry_reminders` | — |
| SRS-AST-23 | Wajib | `tests/Modules/Ast/KondisiNilaiTest.php::test_SRS_AST_23_value_change_by_unauthorised_role_rejected_and_authorised_change_audited` | — |
| SRS-AST-23 | Wajib | `tests/Modules/Ast/KondisiNilaiTest.php::test_SRS_AST_23_condition_and_responsible_officer_changes_audited_append_only` | — |
| SRS-AST-24 | Wajib | `tests/Modules/Ast/LaporanIntegrasiTest.php::test_SRS_AST_24_inventory_report_by_category_district_condition_and_value` | — |
| SRS-AST-24 | Wajib | `tests/Modules/Ast/LaporanIntegrasiTest.php::test_SRS_AST_24_dashboard_kpis_respect_scope` | — |
| SRS-AST-25 | Wajib | `tests/Modules/Ast/LaporanIntegrasiTest.php::test_SRS_AST_25_portfolio_value_by_category_district_and_year_comparison` | — |
| SRS-AST-26 | Wajib | `tests/Modules/Ast/LaporanIntegrasiTest.php::test_SRS_AST_26_critical_assets_annual_cost_by_asset_and_category_and_export` | — |
| SRS-AUD-01 | Wajib | `tests/Modules/Aud/AuditRecordingTest.php::test_SRS_AUD_01_create_update_delete_of_any_model_is_recorded` | — |
| SRS-AUD-02 | Wajib | `tests/Modules/Aud/AuditRecordingTest.php::test_SRS_AUD_02_explicit_entries_for_view_download_print_approval_and_ai_prompt` | — |
| SRS-AUD-02 | Wajib | `tests/Modules/Aud/AuditRecordingTest.php::test_SRS_AUD_02_role_assignment_changes_are_recorded` | — |
| SRS-AUD-03 | Wajib | `tests/Modules/Aud/AuditRecordingTest.php::test_SRS_AUD_03_reason_from_scope_helper_and_from_request_header` | — |
| SRS-AUD-03 | Wajib | `tests/Modules/Aud/AuditRecordingTest.php::test_SRS_AUD_03_rolled_back_change_is_recorded_once_as_failed` | — |
| SRS-AUD-03 | Wajib | `tests/Modules/Aud/AuditRecordingTest.php::test_SRS_AUD_03_change_inside_committed_transaction_is_recorded_after_commit` | — |
| SRS-AUD-04 | Wajib | `tests/Modules/Aud/AuditRecordingTest.php::test_SRS_AUD_04_records_session_device_channel_entity_and_correlation_id` | — |
| SRS-AUD-04 | Wajib | `tests/Modules/Aud/AuditRecordingTest.php::test_SRS_AUD_04_console_work_is_recorded_as_system_channel` | — |
| SRS-AUD-05 | Wajib | `tests/Modules/Aud/AuditRecordingTest.php::test_SRS_AUD_05_single_field_change_records_field_old_new_user_role_ip_module_time` | — |
| SRS-AUD-06 | Wajib | `tests/Modules/Aud/AuditIntegrityTest.php::test_SRS_AUD_06_records_form_a_gapless_sha256_chain_over_content_and_field_changes` | — |
| SRS-AUD-07 | Wajib | `tests/Modules/Aud/AuditIntegrityTest.php::test_SRS_AUD_07_direct_tampering_is_detected_and_alerts` | — |
| SRS-AUD-07 | Wajib | `tests/Modules/Aud/AuditIntegrityTest.php::test_SRS_AUD_07_deleted_or_altered_field_change_and_truncated_tail_are_detected` | — |
| SRS-AUD-07 | Wajib | `tests/Modules/Aud/AuditIntegrityTest.php::test_SRS_AUD_07_removed_records_break_the_sequence` | — |
| SRS-AUD-07 | Wajib | `tests/Modules/Aud/AuditIntegrityTest.php::test_SRS_AUD_07_integrity_check_is_scheduled` | — |
| SRS-AUD-07 | Wajib | `tests/Modules/Aud/AuditIntegrityTest.php::test_SRS_AUD_07_integrity_status_api_and_manual_check` | — |
| SRS-AUD-08 | Wajib | `tests/Modules/Aud/AuditIntegrityTest.php::test_SRS_AUD_08_app_database_account_cannot_update_delete_or_truncate_audit_records` | — |
| SRS-AUD-08 | Wajib | `tests/Modules/Aud/AuditIntegrityTest.php::test_SRS_AUD_08_triggers_reject_changes_even_when_privileges_are_regranted` | — |
| SRS-AUD-08 | Wajib | `tests/Modules/Aud/AuditIntegrityTest.php::test_SRS_AUD_08_application_interface_rejects_update_and_delete_for_all_roles` | — |
| SRS-AUD-09 | Wajib | `tests/Modules/Aud/AuditRecordingTest.php::test_SRS_AUD_09_global_listener_covers_a_model_with_no_audit_code_at_all` | — |
| SRS-AUD-09 | Wajib | `tests/Modules/Aud/AuditRecordingTest.php::test_SRS_AUD_09_framework_and_audit_tables_are_not_audited` | — |
| SRS-AUD-10 | Wajib | `tests/Modules/Aud/AuditSearchExportTest.php::test_SRS_AUD_10_combined_user_module_action_and_period_filters` | — |
| SRS-AUD-11 | Wajib | `tests/Modules/Aud/AuditSearchExportTest.php::test_SRS_AUD_11_record_chronology_spans_modules_in_time_order` | — |
| SRS-AUD-12 | Wajib | `tests/Modules/Aud/AuditSearchExportTest.php::test_SRS_AUD_12_export_in_all_four_formats_with_checksum_and_metadata` | — |
| SRS-AUD-13 | Perlu | `tests/Modules/Aud/AuditSearchExportTest.php::test_SRS_AUD_13_export_history_lists_checksums` | — |
| SRS-AUD-14 | Wajib | `tests/Modules/Aud/AuditSearchExportTest.php::test_SRS_AUD_14_search_view_chronology_and_export_of_the_trail_are_audited` | — |
| SRS-AUD-15 | Wajib | `tests/Modules/Aud/AuditRecordingTest.php::test_SRS_AUD_15_classification_is_taken_from_model` | — |
| SRS-AUD-15 | Wajib | `tests/Modules/Aud/AuditSearchExportTest.php::test_SRS_AUD_15_automatic_reports` | — |
| SRS-AUD-15 | Wajib | `tests/Modules/Aud/AuditSearchExportTest.php::test_SRS_AUD_15_bulk_activity_report_flags_users_above_threshold` | — |
| SRS-AUD-16 | Wajib | `tests/Modules/Aud/AuditRecordingTest.php::test_SRS_AUD_16_document_activity_entries` | — |
| SRS-AUD-17 | Wajib | `tests/Modules/Aud/AuditRecordingTest.php::test_SRS_AUD_17_database_notifications_are_recorded_automatically` | — |
| SRS-AUD-18 | Wajib | `tests/Modules/Aud/AuditRecordingTest.php::test_SRS_AUD_18_workflow_decision_entries_keep_approver_decision_and_stage` | — |
| SRS-AUD-18 | Wajib | `tests/Modules/Wfe/DecisionTest.php::test_SRS_AUD_18_decisions_are_explicit_audit_entries_with_justification` | — |
| SRS-AUD-19 | Wajib | `tests/Modules/Aud/AuditRecordingTest.php::test_SRS_AUD_19_password_login_success_and_failure_are_recorded_with_ip_device_location` | — |
| SRS-AUD-19 | Wajib | `tests/Modules/Aud/AuditRecordingTest.php::test_SRS_AUD_19_rbc_events_are_recorded` | — |
| SRS-AUD-20 | Wajib | `tests/Modules/Aud/AuditRetentionSiemTest.php::test_SRS_AUD_20_records_past_online_retention_are_archived_by_the_scheduled_job` | — |
| SRS-AUD-20 | Wajib | `tests/Modules/Aud/AuditRetentionSiemTest.php::test_SRS_AUD_20_policy_is_configurable_but_never_below_24_months` | — |
| SRS-AUD-21 | Wajib | `tests/Modules/Aud/AuditRetentionSiemTest.php::test_SRS_AUD_21_archived_records_stay_searchable_and_archive_access_is_audited` | — |
| SRS-AUD-22 | Wajib | `tests/Modules/Aud/AuditRetentionSiemTest.php::test_SRS_AUD_22_records_are_streamed_to_siem_as_json_lines` | — |
| SRS-AUD-22 | Wajib | `tests/Modules/Aud/AuditRetentionSiemTest.php::test_SRS_AUD_22_cef_format_for_syslog_siem` | — |
| SRS-AUD-23 | Wajib | `tests/Modules/Aud/AuditSearchExportTest.php::test_SRS_AUD_23_access_is_limited_to_audit_permissions_and_read_only` | — |
| SRS-AUD-24 | Perlu | `tests/Modules/Aud/AuditRetentionSiemTest.php::test_SRS_AUD_24_timestamps_are_stored_in_utc_and_malaysia_time` | — |
| SRS-AUD-25 | Wajib | `tests/Modules/Aud/AuditRecordingTest.php::test_SRS_AUD_25_passwords_tokens_and_ic_numbers_are_masked` | — |
| SRS-AUD-26 | Perlu | `tests/Modules/Aud/AuditRecordingTest.php::test_SRS_AUD_26_audit_failure_does_not_fail_the_business_transaction` | — |
| SRS-CKI-01 | Wajib | `tests/Modules/Cki/DaftarBilTest.php::test_SRS_CKI_01_obligation_is_linked_to_a_lot_in_the_master_register` | — |
| SRS-CKI-01 | Wajib | `tests/Modules/Cki/DaftarBilTest.php::test_SRS_CKI_01_district_officer_cannot_reach_another_districts_lot_or_bill` | — |
| SRS-CKI-01 | Wajib | `tests/Modules/Cki/DaftarBilTest.php::test_SRS_CKI_01_endpoints_require_cki_permissions` | — |
| SRS-CKI-02 | Wajib | `tests/Modules/Cki/DaftarBilTest.php::test_SRS_CKI_02_bill_records_type_issuer_account_year_principal_penalty_due_date_and_status` | — |
| SRS-CKI-02 | Wajib | `tests/Modules/Cki/DaftarBilTest.php::test_SRS_CKI_02_bills_and_receipts_cannot_be_deleted_and_corrections_are_justified_adjustments` | — |
| SRS-CKI-02 | Wajib | `tests/Modules/Cki/ModulBerkaitanTest.php::test_SRS_CKI_02_global_search_finds_bills_by_bill_account_or_lot_number_within_scope` | — |
| SRS-CKI-02 | Wajib | `tests/Modules/Cki/PemantauanTest.php::test_SRS_CKI_02_excel_import_validates_row_by_row_and_reports_accepted_and_rejected` | — |
| SRS-CKI-03 | Wajib | `tests/Modules/Cki/PemantauanTest.php::test_SRS_CKI_03_payment_schedule_for_planning_period_grouped_by_district_pbt_and_due_date` | — |
| SRS-CKI-04 | Wajib | `tests/Modules/Cki/IntegrasiTest.php::test_SRS_CKI_04_central_provider_serves_land_office_and_pbt_bills_and_direct_payment_with_receipt` | — |
| SRS-CKI-05 | Wajib | `tests/Modules/Cki/IntegrasiTest.php::test_SRS_CKI_05_daily_check_updates_100_lots_in_coverage_without_manual_entry` | — |
| SRS-CKI-05 | Wajib | `tests/Modules/Cki/ModulBerkaitanTest.php::test_SRS_CKI_05_sample_command_generates_flagged_synthetic_bills_for_existing_lots` | — |
| SRS-CKI-06 | Wajib | `tests/Modules/Cki/DaftarBilTest.php::test_SRS_CKI_06_coverage_register_per_land_office_and_pbt_flags_every_obligation` | — |
| SRS-CKI-07 | Wajib | `tests/Modules/Cki/DaftarBilTest.php::test_SRS_CKI_07_manual_fallback_only_outside_coverage_or_in_fallback_mode_with_bill_image` | — |
| SRS-CKI-08 | Wajib | `tests/Modules/Cki/IntegrasiTest.php::test_SRS_CKI_08_outage_switches_to_fallback_notifies_officers_and_recovers_automatically` | — |
| SRS-CKI-09 | Wajib | `tests/Modules/Cki/IntegrasiTest.php::test_SRS_CKI_09_resync_conflict_needs_officer_confirmation_and_keeps_both_versions` | — |
| SRS-CKI-09 | Wajib | `tests/Modules/Cki/IntegrasiTest.php::test_SRS_CKI_09_officer_may_keep_the_manual_version` | — |
| SRS-CKI-10 | Wajib | `tests/Modules/Cki/IntegrasiTest.php::test_SRS_CKI_10_every_call_is_logged_retried_masked_and_persistent_failure_escalated` | — |
| SRS-CKI-10 | Wajib | `tests/Modules/Cki/IntegrasiTest.php::test_SRS_CKI_10_on_demand_check_and_outage_simulation_via_api` | — |
| SRS-CKI-11 | Wajib | `tests/Modules/Cki/BayaranTest.php::test_SRS_CKI_11_eft_and_bank_draft_go_to_grp_mais_with_a_request_document_and_status_back` | — |
| SRS-CKI-12 | Wajib | `tests/Modules/Cki/BayaranTest.php::test_SRS_CKI_12_each_of_the_five_methods_requires_its_own_fields` | — |
| SRS-CKI-13 | Wajib | `tests/Modules/Cki/BayaranTest.php::test_SRS_CKI_13_no_transaction_completes_without_a_receipt_or_proof_for_every_method` | — |
| SRS-CKI-14 | Wajib | `tests/Modules/Cki/BayaranTest.php::test_SRS_CKI_14_value_threshold_decides_approval_level_through_the_central_workflow` | — |
| SRS-CKI-14 | Wajib | `tests/Modules/Cki/BayaranTest.php::test_SRS_CKI_14_payment_cannot_execute_without_correct_level_approval_and_the_attempt_is_audited` | — |
| SRS-CKI-14 | Wajib | `tests/Modules/Cki/ModulBerkaitanTest.php::test_SRS_CKI_14_flow_is_declared_and_seeded_in_the_workflow_engine` | — |
| SRS-CKI-15 | Wajib | `tests/Modules/Cki/BayaranTest.php::test_SRS_CKI_15_direct_api_payment_saves_receipt_to_dms_updates_ledger_and_creates_grp_reconciliation_record` | — |
| SRS-CKI-15 | Wajib | `tests/Modules/Cki/BayaranTest.php::test_SRS_CKI_15_fpx_through_api_is_refused_when_the_agency_has_no_payment_service` | — |
| SRS-CKI-16 | Wajib | `tests/Modules/Cki/DaftarBilTest.php::test_SRS_CKI_16_documents_attach_to_the_bill_with_metadata_and_lot_link` | — |
| SRS-CKI-17 | Wajib | `tests/Modules/Cki/PemantauanTest.php::test_SRS_CKI_17_reminders_fire_exactly_30_14_and_7_days_before_due_date` | — |
| SRS-CKI-18 | Wajib | `tests/Modules/Cki/PemantauanTest.php::test_SRS_CKI_18_overdue_penalty_and_out_of_coverage_alerts` | — |
| SRS-CKI-19 | Perlu | `tests/Modules/Cki/PemantauanTest.php::test_SRS_CKI_19_lot_timeline_across_years_with_receipts_and_penalties_exportable` | — |
| SRS-CKI-20 | Wajib | `tests/Modules/Cki/PemantauanTest.php::test_SRS_CKI_20_reconciliation_lists_the_three_deviation_cases` | — |
| SRS-CKI-20 | Wajib | `tests/Modules/Cki/PemantauanTest.php::test_SRS_CKI_20_payment_missing_from_grp_is_a_deviation` | — |
| SRS-CKI-21 | Wajib | `tests/Modules/Cki/ModulBerkaitanTest.php::test_SRS_CKI_21_kpis_for_the_executive_dashboard_respect_district_scope` | — |
| SRS-CKI-21 | Wajib | `tests/Modules/Cki/ModulBerkaitanTest.php::test_SRS_CKI_21_profile_section_shows_tax_position_timeline_and_kos_cukai_series` | — |
| SRS-CKI-21 | Wajib | `tests/Modules/Cki/ModulBerkaitanTest.php::test_SRS_CKI_21_gis_status_layer_colours_lots_by_payment_status_and_respects_scope` | — |
| SRS-CKI-21 | Wajib | `tests/Modules/Cki/PemantauanTest.php::test_SRS_CKI_21_dashboard_totals_paid_arrears_penalties_and_late_risk_filterable_and_scoped` | — |
| SRS-CKI-22 | Perlu | `tests/Modules/Cki/PemantauanTest.php::test_SRS_CKI_22_portfolio_position_by_district_pbt_type_and_arrears_age_with_annual_budget_estimate` | — |
| SRS-CKI-23 | Wajib | `tests/Modules/Cki/PemantauanTest.php::test_SRS_CKI_23_reports_with_pdf_and_excel_export` | — |
| SRS-CKI-24 | Wajib | `tests/Modules/Cki/DaftarBilTest.php::test_SRS_CKI_24_agencies_without_api_service_are_handled_by_structured_manual_fallback` | — |
| SRS-DAT-01 | Wajib | `tests/Modules/Pro/LotMasterTest.php::test_SRS_DAT_01_lot_number_is_unique_per_mukim_even_when_written_differently` | — |
| SRS-DAT-01 | Wajib | `tests/Modules/Pro/LotMasterTest.php::test_SRS_DAT_01_daerah_must_match_the_mukim` | — |
| SRS-DAT-01 | Wajib | `tests/Modules/Ref/SampleLotsTest.php::test_SRS_DAT_01_no_duplicate_lot_number_within_a_mukim` | — |
| SRS-DAT-01 | Wajib | `tests/Modules/Ref/SampleLotsTest.php::test_SRS_DAT_01_lot_fields_respect_format_and_valid_ranges` | — |
| SRS-DAT-02 | Wajib | `tests/Modules/Pro/GovernanceTest.php::test_SRS_DAT_02_known_duplicate_pairs_are_all_found_and_never_auto_merged` | — |
| SRS-DAT-03 | Wajib | `tests/Modules/Pro/ImportTest.php::test_SRS_DAT_03_csv_with_mixed_rows_commits_only_valid_rows_with_full_report` | — |
| SRS-DAT-03 | Wajib | `tests/Modules/Pro/ImportTest.php::test_SRS_DAT_03_excel_with_mixed_rows_commits_only_valid_rows` | — |
| SRS-DAT-04 | Wajib | `tests/Modules/Pro/LotMasterTest.php::test_SRS_DAT_04_versions_keep_old_values_and_can_be_compared` | — |
| SRS-DAT-05 | Wajib | `tests/Modules/Ref/ReferenceApiTest.php::test_SRS_DAT_05_api_lists_districts_with_their_codes` | — |
| SRS-DAT-05 | Wajib | `tests/Modules/Ref/ReferenceApiTest.php::test_SRS_DAT_05_api_filters_mukim_and_pbt_by_district` | — |
| SRS-DAT-05 | Wajib | `tests/Modules/Ref/ReferenceApiTest.php::test_SRS_DAT_05_api_exposes_versions_and_as_of_dates` | — |
| SRS-DAT-05 | Wajib | `tests/Modules/Ref/ReferenceCodesTest.php::test_SRS_DAT_05_seeds_all_selangor_districts_mukims_and_local_authorities` | — |
| SRS-DAT-05 | Wajib | `tests/Modules/Ref/ReferenceCodesTest.php::test_SRS_DAT_05_starter_lists_cover_land_use_title_types_and_classification` | — |
| SRS-DAT-05 | Wajib | `tests/Modules/Ref/ReferenceCodesTest.php::test_SRS_DAT_05_database_rejects_a_second_current_row_for_the_same_code` | — |
| SRS-DAT-05 | Wajib | `tests/Modules/Ref/ReferenceCodesTest.php::test_SRS_DAT_05_database_rejects_duplicate_names_within_a_district_but_allows_them_across_districts` | — |
| SRS-DAT-05 | Wajib | `tests/Modules/Ref/ReferenceCodesTest.php::test_SRS_DAT_05_a_change_creates_a_new_version_and_keeps_the_old_values` | — |
| SRS-DAT-05 | Wajib | `tests/Modules/Ref/ReferenceCodesTest.php::test_SRS_DAT_05_reseeding_unchanged_data_is_a_no_op` | — |
| SRS-DAT-05 | Wajib | `tests/Modules/Ref/ReferenceCodesTest.php::test_SRS_DAT_05_a_code_removed_from_the_source_is_closed_not_deleted` | — |
| SRS-DAT-06 | Wajib | `tests/Modules/Pro/GovernanceTest.php::test_SRS_DAT_06_retention_schedule_is_configurable_per_category` | — |
| SRS-DAT-07 | Wajib | `tests/Modules/Pro/GovernanceTest.php::test_SRS_DAT_07_physical_model_lists_indexes_views_and_stored_procedures` | — |
| SRS-DAT-08 | Wajib | `tests/Modules/Pro/ImportTest.php::test_SRS_DAT_08_staging_import_keeps_lineage_and_is_idempotent` | — |
| SRS-DAT-08 | Wajib | `tests/Modules/Ref/ReferenceDataFilesTest.php::test_SRS_DAT_08_manifest_records_source_licence_date_and_count_for_every_osm_dataset` | — |
| SRS-DAT-08 | Wajib | `tests/Modules/Ref/SampleLotsTest.php::test_SRS_DAT_08_every_staging_row_records_its_lineage_and_load_run` | — |
| SRS-DAT-08 | Wajib | `tests/Modules/Ref/SampleLotsTest.php::test_SRS_DAT_08_rerunning_the_same_seed_replaces_its_rows_with_identical_keys` | — |
| SRS-DAT-09 | Perlu | `tests/Modules/Pro/GovernanceTest.php::test_SRS_DAT_09_scheduled_report_routes_problem_records_to_stewards` | — |
| SRS-DMS-01 | Wajib | `tests/Modules/Dms/FilesAndAuditTest.php::test_SRS_DMS_01_single_repository_for_every_module` | — |
| SRS-DMS-02 | Wajib | `tests/Modules/Dms/FilesAndAuditTest.php::test_SRS_DMS_02_pdf_and_images_preview_inline_in_the_browser` | — |
| SRS-DMS-02 | Wajib | `tests/Modules/Dms/UploadValidationTest.php::test_SRS_DMS_02_batch_upload_of_twenty_files_reports_each_file` | — |
| SRS-DMS-02 | Wajib | `tests/Modules/Dms/UploadValidationTest.php::test_SRS_DMS_02_batch_limit_is_enforced` | — |
| SRS-DMS-03 | Wajib | `tests/Modules/Dms/UploadValidationTest.php::test_SRS_DMS_03_default_whitelist_accepts_every_listed_type` | — |
| SRS-DMS-03 | Wajib | `tests/Modules/Dms/UploadValidationTest.php::test_SRS_DMS_03_type_removed_from_whitelist_by_admin_is_rejected` | — |
| SRS-DMS-03 | Wajib | `tests/Modules/Dms/UploadValidationTest.php::test_SRS_DMS_03_category_can_narrow_allowed_types` | — |
| SRS-DMS-03 | Wajib | `tests/Modules/Dms/UploadValidationTest.php::test_SRS_DMS_03_shapefile_zip_must_be_a_complete_shapefile_set` | — |
| SRS-DMS-04 | Wajib | `tests/Modules/Dms/UploadValidationTest.php::test_SRS_DMS_04_renamed_file_is_rejected_by_content_signature` | — |
| SRS-DMS-05 | Wajib | `tests/Modules/Dms/UploadValidationTest.php::test_SRS_DMS_05_category_size_limit_with_clear_message_and_admin_change_without_release` | — |
| SRS-DMS-05 | Wajib | `tests/Modules/Dms/UploadValidationTest.php::test_SRS_DMS_05_spatial_files_get_the_larger_default_limit` | — |
| SRS-DMS-06 | Perlu | `tests/Modules/Dms/UploadValidationTest.php::test_SRS_DMS_06_cumulative_transaction_limit_across_requests` | — |
| SRS-DMS-07 | Wajib | `tests/Modules/Dms/AntivirusTest.php::test_SRS_DMS_07_infected_file_is_not_stored_but_quarantined_encrypted_and_audited` | — |
| SRS-DMS-07 | Wajib | `tests/Modules/Dms/AntivirusTest.php::test_SRS_DMS_07_scan_happens_before_anything_is_persisted_and_clean_scans_are_recorded` | — |
| SRS-DMS-07 | Wajib | `tests/Modules/Dms/AntivirusTest.php::test_SRS_DMS_07_scanner_outage_fails_closed` | — |
| SRS-DMS-07 | Wajib | `tests/Modules/Dms/AntivirusTest.php::test_SRS_DMS_07_admin_lists_and_destroys_quarantined_files` | — |
| SRS-DMS-07 | Wajib | `tests/Modules/Dms/AntivirusTest.php::test_SRS_DMS_07_real_clamd_detects_eicar` | — |
| SRS-DMS-08 | Perlu | `tests/Modules/Dms/AntivirusTest.php::test_SRS_DMS_08_quarantine_notifies_system_administrators_and_the_uploader` | — |
| SRS-DMS-09 | Wajib | `tests/Modules/Dms/UploadValidationTest.php::test_SRS_DMS_09_executables_scripts_macro_documents_and_extensionless_files_are_blocked` | — |
| SRS-DMS-09 | Wajib | `tests/Modules/Dms/UploadValidationTest.php::test_SRS_DMS_09_macro_inside_a_docx_named_file_is_detected` | — |
| SRS-DMS-09 | Wajib | `tests/Modules/Dms/UploadValidationTest.php::test_SRS_DMS_09_archives_nested_more_than_one_level_are_blocked` | — |
| SRS-DMS-10 | Wajib | `tests/Modules/Dms/MetadataAndLinkingTest.php::test_SRS_DMS_10_structured_metadata_is_stored` | — |
| SRS-DMS-10 | Wajib | `tests/Modules/Dms/MetadataAndLinkingTest.php::test_SRS_DMS_10_metadata_update_keeps_extra_fields_and_resets_expiry_reminder` | — |
| SRS-DMS-11 | Wajib | `tests/Modules/Dms/MetadataAndLinkingTest.php::test_SRS_DMS_11_admin_adds_changes_and_deactivates_metadata_fields_without_code` | — |
| SRS-DMS-12 | Wajib | `tests/Modules/Dms/AccessAndSearchTest.php::test_SRS_DMS_12_full_text_search_over_pdf_content_and_metadata_filters` | — |
| SRS-DMS-12 | Wajib | `tests/Modules/Dms/AccessAndSearchTest.php::test_SRS_DMS_12_scanned_pdf_is_ocrd_and_found_by_its_words_but_not_by_unauthorised_users` | — |
| SRS-DMS-12 | Wajib | `tests/Modules/Dms/AccessAndSearchTest.php::test_SRS_DMS_12_office_and_spatial_text_is_extracted` | — |
| SRS-DMS-13 | Wajib | `tests/Modules/Dms/AccessAndSearchTest.php::test_SRS_DMS_13_search_results_and_counts_exclude_documents_out_of_scope` | — |
| SRS-DMS-13 | Wajib | `tests/Modules/Dms/AccessAndSearchTest.php::test_SRS_DMS_13_php_and_sql_access_rules_agree` | — |
| SRS-DMS-14 | Wajib | `tests/Modules/Dms/VersioningTest.php::test_SRS_DMS_14_new_version_keeps_the_previous_one_and_both_are_retrievable` | — |
| SRS-DMS-14 | Wajib | `tests/Modules/Dms/VersioningTest.php::test_SRS_DMS_14_versions_can_be_compared` | — |
| SRS-DMS-14 | Wajib | `tests/Modules/Dms/VersioningTest.php::test_SRS_DMS_14_new_versions_go_through_the_same_checks` | — |
| SRS-DMS-15 | Wajib | `tests/Modules/Dms/LifecycleTest.php::test_SRS_DMS_15_expiry_reminder_goes_to_the_process_owner_once_and_expired_documents_become_tamat` | — |
| SRS-DMS-16 | Wajib | `tests/Modules/Dms/MetadataAndLinkingTest.php::test_SRS_DMS_16_documents_attach_to_any_record_and_list_from_the_record` | — |
| SRS-DMS-17 | Wajib | `tests/Modules/Dms/MetadataAndLinkingTest.php::test_SRS_DMS_17_document_shows_its_parent_with_an_access_checked_deep_link` | — |
| SRS-DMS-18 | Wajib | `tests/Modules/Dms/FilesAndAuditTest.php::test_SRS_DMS_18_api_rejects_users_without_permission` | — |
| SRS-DMS-18 | Wajib | `tests/Modules/Dms/MetadataAndLinkingTest.php::test_SRS_DMS_18_other_modules_use_the_document_store_contract` | — |
| SRS-DMS-18 | Wajib | `tests/Modules/Dms/MetadataAndLinkingTest.php::test_SRS_DMS_18_document_store_rejects_unknown_category_in_malay` | — |
| SRS-DMS-19 | Wajib | `tests/Modules/Dms/AccessAndSearchTest.php::test_SRS_DMS_19_sulit_and_rahsia_need_explicit_permission` | — |
| SRS-DMS-19 | Wajib | `tests/Modules/Dms/AccessAndSearchTest.php::test_SRS_DMS_19_restricted_upload_and_reclassification_need_the_clearance` | — |
| SRS-DMS-19 | Wajib | `tests/Modules/Rbc/AccessControlTest.php::test_SRS_DMS_19_wildcard_base_roles_never_get_sulit_or_rahsia_access` | — |
| SRS-DMS-20 | Wajib | `tests/Modules/Dms/AntivirusTest.php::test_SRS_DMS_20_documents_are_encrypted_at_rest_with_per_file_keys_and_sha256` | — |
| SRS-DMS-20 | Wajib | `tests/Modules/Dms/AntivirusTest.php::test_SRS_DMS_20_tampering_and_wrong_keys_are_detected` | — |
| SRS-DMS-21 | Wajib | `tests/Modules/Dms/LifecycleTest.php::test_SRS_DMS_21_retention_policy_is_configurable_per_category` | — |
| SRS-DMS-22 | Wajib | `tests/Modules/Dms/FilesAndAuditTest.php::test_SRS_DMS_22_signed_links_are_short_lived_user_bound_and_tamper_proof` | — |
| SRS-DMS-22 | Wajib | `tests/Modules/Dms/LifecycleTest.php::test_SRS_DMS_22_documents_past_active_retention_are_archived_automatically_and_stay_searchable` | — |
| SRS-DMS-23 | Wajib | `tests/Modules/Dms/LifecycleTest.php::test_SRS_DMS_23_archived_documents_cannot_be_deleted_or_disposed_without_two_stage_approval` | — |
| SRS-DMS-23 | Wajib | `tests/Modules/Dms/LifecycleTest.php::test_SRS_DMS_23_approved_disposal_destroys_content_keeps_metadata_and_audits_justification` | — |
| SRS-DMS-23 | Wajib | `tests/Modules/Dms/LifecycleTest.php::test_SRS_DMS_23_requester_cannot_be_the_approver` | — |
| SRS-DMS-23 | Wajib | `tests/Modules/Dms/LifecycleTest.php::test_SRS_DMS_23_disposal_without_workflow_engine_is_refused` | — |
| SRS-DMS-23 | Wajib | `tests/Modules/Dms/LifecycleTest.php::test_SRS_DMS_23_active_documents_are_only_logically_deleted` | — |
| SRS-DMS-24 | Wajib | `tests/Modules/Dms/FilesAndAuditTest.php::test_SRS_DMS_24_upload_download_preview_print_view_archive_are_audited` | — |
| SRS-DMS-25 | Perlu | `tests/Modules/Dms/FilesAndAuditTest.php::test_SRS_DMS_25_quota_usage_is_shown_and_80_percent_alerts_admins` | — |
| SRS-DMS-26 | Baik-ada | `tests/Modules/Dms/FilesAndAuditTest.php::test_SRS_DMS_26_sulit_preview_and_print_carry_a_dynamic_watermark` | — |
| SRS-DMS-26 | Baik-ada | `tests/Modules/Dms/FilesAndAuditTest.php::test_SRS_DMS_26_watermarker_handles_pdfs_the_free_parser_cannot_read` | — |
| SRS-DSH-01 | Wajib | `tests/Modules/Dsh/ArasDashboardTest.php::test_SRS_DSH_01_three_levels_are_chosen_automatically_from_the_users_role` | — |
| SRS-DSH-01 | Wajib | `tests/Modules/Dsh/ArasDashboardTest.php::test_SRS_DSH_01_without_a_role_mapping_the_highest_permitted_level_is_shown` | — |
| SRS-DSH-01 | Wajib | `tests/Modules/Dsh/ArasDashboardTest.php::test_SRS_DSH_01_district_and_division_users_get_their_variant_never_the_state_view` | — |
| SRS-DSH-01 | Wajib | `tests/Modules/Dsh/ArasDashboardTest.php::test_SRS_DSH_01_user_without_permission_is_refused` | — |
| SRS-DSH-02 | Wajib | `tests/Modules/Dsh/KpiRegistryTest.php::test_SRS_DSH_02_registry_is_a_singleton_holding_every_modules_kpis` | — |
| SRS-DSH-02 | Wajib | `tests/Modules/Dsh/KpiRegistryTest.php::test_SRS_DSH_02_kpi_registered_after_the_registry_was_resolved_is_still_shown` | — |
| SRS-DSH-02 | Wajib | `tests/Modules/Dsh/KpiRegistryTest.php::test_SRS_DSH_02_kpi_is_hidden_without_its_permission` | — |
| SRS-DSH-02 | Wajib | `tests/Modules/Dsh/KpiRegistryTest.php::test_SRS_DSH_02_a_failing_provider_yields_an_error_entry_not_a_broken_dashboard` | — |
| SRS-DSH-02 | Wajib | `tests/Modules/Dsh/PortfolioKpiTest.php::test_SRS_DSH_02_portfolio_size_area_and_breakdowns` | — |
| SRS-DSH-02 | Wajib | `tests/Modules/Dsh/PortfolioKpiTest.php::test_SRS_DSH_02_district_officer_sees_only_their_district` | — |
| SRS-DSH-02 | Wajib | `tests/Modules/Dsh/PortfolioKpiTest.php::test_SRS_DSH_02_rahsia_summaries_need_the_explicit_grant` | — |
| SRS-DSH-02 | Wajib | `tests/Modules/Dsh/PortfolioKpiTest.php::test_SRS_DSH_02_titles_land_status_and_expiring` | — |
| SRS-DSH-02 | Wajib | `tests/Modules/Dsh/PortfolioKpiTest.php::test_SRS_DSH_02_acquisitions_by_channel_and_outstanding_actions` | — |
| SRS-DSH-03 | Wajib | `tests/Modules/Dsh/ArasDashboardTest.php::test_SRS_DSH_03_map_widget_is_shown_only_with_gis_permission` | — |
| SRS-DSH-04 | Wajib | `tests/Modules/Dsh/ArasDashboardTest.php::test_SRS_DSH_04_map_and_indicators_share_one_filter_set` | — |
| SRS-DSH-05 | Wajib | `tests/Modules/Dsh/ArasDashboardTest.php::test_SRS_DSH_05_tax_dashboard_shows_mod_cki_kpis` | — |
| SRS-DSH-06 | Wajib | `tests/Modules/Dsh/ArasDashboardTest.php::test_SRS_DSH_06_lease_dashboard_shows_mod_pjk_kpis` | — |
| SRS-DSH-07 | Wajib | `tests/Modules/Dsh/ArasDashboardTest.php::test_SRS_DSH_07_monitoring_dashboard_shows_kpis_registered_by_mod_ptn` | — |
| SRS-DSH-08 | Wajib | `tests/Modules/Dsh/KpiRegistryTest.php::test_SRS_DSH_08_filters_reach_every_provider_and_invalid_values_are_rejected` | — |
| SRS-DSH-08 | Wajib | `tests/Modules/Dsh/PortfolioKpiTest.php::test_SRS_DSH_08_combined_filters_narrow_every_widget` | — |
| SRS-DSH-09 | Wajib | `tests/Modules/Dsh/ArasDashboardTest.php::test_SRS_DSH_09_dashboard_offers_all_seven_visual_types` | — |
| SRS-DSH-10 | Perlu | `tests/Modules/Dsh/SusunAturEksportTest.php::test_SRS_DSH_10_admin_sets_thresholds_and_widgets_without_code` | — |
| SRS-DSH-11 | Wajib | `tests/Modules/Dsh/DrillDownTest.php::test_SRS_DSH_11_kpi_drills_to_record_list_then_to_the_profile_in_three_clicks` | — |
| SRS-DSH-11 | Wajib | `tests/Modules/Dsh/DrillDownTest.php::test_SRS_DSH_11_drill_down_respects_scope_and_permission` | — |
| SRS-DSH-11 | Wajib | `tests/Modules/Dsh/DrillDownTest.php::test_SRS_DSH_11_title_case_and_task_lists_link_to_their_records` | — |
| SRS-DSH-12 | Wajib | `tests/Modules/Dsh/SusunAturEksportTest.php::test_SRS_DSH_12_every_widget_and_the_dashboard_carry_a_data_timestamp` | — |
| SRS-DSH-13 | Wajib | `tests/Modules/Dsh/KpiRegistryTest.php::test_SRS_DSH_13_results_are_cached_per_scope_signature_with_a_ttl_of_at_most_15_minutes` | — |
| SRS-DSH-13 | Wajib | `tests/Modules/Dsh/SusunAturEksportTest.php::test_SRS_DSH_13_dashboard_reads_never_write_other_modules_records` | — |
| SRS-DSH-14 | Wajib | `tests/Modules/Dsh/RingkasanBerkalaTest.php::test_SRS_DSH_14_scheduled_digest_sends_a_snapshot_summary_through_ntf` | — |
| SRS-DSH-14 | Wajib | `tests/Modules/Dsh/RingkasanBerkalaTest.php::test_SRS_DSH_14_manual_snapshot_and_subscription_permissions` | — |
| SRS-DSH-14 | Wajib | `tests/Modules/Dsh/SusunAturEksportTest.php::test_SRS_DSH_14_export_pdf_and_excel_with_filters_timestamp_and_watermark` | — |
| SRS-DSH-14 | Wajib | `tests/Modules/Dsh/SusunAturEksportTest.php::test_SRS_DSH_14_filtered_views_saved_as_personal_favourites` | — |
| SRS-DSH-15 | Perlu | `tests/Modules/Dsh/AiPromptTest.php::test_SRS_DSH_15_plain_language_question_answers_with_a_number` | — |
| SRS-DSH-16 | Perlu | `tests/Modules/Dsh/AiPromptTest.php::test_SRS_DSH_16_prompt_changes_the_personal_layout` | — |
| SRS-DSH-16 | Perlu | `tests/Modules/Dsh/SusunAturEksportTest.php::test_SRS_DSH_16_personal_layout_is_saved_per_user_and_can_be_reset` | — |
| SRS-DSH-17 | Wajib | `tests/Modules/Dsh/AiPromptTest.php::test_SRS_DSH_17_district_officer_asking_about_another_district_gets_own_scope_and_is_audited` | — |
| SRS-DSH-17 | Wajib | `tests/Modules/Dsh/AiPromptTest.php::test_SRS_DSH_17_prompt_cannot_reach_kpis_outside_the_users_permissions` | — |
| SRS-DSH-18 | Wajib | `tests/Modules/Dsh/AiPromptTest.php::test_SRS_DSH_18_default_engine_is_local_rules_and_no_request_leaves_the_server` | — |
| SRS-DSH-18 | Wajib | `tests/Modules/Dsh/AiPromptTest.php::test_SRS_DSH_18_ai_adapter_refuses_public_endpoints` | — |
| SRS-DSH-18 | Wajib | `tests/Modules/Dsh/AiPromptTest.php::test_SRS_DSH_18_local_ollama_adapter_output_is_validated_against_the_catalogue` | — |
| SRS-DSH-19 | Wajib | `tests/Modules/Dsh/PortalInboxTest.php::test_SRS_DSH_19_portal_lists_only_the_functions_the_user_may_use` | — |
| SRS-DSH-20 | Wajib | — | tiada ujian |
| SRS-DSH-21 | Wajib | — | tiada ujian |
| SRS-DSH-22 | Wajib | `tests/Modules/Dsh/PortalInboxTest.php::test_SRS_DSH_22_drafts_are_saved_reopened_edited_and_discarded_by_their_owner_only` | — |
| SRS-DSH-23 | Wajib | `tests/Modules/Dsh/PortalInboxTest.php::test_SRS_DSH_23_inbox_shows_my_tasks_deadlines_sla_status_and_notifications` | — |
| SRS-DSH-24 | Wajib | `tests/Modules/Dsh/PortalInboxTest.php::test_SRS_DSH_24_global_search_includes_my_drafts_only` | — |
| SRS-DSH-25 | Wajib | `tests/Modules/Dsh/PortalInboxTest.php::test_SRS_DSH_25_online_help_has_guides_faq_and_manual_for_the_current_screen` | — |
| SRS-GEN-01 | Wajib | `tests/Modules/Pro/LotMasterTest.php::test_SRS_GEN_01_user_without_permission_is_refused` | — |
| SRS-GEN-01 | Wajib | `tests/Modules/Rbc/AccessControlTest.php::test_SRS_GEN_01_every_rbc_and_user_route_authorises_through_the_gate` | — |
| SRS-GEN-01 | Wajib | `tests/Modules/Ref/ReferenceApiTest.php::test_SRS_GEN_01_ref_api_requires_login_and_the_view_permission` | — |
| SRS-GEN-01 | Wajib | `tests/Modules/Tsu/UrusanTemplatTest.php::test_SRS_GEN_01_endpoints_require_permission_and_respect_district_scope` | — |
| SRS-GEN-02 | Wajib | — | tiada ujian |
| SRS-GEN-03 | Wajib | `tests/Modules/Kes/SampelAliranTest.php::test_SRS_GEN_03_kes_flows_seeded_active_in_the_real_workflow_engine` | — |
| SRS-GEN-04 | Wajib | — | tiada ujian |
| SRS-GEN-05 | Wajib | `tests/Modules/Kes/IntegrasiTest.php::test_SRS_GEN_05_global_search_for_legal_users_only` | — |
| SRS-GEN-05 | Wajib | `tests/Modules/Pro/ProfileApiTest.php::test_SRS_GEN_05_lots_join_global_and_detailed_search_within_scope` | — |
| SRS-GEN-06 | Wajib | — | tiada ujian |
| SRS-GEN-07 | Perlu | — | tiada ujian |
| SRS-GEN-08 | Perlu | — | tiada ujian |
| SRS-GEN-09 | Wajib | — | tiada ujian |
| SRS-GIS-01 | Wajib | `tests/Modules/Gis/LayersTest.php::test_SRS_GIS_01_lot_layer_shows_lots_with_geometry_category_and_status` | — |
| SRS-GIS-02 | Wajib | `tests/Modules/Gis/PlatformTest.php::test_SRS_GIS_02_platform_components_layer_management_search_attributes_filter_and_analysis` | — |
| SRS-GIS-03 | Wajib | `tests/Modules/Gis/ImportEksportTest.php::test_SRS_GIS_03_geojson_in_cassini_is_reprojected_to_wgs84` | — |
| SRS-GIS-03 | Wajib | `tests/Modules/Gis/LayersTest.php::test_SRS_GIS_03_geometry_master_is_4326_with_gist_indexes` | — |
| SRS-GIS-04 | Wajib | — | tiada ujian |
| SRS-GIS-05 | Wajib | — | tiada ujian |
| SRS-GIS-06 | Wajib | — | tiada ujian |
| SRS-GIS-07 | Wajib | — | tiada ujian |
| SRS-GIS-08 | Wajib | `tests/Modules/Gis/LayersTest.php::test_SRS_GIS_08_features_filtered_by_bbox_and_simplified_by_zoom` | — |
| SRS-GIS-08 | Wajib | `tests/Modules/Gis/LayersTest.php::test_SRS_GIS_08_vector_tiles_are_served_via_st_asmvt_and_scoped` | — |
| SRS-GIS-08 | Wajib | `tests/Modules/Gis/LayersTest.php::test_SRS_GIS_08_tiles_are_cached_per_scope_signature` | — |
| SRS-GIS-08 | Wajib | `tests/Modules/Gis/LayersTest.php::test_SRS_GIS_08_location_search_by_lot_number_mukim_and_mosque` | — |
| SRS-GIS-08 | Wajib | `tests/Modules/Gis/PerkhidmatanPetaTest.php::test_SRS_GIS_08_basemaps_osm_with_attribution_and_licensed_satellite_adapter` | — |
| SRS-GIS-09 | Wajib | `tests/Modules/Gis/LayersTest.php::test_SRS_GIS_09_feature_attributes_carry_deep_link_to_360_profile` | — |
| SRS-GIS-10 | Wajib | `tests/Modules/Gis/PlatformTest.php::test_SRS_GIS_10_measure_drawn_polygon_area_and_perimeter` | — |
| SRS-GIS-11 | Wajib | `tests/Modules/Gis/LayersTest.php::test_SRS_GIS_11_ten_mandatory_layers_listed_with_legends_and_filled_by_module_layers` | — |
| SRS-GIS-11 | Wajib | `tests/Modules/Gis/LayersTest.php::test_SRS_GIS_11_registered_module_layer_is_served_as_geojson_with_its_own_permission` | — |
| SRS-GIS-11 | Wajib | `tests/Modules/Gis/LayersTest.php::test_SRS_GIS_11_physical_asset_layer_points_crud_scoped_and_audited` | — |
| SRS-GIS-12 | Wajib | `tests/Modules/Gis/LayersTest.php::test_SRS_GIS_12_layer_order_visibility_opacity_configurable_but_classification_never_lowered` | — |
| SRS-GIS-12 | Wajib | `tests/Modules/Gis/LayersTest.php::test_SRS_GIS_12_legends_match_the_shared_frontend_legend_files` | — |
| SRS-GIS-13 | Wajib | `tests/Modules/Gis/LayersTest.php::test_SRS_GIS_13_land_status_layer_classifies_lots` | — |
| SRS-GIS-14 | Perlu | `tests/Modules/Gis/LayersTest.php::test_SRS_GIS_14_boundary_and_public_facility_layers` | — |
| SRS-GIS-15 | Wajib | `tests/Modules/Gis/AnalisisTest.php::test_SRS_GIS_15_buffer_lists_features_within_user_defined_distance` | — |
| SRS-GIS-15 | Wajib | `tests/Modules/Gis/AnalisisTest.php::test_SRS_GIS_15_buffer_source_outside_scope_is_rejected` | — |
| SRS-GIS-16 | Wajib | `tests/Modules/Gis/AnalisisTest.php::test_SRS_GIS_16_overlay_lists_lots_intersecting_another_layer` | — |
| SRS-GIS-17 | Wajib | `tests/Modules/Gis/AnalisisTest.php::test_SRS_GIS_17_spatial_query_by_drawn_polygon_boundary_and_attribute` | — |
| SRS-GIS-18 | Wajib | `tests/Modules/Gis/AnalisisTest.php::test_SRS_GIS_18_area_calculation_in_geodesic_and_cassini_with_units` | — |
| SRS-GIS-19 | Wajib | `tests/Modules/Gis/AnalisisTest.php::test_SRS_GIS_19_proximity_nearest_and_lots_within_distance_of_mosque` | — |
| SRS-GIS-20 | Perlu | `tests/Modules/Gis/AnalisisTest.php::test_SRS_GIS_20_scenario_saved_shared_by_permission_and_exported` | — |
| SRS-GIS-20 | Perlu | `tests/Modules/Gis/AnalisisTest.php::test_SRS_GIS_20_saved_map_views_are_personal_unless_shared` | — |
| SRS-GIS-21 | Baik-ada | `tests/Modules/Gis/AnalisisTest.php::test_SRS_GIS_21_clustering_groups_nearby_lots` | — |
| SRS-GIS-22 | Wajib | `tests/Modules/Gis/ImportEksportTest.php::test_SRS_GIS_22_valid_geojson_validated_accepted_and_published_as_dataset_layer` | — |
| SRS-GIS-22 | Wajib | `tests/Modules/Gis/ImportEksportTest.php::test_SRS_GIS_22_invalid_geometry_and_outside_selangor_rejected_naming_each_feature` | — |
| SRS-GIS-22 | Wajib | `tests/Modules/Gis/ImportEksportTest.php::test_SRS_GIS_22_virus_found_by_dms_scan_stops_the_import` | — |
| SRS-GIS-22 | Wajib | `tests/Modules/Gis/ImportEksportTest.php::test_SRS_GIS_22_shapefile_kml_and_dxf_are_imported` | — |
| SRS-GIS-22 | Wajib | `tests/Modules/Gis/ImportEksportTest.php::test_SRS_GIS_22_import_requires_permission_and_unsupported_format_rejected` | — |
| SRS-GIS-23 | Wajib | `tests/Modules/Gis/PlatformTest.php::test_SRS_GIS_23_government_source_dataset_imported_with_provenance_and_usable_in_overlay` | — |
| SRS-GIS-24 | Wajib | — | tiada ujian |
| SRS-GIS-25 | Wajib | `tests/Modules/Gis/PerkhidmatanPetaTest.php::test_SRS_GIS_25_wms_getmap_proxied_with_scope_injected_as_cql_filter` | — |
| SRS-GIS-25 | Wajib | `tests/Modules/Gis/PerkhidmatanPetaTest.php::test_SRS_GIS_25_capabilities_urls_rewritten_to_the_proxy` | — |
| SRS-GIS-25 | Wajib | `tests/Modules/Gis/PerkhidmatanPetaTest.php::test_SRS_GIS_25_wfs_getfeature_access_audited_and_sulit_levels_follow_permission` | — |
| SRS-GIS-26 | Wajib | `tests/Modules/Gis/ImportEksportTest.php::test_SRS_GIS_26_layer_export_in_open_formats_with_reprojection_scoped_and_audited` | — |
| SRS-GIS-27 | Wajib | `tests/Modules/Gis/KualitiVersiTest.php::test_SRS_GIS_27_coordinates_outside_selangor_and_outside_mukim_beyond_tolerance` | — |
| SRS-GIS-27 | Wajib | `tests/Modules/Gis/KualitiVersiTest.php::test_SRS_GIS_27_scheduled_command_scans_everything` | — |
| SRS-GIS-28 | Wajib | `tests/Modules/Gis/KualitiVersiTest.php::test_SRS_GIS_28_detects_invalid_geometry_overlaps_gaps_and_missing_geometry` | — |
| SRS-GIS-28 | Wajib | `tests/Modules/Gis/KualitiVersiTest.php::test_SRS_GIS_28_rerun_closes_fixed_findings_and_status_workflow_is_scoped` | — |
| SRS-GIS-29 | Wajib | `tests/Modules/Gis/PerkhidmatanPetaTest.php::test_SRS_GIS_29_layer_and_dataset_metadata_recorded_and_reported` | — |
| SRS-GIS-30 | Wajib | `tests/Modules/Gis/KualitiVersiTest.php::test_SRS_GIS_30_geometry_versions_listed_and_compared` | — |
| SRS-GIS-31 | Wajib | `tests/Modules/Gis/KualitiVersiTest.php::test_SRS_GIS_31_spatial_changes_audited_with_before_and_after` | — |
| SRS-GIS-32 | Wajib | `tests/Modules/Gis/LayersTest.php::test_SRS_GIS_32_out_of_scope_district_and_classification_hidden_from_map_services` | — |
| SRS-GIS-32 | Wajib | `tests/Modules/Gis/LayersTest.php::test_SRS_GIS_32_sulit_layer_refused_on_direct_request_and_attempt_audited` | — |
| SRS-GIS-32 | Wajib | `tests/Modules/Gis/LayersTest.php::test_SRS_GIS_32_user_without_map_permission_is_refused` | — |
| SRS-GIS-32 | Wajib | `tests/Modules/Gis/PerkhidmatanPetaTest.php::test_SRS_GIS_32_ogc_proxy_refuses_filter_injection_unpublished_layers_and_unauthorised_users` | — |
| SRS-GIS-33 | Wajib | `tests/Modules/Gis/PerkhidmatanPetaTest.php::test_SRS_GIS_33_map_printed_to_pdf_with_legend_scale_north_timestamp_and_user_and_audited` | — |
| SRS-GIS-34 | Wajib | `tests/Modules/Gis/PerkhidmatanPetaTest.php::test_SRS_GIS_34_distribution_by_daerah_mukim_and_category_in_scope` | — |
| SRS-GIS-35 | Wajib | — | tiada ujian |
| SRS-HKM-01 | Wajib | `tests/Modules/Hkm/IntegrationContractsTest.php::test_SRS_HKM_01_title_registry_creates_title_and_vault_entry_for_completed_acquisition` | — |
| SRS-HKM-01 | Wajib | `tests/Modules/Hkm/IntegrationContractsTest.php::test_SRS_HKM_01_import_staging_matches_lots_and_is_idempotent` | — |
| SRS-HKM-01 | Wajib | `tests/Modules/Hkm/TitleRegisterTest.php::test_SRS_HKM_01_registered_title_shows_all_a501_fields` | — |
| SRS-HKM-01 | Wajib | `tests/Modules/Hkm/TitleRegisterTest.php::test_SRS_HKM_01_title_number_is_unique_per_mukim` | — |
| SRS-HKM-01 | Wajib | `tests/Modules/Hkm/TitleRegisterTest.php::test_SRS_HKM_01_registration_writes_first_history_rows_and_version` | — |
| SRS-HKM-01 | Wajib | `tests/Modules/Hkm/TitleRegisterTest.php::test_SRS_HKM_01_user_without_permission_is_refused` | — |
| SRS-HKM-01 | Wajib | `tests/Modules/Hkm/TitleRegisterTest.php::test_SRS_HKM_01_out_of_scope_district_is_invisible` | — |
| SRS-HKM-02 | Wajib | `tests/Modules/Hkm/TitleRegisterTest.php::test_SRS_HKM_02_type_comes_from_ref_jenis_hak_milik` | — |
| SRS-HKM-02 | Wajib | `tests/Modules/Hkm/TitleRegisterTest.php::test_SRS_HKM_02_tol_needs_expiry_and_records_dates_and_office` | — |
| SRS-HKM-03 | Wajib | `tests/Modules/Hkm/VaultTest.php::test_SRS_HKM_03_title_shows_vault_file_rack_and_original_status` | — |
| SRS-HKM-03 | Wajib | `tests/Modules/Hkm/VaultTest.php::test_SRS_HKM_03_vault_master_is_managed_by_the_custodian_only` | — |
| SRS-HKM-04 | Wajib | `tests/Modules/Hkm/VaultTest.php::test_SRS_HKM_04_vault_inventory_prints_and_is_audited` | — |
| SRS-HKM-05 | Wajib | `tests/Modules/Hkm/VaultTest.php::test_SRS_HKM_05_original_document_status_values_and_missing_flag` | — |
| SRS-HKM-06 | Wajib | `tests/Modules/Hkm/VaultTest.php::test_SRS_HKM_06_full_issue_and_return_cycle_is_recorded` | — |
| SRS-HKM-06 | Wajib | `tests/Modules/Hkm/VaultTest.php::test_SRS_HKM_06_issue_needs_issue_permission` | — |
| SRS-HKM-07 | Perlu | `tests/Modules/Hkm/VaultTest.php::test_SRS_HKM_07_overdue_documents_are_listed_and_notified_to_custodian_and_head` | — |
| SRS-HKM-08 | Wajib | `tests/Modules/Hkm/ChangeApprovalTest.php::test_SRS_HKM_08_status_list_is_configurable` | — |
| SRS-HKM-09 | Wajib | `tests/Modules/Hkm/ChangeApprovalTest.php::test_SRS_HKM_09_status_change_writes_one_immutable_history_row` | — |
| SRS-HKM-09 | Wajib | `tests/Modules/Hkm/ChangeApprovalTest.php::test_SRS_HKM_09_chronology_and_versions_are_append_only_too` | — |
| SRS-HKM-10 | Perlu | `tests/Modules/Hkm/ChangeApprovalTest.php::test_SRS_HKM_10_lot_ownership_chronology_is_ordered` | — |
| SRS-HKM-11 | Wajib | `tests/Modules/Hkm/ProfileAndSearchTest.php::test_SRS_HKM_11_dms_record_link_points_back_to_the_title` | — |
| SRS-HKM-12 | Wajib | `tests/Modules/Hkm/ChangeApprovalTest.php::test_SRS_HKM_12_heirs_with_fractions_and_unresolved_ownership_flag` | — |
| SRS-HKM-13 | Wajib | `tests/Modules/Hkm/ChangeApprovalTest.php::test_SRS_HKM_13_encumbrance_is_recorded_flagged_and_discharged` | — |
| SRS-HKM-14 | Wajib | `tests/Modules/Hkm/ProfileAndSearchTest.php::test_SRS_HKM_14_search_by_each_of_the_nine_criteria` | — |
| SRS-HKM-14 | Wajib | `tests/Modules/Hkm/ProfileAndSearchTest.php::test_SRS_HKM_14_combined_criteria_and_export` | — |
| SRS-HKM-14 | Wajib | `tests/Modules/Hkm/ProfileAndSearchTest.php::test_SRS_HKM_14_global_search_provider_respects_scope` | — |
| SRS-HKM-15 | Wajib | `tests/Modules/Hkm/ProfileAndSearchTest.php::test_SRS_HKM_15_profile_sections_show_title_risks_and_timeline` | — |
| SRS-HKM-16 | Wajib | `tests/Modules/Hkm/PermanentTitleTest.php::test_SRS_HKM_16_application_is_tracked_to_receipt_and_links_old_and_new_title` | — |
| SRS-HKM-16 | Wajib | `tests/Modules/Hkm/PermanentTitleTest.php::test_SRS_HKM_16_only_temporary_titles_qualify_and_permissions_apply` | — |
| SRS-HKM-17 | Perlu | `tests/Modules/Hkm/ProposalAndReminderTest.php::test_SRS_HKM_17_temporary_title_expiry_reminders_at_180_90_30_days` | — |
| SRS-HKM-17 | Perlu | `tests/Modules/Hkm/ProposalAndReminderTest.php::test_SRS_HKM_17_tol_renewal_and_lot_without_verified_title` | — |
| SRS-HKM-17 | Perlu | `tests/Modules/Hkm/ProposalAndReminderTest.php::test_SRS_HKM_17_expiring_titles_report` | — |
| SRS-HKM-17 | Perlu | `tests/Modules/Hkm/TitleRegisterTest.php::test_SRS_HKM_17_verification_needs_another_officer` | — |
| SRS-HKM-18 | Perlu | `tests/Modules/Hkm/ProposalAndReminderTest.php::test_SRS_HKM_18_external_proposal_lands_as_draft_and_never_writes_the_master` | — |
| SRS-HKM-18 | Perlu | `tests/Modules/Hkm/ProposalAndReminderTest.php::test_SRS_HKM_18_reviewer_can_reject_and_integration_account_cannot_review` | — |
| SRS-HKM-18 | Perlu | `tests/Modules/Hkm/ProposalAndReminderTest.php::test_SRS_HKM_18_unmatched_proposal_needs_the_officer_to_pick_the_title` | — |
| SRS-HKM-19 | Wajib | `tests/Modules/Hkm/ChangeApprovalTest.php::test_SRS_HKM_19_change_starts_the_two_stage_flow_and_is_not_applied_before_approval` | — |
| SRS-HKM-19 | Wajib | `tests/Modules/Hkm/ChangeApprovalTest.php::test_SRS_HKM_19_approved_owner_change_is_applied_as_new_version_with_before_after` | — |
| SRS-HKM-19 | Wajib | `tests/Modules/Hkm/ChangeApprovalTest.php::test_SRS_HKM_19_rejected_or_withdrawn_change_is_never_applied` | — |
| SRS-HKM-19 | Wajib | `tests/Modules/Hkm/ChangeApprovalTest.php::test_SRS_HKM_19_missing_workflow_definition_is_reported` | — |
| SRS-HKM-19 | Wajib | `tests/Modules/Hkm/ChangeApprovalTest.php::test_SRS_HKM_19_change_request_needs_update_permission_and_scope` | — |
| SRS-HKM-19 | Wajib | `tests/Modules/Hkm/WorkflowIntegrationTest.php::test_SRS_HKM_19_seeded_flow_has_two_mfa_stages_and_applies_only_after_both` | — |
| SRS-HKM-20 | Baik-ada | `tests/Modules/Hkm/ProfileAndSearchTest.php::test_SRS_HKM_20_colour_legend_covers_every_reference_code` | — |
| SRS-HKM-21 | Perlu | `tests/Modules/Hkm/TitleRegisterTest.php::test_SRS_HKM_21_presentation_number_and_land_office_transactions_are_tracked` | — |
| SRS-HKM-22 | Wajib | `tests/Modules/Hkm/ChangeApprovalTest.php::test_SRS_HKM_22_applied_change_is_audited_with_old_and_new_values` | — |
| SRS-HKM-22 | Wajib | `tests/Modules/Hkm/TitleRegisterTest.php::test_SRS_HKM_22_viewing_and_creating_a_title_is_audited` | — |
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
| SRS-KBA-01 | Wajib | `tests/Modules/Kba/PengindeksanTest.php::test_SRS_KBA_01_baseline_documents_are_indexed_with_coverage_report_and_claude_md_is_not` | — |
| SRS-KBA-01 | Wajib | `tests/Modules/Kba/PengindeksanTest.php::test_SRS_KBA_01_admin_uploads_markdown_and_docx_manuals` | — |
| SRS-KBA-01 | Wajib | `tests/Modules/Kba/PengindeksanTest.php::test_SRS_KBA_01_docx_headings_become_sections` | — |
| SRS-KBA-02 | Wajib | `tests/Modules/Kba/PengindeksanTest.php::test_SRS_KBA_02_dms_document_is_indexed_incrementally_on_store_and_new_version` | — |
| SRS-KBA-02 | Wajib | `tests/Modules/Kba/PengindeksanTest.php::test_SRS_KBA_02_dms_documents_outside_the_auto_categories_or_still_being_extracted_wait` | — |
| SRS-KBA-02 | Wajib | `tests/Modules/Kba/PengindeksanTest.php::test_SRS_KBA_02_admin_reindex_and_index_status` | — |
| SRS-KBA-03 | Wajib | `tests/Modules/Kba/TanyaJawabTest.php::test_SRS_KBA_03_plain_language_question_in_malay_gets_a_grounded_answer` | — |
| SRS-KBA-03 | Wajib | `tests/Modules/Kba/TanyaJawabTest.php::test_SRS_KBA_03_english_question_over_malay_sources_is_answered` | — |
| SRS-KBA-03 | Wajib | `tests/Modules/Kba/TanyaJawabTest.php::test_SRS_KBA_03_conversation_history_is_per_user` | — |
| SRS-KBA-04 | Wajib | `tests/Modules/Kba/PengindeksanTest.php::test_SRS_KBA_04_chunks_keep_section_number_title_and_path` | — |
| SRS-KBA-04 | Wajib | `tests/Modules/Kba/TanyaJawabTest.php::test_SRS_KBA_04_citations_name_document_version_and_section_and_open_the_source_there` | — |
| SRS-KBA-04 | Wajib | `tests/Modules/Kba/TanyaJawabTest.php::test_SRS_KBA_04_source_viewer_hides_segments_the_user_may_not_read` | — |
| SRS-KBA-05 | Wajib | `tests/Modules/Kba/AiTempatanTest.php::test_SRS_KBA_05_unsupported_generated_sentences_are_dropped_or_the_answer_is_rejected` | — |
| SRS-KBA-05 | Wajib | `tests/Modules/Kba/TanyaJawabTest.php::test_SRS_KBA_05_out_of_scope_question_says_no_sufficient_sources_and_generates_nothing` | — |
| SRS-KBA-05 | Wajib | `tests/Modules/Kba/TanyaJawabTest.php::test_SRS_KBA_05_contract_answer_reports_insufficient_sources` | — |
| SRS-KBA-06 | Wajib | `tests/Modules/Kba/AiTempatanTest.php::test_SRS_KBA_06_generative_mode_answers_from_retrieved_passages_with_citations` | — |
| SRS-KBA-06 | Wajib | `tests/Modules/Kba/PengindeksanTest.php::test_SRS_KBA_06_embeddings_and_permission_metadata_live_in_the_same_postgres_with_pgvector` | — |
| SRS-KBA-06 | Wajib | `tests/Modules/Kba/PengindeksanTest.php::test_SRS_KBA_06_index_works_without_the_embedding_model_and_catches_up_later` | — |
| SRS-KBA-07 | Wajib | `tests/Modules/Kba/TanyaJawabTest.php::test_SRS_KBA_07_contract_without_user_uses_only_awam_sources` | — |
| SRS-KBA-07 | Wajib | `tests/Modules/Kba/TanyaJawabTest.php::test_SRS_KBA_07_classified_passages_never_reach_the_model_for_unauthorised_roles` | — |
| SRS-KBA-07 | Wajib | `tests/Modules/Kba/TanyaJawabTest.php::test_SRS_KBA_07_user_without_permission_or_session_is_refused` | — |
| SRS-KBA-08 | Wajib | `tests/Modules/Kba/PengindeksanTest.php::test_SRS_KBA_08_roles_and_mapping_restrict_segments_and_metadata_changes_apply_at_once` | — |
| SRS-KBA-08 | Wajib | `tests/Modules/Kba/PengindeksanTest.php::test_SRS_KBA_08_klasifikasi_and_district_scopes_from_rbc_apply` | — |
| SRS-KBA-09 | Wajib | `tests/Modules/Kba/EksportSokonganTest.php::test_SRS_KBA_09_support_view_shows_design_integration_parameters_and_problem_history_per_module` | — |
| SRS-KBA-10 | Perlu | `tests/Modules/Kba/TanyaJawabTest.php::test_SRS_KBA_10_keyword_search_with_filters` | — |
| SRS-KBA-11 | Wajib | `tests/Modules/Kba/ArtikelTest.php::test_SRS_KBA_11_articles_are_versioned_with_status_owner_and_review_dates` | — |
| SRS-KBA-12 | Wajib | `tests/Modules/Kba/ArtikelTest.php::test_SRS_KBA_12_new_release_flags_articles_on_older_versions_and_tells_owners` | — |
| SRS-KBA-13 | Wajib | `tests/Modules/Kba/ArtikelTest.php::test_SRS_KBA_13_drafts_are_hidden_until_published_through_the_workflow` | — |
| SRS-KBA-13 | Wajib | `tests/Modules/Kba/ArtikelTest.php::test_SRS_KBA_13_submitting_without_a_configured_flow_is_refused_clearly` | — |
| SRS-KBA-14 | Wajib | `tests/Modules/Kba/ArtikelTest.php::test_SRS_KBA_14_ticket_solution_becomes_a_structured_draft_linked_both_ways` | — |
| SRS-KBA-15 | Wajib | `tests/Modules/Kba/AiTempatanTest.php::test_SRS_KBA_15_only_loopback_or_private_ai_endpoints_are_accepted` | — |
| SRS-KBA-15 | Wajib | `tests/Modules/Kba/AiTempatanTest.php::test_SRS_KBA_15_public_endpoint_in_config_falls_back_to_local_only_engines` | — |
| SRS-KBA-15 | Wajib | `tests/Modules/Kba/AiTempatanTest.php::test_SRS_KBA_15_ollama_adapters_talk_to_the_local_server_only` | — |
| SRS-KBA-15 | Wajib | `tests/Modules/Kba/AiTempatanTest.php::test_SRS_KBA_15_extractive_mode_when_model_is_down_or_failing` | — |
| SRS-KBA-16 | Wajib | `tests/Modules/Kba/AuditAnalitikTest.php::test_SRS_KBA_16_every_prompt_is_logged_with_user_role_ip_model_segments_and_sources` | — |
| SRS-KBA-16 | Wajib | `tests/Modules/Kba/AuditAnalitikTest.php::test_SRS_KBA_16_prompt_log_is_immutable_and_only_auditors_may_review_it` | — |
| SRS-KBA-17 | Perlu | `tests/Modules/Kba/AuditAnalitikTest.php::test_SRS_KBA_17_feedback_on_own_answers_reaches_content_admins` | — |
| SRS-KBA-18 | Perlu | `tests/Modules/Kba/AuditAnalitikTest.php::test_SRS_KBA_18_analytics_frequent_questions_gaps_citations_usage_and_weekly_digest` | — |
| SRS-KBA-19 | Wajib | `tests/Modules/Kba/AiTempatanTest.php::test_SRS_KBA_19_embedded_instructions_are_flagged_and_neutralised_before_the_model` | — |
| SRS-KBA-19 | Wajib | `tests/Modules/Kba/AiTempatanTest.php::test_SRS_KBA_19_prompts_are_rate_limited_per_user_and_role_and_refusals_are_logged` | — |
| SRS-KBA-20 | Wajib | `tests/Modules/Kba/EksportSokonganTest.php::test_SRS_KBA_20_whole_knowledge_base_exports_to_markdown_and_html` | — |
| SRS-KBA-21 | Perlu | `tests/Modules/Kba/AiTempatanTest.php::test_SRS_KBA_21_one_shared_local_ai_infrastructure` | — |
| SRS-KBA-22 | Wajib | — | tiada ujian |
| SRS-KES-01 | Wajib | `tests/Modules/Kes/DaftarKesTest.php::test_SRS_KES_01_default_case_types_and_a_new_type_without_code_change` | — |
| SRS-KES-02 | Wajib | `tests/Modules/Kes/DaftarKesTest.php::test_SRS_KES_02_register_with_every_A_12_02_field` | — |
| SRS-KES-03 | Wajib | `tests/Modules/Kes/DaftarKesTest.php::test_SRS_KES_03_parties_by_role_encrypted_and_masked` | — |
| SRS-KES-04 | Wajib | `tests/Modules/Kes/DaftarKesTest.php::test_SRS_KES_04_counsel_per_party_with_firm_and_contact` | — |
| SRS-KES-05 | Wajib | `tests/Modules/Kes/DaftarKesTest.php::test_SRS_KES_05_one_case_three_lots_shown_on_each_profile` | — |
| SRS-KES-05 | Wajib | `tests/Modules/Kes/DaftarKesTest.php::test_SRS_KES_05_14_profile_section_hidden_for_users_without_the_legal_grant` | — |
| SRS-KES-06 | Perlu | `tests/Modules/Kes/DaftarKesTest.php::test_SRS_KES_06_lots_flagged_while_active_and_restored_when_all_cases_finish` | — |
| SRS-KES-07 | Wajib | `tests/Modules/Kes/DaftarKesTest.php::test_SRS_KES_07_staged_status_with_dated_history` | — |
| SRS-KES-08 | Wajib | `tests/Modules/Kes/ProsidingTest.php::test_SRS_KES_08_proceedings_with_outcome_directions_next_date_and_chronology` | — |
| SRS-KES-09 | Perlu | `tests/Modules/Kes/ProsidingTest.php::test_SRS_KES_09_calendar_filtered_by_officer_type_court_and_district` | — |
| SRS-KES-10 | Wajib | `tests/Modules/Kes/ProsidingTest.php::test_SRS_KES_10_reminders_14_7_1_days_to_officer_and_head` | — |
| SRS-KES-10 | Wajib | `tests/Modules/Kes/ProsidingTest.php::test_SRS_KES_10_reminder_types_registered_with_ntf` | — |
| SRS-KES-11 | Perlu | `tests/Modules/Kes/ProsidingTest.php::test_SRS_KES_11_escalate_when_no_next_date_after_the_set_period` | — |
| SRS-KES-12 | Wajib | `tests/Modules/Kes/KosKeputusanTest.php::test_SRS_KES_12_legal_documents_through_dms_with_category_version_and_logged_download` | — |
| SRS-KES-13 | Wajib | `tests/Modules/Kes/AksesTest.php::test_SRS_KES_13_user_without_legal_role_cannot_open_records_or_documents` | — |
| SRS-KES-13 | Wajib | `tests/Modules/Kes/AksesTest.php::test_SRS_KES_13_every_view_download_and_print_logged_with_ip_and_time` | — |
| SRS-KES-13 | Wajib | `tests/Modules/Kes/AksesTest.php::test_SRS_KES_13_access_log_report_for_auditors` | — |
| SRS-KES-14 | Wajib | `tests/Modules/Kes/AksesTest.php::test_SRS_KES_14_records_are_rahsia_and_need_mfa_through_sso` | — |
| SRS-KES-15 | Wajib | `tests/Modules/Kes/KosKeputusanTest.php::test_SRS_KES_15_costs_by_category_cumulative_with_bcmath` | — |
| SRS-KES-15 | Wajib | `tests/Modules/Kes/KosKeputusanTest.php::test_SRS_KES_15_costs_appear_in_each_linked_lot_cost_chart_with_the_right_amount` | — |
| SRS-KES-15 | Wajib | `tests/Modules/Kes/KosKeputusanTest.php::test_SRS_KES_15_fee_payment_approved_in_wfe_then_paid_and_verified_in_grp` | — |
| SRS-KES-16 | Wajib | `tests/Modules/Kes/KosKeputusanTest.php::test_SRS_KES_16_17_decision_recorded_and_follow_ups_become_wfe_tasks` | — |
| SRS-KES-17 | Wajib | — | tiada ujian |
| SRS-KES-18 | Wajib | `tests/Modules/Kes/KosKeputusanTest.php::test_SRS_KES_18_title_update_proposed_to_hkm_with_case_number_and_order_cross_reference` | — |
| SRS-KES-19 | Perlu | `tests/Modules/Kes/IntegrasiTest.php::test_SRS_KES_19_complaint_raised_to_a_case_carries_lots_documents_and_chronology` | — |
| SRS-KES-20 | Wajib | `tests/Modules/Kes/DaftarKesTest.php::test_SRS_KES_20_assign_officer_with_history_and_caseload` | — |
| SRS-KES-21 | Wajib | `tests/Modules/Kes/LaporanTest.php::test_SRS_KES_21_reports_filter_and_group_by_every_listed_dimension` | — |
| SRS-KES-22 | Wajib | `tests/Modules/Kes/IntegrasiTest.php::test_SRS_KES_22_kpis_registered_with_the_executive_dashboard` | — |
| SRS-KES-22 | Wajib | `tests/Modules/Kes/LaporanTest.php::test_SRS_KES_22_dashboard_status_type_upcoming_value_and_costs` | — |
| SRS-KES-23 | Wajib | `tests/Modules/Kes/LaporanTest.php::test_SRS_KES_23_portfolio_legal_exposure` | — |
| SRS-KES-24 | Perlu | `tests/Modules/Kes/LaporanTest.php::test_SRS_KES_24_reports_export_to_pdf_and_excel_with_audit` | — |
| SRS-LAP-01 | Wajib | `tests/Modules/Lap/AiBiTest.php::test_SRS_LAP_01_metabase_embed_is_signed_with_the_users_district_locked` | — |
| SRS-LAP-01 | Wajib | `tests/Modules/Lap/AiBiTest.php::test_SRS_LAP_01_empty_scope_extra_permission_and_unconfigured_bi_are_refused` | — |
| SRS-LAP-01 | Wajib | `tests/Modules/Lap/AiBiTest.php::test_SRS_LAP_01_bi_dashboards_are_registered_by_bi_administrators_only` | — |
| SRS-LAP-02 | Wajib | `tests/Modules/Lap/LaporanTest.php::test_SRS_LAP_02_catalogue_covers_every_mandatory_category_and_links_module_reports` | — |
| SRS-LAP-02 | Wajib | `tests/Modules/Lap/LaporanTest.php::test_SRS_LAP_02_every_standard_report_runs_and_drills` | — |
| SRS-LAP-02 | Wajib | `tests/Modules/Lap/LaporanTest.php::test_SRS_LAP_02_year_on_year_comparison_with_exact_decimals` | — |
| SRS-LAP-02 | Wajib | `tests/Modules/Lap/LaporanTest.php::test_SRS_LAP_02_composite_board_report_includes_only_permitted_sections` | — |
| SRS-LAP-02 | Wajib | `tests/Modules/Lap/LaporanTest.php::test_SRS_LAP_02_admin_records_crp2_validation_of_a_standard_report` | — |
| SRS-LAP-03 | Wajib | `tests/Modules/Lap/LaporanTest.php::test_SRS_LAP_03_ad_hoc_fields_filters_grouping_sort_and_period` | — |
| SRS-LAP-03 | Wajib | `tests/Modules/Lap/LaporanTest.php::test_SRS_LAP_03_definition_only_accepts_whitelisted_fields_and_operators` | — |
| SRS-LAP-03 | Wajib | `tests/Modules/Lap/LaporanTest.php::test_SRS_LAP_03_saved_shared_definition_runs_with_the_readers_scope` | — |
| SRS-LAP-03 | Wajib | `tests/Modules/Lap/LaporanTest.php::test_SRS_LAP_03_private_definition_is_invisible_to_others_and_role_sharing_works` | — |
| SRS-LAP-04 | Wajib | `tests/Modules/Lap/EksportJadualTest.php::test_SRS_LAP_04_pdf_excel_and_csv_carry_identical_values` | — |
| SRS-LAP-04 | Wajib | `tests/Modules/Lap/EksportJadualTest.php::test_SRS_LAP_04_exports_carry_watermark_and_sulit_marking_and_need_permission` | — |
| SRS-LAP-04 | Wajib | `tests/Modules/Lap/EksportJadualTest.php::test_SRS_LAP_04_csv_text_cells_are_protected_from_formula_injection` | — |
| SRS-LAP-05 | Perlu | `tests/Modules/Lap/EksportJadualTest.php::test_SRS_LAP_05_chart_image_export_is_logged` | — |
| SRS-LAP-06 | Wajib | `tests/Modules/Lap/EksportJadualTest.php::test_SRS_LAP_06_scheduled_report_is_generated_per_recipient_scope_and_sent_through_ntf` | — |
| SRS-LAP-06 | Wajib | `tests/Modules/Lap/EksportJadualTest.php::test_SRS_LAP_06_recipients_without_access_are_refused_at_save` | — |
| SRS-LAP-06 | Wajib | `tests/Modules/Lap/EksportJadualTest.php::test_SRS_LAP_06_next_run_for_every_frequency` | — |
| SRS-LAP-07 | Wajib | `tests/Modules/Lap/EksportJadualTest.php::test_SRS_LAP_07_large_export_runs_in_background_and_notifies_the_requester` | — |
| SRS-LAP-07 | Wajib | `tests/Modules/Lap/LaporanTest.php::test_SRS_LAP_07_results_are_cached_per_scope` | — |
| SRS-LAP-08 | Wajib | `tests/Modules/Lap/KeselamatanBarisTest.php::test_SRS_LAP_08_reporting_views_return_nothing_without_scope_settings` | — |
| SRS-LAP-08 | Wajib | `tests/Modules/Lap/KeselamatanBarisTest.php::test_SRS_LAP_08_direct_query_on_view_enforces_the_session_district_scope` | — |
| SRS-LAP-08 | Wajib | `tests/Modules/Lap/KeselamatanBarisTest.php::test_SRS_LAP_08_district_user_opening_a_state_report_receives_only_their_rows` | — |
| SRS-LAP-08 | Wajib | `tests/Modules/Lap/KeselamatanBarisTest.php::test_SRS_LAP_08_sulit_rows_need_the_dataset_classification_permission` | — |
| SRS-LAP-08 | Wajib | `tests/Modules/Lap/KeselamatanBarisTest.php::test_SRS_LAP_08_bi_role_reads_only_aggregate_views` | — |
| SRS-LAP-09 | Wajib | `tests/Modules/Lap/KeselamatanBarisTest.php::test_SRS_LAP_09_report_needs_the_source_module_permission_and_denials_are_audited` | — |
| SRS-LAP-09 | Wajib | `tests/Modules/Lap/KeselamatanBarisTest.php::test_SRS_LAP_09_field_level_permission_hides_rahsia_fields` | — |
| SRS-LAP-09 | Wajib | `tests/Modules/Lap/KeselamatanBarisTest.php::test_SRS_LAP_09_allowed_roles_restrict_a_report` | — |
| SRS-LAP-09 | Wajib | `tests/Modules/Lap/KeselamatanBarisTest.php::test_SRS_LAP_09_user_without_lap_permission_is_refused` | — |
| SRS-LAP-10 | Wajib | `tests/Modules/Lap/EksportJadualTest.php::test_SRS_LAP_10_generation_and_export_are_logged_with_parameters_user_time_and_count` | — |
| SRS-LAP-11 | Wajib | `tests/Modules/Lap/LaporanTest.php::test_SRS_LAP_11_every_result_carries_data_timestamp_period_and_filters` | — |
| SRS-LAP-12 | Wajib | `tests/Modules/Lap/LaporanTest.php::test_SRS_LAP_12_drill_down_from_aggregate_to_group_to_records_to_original_record` | — |
| SRS-LAP-13 | Perlu | `tests/Modules/Lap/AiBiTest.php::test_SRS_LAP_13_plain_language_request_becomes_an_editable_saveable_report` | — |
| SRS-LAP-13 | Perlu | `tests/Modules/Lap/AiBiTest.php::test_SRS_LAP_13_a_prompt_cannot_widen_the_users_scope_or_datasets` | — |
| SRS-LAP-14 | Perlu | `tests/Modules/Lap/AiBiTest.php::test_SRS_LAP_14_smart_search_finds_lots_from_natural_keywords` | — |
| SRS-LAP-15 | Perlu | `tests/Modules/Lap/AnalitikTest.php::test_SRS_LAP_15_revenue_potential_hides_valuation_derived_money_without_rahsia` | — |
| SRS-LAP-16 | Perlu | `tests/Modules/Lap/AnalitikTest.php::test_SRS_LAP_16_arrears_risk_scores_history_age_and_balance` | — |
| SRS-LAP-17 | Perlu | `tests/Modules/Lap/AnalitikTest.php::test_SRS_LAP_17_idle_land_from_lease_status_and_monitoring` | — |
| SRS-LAP-18 | Perlu | `tests/Modules/Lap/AnalitikTest.php::test_SRS_LAP_18_development_potential_is_published_as_a_scoped_gis_layer` | — |
| SRS-LAP-19 | Perlu | `tests/Modules/Lap/AnalitikTest.php::test_SRS_LAP_19_recommendation_is_accepted_or_rejected_with_justification_and_audited` | — |
| SRS-LAP-20 | Perlu | `tests/Modules/Lap/AnalitikTest.php::test_SRS_LAP_20_executive_insight_is_generated_on_schedule_and_per_scope` | — |
| SRS-LAP-21 | Wajib | `tests/Modules/Lap/AnalitikTest.php::test_SRS_LAP_21_analytics_and_ai_run_locally_and_are_labelled_system_suggestions` | — |
| SRS-LAP-22 | Perlu | `tests/Modules/Lap/LaporanTest.php::test_SRS_LAP_22_system_usage_analytics_feed_the_upgrade_plan` | — |
| SRS-LAP-23 | Baik-ada | `tests/Modules/Lap/LaporanTest.php::test_SRS_LAP_23_data_dictionary_lists_metric_source_owner_and_field_definitions` | — |
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
| SRS-NTF-01 | Wajib | `tests/Modules/Ntf/CommandTest.php::test_SRS_NTF_01_ntf_test_command_sends_a_notification` | — |
| SRS-NTF-01 | Wajib | `tests/Modules/Ntf/EngineTest.php::test_SRS_NTF_01_one_send_delivers_in_app_and_email` | — |
| SRS-NTF-02 | Wajib | `tests/Modules/Ntf/EngineTest.php::test_SRS_NTF_02_single_engine_is_bound_to_the_contracts` | — |
| SRS-NTF-02 | Wajib | `tests/Modules/Ntf/EngineTest.php::test_SRS_NTF_02_unknown_type_is_rejected` | — |
| SRS-NTF-02 | Wajib | `tests/Modules/Ntf/EngineTest.php::test_SRS_NTF_02_inactive_type_and_inactive_users_are_skipped` | — |
| SRS-NTF-03 | Wajib | `tests/Modules/Ntf/AdminApiTest.php::test_SRS_NTF_03_permissions_are_declared_to_rbc` | — |
| SRS-NTF-03 | Wajib | `tests/Modules/Ntf/AdminApiTest.php::test_SRS_NTF_03_admin_configures_channels_priority_frequency_and_recipients_without_code` | — |
| SRS-NTF-03 | Wajib | `tests/Modules/Ntf/AdminApiTest.php::test_SRS_NTF_03_configuration_requires_ntf_permissions` | — |
| SRS-NTF-04 | Wajib | `tests/Modules/Ntf/EngineTest.php::test_SRS_NTF_04_sms_and_whatsapp_go_through_adapter_interfaces` | — |
| SRS-NTF-05 | Wajib | `tests/Modules/Ntf/AdminApiTest.php::test_SRS_NTF_05_template_edit_creates_a_new_active_version_used_by_next_notification` | — |
| SRS-NTF-05 | Wajib | `tests/Modules/Ntf/EngineTest.php::test_SRS_NTF_05_dynamic_fields_are_filled_in_malay_template` | — |
| SRS-NTF-06 | Wajib | `tests/Modules/Ntf/EngineTest.php::test_SRS_NTF_06_event_based_triggers` | — |
| SRS-NTF-06 | Wajib | `tests/Modules/Ntf/SchedulerTest.php::test_SRS_NTF_06_scheduler_registers_the_ntf_jobs` | — |
| SRS-NTF-07 | Wajib | `tests/Modules/Ntf/SchedulerTest.php::test_SRS_NTF_07_scheduled_reminder_runs_on_configured_days_through_both_channels` | — |
| SRS-NTF-07 | Wajib | `tests/Modules/Ntf/SchedulerTest.php::test_SRS_NTF_07_admin_changes_reminder_days_and_template_without_release` | — |
| SRS-NTF-08 | Wajib | `tests/Modules/Ntf/AdminApiTest.php::test_SRS_NTF_08_type_priorities_are_validated` | — |
| SRS-NTF-08 | Wajib | `tests/Modules/Ntf/EngineTest.php::test_SRS_NTF_08_priority_is_stored_and_orders_the_list` | — |
| SRS-NTF-09 | Wajib | `tests/Modules/Ntf/PreferenceApiTest.php::test_SRS_NTF_09_user_chooses_channels_or_opts_out_of_optional_types` | — |
| SRS-NTF-10 | Wajib | `tests/Modules/Ntf/PreferenceApiTest.php::test_SRS_NTF_10_mandatory_types_cannot_be_turned_off_and_are_shown_locked` | — |
| SRS-NTF-11 | Wajib | `tests/Modules/Ntf/InboxApiTest.php::test_SRS_NTF_11_mark_read_and_unread_individually_and_in_bulk_and_filter` | — |
| SRS-NTF-11 | Wajib | `tests/Modules/Ntf/InboxApiTest.php::test_SRS_NTF_11_users_only_see_and_change_their_own_notifications` | — |
| SRS-NTF-11 | Wajib | `tests/Modules/Ntf/InboxApiTest.php::test_SRS_NTF_11_bell_summary_returns_unread_count_and_latest` | — |
| SRS-NTF-12 | Wajib | `tests/Modules/Ntf/InboxApiTest.php::test_SRS_NTF_12_logical_delete_hides_item_but_keeps_delivery_log` | — |
| SRS-NTF-13 | Wajib | `tests/Modules/Ntf/InboxApiTest.php::test_SRS_NTF_13_archive_folder_is_kept_and_searchable` | — |
| SRS-NTF-14 | Perlu | `tests/Modules/Ntf/InboxApiTest.php::test_SRS_NTF_14_filter_by_type_priority_module_read_status_and_date` | — |
| SRS-NTF-15 | Wajib | `tests/Modules/Ntf/AdminApiTest.php::test_SRS_NTF_15_delivery_log_for_auditors_shows_channel_times_status_and_error` | — |
| SRS-NTF-15 | Wajib | `tests/Modules/Ntf/EngineTest.php::test_SRS_NTF_15_every_delivery_is_audited` | — |
| SRS-NTF-16 | Wajib | `tests/Modules/Ntf/EngineTest.php::test_SRS_NTF_16_hantar_terima_baca_are_timestamped` | — |
| SRS-NTF-17 | Perlu | `tests/Modules/Ntf/DeliveryRetryTest.php::test_SRS_NTF_17_job_retries_at_least_three_times_with_increasing_backoff` | — |
| SRS-NTF-17 | Perlu | `tests/Modules/Ntf/DeliveryRetryTest.php::test_SRS_NTF_17_failed_email_is_retried_then_marked_final_and_admin_alerted` | — |
| SRS-NTF-17 | Perlu | `tests/Modules/Ntf/DeliveryRetryTest.php::test_SRS_NTF_17_queue_failed_hook_marks_final` | — |
| SRS-NTF-17 | Perlu | `tests/Modules/Ntf/DeliveryRetryTest.php::test_SRS_NTF_17_alert_failures_never_alert_again` | — |
| SRS-NTF-18 | Wajib | `tests/Modules/Ntf/SchedulerTest.php::test_SRS_NTF_18_unread_kritikal_escalates_to_supervisor_once_and_is_logged` | — |
| SRS-NTF-18 | Wajib | `tests/Modules/Ntf/SchedulerTest.php::test_SRS_NTF_18_supervisor_resolution_is_a_replaceable_contract` | — |
| SRS-NTF-18 | Wajib | `tests/Modules/Ntf/SchedulerTest.php::test_SRS_NTF_18_fallback_supervisor_role_is_an_adm_parameter` | — |
| SRS-NTF-19 | Wajib | `tests/Modules/Ntf/EngineTest.php::test_SRS_NTF_19_sensitive_fields_are_refused` | — |
| SRS-NTF-19 | Wajib | `tests/Modules/Ntf/EngineTest.php::test_SRS_NTF_19_only_summary_and_internal_link_are_kept` | — |
| SRS-NTF-20 | Perlu | `tests/Modules/Ntf/SchedulerTest.php::test_SRS_NTF_20_biasa_and_maklumat_bundled_into_one_digest` | — |
| SRS-NTF-20 | Perlu | `tests/Modules/Ntf/SchedulerTest.php::test_SRS_NTF_20_kritikal_never_waits_for_a_digest` | — |
| SRS-NTF-21 | Perlu | `tests/Modules/Ntf/EngineTest.php::test_SRS_NTF_21_delivery_goes_through_a_persistent_queue` | — |
| SRS-NTF-22 | Perlu | `tests/Modules/Ntf/EngineTest.php::test_SRS_NTF_22_same_event_to_same_recipient_is_sent_once_in_quiet_period` | — |
| SRS-NTF-23 | Perlu | `tests/Modules/Ntf/AdminApiTest.php::test_SRS_NTF_23_monitoring_dashboard_shows_queue_success_rate_and_recent_failures` | — |
| SRS-NTF-23 | Perlu | `tests/Modules/Ntf/DeliveryRetryTest.php::test_SRS_NTF_23_admin_can_retry_a_failed_delivery` | — |
| SRS-NTF-24 | Baik-ada | `tests/Modules/Ntf/AnnouncementApiTest.php::test_SRS_NTF_24_admin_announces_to_groups_by_role_bahagian_and_daerah` | — |
| SRS-NTF-24 | Baik-ada | `tests/Modules/Ntf/AnnouncementApiTest.php::test_SRS_NTF_24_validation_empty_group_and_rate_limit` | — |
| SRS-PER-01 | Wajib | `tests/Modules/Per/PihakDanCapaianTest.php::test_SRS_PER_01_out_of_scope_district_and_bahagian_cannot_see_or_register` | — |
| SRS-PER-01 | Wajib | `tests/Modules/Per/PortalDanLaporanTest.php::test_SRS_PER_01_sample_cases_command_covers_all_channels_and_stages` | — |
| SRS-PER-01 | Wajib | `tests/Modules/Per/SaluranTest.php::test_SRS_PER_01_eleven_channels_with_code_lampiran_h_icon_colour_bahagian_and_target` | — |
| SRS-PER-01 | Wajib | `tests/Modules/Per/SaluranTest.php::test_SRS_PER_01_each_channel_has_its_own_form_fields_and_mandatory_checklist` | — |
| SRS-PER-01 | Wajib | `tests/Modules/Per/SaluranTest.php::test_SRS_PER_01_unknown_channel_field_rejected_and_types_validated` | — |
| SRS-PER-01 | Wajib | `tests/Modules/Per/SaluranTest.php::test_SRS_PER_01_permission_required` | — |
| SRS-PER-02 | Wajib | `tests/Modules/Per/Pemberimilikan5aTest.php::test_SRS_PER_02_five_a_case_tracked_from_etanah_to_vault` | — |
| SRS-PER-03 | Wajib | `tests/Modules/Per/Pemberimilikan5aTest.php::test_SRS_PER_03_mmkn_decision_other_than_approval_stops_the_case` | — |
| SRS-PER-04 | Wajib | `tests/Modules/Per/Pemberimilikan5aTest.php::test_SRS_PER_04_reminder_to_kewangan_and_officer_before_payment_deadline` | — |
| SRS-PER-05 | Wajib | `tests/Modules/Per/Pemberimilikan5aTest.php::test_SRS_PER_05_payment_confirmation_requires_kewangan_and_segregation` | — |
| SRS-PER-06 | Wajib | `tests/Modules/Per/SaluranLainTest.php::test_SRS_PER_06_serah_milik_memo_to_registration_and_vault` | — |
| SRS-PER-06 | Wajib | `tests/Modules/Per/SaluranLainTest.php::test_SRS_PER_06_serahan_hakmilik_flow_closes` | — |
| SRS-PER-07 | Wajib | `tests/Modules/Per/SaluranLainTest.php::test_SRS_PER_07_daftar_kematian_heirs_and_property_verification` | — |
| SRS-PER-08 | Wajib | `tests/Modules/Per/SaluranLainTest.php::test_SRS_PER_08_perintah_arb_order_and_title_status_update` | — |
| SRS-PER-09 | Wajib | `tests/Modules/Per/SaluranLainTest.php::test_SRS_PER_09_serahan_hakmilik_records_ptg_registration` | — |
| SRS-PER-10 | Wajib | `tests/Modules/Per/SaluranLainTest.php::test_SRS_PER_10_perintah_mahkamah_la_probet_with_heirs` | — |
| SRS-PER-11 | Wajib | `tests/Modules/Per/SaluranLainTest.php::test_SRS_PER_11_pusaka_jkptgs_small_estate_certificate` | — |
| SRS-PER-12 | Wajib | `tests/Modules/Per/PortalDanLaporanTest.php::test_SRS_PER_12_portal_adapter_pull_creates_pending_cases` | — |
| SRS-PER-12 | Wajib | `tests/Modules/Per/SaluranLainTest.php::test_SRS_PER_12_wakaf_ikrar_syariah_and_ptg_registration` | — |
| SRS-PER-12 | Wajib | `tests/Modules/Per/SaluranLainTest.php::test_SRS_PER_12_wakaf_cannot_pass_syariah_stage_unverified` | — |
| SRS-PER-13 | Wajib | `tests/Modules/Per/SaluranLainTest.php::test_SRS_PER_13_faraid_heirs_verified_through_e_faraid` | — |
| SRS-PER-13 | Wajib | `tests/Modules/Per/SaluranLainTest.php::test_SRS_PER_13_faraid_flow_closes` | — |
| SRS-PER-14 | Wajib | `tests/Modules/Per/SaluranLainTest.php::test_SRS_PER_14_pembelian_terus_spa_payment_and_ptg_confirmation` | — |
| SRS-PER-15 | Wajib | `tests/Modules/Per/SaluranLainTest.php::test_SRS_PER_15_tanah_warta_gazette_type_purpose_number_and_date` | — |
| SRS-PER-16 | Wajib | `tests/Modules/Per/DokumenDanPeringkatTest.php::test_SRS_PER_16_incomplete_mandatory_documents_block_approval_and_list_is_exact` | — |
| SRS-PER-16 | Wajib | `tests/Modules/Per/DokumenDanPeringkatTest.php::test_SRS_PER_16_documents_go_through_dms_as_sulit_with_owner_pemerolehan` | — |
| SRS-PER-17 | Wajib | `tests/Modules/Per/SaluranTest.php::test_SRS_PER_17_stages_and_targets_configurable_per_channel_for_new_cases` | — |
| SRS-PER-18 | Wajib | `tests/Modules/Per/DokumenDanPeringkatTest.php::test_SRS_PER_18_each_stage_records_officer_entry_exit_duration_and_target` | — |
| SRS-PER-19 | Perlu | `tests/Modules/Per/DokumenDanPeringkatTest.php::test_SRS_PER_19_timeline_shows_stages_officers_durations_chronologically` | — |
| SRS-PER-20 | Wajib | `tests/Modules/Per/LotDanTutupTest.php::test_SRS_PER_20_registered_lot_number_is_linked_directly` | — |
| SRS-PER-20 | Wajib | `tests/Modules/Per/LotDanTutupTest.php::test_SRS_PER_20_expected_lot_relinked_when_lot_is_registered` | — |
| SRS-PER-21 | Wajib | `tests/Modules/Per/LotDanTutupTest.php::test_SRS_PER_21_closure_creates_title_in_hkm_with_vault_and_duration_summary` | — |
| SRS-PER-21 | Wajib | `tests/Modules/Per/LotDanTutupTest.php::test_SRS_PER_21_cannot_close_before_final_stage_or_with_unregistered_lot` | — |
| SRS-PER-21 | Wajib | `tests/Modules/Per/LotDanTutupTest.php::test_SRS_PER_21_without_hkm_the_case_stays_open` | — |
| SRS-PER-22 | Wajib | `tests/Modules/Per/DokumenDanPeringkatTest.php::test_SRS_PER_22_physical_original_location_and_issue_return_history` | — |
| SRS-PER-23 | Wajib | `tests/Modules/Per/PihakDanCapaianTest.php::test_SRS_PER_23_parties_recorded_by_channel_role_with_relationship_and_verification` | — |
| SRS-PER-23 | Wajib | `tests/Modules/Per/PihakDanCapaianTest.php::test_SRS_PER_23_personal_data_masked_without_sulit_permission` | — |
| SRS-PER-23 | Wajib | `tests/Modules/Per/PihakDanCapaianTest.php::test_SRS_PER_23_personal_fields_masked_in_audit_trail` | — |
| SRS-PER-23 | Wajib | `tests/Modules/Per/PihakDanCapaianTest.php::test_SRS_PER_23_officer_without_sulit_cannot_overwrite_personal_fields` | — |
| SRS-PER-24 | Perlu | `tests/Modules/Per/LotDanTutupTest.php::test_SRS_PER_24_acquisition_values_recorded` | — |
| SRS-PER-25 | Perlu | `tests/Modules/Per/PortalDanLaporanTest.php::test_SRS_PER_25_portal_submission_lands_as_pending_wakaf_case_without_touching_master` | — |
| SRS-PER-25 | Perlu | `tests/Modules/Per/PortalDanLaporanTest.php::test_SRS_PER_25_pending_public_case_cannot_progress_until_accepted` | — |
| SRS-PER-26 | Perlu | `tests/Modules/Per/DokumenDanPeringkatTest.php::test_SRS_PER_26_stalled_case_escalated_automatically_to_officer_and_head` | — |
| SRS-PER-27 | Wajib | `tests/Modules/Per/PortalDanLaporanTest.php::test_SRS_PER_27_report_by_channel_district_period_and_status_matches_database` | — |
| SRS-PER-27 | Wajib | `tests/Modules/Per/PortalDanLaporanTest.php::test_SRS_PER_27_special_lists_and_scope` | — |
| SRS-PER-27 | Wajib | `tests/Modules/Per/PortalDanLaporanTest.php::test_SRS_PER_27_export_csv_is_audited` | — |
| SRS-PER-28 | Perlu | `tests/Modules/Per/PortalDanLaporanTest.php::test_SRS_PER_28_lot_profile_section_shows_history_with_channel_colour_and_timeline` | — |
| SRS-PER-28 | Perlu | `tests/Modules/Per/PortalDanLaporanTest.php::test_SRS_PER_28_global_search_finds_cases_within_scope` | — |
| SRS-PER-29 | Wajib | `tests/Modules/Per/KelulusanTest.php::test_SRS_PER_29_entering_approval_starts_wfe_flow_with_channel_and_value_context` | — |
| SRS-PER-29 | Wajib | `tests/Modules/Per/KelulusanTest.php::test_SRS_PER_29_rejection_returns_case_for_resubmission` | — |
| SRS-PER-29 | Wajib | `tests/Modules/Per/KelulusanTest.php::test_SRS_PER_29_registrar_approving_own_case_is_refused` | — |
| SRS-PER-29 | Wajib | `tests/Modules/Per/KelulusanTest.php::test_SRS_PER_29_without_an_active_flow_the_case_does_not_move` | — |
| SRS-PER-29 | Wajib | `tests/Modules/Per/KelulusanTest.php::test_SRS_PER_29_foreign_workflow_events_are_ignored` | — |
| SRS-PER-29 | Wajib | `tests/Modules/Per/KelulusanTest.php::test_SRS_PER_29_layered_approval_through_real_wfe_with_segregation_of_duties` | — |
| SRS-PER-30 | Wajib | `tests/Modules/Per/PihakDanCapaianTest.php::test_SRS_PER_30_cancel_recorded_with_reason` | — |
| SRS-PER-30 | Wajib | `tests/Modules/Per/PihakDanCapaianTest.php::test_SRS_PER_30_every_change_audited_with_old_new_values_and_classification` | — |
| SRS-PJK-01 | Wajib | `tests/Modules/Pjk/KontrakLifecycleTest.php::test_SRS_PJK_01_contract_records_tenant_premise_period_rate_deposit_terms_category_and_status` | — |
| SRS-PJK-01 | Wajib | `tests/Modules/Pjk/KontrakLifecycleTest.php::test_SRS_PJK_01_validation_rejects_bad_dates_rate_and_overlapping_premise` | — |
| SRS-PJK-01 | Wajib | `tests/Modules/Pjk/KontrakLifecycleTest.php::test_SRS_PJK_01_access_requires_permission_and_district_scope` | — |
| SRS-PJK-01 | Wajib | `tests/Modules/Pjk/KontrakLifecycleTest.php::test_SRS_PJK_01_acceptance_1_portal_application_to_active_contract` | — |
| SRS-PJK-01 | Wajib | `tests/Modules/Pjk/PenyewaPremisTest.php::test_SRS_PJK_01_tenant_register_masks_identity_and_full_view_needs_sulit_permission` | — |
| SRS-PJK-01 | Wajib | `tests/Modules/Pjk/PenyewaPremisTest.php::test_SRS_PJK_01_tenant_validation_identity_formats_and_uniqueness` | — |
| SRS-PJK-01 | Wajib | `tests/Modules/Pjk/PenyewaPremisTest.php::test_SRS_PJK_01_district_officer_sees_only_tenants_of_contracts_in_scope` | — |
| SRS-PJK-01 | Wajib | `tests/Modules/Pjk/SampleCommandTest.php::test_SRS_PJK_01_synthetic_sample_data_on_existing_lots_respects_bahagian_and_is_flagged` | — |
| SRS-PJK-02 | Wajib | `tests/Modules/Pjk/KontrakLifecycleTest.php::test_SRS_PJK_02_status_transitions_recorded_with_date_and_officer` | — |
| SRS-PJK-02 | Wajib | `tests/Modules/Pjk/KontrakLifecycleTest.php::test_SRS_PJK_02_automatic_hampir_tamat_and_tamat_tempoh` | — |
| SRS-PJK-02 | Wajib | `tests/Modules/Pjk/PindaanTest.php::test_SRS_PJK_02_termination_releases_premise_and_cancels_future_charges` | — |
| SRS-PJK-03 | Wajib | `tests/Modules/Pjk/KontrakLifecycleTest.php::test_SRS_PJK_03_premise_status_changes_automatically_on_activation` | — |
| SRS-PJK-03 | Wajib | `tests/Modules/Pjk/PenyewaPremisTest.php::test_SRS_PJK_03_premise_register_statuses_and_history` | — |
| SRS-PJK-04 | Wajib | `tests/Modules/Pjk/PenyewaPremisTest.php::test_SRS_PJK_04_catalogue_filters_and_public_api_exposes_only_awam_fields` | — |
| SRS-PJK-05 | Wajib | `tests/Modules/Pjk/KontrakLifecycleTest.php::test_SRS_PJK_05_approval_runs_in_wfe_with_value_and_category_context_never_locally` | — |
| SRS-PJK-05 | Wajib | `tests/Modules/Pjk/KontrakLifecycleTest.php::test_SRS_PJK_05_missing_flow_is_a_clear_error` | — |
| SRS-PJK-05 | Wajib | `tests/Modules/Pjk/KontrakLifecycleTest.php::test_SRS_PJK_05_activation_creates_schedule_deposit_and_first_version` | — |
| SRS-PJK-05 | Wajib | `tests/Modules/Pjk/LaporanIntegrasiTest.php::test_SRS_PJK_05_permissions_flows_and_notification_types_are_declared` | — |
| SRS-PJK-05 | Wajib | `tests/Modules/Pjk/WorkflowIntegrationTest.php::test_SRS_PJK_05_seeded_layered_flow_routes_wakaf_contract_to_wakaf_head_then_activates` | — |
| SRS-PJK-06 | Wajib | `tests/Modules/Pjk/PenyewaPremisTest.php::test_SRS_PJK_06_counter_applications_join_the_same_flow_and_can_be_rejected` | — |
| SRS-PJK-07 | Perlu | `tests/Modules/Pjk/PindaanTest.php::test_SRS_PJK_07_rate_review_compares_with_comparable_contracts_and_gates_rahsia_valuation` | — |
| SRS-PJK-08 | Wajib | `tests/Modules/Pjk/PindaanTest.php::test_SRS_PJK_08_acceptance_5_expiry_reminders_at_6_3_1_months_and_configurable_offsets` | — |
| SRS-PJK-08 | Wajib | `tests/Modules/Pjk/PindaanTest.php::test_SRS_PJK_08_payment_due_reminders` | — |
| SRS-PJK-09 | Wajib | `tests/Modules/Pjk/PelarasanSuratTest.php::test_SRS_PJK_09_letters_from_versioned_templates_with_merge_fields_and_reference_numbers` | — |
| SRS-PJK-10 | Wajib | `tests/Modules/Pjk/PindaanTest.php::test_SRS_PJK_10_renewal_creates_new_agreement_version_with_documents_and_carries_deposit` | — |
| SRS-PJK-10 | Wajib | `tests/Modules/Pjk/PindaanTest.php::test_SRS_PJK_10_rate_change_and_term_amendment_versions_keep_before_and_after` | — |
| SRS-PJK-11 | Wajib | `tests/Modules/Pjk/JadualLejarTest.php::test_SRS_PJK_11_ledger_running_balance_and_period_view` | — |
| SRS-PJK-11 | Wajib | `tests/Modules/Pjk/JadualLejarTest.php::test_SRS_PJK_11_tenant_statement_exports_pdf_and_excel_and_is_audited` | — |
| SRS-PJK-11 | Wajib | `tests/Modules/Pjk/JadualLejarTest.php::test_SRS_PJK_11_ledger_is_append_only_at_database_level` | — |
| SRS-PJK-11 | Wajib | `tests/Modules/Pjk/JadualLejarTest.php::test_SRS_PJK_11_ledger_rows_cannot_be_deleted` | — |
| SRS-PJK-12 | Wajib | `tests/Modules/Pjk/JadualLejarTest.php::test_SRS_PJK_12_schedule_generated_from_terms_with_cycle_and_prorated_last_period` | — |
| SRS-PJK-12 | Wajib | `tests/Modules/Pjk/JadualLejarTest.php::test_SRS_PJK_12_due_charges_posted_daily_and_arrears_aged_in_four_buckets` | — |
| SRS-PJK-12 | Wajib | `tests/Modules/Pjk/JadualLejarTest.php::test_SRS_PJK_12_late_penalty_charged_once_per_month` | — |
| SRS-PJK-13 | Wajib | `tests/Modules/Pjk/BayaranTest.php::test_SRS_PJK_13_deposit_separate_from_rent_and_settled_with_finance_approval` | — |
| SRS-PJK-14 | Wajib | `tests/Modules/Pjk/GatewayTest.php::test_SRS_PJK_14_acceptance_2_fpx_and_card_payments_credit_ledger_and_email_serial_receipts` | — |
| SRS-PJK-14 | Wajib | `tests/Modules/Pjk/GatewayTest.php::test_SRS_PJK_14_gateway_outage_falls_back_to_counter_payment` | — |
| SRS-PJK-14 | Wajib | `tests/Modules/Pjk/GatewayTest.php::test_SRS_PJK_14_test_gateway_page_simulation_goes_through_the_webhook` | — |
| SRS-PJK-14 | Wajib | `tests/Modules/Pjk/GatewayTest.php::test_SRS_PJK_14_portal_can_start_a_payment_for_a_contract` | — |
| SRS-PJK-15 | Wajib | `tests/Modules/Pjk/GatewayTest.php::test_SRS_PJK_15_unique_reference_linked_to_contract_and_charges_with_statuses` | — |
| SRS-PJK-15 | Wajib | `tests/Modules/Pjk/GatewayTest.php::test_SRS_PJK_15_idempotency_key_returns_the_same_transaction` | — |
| SRS-PJK-16 | Wajib | `tests/Modules/Pjk/GatewayTest.php::test_SRS_PJK_16_callback_signature_is_verified_and_events_are_idempotent` | — |
| SRS-PJK-16 | Wajib | `tests/Modules/Pjk/GatewayTest.php::test_SRS_PJK_16_acceptance_3_missing_callback_resolved_by_status_query_without_duplicate_credit` | — |
| SRS-PJK-16 | Wajib | `tests/Modules/Pjk/GatewayTest.php::test_SRS_PJK_16_unresolved_transactions_end_as_tamat_masa` | — |
| SRS-PJK-16 | Wajib | `tests/Modules/Pjk/GatewayTest.php::test_SRS_PJK_16_amount_mismatch_is_never_credited` | — |
| SRS-PJK-17 | Wajib | `tests/Modules/Pjk/BayaranTest.php::test_SRS_PJK_17_receipts_are_sequential_immutable_emailed_and_kept_in_dms` | — |
| SRS-PJK-17 | Wajib | `tests/Modules/Pjk/BayaranTest.php::test_SRS_PJK_17_payment_without_email_records_delivery_status` | — |
| SRS-PJK-18 | Wajib | `tests/Modules/Pjk/RekonsiliasiTest.php::test_SRS_PJK_18_acceptance_4_uploaded_settlement_file_yields_the_three_discrepancy_kinds` | — |
| SRS-PJK-18 | Wajib | `tests/Modules/Pjk/RekonsiliasiTest.php::test_SRS_PJK_18_daily_run_against_the_gateway_settlement_statement` | — |
| SRS-PJK-19 | Wajib | `tests/Modules/Pjk/BayaranTest.php::test_SRS_PJK_19_offline_payment_with_proof_is_credited_only_after_finance_confirms` | — |
| SRS-PJK-19 | Wajib | `tests/Modules/Pjk/BayaranTest.php::test_SRS_PJK_19_recorder_cannot_confirm_own_payment_and_others_need_permission` | — |
| SRS-PJK-19 | Wajib | `tests/Modules/Pjk/BayaranTest.php::test_SRS_PJK_19_manual_matching_to_a_specific_charge` | — |
| SRS-PJK-20 | Perlu | `tests/Modules/Pjk/BayaranTest.php::test_SRS_PJK_20_partial_payment_allocated_by_configured_priority_oldest_first` | — |
| SRS-PJK-21 | Wajib | `tests/Modules/Pjk/RekonsiliasiTest.php::test_SRS_PJK_21_grp_and_esewa_receipts_reconciled_and_differences_reported` | — |
| SRS-PJK-22 | Wajib | `tests/Modules/Pjk/AgihanTest.php::test_SRS_PJK_22_rates_recorded_for_all_five_categories_with_version_dates_and_approval_reference` | — |
| SRS-PJK-23 | Wajib | `tests/Modules/Pjk/AgihanTest.php::test_SRS_PJK_23_wakaf_or_purchased_decided_from_the_acquisition_channel` | — |
| SRS-PJK-24 | Wajib | `tests/Modules/Pjk/AgihanTest.php::test_SRS_PJK_24_acceptance_6_calculation_for_five_places_matches_manual_figures` | — |
| SRS-PJK-24 | Wajib | `tests/Modules/Pjk/AgihanTest.php::test_SRS_PJK_24_deposits_are_not_revenue` | — |
| SRS-PJK-25 | Perlu | `tests/Modules/Pjk/AgihanTest.php::test_SRS_PJK_25_simulation_with_proposed_rates_is_not_stored_and_portal_sees_only_confirmed` | — |
| SRS-PJK-26 | Wajib | `tests/Modules/Pjk/LaporanIntegrasiTest.php::test_SRS_PJK_26_occupancy_dashboard_by_district_and_category_with_vacancy_and_potential_revenue` | — |
| SRS-PJK-26 | Wajib | `tests/Modules/Pjk/LaporanIntegrasiTest.php::test_SRS_PJK_26_map_layer_and_dashboard_kpis_are_registered_and_scoped` | — |
| SRS-PJK-27 | Wajib | `tests/Modules/Pjk/LaporanIntegrasiTest.php::test_SRS_PJK_27_reports_by_status_location_category_tenant_with_pdf_and_excel_export` | — |
| SRS-PJK-27 | Wajib | `tests/Modules/Pjk/LaporanIntegrasiTest.php::test_SRS_PJK_27_profile_section_and_financial_series_feed_the_360_profile` | — |
| SRS-PJK-28 | Wajib | `tests/Modules/Pjk/PelarasanSuratTest.php::test_SRS_PJK_28_acceptance_7_adjustment_cannot_be_completed_by_its_requester` | — |
| SRS-PJK-28 | Wajib | `tests/Modules/Pjk/PelarasanSuratTest.php::test_SRS_PJK_28_receipt_cancellation_by_credit_receipt_and_waiver` | — |
| SRS-PJK-28 | Wajib | `tests/Modules/Pjk/WorkflowIntegrationTest.php::test_SRS_PJK_28_seeded_adjustment_flow_requires_a_second_officer` | — |
| SRS-PRO-01 | Wajib | `tests/Modules/Pro/LotMasterTest.php::test_SRS_PRO_01_concurrent_edit_is_rejected_by_optimistic_locking` | — |
| SRS-PRO-01 | Wajib | `tests/Modules/Pro/LotMasterTest.php::test_SRS_PRO_01_failed_transaction_rolls_back_completely` | — |
| SRS-PRO-02 | Wajib | `tests/Modules/Pro/GovernanceTest.php::test_SRS_PRO_02_justification_document_matches_the_installed_database` | — |
| SRS-PRO-03 | Wajib | `tests/Modules/Pro/GovernanceTest.php::test_SRS_PRO_03_04_document_generator_writes_erd_physical_model_and_dictionary` | — |
| SRS-PRO-04 | Wajib | `tests/Modules/Pro/GovernanceTest.php::test_SRS_PRO_04_executable_ddl_is_generated_from_the_live_schema` | — |
| SRS-PRO-05 | Wajib | `tests/Modules/Pro/GovernanceTest.php::test_SRS_PRO_05_data_dictionary_is_generated_from_schema_merged_with_business_metadata` | — |
| SRS-PRO-06 | Wajib | `tests/Modules/Pro/LotMasterTest.php::test_SRS_PRO_06_schema_has_the_ten_core_entities_as_separate_tables` | — |
| SRS-PRO-06 | Wajib | `tests/Modules/Pro/LotMasterTest.php::test_SRS_PRO_06_lot_registry_contract_applies_the_users_scope` | — |
| SRS-PRO-07 | Wajib | `tests/Modules/Pro/LotMasterTest.php::test_SRS_PRO_07_data_spatial_is_a_full_entity_with_geometry_srid_source_date_and_status` | — |
| SRS-PRO-08 | Wajib | `tests/Modules/Pro/SpatialTest.php::test_SRS_PRO_08_geometry_in_gdm2000_selangor_cassini_is_reprojected_to_wgs84` | — |
| SRS-PRO-08 | Wajib | `tests/Modules/Pro/SpatialTest.php::test_SRS_PRO_08_kml_and_geojson_feature_collections_are_accepted` | — |
| SRS-PRO-08 | Wajib | `tests/Modules/Pro/SpatialTest.php::test_SRS_PRO_08_invalid_geometry_wrong_srid_or_non_polygon_are_rejected_in_malay` | — |
| SRS-PRO-09 | Wajib | `tests/Modules/Pro/SpatialTest.php::test_SRS_PRO_09_source_acquisition_date_uploader_and_file_are_recorded` | — |
| SRS-PRO-10 | Wajib | `tests/Modules/Pro/LotMasterTest.php::test_SRS_PRO_10_list_summary_counts_verification_flags` | — |
| SRS-PRO-10 | Wajib | `tests/Modules/Pro/SpatialTest.php::test_SRS_PRO_10_overlap_marks_geometry_bertindih_and_area_mismatch_is_warned` | — |
| SRS-PRO-10 | Wajib | `tests/Modules/Pro/SpatialTest.php::test_SRS_PRO_10_unverified_geometry_is_flagged_on_list_profile_and_map` | — |
| SRS-PRO-10 | Wajib | `tests/Modules/Pro/SpatialTest.php::test_SRS_PRO_10_authorised_officer_verifies_but_not_their_own_upload` | — |
| SRS-PRO-11 | Wajib | `tests/Modules/Pro/GovernanceTest.php::test_SRS_PRO_11_stewardship_register_covers_every_main_data_component` | — |
| SRS-PRO-12 | Wajib | `tests/Modules/Pro/GovernanceTest.php::test_SRS_PRO_12_quality_failures_go_to_the_correct_steward_with_notification_and_corrective_action` | — |
| SRS-PRO-12 | Wajib | `tests/Modules/Pro/ImportTest.php::test_SRS_PRO_12_rejected_import_rows_are_routed_to_the_steward` | — |
| SRS-PRO-13 | Wajib | `tests/Modules/Pro/LotMasterTest.php::test_SRS_PRO_13_create_lot_normalises_number_and_derives_daerah` | — |
| SRS-PRO-13 | Wajib | `tests/Modules/Pro/LotMasterTest.php::test_SRS_PRO_13_required_fields_and_ranges_are_enforced_with_malay_messages_naming_the_field` | — |
| SRS-PRO-13 | Wajib | `tests/Modules/Pro/LotMasterTest.php::test_SRS_PRO_13_quality_rules_are_configurable` | — |
| SRS-PRO-14 | Wajib | `tests/Modules/Pro/GovernanceTest.php::test_SRS_PRO_14_classification_register_per_data_component` | — |
| SRS-PRO-14 | Wajib | `tests/Modules/Pro/LotMasterTest.php::test_SRS_PRO_14_sulit_lots_and_fields_need_explicit_permission` | — |
| SRS-PRO-15 | Wajib | `tests/Modules/Pro/ImportTest.php::test_SRS_PRO_15_downloadable_templates_per_core_entity` | — |
| SRS-PRO-15 | Wajib | `tests/Modules/Pro/ImportTest.php::test_SRS_PRO_15_column_mapping_is_configurable_and_saved` | — |
| SRS-PRO-16 | Wajib | `tests/Modules/Pro/ImportTest.php::test_SRS_PRO_16_rows_are_validated_and_previewed_before_anything_is_committed` | — |
| SRS-PRO-16 | Wajib | `tests/Modules/Pro/ImportTest.php::test_SRS_PRO_16_spatial_import_checks_lot_existence_and_geometry` | — |
| SRS-PRO-17 | Wajib | `tests/Modules/Pro/ImportTest.php::test_SRS_PRO_17_report_lists_rejected_rows_is_exportable_and_original_file_is_kept` | — |
| SRS-PRO-17 | Wajib | `tests/Modules/Pro/ImportTest.php::test_SRS_PRO_17_rejected_original_file_is_reported_not_lost_silently` | — |
| SRS-PRO-18 | Wajib | `tests/Modules/Pro/ProfileApiTest.php::test_SRS_PRO_18_export_formats_respect_role_and_classification` | — |
| SRS-PRO-19 | Wajib | `tests/Modules/Pro/LotMasterTest.php::test_SRS_PRO_19_create_update_delete_are_audited_with_reason` | — |
| SRS-PRO-20 | Wajib | `tests/Modules/Pro/GovernanceTest.php::test_SRS_PRO_20_matching_criteria_are_configurable` | — |
| SRS-PRO-20 | Wajib | `tests/Modules/Pro/GovernanceTest.php::test_SRS_PRO_20_steward_confirms_merges_or_rejects_and_decision_is_audited` | — |
| SRS-PRO-21 | Wajib | `tests/Modules/Pro/LotMasterTest.php::test_SRS_PRO_21_tags_are_searchable_metadata` | — |
| SRS-PRO-22 | Wajib | `tests/Modules/Pro/LotMasterTest.php::test_SRS_PRO_22_non_critical_change_applies_immediately_as_a_new_version` | — |
| SRS-PRO-22 | Wajib | `tests/Modules/Pro/LotMasterTest.php::test_SRS_PRO_22_critical_change_needs_second_officer_via_workflow` | — |
| SRS-PRO-22 | Wajib | `tests/Modules/Pro/LotMasterTest.php::test_SRS_PRO_22_rejected_or_self_approved_critical_change_is_not_applied` | — |
| SRS-PRO-22 | Wajib | `tests/Modules/Pro/LotMasterTest.php::test_SRS_PRO_22_missing_workflow_configuration_is_reported_and_nothing_changes` | — |
| SRS-PRO-23 | Wajib | `tests/Modules/Pro/GovernanceTest.php::test_SRS_PRO_23_retention_archive_after_approval_searchable_and_restorable` | — |
| SRS-PRO-24 | Wajib | `tests/Modules/Pro/GovernanceTest.php::test_SRS_PRO_24_quality_report_categories_with_filters_and_export` | — |
| SRS-PRO-25 | Wajib | `tests/Modules/Pro/LotMasterTest.php::test_SRS_PRO_25_reference_lists_come_from_mod_ref` | — |
| SRS-PRO-26 | Wajib | `tests/Modules/Pro/ProfileApiTest.php::test_SRS_PRO_26_profile_has_six_tabs_own_sections_and_placeholders_for_missing_modules` | — |
| SRS-PRO-26 | Wajib | `tests/Modules/Pro/ProfileApiTest.php::test_SRS_PRO_26_sections_registered_by_other_modules_replace_their_placeholder` | — |
| SRS-PRO-26 | Wajib | `tests/Modules/Pro/ProfileApiTest.php::test_SRS_PRO_26_acceptance_profile_shows_everything_in_one_view_and_logs_access` | — |
| SRS-PRO-27 | Perlu | `tests/Modules/Pro/ProfileApiTest.php::test_SRS_PRO_27_timeline_aggregates_events_from_sections_newest_first_with_links` | — |
| SRS-PRO-28 | Perlu | `tests/Modules/Pro/ProfileApiTest.php::test_SRS_PRO_28_revenue_and_cost_are_bucketed_monthly_and_yearly_with_net_return` | — |
| SRS-PRO-29 | Perlu | `tests/Modules/Pro/ProfileApiTest.php::test_SRS_PRO_29_scoped_api_keys_with_expiry_rate_limit_revocation_and_usage_log` | — |
| SRS-PRO-29 | Perlu | `tests/Modules/Pro/ProfileApiTest.php::test_SRS_PRO_29_expired_key_is_refused_and_openapi_document_is_served` | — |
| SRS-PRO-29 | Perlu | `tests/Modules/Pro/ProfileApiTest.php::test_SRS_PRO_29_api_key_cannot_exceed_its_owners_permissions` | — |
| SRS-PRO-29 | Perlu | `tests/Modules/Pro/SpatialTest.php::test_SRS_PRO_29_geojson_of_one_lot` | — |
| SRS-PRO-30 | Perlu | `tests/Modules/Pro/ProfileApiTest.php::test_SRS_PRO_30_every_view_export_and_print_is_logged_and_visible_to_authorised_roles` | — |
| SRS-PTN-01 | Wajib | `tests/Modules/Ptn/JadualTest.php::test_SRS_PTN_01_plan_records_schedule_officer_lot_objective_and_status` | — |
| SRS-PTN-01 | Wajib | `tests/Modules/Ptn/JadualTest.php::test_SRS_PTN_01_auto_assignment_round_robin_within_district` | — |
| SRS-PTN-01 | Wajib | `tests/Modules/Ptn/JadualTest.php::test_SRS_PTN_01_access_is_by_permission_and_district_scope` | — |
| SRS-PTN-02 | Perlu | `tests/Modules/Ptn/JadualTest.php::test_SRS_PTN_02_annual_schedule_generated_with_frequency_by_risk_level` | — |
| SRS-PTN-02 | Perlu | `tests/Modules/Ptn/JadualTest.php::test_SRS_PTN_02_frequency_is_configurable_per_lot_category_and_shifted_after_last_visit` | — |
| SRS-PTN-03 | Wajib | `tests/Modules/Ptn/LapanganTest.php::test_SRS_PTN_03_visit_records_condition_encroachment_maintenance_photos_coordinates_and_recommendation` | — |
| SRS-PTN-04 | Wajib | `tests/Modules/Ptn/LapanganTest.php::test_SRS_PTN_04_checklist_must_be_complete_with_baik_isu_kritikal_status` | — |
| SRS-PTN-04 | Wajib | `tests/Modules/Ptn/LapanganTest.php::test_SRS_PTN_04_template_configuration_requires_the_five_categories_and_versions` | — |
| SRS-PTN-05 | Wajib | `tests/Modules/Ptn/LapanganTest.php::test_SRS_PTN_05_field_officer_downloads_only_own_assignments_as_offline_bundle` | — |
| SRS-PTN-05 | Wajib | `tests/Modules/Ptn/LapanganTest.php::test_SRS_PTN_05_offline_visit_syncs_idempotently_without_losing_records` | — |
| SRS-PTN-05 | Wajib | `tests/Modules/Ptn/LapanganTest.php::test_SRS_PTN_05_schedule_changed_after_download_is_kept_as_conflict_for_the_supervisor` | — |
| SRS-PTN-05 | Wajib | `tests/Modules/Ptn/LapanganTest.php::test_SRS_PTN_05_cannot_sync_into_a_schedule_outside_district_or_without_permission` | — |
| SRS-PTN-06 | Wajib | `tests/Modules/Ptn/LapanganTest.php::test_SRS_PTN_06_photo_stored_in_dms_and_tagged_to_lot_and_gis_layer` | — |
| SRS-PTN-07 | Wajib | `tests/Modules/Ptn/LapanganTest.php::test_SRS_PTN_07_photo_inside_boundary_accepted_outside_flagged_for_supervisor` | — |
| SRS-PTN-07 | Wajib | `tests/Modules/Ptn/LapanganTest.php::test_SRS_PTN_07_geotag_and_timestamp_cannot_be_changed_and_gallery_photos_are_marked` | — |
| SRS-PTN-08 | Wajib | `tests/Modules/Ptn/PenemuanTest.php::test_SRS_PTN_08_lot_update_proposal_applied_to_master_only_after_approval` | — |
| SRS-PTN-09 | Wajib | `tests/Modules/Ptn/LaporanLawatanTest.php::test_SRS_PTN_09_report_generated_from_form_and_photos_and_sent_for_validation` | — |
| SRS-PTN-09 | Wajib | `tests/Modules/Ptn/LaporanLawatanTest.php::test_SRS_PTN_09_rejected_report_returns_the_visit_for_correction` | — |
| SRS-PTN-09 | Wajib | `tests/Modules/Ptn/LaporanLawatanTest.php::test_SRS_PTN_09_missing_flow_does_not_lose_the_submission` | — |
| SRS-PTN-10 | Wajib | `tests/Modules/Ptn/AliranSebenarTest.php::test_SRS_PTN_10_seeded_flow_validates_report_and_officer_cannot_validate_own_visit` | — |
| SRS-PTN-10 | Wajib | `tests/Modules/Ptn/LaporanLawatanTest.php::test_SRS_PTN_10_validated_report_is_locked_everywhere_and_corrected_only_by_linked_addendum` | — |
| SRS-PTN-11 | Wajib | `tests/Modules/Ptn/RisikoTest.php::test_SRS_PTN_11_category_weights_are_configurable_and_history_kept` | — |
| SRS-PTN-12 | Wajib | `tests/Modules/Ptn/RisikoTest.php::test_SRS_PTN_12_six_category_scores_match_the_manual_calculation` | — |
| SRS-PTN-12 | Wajib | `tests/Modules/Ptn/RisikoTest.php::test_SRS_PTN_12_owning_module_can_replace_a_builtin_signal` | — |
| SRS-PTN-13 | Wajib | `tests/Modules/Ptn/PenemuanTest.php::test_SRS_PTN_13_corrective_action_tracked_until_verified_and_issue_closed` | — |
| SRS-PTN-14 | Wajib | `tests/Modules/Ptn/RisikoTest.php::test_SRS_PTN_14_action_and_visit_colours_follow_deadline_thresholds` | — |
| SRS-PTN-15 | Wajib | `tests/Modules/Ptn/RisikoTest.php::test_SRS_PTN_15_threshold_change_shows_on_list_card_map_and_dashboard_without_deploy` | — |
| SRS-PTN-16 | Wajib | `tests/Modules/Ptn/LaporanPrestasiTest.php::test_SRS_PTN_16_lot_card_profile_section_timeline_and_global_search` | — |
| SRS-PTN-16 | Wajib | `tests/Modules/Ptn/RisikoTest.php::test_SRS_PTN_16_colour_codes_always_carry_legend_and_text_labels` | — |
| SRS-PTN-17 | Wajib | `tests/Modules/Ptn/PenemuanTest.php::test_SRS_PTN_17_critical_finding_opens_issue_and_complaint_notifies_and_marks_lot_red` | — |
| SRS-PTN-18 | Perlu | `tests/Modules/Ptn/PenemuanTest.php::test_SRS_PTN_18_issue_escalates_to_complaint_without_reentry_and_once` | — |
| SRS-PTN-19 | Wajib | `tests/Modules/Ptn/PenemuanTest.php::test_SRS_PTN_19_overdue_action_notifies_officer_and_supervisor_and_reminder_before_deadline` | — |
| SRS-PTN-20 | Perlu | `tests/Modules/Ptn/LaporanPrestasiTest.php::test_SRS_PTN_20_current_visit_compared_side_by_side_with_previous_visit` | — |
| SRS-PTN-21 | Perlu | `tests/Modules/Ptn/PenemuanTest.php::test_SRS_PTN_21_maintenance_needs_recorded_and_exposed_to_asset_module` | — |
| SRS-PTN-22 | Wajib | `tests/Modules/Ptn/LaporanPrestasiTest.php::test_SRS_PTN_22_issue_report_by_location_category_status_officer_and_duration` | — |
| SRS-PTN-23 | Perlu | `tests/Modules/Ptn/LaporanPrestasiTest.php::test_SRS_PTN_23_performance_report_on_time_overdue_interval_and_findings` | — |
| SRS-PTN-24 | Wajib | `tests/Modules/Ptn/RisikoTest.php::test_SRS_PTN_24_risk_dashboard_distribution_high_risk_list_open_issues_overdue_and_trend` | — |
| SRS-PWS-01 | Wajib | `tests/Modules/Pws/KandunganStatistikTest.php::test_SRS_PWS_01_official_name_is_used_and_portal_wakaf_never_appears_as_a_display_name` | — |
| SRS-PWS-02 | Wajib | `tests/Modules/Pws/SempadanSistemTest.php::test_SRS_PWS_02_portal_reaches_sisdata_only_through_the_versioned_client_authenticated_api` | — |
| SRS-PWS-03 | Wajib | `tests/Modules/Pws/SempadanSistemTest.php::test_SRS_PWS_03_wordpress_theme_and_plugin_pass_php_lint_and_are_fully_bilingual` | — |
| SRS-PWS-04 | Perlu | `tests/Modules/Pws/KandunganStatistikTest.php::test_SRS_PWS_04_ai_draft_uses_the_local_model_and_is_only_a_draft` | — |
| SRS-PWS-04 | Perlu | `tests/Modules/Pws/KandunganStatistikTest.php::test_SRS_PWS_04_publication_needs_WFE_approval_by_a_reviewer` | — |
| SRS-PWS-04 | Perlu | `tests/Modules/Pws/KandunganStatistikTest.php::test_SRS_PWS_04_rejected_draft_returns_to_the_writer` | — |
| SRS-PWS-04 | Perlu | `tests/Modules/Pws/KandunganStatistikTest.php::test_SRS_PWS_04_publication_flows_are_seeded_in_WFE` | — |
| SRS-PWS-05 | Wajib | — | tiada ujian |
| SRS-PWS-06 | Wajib | `tests/Modules/Pws/MaklumatAwamTest.php::test_SRS_PWS_06_catalogue_shows_only_vacant_portal_premises_with_whitelisted_fields` | — |
| SRS-PWS-06 | Wajib | `tests/Modules/Pws/MaklumatAwamTest.php::test_SRS_PWS_06_faq_uses_the_knowledge_base_as_anonymous_awam_only_and_hides_internal_links` | — |
| SRS-PWS-07 | Wajib | `tests/Modules/Pws/PermohonanPortalTest.php::test_SRS_PWS_07_validation_errors_come_back_per_field_and_nothing_is_created` | — |
| SRS-PWS-08 | Wajib | `tests/Modules/Pws/PermohonanPortalTest.php::test_SRS_PWS_08_reference_is_the_single_key_across_portal_and_sisdata` | — |
| SRS-PWS-09 | Wajib | `tests/Modules/Pws/PermohonanPortalTest.php::test_SRS_PWS_09_status_check_needs_the_matching_email_and_never_reveals_existence` | — |
| SRS-PWS-10 | Wajib | `tests/Modules/Pws/BayaranPortalTest.php::test_SRS_PWS_10_amount_is_generated_by_sisdata_not_entered_by_the_payer` | — |
| SRS-PWS-11 | Wajib | `tests/Modules/Pws/BayaranPortalTest.php::test_SRS_PWS_11_fpx_and_card_channels_are_offered_from_the_gateway_adapter` | — |
| SRS-PWS-12 | Wajib | `tests/Modules/Pws/BayaranPortalTest.php::test_SRS_PWS_12_success_only_after_server_side_confirmation_never_on_redirect` | — |
| SRS-PWS-12 | Wajib | `tests/Modules/Pws/BayaranPortalTest.php::test_SRS_PWS_12_missing_callback_is_resolved_by_the_status_query` | — |
| SRS-PWS-12 | Wajib | `tests/Modules/Pws/BayaranPortalTest.php::test_SRS_PWS_12_test_gateway_simulation_goes_through_the_signed_webhook_and_is_client_bound` | — |
| SRS-PWS-13 | Wajib | — | tiada ujian |
| SRS-PWS-14 | Wajib | — | tiada ujian |
| SRS-PWS-15 | Wajib | `tests/Modules/Pws/BayaranPortalTest.php::test_SRS_PWS_15_idempotent_retry_does_not_create_a_second_transaction` | — |
| SRS-PWS-16 | Wajib | `tests/Modules/Pws/BayaranPortalTest.php::test_SRS_PWS_16_no_card_or_bank_data_is_stored` | — |
| SRS-PWS-17 | Wajib | `tests/Modules/Pws/MaklumatAwamTest.php::test_SRS_PWS_17_benefit_distribution_shows_confirmed_figures_from_sisdata_only` | — |
| SRS-PWS-18 | Wajib | `tests/Modules/Pws/MaklumatAwamTest.php::test_SRS_PWS_18_wakaf_and_purchased_property_are_shown_separately` | — |
| SRS-PWS-19 | Perlu | `tests/Modules/Pws/MaklumatAwamTest.php::test_SRS_PWS_19_update_time_and_disclaimer_in_both_languages` | — |
| SRS-PWS-20 | Wajib | `tests/Modules/Pws/SempadanSistemTest.php::test_SRS_PWS_20_no_master_record_or_personal_data_is_kept_by_the_gateway_after_delivery` | — |
| SRS-PWS-21 | Wajib | `tests/Modules/Pws/PermohonanPortalTest.php::test_SRS_PWS_21_waqf_registration_lands_in_PER_as_a_pending_case_with_the_same_reference` | — |
| SRS-PWS-21 | Wajib | `tests/Modules/Pws/PermohonanPortalTest.php::test_SRS_PWS_21_officer_decision_in_PER_is_shown_to_the_applicant` | — |
| SRS-PWS-21 | Wajib | `tests/Modules/Pws/PermohonanPortalTest.php::test_SRS_PWS_21_rental_application_lands_in_PJK_and_status_follows_the_officers_decision` | — |
| SRS-PWS-22 | Wajib | `tests/Modules/Pws/GatewayKeselamatanTest.php::test_SRS_PWS_22_every_call_is_logged_with_a_masked_payload_summary` | — |
| SRS-PWS-22 | Wajib | `tests/Modules/Pws/PermohonanPortalTest.php::test_SRS_PWS_22_idempotency_key_replays_the_same_result_and_refuses_a_different_body` | — |
| SRS-PWS-22 | Wajib | `tests/Modules/Pws/PermohonanPortalTest.php::test_SRS_PWS_22_failed_delivery_is_kept_and_retried` | — |
| SRS-PWS-22 | Wajib | `tests/Modules/Pws/PermohonanPortalTest.php::test_SRS_PWS_22_admin_monitoring_lists_submissions_without_personal_data_and_needs_permission` | — |
| SRS-PWS-23 | Wajib | `tests/Modules/Pws/GatewayKeselamatanTest.php::test_SRS_PWS_23_client_credentials_give_a_short_lived_bearer_token_and_secrets_are_hashed` | — |
| SRS-PWS-23 | Wajib | `tests/Modules/Pws/GatewayKeselamatanTest.php::test_SRS_PWS_23_http_basic_credentials_are_accepted` | — |
| SRS-PWS-23 | Wajib | `tests/Modules/Pws/GatewayKeselamatanTest.php::test_SRS_PWS_23_scopes_limit_what_a_client_may_call` | — |
| SRS-PWS-23 | Wajib | `tests/Modules/Pws/GatewayKeselamatanTest.php::test_SRS_PWS_23_ip_allowlist_applies_to_token_and_calls` | — |
| SRS-PWS-23 | Wajib | `tests/Modules/Pws/GatewayKeselamatanTest.php::test_SRS_PWS_23_rotation_keeps_the_old_secret_for_a_grace_period_and_revocation_is_immediate` | — |
| SRS-PWS-23 | Wajib | `tests/Modules/Pws/GatewayKeselamatanTest.php::test_SRS_PWS_23_expired_client_cannot_get_tokens` | — |
| SRS-PWS-23 | Wajib | `tests/Modules/Pws/GatewayKeselamatanTest.php::test_SRS_PWS_23_cors_only_for_the_portal_origin_never_with_credentials` | — |
| SRS-PWS-23 | Wajib | `tests/Modules/Pws/GatewayKeselamatanTest.php::test_SRS_PWS_23_admin_client_management_needs_permission_and_mfa` | — |
| SRS-PWS-24 | Wajib | `tests/Modules/Pws/GatewayKeselamatanTest.php::test_SRS_PWS_24_per_client_rate_limit` | — |
| SRS-PWS-24 | Wajib | `tests/Modules/Pws/GatewayKeselamatanTest.php::test_SRS_PWS_24_failed_token_attempts_are_limited` | — |
| SRS-PWS-24 | Wajib | `tests/Modules/Pws/MaklumatAwamTest.php::test_SRS_PWS_24_public_complaint_goes_to_ADU_with_the_end_users_ip_and_captcha` | — |
| SRS-PWS-24 | Wajib | `tests/Modules/Pws/MaklumatAwamTest.php::test_SRS_PWS_24_support_request_opens_a_TKT_ticket_from_the_portal_channel` | — |
| SRS-PWS-24 | Wajib | `tests/Modules/Pws/PermohonanPortalTest.php::test_SRS_PWS_24_submissions_are_limited_per_end_user_ip` | — |
| SRS-PWS-25 | Wajib | — | tiada ujian |
| SRS-PWS-26 | Perlu | — | tiada ujian |
| SRS-PWS-27 | Perlu | `tests/Modules/Pws/KandunganStatistikTest.php::test_SRS_PWS_27_statistics_are_aggregate_suppressed_and_published_only_after_approval` | — |
| SRS-PWS-27 | Perlu | `tests/Modules/Pws/MaklumatAwamTest.php::test_SRS_PWS_27_portal_pushes_daily_page_statistics_idempotently` | — |
| SRS-PWS-28 | Wajib | — | tiada ujian |
| SRS-RBC-01 | Wajib | `tests/Feature/Auth/OidcLoginTest.php::test_SRS_RBC_01_oidc_redirect_sends_user_to_mais_realm_authorize_endpoint` | — |
| SRS-RBC-01 | Wajib | `tests/Feature/Auth/OidcLoginTest.php::test_SRS_RBC_01_oidc_callback_logs_in_existing_user` | — |
| SRS-RBC-01 | Wajib | `tests/Feature/Auth/OidcLoginTest.php::test_SRS_RBC_01_oidc_callback_matches_user_by_sub_before_email` | — |
| SRS-RBC-01 | Wajib | `tests/Feature/Auth/OidcLoginTest.php::test_SRS_RBC_01_oidc_callback_creates_user_on_first_login` | — |
| SRS-RBC-01 | Wajib | `tests/Feature/Auth/OidcLoginTest.php::test_SRS_RBC_01_oidc_callback_rejects_email_linked_to_another_subject` | — |
| SRS-RBC-01 | Wajib | `tests/Feature/Auth/OidcLoginTest.php::test_SRS_RBC_01_oidc_callback_rejects_inactive_user` | — |
| SRS-RBC-01 | Wajib | `tests/Feature/Auth/OidcLoginTest.php::test_SRS_RBC_01_oidc_callback_with_idp_error_redirects_to_login` | — |
| SRS-RBC-01 | Wajib | `tests/Feature/Auth/OidcLoginTest.php::test_SRS_RBC_01_oidc_logout_returns_idp_end_session_url` | — |
| SRS-RBC-01 | Wajib | `tests/Feature/Auth/OidcLoginTest.php::test_SRS_RBC_01_password_user_logout_still_returns_204` | — |
| SRS-RBC-01 | Wajib | `tests/Feature/Auth/OidcLoginTest.php::test_SRS_RBC_01_password_login_disabled_returns_403` | — |
| SRS-RBC-01 | Wajib | `tests/Feature/Auth/OidcLoginTest.php::test_SRS_RBC_01_password_registration_disabled_returns_403` | — |
| SRS-RBC-01 | Wajib | `tests/Feature/Auth/OidcLoginTest.php::test_SRS_RBC_01_settings_expose_password_login_flag` | — |
| SRS-RBC-01 | Wajib | `tests/Modules/Rbc/SsoMappingTest.php::test_SRS_RBC_01_sso_login_is_logged_and_opens_a_server_session` | — |
| SRS-RBC-02 | Wajib | `tests/Modules/Rbc/SsoMappingTest.php::test_SRS_RBC_02_claims_map_to_roles_and_scopes_on_sso_login` | — |
| SRS-RBC-02 | Wajib | `tests/Modules/Rbc/SsoMappingTest.php::test_SRS_RBC_02_sso_roles_are_resynced_each_login_and_manual_roles_kept` | — |
| SRS-RBC-02 | Wajib | `tests/Modules/Rbc/SsoMappingTest.php::test_SRS_RBC_02_admin_manages_mapping_rules_and_previews_them` | — |
| SRS-RBC-02 | Wajib | `tests/Modules/Rbc/SsoMappingTest.php::test_SRS_RBC_02_realm_sends_bahagian_unit_and_jawatan_claims` | — |
| SRS-RBC-03 | Wajib | `tests/Feature/Auth/OidcLoginTest.php::test_SRS_RBC_03_mock_idp_realm_configures_totp_mfa_and_role_claims` | — |
| SRS-RBC-04 | Wajib | `tests/Modules/Rbc/AccessControlTest.php::test_SRS_RBC_04_permission_is_enforced_on_the_api` | — |
| SRS-RBC-04 | Wajib | `tests/Modules/Rbc/AccessControlTest.php::test_SRS_RBC_04_pengguna_paparan_cannot_modify_data_through_any_endpoint` | — |
| SRS-RBC-04 | Wajib | `tests/Modules/Rbc/DirectoryTest.php::test_SRS_RBC_04_rbc_binds_the_directories_other_modules_defined` | — |
| SRS-RBC-04 | Wajib | `tests/Modules/Rbc/DirectoryTest.php::test_SRS_RBC_04_recipients_by_role_permission_scope_and_user` | — |
| SRS-RBC-04 | Wajib | `tests/Modules/Rbc/DirectoryTest.php::test_SRS_RBC_04_inactive_roles_grant_no_membership` | — |
| SRS-RBC-04 | Wajib | `tests/Modules/Rbc/DirectoryTest.php::test_SRS_RBC_04_approvers_by_role_and_jawatan_with_fallback_supervisors` | — |
| SRS-RBC-04 | Wajib | `tests/Modules/Rbc/RoleManagementTest.php::test_SRS_RBC_04_user_roles_and_scopes_are_assigned_and_reported` | — |
| SRS-RBC-04 | Wajib | `tests/Modules/Rbc/RoleManagementTest.php::test_SRS_RBC_04_user_list_filters_by_status_and_role` | — |
| SRS-RBC-05 | Wajib | `tests/Modules/Rbc/AccessControlTest.php::test_SRS_RBC_05_seven_base_roles_exist_as_initial_configuration` | — |
| SRS-RBC-05 | Wajib | `tests/Modules/Rbc/AccessControlTest.php::test_SRS_RBC_05_base_role_scopes_follow_rfp_11_2_3` | — |
| SRS-RBC-05 | Wajib | `tests/Modules/Rbc/AccessControlTest.php::test_SRS_RBC_05_a_newly_registered_module_gets_base_role_defaults_once` | — |
| SRS-RBC-05 | Wajib | `tests/Modules/Rbc/AccessControlTest.php::test_SRS_RBC_05_legacy_admin_account_is_pentadbir_sistem` | — |
| SRS-RBC-06 | Wajib | `tests/Modules/Rbc/AccessControlTest.php::test_SRS_RBC_06_new_scoped_role_takes_effect_immediately` | — |
| SRS-RBC-06 | Wajib | `tests/Modules/Rbc/RoleManagementTest.php::test_SRS_RBC_06_admin_creates_and_edits_a_role_with_permissions_and_scopes` | — |
| SRS-RBC-06 | Wajib | `tests/Modules/Rbc/RoleManagementTest.php::test_SRS_RBC_06_unknown_permissions_and_scope_types_are_rejected` | — |
| SRS-RBC-06 | Wajib | `tests/Modules/Rbc/RoleManagementTest.php::test_SRS_RBC_06_deactivated_role_grants_nothing_until_reactivated` | — |
| SRS-RBC-06 | Wajib | `tests/Modules/Rbc/RoleManagementTest.php::test_SRS_RBC_06_role_is_cloned_with_its_permissions_and_scopes` | — |
| SRS-RBC-06 | Wajib | `tests/Modules/Rbc/RoleManagementTest.php::test_SRS_RBC_06_base_roles_cannot_be_deleted_and_used_roles_are_protected` | — |
| SRS-RBC-06 | Wajib | `tests/Modules/Rbc/RoleManagementTest.php::test_SRS_RBC_06_role_management_requires_permission` | — |
| SRS-RBC-06 | Wajib | `tests/Modules/Rbc/RoleManagementTest.php::test_SRS_RBC_06_admins_cannot_grant_permissions_they_do_not_hold` | — |
| SRS-RBC-07 | Wajib | `tests/Modules/Pro/LotMasterTest.php::test_SRS_RBC_07_out_of_scope_district_lots_are_invisible_and_cannot_be_created` | — |
| SRS-RBC-07 | Wajib | `tests/Modules/Rbc/AccessControlTest.php::test_SRS_RBC_07_officer_of_district_a_cannot_see_district_b` | — |
| SRS-RBC-07 | Wajib | `tests/Modules/Rbc/AccessControlTest.php::test_SRS_RBC_07_scopes_combine_across_dimensions` | — |
| SRS-RBC-07 | Wajib | `tests/Modules/Rbc/AccessControlTest.php::test_SRS_RBC_07_role_scope_and_user_scope_intersect` | — |
| SRS-RBC-07 | Wajib | `tests/Modules/Rbc/AccessControlTest.php::test_SRS_RBC_07_unrestricted_roles_and_console_jobs_see_everything` | — |
| SRS-RBC-08 | Wajib | `tests/Modules/Rbc/AccessControlTest.php::test_SRS_RBC_08_gate_and_can_middleware_delegate_to_access_control` | — |
| SRS-RBC-08 | Wajib | `tests/Modules/Rbc/AccessControlTest.php::test_SRS_RBC_08_denied_requests_dispatch_access_denied` | — |
| SRS-RBC-08 | Wajib | `tests/Modules/Rbc/AccessControlTest.php::test_SRS_RBC_08_admin_alias_middleware_is_permission_based` | — |
| SRS-RBC-09 | Perlu | `tests/Modules/Rbc/AccessControlTest.php::test_SRS_RBC_09_new_role_starts_with_no_permissions` | — |
| SRS-RBC-09 | Perlu | `tests/Modules/Rbc/AccessControlTest.php::test_SRS_RBC_09_user_without_roles_has_no_access` | — |
| SRS-RBC-10 | Wajib | `tests/Modules/Rbc/AccessReportTest.php::test_SRS_RBC_10_matrix_shows_roles_permissions_and_scopes` | — |
| SRS-RBC-10 | Wajib | `tests/Modules/Rbc/AccessReportTest.php::test_SRS_RBC_10_matrix_excel_export_matches_configuration` | — |
| SRS-RBC-10 | Wajib | `tests/Modules/Rbc/AccessReportTest.php::test_SRS_RBC_10_matrix_pdf_export_matches_configuration` | — |
| SRS-RBC-10 | Wajib | `tests/Modules/Rbc/AccessReportTest.php::test_SRS_RBC_10_matrix_export_requires_permission` | — |
| SRS-RBC-11 | Wajib | `tests/Modules/Rbc/PasswordAndLockoutTest.php::test_SRS_RBC_11_sso_accounts_keep_no_password_and_cannot_use_password_login` | — |
| SRS-RBC-11 | Wajib | `tests/Modules/Rbc/PasswordAndLockoutTest.php::test_SRS_RBC_11_admin_creates_sso_account_without_password` | — |
| SRS-RBC-11 | Wajib | `tests/Modules/Rbc/SsoMappingTest.php::test_SRS_RBC_11_sso_provisioned_user_has_no_password` | — |
| SRS-RBC-12 | Wajib | `tests/Modules/Rbc/PasswordAndLockoutTest.php::test_SRS_RBC_12_weak_passwords_are_rejected_on_registration` | — |
| SRS-RBC-12 | Wajib | `tests/Modules/Rbc/PasswordAndLockoutTest.php::test_SRS_RBC_12_policy_accepts_12_chars_with_three_classes` | — |
| SRS-RBC-12 | Wajib | `tests/Modules/Rbc/PasswordAndLockoutTest.php::test_SRS_RBC_12_admin_rejects_weak_password_when_creating_user` | — |
| SRS-RBC-12 | Wajib | `tests/Modules/Rbc/PasswordAndLockoutTest.php::test_SRS_RBC_12_admin_set_password_must_be_changed_at_first_login` | — |
| SRS-RBC-12 | Wajib | `tests/Modules/Rbc/PasswordAndLockoutTest.php::test_SRS_RBC_12_password_expires_after_90_days` | — |
| SRS-RBC-12 | Wajib | `tests/Modules/Rbc/PasswordAndLockoutTest.php::test_SRS_RBC_12_password_not_expired_at_89_days` | — |
| SRS-RBC-12 | Wajib | `tests/Modules/Rbc/PasswordAndLockoutTest.php::test_SRS_RBC_12_last_five_passwords_cannot_be_reused` | — |
| SRS-RBC-12 | Wajib | `tests/Modules/Rbc/PasswordAndLockoutTest.php::test_SRS_RBC_12_change_password_requires_current_password` | — |
| SRS-RBC-13 | Wajib | `tests/Modules/Rbc/PasswordAndLockoutTest.php::test_SRS_RBC_13_account_locks_after_five_consecutive_failures` | — |
| SRS-RBC-13 | Wajib | `tests/Modules/Rbc/PasswordAndLockoutTest.php::test_SRS_RBC_13_failures_outside_the_window_do_not_accumulate` | — |
| SRS-RBC-13 | Wajib | `tests/Modules/Rbc/PasswordAndLockoutTest.php::test_SRS_RBC_13_success_resets_the_consecutive_count` | — |
| SRS-RBC-13 | Wajib | `tests/Modules/Rbc/PasswordAndLockoutTest.php::test_SRS_RBC_13_threshold_and_lock_duration_are_configurable` | — |
| SRS-RBC-13 | Wajib | `tests/Modules/Rbc/PasswordAndLockoutTest.php::test_SRS_RBC_13_admin_can_unlock_an_account` | — |
| SRS-RBC-14 | Wajib | `tests/Modules/Rbc/PasswordAndLockoutTest.php::test_SRS_RBC_14_passwords_are_stored_as_salted_bcrypt_hashes` | — |
| SRS-RBC-15 | Wajib | `tests/Modules/Rbc/AccountStatusTest.php::test_SRS_RBC_15_only_aktif_accounts_can_log_in_with_password` | — |
| SRS-RBC-15 | Wajib | `tests/Modules/Rbc/AccountStatusTest.php::test_SRS_RBC_15_non_aktif_accounts_hold_no_permissions` | — |
| SRS-RBC-15 | Wajib | `tests/Modules/Rbc/AccountStatusTest.php::test_SRS_RBC_15_status_change_is_logged_and_dispatched` | — |
| SRS-RBC-15 | Wajib | `tests/Modules/Rbc/AccountStatusTest.php::test_SRS_RBC_15_status_change_requires_permission_and_a_valid_status` | — |
| SRS-RBC-15 | Wajib | `tests/Modules/Rbc/AccountStatusTest.php::test_SRS_RBC_15_admin_cannot_suspend_themself` | — |
| SRS-RBC-15 | Wajib | `tests/Modules/Rbc/AccountStatusTest.php::test_SRS_RBC_15_legacy_is_active_flag_maps_to_status` | — |
| SRS-RBC-15 | Wajib | `tests/Modules/Rbc/SsoMappingTest.php::test_SRS_RBC_15_suspended_account_is_refused_on_sso_login` | — |
| SRS-RBC-16 | Perlu | `tests/Modules/Rbc/AccountStatusTest.php::test_SRS_RBC_16_account_expires_on_its_access_end_date` | — |
| SRS-RBC-16 | Perlu | `tests/Modules/Rbc/AccountStatusTest.php::test_SRS_RBC_16_account_is_suspended_after_90_days_without_login` | — |
| SRS-RBC-16 | Perlu | `tests/Modules/Rbc/AccountStatusTest.php::test_SRS_RBC_16_inactivity_period_is_configurable` | — |
| SRS-RBC-16 | Perlu | `tests/Modules/Rbc/AccountStatusTest.php::test_SRS_RBC_16_reactivated_account_is_not_suspended_again_immediately` | — |
| SRS-RBC-16 | Perlu | `tests/Modules/Rbc/AccountStatusTest.php::test_SRS_RBC_16_expiry_is_applied_at_login_even_before_the_schedule_runs` | — |
| SRS-RBC-16 | Perlu | `tests/Modules/Rbc/AccountStatusTest.php::test_SRS_RBC_16_command_is_scheduled` | — |
| SRS-RBC-17 | Wajib | `tests/Modules/Rbc/SessionTest.php::test_SRS_RBC_17_suspending_a_user_ends_their_sessions_and_tokens_immediately` | — |
| SRS-RBC-17 | Wajib | `tests/Modules/Rbc/SessionTest.php::test_SRS_RBC_17_status_change_outside_the_service_still_logs_the_session_out` | — |
| SRS-RBC-17 | Wajib | `tests/Modules/Rbc/SessionTest.php::test_SRS_RBC_17_admin_can_end_all_sessions_of_a_user` | — |
| SRS-RBC-17 | Wajib | `tests/Modules/Rbc/SessionTest.php::test_SRS_RBC_17_reactivated_user_can_log_in_again` | — |
| SRS-RBC-18 | Wajib | `tests/Modules/Rbc/SessionTest.php::test_SRS_RBC_18_session_ends_after_default_15_minutes_idle` | — |
| SRS-RBC-18 | Wajib | `tests/Modules/Rbc/SessionTest.php::test_SRS_RBC_18_timeout_is_changed_by_admin_without_a_release` | — |
| SRS-RBC-18 | Wajib | `tests/Modules/Rbc/SessionTest.php::test_SRS_RBC_18_status_poll_reports_remaining_time_without_extending_it` | — |
| SRS-RBC-18 | Wajib | `tests/Modules/Rbc/SessionTest.php::test_SRS_RBC_18_extend_button_resets_the_idle_timer` | — |
| SRS-RBC-18 | Wajib | `tests/Modules/Rbc/SessionTest.php::test_SRS_RBC_18_scheduled_sweep_closes_idle_sessions` | — |
| SRS-RBC-19 | Wajib | `tests/Modules/Rbc/SessionTest.php::test_SRS_RBC_19_concurrent_session_limit_ends_the_oldest_session` | — |
| SRS-RBC-19 | Wajib | `tests/Modules/Rbc/SessionTest.php::test_SRS_RBC_19_admin_lists_and_force_terminates_a_session` | — |
| SRS-RBC-19 | Wajib | `tests/Modules/Rbc/SessionTest.php::test_SRS_RBC_19_logout_ends_the_session_on_the_server` | — |
| SRS-RBC-19 | Wajib | `tests/Modules/Rbc/SessionTest.php::test_SRS_RBC_19_user_without_permission_cannot_see_or_end_sessions` | — |
| SRS-RBC-20 | Wajib | `tests/Modules/Rbc/SessionTest.php::test_SRS_RBC_20_session_id_is_regenerated_on_login` | — |
| SRS-RBC-20 | Wajib | `tests/Modules/Rbc/SessionTest.php::test_SRS_RBC_20_session_cookie_is_httponly_samesite_and_secure_over_https` | — |
| SRS-RBC-20 | Wajib | `tests/Modules/Rbc/SessionTest.php::test_SRS_RBC_20_session_is_bound_to_the_browser_fingerprint` | — |
| SRS-RBC-20 | Wajib | `tests/Modules/Rbc/SessionTest.php::test_SRS_RBC_20_session_is_bound_to_the_ip_range` | — |
| SRS-RBC-20 | Wajib | `tests/Modules/Rbc/SessionTest.php::test_SRS_RBC_20_ip_binding_can_be_made_exact_or_switched_off` | — |
| SRS-RBC-21 | Wajib | `tests/Modules/Rbc/LoginMonitoringTest.php::test_SRS_RBC_21_successful_and_failed_logins_are_recorded_with_context` | — |
| SRS-RBC-21 | Wajib | `tests/Modules/Rbc/LoginMonitoringTest.php::test_SRS_RBC_21_logout_dispatches_event` | — |
| SRS-RBC-21 | Wajib | `tests/Modules/Rbc/LoginMonitoringTest.php::test_SRS_RBC_21_login_log_is_searchable_and_exportable` | — |
| SRS-RBC-22 | Wajib | `tests/Modules/Ntf/EngineTest.php::test_SRS_RBC_22_suspicious_activity_alerts_system_administrators` | — |
| SRS-RBC-22 | Wajib | `tests/Modules/Rbc/DirectoryTest.php::test_SRS_RBC_22_suspicious_login_activity_notifies_system_administrators` | — |
| SRS-RBC-22 | Wajib | `tests/Modules/Rbc/LoginMonitoringTest.php::test_SRS_RBC_22_repeated_failures_raise_an_alert` | — |
| SRS-RBC-22 | Wajib | `tests/Modules/Rbc/LoginMonitoringTest.php::test_SRS_RBC_22_login_from_new_device_or_location_raises_alerts` | — |
| SRS-RBC-22 | Wajib | `tests/Modules/Rbc/LoginMonitoringTest.php::test_SRS_RBC_22_repeated_unauthorised_access_attempts_raise_an_alert` | — |
| SRS-RBC-22 | Wajib | `tests/Modules/Rbc/LoginMonitoringTest.php::test_SRS_RBC_22_admin_reviews_alerts` | — |
| SRS-RBC-23 | Wajib | — | tiada ujian |
| SRS-RBC-24 | Wajib | — | tiada ujian |
| SRS-RBC-25 | Wajib | — | tiada ujian |
| SRS-RBC-26 | Wajib | `tests/Modules/Rbc/AccessReportTest.php::test_SRS_RBC_26_access_report_counts_users_by_status_role_and_division` | — |
| SRS-RBC-26 | Wajib | `tests/Modules/Rbc/AccessReportTest.php::test_SRS_RBC_26_access_report_exports_to_excel_and_pdf` | — |
| SRS-RBC-26 | Wajib | `tests/Modules/Rbc/AccessReportTest.php::test_SRS_RBC_26_policy_settings_are_listed_and_validated` | — |
| SRS-RBC-27 | Perlu | `tests/Modules/Rbc/RecertificationTest.php::test_SRS_RBC_27_cycle_snapshots_the_divisions_role_assignments` | — |
| SRS-RBC-27 | Perlu | `tests/Modules/Rbc/RecertificationTest.php::test_SRS_RBC_27_owner_confirms_or_withdraws_and_withdrawal_takes_effect` | — |
| SRS-RBC-27 | Perlu | `tests/Modules/Rbc/RecertificationTest.php::test_SRS_RBC_27_owner_cannot_review_another_division_or_themself` | — |
| SRS-RBC-27 | Perlu | `tests/Modules/Rbc/RecertificationTest.php::test_SRS_RBC_27_closing_withdraws_unconfirmed_access_per_policy` | — |
| SRS-RBC-27 | Perlu | `tests/Modules/Rbc/RecertificationTest.php::test_SRS_RBC_27_closing_keeps_unconfirmed_access_when_policy_says_so` | — |
| SRS-RBC-27 | Perlu | `tests/Modules/Rbc/RecertificationTest.php::test_SRS_RBC_27_schedule_opens_cycles_per_division_and_closes_overdue_ones` | — |
| SRS-RBC-27 | Perlu | `tests/Modules/Rbc/RecertificationTest.php::test_SRS_RBC_27_recertification_requires_permission` | — |
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
| SRS-TKT-01 | Wajib | `tests/Modules/Tkt/IntakeTest.php::test_SRS_TKT_01_ticket_opens_from_ten_screens_across_modules_with_correct_context` | — |
| SRS-TKT-01 | Wajib | `tests/Modules/Tkt/IntakeTest.php::test_SRS_TKT_01_ticket_intake_contract_is_idempotent_on_source_ref` | — |
| SRS-TKT-01 | Wajib | `tests/Modules/Tkt/IntakeTest.php::test_SRS_TKT_01_user_without_permission_cannot_open_a_ticket` | — |
| SRS-TKT-02 | Wajib | `tests/Modules/Tkt/IntakeTest.php::test_SRS_TKT_02_context_is_captured_and_personal_data_masked` | — |
| SRS-TKT-02 | Wajib | `tests/Modules/Tkt/IntakeTest.php::test_SRS_TKT_02_ticket_links_to_adm_error_log_entries_by_request_id` | — |
| SRS-TKT-03 | Wajib | `tests/Modules/Tkt/IntakeTest.php::test_SRS_TKT_03_screenshot_requires_explicit_consent_and_goes_through_dms` | — |
| SRS-TKT-04 | Wajib | `tests/Modules/Tkt/IntakeTest.php::test_SRS_TKT_04_unique_reference_and_recorded_fields` | — |
| SRS-TKT-04 | Wajib | `tests/Modules/Tkt/IntakeTest.php::test_SRS_TKT_04_severity_matrix_and_security_issue_is_severity_one_and_classified` | — |
| SRS-TKT-04 | Wajib | `tests/Modules/Tkt/IntakeTest.php::test_SRS_TKT_04_duplicate_detection_flags_similar_open_ticket` | — |
| SRS-TKT-05 | Wajib | `tests/Modules/Tkt/KitaranHayatTest.php::test_SRS_TKT_05_lifecycle_records_timestamp_and_officer_for_every_transition` | — |
| SRS-TKT-05 | Wajib | `tests/Modules/Tkt/KitaranHayatTest.php::test_SRS_TKT_05_change_request_needs_wfe_approval_before_action` | — |
| SRS-TKT-05 | Wajib | `tests/Modules/Tkt/KitaranHayatTest.php::test_SRS_TKT_05_seeded_change_request_flow_runs_on_the_real_workflow_engine` | — |
| SRS-TKT-05 | Wajib | `tests/Modules/Tkt/KitaranHayatTest.php::test_SRS_TKT_05_land_complaint_is_routed_to_adu_and_duplicates_are_closed` | — |
| SRS-TKT-06 | Wajib | `tests/Modules/Tkt/CadanganTest.php::test_SRS_TKT_06_kb_suggestions_shown_before_submit_with_at_least_three_articles` | — |
| SRS-TKT-07 | Baik-ada | `tests/Modules/Tkt/CadanganTest.php::test_SRS_TKT_07_deflection_records_continue_or_cancel_and_flags_ineffective_articles` | — |
| SRS-TKT-08 | Wajib | `tests/Modules/Tkt/SlaPenugasanTest.php::test_SRS_TKT_08_targets_follow_severity_service_hours_and_selangor_holidays` | — |
| SRS-TKT-08 | Wajib | `tests/Modules/Tkt/SlaPenugasanTest.php::test_SRS_TKT_08_admin_changes_thresholds_without_code_and_history_is_kept` | — |
| SRS-TKT-09 | Wajib | `tests/Modules/Tkt/SlaPenugasanTest.php::test_SRS_TKT_09_each_severity_warns_then_escalates_along_the_hierarchy` | — |
| SRS-TKT-09 | Wajib | `tests/Modules/Tkt/SlaPenugasanTest.php::test_SRS_TKT_09_paused_ticket_is_not_escalated_and_manual_escalation_is_recorded` | — |
| SRS-TKT-10 | Perlu | `tests/Modules/Tkt/SlaPenugasanTest.php::test_SRS_TKT_10_auto_assignment_by_module_category_severity_and_least_loaded` | — |
| SRS-TKT-10 | Perlu | `tests/Modules/Tkt/SlaPenugasanTest.php::test_SRS_TKT_10_manual_reassignment_needs_reason_and_l2_vendor_handover` | — |
| SRS-TKT-11 | Wajib | `tests/Modules/Tkt/KitaranHayatTest.php::test_SRS_TKT_11_internal_notes_are_hidden_from_the_reporter` | — |
| SRS-TKT-11 | Wajib | `tests/Modules/Tkt/KitaranHayatTest.php::test_SRS_TKT_11_timeline_is_append_only` | — |
| SRS-TKT-12 | Wajib | `tests/Modules/Tkt/KitaranHayatTest.php::test_SRS_TKT_12_waiting_for_reporter_pauses_and_reporter_reply_resumes` | — |
| SRS-TKT-12 | Wajib | `tests/Modules/Tkt/KitaranHayatTest.php::test_SRS_TKT_12_reopen_within_window_and_auto_close_without_feedback` | — |
| SRS-TKT-12 | Wajib | `tests/Modules/Tkt/KitaranHayatTest.php::test_SRS_TKT_12_satisfaction_survey_once_by_reporter` | — |
| SRS-TKT-13 | Wajib | `tests/Modules/Tkt/KitaranHayatTest.php::test_SRS_TKT_13_resolved_ticket_produces_one_kb_draft_problem_cause_solution` | — |
| SRS-TKT-14 | Wajib | `tests/Modules/Tkt/IntegrasiTest.php::test_SRS_TKT_14_central_system_opens_a_ticket_and_status_is_synced_back` | — |
| SRS-TKT-14 | Wajib | `tests/Modules/Tkt/IntegrasiTest.php::test_SRS_TKT_14_api_requires_integration_account_and_respects_ip_allowlist` | — |
| SRS-TKT-15 | Wajib | `tests/Modules/Tkt/IntegrasiTest.php::test_SRS_TKT_15_mapping_is_configurable` | — |
| SRS-TKT-16 | Wajib | `tests/Modules/Tkt/IntegrasiTest.php::test_SRS_TKT_16_repeated_inbound_sync_never_duplicates` | — |
| SRS-TKT-16 | Wajib | `tests/Modules/Tkt/IntegrasiTest.php::test_SRS_TKT_16_failed_push_is_retried_and_listed_for_reconciliation` | — |
| SRS-TKT-17 | Wajib | `tests/Modules/Tkt/LaporanTest.php::test_SRS_TKT_17_dashboard_open_by_severity_and_module_sla_trend_and_drill_down` | — |
| SRS-TKT-17 | Wajib | `tests/Modules/Tkt/LaporanTest.php::test_SRS_TKT_17_kpis_registered_with_the_executive_dashboard` | — |
| SRS-TKT-18 | Wajib | `tests/Modules/Tkt/CapaianTest.php::test_SRS_TKT_18_lifecycle_is_in_the_tamper_evident_audit_trail` | — |
| SRS-TKT-19 | Wajib | `tests/Modules/Tkt/LaporanTest.php::test_SRS_TKT_19_monthly_report_exports_pdf_and_excel_with_matching_sla_figures` | — |
| SRS-TKT-19 | Wajib | `tests/Modules/Tkt/LaporanTest.php::test_SRS_TKT_19_report_is_generated_automatically_and_filed_in_dms` | — |
| SRS-TKT-20 | Wajib | `tests/Modules/Tkt/CapaianTest.php::test_SRS_TKT_20_reporter_sees_only_own_tickets` | — |
| SRS-TKT-20 | Wajib | `tests/Modules/Tkt/CapaianTest.php::test_SRS_TKT_20_support_sees_assigned_and_team_module_tickets_only` | — |
| SRS-TKT-20 | Wajib | `tests/Modules/Tkt/CapaianTest.php::test_SRS_TKT_20_classified_and_security_tickets_need_the_sulit_permission` | — |
| SRS-TKT-21 | Wajib | `tests/Modules/Tkt/CapaianTest.php::test_SRS_TKT_21_handover_moves_open_tickets_without_losing_history` | — |
| SRS-TSU-01 | Wajib | `tests/Modules/Tsu/UrusanTemplatTest.php::test_SRS_TSU_01_seven_urusan_types_each_have_their_own_staged_template` | — |
| SRS-TSU-01 | Wajib | `tests/Modules/Tsu/UrusanTemplatTest.php::test_SRS_TSU_01_register_validation_rules` | — |
| SRS-TSU-02 | Wajib | `tests/Modules/Tsu/UrusanTemplatTest.php::test_SRS_TSU_02_admin_versions_a_template_without_code_and_running_urusan_keep_their_stages` | — |
| SRS-TSU-03 | Wajib | `tests/Modules/Tsu/UrusanTemplatTest.php::test_SRS_TSU_03_registration_generates_a_stage_timeline_with_party_target_and_current_stage` | — |
| SRS-TSU-03 | Wajib | `tests/Modules/Tsu/UrusanTemplatTest.php::test_SRS_TSU_03_stage_cannot_close_until_mandatory_documents_are_attached` | — |
| SRS-TSU-03 | Wajib | `tests/Modules/Tsu/UrusanTemplatTest.php::test_SRS_TSU_03_internal_approval_stage_goes_to_wfe_with_mfa_flow_and_advances_on_approval` | — |
| SRS-TSU-03 | Wajib | `tests/Modules/Tsu/UrusanTemplatTest.php::test_SRS_TSU_03_rejection_stops_the_urusan` | — |
| SRS-TSU-03 | Wajib | `tests/Modules/Tsu/UrusanTemplatTest.php::test_SRS_TSU_03_overdue_stage_escalates_to_officer_then_head_of_division` | — |
| SRS-TSU-04 | Wajib | `tests/Modules/Tsu/PelupusanTest.php::test_SRS_TSU_04_full_disposal_process_from_application_to_archived_lot` | — |
| SRS-TSU-05 | Wajib | `tests/Modules/Tsu/LaporanDanIntegrasiTest.php::test_SRS_TSU_05_seeded_wfe_flow_requires_mfa_at_highest_disposal_stage_and_blocks_self_approval` | — |
| SRS-TSU-05 | Wajib | `tests/Modules/Tsu/PelupusanTest.php::test_SRS_TSU_05_disposal_approval_needs_justification_and_current_valuation_before_it_starts` | — |
| SRS-TSU-05 | Wajib | `tests/Modules/Tsu/PelupusanTest.php::test_SRS_TSU_05_disposal_approval_is_not_recorded_without_mfa_stage_and_written_justification` | — |
| SRS-TSU-06 | Wajib | `tests/Modules/Tsu/PelupusanTest.php::test_SRS_TSU_06_disposed_lot_is_archived_and_cannot_be_deleted` | — |
| SRS-TSU-07 | Wajib | `tests/Modules/Tsu/TukarSyaratKosPtdTest.php::test_SRS_TSU_07_change_of_conditions_end_to_end_updates_lot_and_proposes_new_title` | — |
| SRS-TSU-08 | Wajib | `tests/Modules/Tsu/UkurTest.php::test_SRS_TSU_08_09_10_full_surveyor_procurement_cycle_with_segregation_of_duties` | — |
| SRS-TSU-09 | Wajib | — | tiada ujian |
| SRS-TSU-10 | Wajib | — | tiada ujian |
| SRS-TSU-11 | Wajib | `tests/Modules/Tsu/UkurTest.php::test_SRS_TSU_11_progress_lateness_notifies_and_external_surveyor_only_reports_own_appointment` | — |
| SRS-TSU-11 | Wajib | `tests/Modules/Tsu/UkurTest.php::test_SRS_TSU_11_daily_check_flags_appointment_without_progress` | — |
| SRS-TSU-12 | Wajib | `tests/Modules/Tsu/UkurTest.php::test_SRS_TSU_12_13_survey_status_and_acceptance_records_fee_invoice_for_payment` | — |
| SRS-TSU-13 | Wajib | — | tiada ujian |
| SRS-TSU-14 | Wajib | `tests/Modules/Tsu/TukarSyaratKosPtdTest.php::test_SRS_TSU_14_costs_by_category_with_totals_payment_status_and_receipt_only_by_finance` | — |
| SRS-TSU-15 | Perlu | `tests/Modules/Tsu/TukarSyaratKosPtdTest.php::test_SRS_TSU_15_urusan_costs_feed_the_lots_cumulative_cost_series` | — |
| SRS-TSU-16 | Wajib | `tests/Modules/Tsu/LaporanDanIntegrasiTest.php::test_SRS_TSU_16_accepted_resurvey_is_proposed_to_mod_pro_for_verification_not_written` | — |
| SRS-TSU-16 | Wajib | `tests/Modules/Tsu/UkurTest.php::test_SRS_TSU_16_imports_geojson_dxf_and_shapefile_with_crs_validation` | — |
| SRS-TSU-17 | Wajib | `tests/Modules/Tsu/UkurTest.php::test_SRS_TSU_17_side_by_side_comparison_area_tolerance_and_verification_rules` | — |
| SRS-TSU-18 | Wajib | `tests/Modules/Tsu/UkurTest.php::test_SRS_TSU_18_survey_results_and_active_urusan_appear_on_gis_layers_within_scope` | — |
| SRS-TSU-19 | Wajib | `tests/Modules/Tsu/PecahSempadanTest.php::test_SRS_TSU_19_approved_subdivision_with_verified_survey_creates_new_lots_with_geometry` | — |
| SRS-TSU-19 | Wajib | `tests/Modules/Tsu/PecahSempadanTest.php::test_SRS_TSU_19_amalgamation_of_two_lots_creates_one_new_lot_and_archives_both` | — |
| SRS-TSU-20 | Wajib | `tests/Modules/Tsu/PecahSempadanTest.php::test_SRS_TSU_20_records_move_to_new_lot_only_after_two_level_confirmation_by_two_other_officers` | — |
| SRS-TSU-21 | Wajib | `tests/Modules/Tsu/PecahSempadanTest.php::test_SRS_TSU_21_two_way_lineage_on_both_lots_timeline_and_original_kept_archived` | — |
| SRS-TSU-22 | Wajib | `tests/Modules/Tsu/TukarSyaratKosPtdTest.php::test_SRS_TSU_22_land_office_status_by_central_api_or_structured_manual_update_with_follow_up` | — |
| SRS-TSU-23 | Wajib | `tests/Modules/Tsu/TukarSyaratKosPtdTest.php::test_SRS_TSU_23_follow_up_reminders_for_payment_document_expiry_and_stage_target` | — |
| SRS-TSU-24 | Wajib | `tests/Modules/Tsu/PelupusanTest.php::test_SRS_TSU_24_closed_urusan_and_its_decision_history_are_immutable` | — |
| SRS-TSU-25 | Wajib | `tests/Modules/Tsu/PelupusanTest.php::test_SRS_TSU_25_documents_go_through_dms_and_valuation_report_is_rahsia` | — |
| SRS-TSU-26 | Wajib | `tests/Modules/Tsu/LaporanDanIntegrasiTest.php::test_SRS_TSU_26_works_report_by_status_district_surveyor_cost_and_duration_with_export` | — |
| SRS-TSU-27 | Wajib | `tests/Modules/Tsu/LaporanDanIntegrasiTest.php::test_SRS_TSU_27_management_report_active_by_type_district_stage_average_duration_annual_cost_external_backlog` | — |
| SRS-WFE-01 | Wajib | `tests/Modules/Wfe/DecisionTest.php::test_SRS_WFE_01_engine_contract_status_and_active_instance` | — |
| SRS-WFE-01 | Wajib | `tests/Modules/Wfe/DefinitionTest.php::test_SRS_WFE_01_flow_stages_differ_by_financial_threshold` | — |
| SRS-WFE-02 | Wajib | `tests/Modules/Wfe/DefinitionTest.php::test_SRS_WFE_02_branch_on_district_and_land_category` | — |
| SRS-WFE-02 | Wajib | `tests/Modules/Wfe/DefinitionTest.php::test_SRS_WFE_02_parallel_stage_all_any_and_quorum` | — |
| SRS-WFE-02 | Wajib | `tests/Modules/Wfe/DefinitionTest.php::test_SRS_WFE_02_condition_model_is_data_only_and_safe` | — |
| SRS-WFE-02 | Wajib | `tests/Modules/Wfe/DefinitionTest.php::test_SRS_WFE_02_invalid_graphs_are_rejected` | — |
| SRS-WFE-03 | Wajib | `tests/Modules/Wfe/DefinitionTest.php::test_SRS_WFE_03_admin_adds_threshold_stage_without_code_and_running_cases_keep_old_version` | — |
| SRS-WFE-03 | Wajib | `tests/Modules/Wfe/DefinitionTest.php::test_SRS_WFE_03_activation_needs_a_second_administrator` | — |
| SRS-WFE-03 | Wajib | `tests/Modules/Wfe/DefinitionTest.php::test_SRS_WFE_03_definition_endpoints_require_permission` | — |
| SRS-WFE-03 | Wajib | `tests/Modules/Wfe/DemoFlowTest.php::test_SRS_WFE_03_contracts_bound_and_catalogue_declared` | — |
| SRS-WFE-04 | Wajib | `tests/Modules/Wfe/DefinitionTest.php::test_SRS_WFE_04_template_library_copy_as_new_flow` | — |
| SRS-WFE-05 | Wajib | `tests/Modules/Wfe/DefinitionTest.php::test_SRS_WFE_05_approver_by_role_position_individual_and_group` | — |
| SRS-WFE-06 | Wajib | `tests/Modules/Wfe/SlaTest.php::test_SRS_WFE_06_working_days_skip_selangor_weekend_and_public_holidays` | — |
| SRS-WFE-06 | Wajib | `tests/Modules/Wfe/SlaTest.php::test_SRS_WFE_06_working_hours_sla_counts_office_hours_only` | — |
| SRS-WFE-06 | Wajib | `tests/Modules/Wfe/SlaTest.php::test_SRS_WFE_06_seeded_selangor_calendar_2026_2027_with_source` | — |
| SRS-WFE-06 | Wajib | `tests/Modules/Wfe/SlaTest.php::test_SRS_WFE_06_admin_updates_calendar_and_sla_follows` | — |
| SRS-WFE-07 | Wajib | `tests/Modules/Wfe/SlaTest.php::test_SRS_WFE_07_overdue_task_escalates_level_one_then_level_two` | — |
| SRS-WFE-07 | Wajib | `tests/Modules/Wfe/SlaTest.php::test_SRS_WFE_07_sla_check_is_scheduled` | — |
| SRS-WFE-08 | Wajib | `tests/Modules/Wfe/SlaTest.php::test_SRS_WFE_08_escalation_levels_are_configurable_and_validated` | — |
| SRS-WFE-09 | Wajib | `tests/Modules/Wfe/DefinitionTest.php::test_SRS_WFE_09_visual_editor_and_form_save_the_same_definition` | — |
| SRS-WFE-10 | Perlu | — | tiada ujian |
| SRS-WFE-11 | Perlu | — | tiada ujian |
| SRS-WFE-12 | Wajib | `tests/Modules/Wfe/DefinitionTest.php::test_SRS_WFE_12_versions_are_kept_logged_and_never_deleted` | — |
| SRS-WFE-12 | Wajib | `tests/Modules/Wfe/DefinitionTest.php::test_SRS_WFE_12_moving_a_running_instance_to_a_new_version_needs_admin_approval` | — |
| SRS-WFE-13 | Wajib | `tests/Modules/Wfe/MonitoringTest.php::test_SRS_WFE_13_dashboard_counts_match_test_transactions` | — |
| SRS-WFE-14 | Wajib | `tests/Modules/Wfe/DecisionTest.php::test_SRS_WFE_14_task_board_shows_only_my_tasks_with_sla_remaining` | — |
| SRS-WFE-14 | Wajib | `tests/Modules/Wfe/DecisionTest.php::test_SRS_WFE_14_quick_actions_approve_reject_return_from_the_board` | — |
| SRS-WFE-15 | Wajib | `tests/Modules/Wfe/MonitoringTest.php::test_SRS_WFE_15_drill_down_link_to_source_record` | — |
| SRS-WFE-16 | Wajib | `tests/Modules/Wfe/DecisionTest.php::test_SRS_WFE_16_history_lists_every_stage_actor_decision_justification_and_time` | — |
| SRS-WFE-16 | Wajib | `tests/Modules/Wfe/DecisionTest.php::test_SRS_WFE_16_role_at_time_of_action_is_recorded_not_current_role` | — |
| SRS-WFE-17 | Wajib | `tests/Modules/Wfe/DecisionTest.php::test_SRS_WFE_17_reject_return_defer_need_written_justification` | — |
| SRS-WFE-17 | Wajib | `tests/Modules/Wfe/DecisionTest.php::test_SRS_WFE_17_defer_pauses_sla_and_keeps_task_with_approver` | — |
| SRS-WFE-17 | Wajib | `tests/Modules/Wfe/SlaTest.php::test_SRS_WFE_17_deferred_task_resumes_when_period_ends` | — |
| SRS-WFE-18 | Wajib | `tests/Modules/Wfe/DecisionTest.php::test_SRS_WFE_18_applicant_cannot_approve_own_request` | — |
| SRS-WFE-18 | Wajib | `tests/Modules/Wfe/DecisionTest.php::test_SRS_WFE_18_same_user_cannot_act_on_two_consecutive_stages` | — |
| SRS-WFE-19 | Wajib | `tests/Modules/Wfe/MfaStepUpTest.php::test_SRS_WFE_19_critical_stage_cannot_be_approved_without_mfa` | — |
| SRS-WFE-19 | Wajib | `tests/Modules/Wfe/MfaStepUpTest.php::test_SRS_WFE_19_stale_step_up_is_not_accepted` | — |
| SRS-WFE-19 | Wajib | `tests/Modules/Wfe/MfaStepUpTest.php::test_SRS_WFE_19_fresh_step_up_allows_the_decision_and_is_recorded` | — |
| SRS-WFE-19 | Wajib | `tests/Modules/Wfe/MfaStepUpTest.php::test_SRS_WFE_19_step_up_redirects_to_idp_with_forced_reauthentication` | — |
| SRS-WFE-19 | Wajib | `tests/Modules/Wfe/MfaStepUpTest.php::test_SRS_WFE_19_callback_records_step_up_for_same_subject_with_fresh_auth_time` | — |
| SRS-WFE-19 | Wajib | `tests/Modules/Wfe/MfaStepUpTest.php::test_SRS_WFE_19_callback_rejects_other_subject_or_old_authentication` | — |
| SRS-WFE-19 | Wajib | `tests/Modules/Wfe/MfaStepUpTest.php::test_SRS_WFE_19_otp_evidence_can_be_required` | — |
| SRS-WFE-19 | Wajib | `tests/Modules/Wfe/MfaStepUpTest.php::test_SRS_WFE_19_password_only_accounts_cannot_step_up` | — |
| SRS-WFE-19 | Wajib | `tests/Modules/Wfe/MfaStepUpTest.php::test_SRS_WFE_19_return_path_cannot_be_an_open_redirect` | — |
| SRS-WFE-20 | Wajib | `tests/Modules/Wfe/DecisionTest.php::test_SRS_WFE_20_final_outcome_dispatches_event_notifies_and_creates_follow_up` | — |
| SRS-WFE-20 | Wajib | `tests/Modules/Wfe/DecisionTest.php::test_SRS_WFE_20_new_task_notifies_role_holders` | — |
| SRS-WFE-20 | Wajib | `tests/Modules/Wfe/DemoFlowTest.php::test_SRS_WFE_20_seeded_demo_flow_runs_end_to_end` | — |
| SRS-WFE-21 | Perlu | `tests/Modules/Wfe/DelegationTest.php::test_SRS_WFE_21_new_tasks_route_to_delegate_and_both_identities_are_recorded` | — |
| SRS-WFE-21 | Perlu | `tests/Modules/Wfe/DelegationTest.php::test_SRS_WFE_21_delegate_acts_on_pool_tasks_of_the_absent_approver` | — |
| SRS-WFE-21 | Perlu | `tests/Modules/Wfe/DelegationTest.php::test_SRS_WFE_21_delegation_only_inside_its_period_and_respects_segregation` | — |
| SRS-WFE-21 | Perlu | `tests/Modules/Wfe/DelegationTest.php::test_SRS_WFE_21_only_admins_set_a_delegate_for_someone_else` | — |
| SRS-WFE-22 | Baik-ada | `tests/Modules/Wfe/DecisionTest.php::test_SRS_WFE_22_applicant_withdraws_pending_request` | — |
| SRS-WFE-22 | Baik-ada | `tests/Modules/Wfe/DecisionTest.php::test_SRS_WFE_22_return_from_first_stage_goes_back_to_applicant_who_resubmits` | — |
| SRS-WFE-23 | Wajib | `tests/Modules/Wfe/MonitoringTest.php::test_SRS_WFE_23_performance_report_per_stage_bottleneck_escalation_backlog` | — |
| SRS-WFE-24 | Perlu | — | tiada ujian |

**558 daripada 699 keperluan** mempunyai sekurang-kurangnya satu ujian.
