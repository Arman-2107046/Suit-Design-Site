<?php

namespace Tests\Feature;

use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Filament\Widgets\RecentActivity;
use App\Models\Activity;
use App\Models\Admin;
use App\Models\Fabric;
use App\Models\User;
use App\Services\Activity\ActivityLogger;
use Filament\Actions\Testing\TestAction;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

class ActivityLogTest extends TestCase
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

    public function test_an_admins_edit_is_logged_with_only_what_changed(): void
    {
        $admin = Admin::factory()->create(['name' => 'Sara Islam']);
        $fabric = $this->fabric();
        $this->actingAs($admin, 'admin');

        $fabric->update(['price' => 250]);

        $entry = Activity::where('event', 'updated')->sole();
        $this->assertSame($admin->id, $entry->admin_id);
        $this->assertSame('Sara Islam', $entry->admin_name);
        $this->assertSame('Navy Twill', $entry->subject_label);
        $this->assertSame([['field' => 'price', 'old' => 200, 'new' => 250]], array_map(
            fn ($c) => ['field' => $c['field'], 'old' => (int) $c['old'], 'new' => (int) $c['new']],
            $entry->changes()
        ));
        $this->assertSame('Updated fabric “Navy Twill”', $entry->sentence());
    }

    public function test_creating_and_deleting_are_logged_with_a_snapshot(): void
    {
        $this->actingAs(Admin::factory()->superAdmin()->create(), 'admin');

        $fabric = $this->fabric();
        $fabric->delete();

        $this->assertSame(['created', 'deleted'], Activity::orderBy('id')->pluck('event')->all());
        $snapshot = Activity::where('event', 'deleted')->sole()->properties['attributes'];
        $this->assertSame('Navy Twill', $snapshot['name']);
    }

    public function test_customers_are_never_logged_even_when_an_admin_shares_the_browser(): void
    {
        $this->actingAs(User::factory()->create());
        $this->fabric();

        /* Signed into the admin too, but this request is the shop's (web guard) */
        $this->actingAs(Admin::factory()->create(), 'admin');
        Auth::shouldUse('web');
        $this->fabric()->update(['price' => 300]);

        $this->assertSame(0, Activity::count());
    }

    public function test_passwords_never_reach_the_log(): void
    {
        $super = Admin::factory()->superAdmin()->create();
        $colleague = Admin::factory()->create();
        $this->actingAs($super, 'admin');

        $colleague->update(['password' => 'a-new-password']);

        $entry = Activity::where('event', 'updated')->sole();
        $this->assertSame('••••••', $entry->properties['new']['password']);
        $this->assertStringNotContainsString('a-new-password', json_encode($entry->properties));
        $this->assertStringNotContainsString('$2y$', json_encode($entry->properties));
    }

    public function test_sign_ins_sign_outs_and_failed_attempts_are_logged(): void
    {
        $admin = Admin::factory()->create(['email' => 'staff@example.test']);

        Livewire::test(Login::class)
            ->fillForm(['email' => 'staff@example.test', 'password' => 'wrong'])
            ->call('authenticate');

        Livewire::test(Login::class)
            ->fillForm(['email' => 'staff@example.test', 'password' => 'password'])
            ->call('authenticate');

        Auth::guard('admin')->logout();

        $this->assertSame(['login_failed', 'login', 'logout'], Activity::orderBy('id')->pluck('event')->all());

        $failed = Activity::where('event', 'login_failed')->sole();
        $this->assertNull($failed->admin_id, 'whoever failed is unknown');
        $this->assertSame('staff@example.test', $failed->subject_label);
        $this->assertTrue($failed->subject->is($admin), 'the account they tried');

        $this->assertSame(0, Activity::where('event', 'updated')->count(), 'last sign-in time is not an edit');
    }

    public function test_a_bulk_upload_is_one_line_however_many_chunks_it_arrives_in(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');

        ActivityLogger::logBulkUpload(['filed' => 50, 'failed' => 0, 'breakdown' => ['fabrics' => 50]]);
        ActivityLogger::logBulkUpload(['filed' => 48, 'failed' => 2, 'breakdown' => ['fabrics' => 40, 'linings' => 8]]);

        $entry = Activity::where('event', 'bulk_upload')->sole();
        $this->assertSame(98, $entry->properties['filed']);
        $this->assertSame(2, $entry->properties['failed']);
        $this->assertSame(['fabrics' => 90, 'linings' => 8], $entry->properties['breakdown']);
        $this->assertSame('Bulk uploaded 98 files', $entry->sentence());
    }

    public function test_records_written_quietly_are_not_logged_one_by_one(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');

        ActivityLogger::quietly(fn () => $this->fabric());

        $this->assertSame(0, Activity::count());
    }

    public function test_the_log_and_its_details_render_for_a_super_admin(): void
    {
        $super = Admin::factory()->superAdmin()->create();
        $this->actingAs($super, 'admin');
        $this->fabric()->update(['price' => 250, 'name' => 'Midnight Twill']);
        $entry = Activity::where('event', 'updated')->sole();

        Livewire::test(ListActivities::class)
            ->assertCanSeeTableRecords([$entry])
            ->mountAction(TestAction::make('view')->table($entry))
            ->assertMountedActionModalSee(['What changed', 'Before', 'After', 'Navy Twill', 'Midnight Twill']);

        $this->assertTrue(RecentActivity::canView());
        Livewire::test(RecentActivity::class)->assertSee('Updated fabric');
    }

    public function test_admins_cannot_read_the_log(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');

        $this->assertFalse(RecentActivity::canView());
        $this->assertFalse(\App\Filament\Resources\Activities\ActivityResource::canViewAny());
    }
}
