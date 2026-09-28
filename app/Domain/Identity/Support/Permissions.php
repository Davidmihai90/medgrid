<?php

namespace App\Domain\Identity\Support;

final class Permissions
{
    public const DashboardView = 'dashboard.view';

    public const OrganizationsView = 'organizations.view';

    public const OrganizationsManage = 'organizations.manage';

    public const UsersView = 'users.view';

    public const UsersManage = 'users.manage';

    public const RolesView = 'roles.view';

    public const RolesManage = 'roles.manage';

    public const AuditView = 'audit.view';

    public const SystemHealthView = 'system.health.view';

    public const CasesView = 'cases.view';

    public const CasesCreate = 'cases.create';

    public const CasesUpdate = 'cases.update';

    public const CasesAssign = 'cases.assign';

    public const CasesClose = 'cases.close';

    public const VehiclesView = 'vehicles.view';

    public const VehiclesManage = 'vehicles.manage';

    public const CrewView = 'crew.view';

    public const CrewManage = 'crew.manage';

    public const AssignmentsView = 'assignments.view';

    public const AssignmentsCreate = 'assignments.create';

    public const AssignmentsCancel = 'assignments.cancel';

    public const AssignmentsReassign = 'assignments.reassign';

    public const AssignmentsAcknowledge = 'assignments.acknowledge';

    public const AssignmentsAccept = 'assignments.accept';

    public const All = [
        self::DashboardView, self::OrganizationsView, self::OrganizationsManage,
        self::UsersView, self::UsersManage, self::RolesView, self::RolesManage,
        self::AuditView, self::SystemHealthView, self::CasesView, self::CasesCreate,
        self::CasesUpdate, self::CasesAssign, self::CasesClose, self::VehiclesView,
        self::VehiclesManage, self::CrewView, self::CrewManage, self::AssignmentsView,
        self::AssignmentsCreate, self::AssignmentsCancel, self::AssignmentsReassign,
        self::AssignmentsAcknowledge, self::AssignmentsAccept,
    ];
}
