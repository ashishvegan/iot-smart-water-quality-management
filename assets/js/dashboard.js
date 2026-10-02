/**
 * AquaSense IoT - Main Dashboard Real-time Engine
 * Real-time 10-second polling, Chart.js 1-Hour Line Graph, Dynamic UI Alert Theme
 */

document.addEventListener('DOMContentLoaded', () => {
  let waterChart = null;
  let pollInterval = null;
  let activeChartMetric = 'all'; // 'all', 'tds', 'turbidity', 'ph', 'temp'
  let cachedChartData = null;

  // Sound Mute Toggle Button State
  const muteBtn = document.getElementById('muteToggleBtn');
  const updateMuteBtnIcon = () => {
    if (!muteBtn) return;
    const isMuted = AlertSound.isMuted();
    muteBtn.innerHTML = isMuted
      ? `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 5L6 9H2v6h4l5 4V5z"/><line x1="23" y1="9" x2="17" y2="15"/><line x1="17" y1="9" x2="23" y2="15"/></svg><span class="hidden sm:inline">Unmute Alert</span>`
      : `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-cyan-400 animate-pulse" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 5L6 9H2v6h4l5 4V5z"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"/></svg><span class="hidden sm:inline">Mute Alert</span>`;
    muteBtn.classList.toggle('btn-outline', isMuted);
    muteBtn.classList.toggle('btn-info', !isMuted);
  };
  
  if (muteBtn) {
    updateMuteBtnIcon();
    muteBtn.addEventListener('click', () => {
      AlertSound.toggleMute();
      updateMuteBtnIcon();
    });
  }

  // Initialize Chart.js
  const initChart = (chartData) => {
    const ctx = document.getElementById('waterQualityChart');
    if (!ctx) return;

    cachedChartData = chartData;

    const datasets = [
      {
        label: 'TDS (ppm)',
        data: chartData.tds || [],
        borderColor: '#38bdf8', // Aqua blue
        backgroundColor: 'rgba(56, 189, 248, 0.1)',
        borderWidth: 2.5,
        tension: 0.35,
        pointRadius: 2,
        pointHoverRadius: 5,
        yAxisID: 'y_tds',
        hidden: false
      },
      {
        label: 'Turbidity (NTU)',
        data: chartData.turbidity || [],
        borderColor: '#fbbf24', // Amber
        backgroundColor: 'rgba(251, 191, 36, 0.08)',
        borderWidth: 2,
        tension: 0.35,
        pointRadius: 2,
        pointHoverRadius: 5,
        yAxisID: 'y_turbidity',
        hidden: false
      },
      {
        label: 'pH Level',
        data: chartData.ph || [],
        borderColor: '#10b981', // Emerald
        backgroundColor: 'rgba(16, 185, 129, 0.08)',
        borderWidth: 2,
        tension: 0.35,
        pointRadius: 2,
        pointHoverRadius: 5,
        yAxisID: 'y_ph',
        hidden: false
      },
      {
        label: 'Temperature (°C)',
        data: chartData.temperature || [],
        borderColor: '#f97316', // Orange
        backgroundColor: 'rgba(249, 115, 22, 0.08)',
        borderWidth: 2,
        tension: 0.35,
        pointRadius: 2,
        pointHoverRadius: 5,
        yAxisID: 'y_temp',
        hidden: false
      }
    ];

    waterChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: chartData.labels || [],
        datasets: datasets
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
          mode: 'index',
          intersect: false
        },
        plugins: {
          legend: {
            position: 'top',
            labels: {
              color: '#94a3b8',
              font: { family: 'Plus Jakarta Sans', size: 12, weight: 600 },
              usePointStyle: true,
              pointStyle: 'circle',
              padding: 18
            }
          },
          tooltip: {
            backgroundColor: 'rgba(8, 19, 37, 0.95)',
            titleColor: '#38bdf8',
            bodyColor: '#f1f5f9',
            borderColor: 'rgba(56, 189, 248, 0.3)',
            borderWidth: 1,
            padding: 12,
            boxPadding: 6,
            usePointStyle: true
          }
        },
        scales: {
          x: {
            grid: { color: 'rgba(255, 255, 255, 0.05)' },
            ticks: {
              color: '#64748b',
              maxTicksLimit: 8,
              font: { family: 'JetBrains Mono', size: 11 }
            }
          },
          y_tds: {
            type: 'linear',
            position: 'left',
            grid: { color: 'rgba(56, 189, 248, 0.07)' },
            ticks: {
              color: '#38bdf8',
              font: { family: 'JetBrains Mono', size: 11 }
            },
            title: {
              display: true,
              text: 'TDS (ppm)',
              color: '#38bdf8',
              font: { size: 11, weight: 600 }
            }
          },
          y_ph: {
            type: 'linear',
            position: 'right',
            min: 0,
            max: 14,
            grid: { drawOnChartArea: false },
            ticks: {
              color: '#10b981',
              font: { family: 'JetBrains Mono', size: 11 }
            },
            title: {
              display: true,
              text: 'pH Level',
              color: '#10b981',
              font: { size: 11, weight: 600 }
            }
          },
          y_turbidity: {
            type: 'linear',
            position: 'right',
            grid: { drawOnChartArea: false },
            ticks: { display: false }
          },
          y_temp: {
            type: 'linear',
            position: 'left',
            grid: { drawOnChartArea: false },
            ticks: { display: false }
          }
        }
      }
    });
  };

  // Filter Chart datasets based on selector tabs
  const chartFilterTabs = document.querySelectorAll('.chart-filter-btn');
  chartFilterTabs.forEach(btn => {
    btn.addEventListener('click', () => {
      const metric = btn.dataset.metric;
      chartFilterTabs.forEach(b => b.classList.remove('btn-active', 'bg-cyan-500', 'text-white'));
      btn.classList.add('btn-active', 'bg-cyan-500', 'text-white');
      
      if (!waterChart) return;
      
      waterChart.data.datasets.forEach((ds, idx) => {
        if (metric === 'all') {
          ds.hidden = false;
        } else if (metric === 'tds') {
          ds.hidden = (idx !== 0);
        } else if (metric === 'turbidity') {
          ds.hidden = (idx !== 1);
        } else if (metric === 'ph') {
          ds.hidden = (idx !== 2);
        } else if (metric === 'temp') {
          ds.hidden = (idx !== 3);
        }
      });
      waterChart.update();
    });
  });

  // Update Live UI Elements
  const updateUI = (data) => {
    if (!data || !data.latest) return;

    const latest = data.latest;
    const device = data.device || {};
    const thresholds = data.thresholds || {};

    // 1. Update TDS Display
    const elTds = document.getElementById('valTds');
    const elTdsBadge = document.getElementById('badgeTds');
    if (elTds) elTds.textContent = parseFloat(latest.tds).toFixed(1);
    if (elTdsBadge) {
      if (latest.tds > thresholds.tds_max) {
        elTdsBadge.className = 'badge badge-error gap-1 text-xs font-semibold';
        elTdsBadge.textContent = 'High (> ' + thresholds.tds_max + ')';
      } else if (latest.tds < thresholds.tds_min) {
        elTdsBadge.className = 'badge badge-warning gap-1 text-xs font-semibold';
        elTdsBadge.textContent = 'Low Demineralized';
      } else {
        elTdsBadge.className = 'badge badge-success gap-1 text-xs font-semibold';
        elTdsBadge.textContent = 'Optimal Safe';
      }
    }

    // 2. Update Turbidity Display
    const elTurbidity = document.getElementById('valTurbidity');
    const elTurbidityBadge = document.getElementById('badgeTurbidity');
    if (elTurbidity) elTurbidity.textContent = parseFloat(latest.turbidity).toFixed(2);
    if (elTurbidityBadge) {
      if (latest.turbidity > thresholds.turbidity_max) {
        elTurbidityBadge.className = 'badge badge-error gap-1 text-xs font-semibold';
        elTurbidityBadge.textContent = 'Turbid (> ' + thresholds.turbidity_max + ' NTU)';
      } else {
        elTurbidityBadge.className = 'badge badge-success gap-1 text-xs font-semibold';
        elTurbidityBadge.textContent = 'Crystal Clear';
      }
    }

    // 3. Update pH Display
    const elPh = document.getElementById('valPh');
    const elPhBadge = document.getElementById('badgePh');
    const elPhBar = document.getElementById('phProgressBar');
    if (elPh) elPh.textContent = parseFloat(latest.ph).toFixed(2);
    if (elPhBadge) {
      if (latest.ph < thresholds.ph_min) {
        elPhBadge.className = 'badge badge-error gap-1 text-xs font-semibold';
        elPhBadge.textContent = 'Acidic (< ' + thresholds.ph_min + ')';
      } else if (latest.ph > thresholds.ph_max) {
        elPhBadge.className = 'badge badge-error gap-1 text-xs font-semibold';
        elPhBadge.textContent = 'Alkaline (> ' + thresholds.ph_max + ')';
      } else {
        elPhBadge.className = 'badge badge-success gap-1 text-xs font-semibold';
        elPhBadge.textContent = 'Neutral Safe';
      }
    }
    if (elPhBar) {
      const phPercent = Math.min(100, Math.max(0, (latest.ph / 14) * 100));
      elPhBar.style.width = phPercent + '%';
    }

    // 4. Update Temperature Display
    const elTemp = document.getElementById('valTemp');
    const elTempBadge = document.getElementById('badgeTemp');
    if (elTemp) elTemp.textContent = parseFloat(latest.temperature).toFixed(1);
    if (elTempBadge) {
      if (latest.temperature > thresholds.temp_max || latest.temperature < thresholds.temp_min) {
        elTempBadge.className = 'badge badge-warning gap-1 text-xs font-semibold';
        elTempBadge.textContent = 'Abnormal';
      } else {
        elTempBadge.className = 'badge badge-info gap-1 text-xs font-semibold';
        elTempBadge.textContent = 'Normal';
      }
    }

    // 5. Update Overall Water Quality Score
    const elScore = document.getElementById('valQualityScore');
    const elScoreText = document.getElementById('textQualityStatus');
    const elScoreCircle = document.getElementById('scoreRadialProgress');
    const scoreVal = parseInt(latest.score || 100);
    
    if (elScore) elScore.textContent = scoreVal + '%';
    if (elScoreCircle) {
      elScoreCircle.style.setProperty('--value', scoreVal);
      if (latest.status === 'bad') {
        elScoreCircle.className = 'radial-progress text-red-500 font-bold';
      } else if (latest.status === 'warning') {
        elScoreCircle.className = 'radial-progress text-amber-500 font-bold';
      } else {
        elScoreCircle.className = 'radial-progress text-emerald-400 font-bold';
      }
    }
    if (elScoreText) {
      if (latest.status === 'bad') {
        elScoreText.textContent = 'CRITICAL / CONTAMINATED';
        elScoreText.className = 'text-sm font-bold text-red-400 uppercase tracking-wide';
      } else if (latest.status === 'warning') {
        elScoreText.textContent = 'ATTENTION REQUIRED';
        elScoreText.className = 'text-sm font-bold text-amber-400 uppercase tracking-wide';
      } else {
        elScoreText.textContent = 'EXCELLENT & SAFE';
        elScoreText.className = 'text-sm font-bold text-emerald-400 uppercase tracking-wide';
      }
    }

    // 6. Update Solenoid Valve Display & Controls
    const elValveCard = document.getElementById('solenoidValveCard');
    const elValveStatusText = document.getElementById('valveStatusText');
    const elValveModeBadge = document.getElementById('valveModeBadge');
    const btnToggleValve = document.getElementById('btnToggleValve');
    const btnSwitchMode = document.getElementById('btnSwitchMode');

    const isValveOn = Boolean(device.valve_state);
    const isAutoMode = (device.valve_mode === 'auto');

    if (elValveStatusText) {
      elValveStatusText.textContent = isValveOn ? 'VALVE OPEN (WATER FLOW ENABLED)' : 'VALVE SHUT (FLOW CUT OFF)';
      elValveStatusText.className = isValveOn 
        ? 'text-lg font-bold text-emerald-400 tracking-wide' 
        : 'text-lg font-bold text-rose-400 tracking-wide';
    }

    if (elValveModeBadge) {
      elValveModeBadge.textContent = isAutoMode ? 'Mode: AUTO' : 'Mode: MANUAL';
      elValveModeBadge.className = isAutoMode 
        ? 'badge badge-info badge-outline font-mono text-xs uppercase' 
        : 'badge badge-warning badge-outline font-mono text-xs uppercase';
    }

    if (elValveCard) {
      elValveCard.classList.toggle('valve-on-glow', isValveOn);
      elValveCard.classList.toggle('valve-off-glow', !isValveOn);
    }

    if (btnToggleValve) {
      btnToggleValve.textContent = isValveOn ? 'Emergency Close Valve' : 'Open Valve (Enable Flow)';
      btnToggleValve.className = isValveOn 
        ? 'btn btn-error btn-sm font-semibold' 
        : 'btn btn-success btn-sm font-semibold';
      btnToggleValve.disabled = isAutoMode; // In auto mode, manual toggle is disabled unless mode switched
    }

    if (btnSwitchMode) {
      btnSwitchMode.textContent = isAutoMode ? 'Switch to Manual Mode' : 'Switch to Auto Mode';
    }

    // 7. Update Device Diagnostics & Heartbeat
    const elOnlineDot = document.getElementById('deviceOnlineDot');
    const elOnlineText = document.getElementById('deviceOnlineText');
    const elLastSync = document.getElementById('lastSyncText');
    const elEspRam = document.getElementById('espRamVal');
    const elEspTemp = document.getElementById('espCpuTempVal');

    if (elOnlineDot && elOnlineText) {
      if (device.is_online) {
        elOnlineDot.className = 'w-2.5 h-2.5 rounded-full bg-emerald-400 pulse-indicator';
        elOnlineText.textContent = 'ESP32 Connected';
        elOnlineText.className = 'text-xs font-semibold text-emerald-400';
      } else {
        elOnlineDot.className = 'w-2.5 h-2.5 rounded-full bg-slate-500';
        elOnlineText.textContent = 'ESP32 Standby / Offline';
        elOnlineText.className = 'text-xs font-semibold text-slate-400';
      }
    }

    if (elLastSync) {
      elLastSync.textContent = latest.timestamp || 'Just now';
    }

    if (elEspRam) {
      const freeKb = Math.round((device.free_ram || 0) / 1024);
      elEspRam.textContent = freeKb + ' KB Free';
    }

    if (elEspTemp) {
      elEspTemp.textContent = parseFloat(device.cpu_temp || 42.0).toFixed(1) + ' °C';
    }

    // 8. Emergency Alert Banner & Theme Shift
    const alertBanner = document.getElementById('emergencyAlertBanner');
    const alertIssuesList = document.getElementById('alertIssuesList');

    if (latest.status === 'bad') {
      // Trigger Red Alert Theme
      document.body.classList.add('theme-alert-bad');
      
      if (alertBanner) {
        alertBanner.classList.remove('hidden');
        if (alertIssuesList && Array.isArray(latest.issues)) {
          alertIssuesList.innerHTML = latest.issues
            .map(iss => `<li class="flex items-center gap-2">⚠️ ${iss}</li>`)
            .join('');
        }
      }

      // Start Procedural Sound Alert
      AlertSound.startAlarm();
    } else {
      // Restore oceanic calm theme
      document.body.classList.remove('theme-alert-bad');
      if (alertBanner) {
        alertBanner.classList.add('hidden');
      }
      // Stop Procedural Sound Alert
      AlertSound.stopAlarm();
    }

    // 9. Update Chart if initialized
    if (waterChart && data.chart_data && data.chart_data.labels) {
      waterChart.data.labels = data.chart_data.labels;
      waterChart.data.datasets[0].data = data.chart_data.tds;
      waterChart.data.datasets[1].data = data.chart_data.turbidity;
      waterChart.data.datasets[2].data = data.chart_data.ph;
      waterChart.data.datasets[3].data = data.chart_data.temperature;
      waterChart.update('none'); // Update without full redraw glitch
    } else if (!waterChart && data.chart_data) {
      initChart(data.chart_data);
    }
  };

  // Poll server for latest data
  const fetchTelemetry = async () => {
    try {
      const res = await fetch('api/get_latest.php?t=' + Date.now());
      if (!res.ok) throw new Error('Network error');
      const data = await res.json();
      if (data && data.success) {
        updateUI(data);
      }
    } catch (err) {
      console.warn('Telemetry fetch error:', err);
    }
  };

  // Immediate Initial Fetch
  fetchTelemetry();

  // Realtime Polling Every 10 Seconds (Requirement #3)
  pollInterval = setInterval(fetchTelemetry, 10000);

  // Solenoid Valve Control Event Listeners
  const btnToggleValve = document.getElementById('btnToggleValve');
  const btnSwitchMode = document.getElementById('btnSwitchMode');

  if (btnToggleValve) {
    btnToggleValve.addEventListener('click', async () => {
      AlertSound.playClick();
      const currentValveStatusText = document.getElementById('valveStatusText');
      const isOpen = currentValveStatusText && currentValveStatusText.textContent.includes('OPEN');
      const targetState = isOpen ? 0 : 1;

      try {
        btnToggleValve.disabled = true;
        const res = await fetch('api/control_valve.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ valve_state: targetState })
        });
        const resp = await res.json();
        if (resp.success) {
          fetchTelemetry();
        } else {
          alert(resp.error || 'Failed to toggle valve.');
        }
      } catch (e) {
        console.error(e);
      } finally {
        btnToggleValve.disabled = false;
      }
    });
  }

  if (btnSwitchMode) {
    btnSwitchMode.addEventListener('click', async () => {
      AlertSound.playClick();
      const currentModeBadge = document.getElementById('valveModeBadge');
      const isAuto = currentModeBadge && currentModeBadge.textContent.includes('AUTO');
      const targetMode = isAuto ? 'manual' : 'auto';

      try {
        btnSwitchMode.disabled = true;
        const res = await fetch('api/control_valve.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ mode: targetMode })
        });
        const resp = await res.json();
        if (resp.success) {
          fetchTelemetry();
        }
      } catch (e) {
        console.error(e);
      } finally {
        btnSwitchMode.disabled = false;
      }
    });
  }

  // Quick Simulator Buttons for Testing
  const btnSimNormal = document.getElementById('btnSimNormal');
  const btnSimBad = document.getElementById('btnSimBad');

  if (btnSimNormal) {
    btnSimNormal.addEventListener('click', async () => {
      AlertSound.playClick();
      btnSimNormal.disabled = true;
      await fetch('api/simulator.php?action=simulate_reading&type=normal');
      await fetchTelemetry();
      btnSimNormal.disabled = false;
    });
  }

  if (btnSimBad) {
    btnSimBad.addEventListener('click', async () => {
      AlertSound.playClick();
      btnSimBad.disabled = true;
      await fetch('api/simulator.php?action=simulate_reading&type=bad');
      await fetchTelemetry();
      btnSimBad.disabled = false;
    });
  }
});
