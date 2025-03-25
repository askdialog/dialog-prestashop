function askDialogAddToCart(id_product, id_product_attribute, quantity) {
    // Call the add product to cart controller

    $.ajax({
        type: 'POST',
        headers: { "cache-control": "no-cache" },
        url: '/index.php?controller=cart',
        data: {
            'add': 1,
            'ajax': true,
            'qty': quantity,
            'id_product': id_product,
            'token': static_token, // Ensure static_token is defined globally
            'id_product_attribute': id_product_attribute
        },
        success: function (data) {
            // Update cart UI manually
            if (data.hasError) {
                console.log('Error adding product to cart:', data.errors);
            } else {
                console.log('Product added to cart successfully.');
                // Optionally, update cart summary or UI here
               // $('#cart_block').html(data.cart_block); // Example of updating cart block
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.log('Error: ' + textStatus + ' ' + errorThrown);
        }
    });
}

//example of use
//askdialogAddToCart(1, 2, 3, 'token', 1, 'update');

//Function to add the current product to the cart
function askDialogAddCurrentProductToCart() {
    var id_product = $('#product_page_product_id').val();
    var id_product_attribute = $('#idCombination').val();
    var quantity = $('#quantity_wanted').val();
    var action = 'update';
    askDialogAddToCart(id_product, id_product_attribute, quantity);
}

// Expose functions to the global scope for console access
window.askDialogAddToCart = askDialogAddToCart;
window.askDialogAddCurrentProductToCart = askDialogAddCurrentProductToCart;