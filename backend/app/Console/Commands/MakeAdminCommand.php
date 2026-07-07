<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use function Laravel\Prompts\text;
use function Laravel\Prompts\password;
use function Laravel\Prompts\info;

class MakeAdminCommand extends Command
{
    protected $signature = 'app:make-admin';
    protected $description = 'Create a new admin user interactively';

    public function handle()
    {
        $name = text(
            label: 'What is the admin\'s name?',
            required: true,
            validate: fn (string $value) => match (true) {
                strlen($value) < 2 => 'The name must be at least 2 characters.',
                strlen($value) > 255 => 'The name must not exceed 255 characters.',
                default => null
            }
        );

        $email = text(
            label: 'What is the admin\'s email address?',
            required: true,
            validate: function (string $value) {
                $validator = Validator::make(['email' => $value], [
                    'email' => ['required', 'email']
                ]);

                if ($validator->fails()) {
                    return $validator->errors()->first('email');
                }

                return null;
            }
        );

        // Check if email is already taken before asking for password
        $validator = Validator::make(['email' => $email], [
            'email' => ['unique:users,email']
        ]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first('email'));
            return 1;
        }

        $password = password(
            label: 'What should the password be?',
            required: true,
            validate: fn (string $value) => match (true) {
                strlen($value) < 8 => 'The password must be at least 8 characters.',
                default => null
            }
        );

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        info('Admin user created successfully!');
        
        return 0;
    }
}
