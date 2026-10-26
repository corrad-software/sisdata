# Akaun demo SISDATA (persekitaran dev / UAT sahaja)

> Akaun ini dicipta oleh `php artisan sisdata:demo-seed` (atau `--hanya-akses` pada DB sedia ada). **Jangan sekali-kali wujudkan akaun ini dalam produksi.**

Log masuk: http://localhost:3000/login

## Satu akaun bagi setiap peranan asas RFP (SRS-RBC-05)

| E-mel | Kata laluan | Peranan | Skop | Apa yang boleh dicuba |
| --- | --- | --- | --- | --- |
| `admin@example.com` | `password` | Pentadbir Sistem | Semua | Semua modul, tetapan, peranan, parameter kritikal |
| `pentadbir.sistem@mais.test` | `Sisdata@123` | Pentadbir Sistem | Semua | Sama seperti admin |
| `pentadbir.aplikasi@mais.test` | `Sisdata@123` | Pentadbir Aplikasi | Semua | Urus pengguna, peranan, kod rujukan, parameter; lihat semua modul (tiada Sulit/Rahsia) |
| `pegawai.operasi@mais.test` | `Sisdata@123` | Pegawai Operasi | Semua daerah | Cipta & kemas kini rekod harian di semua modul |
| `pegawai.klang@mais.test` | `Sisdata@123` | Pegawai Operasi | Daerah Klang (1001) | Hanya nampak lot/rekod daerah Klang |
| `siti.aminah@mais.test` | `Sisdata@123` | Pegawai Operasi + Pelulus | Daerah Petaling (1008) | Hanya Petaling; ahli kumpulan penyemak aliran kelulusan |
| `mohd.faizal@mais.test` | `Sisdata@123` | Pegawai Operasi + Pelulus + Perundangan (demo) | Semua | Pelulus utama demo; kes mahkamah |
| `pelulus@mais.test` | `Sisdata@123` | Pelulus | Semua | Lihat + lulus/tolak tugasan |
| `nurul.huda@mais.test` | `Sisdata@123` | Pengguna Paparan + Pelulus | Semua | Pengurusan: dashboard eksekutif, kelulusan peringkat atas |
| `paparan@mais.test` | `Sisdata@123` | Pengguna Paparan | Semua | Lihat sahaja — cuba ubah data, mesti ditolak |
| `juruaudit@mais.test` | `Sisdata@123` | Juruaudit | Semua | Audit Trail, log masuk, matriks capaian (baca/eksport sahaja) |
| `perundangan@mais.test` | `Sisdata@123` | Pegawai Operasi + Perundangan (demo) | Semua | Kes Mahkamah (Rahsia) — perlu MFA, lihat nota |
| `pihak.luar@mais.test` | `Sisdata@123` | Pemegang Taruh Terhad | — | Capaian minimum (portal/tiket sokongan) |

## Log masuk SSO MAIS (Keycloak, untuk MFA)

Modul/tindakan kritikal (Kes Mahkamah, peringkat kelulusan bertanda MFA, pengurusan kunci API) memerlukan sesi SSO. Klik **"Log Masuk dengan SSO MAIS"**:

| Nama pengguna Keycloak | Kata laluan | Akaun SISDATA |
| --- | --- | --- |
| `admin` | `Admin@12345` | admin@example.com |
| `siti.aminah` | `Sisdata@123` | siti.aminah@mais.test |
| `mohd.faizal` | `Sisdata@123` | mohd.faizal@mais.test (wajib daftar TOTP kali pertama) |
| `nurul.huda` | `Sisdata@123` | nurul.huda@mais.test |

Konsol Keycloak: http://localhost:8180/admin (`admin` / `admin`).

## Akaun sistem (bukan untuk log masuk manusia)

- `integrasi.portal-hws@sisdata.local`, `ujian.gateway-hws@sisdata.local` — akaun integrasi Portal Hartanah Wakaf Selangor.
- `*@sintetik.sisdata.local` — kakitangan sokongan sintetik (TKT).

## Servis lain

| Servis | URL | Log masuk |
| --- | --- | --- |
| Mailpit (e-mel keluar) | http://localhost:8025 | — |
| MinIO (fail dokumen) | http://localhost:9001 | `sisdata` / `sisdata_minio_pw` |
| Horizon (baris gilir) | http://localhost:8000/horizon | log masuk SISDATA sebagai pentadbir |
| Metabase (BI) | http://localhost:3030 | `admin@sisdata.local` / `Sisdata#Metabase1` (`infra/bin/metabase-dev.sh start`) |
