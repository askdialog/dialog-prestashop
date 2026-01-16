<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    Dialog <contact@askdialog.com>
 * @copyright 2007-2025 Dialog
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

declare(strict_types=1);

namespace Dialog\AskDialog\Form;

use PrestaShopBundle\Form\Admin\Type\SwitchType;
use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

class GeneralFormType extends TranslatorAwareType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('api_key_public', TextType::class, [
                'label' => $this->trans('API Key public', 'Modules.Askdialog.Admin'),
                'help' => $this->trans('Public API key provided by AskDialog', 'Modules.Askdialog.Admin'),
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => $this->trans('Public API key is required', 'Modules.Askdialog.Admin'),
                    ]),
                    new Assert\Length([
                        'min' => 10,
                        'max' => 255,
                        'minMessage' => $this->trans('API key must be at least {{ limit }} characters long', 'Modules.Askdialog.Admin'),
                        'maxMessage' => $this->trans('API key cannot be longer than {{ limit }} characters', 'Modules.Askdialog.Admin'),
                    ]),
                ],
                'attr' => [
                    'autocomplete' => 'off',
                ],
            ])
            ->add('api_key', TextType::class, [
                'label' => $this->trans('API Key private', 'Modules.Askdialog.Admin'),
                'help' => $this->trans('Private API key provided by AskDialog', 'Modules.Askdialog.Admin'),
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => $this->trans('Private API key is required', 'Modules.Askdialog.Admin'),
                    ]),
                    new Assert\Length([
                        'min' => 10,
                        'max' => 255,
                        'minMessage' => $this->trans('API key must be at least {{ limit }} characters long', 'Modules.Askdialog.Admin'),
                        'maxMessage' => $this->trans('API key cannot be longer than {{ limit }} characters', 'Modules.Askdialog.Admin'),
                    ]),
                ],
                'attr' => [
                    'autocomplete' => 'off',
                ],
            ])
            ->add('enable_product_hook', SwitchType::class, [
                'label' => $this->trans('Enable on Product Page', 'Modules.Askdialog.Admin'),
                'help' => $this->trans('Enable or disable the AskDialog assistant on the product page', 'Modules.Askdialog.Admin'),
                'required' => false,
            ]);
    }
}
