<?php

namespace App\Http\Controllers\Compat;

use App\Http\Controllers\Controller;
use App\Models\Mod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class LegacyController extends Controller
{
    public function handle(Request $request): Response
    {
        $page = (string) $request->query('p', '');

        if ($page === '') {
            return redirect('/');
        }

        $mod = Cache::remember('mods:'.$page, 300, function () use ($page) {
            return Mod::where('name', $page)->first();
        });

        if ($mod === null) {
            return response()->view('compat.not-migrated', [
                'page' => $page,
            ], 404);
        }

        // Routes that have been migrated are handled by their own controllers.
        // Everything else shows the "not migrated" page.
        return response()->view('compat.not-migrated', [
            'page' => $page,
            'mod' => $mod,
        ], 200);
    }
}
