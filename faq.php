<?php
declare(strict_types=1);

$pageTitle = 'Frequently Asked Questions & Knowledge Base - Adsbooster.net';
require_once __DIR__ . '/includes/header.php';
?>

<style>
/* FAQ Specific Premium Styles */
.faq-hero-badge {
    background: rgba(196, 240, 0, 0.12);
    border: 1px solid rgba(196, 240, 0, 0.3);
    color: #c4f000;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}
.faq-search-wrapper {
    position: relative;
    max-width: 650px;
    margin: 0 auto;
}
.faq-search-input {
    background: #0d1524 !important;
    border: 1px solid rgba(255, 255, 255, 0.12) !important;
    color: #ffffff !important;
    border-radius: 50px !important;
    padding: 16px 24px 16px 54px !important;
    font-size: 16px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
    transition: all 0.25s ease;
}
.faq-search-input:focus {
    border-color: #c4f000 !important;
    box-shadow: 0 0 0 4px rgba(196, 240, 0, 0.15) !important;
}
.faq-search-icon {
    position: absolute;
    left: 22px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 20px;
    color: #c4f000;
    pointer-events: none;
}
.faq-cat-btn {
    background: #0d1524;
    border: 1px solid rgba(255, 255, 255, 0.08);
    color: #94a3b8;
    padding: 8px 18px;
    border-radius: 30px;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.2s ease;
    cursor: pointer;
}
.faq-cat-btn:hover {
    color: #ffffff;
    border-color: rgba(255, 255, 255, 0.25);
    background: #131c30;
}
.faq-cat-btn.active {
    background: #c4f000 !important;
    color: #080c14 !important;
    border-color: #c4f000 !important;
    box-shadow: 0 4px 14px rgba(196, 240, 0, 0.25);
}

.faq-card-item {
    background: #0d1524 !important;
    border: 1px solid rgba(255, 255, 255, 0.08) !important;
    border-radius: 16px !important;
    margin-bottom: 16px;
    overflow: hidden;
    transition: all 0.25s ease;
}
.faq-card-item:hover {
    border-color: rgba(196, 240, 0, 0.3) !important;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
}
.faq-card-header {
    background: transparent !important;
    border: none !important;
    padding: 0;
}
.faq-btn {
    background: transparent !important;
    border: none !important;
    width: 100%;
    text-align: left;
    padding: 20px 24px !important;
    font-size: 17px !important;
    font-weight: 700 !important;
    color: #ffffff !important;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    cursor: pointer;
    box-shadow: none !important;
}
.faq-btn::after {
    display: none !important; /* Hide bootstrap default chevron */
}
.faq-icon-indicator {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: #c4f000;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    flex-shrink: 0;
    transition: transform 0.3s ease, background 0.3s ease;
}
.faq-btn:not(.collapsed) .faq-icon-indicator {
    transform: rotate(180deg);
    background: #c4f000;
    color: #080c14;
}
.faq-btn:not(.collapsed) {
    color: #c4f000 !important;
}
.faq-body-content {
    padding: 0 24px 24px 24px !important;
    color: #cbd5e1 !important;
    font-size: 15px !important;
    line-height: 1.8 !important;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
    padding-top: 16px !important;
}
.faq-tag {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 3px 8px;
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.06);
    color: #94a3b8;
    margin-right: 8px;
}

/* Light Theme overrides for FAQ */
[data-theme="light"] .faq-search-input {
    background: #ffffff !important;
    border-color: #cbd5e1 !important;
    color: #0f172a !important;
}
[data-theme="light"] .faq-cat-btn {
    background: #f1f5f9;
    border-color: #cbd5e1;
    color: #475569;
}
[data-theme="light"] .faq-cat-btn.active {
    background: #0f172a !important;
    color: #ffffff !important;
    border-color: #0f172a !important;
}
[data-theme="light"] .faq-card-item {
    background: #ffffff !important;
    border-color: #e2e8f0 !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.04);
}
[data-theme="light"] .faq-btn {
    color: #0f172a !important;
}
[data-theme="light"] .faq-btn:not(.collapsed) {
    color: #0284c7 !important;
}
[data-theme="light"] .faq-body-content {
    color: #334155 !important;
    border-color: #f1f5f9;
}
[data-theme="light"] .faq-icon-indicator {
    background: #f1f5f9;
    border-color: #cbd5e1;
    color: #0284c7;
}
[data-theme="light"] .faq-btn:not(.collapsed) .faq-icon-indicator {
    background: #0284c7;
    color: #ffffff;
}

