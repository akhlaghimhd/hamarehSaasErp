<?php
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => 'Hamareh ERP SaaS',
        'status' => 'running',
        'version' => '1.0'
    ]);
});