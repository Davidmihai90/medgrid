<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Identity\Support\Permissions;
use App\Domain\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function index(CurrentOrganization $current, AuditRecorder $audit): View
    {
        $org = $current->get();
        abort_unless(auth()->user()->hasPermission(Permissions::AuditView, $org), 403);
        $audit->record('audit.viewed', organization: $org);
        $logs = AuditLog::when(! auth()->user()->isPlatformSuperAdministrator(), fn ($q) => $q->where('organization_id', $org->id))->latest('occurred_at')->paginate(30);

        return view('admin.audit.index', compact('logs'));
    }
}
