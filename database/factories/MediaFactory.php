<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class MediaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'file_name' => 'test_video.mp4',
            'mime_type' => 'video/mp4',
            'size' => 1024000,
            'duration' => 30, // Default duration
            'path_original' => 'videos/temp/test.mp4',
            'status' => 'completed',
        ];
    }
}