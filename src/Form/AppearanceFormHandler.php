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

use PrestaShop\PrestaShop\Core\Form\FormDataProviderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

/**
 * Form handler for appearance settings (compatible with PrestaShop 1.7.7+)
 */
class AppearanceFormHandler
{
    /**
     * @var FormFactoryInterface
     */
    private $formFactory;

    /**
     * @var FormDataProviderInterface
     */
    private $formDataProvider;

    /**
     * @var string
     */
    private $formType;

    public function __construct(
        FormFactoryInterface $formFactory,
        FormDataProviderInterface $formDataProvider,
        string $formType
    ) {
        $this->formFactory = $formFactory;
        $this->formDataProvider = $formDataProvider;
        $this->formType = $formType;
    }

    /**
     * @return FormInterface
     */
    public function getForm(): FormInterface
    {
        $formBuilder = $this->formFactory->createBuilder($this->formType);
        $formBuilder->setData($this->formDataProvider->getData());

        return $formBuilder->getForm();
    }

    /**
     * @param array $data
     *
     * @return array<string> List of error messages (empty if success)
     */
    public function save(array $data): array
    {
        return $this->formDataProvider->setData($data);
    }
}
