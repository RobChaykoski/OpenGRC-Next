<?php

namespace App\Filament\Pages;

use App\Models\ControlAttestation;
use App\Models\User;
use App\Services\Controls\ControlAttestationService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * The current user's pending control attestations.
 *
 * Only implementations they own are listed. An admin can answer from the
 * implementation page; this page is the owner's queue, not a global register.
 */
class MyAttestations extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-check-badge';

    protected static string|\UnitEnum|null $navigationGroup = 'Entities';

    protected static ?int $navigationSort = 46;

    protected static ?string $slug = 'my-attestations';

    protected string $view = 'filament.pages.my-attestations';

    /** @var array<int|string, string> */
    public array $statements = [];

    public static function getNavigationLabel(): string
    {
        return 'My attestations';
    }

    public function getTitle(): string
    {
        return 'My control attestations';
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return null;
        }

        $count = ControlAttestation::query()
            ->pending()
            ->whereHas('implementation', fn ($query) => $query->where('implementation_owner_id', $user->getKey()))
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'attestations' => $this->attestations(),
        ];
    }

    /**
     * @return Collection<int, ControlAttestation>
     */
    public function attestations(): Collection
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return collect();
        }

        return ControlAttestation::query()
            ->pending()
            ->whereHas('implementation', fn ($query) => $query->where('implementation_owner_id', $user->getKey()))
            ->with(['implementation', 'requestedBy'])
            ->orderBy('due_at')
            ->get();
    }

    public function attest(int $attestationId): void
    {
        $this->respond($attestationId, true);
    }

    public function decline(int $attestationId): void
    {
        $this->respond($attestationId, false);
    }

    protected function respond(int $attestationId, bool $attest): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return;
        }

        $attestation = ControlAttestation::query()
            ->pending()
            ->whereKey($attestationId)
            ->whereHas('implementation', fn ($query) => $query->where('implementation_owner_id', $user->getKey()))
            ->first();

        if (! $attestation instanceof ControlAttestation) {
            Notification::make()
                ->title('That attestation is not available.')
                ->danger()
                ->send();

            return;
        }

        $statement = trim((string) ($this->statements[$attestationId] ?? ''));

        if ($statement === '') {
            Notification::make()
                ->title('Add a statement before answering.')
                ->body('Say what is true, or why it cannot be confirmed.')
                ->warning()
                ->send();

            return;
        }

        try {
            $service = app(ControlAttestationService::class);

            if ($attest) {
                $service->attest($attestation, $user, $statement);
            } else {
                $service->decline($attestation, $user, $statement);
            }
        } catch (InvalidArgumentException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }

        unset($this->statements[$attestationId]);

        Notification::make()
            ->title($attest ? 'Attestation recorded' : 'Attestation declined')
            ->success()
            ->send();
    }
}
