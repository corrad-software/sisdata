'use client';

import { useState } from 'react';
import { PageContainer } from '@/components/PageContainer';
import { PageHeader } from '@/components/PageHeader';
import { Badge } from '@/components/ui/Badge';
import { Timeline } from '@/components/ui/Timeline';

const TABS: Record<string, string> = { perihal: 'Perihal Sistem', modul: 'Modul', teknologi: 'Teknologi', versi: 'Sejarah Versi' };

interface TechItem {
    name: string;
    version: string;
    description: string;
}

const MODULES: Array<{ kod: string; nama: string; keterangan: string }> = [
    { kod: 'PRO', nama: 'Profil Hartanah', keterangan: 'Rekod induk setiap lot tanah MAIS dan paparan profil 360°.' },
    { kod: 'GIS', nama: 'Peta GIS', keterangan: 'Peta interaktif lot, lapisan tematik, pengenalan dan pengukuran.' },
    { kod: 'HKM', nama: 'Hak Milik Tanah', keterangan: 'Daftar hak milik, perubahan hak milik dan peti besi dokumen.' },
    { kod: 'PER', nama: 'Rekod Pemerolehan', keterangan: 'Kes pemerolehan tanah daripada permohonan hingga pendaftaran.' },
    { kod: 'PJK', nama: 'Pajakan & Kontrak', keterangan: 'Pajakan, sewaan, kontrak, bayaran dan pembaharuan.' },
    { kod: 'CKI', nama: 'Cukai Tanah', keterangan: 'Bil cukai, bayaran, tunggakan dan rekonsiliasi dengan PTG.' },
    { kod: 'PTN', nama: 'Pemantauan Tanah', keterangan: 'Jadual lawatan tapak, isu tapak dan skor risiko lot.' },
    { kod: 'AST', nama: 'Aset Fizikal', keterangan: 'Daftar aset di atas tanah, penyelenggaraan dan kerja.' },
    { kod: 'TSU', nama: 'Tukar Syarat & Ukur', keterangan: 'Urusan tukar syarat, pecah sempadan dan ukur.' },
    { kod: 'KES', nama: 'Kes Mahkamah', keterangan: 'Kes perundangan berkaitan tanah dan kalendar perbicaraan.' },
    { kod: 'ADU', nama: 'Aduan & Isu Tanah', keterangan: 'Penerimaan, siasatan dan penyelesaian aduan.' },
    { kod: 'PWS', nama: 'Portal Wakaf', keterangan: 'Pemantauan tanah wakaf dan integrasi Portal Wakaf.' },
    { kod: 'WFE', nama: 'Aliran Kerja', keterangan: 'Tugasan kelulusan berperingkat dengan justifikasi.' },
    { kod: 'DSH', nama: 'Dashboard Eksekutif', keterangan: 'Petunjuk prestasi utama mengikut aras pengurusan.' },
    { kod: 'LAP', nama: 'Laporan & Analitik', keterangan: 'Laporan standard, pembina laporan dan analitik.' },
    { kod: 'DMS', nama: 'Dokumen Digital', keterangan: 'Simpanan dokumen berversi dengan imbasan virus.' },
    { kod: 'KBA', nama: 'Pangkalan Pengetahuan', keterangan: 'Artikel rujukan dan pembantu carian berasaskan AI.' },
    { kod: 'TKT', nama: 'Tiket Sokongan', keterangan: 'Permohonan bantuan dan sokongan teknikal.' },
    { kod: 'NTF', nama: 'Notifikasi', keterangan: 'Pemberitahuan dalam sistem dan melalui e-mel.' },
    { kod: 'AUD', nama: 'Audit Trail', keterangan: 'Jejak audit berantai yang tidak boleh diubah.' },
    { kod: 'RBC', nama: 'Pengguna & Capaian', keterangan: 'SSO MAIS, peranan, skop daerah dan kebenaran.' },
    { kod: 'ADM', nama: 'Pentadbiran Sistem', keterangan: 'Parameter, kod rujukan, templat dan kesihatan sistem.' },
];

const BACKEND_STACK: TechItem[] = [
    { name: 'PHP', version: '^8.3', description: 'Bahasa pengaturcaraan pelayan' },
    { name: 'Laravel Framework', version: '^13.0', description: 'Rangka kerja API (JSON)' },
    { name: 'Laravel Sanctum', version: '^4.3', description: 'Pengesahan SPA berasaskan kuki' },
    { name: 'Laravel Horizon', version: '^5.50', description: 'Pemantauan baris gilir kerja' },
    { name: 'PHPUnit', version: '^12.0', description: 'Ujian automatik backend' },
    { name: 'Larastan / Pint', version: '^3.13 / ^1.24', description: 'Analisis statik dan gaya kod' },
];

