<?php

namespace App\Http\Controllers;

use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Identity\Support\Permissions;
use App\Domain\Organizations\Services\CurrentOrganization;
use App\Events\OrganizationOperationalNotice;
use App\Http\Requests\OperationalNoticeRequest;
use App\Support\CorrelationContext;
use Illuminate\Http\RedirectResponse;

class OperationalNoticeController extends Controller
{
    public function store(OperationalNoticeRequest $request, CurrentOrganization $current, CorrelationContext $correlation, AuditRecorder $audit): RedirectResponse
    {
        $org = $current->get();
        abort_unless($request->user()->hasPermission(Permissions::OrganizationsManage, $org), 403);
        $message = $request->validated('message');
        broadcast(new OrganizationOperationalNotice($org->id, $message, $correlation->id()));
        $audit->record('organization.operational_notice.broadcasted', organization: $org, metadata: ['message_length' => mb_strlen($message)]);

        return back()->with('status', 'Operational notice broadcast.');
    }
}
