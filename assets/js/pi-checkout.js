/* Pi Pay for WooCommerce - Checkout JS */
jQuery(document).ready(function($) {

    var piAuthenticated = false;
    var piUser = null;
    var piPaymentId = null;

    // Initialize Pi SDK
    function initPi() {
        if (typeof Pi === 'undefined') {
            console.error('Pi SDK not loaded');
            return;
        }
        Pi.init({
            version: "2.0",
            sandbox: pi_pay_params.sandbox === 'true'
        });
    }

    // Authenticate with Pi
    $('#pi-pay-btn').on('click', function() {
        var btn = $(this);
        btn.prop('disabled', true).text('Authenticating...');
        setStatus('Connecting to Pi Network...', 'loading');

        Pi.authenticate(['payments'], function(auth) {
            piAuthenticated = true;
            piUser = auth.user;
            $('#pi_username').val(piUser.username);

            setStatus('✅ Authenticated as @' + piUser.username + '. Click "Place Order" to pay.', 'success');
            btn.hide();
            $('#pi-pay-ready').show();
        }, function(err) {
            console.error('Pi auth error:', err);
            setStatus('❌ Authentication failed. Please try again.', 'error');
            btn.prop('disabled', false).html('<img src="' + getLogoUrl() + '" width="20" height="20"> Authenticate with Pi');
        });
    });

    // Handle payment when order is placed
    $('form.checkout').on('checkout_place_order_pi_pay', function() {
        if (!piAuthenticated || !piUser) {
            alert('Please authenticate with Pi Network first.');
            return false;
        }

        var orderTotal = parseFloat($('.order-total .amount').last().text().replace(/[^0-9.]/g, ''));
        var piAmount = getPiAmount(orderTotal);

        $('#pi_amount').val(piAmount);

        // Create Pi payment
        Pi.createPayment({
            amount: piAmount,
            memo: 'WooCommerce Order Payment',
            metadata: {
                orderId: 'pending',
                store: window.location.hostname
            }
        }, {
            onReadyForServerApproval: function(paymentId) {
                piPaymentId = paymentId;
                $('#pi_payment_id').val(paymentId);

                // Approve on server
                $.ajax({
                    url: pi_pay_params.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'pi_pay_approve',
                        payment_id: paymentId,
                        nonce: pi_pay_params.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            setStatus('✅ Payment approved! Completing...', 'success');
                        }
                    }
                });
            },
            onReadyForServerCompletion: function(paymentId, txid) {
                setStatus('✅ Payment complete! Finalizing order...', 'success');
                $('#pi_payment_id').val(paymentId);
                $('form.checkout').submit();
            },
            onCancel: function(paymentId) {
                setStatus('❌ Payment cancelled.', 'error');
            },
            onError: function(error, payment) {
                console.error('Pi payment error:', error);
                setStatus('❌ Payment error: ' + error.message, 'error');
            }
        });

        return false; // Prevent normal form submit
    });

    function getPiAmount(usdAmount) {
        // Use live rate or fixed rate
        var rate = parseFloat(pi_pay_params.pi_rate) || 0.127;
        return Math.ceil((usdAmount / rate) * 100) / 100;
    }

    function setStatus(message, type) {
        var colors = {
            loading: '#f7a600',
            success: '#22c55e',
            error: '#ef4444'
        };
        $('#pi-pay-status').html('<p style="color:' + (colors[type] || '#fff') + '; margin:8px 0;">' + message + '</p>');
    }

    function getLogoUrl() {
        return pi_pay_params.plugin_url + 'assets/images/pi-logo.png';
    }

    // Init on load
    initPi();

    // Add ready button (hidden initially)
    $('#pi-pay-container').append('<button type="button" id="pi-pay-ready" style="display:none;" class="pi-pay-button pi-pay-ready-btn">💰 Pay with Pi</button>');

    $('#pi-pay-ready').on('click', function() {
        $('form.checkout').submit();
    });
});
