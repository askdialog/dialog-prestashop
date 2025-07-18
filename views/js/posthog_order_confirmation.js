// posthog_order_confirmation.js

// Ensure PostHog is loaded
if (typeof posthog !== 'undefined') {
    document.addEventListener('DOMContentLoaded', function () {
        // Replace with actual order details
        const urlParams = new URLSearchParams(window.location.search);
        const orderID = urlParams.get('id_order') || 'unknown'; // Get id_order from URL parameters
        const orderDetails = {
            orderId: orderID, // Use id_order from URL parameters
            totalAmount: orderTotal || 0, // Replace with dynamic total amount
            currency: prestashop.currency.iso_code || 'USD', // Replace with dynamic currency
            customerEmail: prestashop.customer.email || 'unknown', // Replace with dynamic customer email
        };

        console.log('Order details:', orderDetails);

        // Send order confirmation event to PostHog
        posthog.capture('Order Confirmation', {
            order_id: orderDetails.orderId,
            total_amount: orderDetails.totalAmount,
            currency: orderDetails.currency,
            customer_email: orderDetails.customerEmail,
        });

        console.log('Order confirmation event sent to PostHog:', orderDetails);
    });
} else {
    console.error('PostHog is not loaded.');
}