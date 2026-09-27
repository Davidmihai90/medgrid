<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Organizations\Actions\CreateOrganization;
use App\Domain\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreOrganizationRequest;
use App\Http\Requests\Admin\UpdateOrganizationRequest;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function index(CurrentOrganization $current): View
    {
        $this->authorize('viewAny', Organization::class);
        $items = auth()->user()->isPlatformSuperAdministrator() ? Organization::orderBy('name')->paginate(20) : Organization::whereKey($current->get()?->id)->paginate(20);

        return view('admin.organizations.index', ['organizations' => $items]);
    }

    public function create(): View
    {
        $this->authorize('create', Organization::class);

        return view('admin.organizations.form', ['organization' => new Organization]);
    }

    public function store(StoreOrganizationRequest $request, CreateOrganization $action): RedirectResponse
    {
        $organization = $action->handle($request->validated());

        return redirect()->route('admin.organizations.show', $organization)->with('status', 'Organization created.');
    }

    public function show(Organization $organization): View
    {
        $this->authorize('view', $organization);

        return view('admin.organizations.show', ['organization' => $organization->loadCount('memberships')]);
    }

    public function edit(Organization $organization): View
    {
        $this->authorize('update', $organization);

        return view('admin.organizations.form', compact('organization'));
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization, AuditRecorder $audit): RedirectResponse
    {
        $data = $request->validated();
        if (! auth()->user()->isPlatformSuperAdministrator()) {
            unset($data['status']);
        } DB::transaction(function () use ($organization, $data, $audit) {
            $before = $organization->only(array_keys($data));
            $organization->update($data);
            $audit->record('organization.updated', $organization, $organization, ['before' => $before, 'fields' => array_keys($data)]);
        });

        return redirect()->route('admin.organizations.show', $organization)->with('status','Organization updated.');
    }
}
