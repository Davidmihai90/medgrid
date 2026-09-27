<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Health\Services\SystemHealthService;
use App\Domain\Identity\Support\Permissions;
use App\Domain\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class SystemHealthController extends Controller
{
    public function __invoke(CurrentOrganization $current, SystemHealthService $health): View
    {
        abort_unless(auth()->user()->hasPermission(Permissions::SystemHealthView, $current->get()), 403);

        return view('admin.health', ['checks' => $health->check()]);
    }
}
