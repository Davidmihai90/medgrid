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

    public const All = [
        self::DashboardView, self::OrganizationsView, self::OrganizationsManage,
        self::UsersView, self::UsersManage, self::RolesView, self::RolesManage,
        self::AuditView, self::SystemHealthView,
    ];
}
