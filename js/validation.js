(function() {
    'use strict';

    function formatPhone(raw) {
        let digits = raw.replace(/\D/g, '');
        if (digits.length === 0) return '';
        if (digits[0] === '8') digits = '7' + digits.slice(1);
        if (digits[0] !== '7') digits = '7' + digits;
        digits = digits.slice(0, 11);

        let result = '+7';
        if (digits.length > 1) result += ' (' + digits.slice(1, 4);
        if (digits.length >= 4) result += ')';
        if (digits.length > 4) result += ' ' + digits.slice(4, 7);
        if (digits.length > 7) result += '-' + digits.slice(7, 9);
        if (digits.length > 9) result += '-' + digits.slice(9, 11);
        return result;
    }

    function applyPhoneMask(input) {
        if (input._phoneMaskApplied) return;
        input._phoneMaskApplied = true;

        input.addEventListener('input', function() {
            const pos = input.selectionStart;
            const oldLength = input.value.length;
            input.value = formatPhone(input.value);
            const diff = input.value.length - oldLength;
            input.setSelectionRange(pos + diff, pos + diff);
            clearFieldError(input);
        });

        input.addEventListener('focus', function() {
            if (input.value === '') input.value = '+7 (';
        });

        input.addEventListener('blur', function() {
            if (input.value === '+7 (' || input.value === '+7') input.value = '';
        });

        input.addEventListener('keydown', function(e) {
            const navigationKeys = ['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab', 'Home', 'End'];
            if (navigationKeys.includes(e.key)) return;
            if (e.ctrlKey || e.metaKey) return;
            if (!/^\d$/.test(e.key)) e.preventDefault();
        });

        input.addEventListener('paste', function(e) {
            e.preventDefault();
            const text = (e.clipboardData || window.clipboardData).getData('text');
            input.value = formatPhone(text);
        });
    }

    function validateFio(value) {
        const trimmed = value.trim();
        if (!trimmed) return 'Введите ваше имя';
        if (!/^[А-ЯЁа-яёA-Za-z\-\s]+$/.test(trimmed)) {
            return 'Только буквы, пробел и дефис';
        }
        if (trimmed.replace(/\s/g, '').length < 2) return 'Слишком короткое имя';
        return null;
    }

    function validatePhone(value) {
        if (!value || value.trim() === '' || value === '+7 (') {
            return 'Введите номер телефона';
        }
        const digits = value.replace(/\D/g, '');
        if (digits.length < 11) return 'Введите полный номер — 11 цифр';
        if (digits[0] !== '7') return 'Номер должен начинаться с +7';
        return null;
    }

    function showFieldError(input, message) {
        clearFieldError(input);
        input.style.borderColor = '#d32f2f';
        input.style.borderWidth = '2px';
        input.style.background = '#fff8f8';

        const errorElement = document.createElement('div');
        errorElement.className = 'v-error-msg';
        errorElement.style.cssText =
            'color:#d32f2f;font-size:12px;margin-top:5px;font-weight:600;display:flex;align-items:center;gap:5px;';
        errorElement.innerHTML = '<i class="fas fa-exclamation-circle"></i>' + message;
        if (input.parentNode) input.parentNode.appendChild(errorElement);
    }

    function clearFieldError(input) {
        input.style.borderColor = '';
        input.style.borderWidth = '';
        input.style.background = '';
        if (input.parentNode) {
            const oldError = input.parentNode.querySelector('.v-error-msg');
            if (oldError) oldError.remove();
        }
    }

    function markSuccess(input) {
        clearFieldError(input);
        input.style.borderColor = '#27ae60';
        input.style.borderWidth = '2px';
    }

    const depth = window.location.pathname.split('/').filter(Boolean).length;
    const prefix = depth > 1 ? '../'.repeat(depth - 1) : '';

    const CONSENT_HTML =
        'Нажимая кнопку, я даю согласие на ' +
        '<a href="' + prefix + 'privacy-policy.html" target="_blank" ' +
        'style="color:#C8A84B;text-decoration:underline;">обработку персональных данных</a>' +
        ' в соответствии с&nbsp;Федеральным законом №&nbsp;152-ФЗ.';

    function injectConsent(containerEl, uid) {
        if (!containerEl) return;
        if (containerEl.querySelector('.opd-block, .v-consent-block')) return;

        const submitBtn = containerEl.querySelector(
            'button[type="submit"],.btn-submit,button[onclick*="submit"],button[onclick*="Submit"],button[onclick*="Order"],button[onclick*="sendCallbackRequest"]'
        );

        const wrap = document.createElement('div');
        wrap.className = 'v-consent-block';
        wrap.style.cssText = 'margin-top:20px;margin-bottom:16px;padding:8px 0;';
        wrap.innerHTML =
            '<label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;">' +
            '  <input type="checkbox" id="v_consent_' + uid + '" ' +
            '         style="width:16px;height:16px;margin-top:3px;flex-shrink:0;accent-color:#C8A84B;cursor:pointer;">' +
            '  <span style="font-size:13px;color:#666;line-height:1.5;">' + CONSENT_HTML + '</span>' +
            '</label>' +
            '<div class="v-consent-err" ' +
            '     style="display:none;color:#d32f2f;font-size:12px;margin-top:5px;font-weight:600;">' +
            '  <i class="fas fa-exclamation-circle"></i> Необходимо согласие для отправки' +
            '</div>';

        if (submitBtn) {
            submitBtn.parentNode.insertBefore(wrap, submitBtn);
        } else {
            containerEl.appendChild(wrap);
        }

        const checkbox = wrap.querySelector('input');
        const errorEl = wrap.querySelector('.v-consent-err');
        checkbox.addEventListener('change', function() {
            if (checkbox.checked && errorEl) errorEl.style.display = 'none';
        });
    }

    function checkConsent(containerEl) {
        if (!containerEl) return true;

        const checkbox = containerEl.querySelector(
            '.v-consent-block input[type="checkbox"], .opd-block input[type="checkbox"]'
        );
        const errorEl = containerEl.querySelector('.v-consent-err, .opd-err');

        if (!checkbox) return true;

        if (checkbox.checked) {
            if (errorEl) errorEl.style.display = 'none';
            return true;
        }

        if (errorEl) errorEl.style.display = 'block';
        checkbox.closest('.v-consent-block, .opd-block').scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });
        return false;
    }

    window.validateForm = function(nameId, phoneId, containerId) {
        const nameEl = document.getElementById(nameId);
        const phoneEl = document.getElementById(phoneId);
        const formEl = containerId ? document.getElementById(containerId) : null;
        let isValid = true;

        if (nameEl) {
            const nameError = validateFio(nameEl.value);
            if (nameError) {
                showFieldError(nameEl, nameError);
                isValid = false;
            } else {
                markSuccess(nameEl);
            }
        }

        if (phoneEl) {
            const phoneError = validatePhone(phoneEl.value);
            if (phoneError) {
                showFieldError(phoneEl, phoneError);
                isValid = false;
            } else {
                markSuccess(phoneEl);
            }
        }

        if (!checkConsent(formEl)) isValid = false;

        if (!isValid && (nameEl || phoneEl)) {
            const firstField = nameEl || phoneEl;
            const errorField = (nameEl && nameEl.style.borderColor === '#d32f2f') ? nameEl :
                (phoneEl && phoneEl.style.borderColor === '#d32f2f') ? phoneEl : firstField;
            errorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        return isValid;
    };

    const originalSubmitForm = window.submitForm;
    window.submitForm = function(formId, successId) {
        const isModal = formId === 'modalFormWrap';
        const nameId = isModal ? 'mname' : 'fname';
        const phoneId = isModal ? 'mphone' : 'fphone';

        if (!window.validateForm(nameId, phoneId, formId)) return;

        if (typeof originalSubmitForm === 'function') originalSubmitForm(formId, successId);
    };

    const originalOrderModal = window.submitOrderFromModal;
    window.submitOrderFromModal = function() {
        if (!window.validateForm('modalName', 'modalPhone', 'orderForm')) return;
        if (typeof originalOrderModal === 'function') originalOrderModal();
    };

    const originalCallback = window.sendCallbackRequest;
    window.sendCallbackRequest = function() {
        if (!window.validateForm('cbname', 'cbphone', 'callbackFormWrap')) return;
        if (typeof originalCallback === 'function') originalCallback();
    };

    function init() {
        document.querySelectorAll('input[type="tel"], [data-phone]').forEach(applyPhoneMask);

        ['mname', 'fname', 'modalName', 'cbname'].forEach(function(id) {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('input', function() { clearFieldError(el); });
            }
        });

        injectConsent(document.getElementById('orderForm'), 'order');
        injectConsent(document.getElementById('modalFormWrap'), 'modal');
        injectConsent(document.getElementById('mainForm'), 'main');
        injectConsent(document.getElementById('callbackFormWrap'), 'callback');

        document.querySelectorAll('[data-form]').forEach(function(el, index) {
            injectConsent(el, 'df' + index);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    const styles = [
        'input.inp-error, input[style*="#d32f2f"] {',
        '  box-shadow: 0 0 0 3px rgba(211,47,47,.10) !important;',
        '}',
        'input[style*="#27ae60"] {',
        '  box-shadow: 0 0 0 3px rgba(39,174,96,.10) !important;',
        '}',
        '.v-consent-block { user-select: none; }',
        '.v-consent-block label:hover span { color: #333; }',
        '.v-consent-block a:hover { text-decoration: none; }',
    ].join('\n');

    const styleElement = document.createElement('style');
    styleElement.textContent = styles;
    document.head.appendChild(styleElement);

}());

