<?php

namespace App\Filament\Resources\ControlCrosswalkResource\Pages;

use App\Filament\Resources\ControlCrosswalkResource;
use App\Models\Control;
use App\Models\User;
use App\Services\Controls\ControlCrosswalkService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CreateControlCrosswalk extends CreateRecord
{
    protected static string $resource = ControlCrosswalkResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $source = Control::query()->find($data['source_control_id']);
        $target = Control::query()->find($data['target_control_id']);

        if (! $source instanceof Control || ! $target instanceof Control) {
            throw ValidationException::withMessages([
                'source_control_id' => 'Select two existing controls.',
            ]);
        }

        try {
            return app(ControlCrosswalkService::class)->create([
                'source_control_id' => (int) $source->getKey(),
                'target_control_id' => (int) $target->getKey(),
                'rationale' => (string) $data['rationale'],
                'confidence' => (string) $data['confidence'],
            ], $user);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'target_control_id' => $exception->getMessage(),
            ]);
        }
    }
}
