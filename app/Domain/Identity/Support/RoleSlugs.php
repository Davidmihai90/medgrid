<?php

namespace App\Domain\Identity\Support;

final class RoleSlugs
{
    public const SuperAdministrator = 'super-administrator';

    public const OrganizationAdministrator = 'organization-administrator';

    public const Dispatcher = 'dispatcher';

    public const MedicalCoordinator = 'medical-coordinator';

    public const AmbulancePhysician = 'ambulance-physician';

    public const Paramedic = 'paramedic';

    public const Nurse = 'nurse';

    public const AmbulanceDriver = 'ambulance-driver';

    public const HospitalOperator = 'hospital-operator';

    public const HospitalResourceManager = 'hospital-resource-manager';

    public const Doctor = 'doctor';

    public const Auditor = 'auditor';
}
