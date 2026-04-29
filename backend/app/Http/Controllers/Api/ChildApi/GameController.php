<?php

namespace App\Http\Controllers\Api\ChildApi;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $age = $request->user()->age;

        $games = Game::where('is_active', true)
            ->where('min_age', '<=', $age)
            ->where('max_age', '>=', $age)
            ->get();

        return response()->json(['games' => $games]);
    }

    public function show(Game $game): JsonResponse
    {
        return response()->json(['game' => $game]);
    }
}
