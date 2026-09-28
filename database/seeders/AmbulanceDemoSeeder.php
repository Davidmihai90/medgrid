<?php

namespace Database\Seeders;

use App\Models\AssessmentTemplate;
use App\Models\AssessmentTemplateVersion;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

class AmbulanceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::where('slug', 'demo-emergency-service')->firstOrFail();
        $author = User::where('email', 'paramedic.a@medgrid.test')->firstOrFail();

        $template = AssessmentTemplate::updateOrCreate(
            ['organization_id' => $organization->id, 'code' => 'SYNTHETIC-GENERAL-ASSESSMENT'],
            ['name' => 'Synthetic general assessment', 'is_active' => true, 'is_synthetic' => true],
        );

        AssessmentTemplateVersion::updateOrCreate(
            ['assessment_template_id' => $template->id, 'version' => 1],
            [
                'definition' => [
                    'notice' => 'Synthetic development template. No protocol, score, diagnosis, or treatment guidance is encoded.',
                    'fields' => [
                        ['key' => 'presentation_summary', 'label' => 'Presentation summary', 'type' => 'textarea', 'required' => true],
                        ['key' => 'relevant_observations', 'label' => 'Relevant observations', 'type' => 'textarea', 'required' => false],
                        ['key' => 'care_context', 'label' => 'Care context', 'type' => 'textarea', 'required' => false],
                    ],
                ],
                'published_at' => now(),
                'created_by' => $author->id,
            ],
        );
    }
}
