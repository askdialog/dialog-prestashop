<?php
/**
 * 2026 Dialog
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    Axel Paillaud <contact@axelweb.fr>
 * @copyright 2026 Dialog
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

declare(strict_types=1);

namespace Dialog\AskDialog\Form;

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShopBundle\Form\Admin\Type\SwitchType;
use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

class AppearanceFormType extends TranslatorAwareType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('primary_color', TextType::class, [
                'label' => $this->trans('Primary Color', 'Modules.Askdialog.Admin'),
                'help' => $this->trans('Main color for buttons and accents (hex format: #RRGGBB)', 'Modules.Askdialog.Admin'),
                'required' => false,
                'constraints' => [
                    new Assert\Regex([
                        'pattern' => '/^#[0-9A-Fa-f]{6}$/',
                        'message' => $this->trans('Please enter a valid hex color (e.g., #CCCCCC)', 'Modules.Askdialog.Admin'),
                    ]),
                ],
                'attr' => [
                    'class' => 'color-picker',
                    'placeholder' => '#CCCCCC',
                    'maxlength' => 7,
                ],
            ])
            ->add('background_color', TextType::class, [
                'label' => $this->trans('Background Color', 'Modules.Askdialog.Admin'),
                'help' => $this->trans('Background color for the dialog widget (hex format: #RRGGBB)', 'Modules.Askdialog.Admin'),
                'required' => false,
                'constraints' => [
                    new Assert\Regex([
                        'pattern' => '/^#[0-9A-Fa-f]{6}$/',
                        'message' => $this->trans('Please enter a valid hex color (e.g., #FFFFFF)', 'Modules.Askdialog.Admin'),
                    ]),
                ],
                'attr' => [
                    'class' => 'color-picker',
                    'placeholder' => '#FFFFFF',
                    'maxlength' => 7,
                ],
            ])
            ->add('cta_text_color', TextType::class, [
                'label' => $this->trans('CTA Text Color', 'Modules.Askdialog.Admin'),
                'help' => $this->trans('Text color for call-to-action buttons (hex format: #RRGGBB)', 'Modules.Askdialog.Admin'),
                'required' => false,
                'constraints' => [
                    new Assert\Regex([
                        'pattern' => '/^#[0-9A-Fa-f]{6}$/',
                        'message' => $this->trans('Please enter a valid hex color (e.g., #000000)', 'Modules.Askdialog.Admin'),
                    ]),
                ],
                'attr' => [
                    'class' => 'color-picker',
                    'placeholder' => '#000000',
                    'maxlength' => 7,
                ],
            ])
            ->add('cta_border_type', ChoiceType::class, [
                'label' => $this->trans('CTA Border Type', 'Modules.Askdialog.Admin'),
                'help' => $this->trans('Border style for call-to-action buttons', 'Modules.Askdialog.Admin'),
                'required' => false,
                'choices' => [
                    $this->trans('Solid', 'Modules.Askdialog.Admin') => 'solid',
                    $this->trans('Dashed', 'Modules.Askdialog.Admin') => 'dashed',
                    $this->trans('Dotted', 'Modules.Askdialog.Admin') => 'dotted',
                    $this->trans('Double', 'Modules.Askdialog.Admin') => 'double',
                    $this->trans('None', 'Modules.Askdialog.Admin') => 'none',
                ],
            ])
            ->add('capitalize_ctas', SwitchType::class, [
                'label' => $this->trans('Capitalize CTAs', 'Modules.Askdialog.Admin'),
                'help' => $this->trans('Automatically capitalize call-to-action button text', 'Modules.Askdialog.Admin'),
                'required' => false,
            ])
            ->add('font_family', TextType::class, [
                'label' => $this->trans('Font Family', 'Modules.Askdialog.Admin'),
                'help' => $this->trans('CSS font family for the dialog widget (e.g., Arial, sans-serif)', 'Modules.Askdialog.Admin'),
                'required' => false,
                'constraints' => [
                    new Assert\Length([
                        'max' => 255,
                        'maxMessage' => $this->trans('Font family cannot be longer than {{ limit }} characters', 'Modules.Askdialog.Admin'),
                    ]),
                ],
                'attr' => [
                    'placeholder' => 'Arial, sans-serif',
                ],
            ])
            ->add('highlight_product_name', SwitchType::class, [
                'label' => $this->trans('Highlight Product Name', 'Modules.Askdialog.Admin'),
                'help' => $this->trans('Highlight the product name in the dialog responses', 'Modules.Askdialog.Admin'),
                'required' => false,
            ]);
    }
}
