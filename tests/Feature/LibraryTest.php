<?php

namespace Tests\Feature;

use App\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_library_returns_flat_generation_resources(): void
    {
        $user = User::factory()->create();
        $generation = Generation::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('library'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Library')
                ->where('generations.total', 1)
                ->where('generations.data.0.id', $generation->public_id)
                ->where('generations.data.0.prompt', $generation->prompt)
                ->missing('generations.data.0.data'));
    }
}
