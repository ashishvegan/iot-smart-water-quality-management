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
        <span id="systemClock"><?= date(DATETIME_FORMAT) ?></span>
      </div>
    </div>
  </footer>

  <script>
    // Live Asia/Kolkata +5:30 Clock Updater in DD-MM-YYYY and HH:mm AM/PM
    const updateFooterClock = () => {
      const clockEl = document.getElementById('systemClock');
      if (clockEl) {
        const now = new Date();
        const options = {
          timeZone: 'Asia/Kolkata',
          day: '2-digit',
          month: '2-digit',
          year: 'numeric',
          hour: '2-digit',
          minute: '2-digit',
          second: '2-digit',
          hour12: true
        };
        // Format: DD-MM-YYYY, hh:mm:ss AM/PM
        const formatter = new Intl.DateTimeFormat('en-IN', options);
        const parts = formatter.formatToParts(now);
        let day='', month='', year='', hour='', min='', sec='', dayPeriod='';
        parts.forEach(p => {
          if (p.type === 'day') day = p.value;
          if (p.type === 'month') month = p.value;
          if (p.type === 'year') year = p.value;
          if (p.type === 'hour') hour = p.value;
          if (p.type === 'minute') min = p.value;
          if (p.type === 'second') sec = p.value;
          if (p.type === 'dayPeriod') dayPeriod = p.value.toUpperCase();
        });
        clockEl.textContent = `${day}-${month}-${year} ${hour}:${min} ${dayPeriod}`;
      }
    };
    setInterval(updateFooterClock, 1000);
    updateFooterClock();
  </script>
</body>
</html>
