(function () {
	'use strict';

	if (typeof aeroPayCheckout === 'undefined') {
		return;
	}

	var messageEl = document.getElementById('aero-pay-message');
	var boxEl = document.getElementById('aero-pay-box');
	var stopped = false;

	function poll() {
		if (stopped) {
			return;
		}

		fetch(aeroPayCheckout.statusUrl, { credentials: 'omit' })
			.then(function (response) {
				return response.json();
			})
			.then(function (body) {
				if (body.status === 'paid') {
					stopped = true;
					if (messageEl) {
						messageEl.textContent = aeroPayCheckout.paidMessage;
					}
					if (boxEl) {
						boxEl.classList.add('aero-pay-box--paid');
					}
					window.location.href = aeroPayCheckout.redirectUrl;

					return;
				}

				if (body.status === 'cancelled' || body.status === 'expired') {
					stopped = true;
					if (messageEl) {
						messageEl.textContent = aeroPayCheckout.expiredMessage;
					}
					if (boxEl) {
						boxEl.classList.add('aero-pay-box--expired');
					}

					return;
				}

				window.setTimeout(poll, aeroPayCheckout.pollMs);
			})
			.catch(function () {
				window.setTimeout(poll, aeroPayCheckout.pollMs);
			});
	}

	window.setTimeout(poll, aeroPayCheckout.pollMs);
})();
