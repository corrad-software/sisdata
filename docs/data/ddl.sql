-- DDL boleh laksana (pg_dump --schema-only) daripada myds_laravel_test_pro, 24/10/2026 15:16
--
-- PostgreSQL database dump
--

\restrict ZxcWFrd7GPxo2pZHeseAcfzuN63hWCJWdtcxYkfN2TIRd8qPbpSxDnlmiZJagHB

-- Dumped from database version 17.10 (Homebrew)
-- Dumped by pg_dump version 17.10 (Homebrew)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: pg_trgm; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS pg_trgm WITH SCHEMA public;


--
-- Name: EXTENSION pg_trgm; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION pg_trgm IS 'text similarity measurement and index searching based on trigrams';


--
-- Name: postgis; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS postgis WITH SCHEMA public;


--
-- Name: EXTENSION postgis; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION postgis IS 'PostGIS geometry and geography spatial types and functions';


--
-- Name: vector; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS vector WITH SCHEMA public;


--
-- Name: EXTENSION vector; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION vector IS 'vector data type and ivfflat and hnsw access methods';


--
-- Name: aud_arkib_pindah(timestamp with time zone, text); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.aud_arkib_pindah(p_had timestamp with time zone, p_kelompok text) RETURNS integer
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public', 'pg_temp'
    AS $$
    DECLARE
        v_jumlah integer;
    BEGIN
        -- SRS-AUD-20 floor: never archive anything younger than 24 months.
        IF p_had > clock_timestamp() - interval '24 months' THEN
            RAISE EXCEPTION 'Had arkib % kurang daripada tempoh minimum 24 bulan', p_had;
        END IF;

        PERFORM pg_advisory_xact_lock(7319019);
        PERFORM set_config('sisdata.aud_arkib', 'on', true);

        INSERT INTO audit_arkib (id, no_urutan, cincangan_sebelum, cincangan_rekod, tarikh_masa_utc, tarikh_masa_tempatan, id_pengguna, pengecam_pengguna, peranan_semasa, id_sesi, alamat_ip, user_agent, jenis_peranti, lokasi_anggaran, saluran_capaian, modul, nama_entiti, id_rekod, jenis_tindakan, status_transaksi, sebab_perubahan, id_korelasi, klasifikasi, meta, perubahan, diarkib_pada, id_kelompok_arkib)
        SELECT r.id, r.no_urutan, r.cincangan_sebelum, r.cincangan_rekod, r.tarikh_masa_utc, r.tarikh_masa_tempatan, r.id_pengguna, r.pengecam_pengguna, r.peranan_semasa, r.id_sesi, r.alamat_ip, r.user_agent, r.jenis_peranti, r.lokasi_anggaran, r.saluran_capaian, r.modul, r.nama_entiti, r.id_rekod, r.jenis_tindakan, r.status_transaksi, r.sebab_perubahan, r.id_korelasi, r.klasifikasi, r.meta,
               (SELECT json_agg(json_build_object('nama_medan', p.nama_medan, 'nilai_asal', p.nilai_asal,
                        'nilai_baharu', p.nilai_baharu, 'sensitif_ditapis', p.sensitif_ditapis) ORDER BY p.id)
                  FROM audit_perubahan_medan p WHERE p.id_audit = r.id),
               now(), p_kelompok
          FROM audit_rekod r
         WHERE r.tarikh_masa_utc < p_had
         ORDER BY r.no_urutan;
        GET DIAGNOSTICS v_jumlah = ROW_COUNT;

        DELETE FROM audit_perubahan_medan p USING audit_rekod r
         WHERE p.id_audit = r.id AND r.tarikh_masa_utc < p_had;
        DELETE FROM audit_rekod r WHERE r.tarikh_masa_utc < p_had;

        PERFORM set_config('sisdata.aud_arkib', 'off', true);

        RETURN v_jumlah;
    END;
    $$;


