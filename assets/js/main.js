/* =====================================================================
   main.js  —  Client-side scripting (JavaScript curriculum topic).
   Covers: declaring variables, functions, event handlers (onclick,
   onsubmit, onchange), the Document Object Model (DOM) and form validation.
   ===================================================================== */

/* ---- Small DOM helper ------------------------------------------------ */
function $(id) { return document.getElementById(id); }

/* Show a field's error message and red border. Uses the DOM to add a class. */
function setInvalid(inputId, invalid) {
    var input = $(inputId);
    if (!input) return;
    var field = input.closest('.field');
    if (!field) return;
    if (invalid) { field.classList.add('invalid'); }
    else         { field.classList.remove('invalid'); }
}

/* =====================================================================
   Photo upload preview (Sell page) — reads the chosen file and shows it.
   Called from the file input's onchange handler.
   ===================================================================== */
/* Preview several chosen product photos as thumbnails (first = cover). */
function previewPhotos(input) {
    var wrap = $('photoPreviews'), hint = $('dropHint');
    if (!wrap) return;
    wrap.innerHTML = '';
    var files = input.files;
    if (!files || !files.length) { wrap.style.display = 'none'; if (hint) hint.style.display = ''; return; }
    var max = Math.min(files.length, 6);
    for (var i = 0; i < max; i++) {
        (function (file, idx) {
            var reader = new FileReader();
            reader.onload = function (ev) {
                var t = document.createElement('div');
                t.className = 'photo-thumb';
                var im = document.createElement('img'); im.src = ev.target.result; t.appendChild(im);
                if (idx === 0) { var b = document.createElement('span'); b.className = 'cover-badge'; b.textContent = 'Cover'; t.appendChild(b); }
                wrap.appendChild(t);
            };
            reader.readAsDataURL(file);
        })(files[i], i);
    }
    wrap.style.display = ''; if (hint) hint.style.display = 'none';
}

/* Product gallery: clicking a thumbnail swaps the main image. */
function swapGalleryPhoto(src, el) {
    var main = $('galleryMain');
    if (main) main.src = src;
    var thumbs = document.querySelectorAll('.gallery-thumbs .g-thumb');
    for (var i = 0; i < thumbs.length; i++) thumbs[i].classList.remove('active');
    if (el) el.classList.add('active');
}

/* Show the "name your category" box only when Other is chosen. */
function toggleCustomCat() {
    var sel = $('category'), field = $('customCatField');
    if (!sel || !field) return;
    field.style.display = (sel.value === 'Other') ? '' : 'none';
}

/* Show the UPI QR upload only when the UPI checkbox is ticked. */
function toggleQr() {
    var upi = $('payUpi'), field = $('qrField');
    if (!upi || !field) return;
    field.style.display = upi.checked ? '' : 'none';
}

/* Preview the chosen UPI QR image. */
function previewQr(input) {
    if (!input.files || !input.files[0]) return;
    var reader = new FileReader();
    reader.onload = function (ev) {
        var img = $('qrPreview'), hint = $('qrHint');
        img.src = ev.target.result; img.style.display = 'block';
        if (hint) hint.style.display = 'none';
    };
    reader.readAsDataURL(input.files[0]);
}

/* =====================================================================
   Sell form validation — runs on submit (onsubmit="return validateSellForm()")
   Returns false to stop the form if something is wrong.
   ===================================================================== */
function validateSellForm() {
    var ok = true;

    var title = $('title').value.trim();
    setInvalid('title', title === '');
    if (title === '') ok = false;

    // If "Other" is the category, the custom name is required.
    var cat = $('category');
    if (cat && cat.value === 'Other') {
        var cc = $('custom_category');
        var bad = !cc || cc.value.trim() === '';
        setInvalid('custom_category', bad);
        if (bad) ok = false;
    }

    var price = $('price').value;
    var badPrice = (price === '' || isNaN(price) || Number(price) < 0);
    setInvalid('price', badPrice);
    if (badPrice) ok = false;

    var desc = $('description').value.trim();
    setInvalid('description', desc === '');
    if (desc === '') ok = false;

    // At least one payment method must be ticked.
    var cod = document.querySelector('input[name="pay_cod"]');
    var upi = document.querySelector('input[name="pay_upi"]');
    if (cod && upi && !cod.checked && !upi.checked) {
        alert('Please choose at least one payment method (COD or UPI).');
        ok = false;
    }

    if (!ok) {
        // Scroll to the first invalid field so the user sees it.
        var firstBad = document.querySelector('.field.invalid');
        if (firstBad) firstBad.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    return ok;
}

/* =====================================================================
   Login form validation
   ===================================================================== */
function validateLogin() {
    var ok = true;
    var email = $('email').value.trim();
    var emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);   // simple email pattern
    setInvalid('email', !emailOk);
    if (!emailOk) ok = false;

    var pass = $('password').value;
    setInvalid('password', pass === '');
    if (pass === '') ok = false;

    return ok;
}

