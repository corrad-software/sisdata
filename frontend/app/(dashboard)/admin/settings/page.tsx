'use client';

import { useState } from 'react';
import type { ChangeEvent } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { PageHeader } from '@/components/PageHeader';
import { ApplicationLogo } from '@/components/ApplicationLogo';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { FieldInput } from '@/components/ui/FieldInput';
import { ColorPicker } from '@/components/ui/ColorPicker';
import { Spinner } from '@/components/ui/Spinner';
import { ApiError } from '@/lib/api';
import {
    deleteFavicon,
    deleteLogo,
    fetchSettings,
    updateSettings,
    uploadFavicon,
    uploadLogo,
    type UpdateSettingsPayload,
} from '@/lib/settings';
import type { Settings } from '@/lib/types';

const DEFAULT_PRIMARY_COLOR = '#2563EB';

export default function SettingsPage() {
    const { data, isLoading } = useQuery({
        queryKey: ['settings'],
        queryFn: fetchSettings,
    });

    const settings = data?.data;

    if (isLoading || !settings) {
        return (
            <>
                <PageHeader title="Tetapan Laman" breadcrumbs={[{ label: 'Tetapan Laman' }]} />
                <PageContainer className="text-body-sm text-txt-black-500">Memuatkan…</PageContainer>
            </>
        );
    }

    // Keying by whether settings have loaded (via the early return above) means
    // this only ever mounts once real data is available, so local form state
    // can be seeded directly from props instead of syncing in a useEffect.
    return <SettingsForm settings={settings} />;
}

