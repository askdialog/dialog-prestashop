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

namespace Dialog\AskDialog\Controller;

if (!defined('_PS_VERSION_')) {
    exit;
}

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
