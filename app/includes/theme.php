<?php
/**
 * ============================================
 * PahamFin - Shared Theme (Dark / Light Mode)
 * ============================================
 * Include this file inside <head> of every page
 * to provide consistent dark mode support.
 *
 * Usage:  <?php require_once __DIR__ . '/app/includes/theme.php'; ?>
 */

/* ── Helper: render a theme toggle button ── */
function PahamFin_theme_toggle(string $variant = 'navbar'): string
{
    $classes = match ($variant) {
        'navbar'  => 'w-9 h-9 flex items-center justify-center rounded-xl bg-gray-100 dark:bg-slate-700 text-gray-500 dark:text-amber-400 hover:bg-gray-200 dark:hover:bg-slate-600 transition',
        'landing' => 'w-10 h-10 flex items-center justify-center rounded-xl bg-white/70 dark:bg-slate-800/50 text-gray-600 dark:text-amber-400 hover:scale-105 transition-transform shadow-sm',
        'floating'=> 'absolute top-4 right-4 w-10 h-10 flex items-center justify-center rounded-full glass-card text-gray-600 dark:text-amber-400 hover:scale-110 transition-transform shadow-lg',
        default   => 'w-9 h-9 flex items-center justify-center rounded-xl bg-gray-100 dark:bg-slate-700 text-gray-500 dark:text-amber-400 hover:bg-gray-200 dark:hover:bg-slate-600 transition',
    };

    return '<button @click="toggleDark()" class="' . $classes . '" title="Toggle Dark Mode"><i class="ph text-lg" :class="darkMode ? \'ph-sun\' : \'ph-moon\'"></i></button>';
}
?>

<!-- Anti-FOUC: apply dark class before paint -->
<script>
(function(){
    try {
        var d = localStorage.getItem('PahamFin_dark') === '1';
        document.documentElement.classList.toggle('dark', d);
    } catch(e){}
})();
</script>

<style>
/* ─────────────────────────────────────────────
   Canvas Background  (shared across all pages)
   ───────────────────────────────────────────── */
