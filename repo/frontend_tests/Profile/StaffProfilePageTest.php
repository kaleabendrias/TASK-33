<?php

namespace FrontendTests\Profile;

use App\Livewire\Profile\StaffProfilePage;
use Livewire\Livewire;
use FrontendTests\TestCase;

/**
 * Frontend unit tests for StaffProfilePage.
 *
 * Covers: default property state, validation rules, and rendering.
 * Profile save success/failure paths that depend on API responses
 * belong in API_tests/Livewire/LivewireComponentTest.php.
 */
class StaffProfilePageTest extends TestCase
{
    public function test_default_employee_id_is_empty(): void
    {
        $u = $this->createUser('staff');
        $this->actAs($u);
        $this->assertEquals('', Livewire::test(StaffProfilePage::class)->get('employee_id'));
    }

    public function test_default_department_is_empty(): void
    {
        $u = $this->createUser('staff');
        $this->actAs($u);
        $this->assertEquals('', Livewire::test(StaffProfilePage::class)->get('department'));
    }

    public function test_default_title_is_empty(): void
    {
        $u = $this->createUser('staff');
        $this->actAs($u);
        $this->assertEquals('', Livewire::test(StaffProfilePage::class)->get('title'));
    }

    public function test_default_saved_is_false(): void
    {
        $u = $this->createUser('staff');
        $this->actAs($u);
        $this->assertFalse(Livewire::test(StaffProfilePage::class)->get('saved'));
    }

    public function test_component_renders_for_staff(): void
    {
        $u = $this->createUser('staff');
        $this->actAs($u);
        Livewire::test(StaffProfilePage::class)->assertOk();
    }

    public function test_validation_requires_employee_id(): void
    {
        $u = $this->createUser('staff');
        $this->actAs($u);
        Livewire::test(StaffProfilePage::class)
            ->set('employee_id', '')
            ->set('department', 'Engineering')
            ->set('title', 'Engineer')
            ->call('save')
            ->assertHasErrors(['employee_id' => 'required']);
    }

    public function test_validation_requires_department(): void
    {
        $u = $this->createUser('staff');
        $this->actAs($u);
        Livewire::test(StaffProfilePage::class)
            ->set('employee_id', 'E123')
            ->set('department', '')
            ->set('title', 'Engineer')
            ->call('save')
            ->assertHasErrors(['department' => 'required']);
    }

    public function test_validation_requires_title(): void
    {
        $u = $this->createUser('staff');
        $this->actAs($u);
        Livewire::test(StaffProfilePage::class)
            ->set('employee_id', 'E123')
            ->set('department', 'Engineering')
            ->set('title', '')
            ->call('save')
            ->assertHasErrors(['title' => 'required']);
    }

    public function test_property_binding_persists(): void
    {
        $u = $this->createUser('staff');
        $this->actAs($u);
        Livewire::test(StaffProfilePage::class)
            ->set('employee_id', 'E999')
            ->set('department', 'Ops')
            ->set('title', 'Manager')
            ->assertSet('employee_id', 'E999')
            ->assertSet('department', 'Ops')
            ->assertSet('title', 'Manager');
    }
}
