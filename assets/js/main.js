(function () {
    'use strict';

    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrfToken = csrfMeta ? csrfMeta.content : '';

    var productMap = {};
    (window.PRODUCTS || []).forEach(function (product) {
        productMap[product.id] = product;
    });

    var modal = document.getElementById('buyModal');
    var modalName = document.getElementById('modalProductName');
    var sizeGrid = document.getElementById('sizeGrid');
    var modalMessage = document.getElementById('modalMessage');
    var cancelBtn = document.getElementById('cancelBtn');
    var checkoutBtn = document.getElementById('checkoutBtn');

    var shipName = document.getElementById('shipName');
    var shipPhone = document.getElementById('shipPhone');
    var shipAddress = document.getElementById('shipAddress');
    var shipCity = document.getElementById('shipCity');
    var shipPostal = document.getElementById('shipPostal');

    var cardFields = document.getElementById('cardFields');
    var cardName = document.getElementById('cardName');
    var cardNumber = document.getElementById('cardNumber');
    var cardExpiry = document.getElementById('cardExpiry');

    var otherEmail = document.getElementById('otherEmail');

    var activeProductId = null;
    var selectedSize = null;

    function currentPaymentMethod() {
        var checked = document.querySelector('input[name="paymentMethod"]:checked');
        return checked ? checked.value : 'cod';
    }

    function currentReceiptTarget() {
        var checked = document.querySelector('input[name="receiptTarget"]:checked');
        return checked ? checked.value : 'account';
    }

    function isValidEmail(value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    }

    function updateCheckoutAvailability() {
        var filled = function (el) { return el && el.value.trim() !== ''; };

        var ok = !!selectedSize
            && filled(shipName) && filled(shipPhone) && filled(shipAddress)
            && filled(shipCity) && filled(shipPostal);

        if (currentPaymentMethod() === 'credit_card') {
            var digits = cardNumber ? cardNumber.value.replace(/\D/g, '') : '';
            ok = ok
                && filled(cardName)
                && digits.length >= 13 && digits.length <= 19
                && /^\d{2}\/\d{2}$/.test(cardExpiry ? cardExpiry.value.trim() : '');
        }

        if (currentReceiptTarget() === 'other') {
            ok = ok && isValidEmail(otherEmail ? otherEmail.value.trim() : '');
        }

        checkoutBtn.disabled = !ok;
    }

    function resetModalFields() {
        [shipName, shipPhone, shipAddress, shipCity, shipPostal,
            cardName, cardNumber, cardExpiry, otherEmail].forEach(function (el) {
            if (el) { el.value = ''; }
        });

        var codRadio = document.querySelector('input[name="paymentMethod"][value="cod"]');
        if (codRadio) { codRadio.checked = true; }
        if (cardFields) { cardFields.hidden = true; }

        var accountRadio = document.querySelector('input[name="receiptTarget"][value="account"]');
        if (accountRadio) { accountRadio.checked = true; }
        if (otherEmail) { otherEmail.hidden = true; }
    }

    document.querySelectorAll('input[name="paymentMethod"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            if (cardFields) { cardFields.hidden = currentPaymentMethod() !== 'credit_card'; }
            updateCheckoutAvailability();
        });
    });

    document.querySelectorAll('input[name="receiptTarget"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            if (otherEmail) { otherEmail.hidden = currentReceiptTarget() !== 'other'; }
            updateCheckoutAvailability();
        });
    });

    [shipName, shipPhone, shipAddress, shipCity, shipPostal,
        cardName, cardNumber, cardExpiry, otherEmail].forEach(function (el) {
        if (el) { el.addEventListener('input', updateCheckoutAvailability); }
    });

    function openModal(productId) {
        var product = productMap[productId];
        if (!product || !modal) {
            return;
        }

        activeProductId = productId;
        selectedSize = null;

        modalName.textContent = product.name;
        modalMessage.textContent = '';
        modalMessage.classList.remove('success');
        resetModalFields();
        checkoutBtn.disabled = true;
        checkoutBtn.textContent = 'Checkout';
        sizeGrid.innerHTML = '';

        Object.keys(product.sizes).forEach(function (size) {
            var quantity = product.sizes[size];
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'size-option';
            button.textContent = size;
            button.dataset.size = size;

            if (quantity <= 0) {
                button.disabled = true;
                button.classList.add('size-out');
            } else {
                button.addEventListener('click', function () {
                    selectSize(size, button);
                });
            }

            sizeGrid.appendChild(button);
        });

        modal.hidden = false;
    }

    function selectSize(size, button) {
        selectedSize = size;
        var options = sizeGrid.querySelectorAll('.size-option');
        for (var i = 0; i < options.length; i++) {
            options[i].classList.remove('selected');
        }
        button.classList.add('selected');
        modalMessage.textContent = '';
        updateCheckoutAvailability();
    }

    function closeModal() {
        if (!modal) {
            return;
        }
        modal.hidden = true;
        activeProductId = null;
        selectedSize = null;
    }

    var buyButtons = document.querySelectorAll('.buy-button[data-product-id]');
    for (var i = 0; i < buyButtons.length; i++) {
        buyButtons[i].addEventListener('click', function () {
            openModal(parseInt(this.dataset.productId, 10));
        });
    }

    if (cancelBtn) {
        cancelBtn.addEventListener('click', closeModal);
    }

    if (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal();
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !modal.hidden) {
                closeModal();
            }
        });
    }

    if (checkoutBtn) {
        checkoutBtn.addEventListener('click', function () {
            if (!activeProductId || !selectedSize) {
                return;
            }

            checkoutBtn.disabled = true;
            checkoutBtn.textContent = 'Processing…';
            modalMessage.textContent = '';
            modalMessage.classList.remove('success');

            var paymentMethod = currentPaymentMethod();
            var receiptTarget = currentReceiptTarget();

            fetch('../checkout.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    product_id: activeProductId,
                    size: selectedSize,
                    csrf_token: csrfToken,

                    shipping_name: shipName.value.trim(),
                    shipping_phone: shipPhone.value.trim(),
                    shipping_address: shipAddress.value.trim(),
                    shipping_city: shipCity.value.trim(),
                    shipping_postal_code: shipPostal.value.trim(),

                    payment_method: paymentMethod,
                    card_name: paymentMethod === 'credit_card' ? cardName.value.trim() : '',
                    card_number: paymentMethod === 'credit_card' ? cardNumber.value.trim() : '',
                    card_expiry: paymentMethod === 'credit_card' ? cardExpiry.value.trim() : '',

                    receipt_target: receiptTarget,
                    receipt_email: receiptTarget === 'other' ? otherEmail.value.trim() : ''
                })
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (data.success) {
                        productMap[activeProductId].sizes[selectedSize] = data.remaining;
                        modalMessage.textContent = data.message;
                        modalMessage.classList.add('success');

                        if (data.remaining <= 0) {
                            var soldOutBtn = sizeGrid.querySelector('.size-option.selected');
                            if (soldOutBtn) {
                                soldOutBtn.disabled = true;
                                soldOutBtn.classList.add('size-out');
                            }
                        }

                        setTimeout(closeModal, 1600);
                    } else {
                        modalMessage.textContent = data.message || 'Something went wrong.';
                        modalMessage.classList.remove('success');
                    }
                })
                .catch(function () {
                    modalMessage.textContent = 'Network error. Please try again.';
                })
                .finally(function () {
                    updateCheckoutAvailability();
                    checkoutBtn.textContent = 'Checkout';
                });
        });
    }

    var toast = document.getElementById('flashToast');
    if (toast) {
        setTimeout(function () {
            toast.classList.add('hide');
        }, 2500);
    }

    // Lightly highlight the nav item for the section currently in view.
    var sectionIds = ['home', 'gallery', 'about', 'contact'];
    var sections = sectionIds
        .map(function (id) { return document.getElementById(id); })
        .filter(Boolean);

    if ('IntersectionObserver' in window && sections.length) {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    var links = document.querySelectorAll('.header-right-section .menu-button');
                    for (var j = 0; j < links.length; j++) {
                        links[j].classList.remove('active-link');
                    }
                    var match = document.querySelector('.menu-button[href="#' + entry.target.id + '"]');
                    if (match) {
                        match.classList.add('active-link');
                    }
                }
            });
        }, { rootMargin: '-40% 0px -55% 0px' });

        sections.forEach(function (section) {
            observer.observe(section);
        });
    }
})();
