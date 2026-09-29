<?php

namespace App\Http\Controllers\Api\Hospital;

use App\Domain\Hospitals\Services\HospitalConfigurationService;
use App\Domain\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hospital\StoreHospitalCapabilityRequest;
use App\Http\Requests\Hospital\StoreHospitalDepartmentRequest;
use App\Http\Requests\Hospital\StoreHospitalRequest;
use App\Http\Requests\Hospital\UpdateHospitalCapabilityRequest;
use App\Http\Resources\Hospital\HospitalResource;
use App\Models\CapabilityDefinition;
use App\Models\Hospital;
use App\Models\HospitalCapability;
use App\Models\HospitalDepartment;
use Illuminate\Http\JsonResponse;

class HospitalConfigurationController extends Controller
{
    public function hospital(StoreHospitalRequest $request, CurrentOrganization $current, HospitalConfigurationService $configuration): JsonResponse
    {
        $hospital = $configuration->createHospital($current->get(), $request->user(), $request->validated());

        return (new HospitalResource($hospital))->response()->setStatusCode(201);
    }

    public function department(StoreHospitalDepartmentRequest $request, Hospital $hospital, HospitalConfigurationService $configuration): JsonResponse
    {
        return response()->json(['data' => $configuration->createDepartment($hospital, $request->user(), $request->validated())], 201);
    }

    public function capability(StoreHospitalCapabilityRequest $request, Hospital $hospital, HospitalConfigurationService $configuration): JsonResponse
    {
        $data = $request->validated();
        $definition = CapabilityDefinition::findOrFail($data['capability_definition_id']);
        $department = isset($data['hospital_department_id']) ? HospitalDepartment::findOrFail($data['hospital_department_id']) : null;

        return response()->json(['data' => $configuration->addCapability($hospital, $definition, $department, $request->user(), $data), 'meta' => ['hospital_version' => $hospital->fresh()->version]], 201);
    }

    public function updateCapability(UpdateHospitalCapabilityRequest $request, HospitalCapability $capability, HospitalConfigurationService $configuration): JsonResponse
    {
        $capability = $configuration->setCapabilityEnabled($capability, $request->user(), $request->boolean('enabled'), $request->integer('expected_version'));

        return response()->json(['data' => $capability, 'meta' => ['hospital_version' => $capability->hospital->fresh()->version]]);
    }
}
