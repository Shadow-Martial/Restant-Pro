<?php

namespace App\Http\Controllers\API\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class NotificationsController extends Controller
{
    public function index(): JsonResponse
    {

        if (auth()->user()->hasRole(['staff'])) {
            //Get the staff member company user's notifications
            return response()->json([
                'data' => auth()->user()->restaurant->user->notifications,
                'status' => true,
            ]);
        } else {
            return response()->json([
                'data' => auth()->user()->notifications,
                'status' => true,
            ]);
        }

    }
}
