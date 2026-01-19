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

use Dialog\AskDialog\Service\AskDialogClient;
use PrestaShop\PrestaShop\Core\Configuration\DataConfigurationInterface;
use PrestaShop\PrestaShop\Core\ConfigurationInterface;

/**
 * Configuration is used to save data to configuration table and retrieve from it.
 */
final class GeneralDataConfiguration implements DataConfigurationInterface
{
    public const ASKDIALOG_API_KEY_PUBLIC = 'ASKDIALOG_API_KEY_PUBLIC';
    public const ASKDIALOG_API_KEY = 'ASKDIALOG_API_KEY';
    public const ASKDIALOG_ENABLE_PRODUCT_HOOK = 'ASKDIALOG_ENABLE_PRODUCT_HOOK';

    private const API_KEY_MIN_LENGTH = 10;
    private const API_KEY_MAX_LENGTH = 255;

    /**
     * @var ConfigurationInterface
     */
    private $configuration;

    public function __construct(ConfigurationInterface $configuration)
    {
        $this->configuration = $configuration;
    }

    public function getConfiguration(): array
    {
        return [
            'api_key_public' => (string) $this->configuration->get(static::ASKDIALOG_API_KEY_PUBLIC),
            'api_key' => (string) $this->configuration->get(static::ASKDIALOG_API_KEY),
            'enable_product_hook' => (bool) $this->configuration->get(static::ASKDIALOG_ENABLE_PRODUCT_HOOK),
        ];
    }

    public function updateConfiguration(array $configuration): array
    {
        // Normalize
        $normalized = [
            'api_key_public' => isset($configuration['api_key_public']) ? trim((string) $configuration['api_key_public']) : '',
            'api_key' => isset($configuration['api_key']) ? trim((string) $configuration['api_key']) : '',
            'enable_product_hook' => isset($configuration['enable_product_hook']) ? (bool) $configuration['enable_product_hook'] : false,
        ];

        // Validate
        if (!$this->validateConfiguration($normalized)) {
            return ['Invalid configuration values.'];
        }

        // Persist
        $this->configuration->set(static::ASKDIALOG_API_KEY_PUBLIC, $normalized['api_key_public']);
        $this->configuration->set(static::ASKDIALOG_API_KEY, $normalized['api_key']);
        $this->configuration->set(static::ASKDIALOG_ENABLE_PRODUCT_HOOK, $normalized['enable_product_hook']);

        // Register domain with Dialog API after saving API keys
        try {
            $apiClient = new AskDialogClient();
            $result = $apiClient->sendDomainHost();

            if ($result['statusCode'] !== 200) {
                return ['Domain registration failed: ' . $result['body']];
            }
        } catch (\Exception $e) {
            return ['Failed to connect to Dialog API: ' . $e->getMessage()];
        }

        return [];
    }

    /**
     * Ensure the parameters passed are valid.
     *
     * @return bool Returns true if no exception are thrown
     */
    public function validateConfiguration(array $configuration): bool
    {
        // Check required keys
        if (!isset($configuration['api_key_public'], $configuration['api_key'], $configuration['enable_product_hook'])) {
            return false;
        }

        // Business rules validation
        $apiKeyPublic = trim((string) $configuration['api_key_public']);
        $apiKey = trim((string) $configuration['api_key']);

        // Validate public API key
        if ($apiKeyPublic === '' || \strlen($apiKeyPublic) < self::API_KEY_MIN_LENGTH || \strlen($apiKeyPublic) > self::API_KEY_MAX_LENGTH) {
            return false;
        }

        // Validate private API key
        if ($apiKey === '' || \strlen($apiKey) < self::API_KEY_MIN_LENGTH || \strlen($apiKey) > self::API_KEY_MAX_LENGTH) {
            return false;
        }

        return true;
    }
}
