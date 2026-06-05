# Admin User Creation Command Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create a Laravel Artisan command (`app:make-admin`) that interactively prompts for user details to create a new administrator account.

**Architecture:** A single Artisan command class utilizing Laravel's Prompts for interactive input and validation, followed by Eloquent model creation.

**Tech Stack:** Laravel (PHP), Artisan Console.

---

### Task 1: Create the Command and Test

**Files:**
- Create: `backend/app/Console/Commands/MakeAdminCommand.php`
- Create: `backend/tests/Feature/MakeAdminCommandTest.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/MakeAdminCommandTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MakeAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_admin_user()
    {
        $this->artisan('app:make-admin')
            ->expectsQuestion('What is the admin\'s name?', 'Admin User')
            ->expectsQuestion('What is the admin\'s email address?', 'admin@example.com')
            ->expectsQuestion('What should the password be?', 'password123')
            ->expectsOutputToContain('Admin user created successfully!')
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]);

        $user = User::where('email', 'admin@example.com')->first();
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_it_validates_unique_email()
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $this->artisan('app:make-admin')
            ->expectsQuestion('What is the admin\'s name?', 'Admin Two')
            ->expectsQuestion('What is the admin\'s email address?', 'existing@example.com')
            ->expectsOutputToContain('The email has already been taken.')
            ->assertExitCode(1);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd backend && php artisan test --filter MakeAdminCommandTest`
Expected: FAIL with "Command "app:make-admin" is not defined."

- [ ] **Step 3: Write minimal implementation**

Create `backend/app/Console/Commands/MakeAdminCommand.php`:
```php
<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use function Laravel\Prompts\text;
use function Laravel\Prompts\password;
use function Laravel\Prompts\info;
use function Laravel\Prompts\error;

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
                    'email' => ['required', 'email', 'unique:users,email']
                ]);

                if ($validator->fails()) {
                    return $validator->errors()->first('email');
                }

                return null;
            }
        );

        $password = password(
            label: 'What should the password be?',
            required: true,
            validate: fn (string $value) => match (true) {
                strlen($value) < 8 => 'The password must be at least 8 characters.',
                default => null
            }
        );

        // Check if validator failed during testing (Laravel Prompts validation handling in tests can sometimes bypass the validation closure if expectsQuestion is used without proper setup, so we double check)
        $validator = Validator::make(['email' => $email], [
            'email' => ['required', 'email', 'unique:users,email']
        ]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first('email'));
            return 1;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'admin',
            // Default required fields for User model if any
        ]);

        info('Admin user created successfully!');
        
        return 0;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd backend && php artisan test --filter MakeAdminCommandTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add backend/app/Console/Commands/MakeAdminCommand.php backend/tests/Feature/MakeAdminCommandTest.php
git commit -m "feat(cli): add app:make-admin command for creating admin users"
```
