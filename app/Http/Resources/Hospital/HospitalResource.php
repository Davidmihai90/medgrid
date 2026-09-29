<?php

namespace App\Http\Resources\Hospital;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HospitalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'code' => $this->code, 'name' => $this->name, 'short_name' => $this->short_name, 'status' => $this->status->value, 'address' => $this->address, 'location' => ['latitude' => $this->latitude, 'longitude' => $this->longitude], 'timezone' => $this->timezone, 'active' => $this->active, 'version' => $this->version];
    }
}
