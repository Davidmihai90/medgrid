<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Identity\Support\Permissions;
use App\Domain\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(CurrentOrganization $current): View
    {
        abort_unless(auth()->user()->hasPermission(Permissions::RolesView, $current->get()), 403);

        return view('admin.roles.index', ['roles' => Role::with('permissions')->orderBy('scope')->orderBy('name')->get()]);
    }
}
