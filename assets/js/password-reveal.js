/**
 * Password revealer — adds an eye toggle to every password field wrapped
 * in `.pw-reveal-wrap`. Include on login / signup pages:
 *   <script src="<?= BASE_URL ?>/assets/js/password-reveal.js"></script>
 */
(function () {
    'use strict';

    // Self-contained styling for the toggle button.
    var css = document.createElement('style');
    css.textContent =
        '.pw-reveal-wrap{position:relative}' +
        '.pw-reveal-wrap .form-control{padding-right:44px !important}' +
        '.pw-reveal-btn{position:absolute;right:10px;top:50%;transform:translateY(-50%);' +
        'background:transparent;border:0;padding:4px 6px;line-height:1;font-size:18px;' +
        'color:#8b93a7;cursor:pointer;border-radius:8px;transition:color .15s ease}' +
        '.pw-reveal-btn:hover{color:#e8ebf2}' +
        '.pw-reveal-btn i{pointer-events:none}';
    document.head.appendChild(css);

    function wire(btn) {
        var input = document.getElementById(btn.getAttribute('data-pw-target'));
        if (!input || btn.dataset.pwRevealWired) {
            return;
        }
        btn.dataset.pwRevealWired = '1';
        btn.addEventListener('click', function () {
            var showing = input.getAttribute('type') === 'text';
            input.setAttribute('type', showing ? 'password' : 'text');
            btn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
            btn.innerHTML = showing
                ? '<i class="ph ph-eye"></i>'
                : '<i class="ph ph-eye-slash"></i>';
        });
    }

    function scan() {
        document.querySelectorAll('.pw-reveal-btn').forEach(wire);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scan);
    } else {
        scan();
    }
})();
