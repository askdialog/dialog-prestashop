import $ from 'jquery';
import prestashop from 'prestashop';


function askDialogAddToCart(id_product, id_product_attribute, quantity) {
    // Call the add product to cart controller

    $.ajax({
        type: 'POST',
        headers: { "cache-control": "no-cache" },
        url: prestashop.urls.pages.cart,
        data: {
            'controller': 'cart',
            'add': 1,
            'ajax': true,
            'qty': quantity,
            'id_product': id_product,
            'token': prestashop.static_token,
            'id_product_attribute': id_product_attribute
        },
        success: function (data) {
            // Update cart
            prestashop.emit('updateCart', {
                reason: {
                    idProduct: id_product,
                    idProductAttribute: id_product_attribute,
                    quantity: quantity,
                    idCustomization: 0,
                    token: prestashop.static_token,
                    action: 'add-to-cart'

                },
                resp: data
            });
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
window.askDialogAddToCart = askDialogAddCurrentProductToCart;