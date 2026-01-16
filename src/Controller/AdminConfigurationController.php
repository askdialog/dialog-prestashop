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

namespace Dialog\AskDialog\Controller;

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminConfigurationController extends FrameworkBundleAdminController
{
    public function index(Request $request): Response
    {
        $generalFormDataHandler = $this->get('dialog.askdialog.form.general_form_data_handler');
        $appearanceFormDataHandler = $this->get('dialog.askdialog.form.appearance_form_data_handler');

        $generalForm = $generalFormDataHandler->getForm();
        $appearanceForm = $appearanceFormDataHandler->getForm();

        $generalForm->handleRequest($request);
        $appearanceForm->handleRequest($request);

        // Get active tab from query parameter (default: configuration)
        $activeTab = $request->query->get('tab', 'configuration');

        if ($generalForm->isSubmitted() && $generalForm->isValid()) {
            $errors = $generalFormDataHandler->save($generalForm->getData());

            if (empty($errors)) {
                $this->addFlash('success', $this->trans('Successful update.', 'Admin.Notifications.Success'));

                return $this->redirectToRoute('askdialog_form_configuration', ['tab' => 'configuration']);
            }

            $this->flashErrors($errors);
        }

        if ($appearanceForm->isSubmitted() && $appearanceForm->isValid()) {
            $errors = $appearanceFormDataHandler->save($appearanceForm->getData());

            if (empty($errors)) {
                $this->addFlash('success', $this->trans('Successful update.', 'Admin.Notifications.Success'));

                return $this->redirectToRoute('askdialog_form_configuration', ['tab' => 'appearance']);
            }

            $this->flashErrors($errors);
        }

        return $this->render('@Modules/askdialog/views/templates/admin/form.html.twig', [
            'generalForm' => $generalForm->createView(),
            'appearanceForm' => $appearanceForm->createView(),
            'activeTab' => $activeTab,
        ]);
    }
}
