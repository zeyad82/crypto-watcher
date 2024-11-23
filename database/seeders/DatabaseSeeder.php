<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if(! Schema::hasColumn('volume_data', 'price_change')) {
            Schema::table('volume_data', function (Blueprint $table) {
                $table->integer('price_change')->nullable()->after('latest_price');
                $table->string('timeframe')->default('15m')->index()->after('price_change');

            });
        }

        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);


    }
}