--
-- Name: aud_tolak_ubah(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.aud_tolak_ubah() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    BEGIN
        -- Archive move (aud_arkib_pindah) only: the row must be old enough
        -- and already copied, byte-identical hash, into audit_arkib.
        IF TG_OP = 'DELETE' AND current_setting('sisdata.aud_arkib', true) = 'on' THEN
            -- Nested IFs: plpgsql resolves every OLD.<field> in an expression,
            -- so table-specific fields must not share one condition.
            IF TG_TABLE_NAME = 'audit_rekod' THEN
                IF OLD.tarikh_masa_utc < clock_timestamp() - interval '24 months'
                   AND EXISTS (SELECT 1 FROM audit_arkib a WHERE a.id = OLD.id AND a.cincangan_rekod = OLD.cincangan_rekod) THEN
                    RETURN OLD;
                END IF;
            ELSIF TG_TABLE_NAME = 'audit_perubahan_medan' THEN
                IF EXISTS (SELECT 1 FROM audit_arkib a WHERE a.id = OLD.id_audit) THEN
                    RETURN OLD;
                END IF;
            END IF;
        END IF;

        RAISE EXCEPTION 'Rekod audit hanya-tambah: % ke atas % ditolak', TG_OP, TG_TABLE_NAME
            USING ERRCODE = 'insufficient_privilege';
    END;
    $$;


--
-- Name: sp_kesan_duplikasi_lot(real, uuid); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.sp_kesan_duplikasi_lot(p_ambang_nama real DEFAULT 0.6, p_lot uuid DEFAULT NULL::uuid) RETURNS TABLE(id_a uuid, id_b uuid, no_lot_a text, no_lot_b text, skor_nama real, sama_mukim boolean, beza_keluasan_peratus numeric, pertindihan_peratus numeric)
    LANGUAGE sql STABLE
    AS $$
    SELECT a.id, b.id, a.no_lot_normal::text, b.no_lot_normal::text,
           similarity(a.no_lot_normal, b.no_lot_normal),
           a.kod_mukim = b.kod_mukim,
           CASE WHEN a.keluasan_m2 > 0 AND b.keluasan_m2 > 0
                THEN round(abs(a.keluasan_m2 - b.keluasan_m2) / greatest(a.keluasan_m2, b.keluasan_m2) * 100, 2) END,
           CASE WHEN ga.geometri IS NOT NULL AND gb.geometri IS NOT NULL AND ST_Intersects(ga.geometri, gb.geometri)
                THEN round((ST_Area(ST_Intersection(ga.geometri, gb.geometri)::geography)
                     / NULLIF(least(ST_Area(ga.geometri::geography), ST_Area(gb.geometri::geography)), 0) * 100)::numeric, 2)
                ELSE 0 END
    FROM (SELECT l.*, CASE l.unit_keluasan WHEN 'hektar' THEN l.keluasan * 10000 WHEN 'ekar' THEN l.keluasan * 4046.8564 ELSE l.keluasan END AS keluasan_m2
          FROM lot_tanah l WHERE l.deleted_at IS NULL AND l.status_lot NOT IN ('arkib', 'digabung')) a
    JOIN (SELECT l.*, CASE l.unit_keluasan WHEN 'hektar' THEN l.keluasan * 10000 WHEN 'ekar' THEN l.keluasan * 4046.8564 ELSE l.keluasan END AS keluasan_m2
          FROM lot_tanah l WHERE l.deleted_at IS NULL AND l.status_lot NOT IN ('arkib', 'digabung')) b
      ON a.id < b.id AND a.kod_daerah = b.kod_daerah
    LEFT JOIN data_spatial ga ON ga.lot_id = a.id AND ga.semasa
    LEFT JOIN data_spatial gb ON gb.lot_id = b.id AND gb.semasa
    WHERE (p_lot IS NULL OR a.id = p_lot OR b.id = p_lot)
      AND (
          similarity(a.no_lot_normal, b.no_lot_normal) >= p_ambang_nama
          OR (a.kod_mukim = b.kod_mukim AND regexp_replace(a.no_lot_normal, '[^0-9]', '', 'g') = regexp_replace(b.no_lot_normal, '[^0-9]', '', 'g'))
          OR (ga.geometri IS NOT NULL AND gb.geometri IS NOT NULL AND ST_Intersects(ga.geometri, gb.geometri)
              AND NOT ST_Touches(ga.geometri, gb.geometri))
      )
$$;


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: adm_indeks_carian; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.adm_indeks_carian (
    id bigint NOT NULL,
    entiti character varying(60) NOT NULL,
    label character varying(120),
    medan json,
    pemberat numeric(5,2) DEFAULT '1'::numeric NOT NULL,
    aktif boolean DEFAULT true NOT NULL,
    status_indeks character varying(20),
    diindeks_semula_pada timestamp(0) without time zone,
    justifikasi text,
    dikemas_kini_oleh bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN adm_indeks_carian.medan; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.adm_indeks_carian.medan IS 'searchable fields, passed to the provider';


--
-- Name: adm_indeks_carian_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.adm_indeks_carian_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: adm_indeks_carian_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.adm_indeks_carian_id_seq OWNED BY public.adm_indeks_carian.id;


--
-- Name: adm_integrasi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.adm_integrasi (
    id bigint NOT NULL,
    kod character varying(60) NOT NULL,
    nama character varying(200) NOT NULL,
    jenis character varying(30) DEFAULT 'rest'::character varying NOT NULL,
    endpoint character varying(500) NOT NULL,
    id_rahsia character varying(120),
    tempoh_tamat_masa integer DEFAULT 30 NOT NULL,
    kadar_capaian integer,
    jadual character varying(100),
    status character varying(20) DEFAULT 'aktif'::character varying NOT NULL,
    kegagalan_berturut integer DEFAULT 0 NOT NULL,
    terakhir_berjaya_pada timestamp(0) without time zone,
    terakhir_gagal_pada timestamp(0) without time zone,
    justifikasi text,
    dikemas_kini_oleh bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN adm_integrasi.id_rahsia; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.adm_integrasi.id_rahsia IS 'adm_rahsia.kunci — never the secret itself';


--
-- Name: COLUMN adm_integrasi.tempoh_tamat_masa; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.adm_integrasi.tempoh_tamat_masa IS 'seconds';


--
-- Name: COLUMN adm_integrasi.kadar_capaian; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.adm_integrasi.kadar_capaian IS 'max calls per minute';


--
-- Name: COLUMN adm_integrasi.jadual; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.adm_integrasi.jadual IS 'cron expression for scheduled exchange';


--
-- Name: adm_integrasi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.adm_integrasi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: adm_integrasi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.adm_integrasi_id_seq OWNED BY public.adm_integrasi.id;


--
-- Name: adm_kesihatan; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.adm_kesihatan (
    id bigint NOT NULL,
    komponen character varying(40) NOT NULL,
    status character varying(10) NOT NULL,
    masa_respons_ms integer,
    mesej character varying(500),
    butiran json,
    dicatat_pada timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: adm_kesihatan_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.adm_kesihatan_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: adm_kesihatan_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.adm_kesihatan_id_seq OWNED BY public.adm_kesihatan.id;


--
-- Name: adm_kod_rujukan; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.adm_kod_rujukan (
    id bigint NOT NULL,
    jenis_kod character varying(60) NOT NULL,
    kod character varying(60) NOT NULL,
    keterangan character varying(255) NOT NULL,
    kod_induk character varying(60),
    susunan integer DEFAULT 0 NOT NULL,
    status character varying(20) DEFAULT 'aktif'::character varying NOT NULL,
    tarikh_mula date NOT NULL,
    tarikh_tamat date,
    versi integer DEFAULT 1 NOT NULL,
    justifikasi text,
    dikemas_kini_oleh bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: adm_kod_rujukan_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.adm_kod_rujukan_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: adm_kod_rujukan_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.adm_kod_rujukan_id_seq OWNED BY public.adm_kod_rujukan.id;


--
-- Name: adm_kod_rujukan_sejarah; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.adm_kod_rujukan_sejarah (
    id bigint NOT NULL,
    id_kod_rujukan bigint NOT NULL,
    versi integer NOT NULL,
    data json NOT NULL,
    diubah_oleh bigint,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: adm_kod_rujukan_sejarah_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.adm_kod_rujukan_sejarah_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: adm_kod_rujukan_sejarah_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.adm_kod_rujukan_sejarah_id_seq OWNED BY public.adm_kod_rujukan_sejarah.id;


--
-- Name: adm_konfig_kelulusan; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.adm_konfig_kelulusan (
    id bigint NOT NULL,
    jenis_konfig character varying(30) NOT NULL,
    kunci character varying(120) NOT NULL,
    nilai_sebelum json,
    nilai_selepas json,
    nilai_rahsia_tersulit text,
    justifikasi text NOT NULL,
    id_pemohon bigint NOT NULL,
    alamat_ip_pemohon character varying(45),
    id_pelulus bigint,
    catatan_pelulus text,
    status character varying(20) DEFAULT 'menunggu'::character varying NOT NULL,
    id_instans_aliran character varying(64),
    diputuskan_pada timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN adm_konfig_kelulusan.id_instans_aliran; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.adm_konfig_kelulusan.id_instans_aliran IS 'MOD-WFE instance; null = built-in two-person approval';


--
-- Name: adm_konfig_kelulusan_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.adm_konfig_kelulusan_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: adm_konfig_kelulusan_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.adm_konfig_kelulusan_id_seq OWNED BY public.adm_konfig_kelulusan.id;


--
-- Name: adm_log_integrasi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.adm_log_integrasi (
    id bigint NOT NULL,
    kod_integrasi character varying(60) NOT NULL,
    id_korelasi character varying(64),
    tarikh_masa timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    arah character varying(10) DEFAULT 'keluar'::character varying NOT NULL,
    operasi character varying(120),
    berjaya boolean NOT NULL,
    status_http smallint,
    tempoh_ms integer,
    bilangan_rekod integer,
    mesej text,
    konteks json
);


--
-- Name: adm_log_integrasi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.adm_log_integrasi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: adm_log_integrasi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.adm_log_integrasi_id_seq OWNED BY public.adm_log_integrasi.id;


--
-- Name: adm_log_ralat; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.adm_log_ralat (
    id bigint NOT NULL,
    id_korelasi character varying(64),
    tarikh_masa timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    aras_keterukan character varying(12) NOT NULL,
    saluran character varying(30) DEFAULT 'aplikasi'::character varying NOT NULL,
    modul character varying(20),
    kod_ralat character varying(120),
    mesej text NOT NULL,
    jejak_tindanan text,
    id_pengguna bigint,
    alamat_ip character varying(45),
    konteks json
);


--
-- Name: adm_log_ralat_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.adm_log_ralat_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: adm_log_ralat_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.adm_log_ralat_id_seq OWNED BY public.adm_log_ralat.id;


--
-- Name: adm_parameter; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.adm_parameter (
    id bigint NOT NULL,
    kunci character varying(120) NOT NULL,
    modul character varying(20) NOT NULL,
    nilai text,
    justifikasi text,
    dikemas_kini_oleh bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN adm_parameter.nilai; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.adm_parameter.nilai IS 'JSON-encoded value';


--
-- Name: adm_parameter_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.adm_parameter_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: adm_parameter_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.adm_parameter_id_seq OWNED BY public.adm_parameter.id;


--
-- Name: adm_rahsia; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.adm_rahsia (
    id bigint NOT NULL,
    kunci character varying(120) NOT NULL,
    nilai_tersulit text NOT NULL,
    keterangan character varying(255),
    dikemas_kini_oleh bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: adm_rahsia_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.adm_rahsia_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: adm_rahsia_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.adm_rahsia_id_seq OWNED BY public.adm_rahsia.id;


--
-- Name: adm_sinonim_carian; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.adm_sinonim_carian (
    id bigint NOT NULL,
    istilah character varying(120) NOT NULL,
    sinonim json NOT NULL,
    dikemas_kini_oleh bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: adm_sinonim_carian_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.adm_sinonim_carian_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: adm_sinonim_carian_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.adm_sinonim_carian_id_seq OWNED BY public.adm_sinonim_carian.id;


--
-- Name: adm_templat; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.adm_templat (
    id bigint NOT NULL,
    kod character varying(80) NOT NULL,
    nama character varying(200) NOT NULL,
    jenis character varying(30) DEFAULT 'laporan'::character varying NOT NULL,
    format_output character varying(10) DEFAULT 'pdf'::character varying NOT NULL,
    kandungan text NOT NULL,
    medan json,
    versi integer DEFAULT 1 NOT NULL,
    status character varying(20) DEFAULT 'aktif'::character varying NOT NULL,
    justifikasi text,
    dikemas_kini_oleh bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: adm_templat_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.adm_templat_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: adm_templat_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.adm_templat_id_seq OWNED BY public.adm_templat.id;


--
-- Name: adm_templat_versi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.adm_templat_versi (
    id bigint NOT NULL,
    id_templat bigint NOT NULL,
    versi integer NOT NULL,
    format_output character varying(10) NOT NULL,
    kandungan text NOT NULL,
    medan json,
    dicipta_oleh bigint,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: adm_templat_versi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.adm_templat_versi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: adm_templat_versi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.adm_templat_versi_id_seq OWNED BY public.adm_templat_versi.id;


--
-- Name: adm_tugasan_berjadual; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.adm_tugasan_berjadual (
    id bigint NOT NULL,
    kod character varying(80) NOT NULL,
    nama character varying(200) NOT NULL,
    keterangan text,
    kategori character varying(30) NOT NULL,
    arahan character varying(200) NOT NULL,
    argumen json,
    jenis_jadual character varying(10) DEFAULT 'cron'::character varying NOT NULL,
    ungkapan_cron character varying(100),
    selang_minit integer,
    aktif boolean DEFAULT true NOT NULL,
    cubaan_semula smallint DEFAULT '0'::smallint NOT NULL,
    tempoh_tamat_masa_saat integer DEFAULT 3600 NOT NULL,
    toleransi_lewat_minit integer,
    sumber character varying(10) DEFAULT 'adm'::character varying NOT NULL,
    kunci_sistem character varying(200),
    terakhir_mula_pada timestamp(0) without time zone,
    terakhir_status character varying(20),
    justifikasi text,
    dikemas_kini_oleh bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN adm_tugasan_berjadual.arahan; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.adm_tugasan_berjadual.arahan IS 'artisan command name';


--
-- Name: adm_tugasan_berjadual_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.adm_tugasan_berjadual_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: adm_tugasan_berjadual_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.adm_tugasan_berjadual_id_seq OWNED BY public.adm_tugasan_berjadual.id;


--
-- Name: adm_tugasan_larian; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.adm_tugasan_larian (
    id bigint NOT NULL,
    id_tugasan bigint NOT NULL,
    pencetus character varying(10) DEFAULT 'jadual'::character varying NOT NULL,
    id_pengguna bigint,
    cubaan smallint DEFAULT '1'::smallint NOT NULL,
    tarikh_mula timestamp(0) without time zone,
    tarikh_tamat timestamp(0) without time zone,
    tempoh_saat numeric(10,3),
    status character varying(15) NOT NULL,
    bilangan_rekod bigint,
    mesej_ralat text,
    output text,
    id_korelasi character varying(64),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: adm_tugasan_larian_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.adm_tugasan_larian_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: adm_tugasan_larian_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.adm_tugasan_larian_id_seq OWNED BY public.adm_tugasan_larian.id;


--
-- Name: aduan; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.aduan (
    id uuid NOT NULL,
    lot_id uuid,
    kategori character varying(30),
    tarikh_aduan date,
    status character varying(30) DEFAULT 'baharu'::character varying NOT NULL,
    id_pegawai bigint,
    tindakan_susulan text,
    klasifikasi character varying(10) DEFAULT 'dalaman'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    koordinat public.geometry(Point,4326)
);


--
-- Name: amaran_keselamatan; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.amaran_keselamatan (
    id bigint NOT NULL,
    jenis character varying(40) NOT NULL,
    user_id bigint,
    alamat_ip character varying(45),
    butiran json,
    disemak_pada timestamp(0) without time zone,
    disemak_oleh bigint,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: amaran_keselamatan_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.amaran_keselamatan_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: amaran_keselamatan_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.amaran_keselamatan_id_seq OWNED BY public.amaran_keselamatan.id;


--
-- Name: audit_arkib; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.audit_arkib (
    id bigint NOT NULL,
    no_urutan bigint NOT NULL,
    cincangan_sebelum character(64) NOT NULL,
    cincangan_rekod character(64) NOT NULL,
    tarikh_masa_utc timestamp(6) with time zone NOT NULL,
    tarikh_masa_tempatan timestamp(6) without time zone NOT NULL,
    id_pengguna bigint,
    pengecam_pengguna character varying(255),
    peranan_semasa json NOT NULL,
    id_sesi character varying(80),
    alamat_ip character varying(45),
    user_agent text,
    jenis_peranti character varying(20) NOT NULL,
    lokasi_anggaran character varying(255),
    saluran_capaian character varying(20) NOT NULL,
    modul character varying(20) NOT NULL,
    nama_entiti character varying(100),
    id_rekod character varying(100),
    jenis_tindakan character varying(40) NOT NULL,
    status_transaksi character varying(20) NOT NULL,
    sebab_perubahan text,
    id_korelasi character varying(64),
    klasifikasi character varying(20),
    meta json,
    perubahan json,
    diarkib_pada timestamp(6) with time zone NOT NULL,
    id_kelompok_arkib character varying(64) NOT NULL
);


--
-- Name: audit_eksport; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.audit_eksport (
    id bigint NOT NULL,
    id_pengguna bigint,
    pengecam_pengguna character varying(255),
    kriteria_carian json NOT NULL,
    format character varying(10) NOT NULL,
    bilangan_rekod integer NOT NULL,
    checksum character(64) NOT NULL,
    checksum_data character(64) NOT NULL,
    nama_fail character varying(255) NOT NULL,
    saiz_bait bigint NOT NULL,
    tarikh_masa_utc timestamp(6) with time zone NOT NULL,
    tarikh_masa_tempatan timestamp(6) without time zone NOT NULL
);


--
-- Name: audit_eksport_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.audit_eksport_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: audit_eksport_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.audit_eksport_id_seq OWNED BY public.audit_eksport.id;


--
-- Name: audit_integriti_semakan; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.audit_integriti_semakan (
    id bigint NOT NULL,
    dimulakan_pada timestamp(6) with time zone NOT NULL,
    selesai_pada timestamp(6) with time zone NOT NULL,
    status character varying(10) NOT NULL,
    bilangan_disemak bigint NOT NULL,
    no_urutan_terakhir bigint,
    no_urutan_rosak bigint,
    butiran text,
    dicetuskan_oleh character varying(20) NOT NULL,
    id_pengguna bigint
);


--
-- Name: audit_integriti_semakan_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.audit_integriti_semakan_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: audit_integriti_semakan_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.audit_integriti_semakan_id_seq OWNED BY public.audit_integriti_semakan.id;


--
-- Name: audit_perubahan_medan; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.audit_perubahan_medan (
    id bigint NOT NULL,
    id_audit bigint NOT NULL,
    nama_medan character varying(150) NOT NULL,
    nilai_asal text,
    nilai_baharu text,
    sensitif_ditapis boolean DEFAULT false NOT NULL
);


--
-- Name: audit_perubahan_medan_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.audit_perubahan_medan_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: audit_perubahan_medan_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.audit_perubahan_medan_id_seq OWNED BY public.audit_perubahan_medan.id;


--
-- Name: audit_rekod; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.audit_rekod (
    id bigint NOT NULL,
    no_urutan bigint NOT NULL,
    cincangan_sebelum character(64) NOT NULL,
    cincangan_rekod character(64) NOT NULL,
    tarikh_masa_utc timestamp(6) with time zone NOT NULL,
    tarikh_masa_tempatan timestamp(6) without time zone NOT NULL,
    id_pengguna bigint,
    pengecam_pengguna character varying(255),
    peranan_semasa json NOT NULL,
    id_sesi character varying(80),
    alamat_ip character varying(45),
    user_agent text,
    jenis_peranti character varying(20) NOT NULL,
    lokasi_anggaran character varying(255),
    saluran_capaian character varying(20) NOT NULL,
    modul character varying(20) NOT NULL,
    nama_entiti character varying(100),
    id_rekod character varying(100),
    jenis_tindakan character varying(40) NOT NULL,
    status_transaksi character varying(20) NOT NULL,
    sebab_perubahan text,
    id_korelasi character varying(64),
    klasifikasi character varying(20),
    meta json
);


--
-- Name: audit_rekod_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.audit_rekod_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: audit_rekod_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.audit_rekod_id_seq OWNED BY public.audit_rekod.id;


--
-- Name: audit_retensi_polisi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.audit_retensi_polisi (
    id bigint NOT NULL,
    tempoh_dalam_talian_bulan smallint NOT NULL,
    tempoh_arkib_tahun smallint,
    catatan text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT audit_retensi_minimum CHECK ((tempoh_dalam_talian_bulan >= 24))
);


--
-- Name: audit_retensi_polisi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.audit_retensi_polisi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: audit_retensi_polisi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.audit_retensi_polisi_id_seq OWNED BY public.audit_retensi_polisi.id;


--
-- Name: baris_import; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.baris_import (
    id bigint NOT NULL,
    id_sesi uuid NOT NULL,
    no_baris integer NOT NULL,
    data jsonb NOT NULL,
    status character varying(20) NOT NULL,
    id_rekod character varying(64),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN baris_import.status; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.baris_import.status IS 'sah | ditolak | diabaikan | dikomit';


--
-- Name: baris_import_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.baris_import_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: baris_import_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.baris_import_id_seq OWNED BY public.baris_import.id;


--
-- Name: cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: cadangan_duplikasi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cadangan_duplikasi (
    id bigint NOT NULL,
    entiti character varying(64) DEFAULT 'lot_tanah'::character varying NOT NULL,
    id_rekod_a uuid NOT NULL,
    id_rekod_b uuid NOT NULL,
    kriteria_padanan jsonb NOT NULL,
    skor_padanan numeric(5,2) NOT NULL,
    keputusan character varying(20) DEFAULT 'menunggu'::character varying NOT NULL,
    catatan_keputusan text,
    id_data_steward bigint,
    tarikh_keputusan timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN cadangan_duplikasi.keputusan; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.cadangan_duplikasi.keputusan IS 'menunggu | sah | gabung | tolak';


--
-- Name: cadangan_duplikasi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cadangan_duplikasi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cadangan_duplikasi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cadangan_duplikasi_id_seq OWNED BY public.cadangan_duplikasi.id;


--
-- Name: cukai; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cukai (
    id uuid NOT NULL,
    lot_id uuid NOT NULL,
    jenis character varying(30) NOT NULL,
    no_bil character varying(50),
    jumlah numeric(14,2),
    tarikh_akhir date,
    status_bayaran character varying(30) DEFAULT 'belum_bayar'::character varying NOT NULL,
    kaedah_bayaran character varying(30),
    klasifikasi character varying(10) DEFAULT 'dalaman'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: COLUMN cukai.jenis; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.cukai.jenis IS 'tanah | petak | taksiran_pbt';


--
-- Name: daftar_stewardship_data; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.daftar_stewardship_data (
    id bigint NOT NULL,
    komponen_data character varying(100) NOT NULL,
    entiti_berkaitan character varying(100) NOT NULL,
    jawatan_data_owner character varying(150) NOT NULL,
    bahagian_data_owner character varying(150) NOT NULL,
    id_data_owner bigint,
    jawatan_data_steward character varying(150) NOT NULL,
    bahagian_data_steward character varying(150) NOT NULL,
    id_data_steward bigint,
    bahagian_pemilik character varying(30),
    kod_daerah character varying(4),
    peranan_tanggungjawab text NOT NULL,
    tarikh_berkuat_kuasa date NOT NULL,
    status character varying(20) DEFAULT 'aktif'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN daftar_stewardship_data.entiti_berkaitan; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.daftar_stewardship_data.entiti_berkaitan IS 'Jadual, dipisah koma';


--
-- Name: COLUMN daftar_stewardship_data.bahagian_pemilik; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.daftar_stewardship_data.bahagian_pemilik IS 'Hadkan kepada bahagian lot (hartanah | wakaf | baitulmal)';


--
-- Name: COLUMN daftar_stewardship_data.kod_daerah; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.daftar_stewardship_data.kod_daerah IS 'Hadkan kepada daerah (steward daerah)';


--
-- Name: daftar_stewardship_data_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.daftar_stewardship_data_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: daftar_stewardship_data_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.daftar_stewardship_data_id_seq OWNED BY public.daftar_stewardship_data.id;


--
-- Name: data_spatial; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.data_spatial (
    id bigint NOT NULL,
    lot_id uuid NOT NULL,
    srid_asal integer DEFAULT 4326 NOT NULL,
    keluasan_dikira numeric(16,4),
    sumber_data character varying(30) NOT NULL,
    rujukan_fail_sumber character varying(255),
    tarikh_perolehan_data date,
    status_pengesahan character varying(30) DEFAULT 'belum_disahkan'::character varying NOT NULL,
    id_pegawai_pengesah bigint,
    tarikh_pengesahan timestamp(0) without time zone,
    catatan_pertindihan text,
    semasa boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    geometri public.geometry(MultiPolygon,4326) NOT NULL,
    versi integer DEFAULT 1 NOT NULL,
    format_asal character varying(20),
    id_dokumen_sumber character varying(64),
    peratus_beza_keluasan numeric(10,2),
    id_pemuat_naik bigint,
    catatan_pengesahan text,
    sumber_sistem character varying(100),
    kunci_asal character varying(100),
    CONSTRAINT data_spatial_status_check CHECK (((status_pengesahan)::text = ANY ((ARRAY['belum_disahkan'::character varying, 'disahkan_juruukur'::character varying, 'disahkan_ptg'::character varying, 'bertindih'::character varying])::text[]))),
    CONSTRAINT data_spatial_sumber_check CHECK (((sumber_data)::text = ANY ((ARRAY['jupem'::character varying, 'ptg'::character varying, 'pbt'::character varying, 'juruukur'::character varying, 'lakaran'::character varying, 'satelit'::character varying, 'sintetik'::character varying])::text[])))
);


--
-- Name: COLUMN data_spatial.sumber_data; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.data_spatial.sumber_data IS 'jupem | ptg | pbt | juruukur | lakaran | satelit | sintetik';


--
-- Name: COLUMN data_spatial.status_pengesahan; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.data_spatial.status_pengesahan IS 'belum_disahkan | disahkan_juruukur | disahkan_ptg | bertindih';


--
-- Name: COLUMN data_spatial.format_asal; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.data_spatial.format_asal IS 'geojson | wkt | kml | shp';


--
-- Name: COLUMN data_spatial.id_dokumen_sumber; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.data_spatial.id_dokumen_sumber IS 'Fail asal dalam DMS';


--
-- Name: COLUMN data_spatial.peratus_beza_keluasan; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.data_spatial.peratus_beza_keluasan IS 'Beza keluasan dikira berbanding keluasan diisytihar (%)';


--
-- Name: data_spatial_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.data_spatial_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: data_spatial_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.data_spatial_id_seq OWNED BY public.data_spatial.id;


--
-- Name: dokumen; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.dokumen (
    id uuid NOT NULL,
    no_rujukan character varying(30) NOT NULL,
    kategori_id bigint NOT NULL,
    modul_sumber character varying(20) DEFAULT 'dms'::character varying NOT NULL,
    jenis_entiti_induk character varying(64),
    id_rekod_induk character varying(64),
    pautan_induk character varying(255),
    tajuk character varying(255) NOT NULL,
    nama_fail character varying(255) NOT NULL,
    jenis_mime character varying(150) NOT NULL,
    sambungan character varying(16) NOT NULL,
    saiz_bait bigint NOT NULL,
    cincangan_fail character(64) NOT NULL,
    klasifikasi_keselamatan character varying(20) DEFAULT 'dalaman'::character varying NOT NULL,
    status character varying(20) DEFAULT 'aktif'::character varying NOT NULL,
    tarikh_dokumen date,
    tarikh_luput date,
    tahun smallint,
    kod_daerah character varying(20),
    kod_mukim character varying(20),
    pemilik_proses_id bigint,
    id_pencipta bigint,
    versi_semasa integer DEFAULT 1 NOT NULL,
    kata_kunci jsonb DEFAULT '[]'::jsonb NOT NULL,
    metadata jsonb DEFAULT '{}'::jsonb NOT NULL,
    teks_kandungan text,
    status_indeks character varying(20) DEFAULT 'menunggu'::character varying NOT NULL,
    kaedah_indeks character varying(20),
    amaran_luput_pada timestamp(0) without time zone,
    tarikh_arkib timestamp(0) without time zone,
    tarikh_lupus timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    carian tsvector GENERATED ALWAYS AS ((((setweight(to_tsvector('simple'::regconfig, (((((COALESCE(tajuk, ''::character varying))::text || ' '::text) || (COALESCE(nama_fail, ''::character varying))::text) || ' '::text) || (COALESCE(no_rujukan, ''::character varying))::text)), 'A'::"char") || setweight(to_tsvector('simple'::regconfig, COALESCE((kata_kunci)::text, ''::text)), 'A'::"char")) || setweight(to_tsvector('simple'::regconfig, ((((COALESCE((metadata)::text, ''::text) || ' '::text) || (COALESCE(jenis_entiti_induk, ''::character varying))::text) || ' '::text) || (COALESCE(id_rekod_induk, ''::character varying))::text)), 'B'::"char")) || setweight(to_tsvector('simple'::regconfig, "left"(COALESCE(teks_kandungan, ''::text), 500000)), 'C'::"char"))) STORED
);


--
-- Name: dokumen_arkib; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.dokumen_arkib (
    id bigint NOT NULL,
    dokumen_id uuid NOT NULL,
    tarikh_arkib timestamp(0) without time zone NOT NULL,
    tarikh_layak_lupus date,
    sebab character varying(30) NOT NULL,
    diarkib_oleh bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: dokumen_arkib_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.dokumen_arkib_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: dokumen_arkib_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.dokumen_arkib_id_seq OWNED BY public.dokumen_arkib.id;


--
-- Name: dokumen_imbasan; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.dokumen_imbasan (
    id bigint NOT NULL,
    dokumen_id uuid,
    kuarantin_id bigint,
    nama_fail character varying(255) NOT NULL,
    cincangan_fail character(64) NOT NULL,
    enjin character varying(50) NOT NULL,
    versi_tandatangan character varying(255),
    keputusan character varying(20) NOT NULL,
    nama_ancaman character varying(255),
    tarikh_imbasan timestamp(0) without time zone NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: dokumen_imbasan_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.dokumen_imbasan_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: dokumen_imbasan_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.dokumen_imbasan_id_seq OWNED BY public.dokumen_imbasan.id;


--
-- Name: dokumen_kategori; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.dokumen_kategori (
    id bigint NOT NULL,
    kod character varying(50) NOT NULL,
    nama character varying(255) NOT NULL,
    keterangan text,
    jenis_fail_dibenarkan jsonb,
    had_saiz_mb integer,
    tempoh_retensi_bulan integer,
    tempoh_arkib_bulan integer,
    tindakan_selepas_arkib character varying(20) DEFAULT 'semak'::character varying NOT NULL,
    tempoh_amaran_luput_hari integer,
    klasifikasi_lalai character varying(20) DEFAULT 'dalaman'::character varying NOT NULL,
    aktif boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: dokumen_kategori_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.dokumen_kategori_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: dokumen_kategori_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.dokumen_kategori_id_seq OWNED BY public.dokumen_kategori.id;


--
-- Name: dokumen_kebenaran; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.dokumen_kebenaran (
    id bigint NOT NULL,
    dokumen_id uuid NOT NULL,
    jenis_penerima character varying(20) NOT NULL,
    penerima character varying(64) NOT NULL,
    diberi_oleh bigint,
    tamat_pada timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: dokumen_kebenaran_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.dokumen_kebenaran_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: dokumen_kebenaran_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.dokumen_kebenaran_id_seq OWNED BY public.dokumen_kebenaran.id;


--
-- Name: dokumen_kuarantin; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.dokumen_kuarantin (
    id bigint NOT NULL,
    nama_fail character varying(255) NOT NULL,
    saiz_bait bigint NOT NULL,
    cincangan_fail character(64) NOT NULL,
    kunci_storan character varying(255),
    kunci_data_bersulit text,
    id_kunci_induk character varying(16),
    nama_ancaman character varying(255),
    jenis_entiti_induk character varying(64),
    id_rekod_induk character varying(64),
    dimuat_naik_oleh bigint,
    dimusnahkan_pada timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: dokumen_kuarantin_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.dokumen_kuarantin_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: dokumen_kuarantin_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.dokumen_kuarantin_id_seq OWNED BY public.dokumen_kuarantin.id;


--
-- Name: dokumen_medan_metadata; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.dokumen_medan_metadata (
    id bigint NOT NULL,
    kategori_id bigint,
    kod character varying(50) NOT NULL,
    label character varying(255) NOT NULL,
    jenis character varying(20) NOT NULL,
    pilihan jsonb,
    wajib boolean DEFAULT false NOT NULL,
    aktif boolean DEFAULT true NOT NULL,
    susunan integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: dokumen_medan_metadata_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.dokumen_medan_metadata_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: dokumen_medan_metadata_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.dokumen_medan_metadata_id_seq OWNED BY public.dokumen_medan_metadata.id;


--
-- Name: dokumen_no_rujukan_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.dokumen_no_rujukan_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: dokumen_pelupusan; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.dokumen_pelupusan (
    id bigint NOT NULL,
    dokumen_id uuid NOT NULL,
    justifikasi text NOT NULL,
    dimohon_oleh bigint,
    id_aliran_kerja character varying(64),
    status character varying(20) DEFAULT 'menunggu'::character varying NOT NULL,
    diputuskan_oleh bigint,
    catatan_keputusan text,
    tarikh_keputusan timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: dokumen_pelupusan_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.dokumen_pelupusan_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: dokumen_pelupusan_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.dokumen_pelupusan_id_seq OWNED BY public.dokumen_pelupusan.id;


--
-- Name: dokumen_versi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.dokumen_versi (
    id bigint NOT NULL,
    dokumen_id uuid NOT NULL,
    no_versi integer NOT NULL,
    nama_fail character varying(255) NOT NULL,
    jenis_mime character varying(150) NOT NULL,
    saiz_bait bigint NOT NULL,
    cincangan_fail character(64) NOT NULL,
    kunci_storan character varying(255),
    kunci_data_bersulit text,
    id_kunci_induk character varying(16),
    algoritma character varying(30) DEFAULT 'AES-256-GCM'::character varying NOT NULL,
    dimuat_naik_oleh bigint,
    catatan text,
    metadata_snapshot jsonb DEFAULT '{}'::jsonb NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: dokumen_versi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.dokumen_versi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: dokumen_versi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.dokumen_versi_id_seq OWNED BY public.dokumen_versi.id;


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: jadual_retensi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.jadual_retensi (
    id bigint NOT NULL,
    kategori_rekod character varying(50) NOT NULL,
    keterangan character varying(255) NOT NULL,
    entiti character varying(64) DEFAULT 'lot_tanah'::character varying NOT NULL,
    syarat jsonb DEFAULT '{}'::jsonb NOT NULL,
    tempoh_retensi_tahun smallint NOT NULL,
    tindakan_selepas_retensi character varying(20) DEFAULT 'arkib'::character varying NOT NULL,
    rujukan_dasar_mais character varying(255),
    memerlukan_kelulusan boolean DEFAULT true NOT NULL,
    id_pelulus bigint,
    aktif boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN jadual_retensi.syarat; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.jadual_retensi.syarat IS 'Contoh {"status_lot": ["tidak_aktif","digabung"]}';


--
-- Name: jadual_retensi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.jadual_retensi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: jadual_retensi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.jadual_retensi_id_seq OWNED BY public.jadual_retensi.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


--
-- Name: jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: kamus_data; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.kamus_data (
    id bigint NOT NULL,
    nama_jadual character varying(64) NOT NULL,
    nama_medan character varying(64),
    nama_perniagaan character varying(150) NOT NULL,
    definisi text,
    format character varying(150),
    nilai_dibenarkan character varying(500),
    wajib boolean,
    data_owner character varying(150),
    data_steward character varying(150),
    klasifikasi character varying(10) DEFAULT 'dalaman'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN kamus_data.nama_medan; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.kamus_data.nama_medan IS 'NULL = metadata jadual';


--
-- Name: kamus_data_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.kamus_data_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: kamus_data_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.kamus_data_id_seq OWNED BY public.kamus_data.id;


--
-- Name: kata_laluan_sejarah; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.kata_laluan_sejarah (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    kata_laluan character varying(255) NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: kata_laluan_sejarah_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.kata_laluan_sejarah_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: kata_laluan_sejarah_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.kata_laluan_sejarah_id_seq OWNED BY public.kata_laluan_sejarah.id;


--
-- Name: kebenaran; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.kebenaran (
    kunci character varying(150) NOT NULL,
    modul character varying(30) NOT NULL,
    fungsi character varying(60) NOT NULL,
    tindakan character varying(30) NOT NULL,
    label character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: kes_mahkamah; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.kes_mahkamah (
    id uuid NOT NULL,
    no_kes character varying(80) NOT NULL,
    jenis character varying(50),
    mahkamah character varying(150),
    status character varying(30) DEFAULT 'aktif'::character varying NOT NULL,
    kos_guaman numeric(14,2),
    klasifikasi character varying(10) DEFAULT 'rahsia'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: kes_mahkamah_lot; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.kes_mahkamah_lot (
    kes_mahkamah_id uuid NOT NULL,
    lot_id uuid NOT NULL
);


--
-- Name: klasifikasi_data; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.klasifikasi_data (
    id bigint NOT NULL,
    komponen_data character varying(100) NOT NULL,
    entiti character varying(64) NOT NULL,
    aras_kerahsiaan character varying(10) NOT NULL,
    keperluan_encryption character varying(150) NOT NULL,
    peranan_dibenarkan character varying(255) NOT NULL,
    kebenaran_diperlukan character varying(100),
    tarikh_semakan date NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: klasifikasi_data_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.klasifikasi_data_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: klasifikasi_data_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.klasifikasi_data_id_seq OWNED BY public.klasifikasi_data.id;


--
-- Name: kontrak_pajakan; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.kontrak_pajakan (
    id uuid NOT NULL,
    lot_id uuid NOT NULL,
    penyewa character varying(200),
    tarikh_mula date,
    tarikh_tamat date,
    kadar numeric(14,2),
    deposit numeric(14,2),
    terma text,
    status character varying(30) DEFAULT 'draf'::character varying NOT NULL,
    klasifikasi character varying(10) DEFAULT 'sulit'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: kunci_api_pro; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.kunci_api_pro (
    id bigint NOT NULL,
    id_token bigint NOT NULL,
    id_pemilik bigint NOT NULL,
    sistem_pemanggil character varying(100) NOT NULL,
    skop jsonb NOT NULL,
    had_kadar_seminit integer DEFAULT 60 NOT NULL,
    tamat_pada timestamp(0) without time zone,
    dibatalkan_pada timestamp(0) without time zone,
    dibatalkan_oleh bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN kunci_api_pro.id_token; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.kunci_api_pro.id_token IS 'personal_access_tokens.id';


--
-- Name: kunci_api_pro_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.kunci_api_pro_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: kunci_api_pro_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.kunci_api_pro_id_seq OWNED BY public.kunci_api_pro.id;


--
-- Name: log_akses_profil; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.log_akses_profil (
    id bigint NOT NULL,
    lot_id uuid NOT NULL,
    id_pengguna bigint,
    jenis_capaian character varying(20) NOT NULL,
    saluran character varying(20) DEFAULT 'web'::character varying NOT NULL,
    alamat_ip character varying(45),
    butiran jsonb,
    tarikh_masa timestamp(0) without time zone NOT NULL
);


--
-- Name: COLUMN log_akses_profil.jenis_capaian; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.log_akses_profil.jenis_capaian IS 'lihat | eksport | cetak | api';


--
-- Name: log_akses_profil_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.log_akses_profil_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: log_akses_profil_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.log_akses_profil_id_seq OWNED BY public.log_akses_profil.id;


--
-- Name: log_log_masuk; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.log_log_masuk (
    id bigint NOT NULL,
    user_id bigint,
    pengecam character varying(255),
    kaedah character varying(20) NOT NULL,
    berjaya boolean NOT NULL,
    sebab character varying(60),
    alamat_ip character varying(45),
    user_agent text,
    peranti character varying(30),
    pelayar character varying(60),
    platform character varying(60),
    sidik_peranti character varying(64),
    lokasi character varying(255),
    negara character varying(60),
    mencurigakan boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: log_log_masuk_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.log_log_masuk_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: log_log_masuk_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.log_log_masuk_id_seq OWNED BY public.log_log_masuk.id;


--
-- Name: log_penggunaan_api; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.log_penggunaan_api (
    id bigint NOT NULL,
    id_token bigint,
    sistem_pemanggil character varying(100),
    kaedah character varying(10) NOT NULL,
    laluan character varying(255) NOT NULL,
    kod_status smallint NOT NULL,
    alamat_ip character varying(45),
    tarikh_masa timestamp(0) without time zone NOT NULL
);


--
-- Name: log_penggunaan_api_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.log_penggunaan_api_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: log_penggunaan_api_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.log_penggunaan_api_id_seq OWNED BY public.log_penggunaan_api.id;


--
-- Name: lot_tanah; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.lot_tanah (
    id uuid NOT NULL,
    no_lot character varying(50) NOT NULL,
    kod_mukim character varying(6) NOT NULL,
    kod_daerah character varying(4) NOT NULL,
    kod_negeri character varying(2) DEFAULT '10'::character varying NOT NULL,
    keluasan numeric(16,4),
    unit_keluasan character varying(10) DEFAULT 'm2'::character varying NOT NULL,
    kategori_guna_tanah character varying(30),
    zoning character varying(50),
    syarat_nyata text,
    bahagian_pemilik character varying(30),
    status_lot character varying(30) DEFAULT 'aktif'::character varying NOT NULL,
    klasifikasi character varying(10) DEFAULT 'dalaman'::character varying NOT NULL,
    id_data_owner bigint,
    id_data_steward bigint,
    versi_rekod integer DEFAULT 1 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    koordinat_pusat public.geometry(Point,4326),
    no_lot_normal character varying(50),
    sintetik boolean DEFAULT false NOT NULL,
    sumber_sistem character varying(100),
    fail_asal character varying(255),
    kunci_asal character varying(100),
    id_migrasi_larian uuid,
    id_sesi_import uuid,
    id_lot_gabung uuid,
    status_sebelum_arkib character varying(30),
    diarkib_pada timestamp(0) without time zone,
    tag jsonb DEFAULT '[]'::jsonb NOT NULL,
    catatan text,
    id_pencipta bigint,
    CONSTRAINT lot_tanah_bahagian_check CHECK (((bahagian_pemilik IS NULL) OR ((bahagian_pemilik)::text = ANY ((ARRAY['hartanah'::character varying, 'wakaf'::character varying, 'baitulmal'::character varying])::text[])))),
    CONSTRAINT lot_tanah_keluasan_check CHECK (((keluasan IS NULL) OR (keluasan > (0)::numeric))),
    CONSTRAINT lot_tanah_klasifikasi_check CHECK (((klasifikasi)::text = ANY ((ARRAY['awam'::character varying, 'dalaman'::character varying, 'sulit'::character varying, 'rahsia'::character varying])::text[])))
);


--
-- Name: COLUMN lot_tanah.bahagian_pemilik; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.lot_tanah.bahagian_pemilik IS 'hartanah | wakaf | baitulmal';


--
-- Name: COLUMN lot_tanah.no_lot_normal; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.lot_tanah.no_lot_normal IS 'No. lot dinormalkan (huruf besar, tanpa tanda baca) untuk padanan duplikasi';


--
-- Name: COLUMN lot_tanah.sintetik; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.lot_tanah.sintetik IS 'Data contoh sintetik — bukan rekod sebenar';


--
-- Name: COLUMN lot_tanah.sumber_sistem; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.lot_tanah.sumber_sistem IS 'Salasilah: sistem sumber (SRS-DAT-08)';


--
-- Name: COLUMN lot_tanah.fail_asal; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.lot_tanah.fail_asal IS 'Salasilah: jadual / fail asal';


--
-- Name: COLUMN lot_tanah.kunci_asal; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.lot_tanah.kunci_asal IS 'Salasilah: kunci rekod dalam sumber';


--
-- Name: COLUMN lot_tanah.id_lot_gabung; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.lot_tanah.id_lot_gabung IS 'Lot induk apabila rekod ini digabungkan (duplikasi)';


--
-- Name: migrasi_larian; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrasi_larian (
    id uuid NOT NULL,
    jenis character varying(50) NOT NULL,
    sumber_sistem character varying(100) NOT NULL,
    parameter json,
    status character varying(20) DEFAULT 'berjalan'::character varying NOT NULL,
    bilangan_diterima integer DEFAULT 0 NOT NULL,
    bilangan_ditolak integer DEFAULT 0 NOT NULL,
    catatan text,
    mula_pada timestamp(0) without time zone NOT NULL,
    tamat_pada timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: notifications; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notifications (
    id uuid NOT NULL,
    type character varying(255) NOT NULL,
    notifiable_type character varying(255) NOT NULL,
    notifiable_id bigint NOT NULL,
    data text NOT NULL,
    read_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: ntf_eskalasi_log; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ntf_eskalasi_log (
    id bigint NOT NULL,
    status_pengguna_id bigint,
    mesej_id bigint NOT NULL,
    penerima_asal_id bigint,
    penyelia_id bigint,
    mesej_eskalasi_id bigint,
    tarikh_eskalasi timestamp(0) without time zone NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: ntf_eskalasi_log_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ntf_eskalasi_log_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ntf_eskalasi_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ntf_eskalasi_log_id_seq OWNED BY public.ntf_eskalasi_log.id;


--
-- Name: ntf_jenis; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ntf_jenis (
    id bigint NOT NULL,
    kod character varying(100) NOT NULL,
    nama character varying(255) NOT NULL,
    modul character varying(20) NOT NULL,
    keutamaan character varying(10) NOT NULL,
    wajib boolean DEFAULT false NOT NULL,
    saluran_lalai jsonb NOT NULL,
    kekerapan character varying(20) DEFAULT 'serta_merta'::character varying NOT NULL,
    tempoh_eskalasi_minit integer,
    tempoh_pendiaman_minit integer DEFAULT 0 NOT NULL,
    hari_peringatan jsonb,
    medan jsonb DEFAULT '[]'::jsonb NOT NULL,
    medan_sensitif jsonb DEFAULT '[]'::jsonb NOT NULL,
    aktif boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: ntf_jenis_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ntf_jenis_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ntf_jenis_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ntf_jenis_id_seq OWNED BY public.ntf_jenis.id;


--
-- Name: ntf_keutamaan_pengguna; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ntf_keutamaan_pengguna (
    id bigint NOT NULL,
    pengguna_id bigint NOT NULL,
    jenis_id bigint NOT NULL,
    aktif boolean DEFAULT true NOT NULL,
    saluran jsonb,
    ringkasan boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: ntf_keutamaan_pengguna_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ntf_keutamaan_pengguna_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ntf_keutamaan_pengguna_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ntf_keutamaan_pengguna_id_seq OWNED BY public.ntf_keutamaan_pengguna.id;


--
-- Name: ntf_mesej; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ntf_mesej (
    id bigint NOT NULL,
    jenis_id bigint NOT NULL,
    templat_id bigint,
    keutamaan character varying(10) NOT NULL,
    modul character varying(20) NOT NULL,
    subjek character varying(255) NOT NULL,
    kandungan text NOT NULL,
    pautan character varying(255),
    data jsonb DEFAULT '{}'::jsonb NOT NULL,
    kunci_dedupe character varying(191),
    dicipta_oleh bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: ntf_mesej_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ntf_mesej_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ntf_mesej_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ntf_mesej_id_seq OWNED BY public.ntf_mesej.id;


--
-- Name: ntf_penerima_peraturan; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ntf_penerima_peraturan (
    id bigint NOT NULL,
    jenis_id bigint NOT NULL,
    sasaran character varying(20) NOT NULL,
    nilai character varying(255) NOT NULL,
    aktif boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: ntf_penerima_peraturan_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ntf_penerima_peraturan_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ntf_penerima_peraturan_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ntf_penerima_peraturan_id_seq OWNED BY public.ntf_penerima_peraturan.id;


--
-- Name: ntf_penghantaran; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ntf_penghantaran (
    id bigint NOT NULL,
    mesej_id bigint NOT NULL,
    penerima_id bigint,
    saluran character varying(20) NOT NULL,
    status character varying(20) NOT NULL,
    bilangan_cubaan smallint DEFAULT '0'::smallint NOT NULL,
    kod_ralat character varying(60),
    mesej_ralat text,
    ringkasan boolean DEFAULT false NOT NULL,
    tarikh_beratur timestamp(0) without time zone,
    tarikh_hantar timestamp(0) without time zone,
    tarikh_terima timestamp(0) without time zone,
    tarikh_baca timestamp(0) without time zone,
    tarikh_gagal timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: ntf_penghantaran_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ntf_penghantaran_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ntf_penghantaran_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ntf_penghantaran_id_seq OWNED BY public.ntf_penghantaran.id;


--
-- Name: ntf_status_pengguna; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ntf_status_pengguna (
    id bigint NOT NULL,
    mesej_id bigint NOT NULL,
    pengguna_id bigint NOT NULL,
    dibaca_pada timestamp(0) without time zone,
    diarkib_pada timestamp(0) without time zone,
    dipadam_pada timestamp(0) without time zone,
    dieskalasi_pada timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: ntf_status_pengguna_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ntf_status_pengguna_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ntf_status_pengguna_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ntf_status_pengguna_id_seq OWNED BY public.ntf_status_pengguna.id;


--
-- Name: ntf_templat; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ntf_templat (
    id bigint NOT NULL,
    jenis_id bigint NOT NULL,
    versi integer NOT NULL,
    subjek character varying(255) NOT NULL,
    kandungan text NOT NULL,
    aktif boolean DEFAULT false NOT NULL,
    catatan character varying(255),
    dicipta_oleh bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: ntf_templat_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ntf_templat_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ntf_templat_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ntf_templat_id_seq OWNED BY public.ntf_templat.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: pemetaan_import; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.pemetaan_import (
    id bigint NOT NULL,
    entiti_sasaran character varying(64) NOT NULL,
    nama character varying(100) NOT NULL,
    pemetaan jsonb NOT NULL,
    id_pencipta bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN pemetaan_import.pemetaan; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.pemetaan_import.pemetaan IS '{"lajur dalam fail": "medan sasaran"}';


--
-- Name: pemetaan_import_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.pemetaan_import_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: pemetaan_import_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.pemetaan_import_id_seq OWNED BY public.pemetaan_import.id;


--
-- Name: pemetaan_tuntutan; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.pemetaan_tuntutan (
    id bigint NOT NULL,
    jenis_tuntutan character varying(20) NOT NULL,
    nilai character varying(150) NOT NULL,
    peranan_id bigint,
    skop_jenis character varying(30),
    skop_nilai character varying(100),
    aktif boolean DEFAULT true NOT NULL,
    keutamaan integer DEFAULT 100 NOT NULL,
    keterangan character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: pemetaan_tuntutan_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.pemetaan_tuntutan_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: pemetaan_tuntutan_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.pemetaan_tuntutan_id_seq OWNED BY public.pemetaan_tuntutan.id;


--
-- Name: pengguna_peranan; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.pengguna_peranan (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    peranan_id bigint NOT NULL,
    sumber character varying(10) DEFAULT 'manual'::character varying NOT NULL,
    diberi_oleh bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: pengguna_peranan_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.pengguna_peranan_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: pengguna_peranan_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.pengguna_peranan_id_seq OWNED BY public.pengguna_peranan.id;


--
-- Name: pengguna_skop; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.pengguna_skop (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    jenis_skop character varying(30) NOT NULL,
    nilai_skop character varying(100) NOT NULL,
    sumber character varying(10) DEFAULT 'manual'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: pengguna_skop_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.pengguna_skop_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: pengguna_skop_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.pengguna_skop_id_seq OWNED BY public.pengguna_skop.id;


--
-- Name: penilaian; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.penilaian (
    id uuid NOT NULL,
    lot_id uuid NOT NULL,
    tarikh_penilaian date NOT NULL,
    nilai_pasaran numeric(16,2),
    nilai_setara numeric(16,2),
    kaedah character varying(50),
    penilai character varying(150),
    versi integer DEFAULT 1 NOT NULL,
    klasifikasi character varying(10) DEFAULT 'rahsia'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: peranan; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.peranan (
    id bigint NOT NULL,
    kod character varying(60) NOT NULL,
    nama character varying(255) NOT NULL,
    kategori_rfp character varying(60),
    keterangan text,
    status character varying(20) DEFAULT 'aktif'::character varying NOT NULL,
    boleh_padam boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: peranan_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.peranan_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: peranan_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.peranan_id_seq OWNED BY public.peranan.id;


--
-- Name: peranan_kebenaran; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.peranan_kebenaran (
    id bigint NOT NULL,
    peranan_id bigint NOT NULL,
    kebenaran character varying(150) NOT NULL,
    modul character varying(30) NOT NULL,
    fungsi character varying(60) NOT NULL,
    tindakan character varying(30) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: peranan_kebenaran_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.peranan_kebenaran_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: peranan_kebenaran_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.peranan_kebenaran_id_seq OWNED BY public.peranan_kebenaran.id;


--
-- Name: peranan_skop; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.peranan_skop (
    id bigint NOT NULL,
    peranan_id bigint NOT NULL,
    jenis_skop character varying(30) NOT NULL,
    nilai_skop character varying(100) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: peranan_skop_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.peranan_skop_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: peranan_skop_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.peranan_skop_id_seq OWNED BY public.peranan_skop.id;


--
-- Name: peraturan_kualiti_data; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.peraturan_kualiti_data (
    id bigint NOT NULL,
    entiti character varying(64) NOT NULL,
    medan character varying(64) NOT NULL,
    jenis_peraturan character varying(20) NOT NULL,
    ungkapan_peraturan jsonb DEFAULT '{}'::jsonb NOT NULL,
    mesej_ralat character varying(255) NOT NULL,
    tahap_keterukan character varying(10) DEFAULT 'ralat'::character varying NOT NULL,
    status_aktif boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN peraturan_kualiti_data.jenis_peraturan; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.peraturan_kualiti_data.jenis_peraturan IS 'wajib | format | julat | senarai | panjang | rujukan | unik | lengkap';


--
-- Name: COLUMN peraturan_kualiti_data.tahap_keterukan; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.peraturan_kualiti_data.tahap_keterukan IS 'ralat (menghalang) | amaran (dilaporkan sahaja)';


--
-- Name: peraturan_kualiti_data_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.peraturan_kualiti_data_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: peraturan_kualiti_data_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.peraturan_kualiti_data_id_seq OWNED BY public.peraturan_kualiti_data.id;


--
-- Name: personal_access_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.personal_access_tokens (
    id bigint NOT NULL,
    tokenable_type character varying(255) NOT NULL,
    tokenable_id bigint NOT NULL,
    name text NOT NULL,
    token character varying(64) NOT NULL,
    abilities text,
    last_used_at timestamp(0) without time zone,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.personal_access_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.personal_access_tokens_id_seq OWNED BY public.personal_access_tokens.id;


--
-- Name: polisi_keselamatan; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.polisi_keselamatan (
    kunci character varying(60) NOT NULL,
    nilai json NOT NULL,
    dikemas_kini_oleh bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: ralat_import; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ralat_import (
    id bigint NOT NULL,
    id_sesi uuid NOT NULL,
    no_baris integer NOT NULL,
    medan character varying(64),
    nilai_diberi text,
    kod_ralat character varying(50) NOT NULL,
    sebab_penolakan character varying(500) NOT NULL
);


--
-- Name: ralat_import_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ralat_import_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ralat_import_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ralat_import_id_seq OWNED BY public.ralat_import.id;


--
-- Name: recert_keputusan; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.recert_keputusan (
    id bigint NOT NULL,
    kitaran_id bigint NOT NULL,
    user_id bigint NOT NULL,
    peranan_id bigint NOT NULL,
    keputusan character varying(20) DEFAULT 'belum'::character varying NOT NULL,
    catatan character varying(255),
    disemak_oleh bigint,
    disemak_pada timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: recert_keputusan_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.recert_keputusan_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: recert_keputusan_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.recert_keputusan_id_seq OWNED BY public.recert_keputusan.id;


--
-- Name: recert_kitaran; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.recert_kitaran (
    id bigint NOT NULL,
    nama character varying(255) NOT NULL,
    bahagian character varying(100),
    tarikh_mula date NOT NULL,
    tarikh_akhir date NOT NULL,
    status character varying(20) DEFAULT 'dibuka'::character varying NOT NULL,
    dicipta_oleh bigint,
    ditutup_pada timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: recert_kitaran_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.recert_kitaran_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: recert_kitaran_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.recert_kitaran_id_seq OWNED BY public.recert_kitaran.id;


--
-- Name: ref_daerah; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ref_daerah (
    id bigint NOT NULL,
    kod character varying(20) NOT NULL,
    nama character varying(150) NOT NULL,
    keterangan text,
    susunan smallint DEFAULT '0'::smallint NOT NULL,
    aktif boolean DEFAULT true NOT NULL,
    versi integer DEFAULT 1 NOT NULL,
    sah_dari date NOT NULL,
    sah_hingga date,
    sumber character varying(255),
    sumber_url character varying(500),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    negeri_kod character varying(20) NOT NULL,
    kod_pendek character varying(4) NOT NULL,
    kod_disahkan boolean DEFAULT false NOT NULL,
    nama_lain json,
    wikidata character varying(20),
    osm_relation_id bigint,
    CONSTRAINT ref_daerah_tarikh_sah_check CHECK (((sah_hingga IS NULL) OR (sah_hingga >= sah_dari)))
);


--
-- Name: ref_daerah_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ref_daerah_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ref_daerah_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ref_daerah_id_seq OWNED BY public.ref_daerah.id;


--
-- Name: ref_jenis_dokumen; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ref_jenis_dokumen (
    id bigint NOT NULL,
    kod character varying(20) NOT NULL,
    nama character varying(150) NOT NULL,
    keterangan text,
    susunan smallint DEFAULT '0'::smallint NOT NULL,
    aktif boolean DEFAULT true NOT NULL,
    versi integer DEFAULT 1 NOT NULL,
    sah_dari date NOT NULL,
    sah_hingga date,
    sumber character varying(255),
    sumber_url character varying(500),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    klasifikasi_lalai character varying(20),
    CONSTRAINT ref_jenis_dokumen_tarikh_sah_check CHECK (((sah_hingga IS NULL) OR (sah_hingga >= sah_dari)))
);


--
-- Name: ref_jenis_dokumen_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ref_jenis_dokumen_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ref_jenis_dokumen_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ref_jenis_dokumen_id_seq OWNED BY public.ref_jenis_dokumen.id;


--
-- Name: ref_jenis_hak_milik; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ref_jenis_hak_milik (
    id bigint NOT NULL,
    kod character varying(20) NOT NULL,
    nama character varying(150) NOT NULL,
    keterangan text,
    susunan smallint DEFAULT '0'::smallint NOT NULL,
    aktif boolean DEFAULT true NOT NULL,
    versi integer DEFAULT 1 NOT NULL,
    sah_dari date NOT NULL,
    sah_hingga date,
    sumber character varying(255),
    sumber_url character varying(500),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    singkatan character varying(10) NOT NULL,
    peringkat character varying(20) NOT NULL,
    sementara boolean DEFAULT false NOT NULL,
    hak_milik boolean DEFAULT true NOT NULL,
    rujukan_undang character varying(100),
    CONSTRAINT ref_jenis_hak_milik_tarikh_sah_check CHECK (((sah_hingga IS NULL) OR (sah_hingga >= sah_dari)))
);


--
-- Name: ref_jenis_hak_milik_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ref_jenis_hak_milik_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ref_jenis_hak_milik_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ref_jenis_hak_milik_id_seq OWNED BY public.ref_jenis_hak_milik.id;


--
-- Name: ref_kategori_aduan; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ref_kategori_aduan (
    id bigint NOT NULL,
    kod character varying(20) NOT NULL,
    nama character varying(150) NOT NULL,
    keterangan text,
    susunan smallint DEFAULT '0'::smallint NOT NULL,
    aktif boolean DEFAULT true NOT NULL,
    versi integer DEFAULT 1 NOT NULL,
    sah_dari date NOT NULL,
    sah_hingga date,
    sumber character varying(255),
    sumber_url character varying(500),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT ref_kategori_aduan_tarikh_sah_check CHECK (((sah_hingga IS NULL) OR (sah_hingga >= sah_dari)))
);


--
-- Name: ref_kategori_aduan_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ref_kategori_aduan_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ref_kategori_aduan_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ref_kategori_aduan_id_seq OWNED BY public.ref_kategori_aduan.id;


--
-- Name: ref_kategori_guna_tanah; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ref_kategori_guna_tanah (
    id bigint NOT NULL,
    kod character varying(20) NOT NULL,
    nama character varying(150) NOT NULL,
    keterangan text,
    susunan smallint DEFAULT '0'::smallint NOT NULL,
    aktif boolean DEFAULT true NOT NULL,
    versi integer DEFAULT 1 NOT NULL,
    sah_dari date NOT NULL,
    sah_hingga date,
    sumber character varying(255),
    sumber_url character varying(500),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    rujukan_undang character varying(100),
    CONSTRAINT ref_kategori_guna_tanah_tarikh_sah_check CHECK (((sah_hingga IS NULL) OR (sah_hingga >= sah_dari)))
);


--
-- Name: ref_kategori_guna_tanah_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ref_kategori_guna_tanah_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ref_kategori_guna_tanah_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ref_kategori_guna_tanah_id_seq OWNED BY public.ref_kategori_guna_tanah.id;


--
-- Name: ref_klasifikasi_data; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ref_klasifikasi_data (
    id bigint NOT NULL,
    kod character varying(20) NOT NULL,
    nama character varying(150) NOT NULL,
    keterangan text,
    susunan smallint DEFAULT '0'::smallint NOT NULL,
    aktif boolean DEFAULT true NOT NULL,
    versi integer DEFAULT 1 NOT NULL,
    sah_dari date NOT NULL,
    sah_hingga date,
    sumber character varying(255),
    sumber_url character varying(500),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    aras smallint NOT NULL,
    CONSTRAINT ref_klasifikasi_data_tarikh_sah_check CHECK (((sah_hingga IS NULL) OR (sah_hingga >= sah_dari)))
);


--
-- Name: ref_klasifikasi_data_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ref_klasifikasi_data_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ref_klasifikasi_data_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ref_klasifikasi_data_id_seq OWNED BY public.ref_klasifikasi_data.id;


--
-- Name: ref_mukim; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ref_mukim (
    id bigint NOT NULL,
    kod character varying(20) NOT NULL,
    nama character varying(150) NOT NULL,
    keterangan text,
    susunan smallint DEFAULT '0'::smallint NOT NULL,
    aktif boolean DEFAULT true NOT NULL,
    versi integer DEFAULT 1 NOT NULL,
    sah_dari date NOT NULL,
    sah_hingga date,
    sumber character varying(255),
    sumber_url character varying(500),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    daerah_kod character varying(20) NOT NULL,
    kod_pendek character varying(4) NOT NULL,
    kod_disahkan boolean DEFAULT false NOT NULL,
    nama_lain json,
    CONSTRAINT ref_mukim_tarikh_sah_check CHECK (((sah_hingga IS NULL) OR (sah_hingga >= sah_dari)))
);


--
-- Name: ref_mukim_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ref_mukim_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ref_mukim_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ref_mukim_id_seq OWNED BY public.ref_mukim.id;


--
-- Name: ref_negeri; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ref_negeri (
    id bigint NOT NULL,
    kod character varying(20) NOT NULL,
    nama character varying(150) NOT NULL,
    keterangan text,
    susunan smallint DEFAULT '0'::smallint NOT NULL,
    aktif boolean DEFAULT true NOT NULL,
    versi integer DEFAULT 1 NOT NULL,
    sah_dari date NOT NULL,
    sah_hingga date,
    sumber character varying(255),
    sumber_url character varying(500),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    kod_iso character varying(10),
    jenis character varying(30) DEFAULT 'negeri'::character varying NOT NULL,
    CONSTRAINT ref_negeri_tarikh_sah_check CHECK (((sah_hingga IS NULL) OR (sah_hingga >= sah_dari)))
);


--
-- Name: ref_negeri_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ref_negeri_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ref_negeri_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ref_negeri_id_seq OWNED BY public.ref_negeri.id;


--
-- Name: ref_pbt; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ref_pbt (
    id bigint NOT NULL,
    kod character varying(20) NOT NULL,
    nama character varying(150) NOT NULL,
    keterangan text,
    susunan smallint DEFAULT '0'::smallint NOT NULL,
    aktif boolean DEFAULT true NOT NULL,
    versi integer DEFAULT 1 NOT NULL,
    sah_dari date NOT NULL,
    sah_hingga date,
    sumber character varying(255),
    sumber_url character varying(500),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    singkatan character varying(20) NOT NULL,
    jenis character varying(30) NOT NULL,
    negeri_kod character varying(20) NOT NULL,
    daerah_kod json,
    wikidata character varying(20),
    osm_relation_id bigint,
    CONSTRAINT ref_pbt_tarikh_sah_check CHECK (((sah_hingga IS NULL) OR (sah_hingga >= sah_dari)))
);


--
-- Name: ref_pbt_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ref_pbt_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ref_pbt_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ref_pbt_id_seq OWNED BY public.ref_pbt.id;


--
-- Name: ref_sempadan; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ref_sempadan (
    id bigint NOT NULL,
    jenis character varying(20) NOT NULL,
    kod character varying(20) NOT NULL,
    nama character varying(150) NOT NULL,
    geometri public.geometry(MultiPolygon,4326) NOT NULL,
    anggaran boolean DEFAULT false NOT NULL,
    toleransi_permudah numeric(10,6),
    luas_km2 numeric(12,3),
    sumber character varying(255) NOT NULL,
    sumber_url character varying(500),
    lesen character varying(100),
    osm_id bigint,
    diambil_pada date,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: ref_sempadan_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ref_sempadan_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ref_sempadan_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ref_sempadan_id_seq OWNED BY public.ref_sempadan.id;


--
-- Name: ref_tempat_ibadat; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ref_tempat_ibadat (
    id bigint NOT NULL,
    osm_jenis character varying(10) NOT NULL,
    osm_id bigint NOT NULL,
    nama character varying(255),
    jenis character varying(20) NOT NULL,
    daerah_kod character varying(20),
    mukim_kod character varying(20),
    geometri public.geometry(Point,4326) NOT NULL,
    tag json,
    sumber character varying(255) NOT NULL,
    lesen character varying(100) NOT NULL,
    diambil_pada date NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: ref_tempat_ibadat_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ref_tempat_ibadat_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ref_tempat_ibadat_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ref_tempat_ibadat_id_seq OWNED BY public.ref_tempat_ibadat.id;


--
-- Name: rekod_arkib; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.rekod_arkib (
    id uuid NOT NULL,
    entiti character varying(64) NOT NULL,
    id_rekod character varying(64) NOT NULL,
    id_jadual_retensi bigint,
    status_arkib character varying(20) NOT NULL,
    id_pemohon bigint,
    id_pelulus_arkib bigint,
    id_aliran_kerja character varying(64),
    tarikh_arkib timestamp(0) without time zone,
    lokasi_storan_arkib character varying(100) DEFAULT 'pangkalan_data:lot_tanah(status_lot=arkib)'::character varying NOT NULL,
    tarikh_layak_lupus date,
    id_pemulih bigint,
    tarikh_pulih timestamp(0) without time zone,
    sebab text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN rekod_arkib.status_arkib; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.rekod_arkib.status_arkib IS 'dicadang | menunggu_kelulusan | diarkib | ditolak | dipulih';


--
-- Name: sesi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sesi (
    id uuid NOT NULL,
    session_id character varying(255) NOT NULL,
    user_id bigint NOT NULL,
    kaedah_log_masuk character varying(20),
    alamat_ip character varying(45),
    user_agent text,
    sidik_peranti character varying(64),
    tarikh_mula timestamp(0) without time zone NOT NULL,
    tarikh_aktiviti_akhir timestamp(0) without time zone NOT NULL,
    tarikh_tamat timestamp(0) without time zone,
    kaedah_tamat character varying(20),
    ditamatkan_oleh bigint
);


--
-- Name: sesi_import; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sesi_import (
    id uuid NOT NULL,
    entiti_sasaran character varying(64) NOT NULL,
    nama_fail character varying(255) NOT NULL,
    format_fail character varying(10) NOT NULL,
    id_pengguna bigint,
    pemetaan jsonb,
    lajur_fail jsonb,
    bil_baris_dibaca integer DEFAULT 0 NOT NULL,
    bil_sah integer DEFAULT 0 NOT NULL,
    bil_berjaya integer DEFAULT 0 NOT NULL,
    bil_ditolak integer DEFAULT 0 NOT NULL,
    bil_diabaikan integer DEFAULT 0 NOT NULL,
    lokasi_fail_asal character varying(64),
    catatan_fail_asal character varying(255),
    status_sesi character varying(20) DEFAULT 'pratonton'::character varying NOT NULL,
    tarikh_komit timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN sesi_import.lokasi_fail_asal; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.sesi_import.lokasi_fail_asal IS 'ID dokumen DMS';


--
-- Name: COLUMN sesi_import.status_sesi; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.sesi_import.status_sesi IS 'pratonton | dikomit | dibatalkan';


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


--
-- Name: settings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.settings (
    id bigint NOT NULL,
    key character varying(255) NOT NULL,
    value text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: settings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.settings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: settings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.settings_id_seq OWNED BY public.settings.id;


--
-- Name: staging_hak_milik; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.staging_hak_milik (
    id uuid NOT NULL,
    migrasi_larian_id uuid NOT NULL,
    staging_lot_tanah_id uuid NOT NULL,
    no_hak_milik character varying(30) NOT NULL,
    jenis_hak_milik_kod character varying(20) NOT NULL,
    tarikh_daftar date,
    tarikh_tamat date,
    tempoh_pajakan_tahun smallint,
    sekatan_kepentingan text,
    lokasi_simpanan_fizikal character varying(150),
    sintetik boolean DEFAULT false NOT NULL,
    sumber_sistem character varying(100) NOT NULL,
    fail_asal character varying(255) NOT NULL,
    kunci_asal character varying(100) NOT NULL,
    dimuat_pada timestamp(0) without time zone NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: staging_lot_tanah; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.staging_lot_tanah (
    id uuid NOT NULL,
    migrasi_larian_id uuid NOT NULL,
    no_lot character varying(30) NOT NULL,
    jenis_lot character varying(10) NOT NULL,
    negeri_kod character varying(20) NOT NULL,
    daerah_kod character varying(20) NOT NULL,
    mukim_kod character varying(20) NOT NULL,
    keluasan numeric(14,4) NOT NULL,
    unit_keluasan character varying(20) NOT NULL,
    kategori_guna_tanah_kod character varying(20),
    zoning character varying(100),
    syarat_nyata text,
    sekatan_kepentingan text,
    wakaf boolean DEFAULT false NOT NULL,
    jenis_wakaf character varying(10),
    baitulmal boolean DEFAULT false NOT NULL,
    geometri public.geometry(Polygon,4326),
    sintetik boolean DEFAULT false NOT NULL,
    sumber_sistem character varying(100) NOT NULL,
    fail_asal character varying(255) NOT NULL,
    kunci_asal character varying(100) NOT NULL,
    dimuat_pada timestamp(0) without time zone NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT staging_lot_tanah_keluasan_check CHECK ((keluasan > (0)::numeric))
);


--
-- Name: staging_pemilik; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.staging_pemilik (
    id uuid NOT NULL,
    migrasi_larian_id uuid NOT NULL,
    staging_hak_milik_id uuid NOT NULL,
    peranan character varying(30) NOT NULL,
    jenis_pemilik character varying(20) NOT NULL,
    nama character varying(200) NOT NULL,
    no_pengenalan character varying(20),
    bahagian character varying(20),
    sintetik boolean DEFAULT false NOT NULL,
    sumber_sistem character varying(100) NOT NULL,
    fail_asal character varying(255) NOT NULL,
    kunci_asal character varying(100) NOT NULL,
    dimuat_pada timestamp(0) without time zone NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT staging_pemilik_ic_sintetik_check CHECK (((sintetik = false) OR (no_pengenalan IS NULL) OR ((no_pengenalan)::text ~ '^[0-9]{6}-00-[0-9]{4}$'::text)))
);


--
-- Name: status_akaun_log; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.status_akaun_log (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    status_lama character varying(20) NOT NULL,
    status_baru character varying(20) NOT NULL,
    sebab character varying(255),
    dilakukan_oleh bigint,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: status_akaun_log_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.status_akaun_log_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: status_akaun_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.status_akaun_log_id_seq OWNED BY public.status_akaun_log.id;


--
-- Name: tugasan_kualiti_data; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tugasan_kualiti_data (
    id bigint NOT NULL,
    entiti character varying(64) NOT NULL,
    id_rekod character varying(64) NOT NULL,
    kod_isu character varying(50) NOT NULL,
    keterangan text NOT NULL,
    tahap_keterukan character varying(10) DEFAULT 'ralat'::character varying NOT NULL,
    id_stewardship bigint,
    id_data_steward bigint,
    kod_daerah character varying(4),
    status character varying(20) DEFAULT 'terbuka'::character varying NOT NULL,
    tindakan_pembetulan text,
    diselesaikan_oleh bigint,
    diselesaikan_pada timestamp(0) without time zone,
    sumber character varying(30) DEFAULT 'semakan'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN tugasan_kualiti_data.status; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.tugasan_kualiti_data.status IS 'terbuka | selesai | ditolak';


--
-- Name: COLUMN tugasan_kualiti_data.sumber; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.tugasan_kualiti_data.sumber IS 'semakan | import | laporan_berjadual';


--
-- Name: tugasan_kualiti_data_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.tugasan_kualiti_data_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tugasan_kualiti_data_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.tugasan_kualiti_data_id_seq OWNED BY public.tugasan_kualiti_data.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    email_verified_at timestamp(0) without time zone,
    password character varying(255),
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    role character varying(255) DEFAULT 'user'::character varying NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    oidc_sub character varying(255),
    status_akaun character varying(20) DEFAULT 'aktif'::character varying NOT NULL,
    jenis_pengesahan character varying(10) DEFAULT 'dalaman'::character varying NOT NULL,
    no_pekerja character varying(50),
    bahagian character varying(100),
    unit character varying(100),
    jawatan character varying(150),
    tarikh_mula_capaian date,
    tarikh_tamat_capaian date,
    tarikh_log_masuk_akhir timestamp(0) without time zone,
    tarikh_diaktifkan_semula timestamp(0) without time zone,
    mfa_didaftar boolean DEFAULT false NOT NULL,
    kata_laluan_ditukar_pada timestamp(0) without time zone,
    wajib_tukar_kata_laluan boolean DEFAULT false NOT NULL,
    cubaan_gagal smallint DEFAULT '0'::smallint NOT NULL,
    cubaan_gagal_pertama timestamp(0) without time zone,
    dikunci_hingga timestamp(0) without time zone
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: versi_rekod_induk; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.versi_rekod_induk (
    id uuid NOT NULL,
    entiti character varying(64) NOT NULL,
    id_rekod character varying(64) NOT NULL,
    no_versi integer,
    nilai_sebelum jsonb,
    nilai_selepas jsonb NOT NULL,
    kritikal boolean DEFAULT false NOT NULL,
    id_pengguna bigint,
    justifikasi text,
    id_pengesah_kedua bigint,
    status_pengesahan character varying(20) NOT NULL,
    id_aliran_kerja character varying(64),
    catatan_pengesahan text,
    tarikh_pengesahan timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN versi_rekod_induk.no_versi; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.versi_rekod_induk.no_versi IS 'Diberi apabila perubahan berkuat kuasa';


--
-- Name: COLUMN versi_rekod_induk.status_pengesahan; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.versi_rekod_induk.status_pengesahan IS 'berkuat_kuasa | menunggu | lulus | tolak | ditarik_balik | dibatalkan';


--
-- Name: vw_kualiti_data_rekod_tidak_lengkap; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.vw_kualiti_data_rekod_tidak_lengkap AS
 SELECT lot_id,
    no_lot,
    kod_mukim,
    kod_daerah,
    bahagian_pemilik,
    id_data_steward,
    medan_tidak_lengkap
   FROM ( SELECT l.id AS lot_id,
            l.no_lot,
            l.kod_mukim,
            l.kod_daerah,
            l.bahagian_pemilik,
            l.id_data_steward,
            array_remove(ARRAY[
                CASE
                    WHEN (l.keluasan IS NULL) THEN 'keluasan'::text
                    ELSE NULL::text
                END,
                CASE
                    WHEN (l.kategori_guna_tanah IS NULL) THEN 'kategori_guna_tanah'::text
                    ELSE NULL::text
                END,
                CASE
                    WHEN (l.bahagian_pemilik IS NULL) THEN 'bahagian_pemilik'::text
                    ELSE NULL::text
                END,
                CASE
                    WHEN (l.id_data_owner IS NULL) THEN 'id_data_owner'::text
                    ELSE NULL::text
                END,
                CASE
                    WHEN (l.id_data_steward IS NULL) THEN 'id_data_steward'::text
                    ELSE NULL::text
                END,
                CASE
                    WHEN (l.koordinat_pusat IS NULL) THEN 'koordinat_pusat'::text
                    ELSE NULL::text
                END], NULL::text) AS medan_tidak_lengkap
           FROM public.lot_tanah l
          WHERE ((l.deleted_at IS NULL) AND ((l.status_lot)::text <> 'arkib'::text))) t
  WHERE (cardinality(medan_tidak_lengkap) > 0);


--
-- Name: vw_lot_spatial_belum_disahkan; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.vw_lot_spatial_belum_disahkan AS
 SELECT l.id AS lot_id,
    l.no_lot,
    l.kod_mukim,
    l.kod_daerah,
    l.bahagian_pemilik,
    l.id_data_steward,
    ds.id AS id_spatial,
    COALESCE(ds.status_pengesahan, 'tiada_geometri'::character varying) AS status_pengesahan,
    ds.sumber_data,
    ds.tarikh_perolehan_data
   FROM (public.lot_tanah l
     LEFT JOIN public.data_spatial ds ON (((ds.lot_id = l.id) AND ds.semasa)))
  WHERE ((l.deleted_at IS NULL) AND ((l.status_lot)::text <> 'arkib'::text) AND ((ds.id IS NULL) OR ((ds.status_pengesahan)::text = ANY ((ARRAY['belum_disahkan'::character varying, 'bertindih'::character varying])::text[]))));


--
-- Name: vw_profil_hartanah_360; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.vw_profil_hartanah_360 AS
 SELECT l.id AS lot_id,
    l.no_lot,
    l.kod_negeri,
    l.kod_daerah,
    l.kod_mukim,
    l.keluasan,
    l.unit_keluasan,
    l.kategori_guna_tanah,
    l.zoning,
    l.bahagian_pemilik,
    l.status_lot,
    l.klasifikasi,
    l.versi_rekod,
    public.st_y(l.koordinat_pusat) AS lat,
    public.st_x(l.koordinat_pusat) AS lng,
    ds.status_pengesahan AS status_pengesahan_spatial,
    ds.keluasan_dikira,
    ds.sumber_data AS sumber_data_spatial,
    ( SELECT count(*) AS count
           FROM public.penilaian p
          WHERE ((p.lot_id = l.id) AND (p.deleted_at IS NULL))) AS bil_penilaian,
    ( SELECT count(*) AS count
           FROM public.kontrak_pajakan k
          WHERE ((k.lot_id = l.id) AND (k.deleted_at IS NULL))) AS bil_kontrak_pajakan,
    ( SELECT count(*) AS count
           FROM public.cukai c
          WHERE ((c.lot_id = l.id) AND (c.deleted_at IS NULL))) AS bil_cukai,
    ( SELECT count(*) AS count
           FROM public.aduan a
          WHERE ((a.lot_id = l.id) AND (a.deleted_at IS NULL))) AS bil_aduan,
    ( SELECT count(*) AS count
           FROM public.kes_mahkamah_lot km
          WHERE (km.lot_id = l.id)) AS bil_kes_mahkamah,
    l.updated_at
   FROM (public.lot_tanah l
     LEFT JOIN public.data_spatial ds ON (((ds.lot_id = l.id) AND ds.semasa)))
  WHERE (l.deleted_at IS NULL);


--
-- Name: wf_cuti_umum; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.wf_cuti_umum (
    id bigint NOT NULL,
    tarikh date NOT NULL,
    nama character varying(255) NOT NULL,
    negeri character varying(30) DEFAULT 'selangor'::character varying NOT NULL,
    jenis character varying(20) DEFAULT 'persekutuan'::character varying NOT NULL,
    sumber character varying(255),
    catatan character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: wf_cuti_umum_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.wf_cuti_umum_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: wf_cuti_umum_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.wf_cuti_umum_id_seq OWNED BY public.wf_cuti_umum.id;


--
-- Name: wf_definisi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.wf_definisi (
    id bigint NOT NULL,
    kod_aliran character varying(100) NOT NULL,
    nama character varying(255) NOT NULL,
    keterangan text,
    modul character varying(20) NOT NULL,
    jenis_transaksi character varying(100) NOT NULL,
    no_versi integer NOT NULL,
    status character varying(30) DEFAULT 'draf'::character varying NOT NULL,
    templat boolean DEFAULT false NOT NULL,
    tarikh_berkuat_kuasa timestamp(0) without time zone,
    id_pencipta bigint,
    kaedah_cipta character varying(20) DEFAULT 'manual'::character varying NOT NULL,
    id_asal bigint,
    diminta_aktif_oleh bigint,
    diminta_aktif_pada timestamp(0) without time zone,
    diluluskan_oleh bigint,
    diluluskan_pada timestamp(0) without time zone,
    susun_atur jsonb,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: wf_definisi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.wf_definisi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: wf_definisi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.wf_definisi_id_seq OWNED BY public.wf_definisi.id;


--
-- Name: wf_definisi_log; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.wf_definisi_log (
    id bigint NOT NULL,
    id_definisi bigint NOT NULL,
    id_pengguna bigint,
    tindakan character varying(40) NOT NULL,
    kaedah character varying(30) NOT NULL,
    arahan text,
    butiran jsonb,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: wf_definisi_log_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.wf_definisi_log_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: wf_definisi_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.wf_definisi_log_id_seq OWNED BY public.wf_definisi_log.id;


--
-- Name: wf_eskalasi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.wf_eskalasi (
    id bigint NOT NULL,
    id_peringkat bigint NOT NULL,
    aras integer NOT NULL,
    tempoh_nilai integer DEFAULT 0 NOT NULL,
    tempoh_unit character varying(20) DEFAULT 'hari_bekerja'::character varying NOT NULL,
    jenis_penerima character varying(20) NOT NULL,
    nilai_penerima character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: wf_eskalasi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.wf_eskalasi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: wf_eskalasi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.wf_eskalasi_id_seq OWNED BY public.wf_eskalasi.id;


--
-- Name: wf_eskalasi_log; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.wf_eskalasi_log (
    id bigint NOT NULL,
    id_tugasan bigint NOT NULL,
    aras integer NOT NULL,
    penerima jsonb NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: wf_eskalasi_log_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.wf_eskalasi_log_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: wf_eskalasi_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.wf_eskalasi_log_id_seq OWNED BY public.wf_eskalasi_log.id;


--
-- Name: wf_instans; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.wf_instans (
    id uuid NOT NULL,
    id_definisi bigint NOT NULL,
    no_versi_definisi integer NOT NULL,
    kod_aliran character varying(100) NOT NULL,
    modul character varying(20) NOT NULL,
    jenis_subjek character varying(100) NOT NULL,
    id_rekod character varying(100) NOT NULL,
    tajuk character varying(255),
    konteks jsonb,
    kod_daerah character varying(20),
    pautan character varying(255),
    id_pemohon bigint NOT NULL,
    status character varying(20) DEFAULT 'berjalan'::character varying NOT NULL,
    keadaan character varying(30),
    peringkat_semasa bigint,
    kitaran integer DEFAULT 0 NOT NULL,
    laluan jsonb,
    tarikh_mula timestamp(0) without time zone NOT NULL,
    tarikh_tamat timestamp(0) without time zone,
    ulasan_keputusan text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: wf_kumpulan; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.wf_kumpulan (
    id bigint NOT NULL,
    kod character varying(50) NOT NULL,
    nama character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: wf_kumpulan_ahli; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.wf_kumpulan_ahli (
    id_kumpulan bigint NOT NULL,
    id_pengguna bigint NOT NULL
);


--
-- Name: wf_kumpulan_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.wf_kumpulan_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: wf_kumpulan_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.wf_kumpulan_id_seq OWNED BY public.wf_kumpulan.id;


--
-- Name: wf_pelulus; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.wf_pelulus (
    id bigint NOT NULL,
    id_peringkat bigint NOT NULL,
    jenis character varying(20) NOT NULL,
    nilai character varying(100),
    label character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: wf_pelulus_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.wf_pelulus_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: wf_pelulus_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.wf_pelulus_id_seq OWNED BY public.wf_pelulus.id;


--
-- Name: wf_pemangku; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.wf_pemangku (
    id bigint NOT NULL,
    id_pengguna bigint NOT NULL,
    id_pemangku bigint NOT NULL,
    tarikh_mula date NOT NULL,
    tarikh_tamat date NOT NULL,
    sebab character varying(255),
    dicipta_oleh bigint,
    dibatalkan_pada timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: wf_pemangku_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.wf_pemangku_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: wf_pemangku_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.wf_pemangku_id_seq OWNED BY public.wf_pemangku.id;


--
-- Name: wf_peralihan; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.wf_peralihan (
    id bigint NOT NULL,
    id_definisi bigint NOT NULL,
    dari character varying(50) NOT NULL,
    ke character varying(50) NOT NULL,
    keutamaan integer DEFAULT 0 NOT NULL,
    logik character varying(10) DEFAULT 'semua'::character varying NOT NULL,
    label character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: wf_peralihan_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.wf_peralihan_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: wf_peralihan_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.wf_peralihan_id_seq OWNED BY public.wf_peralihan.id;


--
-- Name: wf_peringkat; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.wf_peringkat (
    id bigint NOT NULL,
    id_definisi bigint NOT NULL,
    kod character varying(50) NOT NULL,
    no_urutan integer NOT NULL,
    nama_peringkat character varying(255) NOT NULL,
    mod_kelulusan character varying(20) DEFAULT 'mana_mana'::character varying NOT NULL,
    kuorum integer,
    sla_nilai integer,
    sla_unit character varying(20) DEFAULT 'hari_bekerja'::character varying NOT NULL,
    perlu_mfa boolean DEFAULT false NOT NULL,
    perlu_justifikasi boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: wf_peringkat_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.wf_peringkat_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: wf_peringkat_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.wf_peringkat_id_seq OWNED BY public.wf_peringkat.id;


--
-- Name: wf_pindah_versi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.wf_pindah_versi (
    id bigint NOT NULL,
    id_instans uuid NOT NULL,
    id_definisi_asal bigint NOT NULL,
    id_definisi_baru bigint NOT NULL,
    sebab character varying(255) NOT NULL,
    status character varying(20) DEFAULT 'menunggu'::character varying NOT NULL,
    diminta_oleh bigint NOT NULL,
    diputuskan_oleh bigint,
    diputuskan_pada timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: wf_pindah_versi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.wf_pindah_versi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: wf_pindah_versi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.wf_pindah_versi_id_seq OWNED BY public.wf_pindah_versi.id;


--
-- Name: wf_sejarah; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.wf_sejarah (
    id bigint NOT NULL,
    id_instans uuid NOT NULL,
    id_tugasan bigint,
    id_peringkat bigint,
    nama_peringkat character varying(255),
    peristiwa character varying(30) NOT NULL,
    id_pengguna bigint,
    id_bagi_pihak bigint,
    peranan_semasa jsonb,
    justifikasi text,
    alamat_ip character varying(45),
    mfa_pada timestamp(0) without time zone,
    butiran jsonb,
    tarikh_masa timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: wf_sejarah_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.wf_sejarah_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: wf_sejarah_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.wf_sejarah_id_seq OWNED BY public.wf_sejarah.id;


--
-- Name: wf_susulan; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.wf_susulan (
    id bigint NOT NULL,
    id_definisi bigint NOT NULL,
    bila character varying(20) DEFAULT 'lulus'::character varying NOT NULL,
    tajuk character varying(255) NOT NULL,
    jenis_penerima character varying(20) NOT NULL,
    nilai_penerima character varying(100),
    sla_nilai integer,
    sla_unit character varying(20) DEFAULT 'hari_bekerja'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: wf_susulan_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.wf_susulan_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: wf_susulan_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.wf_susulan_id_seq OWNED BY public.wf_susulan.id;


--
-- Name: wf_syarat; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.wf_syarat (
    id bigint NOT NULL,
    id_peralihan bigint NOT NULL,
    medan character varying(100) NOT NULL,
    operator character varying(20) NOT NULL,
    nilai jsonb,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: wf_syarat_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.wf_syarat_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: wf_syarat_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.wf_syarat_id_seq OWNED BY public.wf_syarat.id;


--
-- Name: wf_tugasan; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.wf_tugasan (
    id bigint NOT NULL,
    id_instans uuid NOT NULL,
    id_peringkat bigint,
    jenis character varying(20) DEFAULT 'kelulusan'::character varying NOT NULL,
    tajuk character varying(255),
    kitaran integer DEFAULT 0 NOT NULL,
    id_pelulus bigint,
    jenis_penerima character varying(20) NOT NULL,
    nilai_penerima character varying(100),
    penerima_kunci character varying(130) NOT NULL,
    id_pelulus_asal bigint,
    status character varying(20) DEFAULT 'menunggu'::character varying NOT NULL,
    keputusan character varying(20),
    tarikh_akhir_sla timestamp(0) without time zone,
    ditangguh_hingga timestamp(0) without time zone,
    aras_eskalasi integer DEFAULT 0 NOT NULL,
    amaran_sla_pada timestamp(0) without time zone,
    diselesaikan_oleh bigint,
    diselesaikan_pada timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: wf_tugasan_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.wf_tugasan_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: wf_tugasan_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.wf_tugasan_id_seq OWNED BY public.wf_tugasan.id;


--
-- Name: adm_indeks_carian id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_indeks_carian ALTER COLUMN id SET DEFAULT nextval('public.adm_indeks_carian_id_seq'::regclass);


--
-- Name: adm_integrasi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_integrasi ALTER COLUMN id SET DEFAULT nextval('public.adm_integrasi_id_seq'::regclass);


--
-- Name: adm_kesihatan id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_kesihatan ALTER COLUMN id SET DEFAULT nextval('public.adm_kesihatan_id_seq'::regclass);


--
-- Name: adm_kod_rujukan id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_kod_rujukan ALTER COLUMN id SET DEFAULT nextval('public.adm_kod_rujukan_id_seq'::regclass);


--
-- Name: adm_kod_rujukan_sejarah id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_kod_rujukan_sejarah ALTER COLUMN id SET DEFAULT nextval('public.adm_kod_rujukan_sejarah_id_seq'::regclass);


--
-- Name: adm_konfig_kelulusan id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_konfig_kelulusan ALTER COLUMN id SET DEFAULT nextval('public.adm_konfig_kelulusan_id_seq'::regclass);


--
-- Name: adm_log_integrasi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_log_integrasi ALTER COLUMN id SET DEFAULT nextval('public.adm_log_integrasi_id_seq'::regclass);


--
-- Name: adm_log_ralat id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_log_ralat ALTER COLUMN id SET DEFAULT nextval('public.adm_log_ralat_id_seq'::regclass);


--
-- Name: adm_parameter id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_parameter ALTER COLUMN id SET DEFAULT nextval('public.adm_parameter_id_seq'::regclass);


--
-- Name: adm_rahsia id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_rahsia ALTER COLUMN id SET DEFAULT nextval('public.adm_rahsia_id_seq'::regclass);


--
-- Name: adm_sinonim_carian id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_sinonim_carian ALTER COLUMN id SET DEFAULT nextval('public.adm_sinonim_carian_id_seq'::regclass);


--
-- Name: adm_templat id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_templat ALTER COLUMN id SET DEFAULT nextval('public.adm_templat_id_seq'::regclass);


--
-- Name: adm_templat_versi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_templat_versi ALTER COLUMN id SET DEFAULT nextval('public.adm_templat_versi_id_seq'::regclass);


--
-- Name: adm_tugasan_berjadual id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_tugasan_berjadual ALTER COLUMN id SET DEFAULT nextval('public.adm_tugasan_berjadual_id_seq'::regclass);


--
-- Name: adm_tugasan_larian id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_tugasan_larian ALTER COLUMN id SET DEFAULT nextval('public.adm_tugasan_larian_id_seq'::regclass);


--
-- Name: amaran_keselamatan id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.amaran_keselamatan ALTER COLUMN id SET DEFAULT nextval('public.amaran_keselamatan_id_seq'::regclass);


--
-- Name: audit_eksport id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_eksport ALTER COLUMN id SET DEFAULT nextval('public.audit_eksport_id_seq'::regclass);


--
-- Name: audit_integriti_semakan id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_integriti_semakan ALTER COLUMN id SET DEFAULT nextval('public.audit_integriti_semakan_id_seq'::regclass);


--
-- Name: audit_perubahan_medan id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_perubahan_medan ALTER COLUMN id SET DEFAULT nextval('public.audit_perubahan_medan_id_seq'::regclass);


--
-- Name: audit_rekod id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_rekod ALTER COLUMN id SET DEFAULT nextval('public.audit_rekod_id_seq'::regclass);


--
-- Name: audit_retensi_polisi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_retensi_polisi ALTER COLUMN id SET DEFAULT nextval('public.audit_retensi_polisi_id_seq'::regclass);


--
-- Name: baris_import id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.baris_import ALTER COLUMN id SET DEFAULT nextval('public.baris_import_id_seq'::regclass);


--
-- Name: cadangan_duplikasi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cadangan_duplikasi ALTER COLUMN id SET DEFAULT nextval('public.cadangan_duplikasi_id_seq'::regclass);


--
-- Name: daftar_stewardship_data id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.daftar_stewardship_data ALTER COLUMN id SET DEFAULT nextval('public.daftar_stewardship_data_id_seq'::regclass);


--
-- Name: data_spatial id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_spatial ALTER COLUMN id SET DEFAULT nextval('public.data_spatial_id_seq'::regclass);


--
-- Name: dokumen_arkib id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dokumen_arkib ALTER COLUMN id SET DEFAULT nextval('public.dokumen_arkib_id_seq'::regclass);


--
-- Name: dokumen_imbasan id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dokumen_imbasan ALTER COLUMN id SET DEFAULT nextval('public.dokumen_imbasan_id_seq'::regclass);


--
-- Name: dokumen_kategori id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dokumen_kategori ALTER COLUMN id SET DEFAULT nextval('public.dokumen_kategori_id_seq'::regclass);


--
-- Name: dokumen_kebenaran id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dokumen_kebenaran ALTER COLUMN id SET DEFAULT nextval('public.dokumen_kebenaran_id_seq'::regclass);


--
-- Name: dokumen_kuarantin id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dokumen_kuarantin ALTER COLUMN id SET DEFAULT nextval('public.dokumen_kuarantin_id_seq'::regclass);


--
-- Name: dokumen_medan_metadata id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dokumen_medan_metadata ALTER COLUMN id SET DEFAULT nextval('public.dokumen_medan_metadata_id_seq'::regclass);


--
-- Name: dokumen_pelupusan id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dokumen_pelupusan ALTER COLUMN id SET DEFAULT nextval('public.dokumen_pelupusan_id_seq'::regclass);


--
-- Name: dokumen_versi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dokumen_versi ALTER COLUMN id SET DEFAULT nextval('public.dokumen_versi_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: jadual_retensi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jadual_retensi ALTER COLUMN id SET DEFAULT nextval('public.jadual_retensi_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: kamus_data id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kamus_data ALTER COLUMN id SET DEFAULT nextval('public.kamus_data_id_seq'::regclass);


--
-- Name: kata_laluan_sejarah id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kata_laluan_sejarah ALTER COLUMN id SET DEFAULT nextval('public.kata_laluan_sejarah_id_seq'::regclass);


--
-- Name: klasifikasi_data id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.klasifikasi_data ALTER COLUMN id SET DEFAULT nextval('public.klasifikasi_data_id_seq'::regclass);


--
-- Name: kunci_api_pro id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kunci_api_pro ALTER COLUMN id SET DEFAULT nextval('public.kunci_api_pro_id_seq'::regclass);


--
-- Name: log_akses_profil id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.log_akses_profil ALTER COLUMN id SET DEFAULT nextval('public.log_akses_profil_id_seq'::regclass);


--
-- Name: log_log_masuk id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.log_log_masuk ALTER COLUMN id SET DEFAULT nextval('public.log_log_masuk_id_seq'::regclass);


--
-- Name: log_penggunaan_api id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.log_penggunaan_api ALTER COLUMN id SET DEFAULT nextval('public.log_penggunaan_api_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: ntf_eskalasi_log id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_eskalasi_log ALTER COLUMN id SET DEFAULT nextval('public.ntf_eskalasi_log_id_seq'::regclass);


--
-- Name: ntf_jenis id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_jenis ALTER COLUMN id SET DEFAULT nextval('public.ntf_jenis_id_seq'::regclass);


--
-- Name: ntf_keutamaan_pengguna id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_keutamaan_pengguna ALTER COLUMN id SET DEFAULT nextval('public.ntf_keutamaan_pengguna_id_seq'::regclass);


--
-- Name: ntf_mesej id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_mesej ALTER COLUMN id SET DEFAULT nextval('public.ntf_mesej_id_seq'::regclass);


--
-- Name: ntf_penerima_peraturan id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_penerima_peraturan ALTER COLUMN id SET DEFAULT nextval('public.ntf_penerima_peraturan_id_seq'::regclass);


--
-- Name: ntf_penghantaran id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_penghantaran ALTER COLUMN id SET DEFAULT nextval('public.ntf_penghantaran_id_seq'::regclass);


--
-- Name: ntf_status_pengguna id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_status_pengguna ALTER COLUMN id SET DEFAULT nextval('public.ntf_status_pengguna_id_seq'::regclass);


--
-- Name: ntf_templat id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_templat ALTER COLUMN id SET DEFAULT nextval('public.ntf_templat_id_seq'::regclass);


--
-- Name: pemetaan_import id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pemetaan_import ALTER COLUMN id SET DEFAULT nextval('public.pemetaan_import_id_seq'::regclass);


--
-- Name: pemetaan_tuntutan id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pemetaan_tuntutan ALTER COLUMN id SET DEFAULT nextval('public.pemetaan_tuntutan_id_seq'::regclass);


--
-- Name: pengguna_peranan id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pengguna_peranan ALTER COLUMN id SET DEFAULT nextval('public.pengguna_peranan_id_seq'::regclass);


--
-- Name: pengguna_skop id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pengguna_skop ALTER COLUMN id SET DEFAULT nextval('public.pengguna_skop_id_seq'::regclass);


--
-- Name: peranan id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.peranan ALTER COLUMN id SET DEFAULT nextval('public.peranan_id_seq'::regclass);


--
-- Name: peranan_kebenaran id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.peranan_kebenaran ALTER COLUMN id SET DEFAULT nextval('public.peranan_kebenaran_id_seq'::regclass);


--
-- Name: peranan_skop id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.peranan_skop ALTER COLUMN id SET DEFAULT nextval('public.peranan_skop_id_seq'::regclass);


--
-- Name: peraturan_kualiti_data id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.peraturan_kualiti_data ALTER COLUMN id SET DEFAULT nextval('public.peraturan_kualiti_data_id_seq'::regclass);


--
-- Name: personal_access_tokens id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens ALTER COLUMN id SET DEFAULT nextval('public.personal_access_tokens_id_seq'::regclass);


--
-- Name: ralat_import id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ralat_import ALTER COLUMN id SET DEFAULT nextval('public.ralat_import_id_seq'::regclass);


--
-- Name: recert_keputusan id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recert_keputusan ALTER COLUMN id SET DEFAULT nextval('public.recert_keputusan_id_seq'::regclass);


--
-- Name: recert_kitaran id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recert_kitaran ALTER COLUMN id SET DEFAULT nextval('public.recert_kitaran_id_seq'::regclass);


--
-- Name: ref_daerah id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_daerah ALTER COLUMN id SET DEFAULT nextval('public.ref_daerah_id_seq'::regclass);


--
-- Name: ref_jenis_dokumen id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_jenis_dokumen ALTER COLUMN id SET DEFAULT nextval('public.ref_jenis_dokumen_id_seq'::regclass);


--
-- Name: ref_jenis_hak_milik id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_jenis_hak_milik ALTER COLUMN id SET DEFAULT nextval('public.ref_jenis_hak_milik_id_seq'::regclass);


--
-- Name: ref_kategori_aduan id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_kategori_aduan ALTER COLUMN id SET DEFAULT nextval('public.ref_kategori_aduan_id_seq'::regclass);


--
-- Name: ref_kategori_guna_tanah id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_kategori_guna_tanah ALTER COLUMN id SET DEFAULT nextval('public.ref_kategori_guna_tanah_id_seq'::regclass);


--
-- Name: ref_klasifikasi_data id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_klasifikasi_data ALTER COLUMN id SET DEFAULT nextval('public.ref_klasifikasi_data_id_seq'::regclass);


--
-- Name: ref_mukim id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_mukim ALTER COLUMN id SET DEFAULT nextval('public.ref_mukim_id_seq'::regclass);


--
-- Name: ref_negeri id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_negeri ALTER COLUMN id SET DEFAULT nextval('public.ref_negeri_id_seq'::regclass);


--
-- Name: ref_pbt id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_pbt ALTER COLUMN id SET DEFAULT nextval('public.ref_pbt_id_seq'::regclass);


--
-- Name: ref_sempadan id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_sempadan ALTER COLUMN id SET DEFAULT nextval('public.ref_sempadan_id_seq'::regclass);


--
-- Name: ref_tempat_ibadat id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_tempat_ibadat ALTER COLUMN id SET DEFAULT nextval('public.ref_tempat_ibadat_id_seq'::regclass);


--
-- Name: settings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.settings ALTER COLUMN id SET DEFAULT nextval('public.settings_id_seq'::regclass);


--
-- Name: status_akaun_log id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.status_akaun_log ALTER COLUMN id SET DEFAULT nextval('public.status_akaun_log_id_seq'::regclass);


--
-- Name: tugasan_kualiti_data id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tugasan_kualiti_data ALTER COLUMN id SET DEFAULT nextval('public.tugasan_kualiti_data_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: wf_cuti_umum id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_cuti_umum ALTER COLUMN id SET DEFAULT nextval('public.wf_cuti_umum_id_seq'::regclass);


--
-- Name: wf_definisi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_definisi ALTER COLUMN id SET DEFAULT nextval('public.wf_definisi_id_seq'::regclass);


--
-- Name: wf_definisi_log id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_definisi_log ALTER COLUMN id SET DEFAULT nextval('public.wf_definisi_log_id_seq'::regclass);


--
-- Name: wf_eskalasi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_eskalasi ALTER COLUMN id SET DEFAULT nextval('public.wf_eskalasi_id_seq'::regclass);


--
-- Name: wf_eskalasi_log id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_eskalasi_log ALTER COLUMN id SET DEFAULT nextval('public.wf_eskalasi_log_id_seq'::regclass);


--
-- Name: wf_kumpulan id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_kumpulan ALTER COLUMN id SET DEFAULT nextval('public.wf_kumpulan_id_seq'::regclass);


--
-- Name: wf_pelulus id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_pelulus ALTER COLUMN id SET DEFAULT nextval('public.wf_pelulus_id_seq'::regclass);


--
-- Name: wf_pemangku id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_pemangku ALTER COLUMN id SET DEFAULT nextval('public.wf_pemangku_id_seq'::regclass);


--
-- Name: wf_peralihan id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_peralihan ALTER COLUMN id SET DEFAULT nextval('public.wf_peralihan_id_seq'::regclass);


--
-- Name: wf_peringkat id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_peringkat ALTER COLUMN id SET DEFAULT nextval('public.wf_peringkat_id_seq'::regclass);


--
-- Name: wf_pindah_versi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_pindah_versi ALTER COLUMN id SET DEFAULT nextval('public.wf_pindah_versi_id_seq'::regclass);


--
-- Name: wf_sejarah id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_sejarah ALTER COLUMN id SET DEFAULT nextval('public.wf_sejarah_id_seq'::regclass);


--
-- Name: wf_susulan id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_susulan ALTER COLUMN id SET DEFAULT nextval('public.wf_susulan_id_seq'::regclass);


--
-- Name: wf_syarat id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_syarat ALTER COLUMN id SET DEFAULT nextval('public.wf_syarat_id_seq'::regclass);


--
-- Name: wf_tugasan id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_tugasan ALTER COLUMN id SET DEFAULT nextval('public.wf_tugasan_id_seq'::regclass);


--
-- Name: adm_indeks_carian adm_indeks_carian_entiti_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_indeks_carian
    ADD CONSTRAINT adm_indeks_carian_entiti_unique UNIQUE (entiti);


--
-- Name: adm_indeks_carian adm_indeks_carian_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_indeks_carian
    ADD CONSTRAINT adm_indeks_carian_pkey PRIMARY KEY (id);


--
-- Name: adm_integrasi adm_integrasi_kod_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_integrasi
    ADD CONSTRAINT adm_integrasi_kod_unique UNIQUE (kod);


--
-- Name: adm_integrasi adm_integrasi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_integrasi
    ADD CONSTRAINT adm_integrasi_pkey PRIMARY KEY (id);


--
-- Name: adm_kesihatan adm_kesihatan_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_kesihatan
    ADD CONSTRAINT adm_kesihatan_pkey PRIMARY KEY (id);


--
-- Name: adm_kod_rujukan adm_kod_rujukan_jenis_kod_kod_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_kod_rujukan
    ADD CONSTRAINT adm_kod_rujukan_jenis_kod_kod_unique UNIQUE (jenis_kod, kod);


--
-- Name: adm_kod_rujukan adm_kod_rujukan_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_kod_rujukan
    ADD CONSTRAINT adm_kod_rujukan_pkey PRIMARY KEY (id);


--
-- Name: adm_kod_rujukan_sejarah adm_kod_rujukan_sejarah_id_kod_rujukan_versi_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_kod_rujukan_sejarah
    ADD CONSTRAINT adm_kod_rujukan_sejarah_id_kod_rujukan_versi_unique UNIQUE (id_kod_rujukan, versi);


--
-- Name: adm_kod_rujukan_sejarah adm_kod_rujukan_sejarah_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_kod_rujukan_sejarah
    ADD CONSTRAINT adm_kod_rujukan_sejarah_pkey PRIMARY KEY (id);


--
-- Name: adm_konfig_kelulusan adm_konfig_kelulusan_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_konfig_kelulusan
    ADD CONSTRAINT adm_konfig_kelulusan_pkey PRIMARY KEY (id);


--
-- Name: adm_log_integrasi adm_log_integrasi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_log_integrasi
    ADD CONSTRAINT adm_log_integrasi_pkey PRIMARY KEY (id);


--
-- Name: adm_log_ralat adm_log_ralat_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_log_ralat
    ADD CONSTRAINT adm_log_ralat_pkey PRIMARY KEY (id);


--
-- Name: adm_parameter adm_parameter_kunci_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_parameter
    ADD CONSTRAINT adm_parameter_kunci_unique UNIQUE (kunci);


--
-- Name: adm_parameter adm_parameter_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_parameter
    ADD CONSTRAINT adm_parameter_pkey PRIMARY KEY (id);


--
-- Name: adm_rahsia adm_rahsia_kunci_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_rahsia
    ADD CONSTRAINT adm_rahsia_kunci_unique UNIQUE (kunci);


--
-- Name: adm_rahsia adm_rahsia_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_rahsia
    ADD CONSTRAINT adm_rahsia_pkey PRIMARY KEY (id);


--
-- Name: adm_sinonim_carian adm_sinonim_carian_istilah_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_sinonim_carian
    ADD CONSTRAINT adm_sinonim_carian_istilah_unique UNIQUE (istilah);


--
-- Name: adm_sinonim_carian adm_sinonim_carian_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_sinonim_carian
    ADD CONSTRAINT adm_sinonim_carian_pkey PRIMARY KEY (id);


--
-- Name: adm_templat adm_templat_kod_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_templat
    ADD CONSTRAINT adm_templat_kod_unique UNIQUE (kod);


--
-- Name: adm_templat adm_templat_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_templat
    ADD CONSTRAINT adm_templat_pkey PRIMARY KEY (id);


--
-- Name: adm_templat_versi adm_templat_versi_id_templat_versi_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_templat_versi
    ADD CONSTRAINT adm_templat_versi_id_templat_versi_unique UNIQUE (id_templat, versi);


--
-- Name: adm_templat_versi adm_templat_versi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_templat_versi
    ADD CONSTRAINT adm_templat_versi_pkey PRIMARY KEY (id);


--
-- Name: adm_tugasan_berjadual adm_tugasan_berjadual_kod_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_tugasan_berjadual
    ADD CONSTRAINT adm_tugasan_berjadual_kod_unique UNIQUE (kod);


--
-- Name: adm_tugasan_berjadual adm_tugasan_berjadual_kunci_sistem_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_tugasan_berjadual
    ADD CONSTRAINT adm_tugasan_berjadual_kunci_sistem_unique UNIQUE (kunci_sistem);


--
-- Name: adm_tugasan_berjadual adm_tugasan_berjadual_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_tugasan_berjadual
    ADD CONSTRAINT adm_tugasan_berjadual_pkey PRIMARY KEY (id);


--
-- Name: adm_tugasan_larian adm_tugasan_larian_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_tugasan_larian
    ADD CONSTRAINT adm_tugasan_larian_pkey PRIMARY KEY (id);


--
-- Name: aduan aduan_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.aduan
    ADD CONSTRAINT aduan_pkey PRIMARY KEY (id);


--
-- Name: amaran_keselamatan amaran_keselamatan_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.amaran_keselamatan
    ADD CONSTRAINT amaran_keselamatan_pkey PRIMARY KEY (id);


--
-- Name: audit_arkib audit_arkib_cincangan_rekod_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_arkib
    ADD CONSTRAINT audit_arkib_cincangan_rekod_unique UNIQUE (cincangan_rekod);


--
-- Name: audit_arkib audit_arkib_no_urutan_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_arkib
    ADD CONSTRAINT audit_arkib_no_urutan_unique UNIQUE (no_urutan);


--
-- Name: audit_arkib audit_arkib_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_arkib
    ADD CONSTRAINT audit_arkib_pkey PRIMARY KEY (id);


--
-- Name: audit_eksport audit_eksport_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_eksport
    ADD CONSTRAINT audit_eksport_pkey PRIMARY KEY (id);


--
-- Name: audit_integriti_semakan audit_integriti_semakan_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_integriti_semakan
    ADD CONSTRAINT audit_integriti_semakan_pkey PRIMARY KEY (id);


--
-- Name: audit_perubahan_medan audit_perubahan_medan_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_perubahan_medan
    ADD CONSTRAINT audit_perubahan_medan_pkey PRIMARY KEY (id);


--
-- Name: audit_rekod audit_rekod_cincangan_rekod_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_rekod
    ADD CONSTRAINT audit_rekod_cincangan_rekod_unique UNIQUE (cincangan_rekod);


--
-- Name: audit_rekod audit_rekod_no_urutan_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_rekod
    ADD CONSTRAINT audit_rekod_no_urutan_unique UNIQUE (no_urutan);


--
-- Name: audit_rekod audit_rekod_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_rekod
    ADD CONSTRAINT audit_rekod_pkey PRIMARY KEY (id);


--
-- Name: audit_retensi_polisi audit_retensi_polisi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_retensi_polisi
    ADD CONSTRAINT audit_retensi_polisi_pkey PRIMARY KEY (id);


--
-- Name: baris_import baris_import_id_sesi_no_baris_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.baris_import
    ADD CONSTRAINT baris_import_id_sesi_no_baris_unique UNIQUE (id_sesi, no_baris);


--
-- Name: baris_import baris_import_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.baris_import
    ADD CONSTRAINT baris_import_pkey PRIMARY KEY (id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: cadangan_duplikasi cadangan_duplikasi_entiti_id_rekod_a_id_rekod_b_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cadangan_duplikasi
    ADD CONSTRAINT cadangan_duplikasi_entiti_id_rekod_a_id_rekod_b_unique UNIQUE (entiti, id_rekod_a, id_rekod_b);


--
-- Name: cadangan_duplikasi cadangan_duplikasi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cadangan_duplikasi
    ADD CONSTRAINT cadangan_duplikasi_pkey PRIMARY KEY (id);


--
-- Name: cukai cukai_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cukai
    ADD CONSTRAINT cukai_pkey PRIMARY KEY (id);


--
-- Name: daftar_stewardship_data daftar_stewardship_data_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.daftar_stewardship_data
    ADD CONSTRAINT daftar_stewardship_data_pkey PRIMARY KEY (id);


--
-- Name: data_spatial data_spatial_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_spatial
    ADD CONSTRAINT data_spatial_pkey PRIMARY KEY (id);


--
-- Name: dokumen_arkib dokumen_arkib_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dokumen_arkib
    ADD CONSTRAINT dokumen_arkib_pkey PRIMARY KEY (id);


--
-- Name: dokumen_imbasan dokumen_imbasan_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dokumen_imbasan
    ADD CONSTRAINT dokumen_imbasan_pkey PRIMARY KEY (id);


--
-- Name: dokumen_kategori dokumen_kategori_kod_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dokumen_kategori
    ADD CONSTRAINT dokumen_kategori_kod_unique UNIQUE (kod);


--
-- Name: dokumen_kategori dokumen_kategori_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dokumen_kategori
    ADD CONSTRAINT dokumen_kategori_pkey PRIMARY KEY (id);


--
-- Name: dokumen_kebenaran dokumen_kebenaran_dokumen_id_jenis_penerima_penerima_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dokumen_kebenaran
    ADD CONSTRAINT dokumen_kebenaran_dokumen_id_jenis_penerima_penerima_unique UNIQUE (dokumen_id, jenis_penerima, penerima);


--
-- Name: dokumen_kebenaran dokumen_kebenaran_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dokumen_kebenaran
    ADD CONSTRAINT dokumen_kebenaran_pkey PRIMARY KEY (id);


--
-- Name: dokumen_kuarantin dokumen_kuarantin_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dokumen_kuarantin
    ADD CONSTRAINT dokumen_kuarantin_pkey PRIMARY KEY (id);


--
-- Name: dokumen_medan_metadata dokumen_medan_metadata_kategori_id_kod_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dokumen_medan_metadata
    ADD CONSTRAINT dokumen_medan_metadata_kategori_id_kod_unique UNIQUE (kategori_id, kod);


--
-- Name: dokumen_medan_metadata dokumen_medan_metadata_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dokumen_medan_metadata
    ADD CONSTRAINT dokumen_medan_metadata_pkey PRIMARY KEY (id);


--
-- Name: dokumen dokumen_no_rujukan_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dokumen
    ADD CONSTRAINT dokumen_no_rujukan_unique UNIQUE (no_rujukan);


--
-- Name: dokumen_pelupusan dokumen_pelupusan_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dokumen_pelupusan
    ADD CONSTRAINT dokumen_pelupusan_pkey PRIMARY KEY (id);


--
-- Name: dokumen dokumen_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dokumen
    ADD CONSTRAINT dokumen_pkey PRIMARY KEY (id);


--
-- Name: dokumen_versi dokumen_versi_dokumen_id_no_versi_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dokumen_versi
    ADD CONSTRAINT dokumen_versi_dokumen_id_no_versi_unique UNIQUE (dokumen_id, no_versi);


--
-- Name: dokumen_versi dokumen_versi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dokumen_versi
    ADD CONSTRAINT dokumen_versi_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: jadual_retensi jadual_retensi_kategori_rekod_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jadual_retensi
    ADD CONSTRAINT jadual_retensi_kategori_rekod_unique UNIQUE (kategori_rekod);


--
-- Name: jadual_retensi jadual_retensi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jadual_retensi
    ADD CONSTRAINT jadual_retensi_pkey PRIMARY KEY (id);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: kamus_data kamus_data_nama_jadual_nama_medan_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kamus_data
    ADD CONSTRAINT kamus_data_nama_jadual_nama_medan_unique UNIQUE (nama_jadual, nama_medan);


--
-- Name: kamus_data kamus_data_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kamus_data
    ADD CONSTRAINT kamus_data_pkey PRIMARY KEY (id);


--
-- Name: kata_laluan_sejarah kata_laluan_sejarah_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kata_laluan_sejarah
    ADD CONSTRAINT kata_laluan_sejarah_pkey PRIMARY KEY (id);


--
-- Name: kebenaran kebenaran_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kebenaran
    ADD CONSTRAINT kebenaran_pkey PRIMARY KEY (kunci);


--
-- Name: kes_mahkamah_lot kes_mahkamah_lot_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kes_mahkamah_lot
    ADD CONSTRAINT kes_mahkamah_lot_pkey PRIMARY KEY (kes_mahkamah_id, lot_id);


--
-- Name: kes_mahkamah kes_mahkamah_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kes_mahkamah
    ADD CONSTRAINT kes_mahkamah_pkey PRIMARY KEY (id);


--
-- Name: klasifikasi_data klasifikasi_data_komponen_data_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.klasifikasi_data
    ADD CONSTRAINT klasifikasi_data_komponen_data_unique UNIQUE (komponen_data);


--
-- Name: klasifikasi_data klasifikasi_data_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.klasifikasi_data
    ADD CONSTRAINT klasifikasi_data_pkey PRIMARY KEY (id);


--
-- Name: kontrak_pajakan kontrak_pajakan_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kontrak_pajakan
    ADD CONSTRAINT kontrak_pajakan_pkey PRIMARY KEY (id);


--
-- Name: kunci_api_pro kunci_api_pro_id_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kunci_api_pro
    ADD CONSTRAINT kunci_api_pro_id_token_unique UNIQUE (id_token);


--
-- Name: kunci_api_pro kunci_api_pro_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kunci_api_pro
    ADD CONSTRAINT kunci_api_pro_pkey PRIMARY KEY (id);


--
-- Name: log_akses_profil log_akses_profil_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.log_akses_profil
    ADD CONSTRAINT log_akses_profil_pkey PRIMARY KEY (id);


--
-- Name: log_log_masuk log_log_masuk_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.log_log_masuk
    ADD CONSTRAINT log_log_masuk_pkey PRIMARY KEY (id);


--
-- Name: log_penggunaan_api log_penggunaan_api_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.log_penggunaan_api
    ADD CONSTRAINT log_penggunaan_api_pkey PRIMARY KEY (id);


--
-- Name: lot_tanah lot_tanah_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lot_tanah
    ADD CONSTRAINT lot_tanah_pkey PRIMARY KEY (id);


--
-- Name: migrasi_larian migrasi_larian_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrasi_larian
    ADD CONSTRAINT migrasi_larian_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: notifications notifications_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT notifications_pkey PRIMARY KEY (id);


--
-- Name: ntf_eskalasi_log ntf_eskalasi_log_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_eskalasi_log
    ADD CONSTRAINT ntf_eskalasi_log_pkey PRIMARY KEY (id);


--
-- Name: ntf_jenis ntf_jenis_kod_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_jenis
    ADD CONSTRAINT ntf_jenis_kod_unique UNIQUE (kod);


--
-- Name: ntf_jenis ntf_jenis_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_jenis
    ADD CONSTRAINT ntf_jenis_pkey PRIMARY KEY (id);


--
-- Name: ntf_keutamaan_pengguna ntf_keutamaan_pengguna_pengguna_id_jenis_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_keutamaan_pengguna
    ADD CONSTRAINT ntf_keutamaan_pengguna_pengguna_id_jenis_id_unique UNIQUE (pengguna_id, jenis_id);


--
-- Name: ntf_keutamaan_pengguna ntf_keutamaan_pengguna_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_keutamaan_pengguna
    ADD CONSTRAINT ntf_keutamaan_pengguna_pkey PRIMARY KEY (id);


--
-- Name: ntf_mesej ntf_mesej_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_mesej
    ADD CONSTRAINT ntf_mesej_pkey PRIMARY KEY (id);


--
-- Name: ntf_penerima_peraturan ntf_penerima_peraturan_jenis_id_sasaran_nilai_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_penerima_peraturan
    ADD CONSTRAINT ntf_penerima_peraturan_jenis_id_sasaran_nilai_unique UNIQUE (jenis_id, sasaran, nilai);


--
-- Name: ntf_penerima_peraturan ntf_penerima_peraturan_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_penerima_peraturan
    ADD CONSTRAINT ntf_penerima_peraturan_pkey PRIMARY KEY (id);


--
-- Name: ntf_penghantaran ntf_penghantaran_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_penghantaran
    ADD CONSTRAINT ntf_penghantaran_pkey PRIMARY KEY (id);


--
-- Name: ntf_status_pengguna ntf_status_pengguna_mesej_id_pengguna_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_status_pengguna
    ADD CONSTRAINT ntf_status_pengguna_mesej_id_pengguna_id_unique UNIQUE (mesej_id, pengguna_id);


--
-- Name: ntf_status_pengguna ntf_status_pengguna_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_status_pengguna
    ADD CONSTRAINT ntf_status_pengguna_pkey PRIMARY KEY (id);


--
-- Name: ntf_templat ntf_templat_jenis_id_versi_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_templat
    ADD CONSTRAINT ntf_templat_jenis_id_versi_unique UNIQUE (jenis_id, versi);


--
-- Name: ntf_templat ntf_templat_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_templat
    ADD CONSTRAINT ntf_templat_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: pemetaan_import pemetaan_import_entiti_sasaran_nama_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pemetaan_import
    ADD CONSTRAINT pemetaan_import_entiti_sasaran_nama_unique UNIQUE (entiti_sasaran, nama);


--
-- Name: pemetaan_import pemetaan_import_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pemetaan_import
    ADD CONSTRAINT pemetaan_import_pkey PRIMARY KEY (id);


--
-- Name: pemetaan_tuntutan pemetaan_tuntutan_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pemetaan_tuntutan
    ADD CONSTRAINT pemetaan_tuntutan_pkey PRIMARY KEY (id);


--
-- Name: pengguna_peranan pengguna_peranan_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pengguna_peranan
    ADD CONSTRAINT pengguna_peranan_pkey PRIMARY KEY (id);


--
-- Name: pengguna_peranan pengguna_peranan_user_id_peranan_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pengguna_peranan
    ADD CONSTRAINT pengguna_peranan_user_id_peranan_id_unique UNIQUE (user_id, peranan_id);


--
-- Name: pengguna_skop pengguna_skop_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pengguna_skop
    ADD CONSTRAINT pengguna_skop_pkey PRIMARY KEY (id);


--
-- Name: pengguna_skop pengguna_skop_user_id_jenis_skop_nilai_skop_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pengguna_skop
    ADD CONSTRAINT pengguna_skop_user_id_jenis_skop_nilai_skop_unique UNIQUE (user_id, jenis_skop, nilai_skop);


--
-- Name: penilaian penilaian_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.penilaian
    ADD CONSTRAINT penilaian_pkey PRIMARY KEY (id);


--
-- Name: peranan_kebenaran peranan_kebenaran_peranan_id_kebenaran_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.peranan_kebenaran
    ADD CONSTRAINT peranan_kebenaran_peranan_id_kebenaran_unique UNIQUE (peranan_id, kebenaran);


--
-- Name: peranan_kebenaran peranan_kebenaran_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.peranan_kebenaran
    ADD CONSTRAINT peranan_kebenaran_pkey PRIMARY KEY (id);


--
-- Name: peranan peranan_kod_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.peranan
    ADD CONSTRAINT peranan_kod_unique UNIQUE (kod);


--
-- Name: peranan peranan_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.peranan
    ADD CONSTRAINT peranan_pkey PRIMARY KEY (id);


--
-- Name: peranan_skop peranan_skop_peranan_id_jenis_skop_nilai_skop_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.peranan_skop
    ADD CONSTRAINT peranan_skop_peranan_id_jenis_skop_nilai_skop_unique UNIQUE (peranan_id, jenis_skop, nilai_skop);


--
-- Name: peranan_skop peranan_skop_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.peranan_skop
    ADD CONSTRAINT peranan_skop_pkey PRIMARY KEY (id);


--
-- Name: peraturan_kualiti_data peraturan_kualiti_data_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.peraturan_kualiti_data
    ADD CONSTRAINT peraturan_kualiti_data_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_token_unique UNIQUE (token);


--
-- Name: polisi_keselamatan polisi_keselamatan_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.polisi_keselamatan
    ADD CONSTRAINT polisi_keselamatan_pkey PRIMARY KEY (kunci);


--
-- Name: ralat_import ralat_import_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ralat_import
    ADD CONSTRAINT ralat_import_pkey PRIMARY KEY (id);


--
-- Name: recert_keputusan recert_keputusan_kitaran_id_user_id_peranan_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recert_keputusan
    ADD CONSTRAINT recert_keputusan_kitaran_id_user_id_peranan_id_unique UNIQUE (kitaran_id, user_id, peranan_id);


--
-- Name: recert_keputusan recert_keputusan_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recert_keputusan
    ADD CONSTRAINT recert_keputusan_pkey PRIMARY KEY (id);


--
-- Name: recert_kitaran recert_kitaran_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recert_kitaran
    ADD CONSTRAINT recert_kitaran_pkey PRIMARY KEY (id);


--
-- Name: ref_daerah ref_daerah_kod_versi_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_daerah
    ADD CONSTRAINT ref_daerah_kod_versi_unique UNIQUE (kod, versi);


--
-- Name: ref_daerah ref_daerah_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_daerah
    ADD CONSTRAINT ref_daerah_pkey PRIMARY KEY (id);


--
-- Name: ref_jenis_dokumen ref_jenis_dokumen_kod_versi_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_jenis_dokumen
    ADD CONSTRAINT ref_jenis_dokumen_kod_versi_unique UNIQUE (kod, versi);


--
-- Name: ref_jenis_dokumen ref_jenis_dokumen_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_jenis_dokumen
    ADD CONSTRAINT ref_jenis_dokumen_pkey PRIMARY KEY (id);


--
-- Name: ref_jenis_hak_milik ref_jenis_hak_milik_kod_versi_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_jenis_hak_milik
    ADD CONSTRAINT ref_jenis_hak_milik_kod_versi_unique UNIQUE (kod, versi);


--
-- Name: ref_jenis_hak_milik ref_jenis_hak_milik_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_jenis_hak_milik
    ADD CONSTRAINT ref_jenis_hak_milik_pkey PRIMARY KEY (id);


--
-- Name: ref_kategori_aduan ref_kategori_aduan_kod_versi_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_kategori_aduan
    ADD CONSTRAINT ref_kategori_aduan_kod_versi_unique UNIQUE (kod, versi);


--
-- Name: ref_kategori_aduan ref_kategori_aduan_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_kategori_aduan
    ADD CONSTRAINT ref_kategori_aduan_pkey PRIMARY KEY (id);


--
-- Name: ref_kategori_guna_tanah ref_kategori_guna_tanah_kod_versi_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_kategori_guna_tanah
    ADD CONSTRAINT ref_kategori_guna_tanah_kod_versi_unique UNIQUE (kod, versi);


--
-- Name: ref_kategori_guna_tanah ref_kategori_guna_tanah_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_kategori_guna_tanah
    ADD CONSTRAINT ref_kategori_guna_tanah_pkey PRIMARY KEY (id);


--
-- Name: ref_klasifikasi_data ref_klasifikasi_data_kod_versi_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_klasifikasi_data
    ADD CONSTRAINT ref_klasifikasi_data_kod_versi_unique UNIQUE (kod, versi);


--
-- Name: ref_klasifikasi_data ref_klasifikasi_data_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_klasifikasi_data
    ADD CONSTRAINT ref_klasifikasi_data_pkey PRIMARY KEY (id);


--
-- Name: ref_mukim ref_mukim_kod_versi_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_mukim
    ADD CONSTRAINT ref_mukim_kod_versi_unique UNIQUE (kod, versi);


--
-- Name: ref_mukim ref_mukim_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_mukim
    ADD CONSTRAINT ref_mukim_pkey PRIMARY KEY (id);


--
-- Name: ref_negeri ref_negeri_kod_versi_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_negeri
    ADD CONSTRAINT ref_negeri_kod_versi_unique UNIQUE (kod, versi);


--
-- Name: ref_negeri ref_negeri_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_negeri
    ADD CONSTRAINT ref_negeri_pkey PRIMARY KEY (id);


--
-- Name: ref_pbt ref_pbt_kod_versi_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_pbt
    ADD CONSTRAINT ref_pbt_kod_versi_unique UNIQUE (kod, versi);


--
-- Name: ref_pbt ref_pbt_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_pbt
    ADD CONSTRAINT ref_pbt_pkey PRIMARY KEY (id);


--
-- Name: ref_sempadan ref_sempadan_jenis_kod_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_sempadan
    ADD CONSTRAINT ref_sempadan_jenis_kod_unique UNIQUE (jenis, kod);


--
-- Name: ref_sempadan ref_sempadan_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_sempadan
    ADD CONSTRAINT ref_sempadan_pkey PRIMARY KEY (id);


--
-- Name: ref_tempat_ibadat ref_tempat_ibadat_osm_jenis_osm_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_tempat_ibadat
    ADD CONSTRAINT ref_tempat_ibadat_osm_jenis_osm_id_unique UNIQUE (osm_jenis, osm_id);


--
-- Name: ref_tempat_ibadat ref_tempat_ibadat_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_tempat_ibadat
    ADD CONSTRAINT ref_tempat_ibadat_pkey PRIMARY KEY (id);


--
-- Name: rekod_arkib rekod_arkib_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rekod_arkib
    ADD CONSTRAINT rekod_arkib_pkey PRIMARY KEY (id);


--
-- Name: sesi_import sesi_import_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sesi_import
    ADD CONSTRAINT sesi_import_pkey PRIMARY KEY (id);


--
-- Name: sesi sesi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sesi
    ADD CONSTRAINT sesi_pkey PRIMARY KEY (id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: settings settings_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.settings
    ADD CONSTRAINT settings_key_unique UNIQUE (key);


--
-- Name: settings settings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.settings
    ADD CONSTRAINT settings_pkey PRIMARY KEY (id);


--
-- Name: staging_hak_milik staging_hak_milik_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.staging_hak_milik
    ADD CONSTRAINT staging_hak_milik_pkey PRIMARY KEY (id);


--
-- Name: staging_hak_milik staging_hak_milik_sumber_sistem_fail_asal_kunci_asal_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.staging_hak_milik
    ADD CONSTRAINT staging_hak_milik_sumber_sistem_fail_asal_kunci_asal_unique UNIQUE (sumber_sistem, fail_asal, kunci_asal);


--
-- Name: staging_lot_tanah staging_lot_tanah_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.staging_lot_tanah
    ADD CONSTRAINT staging_lot_tanah_pkey PRIMARY KEY (id);


--
-- Name: staging_lot_tanah staging_lot_tanah_sumber_sistem_fail_asal_kunci_asal_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.staging_lot_tanah
    ADD CONSTRAINT staging_lot_tanah_sumber_sistem_fail_asal_kunci_asal_unique UNIQUE (sumber_sistem, fail_asal, kunci_asal);


--
-- Name: staging_pemilik staging_pemilik_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.staging_pemilik
    ADD CONSTRAINT staging_pemilik_pkey PRIMARY KEY (id);


--
-- Name: staging_pemilik staging_pemilik_sumber_sistem_fail_asal_kunci_asal_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.staging_pemilik
    ADD CONSTRAINT staging_pemilik_sumber_sistem_fail_asal_kunci_asal_unique UNIQUE (sumber_sistem, fail_asal, kunci_asal);


--
-- Name: status_akaun_log status_akaun_log_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.status_akaun_log
    ADD CONSTRAINT status_akaun_log_pkey PRIMARY KEY (id);


--
-- Name: tugasan_kualiti_data tugasan_kualiti_data_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tugasan_kualiti_data
    ADD CONSTRAINT tugasan_kualiti_data_pkey PRIMARY KEY (id);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_oidc_sub_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_oidc_sub_unique UNIQUE (oidc_sub);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: versi_rekod_induk versi_rekod_induk_entiti_id_rekod_no_versi_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.versi_rekod_induk
    ADD CONSTRAINT versi_rekod_induk_entiti_id_rekod_no_versi_unique UNIQUE (entiti, id_rekod, no_versi);


--
-- Name: versi_rekod_induk versi_rekod_induk_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.versi_rekod_induk
    ADD CONSTRAINT versi_rekod_induk_pkey PRIMARY KEY (id);


--
-- Name: wf_cuti_umum wf_cuti_umum_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_cuti_umum
    ADD CONSTRAINT wf_cuti_umum_pkey PRIMARY KEY (id);


--
-- Name: wf_cuti_umum wf_cuti_umum_tarikh_negeri_nama_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_cuti_umum
    ADD CONSTRAINT wf_cuti_umum_tarikh_negeri_nama_unique UNIQUE (tarikh, negeri, nama);


--
-- Name: wf_definisi wf_definisi_kod_aliran_no_versi_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_definisi
    ADD CONSTRAINT wf_definisi_kod_aliran_no_versi_unique UNIQUE (kod_aliran, no_versi);


--
-- Name: wf_definisi_log wf_definisi_log_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_definisi_log
    ADD CONSTRAINT wf_definisi_log_pkey PRIMARY KEY (id);


--
-- Name: wf_definisi wf_definisi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_definisi
    ADD CONSTRAINT wf_definisi_pkey PRIMARY KEY (id);


--
-- Name: wf_eskalasi_log wf_eskalasi_log_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_eskalasi_log
    ADD CONSTRAINT wf_eskalasi_log_pkey PRIMARY KEY (id);


--
-- Name: wf_eskalasi wf_eskalasi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_eskalasi
    ADD CONSTRAINT wf_eskalasi_pkey PRIMARY KEY (id);


--
-- Name: wf_instans wf_instans_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_instans
    ADD CONSTRAINT wf_instans_pkey PRIMARY KEY (id);


--
-- Name: wf_kumpulan_ahli wf_kumpulan_ahli_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_kumpulan_ahli
    ADD CONSTRAINT wf_kumpulan_ahli_pkey PRIMARY KEY (id_kumpulan, id_pengguna);


--
-- Name: wf_kumpulan wf_kumpulan_kod_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_kumpulan
    ADD CONSTRAINT wf_kumpulan_kod_unique UNIQUE (kod);


--
-- Name: wf_kumpulan wf_kumpulan_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_kumpulan
    ADD CONSTRAINT wf_kumpulan_pkey PRIMARY KEY (id);


--
-- Name: wf_pelulus wf_pelulus_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_pelulus
    ADD CONSTRAINT wf_pelulus_pkey PRIMARY KEY (id);


--
-- Name: wf_pemangku wf_pemangku_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_pemangku
    ADD CONSTRAINT wf_pemangku_pkey PRIMARY KEY (id);


--
-- Name: wf_peralihan wf_peralihan_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_peralihan
    ADD CONSTRAINT wf_peralihan_pkey PRIMARY KEY (id);


--
-- Name: wf_peringkat wf_peringkat_id_definisi_kod_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_peringkat
    ADD CONSTRAINT wf_peringkat_id_definisi_kod_unique UNIQUE (id_definisi, kod);


--
-- Name: wf_peringkat wf_peringkat_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_peringkat
    ADD CONSTRAINT wf_peringkat_pkey PRIMARY KEY (id);


--
-- Name: wf_pindah_versi wf_pindah_versi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_pindah_versi
    ADD CONSTRAINT wf_pindah_versi_pkey PRIMARY KEY (id);


--
-- Name: wf_sejarah wf_sejarah_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_sejarah
    ADD CONSTRAINT wf_sejarah_pkey PRIMARY KEY (id);


--
-- Name: wf_susulan wf_susulan_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_susulan
    ADD CONSTRAINT wf_susulan_pkey PRIMARY KEY (id);


--
-- Name: wf_syarat wf_syarat_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_syarat
    ADD CONSTRAINT wf_syarat_pkey PRIMARY KEY (id);


--
-- Name: wf_tugasan wf_tugasan_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_tugasan
    ADD CONSTRAINT wf_tugasan_pkey PRIMARY KEY (id);


--
-- Name: adm_kesihatan_komponen_dicatat_pada_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX adm_kesihatan_komponen_dicatat_pada_index ON public.adm_kesihatan USING btree (komponen, dicatat_pada);


--
-- Name: adm_kod_rujukan_jenis_kod_status_susunan_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX adm_kod_rujukan_jenis_kod_status_susunan_index ON public.adm_kod_rujukan USING btree (jenis_kod, status, susunan);


--
-- Name: adm_konfig_kelulusan_jenis_konfig_kunci_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX adm_konfig_kelulusan_jenis_konfig_kunci_status_index ON public.adm_konfig_kelulusan USING btree (jenis_konfig, kunci, status);


--
-- Name: adm_konfig_kelulusan_status_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX adm_konfig_kelulusan_status_created_at_index ON public.adm_konfig_kelulusan USING btree (status, created_at);


--
-- Name: adm_log_integrasi_id_korelasi_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX adm_log_integrasi_id_korelasi_index ON public.adm_log_integrasi USING btree (id_korelasi);


--
-- Name: adm_log_integrasi_kod_integrasi_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX adm_log_integrasi_kod_integrasi_index ON public.adm_log_integrasi USING btree (kod_integrasi);


--
-- Name: adm_log_integrasi_tarikh_masa_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX adm_log_integrasi_tarikh_masa_index ON public.adm_log_integrasi USING btree (tarikh_masa);


--
-- Name: adm_log_ralat_aras_keterukan_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX adm_log_ralat_aras_keterukan_index ON public.adm_log_ralat USING btree (aras_keterukan);


--
-- Name: adm_log_ralat_id_korelasi_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX adm_log_ralat_id_korelasi_index ON public.adm_log_ralat USING btree (id_korelasi);


--
-- Name: adm_log_ralat_id_pengguna_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX adm_log_ralat_id_pengguna_index ON public.adm_log_ralat USING btree (id_pengguna);


--
-- Name: adm_log_ralat_kod_ralat_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX adm_log_ralat_kod_ralat_index ON public.adm_log_ralat USING btree (kod_ralat);


--
-- Name: adm_log_ralat_modul_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX adm_log_ralat_modul_index ON public.adm_log_ralat USING btree (modul);


--
-- Name: adm_log_ralat_tarikh_masa_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX adm_log_ralat_tarikh_masa_index ON public.adm_log_ralat USING btree (tarikh_masa);


--
-- Name: adm_parameter_modul_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX adm_parameter_modul_index ON public.adm_parameter USING btree (modul);


--
-- Name: adm_tugasan_larian_id_tugasan_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX adm_tugasan_larian_id_tugasan_created_at_index ON public.adm_tugasan_larian USING btree (id_tugasan, created_at);


--
-- Name: adm_tugasan_larian_status_tarikh_mula_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX adm_tugasan_larian_status_tarikh_mula_index ON public.adm_tugasan_larian USING btree (status, tarikh_mula);


--
-- Name: aduan_lot_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX aduan_lot_id_index ON public.aduan USING btree (lot_id);


--
-- Name: amaran_keselamatan_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX amaran_keselamatan_created_at_index ON public.amaran_keselamatan USING btree (created_at);


--
-- Name: amaran_keselamatan_jenis_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX amaran_keselamatan_jenis_index ON public.amaran_keselamatan USING btree (jenis);


--
-- Name: audit_arkib_alamat_ip_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX audit_arkib_alamat_ip_index ON public.audit_arkib USING btree (alamat_ip);


--
-- Name: audit_arkib_id_korelasi_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX audit_arkib_id_korelasi_index ON public.audit_arkib USING btree (id_korelasi);


--
-- Name: audit_arkib_id_pengguna_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX audit_arkib_id_pengguna_index ON public.audit_arkib USING btree (id_pengguna);


--
-- Name: audit_arkib_modul_jenis_tindakan_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX audit_arkib_modul_jenis_tindakan_index ON public.audit_arkib USING btree (modul, jenis_tindakan);


--
-- Name: audit_arkib_nama_entiti_id_rekod_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX audit_arkib_nama_entiti_id_rekod_index ON public.audit_arkib USING btree (nama_entiti, id_rekod);


--
-- Name: audit_arkib_status_transaksi_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX audit_arkib_status_transaksi_index ON public.audit_arkib USING btree (status_transaksi);


--
-- Name: audit_arkib_tarikh_masa_utc_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX audit_arkib_tarikh_masa_utc_index ON public.audit_arkib USING btree (tarikh_masa_utc);


--
-- Name: audit_eksport_id_pengguna_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX audit_eksport_id_pengguna_index ON public.audit_eksport USING btree (id_pengguna);


--
-- Name: audit_eksport_tarikh_masa_utc_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX audit_eksport_tarikh_masa_utc_index ON public.audit_eksport USING btree (tarikh_masa_utc);


--
-- Name: audit_integriti_semakan_status_dimulakan_pada_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX audit_integriti_semakan_status_dimulakan_pada_index ON public.audit_integriti_semakan USING btree (status, dimulakan_pada);


--
-- Name: audit_perubahan_medan_id_audit_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX audit_perubahan_medan_id_audit_index ON public.audit_perubahan_medan USING btree (id_audit);


--
-- Name: audit_perubahan_medan_nama_medan_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX audit_perubahan_medan_nama_medan_index ON public.audit_perubahan_medan USING btree (nama_medan);


--
-- Name: audit_rekod_alamat_ip_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX audit_rekod_alamat_ip_index ON public.audit_rekod USING btree (alamat_ip);


--
-- Name: audit_rekod_id_korelasi_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX audit_rekod_id_korelasi_index ON public.audit_rekod USING btree (id_korelasi);


--
-- Name: audit_rekod_id_pengguna_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX audit_rekod_id_pengguna_index ON public.audit_rekod USING btree (id_pengguna);


--
-- Name: audit_rekod_modul_jenis_tindakan_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX audit_rekod_modul_jenis_tindakan_index ON public.audit_rekod USING btree (modul, jenis_tindakan);


--
-- Name: audit_rekod_nama_entiti_id_rekod_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX audit_rekod_nama_entiti_id_rekod_index ON public.audit_rekod USING btree (nama_entiti, id_rekod);


--
-- Name: audit_rekod_status_transaksi_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX audit_rekod_status_transaksi_index ON public.audit_rekod USING btree (status_transaksi);


--
-- Name: audit_rekod_tarikh_masa_utc_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX audit_rekod_tarikh_masa_utc_index ON public.audit_rekod USING btree (tarikh_masa_utc);


--
-- Name: cache_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration);


--
-- Name: cache_locks_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration);


--
-- Name: cadangan_duplikasi_keputusan_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cadangan_duplikasi_keputusan_index ON public.cadangan_duplikasi USING btree (keputusan);


--
-- Name: cukai_lot_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cukai_lot_id_index ON public.cukai USING btree (lot_id);


--
-- Name: daftar_stewardship_data_entiti_berkaitan_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX daftar_stewardship_data_entiti_berkaitan_status_index ON public.daftar_stewardship_data USING btree (entiti_berkaitan, status);


--
-- Name: data_spatial_geometri_gix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX data_spatial_geometri_gix ON public.data_spatial USING gist (geometri);


--
-- Name: data_spatial_lot_id_semasa_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX data_spatial_lot_id_semasa_index ON public.data_spatial USING btree (lot_id, semasa);


--
-- Name: data_spatial_satu_semasa; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX data_spatial_satu_semasa ON public.data_spatial USING btree (lot_id) WHERE semasa;


--
-- Name: data_spatial_status_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX data_spatial_status_idx ON public.data_spatial USING btree (status_pengesahan) WHERE semasa;


--
-- Name: dokumen_carian_gin; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX dokumen_carian_gin ON public.dokumen USING gin (carian);


--
-- Name: dokumen_jenis_entiti_induk_id_rekod_induk_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX dokumen_jenis_entiti_induk_id_rekod_induk_index ON public.dokumen USING btree (jenis_entiti_induk, id_rekod_induk);


--
-- Name: dokumen_kod_daerah_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX dokumen_kod_daerah_index ON public.dokumen USING btree (kod_daerah);


--
-- Name: dokumen_nama_fail_trgm; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX dokumen_nama_fail_trgm ON public.dokumen USING gin (nama_fail public.gin_trgm_ops);


--
-- Name: dokumen_status_klasifikasi_keselamatan_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX dokumen_status_klasifikasi_keselamatan_index ON public.dokumen USING btree (status, klasifikasi_keselamatan);


--
-- Name: dokumen_tajuk_trgm; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX dokumen_tajuk_trgm ON public.dokumen USING gin (tajuk public.gin_trgm_ops);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: kata_laluan_sejarah_user_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX kata_laluan_sejarah_user_id_created_at_index ON public.kata_laluan_sejarah USING btree (user_id, created_at);


--
-- Name: kebenaran_modul_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX kebenaran_modul_index ON public.kebenaran USING btree (modul);


--
-- Name: kontrak_pajakan_lot_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX kontrak_pajakan_lot_id_index ON public.kontrak_pajakan USING btree (lot_id);


--
-- Name: log_akses_profil_lot_id_tarikh_masa_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX log_akses_profil_lot_id_tarikh_masa_index ON public.log_akses_profil USING btree (lot_id, tarikh_masa);


--
-- Name: log_log_masuk_alamat_ip_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX log_log_masuk_alamat_ip_created_at_index ON public.log_log_masuk USING btree (alamat_ip, created_at);


--
-- Name: log_log_masuk_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX log_log_masuk_created_at_index ON public.log_log_masuk USING btree (created_at);


--
-- Name: log_log_masuk_user_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX log_log_masuk_user_id_created_at_index ON public.log_log_masuk USING btree (user_id, created_at);


--
-- Name: log_penggunaan_api_id_token_tarikh_masa_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX log_penggunaan_api_id_token_tarikh_masa_index ON public.log_penggunaan_api USING btree (id_token, tarikh_masa);


--
-- Name: lot_tanah_bahagian_pemilik_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX lot_tanah_bahagian_pemilik_index ON public.lot_tanah USING btree (bahagian_pemilik);


--
-- Name: lot_tanah_klasifikasi_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX lot_tanah_klasifikasi_index ON public.lot_tanah USING btree (klasifikasi);


--
-- Name: lot_tanah_kod_daerah_kod_mukim_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX lot_tanah_kod_daerah_kod_mukim_index ON public.lot_tanah USING btree (kod_daerah, kod_mukim);


--
-- Name: lot_tanah_koordinat_pusat_gix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX lot_tanah_koordinat_pusat_gix ON public.lot_tanah USING gist (koordinat_pusat);


--
-- Name: lot_tanah_no_lot_mukim_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX lot_tanah_no_lot_mukim_unique ON public.lot_tanah USING btree (kod_mukim, no_lot) WHERE (deleted_at IS NULL);


--
-- Name: lot_tanah_no_lot_normal_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX lot_tanah_no_lot_normal_index ON public.lot_tanah USING btree (no_lot_normal);


--
-- Name: lot_tanah_no_lot_normal_trgm; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX lot_tanah_no_lot_normal_trgm ON public.lot_tanah USING gin (no_lot_normal public.gin_trgm_ops);


--
-- Name: lot_tanah_salasilah_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX lot_tanah_salasilah_unique ON public.lot_tanah USING btree (sumber_sistem, fail_asal, kunci_asal) WHERE ((sumber_sistem IS NOT NULL) AND (deleted_at IS NULL));


--
-- Name: lot_tanah_status_lot_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX lot_tanah_status_lot_index ON public.lot_tanah USING btree (status_lot);


--
-- Name: notifications_notifiable_type_notifiable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notifications_notifiable_type_notifiable_id_index ON public.notifications USING btree (notifiable_type, notifiable_id);


--
-- Name: ntf_jenis_modul_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ntf_jenis_modul_index ON public.ntf_jenis USING btree (modul);


--
-- Name: ntf_mesej_keutamaan_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ntf_mesej_keutamaan_created_at_index ON public.ntf_mesej USING btree (keutamaan, created_at);


--
-- Name: ntf_mesej_kunci_dedupe_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ntf_mesej_kunci_dedupe_index ON public.ntf_mesej USING btree (kunci_dedupe);


--
-- Name: ntf_penghantaran_penerima_id_saluran_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ntf_penghantaran_penerima_id_saluran_index ON public.ntf_penghantaran USING btree (penerima_id, saluran);


--
-- Name: ntf_penghantaran_saluran_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ntf_penghantaran_saluran_created_at_index ON public.ntf_penghantaran USING btree (saluran, created_at);


--
-- Name: ntf_penghantaran_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ntf_penghantaran_status_index ON public.ntf_penghantaran USING btree (status);


--
-- Name: ntf_status_pengguna_pengguna_id_dipadam_pada_dibaca_pada_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ntf_status_pengguna_pengguna_id_dipadam_pada_dibaca_pada_index ON public.ntf_status_pengguna USING btree (pengguna_id, dipadam_pada, dibaca_pada);


--
-- Name: penilaian_lot_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX penilaian_lot_id_index ON public.penilaian USING btree (lot_id);


--
-- Name: peranan_kebenaran_kebenaran_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX peranan_kebenaran_kebenaran_index ON public.peranan_kebenaran USING btree (kebenaran);


--
-- Name: peraturan_kualiti_data_entiti_status_aktif_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX peraturan_kualiti_data_entiti_status_aktif_index ON public.peraturan_kualiti_data USING btree (entiti, status_aktif);


--
-- Name: personal_access_tokens_expires_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX personal_access_tokens_expires_at_index ON public.personal_access_tokens USING btree (expires_at);


--
-- Name: personal_access_tokens_tokenable_type_tokenable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);


--
-- Name: ralat_import_id_sesi_no_baris_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ralat_import_id_sesi_no_baris_index ON public.ralat_import USING btree (id_sesi, no_baris);


--
-- Name: ref_daerah_kod_sah_dari_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ref_daerah_kod_sah_dari_index ON public.ref_daerah USING btree (kod, sah_dari);


--
-- Name: ref_daerah_kod_semasa_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX ref_daerah_kod_semasa_unique ON public.ref_daerah USING btree (kod) WHERE (sah_hingga IS NULL);


--
-- Name: ref_daerah_nama_semasa_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX ref_daerah_nama_semasa_unique ON public.ref_daerah USING btree (negeri_kod, lower((nama)::text)) WHERE (sah_hingga IS NULL);


--
-- Name: ref_daerah_negeri_kod_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ref_daerah_negeri_kod_index ON public.ref_daerah USING btree (negeri_kod);


--
-- Name: ref_jenis_dokumen_kod_sah_dari_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ref_jenis_dokumen_kod_sah_dari_index ON public.ref_jenis_dokumen USING btree (kod, sah_dari);


--
-- Name: ref_jenis_dokumen_kod_semasa_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX ref_jenis_dokumen_kod_semasa_unique ON public.ref_jenis_dokumen USING btree (kod) WHERE (sah_hingga IS NULL);


--
-- Name: ref_jenis_dokumen_nama_semasa_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX ref_jenis_dokumen_nama_semasa_unique ON public.ref_jenis_dokumen USING btree (lower((nama)::text)) WHERE (sah_hingga IS NULL);


--
-- Name: ref_jenis_hak_milik_kod_sah_dari_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ref_jenis_hak_milik_kod_sah_dari_index ON public.ref_jenis_hak_milik USING btree (kod, sah_dari);


--
-- Name: ref_jenis_hak_milik_kod_semasa_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX ref_jenis_hak_milik_kod_semasa_unique ON public.ref_jenis_hak_milik USING btree (kod) WHERE (sah_hingga IS NULL);


--
-- Name: ref_jenis_hak_milik_nama_semasa_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX ref_jenis_hak_milik_nama_semasa_unique ON public.ref_jenis_hak_milik USING btree (lower((nama)::text)) WHERE (sah_hingga IS NULL);


--
-- Name: ref_kategori_aduan_kod_sah_dari_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ref_kategori_aduan_kod_sah_dari_index ON public.ref_kategori_aduan USING btree (kod, sah_dari);


--
-- Name: ref_kategori_aduan_kod_semasa_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX ref_kategori_aduan_kod_semasa_unique ON public.ref_kategori_aduan USING btree (kod) WHERE (sah_hingga IS NULL);


--
-- Name: ref_kategori_aduan_nama_semasa_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX ref_kategori_aduan_nama_semasa_unique ON public.ref_kategori_aduan USING btree (lower((nama)::text)) WHERE (sah_hingga IS NULL);


--
-- Name: ref_kategori_guna_tanah_kod_sah_dari_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ref_kategori_guna_tanah_kod_sah_dari_index ON public.ref_kategori_guna_tanah USING btree (kod, sah_dari);


--
-- Name: ref_kategori_guna_tanah_kod_semasa_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX ref_kategori_guna_tanah_kod_semasa_unique ON public.ref_kategori_guna_tanah USING btree (kod) WHERE (sah_hingga IS NULL);


--
-- Name: ref_kategori_guna_tanah_nama_semasa_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX ref_kategori_guna_tanah_nama_semasa_unique ON public.ref_kategori_guna_tanah USING btree (lower((nama)::text)) WHERE (sah_hingga IS NULL);


--
-- Name: ref_klasifikasi_data_kod_sah_dari_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ref_klasifikasi_data_kod_sah_dari_index ON public.ref_klasifikasi_data USING btree (kod, sah_dari);


--
-- Name: ref_klasifikasi_data_kod_semasa_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX ref_klasifikasi_data_kod_semasa_unique ON public.ref_klasifikasi_data USING btree (kod) WHERE (sah_hingga IS NULL);


--
-- Name: ref_klasifikasi_data_nama_semasa_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX ref_klasifikasi_data_nama_semasa_unique ON public.ref_klasifikasi_data USING btree (lower((nama)::text)) WHERE (sah_hingga IS NULL);


--
-- Name: ref_mukim_daerah_kod_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ref_mukim_daerah_kod_index ON public.ref_mukim USING btree (daerah_kod);


--
-- Name: ref_mukim_kod_sah_dari_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ref_mukim_kod_sah_dari_index ON public.ref_mukim USING btree (kod, sah_dari);


--
-- Name: ref_mukim_kod_semasa_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX ref_mukim_kod_semasa_unique ON public.ref_mukim USING btree (kod) WHERE (sah_hingga IS NULL);


--
-- Name: ref_mukim_nama_semasa_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX ref_mukim_nama_semasa_unique ON public.ref_mukim USING btree (daerah_kod, lower((nama)::text)) WHERE (sah_hingga IS NULL);


--
-- Name: ref_negeri_kod_sah_dari_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ref_negeri_kod_sah_dari_index ON public.ref_negeri USING btree (kod, sah_dari);


--
-- Name: ref_negeri_kod_semasa_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX ref_negeri_kod_semasa_unique ON public.ref_negeri USING btree (kod) WHERE (sah_hingga IS NULL);


--
-- Name: ref_negeri_nama_semasa_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX ref_negeri_nama_semasa_unique ON public.ref_negeri USING btree (lower((nama)::text)) WHERE (sah_hingga IS NULL);


--
-- Name: ref_pbt_kod_sah_dari_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ref_pbt_kod_sah_dari_index ON public.ref_pbt USING btree (kod, sah_dari);


--
-- Name: ref_pbt_kod_semasa_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX ref_pbt_kod_semasa_unique ON public.ref_pbt USING btree (kod) WHERE (sah_hingga IS NULL);


--
-- Name: ref_pbt_nama_semasa_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX ref_pbt_nama_semasa_unique ON public.ref_pbt USING btree (lower((nama)::text)) WHERE (sah_hingga IS NULL);


--
-- Name: ref_pbt_negeri_kod_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ref_pbt_negeri_kod_index ON public.ref_pbt USING btree (negeri_kod);


--
-- Name: ref_sempadan_geometri_spatialindex; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ref_sempadan_geometri_spatialindex ON public.ref_sempadan USING gist (geometri);


--
-- Name: ref_tempat_ibadat_daerah_kod_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ref_tempat_ibadat_daerah_kod_index ON public.ref_tempat_ibadat USING btree (daerah_kod);


--
-- Name: ref_tempat_ibadat_geometri_spatialindex; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ref_tempat_ibadat_geometri_spatialindex ON public.ref_tempat_ibadat USING gist (geometri);


--
-- Name: ref_tempat_ibadat_mukim_kod_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ref_tempat_ibadat_mukim_kod_index ON public.ref_tempat_ibadat USING btree (mukim_kod);


--
-- Name: rekod_arkib_entiti_id_rekod_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX rekod_arkib_entiti_id_rekod_index ON public.rekod_arkib USING btree (entiti, id_rekod);


--
-- Name: rekod_arkib_status_arkib_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX rekod_arkib_status_arkib_index ON public.rekod_arkib USING btree (status_arkib);


--
-- Name: sesi_import_entiti_sasaran_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sesi_import_entiti_sasaran_created_at_index ON public.sesi_import USING btree (entiti_sasaran, created_at);


--
-- Name: sesi_session_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sesi_session_id_index ON public.sesi USING btree (session_id);


--
-- Name: sesi_user_id_tarikh_tamat_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sesi_user_id_tarikh_tamat_index ON public.sesi USING btree (user_id, tarikh_tamat);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: staging_lot_tanah_daerah_kod_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX staging_lot_tanah_daerah_kod_index ON public.staging_lot_tanah USING btree (daerah_kod);


--
-- Name: staging_lot_tanah_geometri_spatialindex; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX staging_lot_tanah_geometri_spatialindex ON public.staging_lot_tanah USING gist (geometri);


--
-- Name: staging_lot_tanah_mukim_kod_no_lot_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX staging_lot_tanah_mukim_kod_no_lot_index ON public.staging_lot_tanah USING btree (mukim_kod, no_lot);


--
-- Name: tugasan_kualiti_data_entiti_id_rekod_kod_isu_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tugasan_kualiti_data_entiti_id_rekod_kod_isu_status_index ON public.tugasan_kualiti_data USING btree (entiti, id_rekod, kod_isu, status);


--
-- Name: tugasan_kualiti_data_id_data_steward_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tugasan_kualiti_data_id_data_steward_status_index ON public.tugasan_kualiti_data USING btree (id_data_steward, status);


--
-- Name: users_bahagian_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX users_bahagian_index ON public.users USING btree (bahagian);


--
-- Name: users_status_akaun_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX users_status_akaun_index ON public.users USING btree (status_akaun);


--
-- Name: versi_rekod_induk_entiti_id_rekod_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX versi_rekod_induk_entiti_id_rekod_index ON public.versi_rekod_induk USING btree (entiti, id_rekod);


--
-- Name: wf_definisi_kod_aliran_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX wf_definisi_kod_aliran_index ON public.wf_definisi USING btree (kod_aliran);


--
-- Name: wf_instans_jenis_subjek_id_rekod_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX wf_instans_jenis_subjek_id_rekod_index ON public.wf_instans USING btree (jenis_subjek, id_rekod);


--
-- Name: wf_instans_kod_aliran_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX wf_instans_kod_aliran_index ON public.wf_instans USING btree (kod_aliran);


--
-- Name: wf_instans_kod_daerah_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX wf_instans_kod_daerah_index ON public.wf_instans USING btree (kod_daerah);


--
-- Name: wf_instans_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX wf_instans_status_index ON public.wf_instans USING btree (status);


--
-- Name: wf_sejarah_id_instans_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX wf_sejarah_id_instans_id_index ON public.wf_sejarah USING btree (id_instans, id);


--
-- Name: wf_tugasan_penerima_kunci_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX wf_tugasan_penerima_kunci_index ON public.wf_tugasan USING btree (penerima_kunci);


--
-- Name: wf_tugasan_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX wf_tugasan_status_index ON public.wf_tugasan USING btree (status);


--
-- Name: audit_arkib audit_arkib_hanya_tambah; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_arkib_hanya_tambah BEFORE DELETE OR UPDATE ON public.audit_arkib FOR EACH ROW EXECUTE FUNCTION public.aud_tolak_ubah();


--
-- Name: audit_arkib audit_arkib_tiada_truncate; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_arkib_tiada_truncate BEFORE TRUNCATE ON public.audit_arkib FOR EACH STATEMENT EXECUTE FUNCTION public.aud_tolak_ubah();


--
-- Name: audit_eksport audit_eksport_hanya_tambah; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_eksport_hanya_tambah BEFORE DELETE OR UPDATE ON public.audit_eksport FOR EACH ROW EXECUTE FUNCTION public.aud_tolak_ubah();


--
-- Name: audit_eksport audit_eksport_tiada_truncate; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_eksport_tiada_truncate BEFORE TRUNCATE ON public.audit_eksport FOR EACH STATEMENT EXECUTE FUNCTION public.aud_tolak_ubah();


--
-- Name: audit_integriti_semakan audit_integriti_semakan_hanya_tambah; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_integriti_semakan_hanya_tambah BEFORE DELETE OR UPDATE ON public.audit_integriti_semakan FOR EACH ROW EXECUTE FUNCTION public.aud_tolak_ubah();


--
-- Name: audit_integriti_semakan audit_integriti_semakan_tiada_truncate; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_integriti_semakan_tiada_truncate BEFORE TRUNCATE ON public.audit_integriti_semakan FOR EACH STATEMENT EXECUTE FUNCTION public.aud_tolak_ubah();


--
-- Name: audit_perubahan_medan audit_perubahan_medan_hanya_tambah; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_perubahan_medan_hanya_tambah BEFORE DELETE OR UPDATE ON public.audit_perubahan_medan FOR EACH ROW EXECUTE FUNCTION public.aud_tolak_ubah();


--
-- Name: audit_perubahan_medan audit_perubahan_medan_tiada_truncate; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_perubahan_medan_tiada_truncate BEFORE TRUNCATE ON public.audit_perubahan_medan FOR EACH STATEMENT EXECUTE FUNCTION public.aud_tolak_ubah();


--
-- Name: audit_rekod audit_rekod_hanya_tambah; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_rekod_hanya_tambah BEFORE DELETE OR UPDATE ON public.audit_rekod FOR EACH ROW EXECUTE FUNCTION public.aud_tolak_ubah();


--
-- Name: audit_rekod audit_rekod_tiada_truncate; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_rekod_tiada_truncate BEFORE TRUNCATE ON public.audit_rekod FOR EACH STATEMENT EXECUTE FUNCTION public.aud_tolak_ubah();


--
-- Name: adm_indeks_carian adm_indeks_carian_dikemas_kini_oleh_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_indeks_carian
    ADD CONSTRAINT adm_indeks_carian_dikemas_kini_oleh_foreign FOREIGN KEY (dikemas_kini_oleh) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: adm_integrasi adm_integrasi_dikemas_kini_oleh_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_integrasi
    ADD CONSTRAINT adm_integrasi_dikemas_kini_oleh_foreign FOREIGN KEY (dikemas_kini_oleh) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: adm_kod_rujukan adm_kod_rujukan_dikemas_kini_oleh_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_kod_rujukan
    ADD CONSTRAINT adm_kod_rujukan_dikemas_kini_oleh_foreign FOREIGN KEY (dikemas_kini_oleh) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: adm_kod_rujukan_sejarah adm_kod_rujukan_sejarah_diubah_oleh_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_kod_rujukan_sejarah
    ADD CONSTRAINT adm_kod_rujukan_sejarah_diubah_oleh_foreign FOREIGN KEY (diubah_oleh) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: adm_kod_rujukan_sejarah adm_kod_rujukan_sejarah_id_kod_rujukan_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_kod_rujukan_sejarah
    ADD CONSTRAINT adm_kod_rujukan_sejarah_id_kod_rujukan_foreign FOREIGN KEY (id_kod_rujukan) REFERENCES public.adm_kod_rujukan(id) ON DELETE CASCADE;


--
-- Name: adm_konfig_kelulusan adm_konfig_kelulusan_id_pelulus_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_konfig_kelulusan
    ADD CONSTRAINT adm_konfig_kelulusan_id_pelulus_foreign FOREIGN KEY (id_pelulus) REFERENCES public.users(id);


--
-- Name: adm_konfig_kelulusan adm_konfig_kelulusan_id_pemohon_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_konfig_kelulusan
    ADD CONSTRAINT adm_konfig_kelulusan_id_pemohon_foreign FOREIGN KEY (id_pemohon) REFERENCES public.users(id);


--
-- Name: adm_parameter adm_parameter_dikemas_kini_oleh_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_parameter
    ADD CONSTRAINT adm_parameter_dikemas_kini_oleh_foreign FOREIGN KEY (dikemas_kini_oleh) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: adm_rahsia adm_rahsia_dikemas_kini_oleh_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_rahsia
    ADD CONSTRAINT adm_rahsia_dikemas_kini_oleh_foreign FOREIGN KEY (dikemas_kini_oleh) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: adm_sinonim_carian adm_sinonim_carian_dikemas_kini_oleh_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_sinonim_carian
    ADD CONSTRAINT adm_sinonim_carian_dikemas_kini_oleh_foreign FOREIGN KEY (dikemas_kini_oleh) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: adm_templat adm_templat_dikemas_kini_oleh_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_templat
    ADD CONSTRAINT adm_templat_dikemas_kini_oleh_foreign FOREIGN KEY (dikemas_kini_oleh) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: adm_templat_versi adm_templat_versi_dicipta_oleh_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_templat_versi
    ADD CONSTRAINT adm_templat_versi_dicipta_oleh_foreign FOREIGN KEY (dicipta_oleh) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: adm_templat_versi adm_templat_versi_id_templat_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_templat_versi
    ADD CONSTRAINT adm_templat_versi_id_templat_foreign FOREIGN KEY (id_templat) REFERENCES public.adm_templat(id) ON DELETE CASCADE;


--
-- Name: adm_tugasan_berjadual adm_tugasan_berjadual_dikemas_kini_oleh_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_tugasan_berjadual
    ADD CONSTRAINT adm_tugasan_berjadual_dikemas_kini_oleh_foreign FOREIGN KEY (dikemas_kini_oleh) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: adm_tugasan_larian adm_tugasan_larian_id_pengguna_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_tugasan_larian
    ADD CONSTRAINT adm_tugasan_larian_id_pengguna_foreign FOREIGN KEY (id_pengguna) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: adm_tugasan_larian adm_tugasan_larian_id_tugasan_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adm_tugasan_larian
    ADD CONSTRAINT adm_tugasan_larian_id_tugasan_foreign FOREIGN KEY (id_tugasan) REFERENCES public.adm_tugasan_berjadual(id) ON DELETE CASCADE;


--
-- Name: aduan aduan_id_pegawai_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.aduan
    ADD CONSTRAINT aduan_id_pegawai_foreign FOREIGN KEY (id_pegawai) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: aduan aduan_lot_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.aduan
    ADD CONSTRAINT aduan_lot_id_foreign FOREIGN KEY (lot_id) REFERENCES public.lot_tanah(id);


--
-- Name: amaran_keselamatan amaran_keselamatan_disemak_oleh_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.amaran_keselamatan
    ADD CONSTRAINT amaran_keselamatan_disemak_oleh_foreign FOREIGN KEY (disemak_oleh) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: amaran_keselamatan amaran_keselamatan_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.amaran_keselamatan
    ADD CONSTRAINT amaran_keselamatan_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: baris_import baris_import_id_sesi_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.baris_import
    ADD CONSTRAINT baris_import_id_sesi_foreign FOREIGN KEY (id_sesi) REFERENCES public.sesi_import(id) ON DELETE CASCADE;


--
-- Name: cadangan_duplikasi cadangan_duplikasi_id_data_steward_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cadangan_duplikasi
    ADD CONSTRAINT cadangan_duplikasi_id_data_steward_foreign FOREIGN KEY (id_data_steward) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: cukai cukai_lot_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cukai
    ADD CONSTRAINT cukai_lot_id_foreign FOREIGN KEY (lot_id) REFERENCES public.lot_tanah(id);


--
-- Name: daftar_stewardship_data daftar_stewardship_data_id_data_owner_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.daftar_stewardship_data
    ADD CONSTRAINT daftar_stewardship_data_id_data_owner_foreign FOREIGN KEY (id_data_owner) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: daftar_stewardship_data daftar_stewardship_data_id_data_steward_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.daftar_stewardship_data
    ADD CONSTRAINT daftar_stewardship_data_id_data_steward_foreign FOREIGN KEY (id_data_steward) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: data_spatial data_spatial_id_pegawai_pengesah_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_spatial
    ADD CONSTRAINT data_spatial_id_pegawai_pengesah_foreign FOREIGN KEY (id_pegawai_pengesah) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: data_spatial data_spatial_id_pemuat_naik_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_spatial
    ADD CONSTRAINT data_spatial_id_pemuat_naik_foreign FOREIGN KEY (id_pemuat_naik) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: data_spatial data_spatial_lot_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_spatial
    ADD CONSTRAINT data_spatial_lot_id_foreign FOREIGN KEY (lot_id) REFERENCES public.lot_tanah(id) ON DELETE CASCADE;


--
-- Name: dokumen_arkib dokumen_arkib_diarkib_oleh_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dokumen_arkib
    ADD CONSTRAINT dokumen_arkib_diarkib_oleh_foreign FOREIGN KEY (diarkib_oleh) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: dokumen_arkib dokumen_arkib_dokumen_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dokumen_arkib
    ADD CONSTRAINT dokumen_arkib_dokumen_id_foreign FOREIGN KEY (dokumen_id) REFERENCES public.dokumen(id) ON DELETE CASCADE;


--
-- Name: dokumen dokumen_id_pencipta_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dokumen
    ADD CONSTRAINT dokumen_id_pencipta_foreign FOREIGN KEY (id_pencipta) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: dokumen_imbasan dokumen_imbasan_dokumen_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dokumen_imbasan
    ADD CONSTRAINT dokumen_imbasan_dokumen_id_foreign FOREIGN KEY (dokumen_id) REFERENCES public.dokumen(id) ON DELETE SET NULL;


--
-- Name: dokumen_imbasan dokumen_imbasan_kuarantin_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dokumen_imbasan
    ADD CONSTRAINT dokumen_imbasan_kuarantin_id_foreign FOREIGN KEY (kuarantin_id) REFERENCES public.dokumen_kuarantin(id) ON DELETE SET NULL;


--
-- Name: dokumen dokumen_kategori_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dokumen
    ADD CONSTRAINT dokumen_kategori_id_foreign FOREIGN KEY (kategori_id) REFERENCES public.dokumen_kategori(id);


--
-- Name: dokumen_kebenaran dokumen_kebenaran_diberi_oleh_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dokumen_kebenaran
    ADD CONSTRAINT dokumen_kebenaran_diberi_oleh_foreign FOREIGN KEY (diberi_oleh) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: dokumen_kebenaran dokumen_kebenaran_dokumen_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dokumen_kebenaran
    ADD CONSTRAINT dokumen_kebenaran_dokumen_id_foreign FOREIGN KEY (dokumen_id) REFERENCES public.dokumen(id) ON DELETE CASCADE;


--
-- Name: dokumen_kuarantin dokumen_kuarantin_dimuat_naik_oleh_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dokumen_kuarantin
    ADD CONSTRAINT dokumen_kuarantin_dimuat_naik_oleh_foreign FOREIGN KEY (dimuat_naik_oleh) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: dokumen_medan_metadata dokumen_medan_metadata_kategori_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dokumen_medan_metadata
    ADD CONSTRAINT dokumen_medan_metadata_kategori_id_foreign FOREIGN KEY (kategori_id) REFERENCES public.dokumen_kategori(id) ON DELETE CASCADE;


--
-- Name: dokumen_pelupusan dokumen_pelupusan_dimohon_oleh_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dokumen_pelupusan
    ADD CONSTRAINT dokumen_pelupusan_dimohon_oleh_foreign FOREIGN KEY (dimohon_oleh) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: dokumen_pelupusan dokumen_pelupusan_dokumen_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dokumen_pelupusan
    ADD CONSTRAINT dokumen_pelupusan_dokumen_id_foreign FOREIGN KEY (dokumen_id) REFERENCES public.dokumen(id) ON DELETE CASCADE;


--
-- Name: dokumen dokumen_pemilik_proses_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dokumen
    ADD CONSTRAINT dokumen_pemilik_proses_id_foreign FOREIGN KEY (pemilik_proses_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: dokumen_versi dokumen_versi_dimuat_naik_oleh_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dokumen_versi
    ADD CONSTRAINT dokumen_versi_dimuat_naik_oleh_foreign FOREIGN KEY (dimuat_naik_oleh) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: dokumen_versi dokumen_versi_dokumen_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dokumen_versi
    ADD CONSTRAINT dokumen_versi_dokumen_id_foreign FOREIGN KEY (dokumen_id) REFERENCES public.dokumen(id) ON DELETE CASCADE;


--
-- Name: jadual_retensi jadual_retensi_id_pelulus_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jadual_retensi
    ADD CONSTRAINT jadual_retensi_id_pelulus_foreign FOREIGN KEY (id_pelulus) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: kata_laluan_sejarah kata_laluan_sejarah_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kata_laluan_sejarah
    ADD CONSTRAINT kata_laluan_sejarah_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: kes_mahkamah_lot kes_mahkamah_lot_kes_mahkamah_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kes_mahkamah_lot
    ADD CONSTRAINT kes_mahkamah_lot_kes_mahkamah_id_foreign FOREIGN KEY (kes_mahkamah_id) REFERENCES public.kes_mahkamah(id) ON DELETE CASCADE;


--
-- Name: kes_mahkamah_lot kes_mahkamah_lot_lot_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kes_mahkamah_lot
    ADD CONSTRAINT kes_mahkamah_lot_lot_id_foreign FOREIGN KEY (lot_id) REFERENCES public.lot_tanah(id);


--
-- Name: kontrak_pajakan kontrak_pajakan_lot_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kontrak_pajakan
    ADD CONSTRAINT kontrak_pajakan_lot_id_foreign FOREIGN KEY (lot_id) REFERENCES public.lot_tanah(id);


--
-- Name: kunci_api_pro kunci_api_pro_dibatalkan_oleh_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kunci_api_pro
    ADD CONSTRAINT kunci_api_pro_dibatalkan_oleh_foreign FOREIGN KEY (dibatalkan_oleh) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: kunci_api_pro kunci_api_pro_id_pemilik_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kunci_api_pro
    ADD CONSTRAINT kunci_api_pro_id_pemilik_foreign FOREIGN KEY (id_pemilik) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: log_akses_profil log_akses_profil_id_pengguna_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.log_akses_profil
    ADD CONSTRAINT log_akses_profil_id_pengguna_foreign FOREIGN KEY (id_pengguna) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: log_akses_profil log_akses_profil_lot_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.log_akses_profil
    ADD CONSTRAINT log_akses_profil_lot_id_foreign FOREIGN KEY (lot_id) REFERENCES public.lot_tanah(id) ON DELETE CASCADE;


--
-- Name: log_log_masuk log_log_masuk_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.log_log_masuk
    ADD CONSTRAINT log_log_masuk_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: lot_tanah lot_tanah_id_data_owner_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lot_tanah
    ADD CONSTRAINT lot_tanah_id_data_owner_foreign FOREIGN KEY (id_data_owner) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: lot_tanah lot_tanah_id_data_steward_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lot_tanah
    ADD CONSTRAINT lot_tanah_id_data_steward_foreign FOREIGN KEY (id_data_steward) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: lot_tanah lot_tanah_id_pencipta_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lot_tanah
    ADD CONSTRAINT lot_tanah_id_pencipta_foreign FOREIGN KEY (id_pencipta) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: ntf_eskalasi_log ntf_eskalasi_log_mesej_eskalasi_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_eskalasi_log
    ADD CONSTRAINT ntf_eskalasi_log_mesej_eskalasi_id_foreign FOREIGN KEY (mesej_eskalasi_id) REFERENCES public.ntf_mesej(id) ON DELETE SET NULL;


--
-- Name: ntf_eskalasi_log ntf_eskalasi_log_mesej_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_eskalasi_log
    ADD CONSTRAINT ntf_eskalasi_log_mesej_id_foreign FOREIGN KEY (mesej_id) REFERENCES public.ntf_mesej(id);


--
-- Name: ntf_eskalasi_log ntf_eskalasi_log_penerima_asal_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_eskalasi_log
    ADD CONSTRAINT ntf_eskalasi_log_penerima_asal_id_foreign FOREIGN KEY (penerima_asal_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: ntf_eskalasi_log ntf_eskalasi_log_penyelia_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_eskalasi_log
    ADD CONSTRAINT ntf_eskalasi_log_penyelia_id_foreign FOREIGN KEY (penyelia_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: ntf_eskalasi_log ntf_eskalasi_log_status_pengguna_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_eskalasi_log
    ADD CONSTRAINT ntf_eskalasi_log_status_pengguna_id_foreign FOREIGN KEY (status_pengguna_id) REFERENCES public.ntf_status_pengguna(id) ON DELETE SET NULL;


--
-- Name: ntf_keutamaan_pengguna ntf_keutamaan_pengguna_jenis_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_keutamaan_pengguna
    ADD CONSTRAINT ntf_keutamaan_pengguna_jenis_id_foreign FOREIGN KEY (jenis_id) REFERENCES public.ntf_jenis(id) ON DELETE CASCADE;


--
-- Name: ntf_keutamaan_pengguna ntf_keutamaan_pengguna_pengguna_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_keutamaan_pengguna
    ADD CONSTRAINT ntf_keutamaan_pengguna_pengguna_id_foreign FOREIGN KEY (pengguna_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: ntf_mesej ntf_mesej_dicipta_oleh_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_mesej
    ADD CONSTRAINT ntf_mesej_dicipta_oleh_foreign FOREIGN KEY (dicipta_oleh) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: ntf_mesej ntf_mesej_jenis_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_mesej
    ADD CONSTRAINT ntf_mesej_jenis_id_foreign FOREIGN KEY (jenis_id) REFERENCES public.ntf_jenis(id);


--
-- Name: ntf_mesej ntf_mesej_templat_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_mesej
    ADD CONSTRAINT ntf_mesej_templat_id_foreign FOREIGN KEY (templat_id) REFERENCES public.ntf_templat(id) ON DELETE SET NULL;


--
-- Name: ntf_penerima_peraturan ntf_penerima_peraturan_jenis_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_penerima_peraturan
    ADD CONSTRAINT ntf_penerima_peraturan_jenis_id_foreign FOREIGN KEY (jenis_id) REFERENCES public.ntf_jenis(id) ON DELETE CASCADE;


--
-- Name: ntf_penghantaran ntf_penghantaran_mesej_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_penghantaran
    ADD CONSTRAINT ntf_penghantaran_mesej_id_foreign FOREIGN KEY (mesej_id) REFERENCES public.ntf_mesej(id);


--
-- Name: ntf_penghantaran ntf_penghantaran_penerima_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_penghantaran
    ADD CONSTRAINT ntf_penghantaran_penerima_id_foreign FOREIGN KEY (penerima_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: ntf_status_pengguna ntf_status_pengguna_mesej_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_status_pengguna
    ADD CONSTRAINT ntf_status_pengguna_mesej_id_foreign FOREIGN KEY (mesej_id) REFERENCES public.ntf_mesej(id) ON DELETE CASCADE;


--
-- Name: ntf_status_pengguna ntf_status_pengguna_pengguna_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_status_pengguna
    ADD CONSTRAINT ntf_status_pengguna_pengguna_id_foreign FOREIGN KEY (pengguna_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: ntf_templat ntf_templat_dicipta_oleh_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_templat
    ADD CONSTRAINT ntf_templat_dicipta_oleh_foreign FOREIGN KEY (dicipta_oleh) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: ntf_templat ntf_templat_jenis_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_templat
    ADD CONSTRAINT ntf_templat_jenis_id_foreign FOREIGN KEY (jenis_id) REFERENCES public.ntf_jenis(id) ON DELETE CASCADE;


--
-- Name: pemetaan_import pemetaan_import_id_pencipta_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pemetaan_import
    ADD CONSTRAINT pemetaan_import_id_pencipta_foreign FOREIGN KEY (id_pencipta) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: pemetaan_tuntutan pemetaan_tuntutan_peranan_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pemetaan_tuntutan
    ADD CONSTRAINT pemetaan_tuntutan_peranan_id_foreign FOREIGN KEY (peranan_id) REFERENCES public.peranan(id) ON DELETE CASCADE;


--
-- Name: pengguna_peranan pengguna_peranan_diberi_oleh_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pengguna_peranan
    ADD CONSTRAINT pengguna_peranan_diberi_oleh_foreign FOREIGN KEY (diberi_oleh) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: pengguna_peranan pengguna_peranan_peranan_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pengguna_peranan
    ADD CONSTRAINT pengguna_peranan_peranan_id_foreign FOREIGN KEY (peranan_id) REFERENCES public.peranan(id) ON DELETE CASCADE;


--
-- Name: pengguna_peranan pengguna_peranan_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pengguna_peranan
    ADD CONSTRAINT pengguna_peranan_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: pengguna_skop pengguna_skop_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pengguna_skop
    ADD CONSTRAINT pengguna_skop_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: penilaian penilaian_lot_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.penilaian
    ADD CONSTRAINT penilaian_lot_id_foreign FOREIGN KEY (lot_id) REFERENCES public.lot_tanah(id);


--
-- Name: peranan_kebenaran peranan_kebenaran_peranan_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.peranan_kebenaran
    ADD CONSTRAINT peranan_kebenaran_peranan_id_foreign FOREIGN KEY (peranan_id) REFERENCES public.peranan(id) ON DELETE CASCADE;


--
-- Name: peranan_skop peranan_skop_peranan_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.peranan_skop
    ADD CONSTRAINT peranan_skop_peranan_id_foreign FOREIGN KEY (peranan_id) REFERENCES public.peranan(id) ON DELETE CASCADE;


--
-- Name: polisi_keselamatan polisi_keselamatan_dikemas_kini_oleh_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.polisi_keselamatan
    ADD CONSTRAINT polisi_keselamatan_dikemas_kini_oleh_foreign FOREIGN KEY (dikemas_kini_oleh) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: ralat_import ralat_import_id_sesi_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ralat_import
    ADD CONSTRAINT ralat_import_id_sesi_foreign FOREIGN KEY (id_sesi) REFERENCES public.sesi_import(id) ON DELETE CASCADE;


--
-- Name: recert_keputusan recert_keputusan_disemak_oleh_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recert_keputusan
    ADD CONSTRAINT recert_keputusan_disemak_oleh_foreign FOREIGN KEY (disemak_oleh) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: recert_keputusan recert_keputusan_kitaran_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recert_keputusan
    ADD CONSTRAINT recert_keputusan_kitaran_id_foreign FOREIGN KEY (kitaran_id) REFERENCES public.recert_kitaran(id) ON DELETE CASCADE;


--
-- Name: recert_keputusan recert_keputusan_peranan_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recert_keputusan
    ADD CONSTRAINT recert_keputusan_peranan_id_foreign FOREIGN KEY (peranan_id) REFERENCES public.peranan(id) ON DELETE CASCADE;


--
-- Name: recert_keputusan recert_keputusan_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recert_keputusan
    ADD CONSTRAINT recert_keputusan_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: recert_kitaran recert_kitaran_dicipta_oleh_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recert_kitaran
    ADD CONSTRAINT recert_kitaran_dicipta_oleh_foreign FOREIGN KEY (dicipta_oleh) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: rekod_arkib rekod_arkib_id_jadual_retensi_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rekod_arkib
    ADD CONSTRAINT rekod_arkib_id_jadual_retensi_foreign FOREIGN KEY (id_jadual_retensi) REFERENCES public.jadual_retensi(id) ON DELETE SET NULL;


--
-- Name: rekod_arkib rekod_arkib_id_pelulus_arkib_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rekod_arkib
    ADD CONSTRAINT rekod_arkib_id_pelulus_arkib_foreign FOREIGN KEY (id_pelulus_arkib) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: rekod_arkib rekod_arkib_id_pemohon_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rekod_arkib
    ADD CONSTRAINT rekod_arkib_id_pemohon_foreign FOREIGN KEY (id_pemohon) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: rekod_arkib rekod_arkib_id_pemulih_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rekod_arkib
    ADD CONSTRAINT rekod_arkib_id_pemulih_foreign FOREIGN KEY (id_pemulih) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: sesi sesi_ditamatkan_oleh_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sesi
    ADD CONSTRAINT sesi_ditamatkan_oleh_foreign FOREIGN KEY (ditamatkan_oleh) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: sesi_import sesi_import_id_pengguna_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sesi_import
    ADD CONSTRAINT sesi_import_id_pengguna_foreign FOREIGN KEY (id_pengguna) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: sesi sesi_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sesi
    ADD CONSTRAINT sesi_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: staging_hak_milik staging_hak_milik_migrasi_larian_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.staging_hak_milik
    ADD CONSTRAINT staging_hak_milik_migrasi_larian_id_foreign FOREIGN KEY (migrasi_larian_id) REFERENCES public.migrasi_larian(id);


--
-- Name: staging_hak_milik staging_hak_milik_staging_lot_tanah_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.staging_hak_milik
    ADD CONSTRAINT staging_hak_milik_staging_lot_tanah_id_foreign FOREIGN KEY (staging_lot_tanah_id) REFERENCES public.staging_lot_tanah(id) ON DELETE CASCADE;


--
-- Name: staging_lot_tanah staging_lot_tanah_migrasi_larian_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.staging_lot_tanah
    ADD CONSTRAINT staging_lot_tanah_migrasi_larian_id_foreign FOREIGN KEY (migrasi_larian_id) REFERENCES public.migrasi_larian(id);


--
-- Name: staging_pemilik staging_pemilik_migrasi_larian_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.staging_pemilik
    ADD CONSTRAINT staging_pemilik_migrasi_larian_id_foreign FOREIGN KEY (migrasi_larian_id) REFERENCES public.migrasi_larian(id);


--
-- Name: staging_pemilik staging_pemilik_staging_hak_milik_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.staging_pemilik
    ADD CONSTRAINT staging_pemilik_staging_hak_milik_id_foreign FOREIGN KEY (staging_hak_milik_id) REFERENCES public.staging_hak_milik(id) ON DELETE CASCADE;


--
-- Name: status_akaun_log status_akaun_log_dilakukan_oleh_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.status_akaun_log
    ADD CONSTRAINT status_akaun_log_dilakukan_oleh_foreign FOREIGN KEY (dilakukan_oleh) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: status_akaun_log status_akaun_log_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.status_akaun_log
    ADD CONSTRAINT status_akaun_log_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: tugasan_kualiti_data tugasan_kualiti_data_diselesaikan_oleh_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tugasan_kualiti_data
    ADD CONSTRAINT tugasan_kualiti_data_diselesaikan_oleh_foreign FOREIGN KEY (diselesaikan_oleh) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: tugasan_kualiti_data tugasan_kualiti_data_id_data_steward_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tugasan_kualiti_data
    ADD CONSTRAINT tugasan_kualiti_data_id_data_steward_foreign FOREIGN KEY (id_data_steward) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: tugasan_kualiti_data tugasan_kualiti_data_id_stewardship_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tugasan_kualiti_data
    ADD CONSTRAINT tugasan_kualiti_data_id_stewardship_foreign FOREIGN KEY (id_stewardship) REFERENCES public.daftar_stewardship_data(id) ON DELETE SET NULL;


--
-- Name: versi_rekod_induk versi_rekod_induk_id_pengesah_kedua_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.versi_rekod_induk
    ADD CONSTRAINT versi_rekod_induk_id_pengesah_kedua_foreign FOREIGN KEY (id_pengesah_kedua) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: versi_rekod_induk versi_rekod_induk_id_pengguna_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.versi_rekod_induk
    ADD CONSTRAINT versi_rekod_induk_id_pengguna_foreign FOREIGN KEY (id_pengguna) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: wf_definisi wf_definisi_diluluskan_oleh_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_definisi
    ADD CONSTRAINT wf_definisi_diluluskan_oleh_foreign FOREIGN KEY (diluluskan_oleh) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: wf_definisi wf_definisi_diminta_aktif_oleh_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_definisi
    ADD CONSTRAINT wf_definisi_diminta_aktif_oleh_foreign FOREIGN KEY (diminta_aktif_oleh) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: wf_definisi wf_definisi_id_asal_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_definisi
    ADD CONSTRAINT wf_definisi_id_asal_foreign FOREIGN KEY (id_asal) REFERENCES public.wf_definisi(id) ON DELETE SET NULL;


--
-- Name: wf_definisi wf_definisi_id_pencipta_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_definisi
    ADD CONSTRAINT wf_definisi_id_pencipta_foreign FOREIGN KEY (id_pencipta) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: wf_definisi_log wf_definisi_log_id_definisi_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_definisi_log
    ADD CONSTRAINT wf_definisi_log_id_definisi_foreign FOREIGN KEY (id_definisi) REFERENCES public.wf_definisi(id) ON DELETE CASCADE;


--
-- Name: wf_definisi_log wf_definisi_log_id_pengguna_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_definisi_log
    ADD CONSTRAINT wf_definisi_log_id_pengguna_foreign FOREIGN KEY (id_pengguna) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: wf_eskalasi wf_eskalasi_id_peringkat_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_eskalasi
    ADD CONSTRAINT wf_eskalasi_id_peringkat_foreign FOREIGN KEY (id_peringkat) REFERENCES public.wf_peringkat(id) ON DELETE CASCADE;


--
-- Name: wf_eskalasi_log wf_eskalasi_log_id_tugasan_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_eskalasi_log
    ADD CONSTRAINT wf_eskalasi_log_id_tugasan_foreign FOREIGN KEY (id_tugasan) REFERENCES public.wf_tugasan(id) ON DELETE CASCADE;


--
-- Name: wf_instans wf_instans_id_definisi_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_instans
    ADD CONSTRAINT wf_instans_id_definisi_foreign FOREIGN KEY (id_definisi) REFERENCES public.wf_definisi(id);


--
-- Name: wf_instans wf_instans_id_pemohon_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_instans
    ADD CONSTRAINT wf_instans_id_pemohon_foreign FOREIGN KEY (id_pemohon) REFERENCES public.users(id);


--
-- Name: wf_instans wf_instans_peringkat_semasa_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_instans
    ADD CONSTRAINT wf_instans_peringkat_semasa_foreign FOREIGN KEY (peringkat_semasa) REFERENCES public.wf_peringkat(id) ON DELETE SET NULL;


--
-- Name: wf_kumpulan_ahli wf_kumpulan_ahli_id_kumpulan_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_kumpulan_ahli
    ADD CONSTRAINT wf_kumpulan_ahli_id_kumpulan_foreign FOREIGN KEY (id_kumpulan) REFERENCES public.wf_kumpulan(id) ON DELETE CASCADE;


--
-- Name: wf_kumpulan_ahli wf_kumpulan_ahli_id_pengguna_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_kumpulan_ahli
    ADD CONSTRAINT wf_kumpulan_ahli_id_pengguna_foreign FOREIGN KEY (id_pengguna) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: wf_pelulus wf_pelulus_id_peringkat_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_pelulus
    ADD CONSTRAINT wf_pelulus_id_peringkat_foreign FOREIGN KEY (id_peringkat) REFERENCES public.wf_peringkat(id) ON DELETE CASCADE;


--
-- Name: wf_pemangku wf_pemangku_dicipta_oleh_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_pemangku
    ADD CONSTRAINT wf_pemangku_dicipta_oleh_foreign FOREIGN KEY (dicipta_oleh) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: wf_pemangku wf_pemangku_id_pemangku_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_pemangku
    ADD CONSTRAINT wf_pemangku_id_pemangku_foreign FOREIGN KEY (id_pemangku) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: wf_pemangku wf_pemangku_id_pengguna_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_pemangku
    ADD CONSTRAINT wf_pemangku_id_pengguna_foreign FOREIGN KEY (id_pengguna) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: wf_peralihan wf_peralihan_id_definisi_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_peralihan
    ADD CONSTRAINT wf_peralihan_id_definisi_foreign FOREIGN KEY (id_definisi) REFERENCES public.wf_definisi(id) ON DELETE CASCADE;


--
-- Name: wf_peringkat wf_peringkat_id_definisi_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_peringkat
    ADD CONSTRAINT wf_peringkat_id_definisi_foreign FOREIGN KEY (id_definisi) REFERENCES public.wf_definisi(id) ON DELETE CASCADE;


--
-- Name: wf_pindah_versi wf_pindah_versi_diminta_oleh_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_pindah_versi
    ADD CONSTRAINT wf_pindah_versi_diminta_oleh_foreign FOREIGN KEY (diminta_oleh) REFERENCES public.users(id);


--
-- Name: wf_pindah_versi wf_pindah_versi_diputuskan_oleh_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_pindah_versi
    ADD CONSTRAINT wf_pindah_versi_diputuskan_oleh_foreign FOREIGN KEY (diputuskan_oleh) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: wf_pindah_versi wf_pindah_versi_id_definisi_asal_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_pindah_versi
    ADD CONSTRAINT wf_pindah_versi_id_definisi_asal_foreign FOREIGN KEY (id_definisi_asal) REFERENCES public.wf_definisi(id);


--
-- Name: wf_pindah_versi wf_pindah_versi_id_definisi_baru_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_pindah_versi
    ADD CONSTRAINT wf_pindah_versi_id_definisi_baru_foreign FOREIGN KEY (id_definisi_baru) REFERENCES public.wf_definisi(id);


--
-- Name: wf_pindah_versi wf_pindah_versi_id_instans_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_pindah_versi
    ADD CONSTRAINT wf_pindah_versi_id_instans_foreign FOREIGN KEY (id_instans) REFERENCES public.wf_instans(id) ON DELETE CASCADE;


--
-- Name: wf_sejarah wf_sejarah_id_bagi_pihak_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_sejarah
    ADD CONSTRAINT wf_sejarah_id_bagi_pihak_foreign FOREIGN KEY (id_bagi_pihak) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: wf_sejarah wf_sejarah_id_instans_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_sejarah
    ADD CONSTRAINT wf_sejarah_id_instans_foreign FOREIGN KEY (id_instans) REFERENCES public.wf_instans(id) ON DELETE CASCADE;


--
-- Name: wf_sejarah wf_sejarah_id_pengguna_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_sejarah
    ADD CONSTRAINT wf_sejarah_id_pengguna_foreign FOREIGN KEY (id_pengguna) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: wf_sejarah wf_sejarah_id_peringkat_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_sejarah
    ADD CONSTRAINT wf_sejarah_id_peringkat_foreign FOREIGN KEY (id_peringkat) REFERENCES public.wf_peringkat(id) ON DELETE SET NULL;


--
-- Name: wf_sejarah wf_sejarah_id_tugasan_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_sejarah
    ADD CONSTRAINT wf_sejarah_id_tugasan_foreign FOREIGN KEY (id_tugasan) REFERENCES public.wf_tugasan(id) ON DELETE SET NULL;


--
-- Name: wf_susulan wf_susulan_id_definisi_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_susulan
    ADD CONSTRAINT wf_susulan_id_definisi_foreign FOREIGN KEY (id_definisi) REFERENCES public.wf_definisi(id) ON DELETE CASCADE;


--
-- Name: wf_syarat wf_syarat_id_peralihan_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_syarat
    ADD CONSTRAINT wf_syarat_id_peralihan_foreign FOREIGN KEY (id_peralihan) REFERENCES public.wf_peralihan(id) ON DELETE CASCADE;


--
-- Name: wf_tugasan wf_tugasan_diselesaikan_oleh_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_tugasan
    ADD CONSTRAINT wf_tugasan_diselesaikan_oleh_foreign FOREIGN KEY (diselesaikan_oleh) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: wf_tugasan wf_tugasan_id_instans_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_tugasan
    ADD CONSTRAINT wf_tugasan_id_instans_foreign FOREIGN KEY (id_instans) REFERENCES public.wf_instans(id) ON DELETE CASCADE;


--
-- Name: wf_tugasan wf_tugasan_id_pelulus_asal_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_tugasan
    ADD CONSTRAINT wf_tugasan_id_pelulus_asal_foreign FOREIGN KEY (id_pelulus_asal) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: wf_tugasan wf_tugasan_id_pelulus_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_tugasan
    ADD CONSTRAINT wf_tugasan_id_pelulus_foreign FOREIGN KEY (id_pelulus) REFERENCES public.wf_pelulus(id) ON DELETE SET NULL;


--
-- Name: wf_tugasan wf_tugasan_id_peringkat_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wf_tugasan
    ADD CONSTRAINT wf_tugasan_id_peringkat_foreign FOREIGN KEY (id_peringkat) REFERENCES public.wf_peringkat(id);


--
-- PostgreSQL database dump complete
--

\unrestrict ZxcWFrd7GPxo2pZHeseAcfzuN63hWCJWdtcxYkfN2TIRd8qPbpSxDnlmiZJagHB

