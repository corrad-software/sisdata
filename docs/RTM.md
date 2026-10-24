# RTM — SISDATA

_Dijana oleh `php artisan srs:rtm` pada 2026-10-24 11:37:48. Jangan sunting dengan tangan._

| Keperluan | Ujian | Status |
| --- | --- | --- |
| SRS-ADM-12 | `tests/Feature/Api/ErrorFormatTest.php::test_SRS_ADM_12_every_response_carries_a_request_id` | LULUS |
| SRS-ADM-12 | `tests/Feature/Api/ErrorFormatTest.php::test_SRS_ADM_12_a_sane_incoming_request_id_is_kept` | LULUS |
| SRS-ADM-12 | `tests/Feature/Api/ErrorFormatTest.php::test_SRS_ADM_12_a_malformed_incoming_request_id_is_replaced` | LULUS |
| SRS-ADM-13 | `tests/Feature/Api/ErrorFormatTest.php::test_SRS_ADM_13_server_errors_hide_internals_and_return_request_id` | LULUS |
| SRS-ADM-13 | `tests/Feature/Api/ErrorFormatTest.php::test_SRS_ADM_13_unauthenticated_and_not_found_use_the_same_shape` | LULUS |
| SRS-ADM-13 | `tests/Feature/Api/ErrorFormatTest.php::test_SRS_ADM_13_validation_errors_keep_field_errors` | LULUS |
| SRS-RBC-01 | `tests/Feature/Auth/OidcLoginTest.php::test_SRS_RBC_01_oidc_redirect_sends_user_to_mais_realm_authorize_endpoint` | LULUS |
| SRS-RBC-01 | `tests/Feature/Auth/OidcLoginTest.php::test_SRS_RBC_01_oidc_callback_logs_in_existing_user` | LULUS |
| SRS-RBC-01 | `tests/Feature/Auth/OidcLoginTest.php::test_SRS_RBC_01_oidc_callback_matches_user_by_sub_before_email` | LULUS |
| SRS-RBC-01 | `tests/Feature/Auth/OidcLoginTest.php::test_SRS_RBC_01_oidc_callback_creates_user_on_first_login` | LULUS |
| SRS-RBC-01 | `tests/Feature/Auth/OidcLoginTest.php::test_SRS_RBC_01_oidc_callback_rejects_email_linked_to_another_subject` | LULUS |
| SRS-RBC-01 | `tests/Feature/Auth/OidcLoginTest.php::test_SRS_RBC_01_oidc_callback_rejects_inactive_user` | LULUS |
| SRS-RBC-01 | `tests/Feature/Auth/OidcLoginTest.php::test_SRS_RBC_01_oidc_callback_with_idp_error_redirects_to_login` | LULUS |
| SRS-RBC-01 | `tests/Feature/Auth/OidcLoginTest.php::test_SRS_RBC_01_oidc_logout_returns_idp_end_session_url` | GAGAL |
| SRS-RBC-01 | `tests/Feature/Auth/OidcLoginTest.php::test_SRS_RBC_01_password_user_logout_still_returns_204` | GAGAL |
| SRS-RBC-01 | `tests/Feature/Auth/OidcLoginTest.php::test_SRS_RBC_01_password_login_disabled_returns_403` | GAGAL |
| SRS-RBC-01 | `tests/Feature/Auth/OidcLoginTest.php::test_SRS_RBC_01_password_registration_disabled_returns_403` | GAGAL |
| SRS-RBC-01 | `tests/Feature/Auth/OidcLoginTest.php::test_SRS_RBC_01_settings_expose_password_login_flag` | LULUS |
| SRS-RBC-03 | `tests/Feature/Auth/OidcLoginTest.php::test_SRS_RBC_03_mock_idp_realm_configures_totp_mfa_and_role_claims` | LULUS |

**4 keperluan** mempunyai sekurang-kurangnya satu ujian.