function SettingsForm({ settings }: { settings: Settings }) {
    const queryClient = useQueryClient();
    const router = useRouter();

    const [siteTitle, setSiteTitle] = useState(settings.site_title);
    const [footerText, setFooterText] = useState(settings.footer_text ?? '');
    const [primaryColor, setPrimaryColor] = useState(settings.primary_color);
    const [errors, setErrors] = useState<Record<string, string[]>>({});

    function afterMutate() {
        queryClient.invalidateQueries({ queryKey: ['settings'] });
        router.refresh();
    }

    const saveMutation = useMutation({
        mutationFn: (payload: UpdateSettingsPayload) => updateSettings(payload),
        onSuccess: () => {
            afterMutate();
            setErrors({});
            toast.success('Tetapan berjaya disimpan.');
        },
        onError: (error) => {
            if (error instanceof ApiError && error.errors) {
                setErrors(error.errors);
            } else {
                toast.error('Tetapan tidak dapat disimpan.');
            }
        },
    });

    const logoUploadMutation = useMutation({
        mutationFn: uploadLogo,
        onSuccess: () => {
            afterMutate();
            toast.success('Logo dikemas kini.');
        },
        onError: () => toast.error('Logo tidak dapat dimuat naik.'),
    });

    const logoDeleteMutation = useMutation({
        mutationFn: deleteLogo,
        onSuccess: () => {
            afterMutate();
            toast.success('Logo dibuang.');
        },
        onError: () => toast.error('Logo tidak dapat dibuang.'),
    });

    const faviconUploadMutation = useMutation({
        mutationFn: uploadFavicon,
        onSuccess: () => {
            afterMutate();
            toast.success('Ikon laman dikemas kini.');
        },
        onError: () => toast.error('Ikon laman tidak dapat dimuat naik.'),
    });

    const faviconDeleteMutation = useMutation({
        mutationFn: deleteFavicon,
        onSuccess: () => {
            afterMutate();
            toast.success('Ikon laman dibuang.');
        },
        onError: () => toast.error('Ikon laman tidak dapat dibuang.'),
    });

    function handleSave() {
        saveMutation.mutate({
            site_title: siteTitle,
            footer_text: footerText || null,
            primary_color: primaryColor,
        });
    }

    function handleResetColor() {
        setPrimaryColor(DEFAULT_PRIMARY_COLOR);
        saveMutation.mutate({ site_title: siteTitle, footer_text: footerText || null, primary_color: DEFAULT_PRIMARY_COLOR });
    }

    function handleResetFooterText() {
        setFooterText('');
        saveMutation.mutate({ site_title: siteTitle, footer_text: null, primary_color: primaryColor });
    }

    function handleLogoChange(e: ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0];
        if (file) logoUploadMutation.mutate(file);
        e.target.value = '';
    }

    function handleFaviconChange(e: ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0];
        if (file) faviconUploadMutation.mutate(file);
        e.target.value = '';
    }

    function handleRemoveLogo() {
        if (window.confirm('Buang logo semasa?')) logoDeleteMutation.mutate();
    }

    function handleRemoveFavicon() {
        if (window.confirm('Buang ikon laman semasa?')) faviconDeleteMutation.mutate();
    }

    return (
        <>
            <PageHeader title="Tetapan Laman" breadcrumbs={[{ label: 'Tetapan Laman' }]} />

            <PageContainer className="space-y-6">
                    <div className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                        <div>
                            <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Identiti Laman</h3>
                            <p className="text-body-sm text-txt-black-500">
                                Tajuk laman dipaparkan pada tab pelayar dan pengepala halaman.
                            </p>
                        </div>

                        <div className="max-w-sm">
                            <FieldInput
                                label="Tajuk Laman"
                                name="siteTitle"
                                value={siteTitle}
                                onChange={setSiteTitle}
                                required
                                error={errors.site_title?.[0]}
                            />
                        </div>
                    </div>

                    <div className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                        <div>
                            <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Pengaki</h3>
                            <p className="text-body-sm text-txt-black-500">
                                Dipaparkan di bahagian bawah setiap halaman. Biarkan kosong untuk menggunakan baris hak cipta lalai.
                            </p>
                        </div>

                        <div className="flex flex-col sm:flex-row sm:items-end gap-4">
                            <div className="max-w-md flex-1">
                                <FieldInput
                                    label="Teks Pengaki"
                                    name="footerText"
                                    value={footerText}
                                    onChange={setFooterText}
                                    error={errors.footer_text?.[0]}
                                />
                            </div>

                            <Button variant="secondary" type="button" onClick={handleResetFooterText}>
                                Tetap Semula ke Lalai
                            </Button>
                        </div>

                        {!errors.footer_text?.[0] && (
                            <p className="max-w-md text-body-xs text-txt-black-500">
                                {`© ${new Date().getFullYear()} ${siteTitle}. Hak cipta terpelihara.`}
                            </p>
                        )}
                    </div>

                    <div className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                        <div>
                            <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Warna Tema</h3>
                            <p className="text-body-sm text-txt-black-500">
                                Pilih warna utama jenama — skala warna penuh (butang, pautan, gelang fokus) dijana
                                semula daripadanya secara automatik.
                            </p>
                        </div>

                        <div className="flex flex-col sm:flex-row sm:items-end gap-4">
                            <div className="max-w-xs flex-1">
                                <ColorPicker
                                    id="primaryColor"
                                    label="Warna Utama"
                                    value={primaryColor}
                                    onChange={setPrimaryColor}
                                />
                            </div>

                            <Button variant="secondary" type="button" onClick={handleResetColor}>
                                Tetap Semula ke Lalai
                            </Button>
                        </div>

                        {errors.primary_color?.[0] ? (
                            <p className="max-w-xs text-body-xs text-danger-600">{errors.primary_color[0]}</p>
                        ) : (
                            <p className="max-w-xs text-body-xs text-txt-black-500">
                                Digunakan untuk butang, pautan dan gelang fokus di seluruh aplikasi.
                            </p>
                        )}

                        <div className="flex flex-wrap items-center gap-3 rounded-md border border-otl-gray-200 bg-bg-washed p-4">
                            <span className="text-body-xs font-medium text-txt-black-500">Pratonton:</span>
                            <Button variant="primary" type="button">
                                Butang Utama
                            </Button>
                            <Badge variant="info">Lencana</Badge>
                            <a href="#" className="text-body-sm font-medium text-primary-600 hover:text-primary-700">
                                Contoh pautan
                            </a>
                        </div>
                    </div>

                    <BrandingUpload
                        title="Logo Laman"
                        description="Dipaparkan pada bar sisi dan halaman log masuk. Jika tidak ditetapkan, lambang MAIS digunakan."
                        currentUrl={settings.site_logo_url}
                        previewClassName="h-8 w-auto"
                        helpText="Disyorkan PNG atau SVG, maksimum 1MB."
                        uploading={logoUploadMutation.isPending}
                        onChange={handleLogoChange}
                        onRemove={settings.site_logo_url ? handleRemoveLogo : undefined}
                        inputId="logo-upload"
                    />

                    <BrandingUpload
                        title="Ikon Laman (Favicon)"
                        description="Dipaparkan pada tab pelayar. Disyorkan imej segi empat sama (cth. 32×32 atau 64×64)."
                        currentUrl={settings.site_favicon_url}
                        previewClassName="h-6 w-auto"
                        helpText="Disyorkan PNG atau ICO, maksimum 512KB."
                        uploading={faviconUploadMutation.isPending}
                        onChange={handleFaviconChange}
                        onRemove={settings.site_favicon_url ? handleRemoveFavicon : undefined}
                        inputId="favicon-upload"
                    />

                    <div className="flex justify-end">
                        <Button variant="primary" type="button" onClick={handleSave} disabled={saveMutation.isPending}>
                            Simpan Tetapan
                        </Button>
                    </div>
            </PageContainer>
        </>
    );
}