const DATA_STACK: TechItem[] = [
    { name: 'PostgreSQL', version: '17', description: 'Pangkalan data utama' },
    { name: 'PostGIS', version: '3.6', description: 'Data ruang (spatial) lot tanah' },
    { name: 'pgvector', version: '0.8', description: 'Carian semantik Pangkalan Pengetahuan' },
    { name: 'Redis', version: '7', description: 'Cache dan baris gilir' },
    { name: 'MinIO', version: 'S3', description: 'Storan objek dokumen digital' },
    { name: 'Keycloak', version: '26', description: 'SSO MAIS (OIDC) dan MFA' },
];

const FRONTEND_STACK: TechItem[] = [
    { name: 'Next.js', version: '16.4.3', description: 'Rangka kerja React (App Router)' },
    { name: 'React', version: '19.2.8', description: 'Pustaka antara muka pengguna' },
    { name: 'TypeScript', version: '^5.9.3', description: 'Penjenisan statik' },
    { name: 'Tailwind CSS', version: '^3.4.19', description: 'Penggayaan berasaskan utiliti' },
    { name: '@govtechmy/myds-style', version: '^1.0.0', description: 'Sistem Reka Bentuk Kerajaan Malaysia (MYDS)' },
    { name: 'MapLibre GL', version: '6.15.0', description: 'Paparan peta GIS' },
    { name: 'TanStack React Query', version: '^5.101.4', description: 'Pengurusan data daripada pelayan' },
    { name: 'driver.js', version: '1.10.2', description: 'Panduan interaktif (gelembung langkah)' },
];

const TOOLING_STACK: TechItem[] = [
    { name: 'Playwright', version: '^1.62.1', description: 'Ujian hujung ke hujung dan senario UAT' },
    { name: 'ESLint', version: '^9.39.5', description: 'Semakan kualiti kod frontend' },
];

const CHANGELOG = [
    {
        title: 'Bahasa Melayu sepenuhnya & panduan interaktif',
        time: '2026-10-26',
        description:
            'Semua teks antara muka kini dalam Bahasa Melayu (SRS-GEN-09). Panduan interaktif mengikut peranan ditambah untuk pengguna kali pertama dan boleh dihidupkan atau dimatikan daripada menu pengguna (SRS-GEN-08).',
    },
    {
        title: 'Modul perniagaan SISDATA',
        time: '2026-10',
        description:
            'Modul Profil Hartanah, Peta GIS, Hak Milik, Pemerolehan, Pajakan, Cukai, Pemantauan, Aset, Tukar Syarat, Kes Mahkamah, Aduan, Portal Wakaf, Laporan, Dashboard, Pangkalan Pengetahuan dan Tiket Sokongan dibina di atas modul platform.',
        color: 'primary' as const,
    },
    {
        title: 'Modul platform (G0)',
        time: '2026-10',
        description:
            'Pengguna & Capaian (SSO MAIS, RBAC), Audit Trail, Pentadbiran Sistem, Notifikasi, Dokumen Digital dan Aliran Kerja disediakan sebagai asas semua modul.',
        color: 'primary' as const,
    },
    {
        title: 'Asas aplikasi',
        time: '2026-08',
        description: 'API Laravel dan antara muka Next.js berasaskan MYDS disediakan sebagai rangka SISDATA.',
        color: 'primary' as const,
    },
];

function StackSection({ title, description, items }: { title: string; description: string; items: TechItem[] }) {
    return (
        <div className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <div>
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">{title}</h3>
                <p className="text-body-sm text-txt-black-500">{description}</p>
            </div>

            <div className="divide-y divide-otl-divider">
                {items.map((item) => (
                    <div key={item.name} className="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0">
                        <div className="min-w-0">
                            <p className="text-body-sm font-medium text-txt-black-900">{item.name}</p>
                            <p className="text-body-xs text-txt-black-500">{item.description}</p>
                        </div>
                        <Badge variant="info" shape="tag">
                            {item.version}
                        </Badge>
                    </div>
                ))}
            </div>
        </div>
    );
}

