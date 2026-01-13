/**
 * PostHog Cookie Override
 *
 * Forces PostHog to use cookies instead of localStorage for persistence.
 * This allows the PHP backend to read the distinct_id and sync frontend/backend analytics.
 *
 * This file MUST be loaded AFTER PostHog initialization (after instant.js or index.js).
 */

(function() {
    'use strict';

    var maxRetries = 50; // Max 5 seconds (50 * 100ms)
    var currentRetry = 0;

    /**
     * Attempts to configure PostHog to use cookie persistence
     */
    function forcePostHogCookiePersistence() {
        // Check if PostHog is available on window
        if (typeof window.posthog !== 'undefined' && window.posthog) {
            try {
                // Force PostHog to use cookies for persistence
                window.posthog.set_config({
                    persistence: 'cookie'
                });

                console.log('[PostHog Override] Successfully configured to use cookie persistence');
            } catch (error) {
                console.error('[PostHog Override] Failed to configure PostHog:', error);
            }
        } else {
            currentRetry++;

            if (currentRetry >= maxRetries) {
                console.error('[PostHog Override] PostHog not found after ' + maxRetries + ' retries. Giving up.');
                console.error('[PostHog Override] Check if instant.js or index.js loaded correctly.');
                return;
            }

            // PostHog not yet loaded, retry after a short delay
            console.warn('[PostHog Override] PostHog not found, retrying in 100ms... (attempt ' + currentRetry + '/' + maxRetries + ')');
            setTimeout(forcePostHogCookiePersistence, 100);
        }
    }

    document.addEventListener('DOMContentLoaded', forcePostHogCookiePersistence);
})();
