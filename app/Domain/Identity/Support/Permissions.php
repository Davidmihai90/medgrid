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

    public const PatientsView = 'patients.view';

    public const PatientsCreate = 'patients.create';

    public const PatientsUpdate = 'patients.update';

    public const EncountersView = 'encounters.view';

    public const EncountersCreate = 'encounters.create';

    public const EncountersUpdate = 'encounters.update';

    public const VitalsView = 'vitals.view';

    public const VitalsCreate = 'vitals.create';

    public const VitalsCorrect = 'vitals.correct';

    public const AssessmentsView = 'assessments.view';

    public const AssessmentsCreate = 'assessments.create';

    public const AssessmentsUpdate = 'assessments.update';

    public const AssessmentsComplete = 'assessments.complete';

    public const ClinicalNotesView = 'clinical_notes.view';

    public const ClinicalNotesCreate = 'clinical_notes.create';

    public const AmbulanceWorkflowUpdate = 'ambulance.workflow.update';

    public const SyncSubmit = 'sync.submit';

    public const M2 = [self::PatientsView, self::PatientsCreate, self::PatientsUpdate, self::EncountersView, self::EncountersCreate, self::EncountersUpdate, self::VitalsView, self::VitalsCreate, self::VitalsCorrect, self::AssessmentsView, self::AssessmentsCreate, self::AssessmentsUpdate, self::AssessmentsComplete, self::ClinicalNotesView, self::ClinicalNotesCreate, self::AmbulanceWorkflowUpdate, self::SyncSubmit];

    public const All = [self::DashboardView, self::OrganizationsView, self::OrganizationsManage, self::UsersView, self::UsersManage, self::RolesView, self::RolesManage, self::AuditView, self::SystemHealthView, self::CasesView, self::CasesCreate, self::CasesUpdate, self::CasesAssign, self::CasesClose, self::VehiclesView, self::VehiclesManage, self::CrewView, self::CrewManage, self::AssignmentsView, self::AssignmentsCreate, self::AssignmentsCancel, self::AssignmentsReassign, self::AssignmentsAcknowledge, self::AssignmentsAccept, ...self::M2];
}
