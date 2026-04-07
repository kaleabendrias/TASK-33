<?php

namespace Database\Seeders;

use App\Domain\Models\ServiceArea;
use Illuminate\Database\Seeder;

class ServiceAreaSeeder extends Seeder
{
    public function run(): void
    {
        $areas = [
            ['name' => 'Software Engineering', 'slug' => 'software-engineering', 'description' => 'Custom software development and maintenance'],
            ['name' => 'Data Analytics',       'slug' => 'data-analytics',       'description' => 'Business intelligence, reporting, and data pipelines'],
            ['name' => 'Cloud Infrastructure', 'slug' => 'cloud-infrastructure', 'description' => 'Cloud architecture, DevOps, and platform engineering'],
            ['name' => 'Cybersecurity',        'slug' => 'cybersecurity',        'description' => 'Security assessments, compliance, and incident response'],
            ['name' => 'UX Design',            'slug' => 'ux-design',            'description' => 'User research, interface design, and prototyping'],
        ];

        foreach ($areas as $area) {
            ServiceArea::firstOrCreate(['slug' => $area['slug']], $area);
        }
    }
}