export default function AboutPage() {
    const [tab, setTab] = useState<keyof typeof TABS>('perihal');

    return (
        <>
            <PageHeader title="Perihal SISDATA" breadcrumbs={[{ label: 'Perihal SISDATA' }]} />

            <div className="bg-bg-white border-b border-otl-divider">
                <nav className="flex flex-nowrap gap-6 px-4 sm:px-6 lg:px-8 -mb-px overflow-x-auto" aria-label="Bahagian halaman">
                    {Object.entries(TABS).map(([key, label]) => {
                        const active = key === tab;

                        return (
                            <button
                                key={key}
                                type="button"
                                onClick={() => setTab(key)}
                                className={`whitespace-nowrap border-b-2 py-3 text-body-sm font-medium ${
                                    active
                                        ? 'border-primary-600 text-primary-600'
                                        : 'border-transparent text-txt-black-500 hover:text-txt-black-900 hover:border-otl-gray-300'
                                }`}
                            >
                                {label}
                            </button>
                        );
                    })}
                </nav>
            </div>

            <PageContainer>
                {tab === 'perihal' && (
                    <>
                        <div className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-3">
                            <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">
                                SISDATA — Sistem Pengurusan Data Hartanah MAIS
                            </h3>
                            <p className="text-body-sm text-txt-black-700">
                                SISDATA ialah sistem dalaman Majlis Agama Islam Selangor (MAIS) untuk mengurus seluruh
                                kitaran hayat hartanah MAIS — tanah wakaf, baitulmal dan tanah milik MAIS — dalam satu
                                rekod induk. Setiap lot tanah mempunyai satu profil yang menghubungkan hak milik,
                                pemerolehan, pajakan, cukai, pemantauan tapak, aset fizikal, kes mahkamah, aduan dan
                                dokumen berkaitan.
                            </p>
                            <p className="text-body-sm text-txt-black-700">
                                Capaian ditentukan mengikut peranan, daerah dan kategori data pengguna. Setiap tindakan
                                direkodkan dalam jejak audit, dan keputusan penting melalui aliran kerja kelulusan
                                berperingkat.
                            </p>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6">
                                <h4 className="text-body-sm font-semibold text-txt-black-900">Pemilik sistem</h4>
                                <p className="mt-1 text-body-sm text-txt-black-500">Majlis Agama Islam Selangor (MAIS)</p>
                            </div>
                            <div className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6">
                                <h4 className="text-body-sm font-semibold text-txt-black-900">Log masuk</h4>
                                <p className="mt-1 text-body-sm text-txt-black-500">
                                    SSO MAIS dengan pengesahan pelbagai faktor (MFA). Akaun diwujudkan oleh pentadbir sistem.
                                </p>
                            </div>
                            <div className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6">
                                <h4 className="text-body-sm font-semibold text-txt-black-900">Bantuan</h4>
                                <p className="mt-1 text-body-sm text-txt-black-500">
                                    Gunakan butang <span className="font-medium">Bantuan</span> di bahagian atas untuk membuka
                                    tiket sokongan, atau ulang panduan interaktif daripada menu pengguna.
                                </p>
                            </div>
                        </div>
                    </>
                )}

                {tab === 'modul' && (
                    <div className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6">
                        <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Modul SISDATA</h3>
                        <p className="text-body-sm text-txt-black-500">Modul yang anda boleh buka bergantung pada peranan anda.</p>
                        <dl className="mt-4 grid grid-cols-1 gap-x-6 gap-y-3 md:grid-cols-2">
                            {MODULES.map((m) => (
                                <div key={m.kod} className="flex gap-3">
                                    <dt className="w-12 flex-shrink-0">
                                        <Badge variant="info" shape="tag">
                                            {m.kod}
                                        </Badge>
                                    </dt>
                                    <dd className="min-w-0">
                                        <p className="text-body-sm font-medium text-txt-black-900">{m.nama}</p>
                                        <p className="text-body-xs text-txt-black-500">{m.keterangan}</p>
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </div>
                )}

                {tab === 'teknologi' && (
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <StackSection title="Backend" description="Pelayan API — pengesahan, data dan logik perniagaan." items={BACKEND_STACK} />
                        <StackSection title="Frontend" description="Antara muka Next.js — satu-satunya antara muka kepada pengguna." items={FRONTEND_STACK} />
                        <StackSection title="Data & perkhidmatan" description="Storan data dan perkhidmatan sokongan." items={DATA_STACK} />
                        <StackSection title="Alatan" description="Ujian dan kualiti kod." items={TOOLING_STACK} />
                    </div>
                )}

                {tab === 'versi' && (
                    <div className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6">
                        <Timeline items={CHANGELOG} />
                    </div>
                )}
            </PageContainer>
        </>
    );
}
