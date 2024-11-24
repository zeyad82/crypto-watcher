<?php

namespace App\Console\Commands\Tracker;

use App\Models\VolumeData;
use Carbon\Carbon;
use Illuminate\Console\Command;

class Cleanup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tracker:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete old data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        VolumeData::where('timeframe', '1m')->where('timestamp', '<', Carbon::now()->subMinutes(70))->delete();
        VolumeData::where('timeframe', '15m')->where('timestamp', '<', Carbon::now()->subHours(15))->delete();
        VolumeData::where('timeframe', '1h')->where('timestamp', '<', Carbon::now()->subHours(55))->delete();
        VolumeData::where('timeframe', '4h')->where('timestamp', '<', Carbon::now()->subHours(55 * 4))->delete();
    }
}
