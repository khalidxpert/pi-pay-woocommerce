/* XpertCreation Pi Network Payments for WooCommerce - Checkout JS */
jQuery(document).ready(function($) {

    var piUser = null;
    var BACKEND = '/api';

    function setStatus(msg, type) {
        var el = document.getElementById('pi-pay-status');
        if (!el) return;
        el.className = 'status ' + type;
        el.innerHTML = msg;
        el.style.display = 'block';
    }

    async function onIncompletePaymentFound(payment) {
        var txid = payment.transaction ? payment.transaction.txid : null;
        if (!txid) { console.log('No txid - skipping'); return; }
        try {
            await fetch(BACKEND + '/payments/approve/', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ paymentId: payment.identifier })
            });
            await fetch(BACKEND + '/payments/complete/', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ paymentId: payment.identifier, txid: txid, amount: payment.amount, platform: 'woocommerce' })
            });
            console.log('Incomplete payment completed!');
        } catch(e) { console.log('Error:', e); }
    }

    try {
        window.Pi.init({ version: '2.0', sandbox: pi_pay_params.sandbox === 'true' });
    } catch(e) { console.log(e); }

    $('#pi-pay-btn').on('click', async function() {
        var btn = document.getElementById('pi-pay-btn');
        btn.innerHTML = '⏳ Authenticating...';
        btn.disabled = true;
        try {
            var auth = await window.Pi.authenticate(['username', 'payments'], onIncompletePaymentFound);
            piUser = auth.user;
            $('#pi_username').val(piUser.username);
            setStatus('✅ Hello @' + piUser.username + '! Click Pay 1 Pi', 'success');
            $(btn).hide();
            $('#pi-pay-ready').show();
        } catch(e) {
            setStatus('❌ ' + e.message, 'error');
            btn.innerHTML = '🥧 Authenticate with Pi';
            btn.disabled = false;
        }
    });

    $('form.checkout').on('checkout_place_order_xpertcreation_pi_pay', function() {
        if (!piUser) { alert('Please authenticate with Pi Network first.'); return false; }

        var orderTotal = parseFloat($('.order-total .amount').last().text().replace(/[^0-9.]/g, ''));
        var rate = parseFloat(pi_pay_params.pi_rate) || 0.127;
        var piAmount = Math.ceil((orderTotal / rate) * 100) / 100;
        $('#pi_amount').val(piAmount);

        window.Pi.createPayment({
            amount: piAmount,
            memo: 'XpertCreation WooCommerce Order',
            metadata: { type: 'woocommerce', store: window.location.hostname }
        }, {
            onReadyForServerApproval: async function(pid) {
                $('#pi_payment_id').val(pid);
                setStatus('⏳ Approving...', 'loading');
                try {
                    await fetch(BACKEND + '/payments/approve/', {
                        method: 'POST', headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({ paymentId: pid, amount: piAmount })
                    });
                } catch(e) {}
            },
            onReadyForServerCompletion: async function(pid, txid) {
                $('#pi_payment_id').val(pid);
                try {
                    await fetch(BACKEND + '/payments/complete/', {
                        method: 'POST', headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({ paymentId: pid, txid: txid, amount: piAmount, platform: 'woocommerce' })
                    });
                } catch(e) {}
                setStatus('🎉 Payment Successful!', 'success');
                $('form.checkout').submit();
            },
            onCancel: function() { setStatus('❌ Payment cancelled', 'error'); },
            onError: function(e, payment) {
                if(payment) onIncompletePaymentFound(payment);
                setStatus('❌ ' + e.message, 'error');
            }
        });
        return false;
    });

    $('#pi-pay-container').append('<button type="button" id="pi-pay-ready" style="display:none;" class="pi-pay-button">💰 Pay with Pi</button>');
    $('#pi-pay-ready').on('click', function() { $('form.checkout').submit(); });
});
