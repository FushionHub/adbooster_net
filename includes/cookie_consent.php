<?php declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['adsbooster_consent'])) {
    return;
}
?>
<!-- Cookie Consent Banner -->
<div id="cookie-consent-banner" class="d-none" role="dialog" aria-label="Cookie Consent" aria-describedby="cookie-banner-desc">
    <div id="cookie-banner-main">
        <div class="container max-w-1200-px">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div class="d-flex align-items-start gap-3 flex-grow-1">
                    <div class="flex-shrink-0 pt-1">
                        <i class="ph ph-cookie" style="font-size:28px;color:var(--main-600);"></i>
                    </div>
                    <div>
                        <h3 class="text-white fw-semibold mb-1" style="font-size:15px;">We value your privacy</h3>
                        <p id="cookie-banner-desc" class="mb-0" style="color:#a1a1aa;font-size:13px;line-height:1.6;">
                            We use cookies to enhance your browsing experience, serve personalized ads or content, and analyze our traffic. By clicking "Accept All", you consent to our use of cookies. You can customise which categories you allow below.
                        </p>
                    </div>
                </div>
                <div class="d-flex gap-2 flex-shrink-0 flex-wrap">
                    <button id="cookie-btn-reject" class="btn btn-outline-secondary btn-sm rounded-pill fw-medium cookie-btn-outline" type="button">Reject All</button>
                    <button id="cookie-btn-customise" class="btn btn-outline-secondary btn-sm rounded-pill fw-medium cookie-btn-outline d-inline-flex align-items-center gap-1" type="button">
                        <i class="ph ph-sliders-horizontal"></i> Customise
                    </button>
                    <button id="cookie-btn-accept" class="btn btn-sm rounded-pill fw-semibold cookie-btn-green" type="button">Accept All</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Expanded Settings Panel -->
    <div id="cookie-settings-panel" class="d-none">
        <div class="container max-w-1200-px">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <h3 class="text-white fw-semibold mb-0" style="font-size:16px;">Cookie Preferences</h3>
                <button id="cookie-close-panel" class="btn btn-sm p-1 text-secondary hover-text-main-600" type="button" aria-label="Close settings">
                    <i class="ph ph-x" style="font-size:18px;"></i>
                </button>
            </div>
            <p style="color:#a1a1aa;font-size:13px;margin:0 0 14px 0;line-height:1.6;">
                Choose which cookies you allow. You can change these settings at any time. Necessary cookies are always enabled as they are essential for the website to function.
            </p>

            <div class="d-flex flex-column gap-2">
                <div class="cookie-toggle-row">
                    <div class="flex-grow-1 me-3">
                        <span class="text-white fw-medium d-block" style="font-size:14px;">Necessary</span>
                        <span class="d-block" style="color:#71717a;font-size:12px;">Required for the website to function properly. Cannot be disabled.</span>
                    </div>
                    <label class="cookie-toggle-switch disabled mb-0">
                        <input type="checkbox" checked disabled data-cookie="necessary">
                        <span class="cookie-toggle-slider"></span>
                    </label>
                </div>

                <div class="cookie-toggle-row">
                    <div class="flex-grow-1 me-3">
                        <span class="text-white fw-medium d-block" style="font-size:14px;">Preferences</span>
                        <span class="d-block" style="color:#71717a;font-size:12px;">Remember settings like language, region, or display preferences.</span>
                    </div>
                    <label class="cookie-toggle-switch mb-0">
                        <input type="checkbox" data-cookie="preferences">
                        <span class="cookie-toggle-slider"></span>
                    </label>
                </div>

                <div class="cookie-toggle-row">
                    <div class="flex-grow-1 me-3">
                        <span class="text-white fw-medium d-block" style="font-size:14px;">Analytics</span>
                        <span class="d-block" style="color:#71717a;font-size:12px;">Help us understand how visitors interact with our website.</span>
                    </div>
                    <label class="cookie-toggle-switch mb-0">
                        <input type="checkbox" data-cookie="analytics">
                        <span class="cookie-toggle-slider"></span>
                    </label>
                </div>

                <div class="cookie-toggle-row">
                    <div class="flex-grow-1 me-3">
                        <span class="text-white fw-medium d-block" style="font-size:14px;">Marketing</span>
                        <span class="d-block" style="color:#71717a;font-size:12px;">Used to deliver personalized advertisements relevant to your interests.</span>
                    </div>
                    <label class="cookie-toggle-switch mb-0">
                        <input type="checkbox" data-cookie="marketing">
                        <span class="cookie-toggle-slider"></span>
                    </label>
                </div>
            </div>

            <div class="d-flex gap-2 justify-content-end mt-3">
                <button id="cookie-btn-save-prefs" class="btn btn-sm rounded-pill fw-medium cookie-btn-outline" type="button">Save Preferences</button>
                <button id="cookie-btn-accept-settings" class="btn btn-sm rounded-pill fw-semibold cookie-btn-green" type="button">Accept All</button>
            </div>
        </div>
    </div>
</div>

