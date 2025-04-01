<?php

namespace Symfony\Component\ObjectMapper\Condition;

use Symfony\Component\ObjectMapper\ConditionCallableInterface;

final readonly class TargetClass implements ConditionCallableInterface
{
    public function __construct(private string $targetClass) {}

    public function __invoke(mixed $value, object $source, ?object $target = null): bool
    {
        return $target && is_a($target, $this->targetClass, true);
    }
}
