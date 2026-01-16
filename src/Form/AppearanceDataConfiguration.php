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

use Dialog\AskDialog\Repository\AppearanceRepository;
use PrestaShop\PrestaShop\Core\Configuration\DataConfigurationInterface;

/**
 * Configuration for Dialog appearance settings
 * Uses AppearanceRepository to store JSON data in database
 */
final class AppearanceDataConfiguration implements DataConfigurationInterface
{
    /**
     * @var AppearanceRepository
     */
    private $appearanceRepository;

    /**
     * @var int
     */
    private $idShop;

    public function __construct(AppearanceRepository $appearanceRepository, $idShop = null)
    {
        $this->appearanceRepository = $appearanceRepository;
        $this->idShop = $idShop ?: (int) \Context::getContext()->shop->id;
    }

    public function getConfiguration(): array
    {
        return $this->appearanceRepository->getSettings($this->idShop);
    }

    public function updateConfiguration(array $configuration): array
    {
        // Normalize values
        $normalized = $this->normalizeConfiguration($configuration);

        // Validate
        $errors = $this->getValidationErrors($normalized);
        if (!empty($errors)) {
            return $errors;
        }

        // Persist
        $success = $this->appearanceRepository->updateSettings($this->idShop, $normalized);

        if (!$success) {
            return ['Failed to save appearance settings.'];
        }

        return [];
    }

    /**
     * Normalize configuration values
     *
     * @param array $configuration Raw configuration from form
     *
     * @return array Normalized configuration
     */
    private function normalizeConfiguration(array $configuration): array
    {
        $normalized = [];

        // Color fields - trim and uppercase (empty string is preserved)
        $colorFields = ['primary_color', 'background_color', 'cta_text_color'];
        foreach ($colorFields as $field) {
            if (array_key_exists($field, $configuration)) {
                $value = trim((string) $configuration[$field]);
                $normalized[$field] = $value === '' ? '' : strtoupper($value);
            }
        }

        // String fields - trim (empty string is preserved)
        if (array_key_exists('cta_border_type', $configuration)) {
            $normalized['cta_border_type'] = trim((string) $configuration['cta_border_type']);
        }

        if (array_key_exists('font_family', $configuration)) {
            $normalized['font_family'] = trim((string) $configuration['font_family']);
        }

        // Boolean fields
        if (array_key_exists('capitalize_ctas', $configuration)) {
            $normalized['capitalize_ctas'] = (bool) $configuration['capitalize_ctas'];
        }

        if (array_key_exists('highlight_product_name', $configuration)) {
            $normalized['highlight_product_name'] = (bool) $configuration['highlight_product_name'];
        }

        return $normalized;
    }

    /**
     * Validate configuration values
     *
     * @param array $configuration Normalized configuration
     *
     * @return bool True if valid, false otherwise
     */
    public function validateConfiguration(array $configuration): bool
    {
        return empty($this->getValidationErrors($configuration));
    }

    /**
     * Get validation errors for configuration
     *
     * @param array $configuration Normalized configuration
     *
     * @return array Array of error messages (empty if valid)
     */
    private function getValidationErrors(array $configuration): array
    {
        $errors = [];

        // Validate color fields
        $colorFields = ['primary_color', 'background_color', 'cta_text_color'];
        foreach ($colorFields as $field) {
            if (isset($configuration[$field])) {
                $color = $configuration[$field];
                if (!empty($color) && !preg_match('/^#[0-9A-F]{6}$/', $color)) {
                    $errors[] = sprintf('Invalid color format for %s: %s', $field, $color);
                }
            }
        }

        // Validate border type (empty string allowed for theme override)
        if (isset($configuration['cta_border_type'])) {
            $borderType = $configuration['cta_border_type'];
            $validBorderTypes = ['solid', 'dashed', 'dotted', 'double', 'none'];
            if ($borderType !== '' && !in_array($borderType, $validBorderTypes)) {
                $errors[] = 'Invalid border type: ' . $borderType;
            }
        }

        // Validate font family length (empty string allowed for theme override)
        if (isset($configuration['font_family'])) {
            $fontFamily = $configuration['font_family'];
            if ($fontFamily !== '' && strlen($fontFamily) > 255) {
                $errors[] = 'Font family is too long (max 255 characters)';
            }
        }

        return $errors;
    }
}
