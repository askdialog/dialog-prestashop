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

namespace Dialog\AskDialog\Repository;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Repository for Dialog appearance settings
 * Manages JSON-based appearance configuration in database
 */
class AppearanceRepository extends AbstractRepository
{
    /**
     * Default appearance settings
     */
    private const DEFAULT_SETTINGS = [
        'primary_color' => '#CCCCCC',
        'background_color' => '#FFFFFF',
        'cta_text_color' => '#000000',
        'cta_border_type' => 'solid',
        'capitalize_ctas' => false,
        'font_family' => 'Arial, sans-serif',
        'highlight_product_name' => false,
    ];

    /**
     * Get appearance settings for a specific shop
     *
     * @param int $idShop Shop ID
     *
     * @return array Appearance settings (returns defaults if not found)
     */
    public function getSettings($idShop)
    {
        $sql = 'SELECT settings
                FROM `' . $this->getPrefix() . 'askdialog_appearance`
                WHERE id_shop = ' . (int) $idShop;

        $result = $this->getDb()->getValue($sql);

        if (!$result) {
            return self::DEFAULT_SETTINGS;
        }

        $settings = json_decode($result, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return self::DEFAULT_SETTINGS;
        }

        // Merge with defaults to ensure all keys exist
        // But preserve empty strings (they indicate user wants no value)
        $merged = self::DEFAULT_SETTINGS;
        foreach ($settings as $key => $value) {
            $merged[$key] = $value; // This preserves empty strings
        }

        return $merged;
    }

    /**
     * Update appearance settings for a specific shop
     *
     * @param int $idShop Shop ID
     * @param array $settings Appearance settings (empty string = allow theme override)
     *
     * @return bool True on success, false on failure
     */
    public function updateSettings($idShop, array $settings)
    {
        // Get existing settings
        $existingSettings = $this->getSettings($idShop);

        // Normalize empty values: null → empty string (for Smarty compatibility)
        $normalizedSettings = array_map(function ($value) {
            return ($value === null) ? '' : $value;
        }, $settings);

        // Merge with existing settings
        $mergedSettings = array_merge($existingSettings, $normalizedSettings);

        // Encode to JSON
        $jsonSettings = json_encode($mergedSettings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        // Check if row exists
        $exists = $this->getDb()->getValue(
            'SELECT id_appearance
             FROM `' . $this->getPrefix() . 'askdialog_appearance`
             WHERE id_shop = ' . (int) $idShop
        );

        if ($exists) {
            // Update existing row
            return $this->getDb()->update(
                'askdialog_appearance',
                [
                    'settings' => pSQL($jsonSettings, true),
                    'date_upd' => date('Y-m-d H:i:s'),
                ],
                'id_shop = ' . (int) $idShop
            );
        } else {
            // Insert new row
            return $this->getDb()->insert(
                'askdialog_appearance',
                [
                    'id_shop' => (int) $idShop,
                    'settings' => pSQL($jsonSettings, true),
                    'date_add' => date('Y-m-d H:i:s'),
                    'date_upd' => date('Y-m-d H:i:s'),
                ]
            );
        }
    }

    /**
     * Get a specific setting value for a shop
     *
     * @param int $idShop Shop ID
     * @param string $key Setting key
     * @param mixed $default Default value if key not found
     *
     * @return mixed Setting value or default
     */
    public function getSetting($idShop, $key, $default = null)
    {
        $settings = $this->getSettings($idShop);

        return isset($settings[$key]) ? $settings[$key] : $default;
    }

    /**
     * Update a specific setting value for a shop
     *
     * @param int $idShop Shop ID
     * @param string $key Setting key
     * @param mixed $value Setting value
     *
     * @return bool True on success, false on failure
     */
    public function updateSetting($idShop, $key, $value)
    {
        return $this->updateSettings($idShop, [$key => $value]);
    }

    /**
     * Delete appearance settings for a specific shop
     *
     * @param int $idShop Shop ID
     *
     * @return bool True on success, false on failure
     */
    public function deleteSettings($idShop)
    {
        return $this->getDb()->delete(
            'askdialog_appearance',
            'id_shop = ' . (int) $idShop
        );
    }

    /**
     * Get default appearance settings
     *
     * @return array Default settings
     */
    public static function getDefaultSettings()
    {
        return self::DEFAULT_SETTINGS;
    }

    /**
     * Check if a setting key is valid
     *
     * @param string $key Setting key
     *
     * @return bool True if valid
     */
    public static function isValidSettingKey($key)
    {
        return array_key_exists($key, self::DEFAULT_SETTINGS);
    }

    /**
     * Get all valid setting keys
     *
     * @return array Array of valid setting keys
     */
    public static function getValidSettingKeys()
    {
        return array_keys(self::DEFAULT_SETTINGS);
    }
}
