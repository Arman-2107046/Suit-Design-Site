<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Filament\Pages\DesignerLayout;
use App\Filament\Pages\Homepage;
use App\Filament\Pages\SupportSettings;
use App\Filament\Resources\Activities\ActivityResource;
use App\Filament\Resources\Admins\AdminResource;
use App\Filament\Resources\Admins\Pages\EditAdmin;
use App\Filament\Resources\Fabrics\FabricResource;
use App\Models\Admin;
use App\Models\BlogPost;
use App\Models\Fabric;
use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/*
 * Customers and administrators are separate accounts behind separate guards,
 * and admins can do less than super admins.
 */
class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function fabric(): Fabric
    {
        return Fabric::create(['name' => 'Navy Twill', 'price' => 200, 'image' => 'f.png', 'is_default' => true, 'status' => true]);
    }

    public function test_a_customer_account_cannot_sign_in_to_the_admin(): void
    {
        User::factory()->create(['email' => 'shopper@example.test']);   // password: "password"

        Livewire::test(Login::class)
            ->fillForm(['email' => 'shopper@example.test', 'password' => 'password'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest('admin');
    }

    public function test_a_signed_in_customer_is_sent_to_the_admin_sign_in(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertRedirect(Filament::getLoginUrl());
    }

    public function test_a_signed_in_customer_cannot_reach_the_admin_upload_endpoints(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.uploads.image'))
            ->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.bulk-upload.process'), ['files' => []])
            ->assertUnauthorized();
    }

    public function test_an_admin_signs_in_with_their_admin_account(): void
    {
        $admin = Admin::factory()->create(['email' => 'staff@example.test']);

        Livewire::test(Login::class)
            ->fillForm(['email' => 'staff@example.test', 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertGuest('web');
        $this->assertNotNull($admin->fresh()->last_login_at);
    }

    public function test_admins_edit_but_only_super_admins_delete(): void
    {
        $fabric = $this->fabric();

        $this->actingAs(Admin::factory()->create(), 'admin');
        $this->assertTrue(FabricResource::canEdit($fabric));
        $this->assertTrue(FabricResource::canCreate());
        $this->assertFalse(FabricResource::canDelete($fabric));
        $this->assertFalse(FabricResource::canDeleteAny());

        $this->actingAs(Admin::factory()->superAdmin()->create(), 'admin');
        $this->assertTrue(FabricResource::canDelete($fabric));
        $this->assertTrue(FabricResource::canDeleteAny());
    }

    public function test_site_wide_settings_and_the_team_are_for_super_admins(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');

        foreach ([Homepage::class, DesignerLayout::class, SupportSettings::class] as $page) {
            $this->assertFalse($page::canAccess(), $page);
        }
        $this->get(Homepage::getUrl())->assertForbidden();
        $this->get(AdminResource::getUrl('index'))->assertForbidden();
        $this->get(ActivityResource::getUrl('index'))->assertForbidden();

        $this->actingAs(Admin::factory()->superAdmin()->create(), 'admin');

        foreach ([Homepage::class, DesignerLayout::class, SupportSettings::class] as $page) {
            $this->assertTrue($page::canAccess(), $page);
        }
        $this->get(AdminResource::getUrl('index'))->assertOk()->assertSee('Administrators');
        $this->get(ActivityResource::getUrl('index'))->assertOk();
    }

    public function test_a_super_admin_cannot_delete_themselves_or_change_their_own_role(): void
    {
        $me = Admin::factory()->superAdmin()->create();
        Admin::factory()->superAdmin()->create();
        $colleague = Admin::factory()->create();

        $this->actingAs($me, 'admin');

        $this->assertFalse(AdminResource::canDelete($me));
        $this->assertTrue(AdminResource::canDelete($colleague));

        Livewire::test(EditAdmin::class, ['record' => $me->getKey()])->assertFormFieldDisabled('role');
        Livewire::test(EditAdmin::class, ['record' => $colleague->getKey()])->assertFormFieldEnabled('role');
    }

    public function test_the_last_super_admin_cannot_be_removed_or_demoted(): void
    {
        $only = Admin::factory()->superAdmin()->create();
        $this->actingAs(Admin::factory()->superAdmin()->create(), 'admin');
        $this->assertTrue(AdminResource::canDelete($only), 'two super admins: either can go');

        Admin::where('id', '!=', $only->id)->delete();

        $this->assertFalse(AdminResource::canDelete($only->fresh()));

        try {
            $only->fresh()->update(['role' => AdminRole::Admin]);
            $this->fail('The last super admin was demoted.');
        } catch (ValidationException) {
            $this->assertSame(AdminRole::SuperAdmin, $only->fresh()->role);
        }

        $this->expectException(ValidationException::class);
        $only->fresh()->delete();
    }

    public function test_a_super_admin_creates_an_admin_who_can_then_sign_in(): void
    {
        $this->actingAs(Admin::factory()->superAdmin()->create(), 'admin');

        Livewire::test(\App\Filament\Resources\Admins\Pages\CreateAdmin::class)
            ->fillForm([
                'name' => 'Sara Islam',
                'email' => 'sara@example.test',
                'role' => AdminRole::Admin->value,
                'password' => 'a-long-password',
                'password_confirmation' => 'a-long-password',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $sara = Admin::where('email', 'sara@example.test')->firstOrFail();
        $this->assertSame(AdminRole::Admin, $sara->role);
        $this->assertTrue(Hash::check('a-long-password', $sara->password));
        $this->assertDatabaseMissing('users', ['email' => 'sara@example.test']);
    }

    public function test_admin_create_moves_an_existing_account_across_with_its_password_and_posts(): void
    {
        $user = User::factory()->create(['name' => 'Arman', 'email' => 'arman@example.test', 'password' => 'secret-pass']);
        /* Written before admins had accounts of their own */
        $post = BlogPost::create(['title' => 'Shoulders', 'body' => '<p>x</p>', 'status' => 'draft']);
        $post->forceFill(['user_id' => $user->id])->save();

        $this->artisan('admin:create', ['--from-user' => 'arman@example.test', '--super' => true])->assertSuccessful();

        $admin = Admin::where('email', 'arman@example.test')->firstOrFail();
        $this->assertTrue($admin->isSuperAdmin());
        $this->assertTrue(Hash::check('secret-pass', $admin->password), 'same password as before');
        $this->assertSame($admin->id, $post->fresh()->admin_id);
        $this->assertNull($post->fresh()->user_id);
        $this->assertModelExists($user);   // the customer account stays, with any orders on it

        /* Running it twice changes nothing */
        $this->artisan('admin:create', ['--from-user' => 'arman@example.test'])->assertSuccessful();
        $this->assertSame(1, Admin::count());
        $this->assertTrue($admin->fresh()->isSuperAdmin(), 'a re-run without --super never demotes');
    }

    public function test_admin_create_with_super_promotes_someone_who_is_already_an_admin(): void
    {
        User::factory()->create(['email' => 'arman@example.test']);
        $this->artisan('admin:create', ['--from-user' => 'arman@example.test'])->assertSuccessful();
        $this->assertFalse(Admin::sole()->isSuperAdmin());

        $this->artisan('admin:create', ['--from-user' => 'arman@example.test', '--super' => true])
            ->expectsOutputToContain('is now a super admin')
            ->assertSuccessful();

        $this->assertTrue(Admin::sole()->isSuperAdmin());
        $this->assertSame(1, Admin::count());
    }
}
