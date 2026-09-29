<?php

namespace App\Policies;

use App\Enums\PermissionName as P;
use App\Models\ReportGeneration;
use App\Models\User;

/**
 * Exported reports (RF_00_4, RF_84). Anyone with reports.export exports and downloads their own files;
 * reports.history opens everybody's.
 */
class ReportGenerationPolicy
{
    /** RF_84. */
    public function viewAny(User $user): bool
    {
        return $user->can(P::ReportsHistory);
    }

    public function view(User $user, ReportGeneration $generation): bool
    {
        return $user->can(P::ReportsHistory)
            || ($generation->generated_by === $user->id && $user->can(P::ReportsExport));
    }

    /** RF_00_4, RF_70, RF_71. */
    public function create(User $user): bool
    {
        return $user->can(P::ReportsExport);
    }

    public function download(User $user, ReportGeneration $generation): bool
    {
        return $this->view($user, $generation);
    }
}
