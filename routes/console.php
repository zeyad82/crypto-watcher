<?php

use App\Console\Commands\Tracker\BinanceWebSocket;
use App\Console\Commands\Tracker\Cleanup;
use App\Console\Commands\Tracker\CrossoversAlert;
use App\Console\Commands\Tracker\FetchCryptos;
use App\Console\Commands\Tracker\RSIAlert;
use App\Console\Commands\Tracker\VolumesAlert;
use App\Console\Commands\UpdateAlertResults;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

use Illuminate\Support\Facades\Schedule;

Schedule::command(FetchCryptos::class)->everyFifteenMinutes();
Schedule::command(Cleanup::class)->everyThirtyMinutes();

Schedule::command(RSIAlert::class)->everyMinute();
Schedule::command(CrossoversAlert::class)->everyMinute();
Schedule::command(VolumesAlert::class)->everyMinute();
Schedule::command(UpdateAlertResults::class)->everyMinute();

Schedule::command(BinanceWebSocket::class, ['4h', '--once'])
->hourlyAt(1);