/* Contact Card in FAQ */
.contact-glass-card {
    background: #0d1524 !important;
    border: 1px solid rgba(255, 255, 255, 0.08) !important;
    border-radius: 20px;
    padding: 40px;
    box-shadow: 0 16px 40px rgba(0,0,0,0.3);
}
[data-theme="light"] .contact-glass-card {
    background: #ffffff !important;
    border-color: #e2e8f0 !important;
    box-shadow: 0 16px 40px rgba(0,0,0,0.06);
}
.contact-input {
    background: rgba(255, 255, 255, 0.04) !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    color: #ffffff !important;
    border-radius: 10px !important;
    padding: 12px 18px !important;
    font-size: 15px;
}
.contact-input:focus {
    border-color: #c4f000 !important;
    box-shadow: 0 0 0 3px rgba(196, 240, 0, 0.15) !important;
}
[data-theme="light"] .contact-input {
    background: #f8fafc !important;
    border-color: #cbd5e1 !important;
    color: #0f172a !important;
}
</style>

<!-- ==================== Breadcrumb Start ==================== -->
<section class="breadcrumb position-relative z-1 mb-0" style="padding-top: 140px; padding-bottom: 50px; background: #080c14;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10 text-center">
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-3" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);">
                    <a href="<?= url('index') ?>" class="text-white-50 text-decoration-none hover-text-main-600 font-size-13"><i class="ph ph-house me-1"></i> Home</a>
                    <span class="text-white-50 font-size-13">/</span>
                    <span class="text-main-600 font-size-13 fw-semibold">Knowledge Base &amp; FAQ</span>
                </div>
                <h1 class="text-white fw-bold display-5 mb-3" style="letter-spacing: -0.5px;">
                    Frequently Asked <span style="color: #c4f000;">Questions</span>
                </h1>
                <p class="text-white-50 font-size-16 max-w-650-px mx-auto mb-4" style="color: #94a3b8 !important;">
                    Find instant answers regarding Adsbooster AI studio, multi-platform ads scaling, 2FA account security, verified directory listings, and wallet funding.
                </p>

                <!-- Search Input -->
                <div class="faq-search-wrapper mb-4">
                    <i class="ph-bold ph-magnifying-glass faq-search-icon"></i>
                    <input type="text" id="faqSearchInput" class="form-control faq-search-input" placeholder="Search questions or keywords (e.g. 2FA, TikTok, ROAS, Wallet, UGC)..." autocomplete="off">
                </div>

                <!-- Category Filters -->
                <div class="d-flex flex-wrap align-items-center justify-content-center gap-2 mt-3" id="faqCategoryFilters">
                    <button type="button" class="faq-cat-btn active" data-category="all">All Topics (14)</button>
                    <button type="button" class="faq-cat-btn" data-category="ai">AI &amp; UGC Studio</button>
                    <button type="button" class="faq-cat-btn" data-category="ads">Ad Networks &amp; ROAS</button>
                    <button type="button" class="faq-cat-btn" data-category="security">Security &amp; 2FA</button>
                    <button type="button" class="faq-cat-btn" data-category="billing">Billing &amp; Wallet</button>
                    <button type="button" class="faq-cat-btn" data-category="directory">Directory &amp; Portfolio</button>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- ==================== Breadcrumb End ==================== -->

