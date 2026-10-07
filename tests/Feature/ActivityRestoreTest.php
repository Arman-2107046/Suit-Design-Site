<?php

namespace Tests\Feature;

use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Models\Activity;
use App\Models\Admin;
use App\Models\BlogPost;
use App\Models\Fabric;
use App\Services\Activity\ActivityRestorer;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ActivityRestoreTest extends TestCase
{
    use RefreshDatabase;

    private Admin $super;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->super = Admin::factory()->superAdmin()->create(['name' => 'Arman']);
    }

    private function fabric(): Fabric
    {
        return Fabric::create(['name' => 'Navy Twill', 'price' => 200, 'image' => 'https://x/n.png', 'is_default' => true, 'status' => true]);
    }

    /** Make an edit as an admin, and return its log entry. */
    private function edit(Admin $admin, $model, array $changes): Activity
    {
        $this->actingAs($admin, 'admin');
        $model->update($changes);

        return Activity::query()->where('event', 'updated')->latest('id')->firstOrFail();
    }

    public function test_an_edit_is_put_back_and_the_restore_is_logged(): void
    {
        $fabric = $this->fabric();
        $sara = Admin::factory()->create(['name' => 'Sara']);
        $entry = $this->edit($sara, $fabric, ['name' => 'Midnight Twill', 'price' => 260]);

        $restored = app(ActivityRestorer::class)->restore($entry, $this->super);

        $fabric->refresh();
        $this->assertSame('Navy Twill', $fabric->name);
        $this->assertEquals(200, (float) $fabric->price);

        $this->assertSame('restored', $restored->event);
        $this->assertSame('Arman', $restored->admin_name);
        $this->assertSame($entry->id, $restored->properties['restored_from']);
        $this->assertSame('Restored fabric “Navy Twill” to an earlier version', $restored->sentence());
        $this->assertSame(0, Activity::where('event', 'updated')->where('id', '>', $entry->id)->count(), 'one "restored" entry, not an extra "updated" one');

        $this->assertTrue(app(ActivityRestorer::class)->restoredBy($entry)->is($restored));
    }

    public function test_a_restore_can_itself_be_undone(): void
    {
        $fabric = $this->fabric();
        $entry = $this->edit($this->super, $fabric, ['name' => 'Midnight Twill']);

        $restored = app(ActivityRestorer::class)->restore($entry, $this->super);
        app(ActivityRestorer::class)->restore($restored, $this->super);

        $this->assertSame('Midnight Twill', $fabric->fresh()->name);
    }

    public function test_a_field_edited_again_since_is_flagged_before_it_is_overwritten(): void
    {
        $fabric = $this->fabric();
        $entry = $this->edit($this->super, $fabric, ['name' => 'Midnight Twill']);
        $fabric->update(['name' => 'Midnight Twill II']);

        $plan = app(ActivityRestorer::class)->plan($entry);

        $this->assertTrue($plan['fields'][0]['changed_since']);
        $this->assertSame('Midnight Twill II', $plan['fields'][0]['now']);
        $this->assertSame('Navy Twill', $plan['fields'][0]['back_to']);
    }

    public function test_passwords_are_skipped_and_everything_else_restored(): void
    {
        $sara = Admin::factory()->create(['name' => 'Sara', 'password' => 'first-password']);
        $entry = $this->edit($this->super, $sara, ['name' => 'Sara Islam', 'password' => 'second-password']);

        $plan = app(ActivityRestorer::class)->plan($entry);
        $this->assertSame(['name'], array_column($plan['fields'], 'field'));
        $this->assertSame(['password'], array_column($plan['skipped'], 'field'));

        app(ActivityRestorer::class)->restore($entry, $this->super);

        $this->assertSame('Sara', $sara->fresh()->name);
        $this->assertTrue(Hash::check('second-password', $sara->fresh()->password), 'the password is left as it is');
    }

    public function test_long_text_and_list_fields_come_back_exactly(): void
    {
        $body = '<p>'.str_repeat('The shoulder seam should sit where your shoulder ends. ', 40).'</p>';   // well over the 500 shown
        $post = BlogPost::create(['title' => 'Shoulders', 'body' => $body, 'tags' => ['Fit', 'Style'], 'status' => 'draft']);
        $entry = $this->edit($this->super, $post, ['body' => '<p>Short now.</p>', 'tags' => ['Fit']]);

        app(ActivityRestorer::class)->restore($entry, $this->super);

        $this->assertSame($body, $post->fresh()->body);
        $this->assertSame(['Fit', 'Style'], $post->fresh()->tags);
    }

    public function test_an_older_entry_skips_text_that_was_cut_short_rather_than_guessing(): void
    {
        $post = BlogPost::create(['title' => 'Shoulders', 'body' => '<p>Now</p>', 'status' => 'draft']);

        /* An entry from before the full values were kept: only the shortened copy */
        $entry = Activity::create([
            'admin_id' => $this->super->id, 'admin_name' => 'Arman', 'event' => 'updated',
            'subject_type' => BlogPost::class, 'subject_id' => $post->id, 'subject_label' => 'Shoulders',
            'properties' => ['old' => ['title' => 'Old title', 'body' => str_repeat('x', 500).'…'], 'new' => ['title' => 'Shoulders', 'body' => '<p>Now</p>']],
            'created_at' => now(),
        ]);

        $plan = app(ActivityRestorer::class)->plan($entry);

        $this->assertSame(['title'], array_column($plan['fields'], 'field'));
        $this->assertSame('Too long to have been kept in full by this older entry.', $plan['skipped'][0]['why']);
    }

    public function test_a_deleted_record_cannot_be_restored(): void
    {
        $fabric = $this->fabric();
        $entry = $this->edit($this->super, $fabric, ['name' => 'Midnight Twill']);
        $fabric->delete();

        $plan = app(ActivityRestorer::class)->plan($entry);

        $this->assertFalse($plan['possible']);
        $this->assertSame('This record has since been deleted.', $plan['reason']);
    }

    public function test_the_restore_button_in_the_log_puts_values_back(): void
    {
        $fabric = $this->fabric();
        $entry = $this->edit($this->super, $fabric, ['name' => 'Midnight Twill']);

        Livewire::test(ListActivities::class)
            ->assertActionVisible(TestAction::make('restore')->table($entry))
            ->callAction(TestAction::make('restore')->table($entry))
            ->assertNotified('Restored');

        $this->assertSame('Navy Twill', $fabric->fresh()->name);

        /* Already back where it was: nothing left to restore */
        Livewire::test(ListActivities::class)->assertActionDisabled(TestAction::make('restore')->table($entry));
    }
}
