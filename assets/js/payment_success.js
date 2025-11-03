// Payment success page auto-refresh for processing status

document.addEventListener('DOMContentLoaded', function() {
    // Auto-refresh if payment status is processing
    const paymentAutoRefresh = document.querySelector('.payment-auto-refresh');
    if (paymentAutoRefresh) {
        setTimeout(function() {
            window.location.reload();
        }, 3000);
    }
});


