<?php

namespace FrontendTests\Auth;

use App\Livewire\Auth\Login;
use Livewire\Livewire;
use FrontendTests\TestCase;

/**
 * Frontend unit tests for the Login component.
 *
 * Covers: default property state, validation rules, and rendering.
 * The login() success/error paths depend on an outbound Http call
 * and belong in the API integration suite (API_tests/Livewire/).
 */
class LoginTest extends TestCase
{
    public function test_component_renders(): void
    {
        Livewire::test(Login::class)->assertOk();
    }

    public function test_default_property_values(): void
    {
        $component = Livewire::test(Login::class);
        $this->assertEquals('', $component->get('username'));
        $this->assertEquals('', $component->get('password'));
        $this->assertEquals('', $component->get('error'));
    }

    public function test_validation_requires_username(): void
    {
        Livewire::test(Login::class)
            ->set('username', '')
            ->set('password', 'SomePass@1!')
            ->call('login')
            ->assertHasErrors(['username' => 'required']);
    }

    public function test_validation_requires_password(): void
    {
        Livewire::test(Login::class)
            ->set('username', 'someone')
            ->set('password', '')
            ->call('login')
            ->assertHasErrors(['password' => 'required']);
    }

    public function test_validation_requires_both_fields_when_empty(): void
    {
        Livewire::test(Login::class)
            ->set('username', '')
            ->set('password', '')
            ->call('login')
            ->assertHasErrors(['username', 'password']);
    }

    public function test_setting_username_and_password_persists(): void
    {
        Livewire::test(Login::class)
            ->set('username', 'alice')
            ->set('password', 'AlicePass@1!')
            ->assertSet('username', 'alice')
            ->assertSet('password', 'AlicePass@1!');
    }
}
