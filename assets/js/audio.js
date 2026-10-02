/**
 * Web Audio Procedural Siren & Alert Sound System
 * IoT Smart Water Quality Management System
 */

const AlertSound = (() => {
  let audioCtx = null;
  let isMuted = localStorage.getItem('aquasense_audio_muted') === 'true';
  let isPlaying = false;
  let alarmInterval = null;

  // Initialize Audio Context upon user interaction
  const initAudio = () => {
    if (!audioCtx) {
      const AudioContextClass = window.AudioContext || window.webkitAudioContext;
      if (AudioContextClass) {
        audioCtx = new AudioContextClass();
      }
    }
    if (audioCtx && audioCtx.state === 'suspended') {
      audioCtx.resume();
    }
  };

  // Play a single procedural alert tone burst
  const playToneBurst = (freq1 = 880, freq2 = 587.33, duration = 0.25) => {
    if (isMuted) return;
    initAudio();
    if (!audioCtx) return;

    try {
      const osc = audioCtx.createOscillator();
      const gain = audioCtx.createGain();

      osc.type = 'sine';
      osc.frequency.setValueAtTime(freq1, audioCtx.currentTime);
      osc.frequency.exponentialRampToValueAtTime(freq2, audioCtx.currentTime + duration);

      gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + duration);

      osc.connect(gain);
      gain.connect(audioCtx.destination);

      osc.start();
      osc.stop(audioCtx.currentTime + duration);
    } catch (e) {
      console.warn('Audio play error:', e);
    }
  };

  // Start continuous emergency alarm (alternating siren)
  const startAlarm = () => {
    if (isPlaying) return;
    isPlaying = true;
    initAudio();

    // Fire immediate burst
    playToneBurst(920, 600, 0.35);

    alarmInterval = setInterval(() => {
      if (!isMuted && isPlaying) {
        playToneBurst(920, 600, 0.35);
      }
    }, 1800);
  };

  // Stop emergency alarm
  const stopAlarm = () => {
    isPlaying = false;
    if (alarmInterval) {
      clearInterval(alarmInterval);
      alarmInterval = null;
    }
  };

  // Toggle Mute
  const toggleMute = () => {
    isMuted = !isMuted;
    localStorage.setItem('aquasense_audio_muted', isMuted);
    if (isMuted) {
      stopAlarm();
    }
    return isMuted;
  };

  // Play quick subtle UI feedback blip (e.g. on toggle)
  const playClick = () => {
    if (isMuted) return;
    initAudio();
    if (!audioCtx) return;
    try {
      const osc = audioCtx.createOscillator();
      const gain = audioCtx.createGain();
      osc.type = 'triangle';
      osc.frequency.setValueAtTime(600, audioCtx.currentTime);
      osc.frequency.exponentialRampToValueAtTime(800, audioCtx.currentTime + 0.08);
      gain.gain.setValueAtTime(0.15, audioCtx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.08);
      osc.connect(gain);
      gain.connect(audioCtx.destination);
      osc.start();
      osc.stop(audioCtx.currentTime + 0.08);
    } catch (e) {}
  };

  return {
    init: initAudio,
    startAlarm,
    stopAlarm,
    playToneBurst,
    toggleMute,
    playClick,
    isMuted: () => isMuted
  };
})();

// Automatically attempt audio context unlock on first user click anywhere
window.addEventListener('click', () => {
  AlertSound.init();
}, { once: true });
