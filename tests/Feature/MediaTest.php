<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;
use App\Jobs\ProcessVideoUpload;
use App\Jobs\ProcessImageUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can upload VIDEO file and dispatches video job', function () {
    Storage::fake('s3'); 
    Queue::fake(); 

    $user = User::factory()->create(['role' => 'advertiser']);
    $file = UploadedFile::fake()->create('iklan.mp4', 5000, 'video/mp4');

    $this->actingAs($user)
        ->postJson('/api/media', ['file' => $file]) 
        ->assertCreated()
        ->assertJsonPath('data.type', 'video')
        ->assertJsonPath('data.moderation_status', 'pending');

    Queue::assertPushed(ProcessVideoUpload::class);
    Queue::assertNotPushed(ProcessImageUpload::class);
});

it('can upload IMAGE file and dispatches image job', function () {
    Storage::fake('s3'); 
    Queue::fake(); 

    $user = User::factory()->create(['role' => 'advertiser']);
    
    // [FIX] Gunakan 'create' bukan 'image' untuk menghindari butuh GD Library
    $file = UploadedFile::fake()->create('poster.jpg', 500, 'image/jpeg');

    $this->actingAs($user)
        ->postJson('/api/media', ['file' => $file])
        ->assertCreated()
        ->assertJsonPath('data.type', 'image');

    Queue::assertPushed(ProcessImageUpload::class);
    Queue::assertNotPushed(ProcessVideoUpload::class);
});

it('validates file type', function () {
    $user = User::factory()->create(['role' => 'advertiser']);
    $file = UploadedFile::fake()->create('doc.pdf', 1000, 'application/pdf');

    $this->actingAs($user)
        ->postJson('/api/media', ['file' => $file])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['file']);
});