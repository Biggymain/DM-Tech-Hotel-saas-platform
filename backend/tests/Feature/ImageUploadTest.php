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
                 ->assertJsonStructure(['message', 'url']);
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
