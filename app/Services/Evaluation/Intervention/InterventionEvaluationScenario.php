<?php

namespace App\Services\Evaluation\Intervention;

/**
 * Common contract for M6-03 evaluation scenarios across the four evaluation kinds
 * (intervention selection, next action, reassessment, intervention response).
 *
 * Implementations are pure, synthetic, independently authored value objects. They
 * never reference or execute the production services under evaluation.
 */
interface InterventionEvaluationScenario
{
    public function scenarioId(): string;

    /**
     * One of: intervention, next_action, reassessment, response.
     */
    public function kind(): string;

    public function category(): string;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
