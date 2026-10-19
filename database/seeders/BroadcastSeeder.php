<?php

namespace Database\Seeders;

use App\Models\BroadcastChannel;
use Illuminate\Database\Seeder;

/**
 * Creates the main channel in an honest OFF-AIR state with no source.
 * Add the real HLS/YouTube source in Admin → Video & Live TV.
 */
class BroadcastSeeder extends Seeder
{
    public function run(): void
    {
        BroadcastChannel::firstOrCreate(['slug' => 'doogsan-tv'], [
            'name' => 'Doogsan TV',
            'source_type' => 'hls',
            'is_enabled' => true,
            'is_live' => false,
            'show_live_indicator' => true,
            'health_status' => 'unknown',
        ]);
    }
}
