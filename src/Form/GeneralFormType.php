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
