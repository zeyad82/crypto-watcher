<?php

use App\Console\Commands\Tracker\EmasAlert;
use App\Console\Commands\Tracker\FetchCryptos;
use App\Console\Commands\Tracker\FetchVolumes;
use App\Console\Commands\Tracker\VolumesAlert;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

use Illuminate\Support\Facades\Schedule;

Schedule::command(FetchCryptos::class)->daily();
Schedule::command(EmasAlert::class)->everyMinute();
Schedule::command(VolumesAlert::class)->everyMinute();
