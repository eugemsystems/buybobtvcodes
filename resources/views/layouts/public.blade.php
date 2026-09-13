<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        @production
        <script src="https://cdn.jsdelivr.net/npm/disable-devtool@latest/disable-devtool.min.js"></script>
        <script>
            DisableDevtool({
                disableMenu: true,
                clearLog: true,
                detectorWorker: true,
                ondevtoolopen: function () {
                    document.open('text/html', 'replace');
                    document.write('');
                    document.close();
                    window.location.replace('about:blank');
                },
            });
        </script>
        @endproduction
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Azeret+Mono:wght@400;500&display=swap" rel="stylesheet">
        <style>
            :root {
                --accent: #DDF247;
                --accent-dim: rgba(221,242,71,0.12);
                --accent-border: rgba(221,242,71,0.28);
                --card-1: #1a1a1a;
                --card-2: #232323;
                --border: rgba(255,255,255,0.08);
                --text-muted: rgba(255,255,255,0.50);
            }
            body { font-family: 'Manrope', sans-serif; background: #111111; }

            /* Custom cursor */
            * { cursor: none !important; }
            /* Restore native cursor inside open dialogs (top-layer; custom cursor can't render there) */
            dialog[open], dialog[open] * { cursor: auto !important; }
            /* z-index is maxed out so the cursor always wins against page overlays (PesePay's
               modal, PayFast's own onsite iframe overlay, etc.) — anything lower would risk
               rendering behind whichever overlay currently has the highest stacking. */
            #cursor-inner {
                position: fixed; width: 10px; height: 10px; border-radius: 50%;
                background: var(--accent); pointer-events: none; z-index: 2147483647;
                transform: translate(-50%,-50%); transition: transform 0.1s;
            }
            #cursor-outer {
                position: fixed; width: 36px; height: 36px; border-radius: 50%;
                border: 1.5px solid rgba(221,242,71,0.5); pointer-events: none;
                z-index: 2147483646; transform: translate(-50%,-50%);
                transition: transform 0.12s, width 0.2s, height 0.2s, opacity 0.2s;
            }

            /* Pulse keyframe for live badge */
            @keyframes pulse {
                0%, 100% { opacity: 1; }
                50% { opacity: .4; }
            }

            /* Animated gradient text */
            .gradient-text {
                background: linear-gradient(135deg, #DDF247, #a8ff78, #DDF247);
                background-size: 200% 200%;
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
                animation: gradientShift 4s ease infinite;
            }
            @keyframes gradientShift {
                0%,100% { background-position: 0% 50%; }
                50%      { background-position: 100% 50%; }
            }

            /* Brand card glow on hover */
            .brand-card:hover .brand-icon-wrap {
                box-shadow: 0 0 24px rgba(221,242,71,0.20);
                border-color: rgba(221,242,71,0.35) !important;
            }

            /* Step rainbow bars */
            .step-bar-1 { background: linear-gradient(129deg, #FDCF00, #FD9601); }
            .step-bar-2 { background: linear-gradient(129deg, #DDF247, #5B8500); }
            .step-bar-3 { background: linear-gradient(129deg, #EA66E2, #3E4FE7); }
            .step-bar-4 { background: linear-gradient(129deg, #FFEB00, #2DC41A); }

            /* Token store card hover */
            .token-card { transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease; }
            .token-card:hover { transform: translateY(-8px); box-shadow: 0 20px 50px rgba(0,0,0,0.5); border-color: rgba(221,242,71,0.3) !important; }

            /* Brands carousel */
            @keyframes brandScroll {
                from { transform: translateX(0); }
                to   { transform: translateX(-50%); }
            }
            .brands-track { animation: brandScroll 48s linear infinite; }
            .brands-track:hover { animation-play-state: paused; }

            /* Tokens for Every Need — responsive grid */
            #need-grid { display: grid; gap: 24px; grid-template-columns: 1fr; }
            @media (min-width: 640px)  { #need-grid { grid-template-columns: repeat(2, 1fr); } }
            @media (min-width: 1024px) { #need-grid { grid-template-columns: repeat(4, 1fr); } }

            /* How It Works — responsive grid */
            #hiw-grid { display: grid; gap: 24px; }
            .hiw-arrow { display: none; }
            @media (min-width: 1024px) {
                #hiw-grid { grid-template-columns: 1fr 72px 1fr 72px 1fr; gap: 0; }
                .hiw-arrow { display: flex; align-items: center; justify-content: center; padding-top: 50px; }
            }

            /* Section spacing */
            .sec { padding: 112px 0; }
            .sec-sm { padding: 96px 0; }
            .sec-inner { max-width: 72rem; margin: 0 auto; padding: 0 24px; }
            .sec-inner-sm { max-width: 48rem; margin: 0 auto; padding: 0 24px; }
            .sec-inner-md { max-width: 64rem; margin: 0 auto; padding: 0 24px; }
            .sec-head { margin-bottom: 64px; text-align: center; }
            .sec-badge { display: inline-flex; align-items: center; gap: 8px; border-radius: 999px; border: 1px solid; padding: 6px 16px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 4px; margin-bottom: 20px; font-family: 'Manrope', sans-serif; }
            .sec-h2 { font-size: 38px; font-weight: 800; color: #fff; margin-bottom: 14px; font-family: 'Manrope', sans-serif; line-height: 1.2; }
            .sec-sub { color: rgba(255,255,255,0.50); font-family: 'Azeret Mono', monospace; font-size: 14px; }

            /* Site header — always on top */
            #site-header {
                position: fixed; top: 0; left: 0; right: 0; z-index: 500;
                border-bottom: 1px solid rgba(255,255,255,0.07);
                background: rgba(17,17,17,0.92); backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
            }
            #site-header-inner {
                max-width: 72rem; margin: 0 auto; padding: 0 24px; height: 72px;
                display: flex; align-items: center; justify-content: space-between; gap: 24px;
            }

            /* Nav */
            .site-nav { display: none; align-items: center; gap: 4px; }
            @media (min-width: 768px) { .site-nav { display: flex; } }
            .nav-link {
                padding: 8px 14px; border-radius: 10px; font-size: 14px; font-weight: 600;
                color: rgba(255,255,255,0.65); text-decoration: none;
                transition: color 0.2s ease; font-family: 'Manrope', sans-serif; white-space: nowrap;
            }
            .nav-link:hover { color: #DDF247; }
            .nav-link.active { color: #DDF247; }

            /* Footer */
            .footer-grid {
                display: grid; gap: 40px; padding-bottom: 48px;
                border-bottom: 1px solid rgba(255,255,255,0.07);
                grid-template-columns: 1fr;
            }
            @media (min-width: 640px)  { .footer-grid { grid-template-columns: repeat(2,1fr); } }
            @media (min-width: 1024px) { .footer-grid { grid-template-columns: repeat(5,1fr); } }
            .footer-col-title {
                font-size: 11px; font-weight: 700; text-transform: uppercase;
                letter-spacing: 4px; color: #DDF247; margin-bottom: 20px;
                font-family: 'Manrope', sans-serif;
            }
            .footer-links { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 12px; }
            .footer-links li { font-size: 14px; color: rgba(255,255,255,0.50); font-family: 'Manrope', sans-serif; }
            .footer-links a { color: rgba(255,255,255,0.50); text-decoration: none; transition: color 0.2s; }
            .footer-links a:hover { color: #fff; }
            .footer-bottom {
                display: flex; flex-direction: column; align-items: center;
                justify-content: space-between; gap: 16px; padding-top: 32px;
            }
            @media (min-width: 640px) { .footer-bottom { flex-direction: row; } }

            /* Pill button */
            .btn-primary {
                display: inline-flex; align-items: center; justify-content: center; gap: 8px;
                background: var(--accent); color: #111111; font-weight: 800;
                border-radius: 14px; padding: 14px 28px; font-size: 15px; line-height: 1;
                transition: all 0.25s ease; border: none; font-family: 'Manrope', sans-serif;
            }
            .btn-primary:hover { background: #fff; color: #111111; transform: translateY(-2px); }
            .btn-ghost {
                display: inline-flex; align-items: center; justify-content: center; gap: 8px;
                background: transparent; color: rgba(255,255,255,0.8); font-weight: 700;
                border-radius: 14px; padding: 13px 28px; font-size: 15px; line-height: 1;
                border: 1.5px solid rgba(255,255,255,0.15); font-family: 'Manrope', sans-serif;
                transition: all 0.25s ease;
            }
            .btn-ghost:hover { border-color: var(--accent); color: var(--accent); }

            /* Inline flex-row label for x-show toggled content — Alpine's x-show manipulates
               the `display` property directly on the inline style attribute, so a flex layout
               declared via inline style gets silently dropped when toggled. Using a class here
               instead keeps the flex row intact regardless of Alpine's show/hide state. */
            .inline-row { display: flex; align-items: center; justify-content: center; gap: 8px; white-space: nowrap; }
            .flex-center { display: flex; align-items: center; justify-content: center; }

            /* Divider */
            .divider { height: 1px; background: rgba(255,255,255,0.08); }

            /* FAQ accordion */
            .faq-answer { max-height: 0; overflow: hidden; transition: max-height 0.35s ease; }
            .faq-item.open .faq-answer { max-height: 200px; }
            .faq-item.open .faq-chevron { transform: rotate(180deg); }
            .faq-chevron { transition: transform 0.3s ease; }

            /* Shop page layout */
            #shop-layout { display: grid; gap: 32px; align-items: start; }
            @media (min-width: 1024px) { #shop-layout { grid-template-columns: 1fr 340px; } }
            #shop-grid { display: grid; gap: 20px; grid-template-columns: 1fr; }
            @media (min-width: 640px)  { #shop-grid { grid-template-columns: repeat(2, 1fr); } }
            @media (min-width: 1280px) { #shop-grid { grid-template-columns: repeat(3, 1fr); } }
            #shop-cart { position: sticky; top: 90px; }

            /* WhatsApp chat widget */
            #whatsapp-widget {
                position: fixed; right: 20px; bottom: 20px; z-index: 400;
                width: 56px; height: 56px; border-radius: 50%;
                background: #25D366; display: flex; align-items: center; justify-content: center;
                box-shadow: 0 6px 20px rgba(0,0,0,0.35);
                transition: transform 0.2s ease, box-shadow 0.2s ease;
            }
            #whatsapp-widget:hover { transform: scale(1.08); box-shadow: 0 8px 24px rgba(0,0,0,0.45); }
            #whatsapp-widget svg { width: 30px; height: 30px; }
        </style>
    </head>
    <body class="min-h-screen text-white antialiased">

        {{-- Custom cursor --}}
        <div id="cursor-inner"></div>
        <div id="cursor-outer"></div>

        {{-- WhatsApp chat widget --}}
        <a
            id="whatsapp-widget"
            href="https://wa.me/27787965339"
            target="_blank"
            rel="noopener noreferrer"
            aria-label="Chat with us on WhatsApp"
            title="Chat with us on WhatsApp"
        >
            <svg viewBox="0 0 32 32" fill="#fff" xmlns="http://www.w3.org/2000/svg">
                <path d="M16.004 2.667c-7.363 0-13.333 5.97-13.333 13.333 0 2.353.615 4.646 1.784 6.665L2.667 29.333l6.83-1.791a13.27 13.27 0 0 0 6.507 1.708h.006c7.362 0 13.333-5.97 13.333-13.333s-5.977-13.25-13.339-13.25Zm0 24.402h-.005a11.06 11.06 0 0 1-5.636-1.543l-.404-.24-4.052 1.063 1.082-3.951-.264-.406a11.03 11.03 0 0 1-1.69-5.892c0-6.101 4.966-11.067 11.074-11.067 2.958 0 5.738 1.154 7.828 3.246a10.99 10.99 0 0 1 3.241 7.827c0 6.102-4.967 11.063-11.074 11.063Zm6.073-8.287c-.333-.167-1.966-.97-2.271-1.08-.305-.111-.527-.167-.749.167-.222.333-.86 1.08-1.055 1.302-.194.222-.388.25-.72.083-.333-.167-1.406-.518-2.678-1.652-.99-.883-1.659-1.974-1.853-2.307-.194-.334-.021-.514.146-.68.15-.15.333-.389.5-.583.167-.194.222-.333.333-.556.111-.222.056-.417-.028-.583-.083-.167-.749-1.806-1.026-2.473-.27-.65-.545-.562-.748-.572-.194-.01-.416-.012-.638-.012a1.226 1.226 0 0 0-.888.416c-.305.334-1.166 1.14-1.166 2.779 0 1.639 1.194 3.222 1.361 3.445.166.222 2.352 3.593 5.7 5.04.796.344 1.418.549 1.903.703.8.254 1.528.218 2.104.132.642-.096 1.966-.804 2.243-1.581.278-.777.278-1.443.194-1.581-.083-.139-.305-.222-.638-.389Z"/>
            </svg>
        </a>

        {{-- ── HEADER ── --}}
        <header id="site-header">
            <div id="site-header-inner">
                {{-- Logo --}}
                @php
                    $__storeLogo = app(\App\Support\CurrentStore::class)->get()?->logo_url;
                    $__siteLogo = $__storeLogo ?: cache()->remember('setting.logo', 300, fn () => \App\Models\Setting::get('logo', ''));
                @endphp
                <a href="{{ route('home') }}" wire:navigate style="display:flex;align-items:center;gap:10px;text-decoration:none;flex-shrink:0;">
                    @if ($__siteLogo)
                        <img src="{{ $__siteLogo }}" alt="{{ site_name() }}" style="max-width:120px;max-height:48px;object-fit:contain;flex-shrink:0;" />
                    @else
                        <div style="width:32px;height:32px;border-radius:8px;background:#DDF247;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <svg style="width:16px;height:16px;" viewBox="0 0 24 24" fill="#111"><path d="M13 3L4 14h7l-2 7 9-11h-7l2-7z"/></svg>
                        </div>
                    @endif
                </a>

                {{-- Nav --}}
                <nav class="site-nav">
                    <a href="{{ route('home') }}#hero-section" class="nav-link">Home</a>
                    <a href="{{ route('shop') }}" wire:navigate class="nav-link">Shop</a>
                    <a href="{{ route('home') }}#how-it-works" class="nav-link">How It Works</a>
                    <a href="{{ route('home') }}#about" class="nav-link">About</a>
                    <a href="{{ route('home') }}#faq" class="nav-link">FAQ</a>
                </nav>

                {{-- Actions --}}
                <div style="display:flex;align-items:center;gap:10px;flex-shrink:0;">
                    @auth
                        <a href="{{ route('dashboard') }}" wire:navigate class="btn-ghost" style="padding:10px 20px;font-size:14px;">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" wire:navigate class="btn-ghost" style="padding:10px 20px;font-size:14px;">Sign in</a>
                    @endauth
                    <a href="/store" class="btn-primary" style="padding:10px 20px;font-size:14px;">Shop Now</a>
                </div>
            </div>
        </header>

        <main style="padding-top:72px;">
            {{ $slot }}
        </main>

        {{-- ── FOOTER ── --}}
        <footer style="background:#0d0d0d;border-top:1px solid rgba(255,255,255,0.07);padding:80px 0 40px;">
            <div class="sec-inner">
         
                <div class="footer-bottom">
                    <p style="font-size:14px;color:rgba(255,255,255,0.50);font-family:'Manrope',sans-serif;">&copy; {{ date('Y') }} {{ site_name() }}. All rights reserved.</p>
                    
                    <p style="font-size:12px;color:rgba(255,255,255,0.25);font-family:'Azeret Mono',monospace;">Secure payments · Instant delivery . +27 78 796 5339</p>
                </div>
            </div>
        </footer>

        <script src="{{ config('payfast.js_url') }}"></script>
        @fluxScripts

        <script>
            // Custom cursor — event delegation survives wire:navigate morphing
            (function () {
                var ci, co;
                function getRefs() {
                    ci = document.getElementById('cursor-inner');
                    co = document.getElementById('cursor-outer');
                }
                getRefs();
                document.addEventListener('livewire:navigated', getRefs);

                document.addEventListener('mousemove', function (e) {
                    if (!ci) { getRefs(); }
                    if (!ci || !co) { return; }
                    ci.style.left = e.clientX + 'px'; ci.style.top = e.clientY + 'px';
                    co.style.left = e.clientX + 'px'; co.style.top = e.clientY + 'px';
                });
                document.addEventListener('mouseover', function (e) {
                    if (!co) { getRefs(); }
                    if (co && e.target.closest('a, button, [wire\\:click]')) {
                        co.style.width = '52px'; co.style.height = '52px'; co.style.borderColor = 'var(--accent)';
                    }
                });
                document.addEventListener('mouseout', function (e) {
                    if (!co) { getRefs(); }
                    if (co && e.target.closest('a, button, [wire\\:click]')) {
                        co.style.width = '36px'; co.style.height = '36px'; co.style.borderColor = 'rgba(221,242,71,0.5)';
                    }
                });
            })();

            // Smooth scroll for anchor links (works on same page or after navigation)
            function bindSmoothScroll() {
                document.querySelectorAll('a[href*="#"]').forEach(a => {
                    a.addEventListener('click', e => {
                        const hash = a.getAttribute('href').split('#')[1];
                        if (!hash) return;
                        const target = document.getElementById(hash);
                        if (target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth' }); }
                        // No target on this page — let normal navigation proceed to the full URL
                    });
                });
            }
            bindSmoothScroll();
            document.addEventListener('livewire:navigated', bindSmoothScroll);

            // Scroll to hash section after navigating to home page with a #hash
            function scrollToHash() {
                if (window.location.hash) {
                    const target = document.getElementById(window.location.hash.slice(1));
                    if (target) { setTimeout(() => target.scrollIntoView({ behavior: 'smooth' }), 150); }
                }
            }
            scrollToHash();
            document.addEventListener('livewire:navigated', scrollToHash);

            // Spider web canvas + hero parallax
            (function () {
                var hero = document.getElementById('hero-section');
                var canvas = document.getElementById('hero-canvas');
                if (!hero || !canvas) return;

                var ctx = canvas.getContext('2d');
                var pts = [], mx = -9999, my = -9999;
                var N = 80, CONN = 145, REP = 110;

                function resize() {
                    canvas.width  = hero.offsetWidth;
                    canvas.height = hero.offsetHeight;
                }

                function init() {
                    pts = [];
                    for (var i = 0; i < N; i++) {
                        pts.push({
                            x: Math.random() * canvas.width,
                            y: Math.random() * canvas.height,
                            vx: (Math.random() - .5) * .55,
                            vy: (Math.random() - .5) * .55,
                            r: Math.random() * 1.6 + .7
                        });
                    }
                }

                function frame() {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    for (var i = 0; i < pts.length; i++) {
                        var p = pts[i];
                        // mouse repulsion
                        var qx = p.x - mx, qy = p.y - my;
                        var qd = Math.sqrt(qx * qx + qy * qy);
                        if (qd < REP && qd > 0) {
                            var f = (REP - qd) / REP * 1.4;
                            p.vx += qx / qd * f * .45;
                            p.vy += qy / qd * f * .45;
                        }
                        p.vx *= .97; p.vy *= .97;
                        var sp = Math.sqrt(p.vx * p.vx + p.vy * p.vy);
                        if (sp > 2.8) { p.vx = p.vx / sp * 2.8; p.vy = p.vy / sp * 2.8; }
                        p.x += p.vx; p.y += p.vy;
                        if (p.x < 0) p.x = canvas.width;  if (p.x > canvas.width)  p.x = 0;
                        if (p.y < 0) p.y = canvas.height; if (p.y > canvas.height) p.y = 0;
                        ctx.beginPath();
                        ctx.arc(p.x, p.y, p.r, 0, 6.283);
                        ctx.fillStyle = 'rgba(221,242,71,.60)';
                        ctx.fill();
                    }
                    for (var i = 0; i < pts.length; i++) {
                        for (var j = i + 1; j < pts.length; j++) {
                            var dx = pts[i].x - pts[j].x, dy = pts[i].y - pts[j].y;
                            var d = Math.sqrt(dx * dx + dy * dy);
                            if (d < CONN) {
                                ctx.beginPath();
                                ctx.moveTo(pts[i].x, pts[i].y);
                                ctx.lineTo(pts[j].x, pts[j].y);
                                ctx.strokeStyle = 'rgba(221,242,71,' + ((1 - d / CONN) * .25) + ')';
                                ctx.lineWidth = .8;
                                ctx.stroke();
                            }
                        }
                    }
                    requestAnimationFrame(frame);
                }

                resize(); init(); frame();
                window.addEventListener('resize', function () { resize(); init(); });

                hero.addEventListener('mousemove', function (e) {
                    var r = canvas.getBoundingClientRect();
                    mx = e.clientX - r.left;
                    my = e.clientY - r.top;
                    // depth-layer parallax
                    var dx = (mx - canvas.width  / 2) / (canvas.width  / 2);
                    var dy = (my - canvas.height / 2) / (canvas.height / 2);
                    hero.querySelectorAll('[data-depth]').forEach(function (el) {
                        var d = parseFloat(el.dataset.depth);
                        el.style.transform = 'translate(' + (dx * d * 80) + 'px,' + (dy * d * 60) + 'px)';
                    });
                });
                hero.addEventListener('mouseleave', function () {
                    mx = -9999; my = -9999;
                    hero.querySelectorAll('[data-depth]').forEach(function (el) {
                        el.style.transition = 'transform 0.9s ease-out';
                        el.style.transform  = '';
                        setTimeout(function () { el.style.transition = 'transform 0.15s ease-out'; }, 900);
                    });
                });
            })();

            // FAQ accordion
            document.querySelectorAll('.faq-item').forEach(item => {
                item.querySelector('.faq-trigger').addEventListener('click', () => {
                    const isOpen = item.classList.contains('open');
                    document.querySelectorAll('.faq-item').forEach(i => i.classList.remove('open'));
                    if (!isOpen) item.classList.add('open');
                });
            });
        </script>
    </body>
</html>
