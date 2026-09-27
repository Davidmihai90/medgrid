<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Identity\Actions\CreateOrganizationUser;
use App\Domain\Identity\Actions\UpdateOrganizationUser;
use App\Domain\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(CurrentOrganization $current): View
    {
        $this->authorize('viewAny', User::class);
        $org = $current->get();
        $users = User::whereHas('memberships', fn ($q) => $q->where('organization_id', $org->id))->with(['memberships' => fn ($q) => $q->where('organization_id', $org->id)->with('roles')])->orderBy('name')->paginate(20);

        return view('admin.users.index', compact('users', 'org'));
    }

    public function create(CurrentOrganization $current): View
    {
        $this->authorize('create', User::class);

        return view('admin.users.form', ['subject' => new User, 'membership' => null, 'roles' => Role::where('scope', 'ORGANIZATION')->orderBy('name')->get(), 'org' => $current->get()]);
    }

    public function store(StoreUserRequest $request, CurrentOrganization $current, CreateOrganizationUser $action): RedirectResponse
    {
        $user = $action->handle($current->get(), $request->validated());

        return redirect()->route('admin.users.show', $user)->with('status', 'User created.');
    }

    public function show(User $user, CurrentOrganization $current): View
    {
        $this->authorize('view', $user);
        $membership = $user->memberships()->where('organization_id', $current->get()->id)->with('roles')->firstOrFail();

        return view('admin.users.show', ['subject' => $user, 'membership' => $membership, 'org' => $current->get()]);
    }

    public function edit(User $user, CurrentOrganization $current): View
    {
        $this->authorize('update', $user);
        $membership = $user->memberships()->where('organization_id', $current->get()->id)->with('roles')->firstOrFail();

        return view('admin.users.form', ['subject' => $user, 'membership' => $membership, 'roles' => Role::where('scope', 'ORGANIZATION')->orderBy('name')->get(), 'org' => $current->get()]);
    }

    public function update(UpdateUserRequest $request, User $user, CurrentOrganization $current, UpdateOrganizationUser $action): RedirectResponse
    {
        $action->handle($current->get(), $user, $request->validated(), $request->user()->isPlatformSuperAdministrator());

        return redirect()->route('admin.users.show',$user)->with('status','User updated.');
    }
}