<!-- =============================== FAQ Section Start ================================= -->
<section class="py-5" style="background: #080c14; min-height: 60vh;">
    <div class="container">
        
        <div id="noResultsMsg" class="text-center py-5 d-none">
            <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px; background: rgba(196,240,0,0.1); color: #c4f000; font-size: 28px;">
                <i class="ph ph-magnifying-glass"></i>
            </div>
            <h4 class="text-white fw-bold mb-2">No matching questions found</h4>
            <p class="text-white-50 font-size-14 max-w-450-px mx-auto mb-4">
                We couldn't find any questions matching your query. Try different keywords or reach out to our team directly.
            </p>
            <button type="button" class="btn btn-outline-light rounded-pill px-4" onclick="resetFaqSearch()">
                Clear Search
            </button>
        </div>

        <div class="row g-4" id="faqAccordionContainer">
            <!-- Left Column: Items 1 to 7 -->
            <div class="col-lg-6">
                
                <!-- Q1 -->
                <div class="faq-card-item" data-category="ads" data-keywords="what is adsbooster how it works unified budget automation platform">
                    <div class="faq-card-header">
                        <button class="faq-btn" type="button" data-bs-toggle="collapse" data-bs-target="#faqCol1" aria-expanded="true">
                            <span>
                                <span class="faq-tag">Platform</span>
                                1. What is Adsbooster and how does it work?
                            </span>
                            <span class="faq-icon-indicator"><i class="ph-bold ph-caret-down"></i></span>
                        </button>
                    </div>
                    <div id="faqCol1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordionContainer">
                        <div class="faq-body-content">
                            Adsbooster is a high-performance advertising automation suite engineered for businesses, e-commerce brands, and growth agencies. It empowers you to create AI-generated video and image ad creatives, split-test campaigns across Meta (Facebook &amp; Instagram), TikTok, Google Ads, Snapchat, and LinkedIn simultaneously, and manage budgets in real time from a single unified wallet.
                        </div>
                    </div>
                </div>

                <!-- Q2 -->
                <div class="faq-card-item" data-category="ai" data-keywords="ugc video studio ai reel generator groq gemini hooks storyboards">
                    <div class="faq-card-header">
                        <button class="faq-btn collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCol2" aria-expanded="false">
                            <span>
                                <span class="faq-tag">AI Studio</span>
                                2. How does the AI UGC Video Studio generate scripts?
                            </span>
                            <span class="faq-icon-indicator"><i class="ph-bold ph-caret-down"></i></span>
                        </button>
                    </div>
                    <div id="faqCol2" class="accordion-collapse collapse" data-bs-parent="#faqAccordionContainer">
                        <div class="faq-body-content">
                            Our AI Studio utilizes high-throughput Groq LLM (Llama 3.3) and Google Gemini neural models to analyze target audiences and formulate viral 3-second hooks, multi-scene storyboards, camera movement prompts, voiceover dialogues, and on-screen captions. It features an interactive 9:16 vertical smartphone preview with instant export to CapCut and TikTok ad formats.
                        </div>
                    </div>
                </div>

                <!-- Q3 -->
                <div class="faq-card-item" data-category="directory" data-keywords="directory verified listings marketplace local business whatsapp maps">
                    <div class="faq-card-header">
                        <button class="faq-btn collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCol3" aria-expanded="false">
                            <span>
                                <span class="faq-tag">Directory</span>
                                3. How do verified local marketplace listings work?
                            </span>
                            <span class="faq-icon-indicator"><i class="ph-bold ph-caret-down"></i></span>
                        </button>
                    </div>
                    <div id="faqCol3" class="accordion-collapse collapse" data-bs-parent="#faqAccordionContainer">
                        <div class="faq-body-content">
                            Verified businesses, medical practices, boutique hotels, restaurants, and retail providers can publish high-visibility listings on our homepage directory and marketplace. Each listing includes direct WhatsApp chat, telephone click-to-call, GPS turn-by-turn navigation via Google Maps, verified trust badges, and rich customer reviews.
                        </div>
                    </div>
                </div>

                <!-- Q4 -->
                <div class="faq-card-item" data-category="ads" data-keywords="social connect gateway bundle zernio meta tiktok google snapchat handshake signed state">
                    <div class="faq-card-header">
                        <button class="faq-btn collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCol4" aria-expanded="false">
                            <span>
                                <span class="faq-tag">Social Sync</span>
                                4. How do I connect my real Meta, TikTok, and Google Ads accounts?
                            </span>
                            <span class="faq-icon-indicator"><i class="ph-bold ph-caret-down"></i></span>
                        </button>
                    </div>
                    <div id="faqCol4" class="accordion-collapse collapse" data-bs-parent="#faqAccordionContainer">
                        <div class="faq-body-content">
                            Adsbooster routes <strong class="text-info">100% of external traffic through two third-party gateways</strong> — nothing connects directly to platform APIs. In <strong>Social Connect</strong>, organic channels authorize through the <strong>Bundle.social</strong> engine and ad accounts through the <strong>Zernio</strong> Ads Engine with signed, single-use connect states. The gateway holds every platform credential server-side; Conversion/Events delivery runs asynchronously through Zernio with per-destination outcome logging, and no platform access token is ever stored on your Adsbooster account.
                        </div>
                    </div>
                </div>

                <!-- Q5 -->
                <div class="faq-card-item" data-category="security" data-keywords="2fa two factor authentication totp qr code google authenticator backup recovery">
                    <div class="faq-card-header">
                        <button class="faq-btn collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCol5" aria-expanded="false">
                            <span>
                                <span class="faq-tag">Security</span>
                                5. How does Two-Factor Authentication (2FA) protect my account?
                            </span>
                            <span class="faq-icon-indicator"><i class="ph-bold ph-caret-down"></i></span>
                        </button>
                    </div>
                    <div id="faqCol5" class="accordion-collapse collapse" data-bs-parent="#faqAccordionContainer">
                        <div class="faq-body-content">
                            We implement production-grade RFC 6238 TOTP algorithms compatible with Google Authenticator, Microsoft Authenticator, and 1Password. When you activate 2FA in **2FA Security**, Adsbooster renders a dynamic live QR code and secret key. Upon successful code confirmation, you are generated 10 emergency cryptographic backup recovery keys to protect your wallet balance and ad campaigns.
                        </div>
                    </div>
                </div>

                <!-- Q6 -->
                <div class="faq-card-item" data-category="directory" data-keywords="digital vcard portfolio builder resume upload pdf qr code shareable">
                    <div class="faq-card-header">
                        <button class="faq-btn collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCol6" aria-expanded="false">
                            <span>
                                <span class="faq-tag">Portfolio</span>
                                6. How does the Digital vCard &amp; AI Portfolio Builder work?
                            </span>
                            <span class="faq-icon-indicator"><i class="ph-bold ph-caret-down"></i></span>
                        </button>
                    </div>
                    <div id="faqCol6" class="accordion-collapse collapse" data-bs-parent="#faqAccordionContainer">
                        <div class="faq-body-content">
                            Every user can generate a personalized public portfolio and vCard. Upload your resume (PDF/DOCX) or paste your experience, and our AI automatically extracts your bio, skills, credentials, and achievements. You can select between Dark Luxury, Cyber Neon, or Executive styles, share your dedicated public link (<code class="text-main-600">/profile.php?u=...</code>), and let prospects download your digital contact card (.vcf) with one click.
                        </div>
                    </div>
                </div>

                <!-- Q7 -->
                <div class="faq-card-item" data-category="billing" data-keywords="theme dark mode interface reading comfort eye strain contrast">
                    <div class="faq-card-header">
                        <button class="faq-btn collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCol7" aria-expanded="false">
                            <span>
                                <span class="faq-tag">Interface</span>
                                7. Which theme does Adsbooster use for its interface?
                            </span>
                            <span class="faq-icon-indicator"><i class="ph-bold ph-caret-down"></i></span>
                        </button>
                    </div>
                    <div id="faqCol7" class="accordion-collapse collapse" data-bs-parent="#faqAccordionContainer">
                        <div class="faq-body-content">
                            Adsbooster uses a single carefully engineered dark interface across every page and dashboard — deep ink backgrounds, crisp white typography, and high-contrast accent highlights (#c8ff00) tuned for extended working sessions. This keeps contrast predictable and text legible for all form inputs, dashboards, and marketing pages without per-user configuration.
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right Column: Items 8 to 14 -->
            <div class="col-lg-6">

                <!-- Q8 -->
                <div class="faq-card-item" data-category="billing" data-keywords="payment methods wallet paystack flutterwave stripe currency funding">
                    <div class="faq-card-header">
                        <button class="faq-btn" type="button" data-bs-toggle="collapse" data-bs-target="#faqCol8" aria-expanded="true">
                            <span>
                                <span class="faq-tag">Billing</span>
                                8. What payment methods are supported for ad credits?
                            </span>
                            <span class="faq-icon-indicator"><i class="ph-bold ph-caret-down"></i></span>
                        </button>
                    </div>
                    <div id="faqCol8" class="accordion-collapse collapse show" data-bs-parent="#faqAccordionContainer">
                        <div class="faq-body-content">
                            You can fund your ad budget and AI credits instantly using Paystack (debit/credit cards, bank transfers, USSD), Flutterwave, and Stripe. Deposits reflect immediately in your balance with automatic exchange rate conversion, enabling you to launch ad campaigns without processing delays.
                        </div>
                    </div>
                </div>

                <!-- Q9 -->
                <div class="faq-card-item" data-category="ads" data-keywords="campaign doctor roas optimization cpa fatigue budget scaling">
                    <div class="faq-card-header">
                        <button class="faq-btn collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCol9" aria-expanded="false">
                            <span>
                                <span class="faq-tag">ROAS Engine</span>
                                9. How does the autonomous Campaign Doctor optimize my ROAS?
                            </span>
                            <span class="faq-icon-indicator"><i class="ph-bold ph-caret-down"></i></span>
                        </button>
                    </div>
                    <div id="faqCol9" class="accordion-collapse collapse" data-bs-parent="#faqAccordionContainer">
                        <div class="faq-body-content">
                            Our 24/7 heuristic Campaign Doctor continuously monitors conversion metrics, identifies creative hook fatigue, pauses ad sets exceeding your target acquisition threshold, and shifts ad spend towards top-performing demographics and networks to maximize your return on ad spend.
                        </div>
                    </div>
                </div>

                <!-- Q10 -->
                <div class="faq-card-item" data-category="directory" data-keywords="jobs bounties hiring talent media buyers ugc creators contract">
                    <div class="faq-card-header">
                        <button class="faq-btn collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCol10" aria-expanded="false">
                            <span>
                                <span class="faq-tag">Marketplace</span>
                                10. Can I hire creators and media buyers through Jobs &amp; Bounties?
                            </span>
                            <span class="faq-icon-indicator"><i class="ph-bold ph-caret-down"></i></span>
                        </button>
                    </div>
                    <div id="faqCol10" class="accordion-collapse collapse" data-bs-parent="#faqAccordionContainer">
                        <div class="faq-body-content">
                            Yes! Brands and agency operators can post contract roles, fixed-price bounties, and media buying briefs on our marketplace. Creators and media buyers can submit applications, showcase their verified vCard portfolios, and coordinate deliverables through built-in P2P messaging.
                        </div>
                    </div>
                </div>

                <!-- Q11 -->
                <div class="faq-card-item" data-category="ai" data-keywords="ai consultant p2p messenger chat direct real time strategist">
                    <div class="faq-card-header">
                        <button class="faq-btn collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCol11" aria-expanded="false">
                            <span>
                                <span class="faq-tag">P2P Chat</span>
                                11. How does the P2P &amp; AI Consultant Chat work?
                            </span>
                            <span class="faq-icon-indicator"><i class="ph-bold ph-caret-down"></i></span>
                        </button>
                    </div>
                    <div id="faqCol11" class="accordion-collapse collapse" data-bs-parent="#faqAccordionContainer">
                        <div class="faq-body-content">
                            Our integrated communications suite provides direct user-to-user messaging for project collaboration, alongside a 24/7 autonomous AI Media Buying Strategist. The AI Consultant can audit campaign plans, suggest high-converting ad angles, write split-test copy, and provide multi-platform tactical playbooks in seconds.
                        </div>
                    </div>
                </div>

                <!-- Q12 -->
                <div class="faq-card-item" data-category="billing" data-keywords="contracts subscriptions pricing plans cancel refund transparent">
                    <div class="faq-card-header">
                        <button class="faq-btn collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCol12" aria-expanded="false">
                            <span>
                                <span class="faq-tag">Pricing</span>
                                12. Are there any long-term contracts or hidden fees?
                            </span>
                            <span class="faq-icon-indicator"><i class="ph-bold ph-caret-down"></i></span>
                        </button>
                    </div>
                    <div id="faqCol12" class="accordion-collapse collapse" data-bs-parent="#faqAccordionContainer">
                        <div class="faq-body-content">
                            No. Adsbooster is completely transparent. You pay strictly for your chosen subscription tier and your exact ad spend / AI generation credits. You can upgrade, downgrade, or cancel your plan at any time directly from the Billing &amp; Wallet settings with zero cancellation penalties.
                        </div>
                    </div>
                </div>

                <!-- Q13 -->
                <div class="faq-card-item" data-category="ai" data-keywords="post creator social publish video automation schedule batch">
                    <div class="faq-card-header">
                        <button class="faq-btn collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCol13" aria-expanded="false">
                            <span>
                                <span class="faq-tag">Automation</span>
                                13. Can I schedule and publish organic posts as well as ads?
                            </span>
                            <span class="faq-icon-indicator"><i class="ph-bold ph-caret-down"></i></span>
                        </button>
                    </div>
                    <div id="faqCol13" class="accordion-collapse collapse" data-bs-parent="#faqAccordionContainer">
                        <div class="faq-body-content">
                            Yes. Using our Post Creator and Content Calendar, you can craft, schedule, and cross-publish organic videos, carousel updates, and announcements across your connected social channels, ensuring your organic brand presence grows alongside your paid ad funnels.
                        </div>
                    </div>
                </div>

                <!-- Q14 -->
                <div class="faq-card-item" data-category="ads" data-keywords="get started onboard register trial step by step setup">
                    <div class="faq-card-header">
                        <button class="faq-btn collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCol14" aria-expanded="false">
                            <span>
                                <span class="faq-tag">Get Started</span>
                                14. How do I get started with Adsbooster today?
                            </span>
                            <span class="faq-icon-indicator"><i class="ph-bold ph-caret-down"></i></span>
                        </button>
                    </div>
                    <div id="faqCol14" class="accordion-collapse collapse" data-bs-parent="#faqAccordionContainer">
                        <div class="faq-body-content">
                            Getting started takes under two minutes. Simply click <strong>Dashboard</strong> or <strong>Register</strong> to create your account, configure your profile and 2FA security, test our AI UGC Studio, and link your ad accounts. You can also explore open jobs or list your business on the verified directory right away.
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>
</section>
<!-- =============================== FAQ Section End ================================= -->

<!-- =============================== Contact Support Section Start ================================= -->
<section class="py-5" style="background: #080c14; border-top: 1px solid rgba(255,255,255,0.06);">
    <div class="container py-4">
        <div class="row gy-4 align-items-center">
            
            <div class="col-lg-7">
                <div class="contact-glass-card">
                    <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-3" style="background: rgba(196,240,0,0.1); border: 1px solid rgba(196,240,0,0.25);">
                        <i class="ph-bold ph-chats text-main-600"></i>
                        <span class="text-main-600 font-size-12 fw-bold text-uppercase">Direct Inquiries</span>
                    </div>
                    <h2 class="text-white fw-bold h3 mb-2">Have a question not listed here?</h2>
                    <p class="text-white-50 font-size-14 mb-4" style="color: #94a3b8 !important;">
                        Our 24/7 support engineering team and campaign consultants are ready to assist you.
                    </p>

                    <form id="faqContactForm" onsubmit="handleFaqContactSubmit(event)">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="text-white-50 font-size-13 fw-semibold mb-1 d-block">Your Full Name *</label>
                                <input type="text" class="form-control contact-input" placeholder="e.g. Alex Vance" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="text-white-50 font-size-13 fw-semibold mb-1 d-block">Email Address *</label>
                                <input type="email" class="form-control contact-input" placeholder="alex@company.com" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="text-white-50 font-size-13 fw-semibold mb-1 d-block">Phone / WhatsApp</label>
                                <input type="tel" class="form-control contact-input" placeholder="+1 (555) 019-2834">
                            </div>
                            <div class="col-sm-6">
                                <label class="text-white-50 font-size-13 fw-semibold mb-1 d-block">Subject</label>
                                <select class="form-select contact-input">
                                    <option value="General Inquiry">General Platform Inquiry</option>
                                    <option value="2FA & Security">2FA &amp; Security Support</option>
                                    <option value="Ad Networks">Ad Account &amp; API Integration</option>
                                    <option value="Billing & Wallet">Billing &amp; Wallet Top-Up</option>
                                    <option value="Enterprise">Enterprise &amp; Agency Partnership</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="text-white-50 font-size-13 fw-semibold mb-1 d-block">Your Message *</label>
                                <textarea class="form-control contact-input" rows="4" placeholder="How can we assist you with Adsbooster today?" required></textarea>
                            </div>
                            <div class="col-12 pt-2">
                                <button type="submit" class="btn btn-main-600 text-dark fw-bold rounded-pill px-4 py-3 d-inline-flex align-items-center gap-2">
                                    <span>Send Message to Support</span>
                                    <i class="ph-bold ph-paper-plane-tilt"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                    <div id="contactSuccessAlert" class="alert alert-success mt-4 rounded-3 d-none">
                        <i class="ph-fill ph-check-circle me-1"></i> Thank you! Your inquiry has been routed to our technical support team. A representative will contact you within 15 minutes.
                    </div>
                </div>
            </div>

            <!-- Right Column Info Box -->
            <div class="col-lg-5 ps-lg-4">
                <div class="card bg-card border border-secondary border-opacity-15 rounded-4 p-4 p-md-5" style="background: #0d1524;">
                    <h4 class="text-white fw-bold mb-4 font-size-18">Instant Support Channels</h4>
                    
                    <div class="d-flex align-items-start gap-3 mb-4">
                        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px; background: rgba(196,240,0,0.15); color: #c4f000; font-size: 20px;">
                            <i class="ph ph-chat-teardrop-dots"></i>
                        </div>
                        <div>
                            <div class="text-white fw-bold font-size-15">Real-Time P2P &amp; AI Chat</div>
                            <div class="text-white-50 font-size-13 mb-2" style="color: #94a3b8 !important;">Chat with live agents or consult our AI strategist in your portal.</div>
                            <a href="<?= url('p2p-chat') ?>" class="text-main-600 font-size-13 fw-semibold text-decoration-none hover-underline">
                                Open Chat Studio &rarr;
                            </a>
                        </div>
                    </div>

                    <div class="d-flex align-items-start gap-3 mb-4">
                        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px; background: rgba(37,211,102,0.15); color: #25D366; font-size: 20px;">
                            <i class="ph ph-whatsapp-logo"></i>
                        </div>
                        <div>
                            <div class="text-white fw-bold font-size-15">WhatsApp Priority Desk</div>
                            <div class="text-white-50 font-size-13 mb-2" style="color: #94a3b8 !important;">Direct escalation for ad account linking and wallet funding.</div>
                            <a href="https://wa.me/18005550199?text=Hello%20Adsbooster%20Support" target="_blank" class="text-success font-size-13 fw-semibold text-decoration-none hover-underline">
                                Message on WhatsApp &rarr;
                            </a>
                        </div>
                    </div>

                    <div class="d-flex align-items-start gap-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px; background: rgba(56,189,248,0.15); color: #38bdf8; font-size: 20px;">
                            <i class="ph ph-shield-check"></i>
                        </div>
                        <div>
                            <div class="text-white fw-bold font-size-15">Security &amp; 2FA Assistance</div>
                            <div class="text-white-50 font-size-13 mb-2" style="color: #94a3b8 !important;">Need to reset backup codes or verify your authenticator app?</div>
                            <a href="<?= url('security-2fa') ?>" class="text-info font-size-13 fw-semibold text-decoration-none hover-underline">
                                Manage 2FA Settings &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>
<!-- =============================== Contact Support Section End ================================= -->

<script>
// FAQ Interactive Search & Category Filter
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('faqSearchInput');
    const categoryBtns = document.querySelectorAll('#faqCategoryFilters .faq-cat-btn');
    const faqCards = document.querySelectorAll('.faq-card-item');
    const noResultsMsg = document.getElementById('noResultsMsg');

    let currentCategory = 'all';
    let currentQuery = '';

    function filterFaqs() {
        let visibleCount = 0;

        faqCards.forEach(card => {
            const cardCat = card.getAttribute('data-category');
            const cardKeywords = (card.getAttribute('data-keywords') || '') + ' ' + card.innerText.toLowerCase();
            
            const matchesCat = (currentCategory === 'all' || cardCat === currentCategory);
            const matchesQuery = !currentQuery || cardKeywords.includes(currentQuery);

            if (matchesCat && matchesQuery) {
                card.classList.remove('d-none');
                visibleCount++;
            } else {
                card.classList.add('d-none');
            }
        });

        if (visibleCount === 0) {
            noResultsMsg.classList.remove('d-none');
        } else {
            noResultsMsg.classList.add('d-none');
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            currentQuery = this.value.trim().toLowerCase();
            filterFaqs();
        });
    }

    categoryBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            categoryBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentCategory = this.getAttribute('data-category');
            filterFaqs();
        });
    });

    window.resetFaqSearch = function() {
        if (searchInput) searchInput.value = '';
        currentQuery = '';
        currentCategory = 'all';
        categoryBtns.forEach(b => b.classList.toggle('active', b.getAttribute('data-category') === 'all'));
        filterFaqs();
    };
});

function handleFaqContactSubmit(e) {
    e.preventDefault();
    const alert = document.getElementById('contactSuccessAlert');
    if (alert) {
        alert.classList.remove('d-none');
        e.target.reset();
        setTimeout(() => {
            alert.classList.add('d-none');
        }, 7000);
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
