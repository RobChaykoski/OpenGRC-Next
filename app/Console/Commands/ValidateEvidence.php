<?php

namespace App\Console\Commands;

use App\Models\Control;
use App\Models\ControlValidationRule;
use App\Models\EvidenceSource;
use App\Services\Evidence\ControlValidator;
use App\Services\Evidence\ValidationRun;
use Illuminate\Console\Command;

class ValidateEvidence extends Command
{
    protected $signature = 'opengrc:evidence:validate
                            {--control= : Validate the rules for one control ID}
                            {--source= : Validate the rules for one evidence source ID}
                            {--rule= : Validate one rule ID}';

    protected $description = 'Evaluate evidence validation rules and record control status, drift, and findings';

    public function handle(ControlValidator $validator): int
    {
        $run = $this->runValidation($validator);

        if ($run === null) {
            return self::FAILURE;
        }

        $this->table(
            ['Result', 'Count'],
            [
                ['Validations written', $run->validations],
                ['Effective', $run->effective],
                ['Failed', $run->failed],
                ['Unknown', $run->unknown],
                ['Findings opened', $run->findingsOpened],
                ['Findings updated', $run->findingsUpdated],
                ['Findings resolved', $run->findingsResolved],
            ]
        );

        return self::SUCCESS;
    }

    protected function runValidation(ControlValidator $validator): ?ValidationRun
    {
        if ($this->option('rule') !== null) {
            $rule = ControlValidationRule::query()->find($this->option('rule'));

            if (! $rule instanceof ControlValidationRule) {
                $this->error('No validation rule found for that ID.');

                return null;
            }

            return $validator->validateRule($rule);
        }

        if ($this->option('control') !== null) {
            $control = Control::query()->find($this->option('control'));

            if (! $control instanceof Control) {
                $this->error('No control found for that ID.');

                return null;
            }

            return $validator->validateControl($control);
        }

        if ($this->option('source') !== null) {
            $source = EvidenceSource::query()->find($this->option('source'));

            if (! $source instanceof EvidenceSource) {
                $this->error('No evidence source found for that ID.');

                return null;
            }

            return $validator->validateSource($source);
        }

        return $validator->validateAll();
    }
}
