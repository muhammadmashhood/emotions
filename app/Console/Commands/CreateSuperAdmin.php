<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateSuperAdmin extends Command
{
    protected $signature = 'app:create-super-admin {--name=} {--email=} {--password=}';

    protected $description = 'Create the first (or another) super admin account';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Name', 'Administrator');
        $email = $this->option('email') ?: $this->ask('Email');
        $password = $this->option('password') ?: $this->secret('Password (min 12 characters)');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            ['name' => 'required|string|max:255', 'email' => 'required|email|unique:users,email', 'password' => 'required|string|min:12'],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => Role::SuperAdmin,
            'hospital_id' => null,
            'is_active' => true,
        ]);

        $this->info("Super admin {$user->email} created.");

        return self::SUCCESS;
    }
}