function BrandingUpload({
    title,
    description,
    currentUrl,
    previewClassName,
    helpText,
    uploading,
    onChange,
    onRemove,
    inputId,
}: {
    title: string;
    description: string;
    currentUrl: string | null;
    previewClassName: string;
    helpText: string;
    uploading: boolean;
    onChange: (e: ChangeEvent<HTMLInputElement>) => void;
    onRemove?: () => void;
    inputId: string;
}) {
    return (
        <div className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <div>
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">{title}</h3>
                <p className="text-body-sm text-txt-black-500">{description}</p>
            </div>

            <div className="flex items-center gap-4">
                <div className="flex h-16 w-16 flex-shrink-0 items-center justify-center rounded-lg border border-otl-gray-200 bg-bg-washed p-2">
                    {currentUrl ? (
                        // eslint-disable-next-line @next/next/no-img-element
                        <img src={currentUrl} alt={title} className="max-h-full max-w-full object-contain" />
                    ) : (
                        <ApplicationLogo className={`${previewClassName} fill-current text-primary-600`} />
                    )}
                </div>

                <div className="space-y-2">
                    <div className="flex flex-wrap items-center gap-3">
                        <label
                            htmlFor={inputId}
                            className="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-otl-gray-300 px-3 py-2 text-body-sm font-medium text-txt-black-900 hover:bg-bg-washed"
                        >
                            Pilih Fail
                            <input
                                id={inputId}
                                type="file"
                                accept="image/*"
                                className="hidden"
                                onChange={onChange}
                            />
                        </label>

                        {onRemove && (
                            <button
                                type="button"
                                onClick={onRemove}
                                className="text-body-sm font-medium text-danger-600 hover:text-danger-700"
                            >
                                Buang
                            </button>
                        )}
                    </div>
                    <p className="text-body-xs text-txt-black-500">{helpText}</p>
                    {uploading && (
                        <div className="flex items-center gap-2">
                            <Spinner size="sm" className="text-txt-black-500" />
                            <span className="text-body-xs text-txt-black-500">Sedang memuat naik…</span>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
