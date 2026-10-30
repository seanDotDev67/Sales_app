<?php
// Shown once, right after a successful login (flag is set in login.php).
if (empty($_SESSION['show_splash'])) {
    return;
}
unset($_SESSION['show_splash']);

$splashRoles = [1 => 'Administrator', 2 => 'Customer', 3 => 'Owner'];
$splashRole  = $splashRoles[(int)($_SESSION['role_id'] ?? 2)] ?? 'User';
?>
<style>
    #welcome-splash {
        position: fixed; inset: 0; z-index: 3000;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        background: #163a68; color: #fff; text-align: center;
        transition: opacity .6s ease, visibility .6s ease, transform .6s ease;
    }
    #welcome-splash.hide { opacity: 0; visibility: hidden; transform: scale(1.04); }
    #welcome-splash .mark {
        width: 72px; height: 72px; border-radius: 18px; border: 2px solid rgba(255,255,255,.85);
        display: grid; place-items: center; font-size: 2rem;
        animation: ws-pop .7s cubic-bezier(.2,.9,.3,1.2) both;
    }
    #welcome-splash .hello { margin-top: 1.2rem; font-size: 1.35rem; font-weight: 600; animation: ws-rise .6s ease .3s both; }
    #welcome-splash .role  { margin-top: .25rem; font-size: .8rem; letter-spacing: .12em; text-transform: uppercase; opacity: .75; animation: ws-rise .6s ease .45s both; }
    #welcome-splash .bar   { margin-top: 1.4rem; width: 140px; height: 3px; background: rgba(255,255,255,.2); border-radius: 3px; overflow: hidden; }
    #welcome-splash .bar span { display: block; height: 100%; background: #fff; transform: translateX(-100%); animation: ws-load 1.2s ease-in-out .3s forwards; }
    @keyframes ws-pop  { from { opacity: 0; transform: scale(.6); } to { opacity: 1; transform: scale(1); } }
    @keyframes ws-rise { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
    @keyframes ws-load { to { transform: translateX(0); } }
    @media (prefers-reduced-motion: reduce) {
        #welcome-splash * { animation-duration: .01ms !important; animation-delay: 0s !important; }
    }
</style>
<div id="welcome-splash" aria-hidden="true">
    <div class="mark"><i class="bi bi-bar-chart-line"></i></div>
    <div class="hello">Welcome back, <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></div>
    <div class="role"><?= htmlspecialchars($splashRole) ?></div>
    <div class="bar"><span></span></div>
</div>
<script>
    (function () {
        var el = document.getElementById('welcome-splash');
        setTimeout(function () {
            el.classList.add('hide');
            setTimeout(function () { el.remove(); }, 800);
        }, 1700);
    })();
</script>
