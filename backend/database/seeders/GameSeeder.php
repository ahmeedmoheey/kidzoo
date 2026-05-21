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
                'slug' => 'find-items',
                'name' => 'Find Items',
                'description' => 'Find the requested picture among multiple distractors.',
                'icon_url' => '/icons/games/find-items.png',
                'asset_type' => 'image',
                'task_type' => 'Discrimination',
                'target_type' => 'Position',
                'skill' => 'Visual Discrimination',
                'min_age' => 3,
                'max_age' => 10,
                'total_levels' => 10,
                'is_active' => true,
                'settings' => [
                    'answer_mode' => 'tap',
                    'content_source' => 'image',
                ],
            ],
            [
                'slug' => 'sequence-game',
                'name' => 'Sequence Game',
                'description' => 'Repeat the shown order of symbols to train visual sequencing.',
                'icon_url' => '/icons/games/sequence-game.png',
                'asset_type' => 'icon',
                'task_type' => 'Orientation',
                'target_type' => 'Direction',
                'skill' => 'Spatial Orientation',
                'min_age' => 4,
                'max_age' => 12,
                'total_levels' => 10,
                'is_active' => true,
                'settings' => [
                    'answer_mode' => 'sequence',
                    'content_source' => 'flutter_icon',
                ],
            ],
            [
                'slug' => 'visual-game',
                'name' => 'Visual Game',
                'description' => 'Match visual icons and patterns to strengthen attention and discrimination.',
                'icon_url' => '/icons/games/visual-game.png',
                'asset_type' => 'icon',
                'task_type' => 'Discrimination',
                'target_type' => 'Shape',
                'skill' => 'Visual Discrimination',
                'min_age' => 3,
                'max_age' => 10,
                'total_levels' => 12,
                'is_active' => true,
                'settings' => [
                    'answer_mode' => 'tap',
                    'content_source' => 'flutter_icon',
                ],
            ],
            [
                'slug' => 'shape-match',
                'name' => 'Shape Match',
                'description' => 'Match shapes with the correct slot using Flutter icons.',
                'icon_url' => '/icons/games/shape-match.png',
                'asset_type' => 'icon',
                'task_type' => 'Matching',
                'target_type' => 'Shape',
                'skill' => 'Visual Matching',
                'min_age' => 3,
                'max_age' => 10,
                'total_levels' => 15,
                'is_active' => true,
                'settings' => [
                    'answer_mode' => 'drag_or_tap',
                    'content_source' => 'flutter_icon',
                ],
            ],
            [
                'slug' => 'animal-match',
                'name' => 'Animal Match',
                'description' => 'Match animal pictures with their identical pair.',
                'icon_url' => '/icons/games/animal-match.png',
                'asset_type' => 'image',
                'task_type' => 'Matching',
                'target_type' => 'Shape',
                'skill' => 'Visual Matching',
                'min_age' => 3,
                'max_age' => 8,
                'total_levels' => 10,
                'is_active' => true,
                'settings' => [
                    'answer_mode' => 'pair_match',
                    'content_source' => 'image',
                ],
            ],
        ];

        $activeSlugs = array_column($games, 'slug');

        Game::query()
            ->whereNotIn('slug', $activeSlugs)
            ->update(['is_active' => false]);

        foreach ($games as $game) {
            Game::updateOrCreate(['slug' => $game['slug']], $game);
        }
    }
}
