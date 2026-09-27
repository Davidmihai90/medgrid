<?php

namespace App\Http\Controllers;

use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Organizations\Services\CurrentOrganization;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrganizationContextController extends Controller
{
    public function update(Request $request, Organization $organization, CurrentOrganization $current, AuditRecorder $audit): RedirectResponse
    {
        $previous = $current->get();
        $current->set($request->user(), $organization);
        $audit->record('organization.context_changed', $organization, $organization, ['previous_organization_id' => $previous?->id]);

        return back();
    }
}
