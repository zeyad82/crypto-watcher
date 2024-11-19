<?php

use App\Console\Commands\Tracker\CrossoversAlert;
use App\Console\Commands\Tracker\FetchCryptos;
use App\Console\Commands\Tracker\VolumesAlert;
use App\Console\Commands\UpdateAlertResults;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

use Illuminate\Support\Facades\Schedule;

Schedule::command(FetchCryptos::class)->daily();

Schedule::command(CrossoversAlert::class)->everyMinute();
Schedule::command(VolumesAlert::class)->everyMinute();
Schedule::command(UpdateAlertResults::class)->everyMinute();
