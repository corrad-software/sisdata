<?php

namespace App\Modules\Pws\Http\Controllers\Awam;

use App\Modules\Kba\Contracts\KnowledgeBase;
use App\Modules\Pws\Http\Middleware\AuthenticateKlien;
use App\Modules\Pws\Models\Kandungan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Public FAQ / knowledge search (scope "baca"): MOD-KBA's KnowledgeBase with
 * user NULL — only Awam-classified sources are searched (SRS-KBA, SRS-PWS
 * integration "sumber kandungan soal-jawab awam"), on the local model only.
 * Internal links (`pautan` to /admin/…) are never passed to the portal.
 */
class SoalanLazimController
{
    public function __construct(private KnowledgeBase $kb) {}

    public function carian(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:200']]);
        $faq = Kandungan::query()->where('status_terbitan', 'diterbitkan')->where('jenis', 'soal_jawab')
            ->where(fn ($w) => $w->where('tajuk_bm', 'ilike', '%'.$this->like($data['q']).'%')->orWhere('tajuk_bi', 'ilike', '%'.$this->like($data['q']).'%')->orWhere('kandungan_bm', 'ilike', '%'.$this->like($data['q']).'%'))
            ->limit(10)->get()->map(fn (Kandungan $k) => ['slug' => $k->slug, 'tajuk_bm' => $k->tajuk_bm, 'tajuk_bi' => $k->tajuk_bi, 'kandungan_bm' => $k->kandungan_bm, 'kandungan_bi' => $k->kandungan_bi])->all();

        $kb = array_map(fn (array $r) => ['dokumen' => $r['dokumen'], 'seksyen' => $r['seksyen'], 'petikan' => $r['petikan']], $this->kb->search($data['q'], null, 5));

        return response()->json(['data' => ['soalan_lazim' => $faq, 'pangkalan_pengetahuan' => $kb]]);
    }

    public function tanya(Request $request): JsonResponse
    {
        $ip = AuthenticateKlien::endUserIp($request);
        $key = 'pws:ip:tanya:'.sha1($ip);
        if (RateLimiter::tooManyAttempts($key, 20)) {
            abort(429, __('pws::pws.ralat.had_kadar'));
        }
        RateLimiter::hit($key, 3600);
        $data = $request->validate(['soalan' => ['required', 'string', 'min:5', 'max:500']]);
        $answer = $this->kb->answer($data['soalan'], null);

        return response()->json(['data' => [
            'jawapan' => $answer['jawapan'],
            'cukup_sumber' => $answer['cukup_sumber'],
            'sumber' => array_map(fn (array $s) => ['dokumen' => $s['dokumen'], 'seksyen' => $s['seksyen'], 'petikan' => $s['petikan']], $answer['sumber']),
            'penafian' => __('pws::pws.kba.penafian'),
        ]]);
    }

    private function like(string $q): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
    }
}
