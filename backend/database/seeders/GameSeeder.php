<?php

namespace Database\Seeders;

use App\Models\Game;
use Illuminate\Database\Seeder;

class GameSeeder extends Seeder
{
    public function run(): void
    {
        $games = [
            [
                'slug' => 'shape-matcher',
                'name' => 'Shape Matcher',
                'description' => 'Drag shapes to matching slots to train visual matching.',
                'task_type' => 'Matching',
                'skill' => 'Visual Matching',
                'min_age' => 3,
                'max_age' => 10,
                'total_levels' => 10,
                'icon_url' => '/icons/games/shape-matcher.png',
            ],
            [
                'slug' => 'maze-runner',
                'name' => 'Maze Runner',
                'description' => 'Navigate mazes to train visual tracking.',
                'task_type' => 'Tracking',
                'skill' => 'Visual Tracking',
                'min_age' => 4,
                'max_age' => 12,
                'total_levels' => 15,
                'icon_url' => '/icons/games/maze.png',
            ],
            [
                'slug' => 'animal-puzzle',
                'name' => 'Animal Puzzle',
                'description' => 'Assemble animal pictures to train visual matching.',
                'task_type' => 'Matching',
                'skill' => 'Visual Matching',
                'min_age' => 3,
                'max_age' => 10,
                'total_levels' => 8,
                'icon_url' => '/icons/games/puzzle.png',
            ],
            [
                'slug' => 'letter-tracing',
                'name' => 'Letter Tracing',
                'description' => 'Trace letters with accuracy to train orientation.',
                'task_type' => 'Orientation',
                'skill' => 'Spatial Orientation',
                'min_age' => 4,
                'max_age' => 9,
                'total_levels' => 12,
                'icon_url' => '/icons/games/tracing.png',
            ],
            [
                'slug' => 'which-one',
                'name' => 'Which One Is It?',
                'description' => 'Pick the correct shape to train visual discrimination.',
                'task_type' => 'Discrimination',
                'skill' => 'Visual Discrimination',
                'min_age' => 3,
                'max_age' => 8,
                'total_levels' => 10,
                'icon_url' => '/icons/games/which-one.png',
            ],
        ];

        foreach ($games as $game) {
            Game::updateOrCreate(['slug' => $game['slug']], $game);
        }
    }
}
