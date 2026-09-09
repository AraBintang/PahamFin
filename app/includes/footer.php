                </div> <!-- End max-w-screen-xl -->
            </div> <!-- End flex-1 overflow-y-auto -->
        </main>
    </div>

    <!-- Global Toast Notification -->
    <?php if (!empty($_SESSION['flash_msg'])):
        $flashType = $_SESSION['flash_type'] ?? 'success';
        $bgClass = ($flashType === 'error' || $flashType === 'red')
            ? 'bg-rose-500 dark:bg-rose-600'
            : 'bg-emerald-500 dark:bg-emerald-600';
        $iconClass = ($flashType === 'error' || $flashType === 'red')
            ? 'ph-warning-circle'
            : 'ph-check-circle';
    ?>
    <div x-data="{ show: true }"
         x-show="show"
         x-init="setTimeout(() => show = false, 3500)"
         x-transition:enter="transition ease-out duration-300 transform"
         x-transition:enter-start="translate-y-10 opacity-0"
         x-transition:enter-end="translate-y-0 opacity-100"
         x-transition:leave="transition ease-in duration-200 transform"
         x-transition:leave-start="translate-y-0 opacity-100"
         x-transition:leave-end="translate-y-10 opacity-0"
         class="fixed bottom-6 right-6 z-50 flex items-center gap-3 px-4 py-3 rounded-xl shadow-[0_10px_35px_rgba(0,0,0,0.18)] text-white <?= $bgClass ?>"
         style="min-width:220px; max-width:360px;">
        <i class="ph <?= $iconClass ?> text-2xl shrink-0"></i>
        <span class="font-medium text-sm flex-1"><?= htmlspecialchars($_SESSION['flash_msg']) ?></span>
        <button @click="show = false" class="ml-2 text-white/80 hover:text-white transition shrink-0">
            <i class="ph ph-x text-lg"></i>
        </button>
    </div>
    <?php
        unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
    endif;
    ?>
</body>
</html>
