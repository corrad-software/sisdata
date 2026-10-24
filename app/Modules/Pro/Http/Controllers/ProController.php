<?php

namespace App\Modules\Pro\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Pro\Models\LotTanah;
use App\Modules\Pro\Services\LotQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

abstract class ProController extends Controller
{
    protected function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    /** A lot inside the user's scope, or 404 (out-of-scope lots behave as if missing). */
    protected function lot(Request $request, string $id, bool $withArchived = true): LotTanah
    {
        $lot = app(LotQuery::class)->find($this->user($request), $id, $withArchived);
        if ($lot === null) {
            throw new NotFoundHttpException(__('pro::pro.lot.tiada'));
        }

        return $lot;
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $page
     * @return array<string, int|null>
     */
    protected function meta(LengthAwarePaginator $page): array
    {
        return [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
            'from' => $page->firstItem(),
            'to' => $page->lastItem(),
        ];
    }
}