/* =====================================================================
   Register form validation
   ===================================================================== */
function validateRegister() {
    var ok = true;

    var name = $('name').value.trim();
    setInvalid('name', name === '');
    if (name === '') ok = false;

    var email = $('email').value.trim();
    var emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    setInvalid('email', !emailOk);
    if (!emailOk) ok = false;

    var pass = $('password').value;
    setInvalid('password', pass.length < 6);
    if (pass.length < 6) ok = false;

    var confirm = $('confirm').value;
    setInvalid('confirm', pass !== confirm);
    if (pass !== confirm) ok = false;

    // If they picked "Other" location, the typed value is required.
    var loc = $('hostel');
    if (loc && loc.value === 'Other') {
        var cl = $('custom_location');
        var bad = !cl || cl.value.trim() === '';
        setInvalid('custom_location', bad);
        if (bad) ok = false;
    }
    return ok;
}

/* Reveal the "type your location" field when "Other" is chosen. */
function toggleCustomLoc() {
    var sel = $('hostel'), field = $('customLocField');
    if (!sel || !field) return;
    field.style.display = (sel.value === 'Other') ? '' : 'none';
}

/* OTP screen: require a 6-digit code. */
function validateOtp() {
    var box = $('otp');
    var ok = box && /^\d{6}$/.test(box.value.trim());
    setInvalid('otp', !ok);
    return !!ok;
}

/* =====================================================================
   Chat: don't send an empty message
   ===================================================================== */
function validateChat() {
    var box = $('chatBox');
    if (!box || box.value.trim() === '') return false;
    return true;
}

/* When the messages page loads, scroll the chat to the newest message. */
document.addEventListener('DOMContentLoaded', function () {
    var body = $('chatBody');
    if (body) body.scrollTop = body.scrollHeight;
});

/* =====================================================================
   CUSTOM CURSOR  —  a small accent dot that follows the pointer and morphs
   into a thin ring over clickable things. Only on real mouse devices, and
   never under reduced-motion (the normal system cursor is left alone).
   ===================================================================== */
(function setupCursor() {
    var finePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
    var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (!finePointer || reduced) return;

    document.body.classList.add('cursor-on');

    var dot = document.createElement('div');
    dot.className = 'cursor-dot';
    document.body.appendChild(dot);

    var TEXT_OR_MODAL = 'input:not([type=checkbox]):not([type=radio]), textarea, dialog[open]';

    document.addEventListener('mousemove', function (e) {
        dot.style.transform = 'translate(' + e.clientX + 'px,' + e.clientY + 'px)';
        // Over text fields / inside a modal the native cursor takes over.
        var overText = !!(e.target.closest && e.target.closest(TEXT_OR_MODAL));
        dot.style.opacity = overText ? '0' : '';
        var overDark = !!(e.target.closest && e.target.closest('.site-header, .site-footer'));
        dot.classList.toggle('on-dark', overDark);
    });

    // Morph the dot into a ring over anything clickable (text fields excluded).
    var interactive = 'a, button, .card, .toggle, select, .icon-btn, .avatar, .thread, label, [role=button]';
    document.addEventListener('mouseover', function (e) {
        if (e.target.closest && e.target.closest(interactive)) dot.classList.add('is-hover');
    });
    document.addEventListener('mouseout', function (e) {
        if (e.target.closest && e.target.closest(interactive)) dot.classList.remove('is-hover');
    });
    document.addEventListener('mousedown', function () { dot.classList.add('is-down'); });
    document.addEventListener('mouseup',   function () { dot.classList.remove('is-down'); });
    document.addEventListener('mouseleave', function () { dot.style.opacity = '0'; });
    document.addEventListener('mouseenter', function () { dot.style.opacity = ''; });
})();
