<?php

namespace Symfony\Component\ObjectMapper\Condition;

use Symfony\Component\ObjectMapper\ConditionCallableInterface;

final readonly class All implements ConditionCallableInterface
{
    /**
     * @param ConditionCallableInterface[] $conditions
     */
    private array $conditions;
    public function __construct(ConditionCallableInterface ...$conditions)
    {
        $this->conditions = $conditions;
    }

    public function __invoke(mixed $value, object $source, ?object $target = null): bool
    {
        foreach ($this->conditions as $condition) {
            if (!$condition($value, $source, $target)) {
                return false;
            }
        }

        return true;
    }
}
