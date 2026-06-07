(function () {
	var toggle = document.querySelector('.menu-toggle');
	var nav = document.querySelector('.main-nav');
	var translations = {
		'Cart': 'Giỏ hàng',
		'Products in cart': 'Sản phẩm trong giỏ',
		'Cart totals': 'Tổng giỏ hàng',
		'Product': 'Sản phẩm',
		'Details': 'Chi tiết',
		'Total': 'Tổng',
		'Subtotal': 'Tạm tính',
		'Estimated total': 'Tổng thanh toán',
		'Proceed to Checkout': 'Tiến hành thanh toán',
		'View cart': 'Xem giỏ hàng',
		'Add coupons': 'Mã giảm giá',
		'Checkout': 'Thanh toán',
		'Contact information': 'Thông tin liên hệ',
		'Shipping address': 'Địa chỉ giao hàng',
		'Billing address': 'Địa chỉ thanh toán',
		'Payment options': 'Phương thức thanh toán',
		'Order summary': 'Tóm tắt đơn hàng',
		'Place Order': 'Đặt hàng',
		'Place order': 'Đặt hàng'
	};

	function translateWooBlocks(root) {
		var scope = root || document.body;
		var walker = document.createTreeWalker(scope, NodeFilter.SHOW_TEXT);
		var nodes = [];
		var node;

		while ((node = walker.nextNode())) {
			nodes.push(node);
		}

		nodes.forEach(function (textNode) {
			var value = textNode.nodeValue.trim();

			if (translations[value]) {
				textNode.nodeValue = textNode.nodeValue.replace(value, translations[value]);
			}
		});
	}

	function buildVariationSwatches() {
		document.querySelectorAll('form.variations_form table.variations select').forEach(function (select) {
			if (select.dataset.pimouSwatches === 'ready') {
				return;
			}

			var wrapper = document.createElement('div');
			wrapper.className = 'pimou-variation-swatches';
			wrapper.setAttribute('data-for', select.name || select.id || '');

			Array.prototype.slice.call(select.options).forEach(function (option) {
				if (!option.value) {
					return;
				}

				var button = document.createElement('button');
				button.type = 'button';
				button.className = 'pimou-variation-chip';
				button.textContent = option.textContent.trim();
				button.dataset.value = option.value;

				if (option.selected) {
					button.classList.add('is-selected');
				}

				button.addEventListener('click', function () {
					select.value = option.value;
					select.dispatchEvent(new Event('change', { bubbles: true }));
				});

				wrapper.appendChild(button);
			});

			select.insertAdjacentElement('afterend', wrapper);
			select.dataset.pimouSwatches = 'ready';

			select.addEventListener('change', function () {
				wrapper.querySelectorAll('.pimou-variation-chip').forEach(function (button) {
					button.classList.toggle('is-selected', button.dataset.value === select.value);
					button.disabled = Array.prototype.some.call(select.options, function (option) {
						return option.value === button.dataset.value && option.disabled;
					});
				});
			});
		});
	}

	if (toggle && nav) {
		toggle.addEventListener('click', function () {
			var isOpen = nav.classList.toggle('is-open');
			toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
		});
	}

	document.addEventListener('click', function (event) {
		if (!nav || !toggle || !nav.classList.contains('is-open')) {
			return;
		}

		if (!nav.contains(event.target) && !toggle.contains(event.target)) {
			nav.classList.remove('is-open');
			toggle.setAttribute('aria-expanded', 'false');
		}
	});

	translateWooBlocks(document.body);
	buildVariationSwatches();

	var observer = new MutationObserver(function (mutations) {
		mutations.forEach(function (mutation) {
			mutation.addedNodes.forEach(function (node) {
				if (node.nodeType === 1) {
					translateWooBlocks(node);
				}
			});
		});
		buildVariationSwatches();
	});

	observer.observe(document.body, { childList: true, subtree: true });
})();
