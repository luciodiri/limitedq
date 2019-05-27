/* global wc_add_to_cart_params */
jQuery( function( $ ) {

    if ( typeof wc_add_to_cart_params === 'undefined' ) {
        return false;
    }

    /**
     * LqAddToCartHandler class.
     */
    var LqAddToCartHandler = function() {
        $( document.body )
            .on( 'click', '.lq_add_to_cart_button', this.onAddToCart )
            .on( 'click', '.lq_remove_from_cart_button', this.onRemoveFromCart )
            .on( 'lq_added_to_cart', this.updateAddButton )
            .on( 'lq_added_to_cart lq_removed_from_cart', this.updateCartPage ) // consistency with wc
            .on( 'lq_added_to_cart lq_removed_from_cart', this.updateFragments ) // consistency with wc
            .on('wc_cart_button_updated', this.replaceButton); // feature cancelled
    };

    /**
     * Handle the add to cart event:
     * add item process
     * after event, update button:
     *  if it's first item - grab new cart item id and quantity and add the attributes to both buttons
     *  else - update only quantity
     */
    LqAddToCartHandler.prototype.onAddToCart = function( e ) {

        var $thisbutton = $( this );

        if ( $thisbutton.is( '.lq_ajax_add_to_cart' ) ) {
            if ( ! $thisbutton.attr( 'data-product_id' ) ) {
                return true;
            }

            e.preventDefault();

            // if maximum quantity is reached disable button with message and abort
            var max_quantity_reached = flag_max_quantity_limit_reached( $thisbutton );
            if( max_quantity_reached ) {
                return true;
            }

            $thisbutton.removeClass( 'added' );
            $thisbutton.addClass( 'loading' );

            var data = {};

            $.each( $thisbutton.data(), function( key, value ) {
                data[ key ] = value;
            });

            // Trigger event.
            $( document.body ).trigger( 'adding_to_cart', [ $thisbutton, data ] );

            // Ajax action. update cart
            $.post( wc_add_to_cart_params.wc_ajax_url.toString().replace( '%%endpoint%%', 'add_to_cart' ), data, function( response ) {
                if ( ! response ) {
                    return;
                }
                if ( response.error && response.product_url ) {
                    window.location = response.product_url;
                    return;
                }
                // Redirect to cart option
                if ( wc_add_to_cart_params.cart_redirect_after_add === 'yes' ) {
                    window.location = wc_add_to_cart_params.cart_url;
                    return;
                }
                // Trigger event so themes can refresh other areas.
                $( document.body ).trigger( 'lq_added_to_cart', [ response.fragments, response.cart_hash, $thisbutton ] );
            });
        }
    };

    /**
     * Handle remove from cart event. this event does not exist in WC default
     */
    LqAddToCartHandler.prototype.onRemoveFromCart = function( e ) {

        var $thisbutton = $( this );

        e.preventDefault();

        // check if quantity > 0 to proceed
        var quantity = parseInt($thisbutton.parent().find('.lq-curr-quantity').text());
        if ( 0 == quantity ) return;

        $thisbutton.removeClass( 'added' );
        $thisbutton.addClass( 'loading' );

        // setup data for request
        var data = {
            action: 'limitedq_addtocart',
            _ajax_nonce: lq_ajax_obj.nonce,
            option: 'remove_item'
        };
        $.each( $thisbutton.data(), function( key, value ) {
            data[ key ] = value;
        });

        // TODO: The custom Ajax function should be generalized for re-use across class
        $.post(lq_ajax_obj.lq_ajax_url, data, function( response ){
            if ( ! response ) {
                return;
            }
            if ( response.error ) {
                return;
            }
            // update item new quantity
            $thisbutton.parent().find('.lq-curr-quantity').text(response);
            // if quantity is 0 remove cart item attributes
            if( 0 == response ) {
                $thisbutton.attr('data-cart_item_key', '');
                $thisbutton.siblings('.button').attr('data-cart_item_key', '');

            }

            $thisbutton.removeClass( 'loading' );
            $thisbutton.addClass( 'added' );

            // if [+] button is disabled, activate it
            $thisbutton.siblings('.button').removeClass('lq-disabled');
            $thisbutton.parent().find('div.limitedq-full-msg').remove();

            $( document.body ).trigger( 'wc_cart_button_updated', [ $thisbutton ] );

            // TODO: synch quantity removal with mini cart (no wc hook for updating quantity, only full item removal)

        });
    };

    LqAddToCartHandler.prototype.replaceButton = function ($button) {
        // feature cancelled
    }

    /**
     * Update button after add to cart event.
     * update quantity.
     * if first item, add new cart attributes
     */
    LqAddToCartHandler.prototype.updateAddButton = function( e, fragments, cart_hash, $button ) {
        $button = typeof $button === 'undefined' ? false : $button;

        if ( $button ) {

            // if original quantity = 0 pull new cart item attributes
            quantity_el = $button.parent().find('.lq-curr-quantity');
            if( 0 == quantity_el.text() ) {

                // setup data for request (as noted, AJAX function should be extracted and reused)
                var data = {
                    action: 'limitedq_addtocart',
                    _ajax_nonce: lq_ajax_obj.nonce,
                    option: 'add_new_item'
                };
                $.each( $button.data(), function( key, value ) {
                    data[ key ] = value;
                });
                $.post(lq_ajax_obj.lq_ajax_url, data, function( response ){
                    if ( ! response ) {
                        return;
                    }
                    if ( response.error ) {
                        return;
                    }
                    // add new attributes to buttons
                    response = JSON.parse(response);
                    $button.attr('data-cart_item_key', response['item_key']);
                    $button.siblings('.button').attr('data-cart_item_key', response['quantity']);

                    // update item new quantity
                    $button.parent().find('.lq-curr-quantity').text(response['quantity']);

                });
            }
            else {
                // else Update quantity counter. (yes, this is a not reliable/robust shortcut, for saving ajax call)
                new_q = parseInt(quantity_el.text()) + 1;
                quantity_el.text(new_q);
            }

            $button.removeClass( 'loading' );
            $button.addClass( 'added' );

            $( document.body ).trigger( 'wc_cart_button_updated', [ $button ] );
        }
    };

    /**
     * Update cart page elements after add to cart events.
     * wc compatibility
     * MINI CART is updated only on adding. update on removal is out of scope
     */
    LqAddToCartHandler.prototype.updateCartPage = function() {
        var page = window.location.toString().replace( 'add-to-cart', 'added-to-cart' );

        $.get( page, function( data ) {
            $( '.shop_table.cart:eq(0)' ).replaceWith( $( data ).find( '.shop_table.cart:eq(0)' ) );
            $( '.cart_totals:eq(0)' ).replaceWith( $( data ).find( '.cart_totals:eq(0)' ) );
            $( '.cart_totals, .shop_table.cart' ).stop( true ).css( 'opacity', '1' ).unblock();
            $( document.body ).trigger( 'cart_page_refreshed' );
            $( document.body ).trigger( 'cart_totals_refreshed' );
        } );
    };

    /**
     * Update fragments after add to cart events.
     * wc compatibility
     */
    LqAddToCartHandler.prototype.updateFragments = function( e, fragments ) {
        if ( fragments ) {
            $.each( fragments, function( key ) {
                $( key )
                    .addClass( 'updating' )
                    .fadeTo( '400', '0.6' )
                    .block({
                        message: null,
                        overlayCSS: {
                            opacity: 0.6
                        }
                    });
            });

            $.each( fragments, function( key, value ) {
                $( key ).replaceWith( value );
                $( key ).stop( true ).css( 'opacity', '1' ).unblock();
            });

            $( document.body ).trigger( 'wc_fragments_loaded' );
        }
    };

    /**
     * Init LqAddToCartHandler.
     */
    new LqAddToCartHandler();
});

// helpers
flag_max_quantity_limit_reached = function( $thisbutton ) {
    if( $thisbutton.hasClass('lq-disabled') )  return true;
    var original_quantity = parseInt($thisbutton.parent().find('.lq-curr-quantity').text());
    var max = $thisbutton.data('max_items_allowed');
    if( original_quantity >= max ) { // disable and abort
        $thisbutton.addClass('lq-disabled');
        $thisbutton.parent().append('<div class="limitedq-full-msg">Max items per order reached</div>');
        return true;
    }

    return false; // limit not reached
}
