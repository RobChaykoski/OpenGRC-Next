<?php

namespace App\Filament\Vendor\Pages\Auth;

use App\Models\VendorUser;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * First-login password setup for vendor accounts created by magic link.
 *
 * The user is already authenticated by the signed link; this page only turns a
 * passwordless account into one with credentials.
 */
class SetPassword extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.vendor.pages.auth.set-password';

    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    public function mount(): void
    {
        $user = Filament::auth()->user();

        if (! $user instanceof VendorUser || $user->hasPassword()) {
            redirect()->intended(Filament::getUrl());

            return;
        }

        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->required()
                    ->rule(Password::default()->mixedCase()->uncompromised(3)->min(12))
                    ->same('passwordConfirmation')
                    ->validationAttribute('password'),
                TextInput::make('passwordConfirmation')
                    ->label('Confirm Password')
                    ->password()
                    ->required()
                    ->dehydrated(false),
            ])
            ->statePath('data');
    }

    public function setPassword(): void
    {
        $data = $this->form->getState();

        $user = Filament::auth()->user();

        if ($user instanceof VendorUser) {
            $user->update([
                'password' => Hash::make($data['password']),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);

            Notification::make()
                ->title('Password Set')
                ->body('Your password has been set successfully.')
                ->success()
                ->send();
        }

        redirect()->intended(Filament::getUrl());
    }

    public function getTitle(): string
    {
        return 'Set Your Password';
    }

    public function getHeading(): string
    {
        return 'Set Your Password';
    }

    public function getSubheading(): ?string
    {
        return 'Please create a password to secure your account.';
    }
}
