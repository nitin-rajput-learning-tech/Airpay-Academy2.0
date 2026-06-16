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
 * The production config (capacitor.config.ts) points to https://www.airpay.academy.
 *
 * LOCAL URLs:
 *   Android emulator:  http://10.0.2.2:8080/moodle
 *   iOS Simulator:     http://localhost:8080/moodle
 *   Physical Android:  http://<your-machine-LAN-IP>:8080/moodle
 *   Physical iOS:      http://<your-machine-LAN-IP>:8080/moodle
 */
const config: CapacitorConfig = {
  appId: 'academy.airpay.sentientia',
  appName: 'Airpay Academy (Dev)',
  webDir: 'www',

  server: {
    url: 'http://10.0.2.2:8080/moodle',
    // url: 'http://localhost:8080/moodle',          // iOS Simulator
    // url: 'http://192.168.1.5:8080/moodle',        // Physical device (set your LAN IP)
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
