<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\Type;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\WriteOnlyType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Test\FormIntegrationTestCase;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validation;

class WriteOnlyTypeTest extends FormIntegrationTestCase
{
    protected function getExtensions(): array
    {
        return [
            new ValidatorExtension(Validation::createValidator()),
        ];
    }

    /**
     * Root form bound to a scalar, mirroring the expanded ChoiceType tests:
     * this is where the write-only semantics live.
     */
    private function scalarForm(?string $secret, array $options = []): FormInterface
    {
        return $this->factory->create(WriteOnlyType::class, $secret, $options);
    }

    /**
     * The realistic wiring: a WriteOnlyType mapped to a property of an object.
     */
    private function modelForm(object $model, array $options = []): FormInterface
    {
        return $this->factory
            ->createBuilder(FormType::class, $model)
            ->add('secret', WriteOnlyType::class, $options)
            ->getForm();
    }

    public function testStoredValueNeverAppearsInTheView()
    {
        $view = $this->scalarForm('s3cret')->createView();

        $this->assertSame('', $view->vars['value']);
        $this->assertSame('', $view['value']->vars['value']);
    }

    public function testEmptySubmissionKeepsStoredValue()
    {
        $form = $this->scalarForm('s3cret');
        $form->submit(['value' => '']);

        $this->assertTrue($form->isSynchronized());
        $this->assertSame('s3cret', $form->getData());
    }

    public function testWhitespaceOnlySubmissionKeepsStoredValue()
    {
        $form = $this->scalarForm('s3cret');
        $form->submit(['value' => '   ']);

        $this->assertTrue($form->isSynchronized());
        $this->assertSame('s3cret', $form->getData());
    }

    public function testTypedValueOverwrites()
    {
        $form = $this->scalarForm('s3cret');
        $form->submit(['value' => 'new-secret']);

        $this->assertSame('new-secret', $form->getData());
    }

    public function testRealValueKeepsItsSurroundingSpaces()
    {
        $form = $this->scalarForm('s3cret');
        $form->submit(['value' => '  abc  ']);

        $this->assertSame('  abc  ', $form->getData());
    }

    public function testClearCheckboxIsOptionalByDefault()
    {
        $form = $this->scalarForm('s3cret');

        $this->assertFalse($form->has('clear'));
    }

    public function testClearSetsNull()
    {
        $form = $this->scalarForm('s3cret', ['allow_clear' => true]);
        $form->submit(['value' => '', 'clear' => '1']);

        $this->assertNull($form->getData());
    }

    public function testClearWinsOverTypedValue()
    {
        $form = $this->scalarForm('s3cret', ['allow_clear' => true]);
        $form->submit(['value' => 'ignored', 'clear' => '1']);

        $this->assertNull($form->getData());
    }

    public function testAllowClearNullOffersTheCheckboxWhenNotRequired()
    {
        $form = $this->scalarForm('s3cret', ['allow_clear' => null, 'required' => false]);

        $this->assertTrue($form->has('clear'));
    }

    public function testClearCheckboxIsHiddenWhenNoStoredValue()
    {
        $view = $this->scalarForm(null, ['allow_clear' => true])->createView();

        $this->assertFalse($view->vars['show_clear']);
        $this->assertTrue($view->offsetExists('clear'));
    }

    public function testClearCheckboxIsShownWhenStoredValueExists()
    {
        $view = $this->scalarForm('s3cret', ['allow_clear' => true])->createView();

        $this->assertTrue($view->vars['show_clear']);
        $this->assertTrue($view->offsetExists('clear'));
    }

    public function testAllowClearFalseDisablesTheCheckbox()
    {
        $form = $this->scalarForm('s3cret', ['allow_clear' => false]);

        $this->assertFalse($form->has('clear'));
    }

    public function testHasValueIsExposedInTheView()
    {
        $view = $this->scalarForm('s3cret')->createView();

        $this->assertTrue($view->vars['has_value']);
        $this->assertSame('*****', $view->vars['existing_value_placeholder']);

        $emptyView = $this->scalarForm(null)->createView();
        $this->assertFalse($emptyView->vars['has_value']);
    }

    public function testExistingValuePlaceholderIsExposedWhenEnabled()
    {
        $view = $this->scalarForm('s3cret', [
            'existing_value_placeholder' => 'Leave blank to keep the current value',
        ])->createView();

        $this->assertSame('Leave blank to keep the current value', $view->vars['existing_value_placeholder']);
    }

    public function testExistingValuePlaceholderCanBeDisabled()
    {
        $view = $this->scalarForm('s3cret', [
            'existing_value_placeholder' => null,
        ])->createView();

        $this->assertNull($view->vars['existing_value_placeholder']);
    }

    public function testRequiredNewFormEmptySubmissionIsInvalid()
    {
        $form = $this->scalarForm(null, [
            'constraints' => [new NotBlank()],
        ]);
        $form->submit(['value' => '']);

        $this->assertFalse($form->isValid());
    }

    public function testRequiredEditKeepsExistingValueAndStaysValid()
    {
        $form = $this->modelForm(new class {
            public ?string $secret = 's3cret';
        }, [
            'constraints' => [new NotBlank()],
        ]);
        $form->submit(['secret' => ['value' => '']]);

        $this->assertTrue($form->isValid());
        $this->assertSame('s3cret', $this->getModelSecret($form));
    }

    public function testFailedSubmitKeepsTypedValueInTheView()
    {
        $form = $this->scalarForm('s3cret', [
            'constraints' => [new Length(min: 10)],
        ]);
        $form->submit(['value' => 'tropcourt']);
        $this->assertFalse($form->isValid());

        // The widget shows what was typed, never the stored value. The failed
        // submission is not persisted by the caller: isValid() is false.
        $this->assertSame('tropcourt', $form->createView()['value']->vars['value']);
        $this->assertSame('', $form->createView()->vars['value']);
    }

    public function testDisabledFormKeepsStoredValue()
    {
        $model = new class {
            public ?string $secret = 's3cret';
        };
        $form = $this->modelForm($model, ['disabled' => true]);
        $form->submit(['secret' => ['value' => 'pwn', 'clear' => '1']]);

        $this->assertSame('s3cret', $this->getModelSecret($form));
    }

    public function testTextareaValueTypeIsHonored()
    {
        $form = $this->scalarForm("line1\nline2", ['value_type' => TextareaType::class]);
        $view = $form->createView();

        // Masking holds regardless of the injected widget type.
        $this->assertSame('', $view['value']->vars['value']);
        $form->submit(['value' => '']);
        $this->assertSame("line1\nline2", $form->getData());

        $overwriteForm = $this->scalarForm('old', ['value_type' => TextareaType::class]);
        $overwriteForm->submit(['value' => "a\nb"]);
        $this->assertSame("a\nb", $overwriteForm->getData());
    }

    public function testCustomValueOptionsAreMerged()
    {
        $view = $this->scalarForm(null, [
            'value_type' => TextareaType::class,
            'value_options' => ['attr' => ['placeholder' => 'Paste a token']],
        ])->createView();

        $this->assertSame('Paste a token', $view['value']->vars['attr']['placeholder']);
        // The protective options always win.
        $this->assertSame('off', $view['value']->vars['attr']['autocomplete']);
    }

    private function getModelSecret(FormInterface $form): mixed
    {
        $data = $form->getData();

        return \is_object($data) ? $data->secret : null;
    }
}
