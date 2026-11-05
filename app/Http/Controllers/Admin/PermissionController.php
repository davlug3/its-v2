<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Acl;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        $acls = Acl::orderBy('id')->get();

        return response()->json($acls);
    }

    // Permission changes go through UserController::updateAccess.
}
