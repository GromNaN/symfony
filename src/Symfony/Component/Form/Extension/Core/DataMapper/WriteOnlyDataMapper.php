<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Form\Extension\Core\DataMapper;

use Symfony\Component\Form\DataMapperInterface;

/**
 * Maps the two children of WriteOnlyType onto the stored scalar value.
 *
 * The compound resolves its two children by writing the view data by
 * reference, the same way RadioListMapper resolves the radios of an expanded
 * ChoiceType. The resolution is synchronous and idempotent:
 *
 *  1. the "clear" child is checked -> the value becomes null (the explicit
 *     deletion wins over a simultaneously typed value);
 *  2. a real value was typed -> it overwrites;
 *  3. nothing, or blank, was typed -> the current value is left untouched.
 */
class WriteOnlyDataMapper implements DataMapperInterface
{
    public function mapDataToForms(mixed $data, \Traversable $forms): void
    {
        foreach ($forms as $form) {
            match ($form->getName()) {
                // The stored value never enters the widget: the inner input is
                // always rendered empty. Only what the user types becomes the
                // widget's view data after a submit.
                'value' => $form->setData(''),
                'clear' => $form->setData(false),
                default => null,
            };
        }
    }

    public function mapFormsToData(\Traversable $forms, mixed &$value): void
    {
        $typed = null;
        $clear = false;

        foreach ($forms as $form) {
            match ($form->getName()) {
                'value' => $typed = $form->getData(),
                'clear' => $clear = true === $form->getData(),
                default => null,
            };
        }

        if ($clear) {
            $value = null;

            return;
        }

        if (!$this->isBlank($typed)) {
            $value = $typed;
        }
    }

    /**
     * Blank = null or whitespace-only string. Whitespace counts as "nothing
     * was typed", because a write-only field is meant for secrets: an all-space
     * field is an accidental empty, not a deliberate value. A real value keeps
     * its surrounding spaces, only the blank check trims.
     */
    private function isBlank(mixed $value): bool
    {
        return null === $value || (\is_string($value) && '' === trim($value));
    }
}
