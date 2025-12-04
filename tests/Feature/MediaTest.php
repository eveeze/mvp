<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;
use App\Jobs\ProcessVideoUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can upload video file', function () {
    // 1. Mock Storage & Queue agar tidak melakukan upload beneran
    Storage::fake('s3'); 
    Queue::fake(); 

    // 2. Buat user dengan role advertiser (PENTING agar tidak 403)
    $user = User::factory()->create(['role' => 'advertiser']);
    
    // 3. Buat file dummy video
    $file = UploadedFile::fake()->create('iklan.mp4', 5000, 'video/mp4');

    // 4. Hit Endpoint
    $response = $this->actingAs($user)
        ->postJson('/api/media', ['video' => $file]);

    // 5. Assertions
    $response->assertCreated(); // Harus 201

    // Pastikan Job processing dipanggil
    Queue::assertPushed(ProcessVideoUpload::class);
});

it('validates video file type', function () {
    // 1. Buat user advertiser
    $user = User::factory()->create(['role' => 'advertiser']);
    
    // 2. Buat file dummy PDF (salah tipe)
    $file = UploadedFile::fake()->create('document.pdf', 1000, 'application/pdf');

    // 3. Hit Endpoint
    $this->actingAs($user)
        ->postJson('/api/media', ['video' => $file])
        ->assertStatus(422) // Harus Error Validasi
        ->assertJsonValidationErrors(['video']);
});