<style>
    #cookie-consent-banner {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        z-index: 99999;
    }
    #cookie-banner-main {
        background: #0a0a0a;
        border-top: 1px solid rgba(34,197,94,0.2);
        padding: 16px 0;
    }
    .cookie-btn-green {
        background: var(--main-600);
        color: #000;
        border: none;
    }
    .cookie-btn-green:hover {
        background: var(--main-700);
        color: #000;
    }
    .cookie-btn-outline {
        background: transparent;
        color: #d4d4d8;
        border: 1px solid #3f3f46;
    }
    .cookie-btn-outline:hover {
        border-color: var(--main-600);
        color: var(--main-600);
    }
    #cookie-settings-panel {
        background: #111111;
        border-top: 1px solid rgba(34,197,94,0.15);
        padding: 20px 0 24px;
    }
    .cookie-toggle-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 16px;
        background: #18181b;
        border-radius: 8px;
        border: 1px solid #27272a;
    }
    .cookie-toggle-switch {
        position: relative;
        display: inline-block;
        width: 42px;
        height: 24px;
        flex-shrink: 0;
    }
    .cookie-toggle-switch.disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    .cookie-toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    .cookie-toggle-slider {
        position: absolute;
        inset: 0;
        background: #3f3f46;
        border-radius: 24px;
        transition: background 0.3s;
        cursor: pointer;
    }
    .cookie-toggle-slider:before {
        content: "";
        position: absolute;
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background: #fff;
        border-radius: 50%;
        transition: transform 0.3s;
    }
    .cookie-toggle-switch input:checked + .cookie-toggle-slider {
        background: var(--main-600);
    }
    .cookie-toggle-switch input:checked + .cookie-toggle-slider:before {
        transform: translateX(18px);
    }
    @media (max-width: 768px) {
        #cookie-banner-main .d-flex {
            flex-direction: column;
            align-items: stretch !important;
        }
        #cookie-banner-main .d-flex .d-flex:last-child {
            justify-content: stretch;
        }
        #cookie-banner-main .d-flex .d-flex:last-child .cookie-btn-outline,
        #cookie-banner-main .d-flex .d-flex:last-child .cookie-btn-green {
            flex: 1;
            justify-content: center;
        }
    }
</style>

<script>
(function() {
    'use strict';

    var banner = document.getElementById('cookie-consent-banner');
    var mainPanel = document.getElementById('cookie-banner-main');
    var settingsPanel = document.getElementById('cookie-settings-panel');

    function generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            var r = Math.random() * 16 | 0;
            return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
        });
    }

    function getCookie(name) {
        var v = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');
        return v ? v.pop() : null;
    }

    function setCookie(name, value, days) {
        var d = new Date();
        d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000);
        document.cookie = name + '=' + value + ';expires=' + d.toUTCString() + ';path=/;SameSite=Lax';
    }

    function sendConsent(necessary, preferences, analytics, marketing) {
        var uuid = getCookie('adsbooster_consent_uuid') || generateUUID();
        var baseUrl = window.BASE_URL || '';

        var params = new URLSearchParams({
            action: 'save',
            consent_uuid: uuid,
            necessary: necessary ? '1' : '0',
            preferences: preferences ? '1' : '0',
            analytics: analytics ? '1' : '0',
            marketing: marketing ? '1' : '0'
        });

        var url = baseUrl + '/includes/cookie_consent_handler.php?' + params.toString();

        fetch(url)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                setCookie('adsbooster_consent', '1', 365);
                setCookie('adsbooster_consent_uuid', uuid, 365);
                setCookie('adsbooster_consent_prefs', JSON.stringify({
                    necessary: 1,
                    preferences: preferences ? 1 : 0,
                    analytics: analytics ? 1 : 0,
                    marketing: marketing ? 1 : 0
                }), 365);
                banner.classList.add('d-none');
                if (analytics) loadAnalytics();
                if (marketing) loadMarketing();
            })
            .catch(function() {
                setCookie('adsbooster_consent', '1', 365);
                setCookie('adsbooster_consent_prefs', JSON.stringify({
                    necessary: 1,
                    preferences: preferences ? 1 : 0,
                    analytics: analytics ? 1 : 0,
                    marketing: marketing ? 1 : 0
                }), 365);
                banner.classList.add('d-none');
                if (analytics) loadAnalytics();
                if (marketing) loadMarketing();
            });
    }

    function loadAnalytics() {
        if (window._adsbooster_analytics_loaded) return;
        window._adsbooster_analytics_loaded = true;
        var s = document.createElement('script');
        s.src = 'https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX';
        s.async = true;
        document.head.appendChild(s);
    }

    function loadMarketing() {
        if (window._adsbooster_marketing_loaded) return;
        window._adsbooster_marketing_loaded = true;
    }

    document.getElementById('cookie-btn-reject').addEventListener('click', function() {
        sendConsent(true, false, false, false);
    });

    document.getElementById('cookie-btn-accept').addEventListener('click', function() {
        sendConsent(true, true, true, true);
    });

    document.getElementById('cookie-btn-customise').addEventListener('click', function() {
        mainPanel.classList.add('d-none');
        settingsPanel.classList.remove('d-none');
    });

    document.getElementById('cookie-close-panel').addEventListener('click', function() {
        settingsPanel.classList.add('d-none');
        mainPanel.classList.remove('d-none');
    });

    document.getElementById('cookie-btn-save-prefs').addEventListener('click', function() {
        var prefs = {};
        document.querySelectorAll('#cookie-settings-panel input[data-cookie]').forEach(function(input) {
            prefs[input.dataset.cookie] = input.checked ? 1 : 0;
        });
        sendConsent(1, prefs.preferences || 0, prefs.analytics || 0, prefs.marketing || 0);
    });

    document.getElementById('cookie-btn-accept-settings').addEventListener('click', function() {
        sendConsent(true, true, true, true);
    });

    if (!getCookie('adsbooster_consent')) {
        banner.classList.remove('d-none');
    } else {
        var savedPrefs = getCookie('adsbooster_consent_prefs');
        if (savedPrefs) {
            try {
                var p = JSON.parse(savedPrefs);
                if (p.analytics) loadAnalytics();
                if (p.marketing) loadMarketing();
            } catch(e) {}
        }
    }
})();
</script>
