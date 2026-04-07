<?php

namespace Database\Seeders;

use App\Domain\Models\BookableItem;
use App\Domain\Models\ServiceArea;
use Illuminate\Database\Seeder;

class BookableItemSeeder extends Seeder
{
    public function run(): void
    {
        $swEng = ServiceArea::where('slug', 'software-engineering')->first();
        $data  = ServiceArea::where('slug', 'data-analytics')->first();
        $cloud = ServiceArea::where('slug', 'cloud-infrastructure')->first();

        $items = [
            ['type' => 'lab',         'name' => 'Engineering Lab A',        'location' => 'Building 1, Floor 2',  'service_area_id' => $swEng->id, 'hourly_rate' => 50,  'daily_rate' => 350,  'tax_rate' => 0.0800, 'capacity' => 1,  'description' => 'Full-stack development lab with 12 workstations'],
            ['type' => 'lab',         'name' => 'Data Science Lab',         'location' => 'Building 2, Floor 1',  'service_area_id' => $data->id,  'hourly_rate' => 75,  'daily_rate' => 500,  'tax_rate' => 0.0800, 'capacity' => 1,  'description' => 'GPU-equipped lab for ML workloads'],
            ['type' => 'room',        'name' => 'Conference Room Alpha',    'location' => 'Building 1, Floor 3',  'service_area_id' => null,       'hourly_rate' => 30,  'daily_rate' => 200,  'tax_rate' => 0.0800, 'capacity' => 2,  'description' => 'Seats 20, projector, whiteboard'],
            ['type' => 'room',        'name' => 'Meeting Room Beta',        'location' => 'Building 1, Floor 1',  'service_area_id' => null,       'hourly_rate' => 20,  'daily_rate' => 140,  'tax_rate' => 0.0800, 'capacity' => 3,  'description' => 'Seats 8, video conferencing'],
            ['type' => 'workstation', 'name' => 'Hot Desk Zone A',          'location' => 'Building 1, Floor 1',  'service_area_id' => null,       'hourly_rate' => 10,  'daily_rate' => 60,   'tax_rate' => 0.0500, 'capacity' => 15, 'description' => 'Open-plan hot desking area'],
            ['type' => 'workstation', 'name' => 'Cloud Ops Station',        'location' => 'Building 2, Floor 3',  'service_area_id' => $cloud->id, 'hourly_rate' => 25,  'daily_rate' => 160,  'tax_rate' => 0.0500, 'capacity' => 4,  'description' => 'Dual-monitor stations with VPN access'],
            ['type' => 'equipment',   'name' => '3D Printer (Prusa MK4)',   'location' => 'Building 1, Maker Lab', 'service_area_id' => $swEng->id, 'hourly_rate' => 15,  'daily_rate' => 100,  'tax_rate' => 0.0800, 'capacity' => 1,  'description' => 'FDM 3D printer, PLA/PETG filament'],
            ['type' => 'equipment',   'name' => 'Oscilloscope (Rigol)',     'location' => 'Building 2, EE Lab',   'service_area_id' => null,       'hourly_rate' => 12,  'daily_rate' => 80,   'tax_rate' => 0.0800, 'capacity' => 3,  'description' => '4-channel digital oscilloscope'],
            ['type' => 'consumable',  'name' => 'PLA Filament Spool (1kg)', 'location' => 'Building 1, Maker Lab', 'service_area_id' => null,       'unit_price' => 25,   'tax_rate' => 0.0800, 'stock' => 50,  'description' => '1.75mm PLA filament, various colors'],
            ['type' => 'consumable',  'name' => 'Lab Notebook',             'location' => 'Supply Room',           'service_area_id' => null,       'unit_price' => 8.50, 'tax_rate' => 0.0000, 'stock' => 200, 'description' => '200-page gridded lab notebook'],
        ];

        foreach ($items as $item) {
            BookableItem::firstOrCreate(
                ['name' => $item['name'], 'type' => $item['type']],
                $item,
            );
        }
    }
}
