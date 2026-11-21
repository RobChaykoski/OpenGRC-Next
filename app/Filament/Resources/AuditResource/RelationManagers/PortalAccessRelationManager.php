<?php

namespace App\Filament\Resources\AuditResource\RelationManagers;

use App\Models\Audit;
use App\Models\AuditPortalGrant;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Issues and retires the read-only auditor links for one audit (ADR 0045).
 *
 * The raw token is shown once, immediately after issue or rotation. It is never
 * stored, so the only recovery path for a lost link is rotation.
 */
class PortalAccessRelationManager extends RelationManager
{
    protected static string $relationship = 'portalGrants';

    /**
     * The link issued or rotated during this request. Not persisted; cleared
     * when the page is reloaded.
     */
    public ?string $issuedLink = null;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('audit-portal.admin.title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['creator', 'revoker'])->latest())
            ->emptyStateHeading(__('audit-portal.admin.empty_heading'))
            ->emptyStateDescription(__('audit-portal.admin.empty_description'))
            ->columns([
                TextColumn::make('label')
                    ->label(__('audit-portal.admin.label_column'))
                    ->searchable()
                    ->wrap(),
                TextColumn::make('state')
                    ->label(__('audit-portal.admin.status'))
                    ->badge()
                    ->getStateUsing(fn (AuditPortalGrant $record): string => $record->state())
                    ->formatStateUsing(fn (string $state): string => __('audit-portal.admin.state_'.$state))
                    ->color(fn (AuditPortalGrant $record): string => match ($record->state()) {
                        'active' => 'success',
                        'expired' => 'warning',
                        'revoked' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('expires_at')
                    ->label(__('audit-portal.admin.expires_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('last_accessed_at')
                    ->label(__('audit-portal.admin.last_accessed'))
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('access_count')
                    ->label(__('audit-portal.admin.access_count'))
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('creator.name')
                    ->label(__('audit-portal.admin.issued_by'))
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->recordActions([
                Action::make('views')
                    ->label(__('audit-portal.admin.views'))
                    ->icon('heroicon-o-clock')
                    ->modalHeading(__('audit-portal.admin.views_heading'))
                    ->modalDescription(__('audit-portal.admin.views_description'))
                    ->modalContent(fn (AuditPortalGrant $record) => view('filament.audit-portal-views', [
                        'views' => $record->views()->latest('viewed_at')->limit(20)->get(),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('audit-portal.admin.close')),
                Action::make('rotate')
                    ->label(__('audit-portal.admin.rotate'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false)
                    ->requiresConfirmation()
                    ->modalHeading(__('audit-portal.admin.rotate'))
                    ->modalDescription(__('audit-portal.admin.rotate_confirm'))
                    ->fillForm(fn (AuditPortalGrant $record): array => [
                        'label' => $record->label,
                        'expires_in_days' => 30,
                    ])
                    ->schema([
                        TextInput::make('label')
                            ->label(__('audit-portal.admin.label'))
                            ->required()
                            ->maxLength(120),
                        Select::make('expires_in_days')
                            ->label(__('audit-portal.admin.expires_in'))
                            ->options($this->expiryOptions())
                            ->default(30)
                            ->required(),
                    ])
                    ->action(function (AuditPortalGrant $record, array $data): void {
                        $record->rotate(
                            label: $data['label'],
                            expiresAt: now()->addDays((int) $data['expires_in_days']),
                            actor: auth()->user(),
                        );

                        $this->issuedLink = route('audit-portal.show', ['token' => $record->plainToken]);

                        Notification::make()
                            ->title(__('audit-portal.admin.rotated'))
                            ->body(__('audit-portal.admin.rotated_body'))
                            ->success()
                            ->persistent()
                            ->send();
                    }),
                Action::make('revoke')
                    ->label(__('audit-portal.admin.revoke'))
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (AuditPortalGrant $record): bool => (auth()->user()?->isAdmin() ?? false) && $record->state() === 'active')
                    ->requiresConfirmation()
                    ->modalHeading(__('audit-portal.admin.revoke'))
                    ->modalDescription(__('audit-portal.admin.revoke_confirm'))
                    ->action(function (AuditPortalGrant $record): void {
                        $record->revoke(auth()->user());

                        Notification::make()
                            ->title(__('audit-portal.admin.revoked'))
                            ->body(__('audit-portal.admin.revoked_body'))
                            ->success()
                            ->send();
                    }),
            ])
            ->headerActions([
                Action::make('invite')
                    ->label(__('audit-portal.admin.invite'))
                    ->icon('heroicon-o-link')
                    ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false)
                    ->modalHeading(__('audit-portal.admin.invite_heading'))
                    ->modalDescription(__('audit-portal.admin.invite_description'))
                    ->schema([
                        TextInput::make('label')
                            ->label(__('audit-portal.admin.label'))
                            ->placeholder(__('audit-portal.admin.label_placeholder'))
                            ->required()
                            ->maxLength(120),
                        Select::make('expires_in_days')
                            ->label(__('audit-portal.admin.expires_in'))
                            ->options($this->expiryOptions())
                            ->default(30)
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        /** @var Audit $audit */
                        $audit = $this->getOwnerRecord();

                        $grant = AuditPortalGrant::issue(
                            audit: $audit,
                            label: $data['label'],
                            expiresAt: now()->addDays((int) $data['expires_in_days']),
                            creator: auth()->user(),
                        );

                        $this->issuedLink = route('audit-portal.show', ['token' => $grant->plainToken]);

                        Notification::make()
                            ->title(__('audit-portal.admin.issued'))
                            ->body(__('audit-portal.admin.issued_body'))
                            ->success()
                            ->persistent()
                            ->send();
                    }),
                Action::make('showIssuedLink')
                    ->label(__('audit-portal.admin.show_link'))
                    ->icon('heroicon-o-clipboard')
                    ->color('warning')
                    ->visible(fn (): bool => $this->issuedLink !== null)
                    ->modalHeading(__('audit-portal.admin.link_heading'))
                    ->modalDescription(__('audit-portal.admin.link_description'))
                    ->modalContent(fn () => view('filament.audit-portal-link', ['url' => $this->issuedLink]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('audit-portal.admin.close')),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private function expiryOptions(): array
    {
        return collect([7, 14, 30, 60, 90])
            ->mapWithKeys(fn (int $days): array => [$days => __('audit-portal.admin.days', ['count' => $days])])
            ->all();
    }
}
