<?php

namespace App\Console\Commands;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/*
 * Makes an administrator account. With --from-user it moves someone who used
 * to sign into the admin with a customer account: same name, email and
 * password, their journal posts carried across. The customer account is left
 * alone, so any orders on it stay where they are.
 */
class CreateAdmin extends Command
{
    protected $signature = 'admin:create
        {--from-user= : Email of an existing customer account to copy name and password from}
        {--name= : Name, when not copying}
        {--email= : Email, when not copying}
        {--super : Make a super admin}';

    protected $description = 'Create an administrator account (separate from customer accounts)';

    public function handle(): int
    {
        $role = $this->option('super') ? AdminRole::SuperAdmin : AdminRole::Admin;

        if ($from = $this->option('from-user')) {
            return $this->fromUser($from, $role);
        }

        $data = [
            'name' => $this->option('name') ?: text('Name', required: true),
            'email' => $this->option('email') ?: text('Email', required: true),
            'password' => password('Password (at least 8 characters)', required: true),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:admins,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $admin = Admin::create([...$data, 'role' => $role]);

        $this->components->info("{$admin->name} <{$admin->email}> is now an administrator ({$role->getLabel()}).");

        return self::SUCCESS;
    }

    private function fromUser(string $email, AdminRole $role): int
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->components->error("No customer account uses {$email}.");

            return self::FAILURE;
        }

        if ($existing = Admin::where('email', $user->email)->first()) {
            /* Already moved across: --super still promotes them, so the command can be used to fix a role */
            if ($role === AdminRole::SuperAdmin && ! $existing->isSuperAdmin()) {
                $existing->update(['role' => AdminRole::SuperAdmin]);
                $this->components->info("{$existing->email} was already an administrator, and is now a super admin.");

                return self::SUCCESS;
            }

            $this->components->warn("{$existing->email} is already an administrator ({$existing->role->getLabel()}). Nothing changed.");

            return self::SUCCESS;
        }

        $admin = DB::transaction(function () use ($user, $role) {
            /* The hash as it stands (the cast keeps an existing hash): same password, never seen in clear. */
            $admin = Admin::create([
                'name' => $user->name,
                'email' => $user->email,
                'password' => $user->getRawOriginal('password'),
                'role' => $role,
            ]);

            BlogPost::where('user_id', $user->id)->update(['admin_id' => $admin->id, 'user_id' => null]);

            return $admin;
        });

        $posts = $admin->blogPosts()->count();

        $this->components->info("{$admin->name} <{$admin->email}> is now an administrator ({$role->getLabel()}), with the same password.");
        if ($posts > 0) {
            $this->components->info("Moved {$posts} journal ".str('post')->plural($posts).' to their admin account.');
        }

        return self::SUCCESS;
    }
}
