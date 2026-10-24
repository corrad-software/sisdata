<?php

namespace App\Modules\Wfe\Contracts;

use App\Models\User;

/**
 * Who holds a role / position, and who supervises whom — needed to resolve
 * approvers (SRS-WFE-05) and escalation recipients (SRS-WFE-07).
 *
 * RBC's AccessControl answers "what may this user do" but not "which users
 * hold role X" or the supervisor hierarchy, so MOD-WFE defines this
 * contract. MOD-RBC should bind a real implementation (peranan / jawatan /
 * hierarki penyeliaan); until then WFE binds Services\DefaultApproverDirectory
 * (legacy users.role column, optional users.jawatan / users.penyelia_id).
 * Approver *groups* are owned by WFE itself (wf_kumpulan) and are not part of
 * this contract.
 */
interface ApproverDirectory
{
    /**
     * Active users holding a role code right now.
     *
     * @return list<User>
     */
    public function usersWithRole(string $roleCode): array;

    /**
     * Active users holding a position (jawatan) code right now.
     *
     * @return list<User>
     */
    public function usersWithPosition(string $positionCode): array;

    /** The user's current position code, if the directory knows positions. */
    public function positionOf(User $user): ?string;

    /**
     * Supervisors of a user per the configured role hierarchy (nearest first).
     *
     * @return list<User>
     */
    public function supervisorsOf(User $user): array;
}
