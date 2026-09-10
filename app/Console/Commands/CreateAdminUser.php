<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

#[Signature('zerrors:create-admin {name? : The admin\'s name} {email? : The admin\'s email address} {password? : The admin\'s password} {--organization= : Name of an existing or new organization to own}')]
#[Description('Create a user and attach them as the owner of an organization.')]
class CreateAdminUser extends Command
{
    public function handle(): int
    {
        $name = $this->argument('name') ?? $this->ask('Name');
        $email = $this->argument('email') ?? $this->ask('Email address');
        $password = $this->argument('password') ?? $this->secret('Password');
        $organizationName = $this->option('organization') ?? $this->ask('Organization name');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password, 'organization' => $organizationName],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:8'],
                'organization' => ['required', 'string', 'max:255'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ]);

        $organization = Organization::firstOrCreate(['name' => $organizationName]);

        $organization->users()->attach($user->id, ['role' => 'owner']);

        $this->info("Admin user \"{$user->email}\" created and attached as owner of \"{$organization->name}\".");

        return self::SUCCESS;
    }
}
