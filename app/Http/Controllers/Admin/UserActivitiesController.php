<?php
// app/Http/Controllers/UserActivitiesController.php

namespace App\Http\Controllers;

use Inertia\Inertia;

class UserActivitiesController extends Controller
{
    public function index()
    {
        return Inertia::render('UserActivities', [
            'user_id' => request()->get('user_id'),
            'user_name' => request()->get('user_name'),
            'user_email' => request()->get('user_email')
        ]);
    }
}