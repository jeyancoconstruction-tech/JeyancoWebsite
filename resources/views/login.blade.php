{{-- The sign-in page: Michael's jeyanco-login-loader design.

     Opened fresh — a link or a typed address, not a refresh, the back
     button, a click from inside the app or a refused sign-in coming back —
     it opens on the site being built: a worker walks on, the buildings rise,
     the crane swings in and lowers the JEYANCO sign. Then the drawing shrinks
     into the left panel and stays there as its background, the sign's words
     fly into the logo, and the sign-in panel slides in. Skip goes straight
     to the end. Any other way here shows the end at once.

     A page of its own rather than auth.layout: Forgot password, Set a new
     password and Choose a password keep the layout and the arrival splash
     they have. Everything the form does is as it was — the same fields,
     routes, errors, Caps Lock warning and Google button — and Sign in shows
     a spinner in the button, nothing more. --}}
@php
    $mark = $company?->logo_path ? $company->logoUrl() : asset('images/logo-mark.png');
    $name = $company?->company_name ?? 'Jeyanco Construction';

    // A page that comes back with something to say — a refused sign-in, a
    // password just set — is a continuation, not an arrival.
    $quiet = $errors->any() || session()->has('success');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $company?->company_name ?? 'Jeyanco' }} | Sign In</title>

    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="64x64" href="{{ asset('favicon-64.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicon-180.png') }}">
    {{-- The mark is on screen from the first frame without the intro; asked
         for early, so the ring is never seen empty while it downloads. --}}
    <link rel="preload" as="image" href="{{ $mark }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Big+Shoulders+Stencil+Display:wght@800;900&family=Big+Shoulders+Display:wght@600;700&display=swap" rel="stylesheet">

    {{-- Whether the intro plays, decided before anything is painted so the
         page never shows one state and jumps to the other. Only a navigation
         of the "navigate" kind from outside the site counts — not a reload,
         not back or forward, not a link from inside the app (signing out
         lands here), and not this page coming back with an error on it,
         which is also a "navigate". Nor for anyone who asked for less motion.
         Without the intro the page opens on its finished state. --}}
    <script>
        (function () {
            var d = document.documentElement, play = false;
            try {
                var nav = performance.getEntriesByType('navigation')[0];
                var internal = false;
                try { internal = !!document.referrer && new URL(document.referrer).origin === location.origin; } catch (e) {}
                var still = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
                play = !!nav && nav.type === 'navigate' && !internal && !still && !@json($quiet);
            } catch (e) {}
            d.classList.add(play ? 'intro' : 'in');
            if (!play) d.classList.add('landed');
        })();
    </script>

    <style>
        :root {
            color-scheme: dark;
            --bg: #070e1c;
            --grid: rgba(96,150,230,.08);
            --grid2: rgba(96,150,230,.15);
            --ln: #5b93e0;
            --ln-soft: rgba(91,147,224,.45);
            --ink: #e8eef8;
            --muted: #8fa3c2;
            --soft: #9aa9c0;
            --hat: #f2b84b;
            --danger: #F08A8A;
            --success: #5FCB9F;
            --warn: #E7C07A;
            --control: 52px;
            --sans: "Manrope", ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
            --stencil: "Big Shoulders Stencil Display", "Arial Narrow", Impact, sans-serif;
            --cond: "Big Shoulders Display", "Arial Narrow", sans-serif;
        }
        * { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; background: var(--bg); }
        body { color: var(--ink); font-family: var(--sans); -webkit-font-smoothing: antialiased; }
        /* On a desktop the page is the screen: nothing scrolls. */
        @media (min-width: 901px) { html, body { height: 100dvh; overflow: hidden; } }
        a { color: #7fb0ff; font-weight: 700; text-decoration: none; }
        a:hover { color: #a9cbf0; }
        :focus-visible { outline: 2px solid var(--ln); outline-offset: 2px; }
        .i { fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; flex-shrink: 0; }
        .sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }

        /* ── The site being built ─────────────────────────────────────────
           Full screen while the intro plays; afterwards — and from the start
           when it does not play — the finished drawing sits shrunk into the
           left panel as its background, its windows still blinking. */
        .stage {
            position: fixed; inset: 0; z-index: 1; overflow: hidden;
            background: radial-gradient(120% 90% at 30% 15%, #123166 0%, #0b1a33 45%, #070e1c 100%);
        }
        .bp {
            position: absolute; inset: 0;
            background-image:
                linear-gradient(var(--grid) 1px, transparent 1px), linear-gradient(90deg, var(--grid) 1px, transparent 1px),
                linear-gradient(var(--grid2) 1px, transparent 1px), linear-gradient(90deg, var(--grid2) 1px, transparent 1px);
            background-size: 24px 24px, 24px 24px, 120px 120px, 120px 120px;
        }
        svg.scene {
            position: absolute; inset: 0; width: 100%; height: 100%;
            transform-origin: 0 100%;
            transition: opacity 1s ease, transform 1.3s cubic-bezier(.76,0,.24,1);
        }
        .in svg.scene { opacity: .5; transform: translate(-1%, -1%) scale(.5); }
        svg.scene .title, svg.scene .hud { transition: opacity .45s ease; }
        .in svg.scene .title, .in svg.scene .hud { opacity: 0; }

        .st { fill: none; stroke: var(--ln); stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
        .sf { fill: none; stroke: var(--ln-soft); stroke-width: 1.2; }
        .draw { stroke-dasharray: 1; stroke-dashoffset: 1; animation: draw 1s cubic-bezier(.6,0,.3,1) forwards; }
        @keyframes draw { to { stroke-dashoffset: 0; } }
        .ground { animation-duration: 1.2s; }

        .walker { animation: walkIn 2.2s cubic-bezier(.35,.1,.25,1) .2s both; }
        @keyframes walkIn { from { transform: translateX(-360px); } to { transform: translateX(0); } }
        .bob { animation: bob .44s ease-in-out .2s 5; }
        @keyframes bob { 50% { transform: translateY(-3px); } }
        .limb { transform-box: fill-box; transform-origin: 50% 0; }
        .legL { animation: stepA .44s ease-in-out .2s 5; }
        .legR { animation: stepB .44s ease-in-out .2s 5; }
        .armL { animation: stepB .44s ease-in-out .2s 5; }
        @keyframes stepA { 25% { transform: rotate(24deg); } 75% { transform: rotate(-24deg); } }
        @keyframes stepB { 25% { transform: rotate(-24deg); } 75% { transform: rotate(24deg); } }
        .armR { animation: stepA .44s ease-in-out .2s 5, point .6s cubic-bezier(.3,1.4,.5,1) 2.5s forwards; }
        @keyframes point { to { transform: rotate(-118deg); } }
        .hat { fill: var(--hat); stroke: none; }
        .vest { stroke: var(--hat); stroke-width: 2.4; }
        .shadow { fill: rgba(0,0,0,.35); transform-box: fill-box; transform-origin: center; animation: walkIn 2.2s cubic-bezier(.35,.1,.25,1) .2s both; }

        .bld { transform-box: fill-box; transform-origin: 50% 100%; transform: scaleY(0); animation: rise .9s cubic-bezier(.2,.8,.2,1) forwards; }
        @keyframes rise { to { transform: scaleY(1); } }
        .b1 { animation-delay: 2.7s; } .b2 { animation-delay: 2.95s; } .b3 { animation-delay: 3.2s; } .b4 { animation-delay: 3.45s; }
        .bld .win { opacity: 0; animation: fadeIn .5s ease forwards; }
        .b1 .win { animation-delay: 3.4s; } .b2 .win { animation-delay: 3.65s; } .b3 .win { animation-delay: 3.9s; } .b4 .win { animation-delay: 4.15s; }
        .lit { fill: rgba(111,163,234,.22); opacity: 0; animation: blink 2.4s ease-in-out 4.3s infinite; }
        .lit.l2 { animation-delay: 4.9s; } .lit.l3 { animation-delay: 5.4s; }
        @keyframes blink { 0%, 100% { opacity: 0; } 30%, 70% { opacity: 1; } }
        @keyframes fadeIn { to { opacity: 1; } }

        .mast { animation-delay: 2.5s; animation-duration: 1s; }
        .jib { transform-box: fill-box; transform-origin: 100% 50%; transform: rotate(-70deg); opacity: 0; animation: jib 1.1s cubic-bezier(.3,1.2,.4,1) 3.2s forwards; }
        @keyframes jib { to { transform: rotate(0); opacity: 1; } }
        .hook { animation: hook 2.6s ease-in-out 4.3s infinite alternate; }
        @keyframes hook { from { transform: translateY(0); } to { transform: translateY(26px); } }

        .t1 { font-family: var(--stencil); font-weight: 900; font-size: 108px; letter-spacing: 14px; fill: #eef4fd; }
        .t2 { font-family: var(--cond); font-weight: 700; font-size: 25px; letter-spacing: 13px; fill: #9db8e0; }
        .sign { transform: translateY(-460px); animation: lower 1.5s cubic-bezier(.3,.05,.3,1) 3.7s forwards; }
        @keyframes lower { 0% { transform: translateY(-460px); } 78% { transform: translateY(10px); } 90% { transform: translateY(-4px); } 100% { transform: translateY(0); } }
        .swing { transform-box: view-box; transform-origin: 600px -60px; animation: swingSign 2.4s cubic-bezier(.4,0,.2,1) 5.1s both; }
        @keyframes swingSign { 0% { transform: rotate(1.6deg); } 25% { transform: rotate(-1.1deg); } 50% { transform: rotate(.6deg); } 75% { transform: rotate(-.25deg); } 100% { transform: rotate(0); } }
        .board { fill: rgba(12,32,70,.55); stroke: #6fa3ea; stroke-width: 1.6; }
        .board-in { fill: none; stroke: rgba(111,163,234,.35); stroke-width: 1; stroke-dasharray: 5 4; }
        .cable { stroke: #8fb6ec; stroke-width: 1.4; }
        .bolt { fill: #0b1a33; stroke: #8fb6ec; stroke-width: 1.2; }
        .hookc { fill: none; stroke: #8fb6ec; stroke-width: 2; stroke-linecap: round; }
        .stripe { fill: #f2b84b; }
        .rule { stroke: var(--ln); stroke-width: 1.2; }
        .shine { fill: url(#shineGrad); opacity: 0; animation: shineSweep 1.1s ease 5.4s forwards; }
        @keyframes shineSweep { 0% { opacity: .9; transform: translateX(-520px); } 100% { opacity: .9; transform: translateX(520px); } }

        .status { opacity: 0; animation: fadeIn .5s ease 6.2s forwards; }
        .status text { font-family: var(--sans); font-size: 15px; fill: var(--muted); font-weight: 500; }
        .dot { fill: var(--ln); animation: dot 1.1s ease-in-out infinite; }
        .dot.d2 { animation-delay: .15s; } .dot.d3 { animation-delay: .3s; }
        @keyframes dot { 0%, 80%, 100% { opacity: .25; } 40% { opacity: 1; } }
        .bar-bg { fill: rgba(91,147,224,.18); }
        .bar { fill: var(--ln); transform-box: fill-box; transform-origin: 0 50%; transform: scaleX(0); animation: bar 6.4s cubic-bezier(.4,0,.2,1) .2s forwards; }
        @keyframes bar { to { transform: scaleX(1); } }

        /* No intro: every one-off step of the build is already over, so the
           drawing opens finished. Only the blinking windows and the swinging
           hook — the living part of the background — go on. */
        html:not(.intro) svg.scene :is(.draw, .walker, .bob, .limb, .shadow, .bld, .win, .jib, .sign, .swing, .shine, .status, .bar) {
            animation-delay: -30s !important;
        }

        /* Skip: only while the intro plays, and plain to see. It goes
           straight to the finished page (see show(true) below). */
        .skip {
            position: fixed; right: 20px; top: calc(20px + env(safe-area-inset-top, 0px)); z-index: 4;
            display: none; align-items: center; gap: 8px; height: 38px; padding: 0 14px 0 16px;
            border: 1px solid rgba(127,176,255,.3); border-radius: 999px; cursor: pointer;
            background: rgba(8,20,44,.62); -webkit-backdrop-filter: blur(8px); backdrop-filter: blur(8px);
            font: 700 13px var(--sans); letter-spacing: .02em; color: #d6e4f7;
            transition: background .15s, border-color .15s, color .15s;
        }
        .skip:hover { border-color: #7fb0ff; background: rgba(20,44,90,.72); color: #fff; }
        .skip kbd { padding: 1px 6px; border: 1px solid rgba(127,176,255,.3); border-radius: 5px; font: 600 10.5px var(--sans); color: var(--muted); }
        .intro:not(.in) .skip { display: inline-flex; }

        /* The jump itself: for the one frame it takes to land on the finished
           page, nothing transitions — the panel does not slide, the drawing
           does not shrink, the words do not arrive. It is simply there. */
        .snap *, .snap *::before, .snap *::after { transition: none !important; }

        /* ── The sign-in page ──────────────────────────────────────────── */
        .login {
            position: fixed; inset: 0; z-index: 2;
            display: grid; grid-template-columns: minmax(0, 53fr) minmax(0, 47fr); grid-template-rows: minmax(0, 1fr);
            pointer-events: none;
        }
        .in .login { pointer-events: auto; }

        /* The seam sweeps in and becomes the divider as the panel follows it. */
        .seam {
            position: fixed; top: 0; bottom: 0; left: 100%; width: 2px; z-index: 3; pointer-events: none; opacity: 0;
            background: linear-gradient(180deg, transparent, #7fb0ff 20%, #bfd8ff 50%, #7fb0ff 80%, transparent);
            box-shadow: 0 0 18px 4px rgba(91,147,224,.55);
        }
        .intro.in .seam { animation: seam 1.3s cubic-bezier(.76,0,.24,1) forwards, seamOut .9s ease 1.35s forwards; }
        @keyframes seam { 0% { left: 100%; opacity: 1; } 100% { left: 53%; opacity: 1; } }
        @keyframes seamOut { to { opacity: 0; } }

        .lp { position: relative; overflow: hidden; min-height: 0; padding: 48px 56px; display: flex; flex-direction: column; gap: clamp(18px, 3vh, 32px); container-type: inline-size; }
        .lp::before {
            content: ""; position: absolute; inset: 0; opacity: 0; transition: opacity 1s ease .9s;
            background: linear-gradient(180deg, rgba(7,16,36,.8) 0%, rgba(7,16,36,.55) 55%, rgba(7,16,36,.35) 85%, rgba(7,16,36,.15) 100%);
        }
        .in .lp::before { opacity: 1; }
        .lp > * { position: relative; z-index: 1; }
        .lp .bp { display: none; }

        .lbrand { display: flex; gap: 14px; align-items: center; }
        .lbrand b { display: block; width: max-content; font-family: var(--stencil); font-weight: 900; font-size: clamp(22px, min(3.8cqi, 3.4vh), 32px); line-height: 1; letter-spacing: .12em; }
        .lbrand small { display: block; margin-top: 4px; font-family: var(--cond); font-weight: 700; font-size: clamp(10.5px, min(1.65cqi, 1.5vh), 14px); letter-spacing: .42em; color: #a9bbd9; }
        .lbrand b, .lbrand small { opacity: 0; }
        .landed .lbrand b, .landed .lbrand small { opacity: 1; }

        /* The logo: our mark, with a ring that draws itself round it. */
        .lgo { --lgo: clamp(50px, min(8.4cqi, 7.4vh), 68px); position: relative; width: var(--lgo); height: var(--lgo); flex: none; }
        .lgo img { position: absolute; inset: 9%; width: 82%; height: 82%; border-radius: 50%; object-fit: cover; }
        .lgo svg { position: absolute; inset: 0; width: 100%; height: 100%; overflow: visible; }
        .lgo .ring { fill: none; stroke: #8cc0ff; stroke-width: 2; stroke-dasharray: 1; stroke-dashoffset: 0; }
        .lgo .tick { stroke: #8cc0ff; stroke-width: 1.5; opacity: 0; }
        .intro:not(.in) .lgo { visibility: hidden; }
        .intro .lgo .ring { stroke-dashoffset: 1; }
        .intro .lgo img { opacity: 0; transform: scale(.6); }
        .intro.in .lgo .ring { animation: draw 1s cubic-bezier(.6,0,.3,1) 1.35s forwards; }
        .intro.in .lgo .tick { animation: fadeIn .2s ease 1.3s forwards, tickOut .4s ease 2.2s forwards; }
        .intro.in .lgo img { animation: disc .7s cubic-bezier(.2,1.3,.4,1) 1.9s forwards; }
        @keyframes disc { to { opacity: 1; transform: none; } }
        @keyframes tickOut { to { opacity: 0; } }

        /* Sized to the panel, not to a fixed scale: the headline as wide as
           the panel allows (its longest line, "Every peso accounted for.",
           is about 12.4 font-sizes long, so 7.6cqi keeps it on one line) and
           no taller than the screen allows, so the words stay clear of the
           drawing under them. The lead, the list and the logo follow. */
        .pitch { display: flex; flex-direction: column; gap: clamp(14px, 2.6vh, 28px); margin-top: 0; }
        .lp h1 { margin: 0; font-size: clamp(30px, min(7.6cqi, 7.6vh), 80px); line-height: 1.08; font-weight: 800; letter-spacing: -.02em; text-wrap: balance; --base: 3.25s; --step: 60ms; }
        .lp h1 .w { margin-right: .22em; }
        .lead { margin: clamp(10px, 1.6vh, 18px) 0 0; max-width: 46ch; font-size: clamp(15px, min(2.6cqi, 2.3vh), 21px); line-height: 1.6; color: #b7c6de; }
        .feats { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: clamp(8px, 1.5vh, 16px); }
        .feats li { display: flex; gap: .85em; align-items: center; font-size: clamp(14px, min(2.4cqi, 2.1vh), 19px); }
        .feats .ic svg { width: 1.15em; height: 1.15em; }
        .feats .ic { width: 2.55em; height: 2.55em; flex: none; display: grid; place-items: center; border-radius: .62em; border: 1px solid rgba(111,163,234,.3); background: rgba(20,50,100,.45); color: #9cc0f2; }

        .rp {
            position: relative; min-height: 0; overflow-y: auto;
            display: flex; flex-direction: column; padding: 28px 56px;
            background: linear-gradient(160deg, rgba(14,28,56,.55), rgba(8,16,34,.72));
            -webkit-backdrop-filter: blur(18px) saturate(140%); backdrop-filter: blur(18px) saturate(140%);
            border-left: 1px solid rgba(127,176,255,.14);
            box-shadow: -30px 0 60px rgba(0,0,0,.25);
            transform: translateX(100%); transition: transform 1.3s cubic-bezier(.76,0,.24,1);
        }
        .in .rp { transform: none; }
        .rtop { text-align: right; font-size: 13.5px; color: var(--soft); }
        .rbody { margin: auto; width: 100%; max-width: 452px; display: flex; flex-direction: column; gap: 18px; padding-block: 24px; }

        /* The form on a card of its own: one clear place to look, with the
           heading, the fields and the two ways in held together. */
        .card {
            display: flex; flex-direction: column; gap: 22px; padding: 34px 34px 30px;
            border: 1px solid rgba(127,176,255,.15); border-radius: 18px;
            background: linear-gradient(180deg, rgba(16,32,64,.74), rgba(9,18,38,.78));
            box-shadow: inset 0 1px 0 rgba(255,255,255,.05), 0 30px 70px -24px rgba(0,0,0,.6);
        }
        .card-head { display: flex; flex-direction: column; gap: 10px; }
        .card-head h2 { margin: 0; font-size: 34px; font-weight: 800; line-height: 1.12; letter-spacing: -.02em; }
        .card-head .sub { margin: 0; font-size: 15.5px; line-height: 1.55; color: var(--soft); }
        form { display: flex; flex-direction: column; gap: 18px; }

        .alert { display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px; border-radius: 10px; font-size: 13.5px; font-weight: 600; line-height: 1.45; }
        .alert-error   { background: rgba(240,138,138,.1); border: 1px solid rgba(240,138,138,.28); color: var(--danger); }
        .alert-success { background: rgba(95,203,159,.1);  border: 1px solid rgba(95,203,159,.28);  color: var(--success); }

        .fld { display: flex; flex-direction: column; gap: 7px; }
        .fld label { font-size: 13px; font-weight: 600; letter-spacing: .01em; color: #c9d4e6; }
        .inp {
            display: flex; align-items: center; gap: 11px; height: var(--control); padding: 0 6px 0 14px;
            border: 1px solid rgba(148,178,230,.2); border-radius: 11px; background: rgba(6,14,30,.55);
            transition: border-color .2s, box-shadow .2s, background .2s;
        }
        .inp:hover { border-color: rgba(148,178,230,.34); }
        .inp:focus-within { border-color: #5b93e0; background: rgba(10,24,52,.7); box-shadow: 0 0 0 3px rgba(79,134,214,.22); }
        .inp:has(input.invalid) { border-color: rgba(240,138,138,.6); }
        .inp:has(input.invalid):focus-within { box-shadow: 0 0 0 3px rgba(240,138,138,.16); }
        .inp > .i { color: #7d8fab; }
        .inp input { flex: 1; min-width: 0; height: 100%; border: 0; outline: 0; background: transparent; color: var(--ink); font: 500 15px var(--sans); }
        .inp input::placeholder { color: #6b7c98; }
        .inp input:focus-visible { outline: 0; }
        /* Chrome paints its own near-white block over an autofilled box. */
        .inp input:-webkit-autofill,
        .inp input:-webkit-autofill:hover,
        .inp input:-webkit-autofill:focus {
            -webkit-text-fill-color: var(--ink);
            -webkit-box-shadow: 0 0 0 1000px #0c1a33 inset;
            caret-color: var(--ink);
        }
        .eye { width: 38px; height: 38px; flex: none; display: grid; place-items: center; border: 0; border-radius: 8px; background: none; color: #7d8fab; cursor: pointer; }
        .eye:hover { background: rgba(127,176,255,.08); color: var(--ink); }
        .eye .off { display: none; }
        .eye[aria-pressed="true"] .on  { display: none; }
        .eye[aria-pressed="true"] .off { display: block; }
        .field-error { display: flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 600; color: var(--danger); }
        .caps { display: none; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 600; color: var(--warn); }
        .caps.show { display: flex; }

        .row { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-top: -2px; font-size: 13.5px; }
        .row a { font-weight: 700; }
        .remember { display: flex; gap: 10px; align-items: center; color: #c9d4e6; cursor: pointer; user-select: none; }
        /* The box drawn to match the fields rather than left to the browser. */
        .remember input {
            appearance: none; -webkit-appearance: none; display: grid; place-content: center;
            width: 18px; height: 18px; margin: 0; flex: none; cursor: pointer;
            border: 1.5px solid rgba(148,178,230,.45); border-radius: 5px; background: rgba(6,14,30,.6);
            transition: background .15s, border-color .15s;
        }
        .remember input::after {
            content: ""; width: 9px; height: 5px; margin-top: -2px;
            border-left: 2px solid #fff; border-bottom: 2px solid #fff;
            transform: rotate(-45deg) scale(0); transition: transform .12s ease;
        }
        .remember input:hover { border-color: #7fb0ff; }
        .remember input:checked { background: #2f6fc0; border-color: #2f6fc0; }
        .remember input:checked::after { transform: rotate(-45deg) scale(1); }
        .remember input:focus-visible { outline: 2px solid var(--ln); outline-offset: 2px; }

        .signin {
            position: relative; overflow: hidden; height: var(--control);
            display: flex; gap: 10px; align-items: center; justify-content: center;
            margin-top: 2px; border: 1px solid rgba(160,200,255,.28); border-radius: 11px; cursor: pointer;
            background: linear-gradient(180deg, #2f72cf, #2259b0);
            color: #fff; font: 700 15px var(--sans); letter-spacing: .01em;
            box-shadow: inset 0 1px 0 rgba(255,255,255,.18), 0 12px 28px -10px rgba(37,99,184,.75);
            transition: background .2s, transform .1s, box-shadow .2s;
        }
        .signin:hover { background: linear-gradient(180deg, #3a7fdd, #2663c2); box-shadow: inset 0 1px 0 rgba(255,255,255,.2), 0 14px 32px -10px rgba(37,99,184,.9); }
        .signin:active { transform: translateY(1px); }
        .signin .arrow { transition: transform .18s cubic-bezier(.2,.8,.2,1); }
        .signin:hover .arrow { transform: translateX(2px); }
        /* Signing in: the spinner, and only the spinner. */
        .signin .spin { display: none; width: 20px; height: 20px; border-radius: 50%; border: 2px solid rgba(255,255,255,.35); border-top-color: #fff; animation: sp .8s linear infinite; }
        .signin.loading { pointer-events: none; }
        .signin.loading .label, .signin.loading .arrow { display: none; }
        .signin.loading .spin { display: block; }
        @keyframes sp { to { transform: rotate(360deg); } }
        .intro.in .signin::after {
            content: ""; position: absolute; inset: 0; transform: translateX(-100%);
            background: linear-gradient(100deg, transparent 30%, rgba(255,255,255,.35) 50%, transparent 70%);
            animation: shine 1.1s ease 2.1s;
        }
        @keyframes shine { to { transform: translateX(100%); } }

        .or { display: flex; align-items: center; gap: 14px; color: #6b7c98; font-size: 11.5px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; }
        .or::before, .or::after { content: ""; flex: 1; height: 1px; background: rgba(127,176,255,.16); }

        /* The Google button, as it was: the second way in, quieter than
           Sign in. Only its height follows the other controls. */
        .google {
            height: var(--control); width: 100%; border-radius: 12px;
            border: 1px solid rgba(255,255,255,.14); background: #0F1629; color: #E6EDF7;
            font: 700 15px var(--sans);
            display: flex; align-items: center; justify-content: center; gap: 14px;
            transition: background .15s, border-color .15s, transform .08s;
        }
        .google:hover { background: #131C33; border-color: rgba(255,255,255,.24); color: #E6EDF7; }
        .google:active { transform: translateY(1px); }

        /* The foot of the card: centred under a hairline, one short line. */
        .notice {
            display: flex; gap: 8px; align-items: center; justify-content: center; margin: 0; padding-top: 18px;
            border-top: 1px solid rgba(127,176,255,.1);
            color: #8e9db6; font-size: 13px; font-weight: 600; line-height: 1.4; text-align: center;
        }
        .notice .i { color: #34d399; }
        .rfoot { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; font-size: 12.5px; color: #7d8fab; }
        .rfoot a { color: var(--soft); font-weight: 500; }

        /* The words of the heading arrive one at a time after the intro. */
        .ch, .w { display: inline-block; }
        .intro .w { opacity: 0; transform: translateY(10px); filter: blur(4px); }
        .intro.in .w {
            opacity: 1; transform: none; filter: none;
            transition: opacity .5s ease, transform .7s cubic-bezier(.16,1,.3,1), filter .5s ease;
            transition-delay: calc(var(--k) * var(--step) + var(--base));
        }

        /* Everything else in its turn — only after the intro. Without it the
           page is simply there. */
        .intro .enter { opacity: 0; transform: translateY(18px); filter: blur(6px); }
        .intro .rp .enter { transform: translateX(34px); }
        .intro.in .enter {
            opacity: 1; transform: none; filter: none;
            transition: opacity .8s ease, transform 1s cubic-bezier(.16,1,.3,1), filter .8s ease;
            transition-delay: calc(var(--i, 0) * 70ms + 1.25s);
        }
        .intro.in .rp .enter { transition-delay: calc(var(--i, 0) * 70ms + .75s); }
        .intro.in .lp .enter { transition-delay: calc(var(--i, 0) * 110ms + 3s); }

        .fly { position: fixed; inset: 0; z-index: 5; pointer-events: none; }

        /* A shorter screen: the same page, closer together. */
        @media (min-width: 901px) and (max-height: 820px) {
            :root { --control: 46px; }
            .lp { padding: 36px 48px; }
            .pitch { margin-top: 1vh; }
            .rp { padding: 22px 48px; }
            .rbody { gap: 12px; padding-block: 10px; }
            .card { gap: 16px; padding: 24px 28px 22px; }
            .card-head { gap: 6px; }
            .card-head h2 { font-size: 28px; }
            .card-head .sub { font-size: 14.5px; }
            form { gap: 13px; }
        }
        @media (min-width: 901px) and (max-height: 680px) {
            .feats { display: none; }
            .notice { display: none; }
        }

        /* ── A large screen: the words take the room it has ─────────────────
           On a big desktop the page ran small inside a lot of space — a
           54px headline in a 900px panel, a 450px form in one as wide. The
           headline follows its panel's width (cqi: the longest line, "Every
           peso accounted for.", is about 12.4 font-sizes long, so 7.6cqi
           keeps it on one line), and the form, its card and everything on
           it grow with it. Only where there is height for it too; a shorter
           screen keeps the compact sizes above. */
        @media (min-width: 1500px) and (min-height: 821px) {
            :root { --control: 58px; }
            .lp { padding: 44px 64px; }
            .lbrand { gap: 16px; }

            .rp { padding: 32px 64px; }
            .rtop { font-size: 15px; }
            .rbody { max-width: 548px; gap: 22px; padding-block: 14px; }
            .card { gap: 24px; padding: 38px 46px 32px; border-radius: 20px; }
            .card-head h2 { font-size: 44px; }
            .card-head .sub { font-size: 18px; }
            form { gap: 21px; }
            .fld { gap: 9px; }
            .fld label { font-size: 15px; }
            .inp { gap: 13px; padding-left: 16px; }
            .inp > .i { width: 20px; height: 20px; }
            .inp input { font-size: 16.5px; }
            .row { font-size: 15px; }
            .remember input { width: 20px; height: 20px; }
            .signin { font-size: 17px; }
            .signin .arrow { width: 20px; height: 20px; }
            .or { font-size: 12.5px; }
            .google { font-size: 16.5px; }
            .google svg { width: 20px; height: 20px; }
            .notice { font-size: 14px; }
            .notice .i { width: 17px; height: 17px; }
            .rfoot { font-size: 14px; }
        }

        /* ── A phone: one column, the brand over the form ────────────────── */
        @media (max-width: 900px) {
            .intro.in .stage { animation: stageFade .8s ease .3s forwards; }
            html:not(.intro) .stage { display: none; }
            .login {
                position: relative; inset: auto; min-height: 100dvh;
                grid-template-columns: minmax(0, 1fr); grid-template-rows: auto 1fr;
                background: #0a1120;
            }
            .intro .login { opacity: 0; transition: opacity .6s ease .3s; }
            .intro.in .login { opacity: 1; }
            .lp { padding: 32px 20px; gap: 24px; background: radial-gradient(110% 80% at 25% 20%, #133a78 0%, #0c2350 40%, #08142b 100%); }
            .lp .bp { display: block; }
            .lp::before { display: none; }
            .pitch { margin-top: 0; }
            .feats { display: none; }
            .rp { transform: none; overflow: visible; padding: 20px; border-left: 0; box-shadow: none; }
            .intro .rp .enter { transform: translateY(18px); }
            .rbody { padding-block: 8px; }
            .card { padding: 24px 18px 20px; border-radius: 16px; }
            .seam { display: none; }
        }
        @keyframes stageFade { to { opacity: 0; visibility: hidden; } }
        @media (max-width: 480px) {
            .card-head h2 { font-size: 28px; }
            .rfoot { flex-direction: column; align-items: center; gap: 6px; }
        }

        @media (prefers-reduced-motion: reduce) {
            .lit, .hook, .dot { animation: none; }
            .signin .spin { animation-duration: 1.6s; }
            svg.scene, .rp, .signin, .signin .arrow, .inp, .google { transition: none; }
        }
    </style>
</head>
<body>

<div class="stage" id="stage" aria-hidden="true">
    <div class="bp"></div>
    <svg class="scene" viewBox="0 0 1200 675" preserveAspectRatio="xMidYMid meet" focusable="false">
        <defs>
            <linearGradient id="shineGrad" x1="0" x2="1"><stop offset="0" stop-color="#fff" stop-opacity="0"/><stop offset=".5" stop-color="#cfe3ff" stop-opacity=".22"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></linearGradient>
            <clipPath id="boardClip"><rect x="340" y="62" width="520" height="164" rx="6"/></clipPath>
        </defs>

        {{-- The site sign, lowered on the crane's cables. --}}
        <g class="title">
            <g class="sign"><g class="swing">
                <line class="cable" x1="600" y1="-80" x2="600" y2="4"/>
                <path class="hookc" d="M600 4v8a7 7 0 1 1-7 7"/>
                <line class="cable" x1="600" y1="20" x2="356" y2="62"/>
                <line class="cable" x1="600" y1="20" x2="844" y2="62"/>
                <rect class="board" x="340" y="62" width="520" height="164" rx="6"/>
                <rect class="board-in" x="350" y="72" width="500" height="144" rx="3"/>
                <g clip-path="url(#boardClip)">
                    <path class="stripe" d="M340 214h520v12H340z" opacity=".9"/>
                    <path d="M352 214l12 12M376 214l12 12M400 214l12 12M424 214l12 12M448 214l12 12M472 214l12 12M496 214l12 12M520 214l12 12M544 214l12 12M568 214l12 12M592 214l12 12M616 214l12 12M640 214l12 12M664 214l12 12M688 214l12 12M712 214l12 12M736 214l12 12M760 214l12 12M784 214l12 12M808 214l12 12M832 214l12 12" stroke="#0b1a33" stroke-width="5"/>
                    <rect class="shine" x="340" y="62" width="160" height="164"/>
                </g>
                <circle class="bolt" cx="356" cy="62" r="4"/><circle class="bolt" cx="844" cy="62" r="4"/>
                <text class="t1" x="600" y="160" text-anchor="middle" textLength="440" lengthAdjust="spacingAndGlyphs">JEYANCO</text>
                <line class="rule" x1="420" y1="176" x2="780" y2="176"/>
                <text class="t2" x="600" y="203" text-anchor="middle" textLength="380" lengthAdjust="spacingAndGlyphs">CONSTRUCTION</text>
            </g></g>
        </g>

        {{-- Ground --}}
        <line class="st draw ground" pathLength="1" x1="60" y1="560" x2="1140" y2="560"/>
        <line class="sf draw ground" pathLength="1" x1="60" y1="568" x2="1140" y2="568" style="animation-delay:.2s"/>

        {{-- Buildings --}}
        <g class="bld b1">
            <rect class="st" x="560" y="360" width="104" height="200"/>
            <g class="win"><path class="sf" d="M560 400h104M560 440h104M560 480h104M560 520h104M595 360v200M629 360v200"/><rect class="lit" x="597" y="402" width="30" height="36"/></g>
        </g>
        <g class="bld b2">
            <rect class="st" x="684" y="420" width="84" height="140"/>
            <g class="win"><path class="sf" d="M684 455h84M684 490h84M684 525h84M712 420v140M740 420v140"/><rect class="lit l2" x="742" y="457" width="24" height="31"/></g>
        </g>
        <g class="bld b3">
            <rect class="st" x="788" y="290" width="124" height="270"/>
            <path class="st" d="M788 290l62-26 62 26"/>
            <g class="win"><path class="sf" d="M788 330h124M788 370h124M788 410h124M788 450h124M788 490h124M788 530h124M829 290v270M871 290v270"/><rect class="lit l3" x="831" y="372" width="38" height="36"/><rect class="lit" x="873" y="452" width="37" height="36"/></g>
        </g>
        <g class="bld b4">
            <rect class="st" x="932" y="400" width="96" height="160"/>
            <g class="win"><path class="sf" d="M932 440h96M932 480h96M932 520h96M964 400v160M996 400v160"/><rect class="lit l2" x="934" y="482" width="28" height="36"/></g>
        </g>

        {{-- Crane --}}
        <path class="st draw mast" pathLength="1" d="M1072 560V250M1092 560V250M1072 270l20 20M1092 290l-20 20M1072 310l20 20M1092 330l-20 20M1072 350l20 20M1092 370l-20 20M1072 390l20 20M1092 410l-20 20M1072 430l20 20M1092 450l-20 20M1072 470l20 20M1092 490l-20 20M1072 510l20 20M1092 530l-20 20"/>
        <g class="jib">
            <path class="st" d="M820 250h310M820 250l252-34 58 34M1072 216v34M860 250l10-12M900 250l10-12M940 250l10-12M980 250l10-12M1020 250l10-12"/>
            <rect class="st" x="1106" y="250" width="26" height="16"/>
            <g class="hook"><line class="st" x1="880" y1="250" x2="880" y2="300"/><path class="st" d="M872 300h16l-4 12h-8z"/></g>
        </g>

        {{-- The worker, who walks on and points at the site. --}}
        <g transform="translate(400 560) scale(1.7) translate(-420 -560)">
            <ellipse class="shadow" cx="420" cy="562" rx="26" ry="5"/>
            <g class="walker">
                <g class="bob">
                    <g class="limb armL"><path class="st" d="M420 470l-4 42"/><circle cx="416" cy="514" r="4" fill="#0b1a33" stroke="#5b93e0" stroke-width="2"/></g>
                    <g class="limb legL"><path class="st" d="M420 510l-2 50"/><path class="st" d="M418 560h14"/></g>
                    <g class="limb legR"><path class="st" d="M420 510l2 50"/><path class="st" d="M422 560h14"/></g>
                    <path d="M408 470h24l-2 42h-20z" fill="#0f2649" stroke="#5b93e0" stroke-width="2" stroke-linejoin="round"/>
                    <path class="vest" d="M411 494h18"/>
                    <circle cx="420" cy="452" r="11" fill="#0f2649" stroke="#5b93e0" stroke-width="2"/>
                    <path class="hat" d="M407 447a13 12 0 0 1 26 0z"/>
                    <path d="M404 447h32" stroke="#f2b84b" stroke-width="3" stroke-linecap="round"/>
                    <g class="limb armR"><path class="st" d="M424 472l4 40"/><circle cx="428" cy="514" r="4" fill="#0b1a33" stroke="#5b93e0" stroke-width="2"/></g>
                </g>
            </g>
        </g>

        {{-- Status and progress --}}
        <g class="hud">
            <g class="status">
                <text x="600" y="620" text-anchor="middle">{{ __('Loading Jeyanco Payroll') }}</text>
                <circle class="dot" cx="712" cy="615" r="2.5"/><circle class="dot d2" cx="721" cy="615" r="2.5"/><circle class="dot d3" cx="730" cy="615" r="2.5"/>
            </g>
            <rect class="bar-bg" x="480" y="640" width="240" height="3" rx="1.5"/>
            <rect class="bar" x="480" y="640" width="240" height="3" rx="1.5"/>
        </g>
    </svg>
</div>

<button class="skip" id="skip" type="button" aria-label="{{ __('Skip the intro') }}">
    {{ __('Skip intro') }}
    <svg class="i" width="14" height="14" viewBox="0 0 24 24" style="stroke-width:2.2"><path d="M5 5l7 7-7 7M13 5l7 7-7 7"/></svg>
    <kbd>Esc</kbd>
</button>
<div class="seam" aria-hidden="true"></div>

<div class="login" id="login">
    {{-- ── What the system is ─────────────────────────────────────────── --}}
    <section class="lp" aria-label="{{ $name }}">
        <div class="bp" aria-hidden="true"></div>

        <div class="lbrand">
            <span class="lgo" aria-hidden="true">
                <img src="{{ $mark }}" alt="">
                <svg viewBox="0 0 58 58"><circle class="ring" pathLength="1" cx="29" cy="29" r="28" transform="rotate(-90 29 29)"/><line class="tick" x1="29" y1="-1" x2="29" y2="4"/></svg>
            </span>
            <div><b>{{ __('JEYANCO') }}</b><small>{{ __('CONSTRUCTION') }}</small></div>
        </div>

        <div class="pitch">
            <div>
                <h1>{{ __('Every shift clocked.') }}<br>{{ __('Every peso accounted for.') }}</h1>
                <p class="lead enter" style="--i:5">{{ __('Attendance from the site kiosks flows straight into payroll, so the crew gets paid right and on time.') }}</p>
            </div>
            <ul class="feats">
                <li class="enter" style="--i:7">
                    <span class="ic"><svg class="i" width="18" height="18" viewBox="0 0 24 24"><path d="M6.5 7.5A7 7 0 0 1 19 11v1.5M5 12a7 7 0 0 1 .4-2.4M8.5 20a14 14 0 0 1-1-5.5v-2.5a4.5 4.5 0 0 1 9 0v1.5M12 12v2.5a11 11 0 0 0 1.5 5.5M16.5 17.5a18 18 0 0 0 .5-3"/></svg></span>
                    {{ __('Biometric time-in and time-out from every site') }}
                </li>
                <li class="enter" style="--i:8">
                    <span class="ic"><svg class="i" width="18" height="18" viewBox="0 0 24 24"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M8 7h8M8 12h.01M12 12h.01M16 12h.01M8 16h.01M12 16h.01M16 16h.01"/></svg></span>
                    {{ __('DOLE-compliant pay, deductions and contributions') }}
                </li>
                <li class="enter" style="--i:9">
                    <span class="ic"><svg class="i" width="18" height="18" viewBox="0 0 24 24"><path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg></span>
                    {{ __('GPS monitoring across project sites') }}
                </li>
            </ul>
        </div>
    </section>

    {{-- ── The way in ──────────────────────────────────────────────────── --}}
    <main class="rp">
        <div class="rtop enter" style="--i:1">{{ __('Need an account?') }} <a href="{{ route('password.request') }}">{{ __('Contact your administrator') }}</a></div>

        <div class="rbody">
          <div class="card enter" style="--i:2">
            <div class="card-head">
                <h2>{{ __('Welcome back') }}</h2>
                <p class="sub">{{ __('Sign in to the Jeyanco management dashboard.') }}</p>
            </div>

            @if(session('success'))
                <div class="alert alert-success" role="status">
                    <svg class="i" width="18" height="18" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/></svg>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            {{-- Laravel's own errors, so a refused sign-in comes back marked
                 whether or not any script ran. --}}
            @if($errors->any())
                <div class="alert alert-error" role="alert">
                    <svg class="i" width="18" height="18" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form action="{{ route('login.post') }}" method="POST" id="login-form" novalidate>
                @csrf

                <div class="fld">
                    <label for="username">{{ __('Username or email') }}</label>
                    <div class="inp">
                        <svg class="i" width="18" height="18" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.5-6 8-6s8 2 8 6"/></svg>
                        {{-- text, not email: this box takes either one. --}}
                        <input id="username" name="username" type="text" value="{{ old('username') }}"
                               class="{{ $errors->has('username') ? 'invalid' : '' }}"
                               autocomplete="username" spellcheck="false" autocapitalize="none"
                               placeholder="{{ __('you@jeyanco.com') }}">
                    </div>
                    @error('username')
                        <span class="field-error">
                            <svg class="i" width="14" height="14" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
                            {{ $message }}
                        </span>
                    @enderror
                </div>

                <div class="fld">
                    <label for="password">{{ __('Password') }}</label>
                    <div class="inp">
                        <svg class="i" width="18" height="18" viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
                        <input id="password" name="password" type="password"
                               class="{{ $errors->has('password') ? 'invalid' : '' }}"
                               autocomplete="current-password" placeholder="{{ __('Enter your password') }}">
                        <button class="eye" id="toggle-pw" type="button" aria-label="{{ __('Show password') }}" aria-pressed="false">
                            <svg class="i on" width="18" height="18" viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="i off" width="18" height="18" viewBox="0 0 24 24"><path d="M3 3l18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.2A9.7 9.7 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3.2 4.1M6.6 6.6A17 17 0 0 0 2 12s3.5 7 10 7a9.6 9.6 0 0 0 5.4-1.6"/></svg>
                        </button>
                    </div>
                    @error('password')
                        <span class="field-error">
                            <svg class="i" width="14" height="14" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
                            {{ $message }}
                        </span>
                    @enderror
                    <span class="caps" id="caps" role="status">
                        <svg class="i" width="14" height="14" viewBox="0 0 24 24"><path d="M12 4l7 8h-4v4H9v-4H5zM9 20h6"/></svg>
                        {{ __('Caps Lock is on') }}
                    </span>
                </div>

                <div class="row">
                    <label class="remember">
                        <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}>{{ __('Remember me') }}
                    </label>
                    <a href="{{ route('password.request') }}">{{ __('Forgot password?') }}</a>
                </div>

                <button class="signin" id="submit" type="submit">
                    <span class="spin" aria-hidden="true"></span>
                    <span class="label">{{ __('Sign in') }}</span>
                    <svg class="i arrow" width="18" height="18" viewBox="0 0 24 24" style="stroke-width:2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </button>
                <span class="sr" id="signing" role="status" aria-live="polite"></span>

                {{-- Only an address an admin has put on an account gets in this way. --}}
                @if($googleSignIn ?? false)
                    <div class="or">{{ __('or') }}</div>
                    <a class="google" id="google" href="{{ route('login.google') }}">
                        <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
                        {{ __('Sign in with Google') }}
                    </a>
                @endif

            </form>

            {{-- Who this page is for, said once at the foot of the card. --}}
            <p class="notice">
                <svg class="i" width="15" height="15" viewBox="0 0 24 24"><path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z"/><path d="M9 12l2 2 4-4"/></svg>
                {{ __('For authorized Jeyanco personnel only.') }}
            </p>
          </div>
        </div>

        <footer class="rfoot enter" style="--i:9">
            <span>&copy; {{ now()->year }} {{ $name }}</span>
            <a href="{{ route('password.request') }}">{{ __('Trouble signing in?') }}</a>
        </footer>
    </main>
</div>

<script>
(function () {
  var d      = document.documentElement;
  var login  = document.getElementById('login');
  var stage  = document.getElementById('stage');
  var skip   = document.getElementById('skip');
  var form   = document.getElementById('login-form');
  var user   = document.getElementById('username');
  var pw     = document.getElementById('password');
  var toggle = document.getElementById('toggle-pw');
  var caps   = document.getElementById('caps');
  var btn    = document.getElementById('submit');
  var said   = document.getElementById('signing');

  // ── The field to start in ──────────────────────────────────────────────
  // A field the server refused, with the cursor at the end, so the
  // correction begins where the problem is; otherwise the username.
  function focusForm() {
    var bad = form.querySelector('input.invalid');
    var el  = bad || user;
    try { el.focus({ preventScroll: true }); } catch (e) { el.focus(); }
    if (bad) { var v = bad.value; bad.value = ''; bad.value = v; }
  }

  // ── The intro ──────────────────────────────────────────────────────────
  var intro = d.classList.contains('intro');
  var DUR   = 7300;
  var timer = null;

  // The big JEYANCO / CONSTRUCTION on the sign flies into the logo lockup.
  function flyTitle() {
    var svg  = stage.querySelector('svg.scene');
    var land = function () { d.classList.add('landed'); };
    var pairs = [
      [svg.querySelector('.t1'), login.querySelector('.lbrand b'),     '#eef4fd'],
      [svg.querySelector('.t2'), login.querySelector('.lbrand small'), '#a9bbd9']
    ];
    var layer = document.createElement('div');
    layer.className = 'fly';
    document.body.appendChild(layer);

    pairs.forEach(function (p, i) {
      var a = p[0].getBoundingClientRect(), b = p[1].getBoundingClientRect(), cs = getComputedStyle(p[1]);
      var el = document.createElement('div');
      el.textContent = p[1].textContent;
      el.style.cssText = 'position:fixed;left:' + b.left + 'px;top:' + b.top + 'px;font-weight:' + cs.fontWeight +
        ';font-size:' + cs.fontSize + ';line-height:' + cs.lineHeight + ';font-family:' + cs.fontFamily +
        ';letter-spacing:' + cs.letterSpacing + ';color:' + p[2] + ';white-space:nowrap;transform-origin:0 0';
      layer.appendChild(el);
      var sc = a.width / Math.max(1, el.getBoundingClientRect().width);
      var dx = a.left - b.left, dy = a.top - b.top + (a.height - b.height * sc) / 2;
      if (el.animate) {
        el.animate([{ transform: 'translate(' + dx + 'px,' + dy + 'px) scale(' + sc + ')' }, { transform: 'none' }],
                   { duration: 1300, delay: i * 90, easing: 'cubic-bezier(.76,0,.24,1)', fill: 'both' });
      }
    });

    var title = svg.querySelector('.title');
    title.style.transition = 'none';
    title.style.opacity = 0;
    setTimeout(function () { land(); layer.remove(); }, 1450);
  }

  function show(fast) {
    if (d.classList.contains('in')) return;
    clearTimeout(timer);
    login.inert = false;
    login.removeAttribute('aria-hidden');

    // Skip: straight to the finished page, with nothing left to watch — no
    // hand-over, no logo building itself, no words arriving one by one. The
    // intro class goes, so the page is exactly what a reload would show,
    // and for that one frame nothing is allowed to transition.
    if (fast) {
      d.classList.add('snap');
      d.classList.remove('intro');
      d.classList.add('in', 'landed');
      void d.offsetWidth;
      requestAnimationFrame(function () {
        requestAnimationFrame(function () { d.classList.remove('snap'); });
      });
      focusForm();
      return;
    }

    d.classList.add('in');
    if (window.matchMedia('(max-width: 900px)').matches) { d.classList.add('landed'); }
    else { flyTitle(); }
    setTimeout(focusForm, 900);
  }

  // The heading's words, each on its own beat once the page arrives.
  function splitWords(el) {
    var k = 0;
    Array.prototype.slice.call(el.childNodes).forEach(function (n) {
      if (n.nodeType !== 3) return;
      var frag = document.createDocumentFragment();
      n.textContent.split(/\s+/).filter(Boolean).forEach(function (w) {
        var sp = document.createElement('span');
        sp.className = 'w';
        sp.style.setProperty('--k', k++);
        sp.textContent = w;
        frag.appendChild(sp);
      });
      n.replaceWith(frag);
    });
  }

  if (intro) {
    login.inert = true;
    login.setAttribute('aria-hidden', 'true');
    splitWords(login.querySelector('.lp h1'));
    skip.addEventListener('click', function () { show(true); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !d.classList.contains('in')) show(true);
    });
    timer = setTimeout(function () { show(false); }, DUR);
  } else {
    focusForm();
  }

  // ── Show / hide the password ───────────────────────────────────────────
  toggle.addEventListener('click', function () {
    var show = pw.type === 'password';
    pw.type = show ? 'text' : 'password';
    toggle.setAttribute('aria-pressed', String(show));
    toggle.setAttribute('aria-label', show ? @json(__('Hide password')) : @json(__('Show password')));
    pw.focus();
  });

  // ── Caps Lock ──────────────────────────────────────────────────────────
  // The commonest reason a correct password is refused, and the one thing
  // the server can never tell you afterwards.
  function checkCaps(e) {
    if (e.getModifierState) caps.classList.toggle('show', e.getModifierState('CapsLock'));
  }
  pw.addEventListener('keyup', checkCaps);
  pw.addEventListener('keydown', checkCaps);
  pw.addEventListener('blur', function () { caps.classList.remove('show'); });

  // ── Typing clears the mark the server put on the field ─────────────────
  [user, pw].forEach(function (el) {
    el.addEventListener('input', function () { el.classList.remove('invalid'); });
  });

  // ── Submit ─────────────────────────────────────────────────────────────
  form.addEventListener('submit', function (e) {
    if (btn.classList.contains('loading')) { e.preventDefault(); return; }

    var missing = [];
    if (!user.value.trim()) missing.push(user);
    if (!pw.value) missing.push(pw);

    // Caught here only to save a round trip; the server checks the same two
    // things and is what actually decides.
    if (missing.length) {
      e.preventDefault();
      missing.forEach(function (el) { el.classList.add('invalid'); });
      missing[0].focus();
      return;
    }

    // The form goes straight away. The button shows a spinner and nothing
    // else; the intro does not play again, and nothing covers the page
    // while the dashboard comes.
    btn.classList.add('loading');
    said.textContent = @json(__('Signing in…'));
  });

  // Coming back to this page from the browser's cache: the button stops
  // spinning on a form no longer being submitted, and an intro left
  // mid-way is taken to its end.
  window.addEventListener('pageshow', function (e) {
    if (!e.persisted) return;
    btn.classList.remove('loading');
    said.textContent = '';
    if (intro) show(true);
  });
})();
</script>
</body>
</html>
