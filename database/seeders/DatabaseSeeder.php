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
        if(! Schema::hasColumn('alerts', 'volume_id')) {
            Schema::table('alerts', function (Blueprint $table) {
                $table->foreignId('volume_id')->nullable()->after('crypto_id')->constrained('volume_data')->onDelete('cascade');
            });
        }

        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);


    }
}
