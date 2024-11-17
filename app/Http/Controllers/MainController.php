<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Artisan;

class MainController extends Controller
{
    public function index()
    {
        Artisan::call('tracker:pull-data');
    }
}
