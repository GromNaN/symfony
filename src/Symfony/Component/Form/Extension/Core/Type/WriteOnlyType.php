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

namespace Symfony\Component\Form\Extension\Core\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\DataMapper\WriteOnlyDataMapper;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Util\FormUtil;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A compound field for sensitive values (API keys, secrets), write-only in
 * two directions:
 *
 * - the stored value never reaches the rendered widget: only a value typed by
 *   the user does, and an empty input on a failed submit shows nothing but
 *   what was typed;
 * - an empty (or whitespace-only) submission leaves the stored value
 *   untouched, so editing a secret never forces the operator to retype it;
 * - an optional "clear" child sets the stored value to null explicitly and
 *   wins over a simultaneously typed value.
 */
class WriteOnlyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('value', $options['value_type'], array_replace($options['value_options'], [
                // The injected widget's own attribute take precedence for
                // aesthetics, the protective ones always win afterwards.
                'attr' => array_replace($options['value_options']['attr'] ?? [], ['autocomplete' => 'off'], $options['attr']),
                // Required is never propagated to the inner input: an empty
                // submission is the "keep the stored value" signal and must
                // stay submittable.
                'required' => false,
                'trim' => false,
                'empty_data' => null,
                'error_bubbling' => true,
            ]))
            ->setDataMapper(new WriteOnlyDataMapper());

        if ($options['allow_clear']) {
            $builder->add('clear', CheckboxType::class, [
                'mapped' => false,
                'required' => false,
                'label' => $options['clear_label'],
                'translation_domain' => $options['clear_translation_domain'],
                'attr' => $options['clear_attr'],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'compound' => true,
                'data_class' => null,
                // A sensitive value is nullable by nature, hence optional by
                // default. Enforce presence at creation with a constraint.
                'required' => false,
                // Prevents the compound default (an empty array for a form
                // without data_class) from being substituted when the stored
                // value is itself empty: the mapper must keep seeing the true
                // old value to leave it untouched.
                'empty_data' => fn (FormInterface $form): mixed => $form->getData(),
                // The clear checkbox is optional: off by default and only
                // added when explicitly requested. Passing null opts into the
                // smart rule, which offers the clear checkbox only when the
                // field can actually hold null (not required).
                'allow_clear' => false,
                // A placeholder applied to the inner input only when a value
                // already exists, telling the operator that an empty field
                // keeps it (for example "Leave blank to keep the current
                // value"). Never echoes the value itself.
                'existing_value_placeholder' => null,
                'clear_label' => 'Delete current value',
                // null uses the parent's domain, false disables translation,
                // any string names a domain. Same contract as ChoiceType's
                // choice_translation_domain.
                'clear_translation_domain' => null,
                'clear_attr' => [],
                'value_type' => TextType::class,
                'value_options' => [],
                // Field-level constraints validate the resolved scalar and must
                // render on this field's own row, not bubble to the parent.
                'error_bubbling' => false,
            ])
            ->setAllowedTypes('allow_clear', ['bool', 'null'])
            ->setAllowedTypes('existing_value_placeholder', ['string', 'null'])
            ->setAllowedTypes('clear_label', 'string')
            ->setAllowedTypes('clear_translation_domain', ['null', 'bool', 'string'])
            ->setAllowedTypes('clear_attr', 'array')
            ->setAllowedTypes('value_type', 'string')
            ->setAllowedTypes('value_options', 'array')
            ->setNormalizer('allow_clear', static fn (Options $options, ?bool $allowClear): bool => $allowClear ?? !$options['required'])
        ;
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $hasValue = !FormUtil::isEmpty($form->getData());

        $view->vars['value'] = '';
        // Lets custom themes hint that an empty field keeps the stored value.
        $view->vars['has_value'] = $hasValue;
        // Only offer to clear when a value actually exists.
        $view->vars['show_clear'] = $options['allow_clear'] && $hasValue;
        $view->vars['existing_value_placeholder'] = $options['existing_value_placeholder'];
    }

    public function getBlockPrefix(): string
    {
        return 'write_only';
    }
}
