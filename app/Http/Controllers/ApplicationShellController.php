<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Services\ApplicationAreaRegistry;
use Illuminate\View\View;

class ApplicationShellController extends Controller
{
    public function __invoke(string $area, ApplicationAreaRegistry $areas): View
    {
        abort_unless(isset($areas->all()[$area]), 404);

        return view('shell', ['area' => $area, 'definition' => $areas->all()[$area]]);
    }
}
