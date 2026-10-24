import { getServerSettings } from '@/lib/settings-server';
import { LoginForm } from './LoginForm';

// SRS-RBC-01: reasons OidcController::failed() appends as ?sso_error=…
const SSO_ERRORS: Record<string, string> = {
    ditolak: 'Log masuk SSO MAIS dibatalkan atau ditolak.',
    gagal: 'Log masuk SSO MAIS gagal. Sila cuba lagi.',
    konflik: 'Emel ini telah dipautkan kepada akaun SSO MAIS yang lain. Sila hubungi pentadbir sistem.',
    tiada_emel: 'Akaun SSO MAIS anda tidak mempunyai alamat emel. Sila hubungi pentadbir sistem.',
    tidak_aktif: 'Akaun anda tidak aktif. Sila hubungi pentadbir sistem.',
};

export default async function LoginPage({
    searchParams,
}: {
    searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
    const [settings, params] = await Promise.all([getServerSettings(), searchParams]);
    const errorKey = typeof params.sso_error === 'string' ? params.sso_error : null;
    const ssoError = errorKey ? (SSO_ERRORS[errorKey] ?? SSO_ERRORS.gagal) : null;

    return <LoginForm passwordLoginEnabled={settings.password_login_enabled !== false} ssoError={ssoError} />;
}
