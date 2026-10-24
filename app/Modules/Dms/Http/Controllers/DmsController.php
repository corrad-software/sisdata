<?php

namespace App\Modules\Dms\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Dms\Models\Dokumen;
use App\Modules\Dms\Services\Access\DocumentAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

abstract class DmsController extends Controller
{
    public function __construct(protected DocumentAccess $access) {}

    protected function user(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new AuthorizationException;
        }

        return $user;
    }

    /**
     * A document the user may see. Out-of-scope documents are reported as not
     * found, so their existence isn't revealed (SRS-DMS-13).
     */
    protected function visible(string $id, User $user): Dokumen
    {
        $doc = Str::isUuid($id) ? Dokumen::query()->with('kategori')->find($id) : null;
        if ($doc === null || ! $this->access->canView($user, $doc)) {
            throw new NotFoundHttpException(__('Record not found.'));
        }

        return $doc;
    }

    protected function requirePermission(User $user, string $permission): void
    {
        if (! $this->access->allows($user, $permission)) {
            throw new AuthorizationException(__('This action is unauthorized.'));
        }
    }
}
