# Design Spec: Admin User Creation Command

## Overview
Currently, there is no way to create an admin user from the command line. This spec defines a new Artisan command to interactively create admin accounts, which is especially useful for initial setup and bootstrapping.

## Architecture
- **Location**: `backend/app/Console/Commands/MakeAdminCommand.php`
- **Command Signature**: `app:make-admin`

## Workflow
1. The command will run interactively.
2. It will prompt for the admin's **Name**.
3. It will prompt for the admin's **Email**. It will validate that the email is a valid format and is unique in the `users` table.
4. It will prompt for a **Password** (using hidden input).
5. It will create a new `User` model instance with `role` set to `'admin'`, and the password properly hashed using `Hash::make()`.
6. It will output a success message confirming the creation of the admin account.

## Testing
- Unit/Feature test to run the artisan command, assert the prompts, and verify the database has the new admin user.
