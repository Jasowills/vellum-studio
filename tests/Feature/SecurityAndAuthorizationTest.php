<?php

namespace Tests\Feature;

use App\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SecurityAndAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_private_pages(): void
    {
        $this->get('/studio')->assertRedirect('/login');
        $this->get('/library')->assertRedirect('/login');
        $this->get('/usage')->assertRedirect('/login');
        $this->get('/admin/report')->assertRedirect('/login');
    }

    public function test_one_user_cannot_view_another_users_generation_or_image(): void
    {
        $owner = User::factory()->create();
        $generation = Generation::factory()->for($owner)->create();
        $other = User::factory()->create();

        $this->actingAs($other)->get(route('generations.show', $generation))->assertForbidden();
        $this->actingAs($other)->get(route('generations.image', $generation))->assertForbidden();
    }

    public function test_non_admins_cannot_open_the_operator_report(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/report')
            ->assertForbidden();
    }

    public function test_admins_can_open_the_report_with_a_faked_usage_feed(): void
    {
        Http::fake([
            'pixelforge.test/v1/usage' => Http::response([
                'data' => [],
                'total' => [],
            ]),
        ]);

        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $this->actingAs($admin)
            ->get('/admin/report?days=7')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Report')
                ->where('range.days', 7)
                ->where('lifetime.charged_generations', 0));
    }
}
