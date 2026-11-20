<?php

namespace App\Filament\Resources\ControlCrosswalkResource\Pages;

use App\Enums\CrosswalkStatus;
use App\Filament\Resources\ControlCrosswalkResource;
use App\Models\Control;
use App\Models\ControlCrosswalk;
use App\Services\Controls\ControlCrosswalkService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class EditControlCrosswalk extends EditRecord
{
    protected static string $resource = ControlCrosswalkResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof ControlCrosswalk || $record->status !== CrosswalkStatus::Draft) {
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
            app(ControlCrosswalkService::class)->assertPair($source, $target, (int) $record->getKey());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'target_control_id' => $exception->getMessage(),
            ]);
        }

        $record->update([
            'source_control_id' => $source->getKey(),
            'target_control_id' => $target->getKey(),
            'rationale' => (string) $data['rationale'],
            'confidence' => (string) $data['confidence'],
        ]);

        return $record;
    }
}
