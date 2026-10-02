<?php
/**
 * Footer Component
 * IoT Smart Water Quality Management System
 */

$footerText = !empty($settings['footer_text']) 
    ? $settings['footer_text'] 
    : 'AquaSense IoT - Smart Water Quality Management System © 2026';
?>
  </main>

  <!-- Ambient Wave Footer Decoration -->
  <div class="wave-container h-12 w-full mt-auto pointer-events-none relative opacity-40">
    <div class="wave-bar"></div>
  </div>

  <!-- Bottom Footer -->
  <footer class="bg-slate-950/90 border-t border-sky-900/30 py-6 relative z-10 text-xs text-slate-400">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4">
      <div class="flex items-center gap-2">
        <span class="inline-block w-2 h-2 rounded-full bg-cyan-400 animate-pulse"></span>
        <span><?= htmlspecialchars($footerText) ?></span>
      </div>

      <div class="flex items-center gap-4 text-[11px] font-mono text-slate-400">
        <span>ESP32 30-Pin Node</span>
        <span class="text-sky-500">•</span>
        <span>Relay 1: Active LOW</span>
        <span class="text-sky-500">•</span>
        <span id="systemClock"><?= date('H:i:s') ?></span>
      </div>
    </div>
  </footer>

  <script>
    // Simple footer clock updater
    setInterval(() => {
      const clockEl = document.getElementById('systemClock');
      if (clockEl) {
        const d = new Date();
        clockEl.textContent = d.toTimeString().split(' ')[0];
      }
    }, 1000);
  </script>
</body>
</html>
