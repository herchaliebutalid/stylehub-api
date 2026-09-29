<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\PhilippinePhoneNumber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CreateInitialAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * This command intentionally exposes no password or personal
     * information through command-line arguments.
     */
    protected $signature = 'stylehub:admin:create';

    /**
     * The console command description.
     */
    protected $description = 'Create the first StyleHub administrator account';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        /*
        |--------------------------------------------------------------------------
        | Bootstrap Protection
        |--------------------------------------------------------------------------
        |
        | This command exists only for creating the initial administrator.
        | Once any administrator has existed, additional administrators
        | must eventually be managed through protected administrator tools.
        |
        */

        if (
            User::withTrashed()
                ->where(
                    'role',
                    UserRole::Admin->value
                )
                ->exists()
        ) {
            $this->error(
                'An administrator account already exists. '
                .'This bootstrap command can only be used for the first administrator.'
            );

            return self::FAILURE;
        }

        $firstName = trim(
            (string) $this->ask('First name')
        );

        $middleNameInput = trim(
            (string) $this->ask(
                'Middle name (optional)'
            )
        );

        $middleName = $middleNameInput === ''
            ? null
            : $middleNameInput;

        $lastName = trim(
            (string) $this->ask('Last name')
        );

        $email = mb_strtolower(
            trim(
                (string) $this->ask('Email')
            )
        );

        $phoneNumber =
            PhilippinePhoneNumber::normalize(
                (string) $this->ask(
                    'Philippine mobile number'
                )
            );

        /*
         * secret() prevents the password from being displayed
         * while it is entered in the terminal.
         */
        $password = (string) $this->secret(
            'Password'
        );

        $passwordConfirmation = (string) $this->secret(
            'Confirm password'
        );

        $data = [
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'email' => $email,
            'phone_number' => $phoneNumber,
            'password' => $password,
            'password_confirmation' =>
                $passwordConfirmation,
        ];

        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        |
        | Administrator creation follows the same strong password policy used
        | by the public authentication API.
        |
        */

        $validator = Validator::make(
            $data,
            [
                'first_name' => [
                    'required',
                    'string',
                    'min:1',
                    'max:100',
                ],

                'middle_name' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'last_name' => [
                    'required',
                    'string',
                    'min:1',
                    'max:100',
                ],

                'email' => [
                    'required',
                    'string',
                    'email:rfc',
                    'max:255',
                    Rule::unique(
                        User::class,
                        'email'
                    ),
                ],

                'phone_number' => [
                    'required',
                    'string',
                    'max:20',
                    'regex:/^\+639\d{9}$/',
                ],

                'password' => [
                    'required',
                    'string',
                    'confirmed',
                    Password::defaults(),
                ],

                'password_confirmation' => [
                    'required',
                    'string',
                    'max:64',
                ],
            ],
            [
                'phone_number.regex' =>
                    'The phone number must be a valid Philippine mobile number.',

                'password.confirmed' =>
                    'The password confirmation does not match.',
            ]
        );

        if ($validator->fails()) {
            $this->newLine();

            $this->error(
                'Administrator account was not created because validation failed.'
            );

            foreach (
                $validator->errors()->all()
                as $message
            ) {
                $this->line(
                    ' - '.$message
                );
            }

            return self::FAILURE;
        }

        $validated = $validator->validated();

        $this->newLine();

        $this->line(
            'Administrator name: '
            .$validated['first_name']
            .' '
            .($validated['middle_name']
                ? $validated['middle_name'].' '
                : '')
            .$validated['last_name']
        );

        $this->line(
            'Email: '.$validated['email']
        );

        $this->line(
            'Phone: '.$validated['phone_number']
        );

        $this->newLine();

        if (
            ! $this->confirm(
                'Create this administrator account?',
                true
            )
        ) {
            $this->warn(
                'Administrator creation cancelled.'
            );

            return self::SUCCESS;
        }

        $admin = DB::transaction(
            function () use ($validated): User {
                /*
                 * Re-check inside the transaction before writing.
                 */
                if (
                    User::withTrashed()
                        ->where(
                            'role',
                            UserRole::Admin->value
                        )
                        ->exists()
                ) {
                    throw new \RuntimeException(
                        'An administrator account already exists.'
                    );
                }

                $admin = new User([
                    'first_name' =>
                        $validated['first_name'],

                    'middle_name' =>
                        $validated['middle_name'],

                    'last_name' =>
                        $validated['last_name'],

                    'email' =>
                        $validated['email'],

                    'phone_number' =>
                        $validated['phone_number'],

                    'password' =>
                        $validated['password'],
                ]);

                /*
                 * Security-sensitive fields are assigned explicitly by
                 * trusted backend code, never through mass assignment.
                 */
                $admin->role =
                    UserRole::Admin;

                $admin->status =
                    UserStatus::Active;

                $admin->save();

                return $admin;
            }
        );

        $this->newLine();

        $this->info(
            'StyleHub administrator created successfully.'
        );

        $this->line(
            'Administrator ID: '.$admin->id
        );

        $this->line(
            'Email: '.$admin->email
        );

        $this->newLine();

        $this->warn(
            'Keep the administrator password private. '
            .'Do not store it in source code or commit it to Git.'
        );

        return self::SUCCESS;
    }
}