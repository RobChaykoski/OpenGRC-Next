<?php

namespace App\Console\Commands;

use App\Enums\AccessRuleScope;
use App\Models\AccessRule;
use App\Services\Access\AccessRuleEvaluator;
use Illuminate\Console\Command;

/**
 * Inspect, test and disable access rules (ADR 0047).
 *
 * This is the recovery path when a bad allow/deny rule locks the administrator
 * out of the UI, and the quickest way to answer "would this IP/email get in?".
 */
class AccessRulesCommand extends Command
{
    protected $signature = 'opengrc:access:rules
                            {--all : Include inactive rules in the list}
                            {--scope= : access or registration}
                            {--ip= : Test an IP address against the rules}
                            {--email= : Test an email address against the rules}
                            {--disable= : Disable the rule with this ID}';

    protected $description = 'List, test or disable platform access rules';

    public function handle(AccessRuleEvaluator $evaluator): int
    {
        if ($this->option('disable') !== null) {
            return $this->disable((int) $this->option('disable'));
        }

        $scope = $this->scope();

        if ($scope === null) {
            return self::FAILURE;
        }

        if ($this->option('ip') !== null || $this->option('email') !== null) {
            $decision = $evaluator->evaluate(
                $scope,
                $this->option('ip') !== null ? (string) $this->option('ip') : null,
                $this->option('email') !== null ? (string) $this->option('email') : null,
            );

            $this->line($decision->allowed ? '<fg=green>Allowed</>' : '<fg=red>Denied</>');
            $this->line('Reason: '.($decision->reason ?? 'allowed'));

            if ($decision->ruleId !== null) {
                $this->line("Matched rule: {$decision->ruleId}");
            }

            return $decision->allowed ? self::SUCCESS : self::FAILURE;
        }

        $rules = AccessRule::query()
            ->forScope($scope)
            ->when(! $this->option('all'), fn ($query) => $query->active())
            ->orderBy('type')
            ->orderBy('effect')
            ->get();

        if ($rules->isEmpty()) {
            $this->info("No rules for scope [{$scope->value}].");

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Type', 'Effect', 'Value', 'Active', 'Expires', 'Reason'],
            $rules->map(fn (AccessRule $rule): array => [
                (string) $rule->getKey(),
                $rule->type->getLabel(),
                $rule->effect->getLabel(),
                $rule->value,
                $rule->is_active ? 'yes' : 'no',
                $rule->expires_at?->format('Y-m-d H:i') ?? 'never',
                $rule->note ?? '—',
            ])->all()
        );

        return self::SUCCESS;
    }

    private function scope(): ?AccessRuleScope
    {
        $scope = $this->option('scope');

        if ($scope === null) {
            return AccessRuleScope::Access;
        }

        $resolved = AccessRuleScope::tryFrom((string) $scope);

        if (! $resolved) {
            $this->error('Scope must be access or registration.');

            return null;
        }

        return $resolved;
    }

    private function disable(int $id): int
    {
        $rule = AccessRule::query()->find($id);

        if ($rule === null) {
            $this->error("No access rule found for ID {$id}.");

            return self::FAILURE;
        }

        $rule->update(['is_active' => false]);

        $this->info("Disabled access rule {$id} ({$rule->effect->getLabel()} {$rule->value}).");

        return self::SUCCESS;
    }
}