.bg-canvas {
    background-color: #dbeafe;
    background-attachment: fixed;
    background-image:
        /* Sinar matahari kiri atas */
        radial-gradient(ellipse at 0% 0%, rgba(186,230,253,0.9) 0%, transparent 50%),
        /* Bintik aurora ungu kanan atas */
        radial-gradient(ellipse at 100% 5%, rgba(216,180,254,0.7) 0%, transparent 40%),
        /* Aura hijau mint kiri bawah */
        radial-gradient(ellipse at 5% 95%, rgba(167,243,208,0.8) 0%, transparent 45%),
        /* Aura biru langit tengah */
        radial-gradient(ellipse at 50% 30%, rgba(147,197,253,0.4) 0%, transparent 60%),
        /* Base gradient siang cerah */
        linear-gradient(145deg, #e0f2fe 0%, #ede9fe 40%, #ecfdf5 80%, #fef9c3 100%);
}
.dark .bg-canvas {
    background-color: #020617;
    background-attachment: fixed;
    background-image:
        url("data:image/svg+xml,%3Csvg width='400' height='400' viewBox='0 0 400 400' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M50 50 L100 120 L250 80 L350 200' stroke='rgba(255,255,255,0.1)' stroke-width='1' fill='none'/%3E%3Ccircle cx='50' cy='50' r='1.5' fill='rgba(255,255,255,0.5)'/%3E%3Ccircle cx='100' cy='120' r='2' fill='rgba(255,255,255,0.7)'/%3E%3Ccircle cx='250' cy='80' r='1.5' fill='rgba(255,255,255,0.6)'/%3E%3Ccircle cx='350' cy='200' r='2' fill='rgba(255,255,255,0.8)'/%3E%3Cpath d='M150 250 L200 350 L300 300' stroke='rgba(255,255,255,0.1)' stroke-width='1' fill='none'/%3E%3Ccircle cx='150' cy='250' r='1.5' fill='rgba(255,255,255,0.7)'/%3E%3Ccircle cx='200' cy='350' r='2' fill='rgba(255,255,255,0.5)'/%3E%3Ccircle cx='300' cy='300' r='1.5' fill='rgba(255,255,255,0.8)'/%3E%3C/svg%3E"),
        radial-gradient(2px 2px at 20px 30px, #ffffff, rgba(0,0,0,0)),
        radial-gradient(2px 2px at 150px 80px, #ffffff, rgba(0,0,0,0)),
        radial-gradient(2px 2px at 80px 140px, #ffffff, rgba(0,0,0,0)),
        radial-gradient(3px 3px at 260px 120px, #ffffff, rgba(0,0,0,0)),
        radial-gradient(circle at 15% 50%, rgba(76, 29, 149, 0.3) 0%, transparent 60%),
        radial-gradient(circle at 85% 30%, rgba(30, 58, 138, 0.3) 0%, transparent 60%);
    background-size: 400px 400px, 200px 200px, 250px 250px, 300px 300px, 350px 350px, 100% 100%, 100% 100%;
}

/* ─────────────────────────────────────────────
   Glass Card
   ───────────────────────────────────────────── */
.glass-card {
    background: linear-gradient(145deg,
        rgba(255,255,255,0.88) 0%,
        rgba(240,249,255,0.82) 50%,
        rgba(245,243,255,0.80) 100%);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,0.9);
    box-shadow:
        0 1px 2px rgba(0,0,0,0.04),
        0 4px 20px rgba(99,102,241,0.07),
        0 8px 32px rgba(14,165,233,0.06),
        inset 0 1px 0 rgba(255,255,255,1);
}
.dark .glass-card {
    background: linear-gradient(145deg, rgba(15,23,42,0.92) 0%, rgba(17,24,39,0.88) 50%, rgba(30,27,75,0.7) 100%);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border-color: rgba(51,65,85,0.5);
    box-shadow: 0 1px 4px rgba(0,0,0,0.3), 0 4px 24px rgba(99,102,241,0.06), inset 0 1px 0 rgba(255,255,255,0.04);
}

/* ─────────────────────────────────────────────
   Dark-mode overrides (global)
   ───────────────────────────────────────────── */

/* Form elements */
.dark input, .dark select, .dark textarea {
    background-color: rgba(15,23,42,0.6) !important;
    border-color: #334155 !important;
    color: #e2e8f0 !important;
}
.dark input::placeholder { color: #475569 !important; }

/* Table rows */
.dark tr:hover td { background-color: rgba(51,65,85,0.3) !important; }
.dark thead tr th { background-color: transparent !important; color: #64748b !important; }

/* Notification dropdown */
.dark .notif-dropdown { background-color: #1e293b !important; border-color: #334155 !important; }

/* Scrollbar */
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }


/* Sidebar gradient */
.sidebar-grad {
    background: linear-gradient(180deg,
        #0369a1 0%,
        #0284c7 30%,
        #0ea5e9 65%,
        #06b6d4 85%,
        #10b981 100%);
    box-shadow: 2px 0 20px rgba(3, 105, 161, 0.2);
}
.dark .sidebar-grad {
    background: linear-gradient(180deg, #020617 0%, #0f172a 40%, #1e1b4b 80%, #312e81 100%);
    box-shadow: 2px 0 20px rgba(0,0,0,0.4);
}

/* Light mode — input fields */
input, select, textarea {
    background-color: rgba(255,255,255,0.9);
}

/* Light mode table refinement */
thead tr th {
    background: rgba(248,250,252,0.7) !important;
}

/* Light mode: stat cards on dashboard */
.stat-card-light {
    background: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(240,249,255,0.85) 100%);
    border: 1px solid rgba(255,255,255,0.95);
    box-shadow: 0 2px 12px rgba(14,165,233,0.1), inset 0 1px 0 rgba(255,255,255,1);
}

/* ─────────────────────────────────────────────
   Smooth theme transitions
   ───────────────────────────────────────────── */
* { transition-property: background-color, border-color, color; transition-duration: 150ms; }
img, svg, i, button, a { transition-duration: 0ms !important; }

</style>

