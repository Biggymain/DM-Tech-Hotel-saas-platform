<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ImageUploadTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $hotel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = Hotel::create(['name' => 'Image Test Hotel']);
        $this->user = User::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
            'is_approved' => true,
            'hardware_hash' => TestCase::generateMockHardwareHash(),
        ]);

        Sanctum::actingAs($this->user);
    }

    #[Test]
    public function test_can_upload_image_successfully()
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('banner.jpg', 100, 'image/jpeg');

        $response = $this->postJson('/api/v1/organization/website/upload-image', [
            'image' => $file,
            'folder' => 'test'
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure(['message', 'url', 'path']);
        
        $path = $response->json('path');
        $hotelId = $this->hotel->id;
        
        // Assert path structure: tenants/{id}/public/test/{uuid}.jpg
        $this->assertStringStartsWith("tenants/{$hotelId}/public/test/", $path);
        
        Storage::disk('public')->assertExists($path);
    }

    #[Test]
    public function test_private_upload_is_secured()
    {
        Storage::fake('public');
        
        $file = UploadedFile::fake()->create('id_scan.jpg', 100, 'image/jpeg');
        
        // Manually use service for private upload since controller defaults to 'website' folder
        $service = app(\App\Services\ImageUploadService::class);
        $path = $service->upload($file, 'guest_ids', 'private');
        
        $hotelId = $this->hotel->id;
        $this->assertStringStartsWith("tenants/{$hotelId}/private/guest_ids/", $path);
        
        $url = get_secure_url($path);
        
        if (config('filesystems.default') === 'gcs') {
            // In GCS, it should be a signed URL (contains signature/expires etc)
            $this->assertStringContainsString('Expires=', $url);
        } else {
            // Local fallback
            $this->assertEquals(Storage::disk('public')->url($path), $url);
        }
    }

    #[Test]
    public function test_upload_fails_if_not_an_image()
    {
        $file = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->postJson('/api/v1/organization/website/upload-image', [
            'image' => $file
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['image']);
    }

    #[Test]
    public function test_upload_fails_if_size_is_exceeded()
    {
        $file = UploadedFile::fake()->create('large.jpg', 6000, 'image/jpeg'); // 6MB, limit is 5MB

        $response = $this->postJson('/api/v1/organization/website/upload-image', [
            'image' => $file
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['image']);
    }
}
