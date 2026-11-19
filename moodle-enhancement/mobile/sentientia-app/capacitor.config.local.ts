import { CapacitorConfig } from '@capacitor/cli';

/**
 * Capacitor LOCAL DEV config — points to local XAMPP Moodle instance.
 *
 * Usage:
 *   cp capacitor.config.local.ts capacitor.config.ts
 *   npx cap sync
 *   npx cap open android   # or ios
 *
 * Never commit this file as capacitor.config.ts — it is a local-dev override.
 * The production config (capacitor.config.ts) holds a placeholder on the reserved .invalid
 * domain (corrected 2026-11-19: it pointed at the live BizLMS site).
 * The whole scaffold is superseded by ADR-040; see the status note in capacitor.config.ts.
 *
 * LOCAL URLs (the local XAMPP wwwroot is http://localhost:8080, with no /moodle path;
 * corrected 2026-11-19, these said .../moodle):
 *   Android emulator:  http://10.0.2.2:8080
 *   iOS Simulator:     http://localhost:8080
 *   Physical Android:  http://<your-machine-LAN-IP>:8080
 *   Physical iOS:      http://<your-machine-LAN-IP>:8080
 */
const config: CapacitorConfig = {
  appId: 'academy.airpay.sentientia',
  appName: 'Airpay Academy (Dev)',
  webDir: 'www',

  server: {
    url: 'http://10.0.2.2:8080',
    // url: 'http://localhost:8080',          // iOS Simulator
    // url: 'http://192.168.1.5:8080',        // Physical device (set your LAN IP)
    cleartext: true, // ONLY for local HTTP dev — never in production config
  },

  plugins: {
    PushNotifications: {
      presentationOptions: ['badge', 'alert', 'sound'],
    },
    SplashScreen: {
      launchShowDuration: 1000,
      launchAutoHide: true,
      backgroundColor: '#F2F4FB',
    },
    StatusBar: {
      overlaysWebView: false,
      style: 'DARK',
      backgroundColor: '#0066A7',
    },
  },
};

export default config;
