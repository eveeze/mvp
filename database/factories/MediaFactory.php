<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class MediaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'type' => 'video', // Default video
            'file_name' => 'test_file.mp4',
            'mime_type' => 'video/mp4',
            'size' => 1024000,
            'duration' => 30,
            'path_original' => 'videos/temp/raw.mp4',
            'path_optimized' => 'hls/dummy/master.m3u8',
            'thumbnail_path' => 'hls/dummy/thumbnail.jpg',
            'status' => 'completed', // Status teknis
            'moderation_status' => 'pending', // Default status moderasi
        ];
    }

    // Helper untuk membuat media yang sudah disetujui (untuk test campaign)
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'moderation_status' => 'approved',
        ]);
    }

    // Helper untuk media gambar
    public function image(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'image',
            'mime_type' => 'image/jpeg',
            'file_name' => 'image.jpg',
            'path_original' => 'images/temp/raw.jpg',
            'path_optimized' => 'images/dummy/optimized.webp',
        ]);
    }
}