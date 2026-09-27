<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Services\ApplicationAreaRegistry;
use App\Domain\Organizations\Services\CurrentOrganization;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(CurrentOrganization $current, ApplicationAreaRegistry $areas): View
    {
        $organization = $current->get();

        return view('dashboard', ['areas' => $areas->allowed(auth()->user(), $organization), 'organization' => $organization]);
    }
}
