import { CapacitorConfig } from '@capacitor/cli';

/**
 * Capacitor production config — Remote URL mode.
 *
 * REMOTE URL MODE (this file) vs LOCAL BUNDLE MODE (capacitor.config.local.ts):
 *
 * Remote URL mode:
 *   - The app shell loads the PWA directly from https://www.airpay.academy
 *   - Native plugins (push, status-bar, splash) still work — Capacitor injects
 *     the bridge into the remote WebView
 *   - No separate JS/CSS build step required in this repo
 *   - App updates deploy instantly with Moodle — no App Store resubmission
 *   - Requires the device to be online to load the app
 *   - This is the RECOMMENDED mode for production (mirrors PWA-on-device behaviour)
 *
 * Local bundle mode (see capacitor.config.local.ts):
 *   - www/index.html is bundled into the APK/IPA
 *   - Useful for fully offline-first scenarios (not currently planned)
 *   - Requires a build step and re-sync on every content update
 *   - Use only if a future customer needs guaranteed offline-first launch
 *
 * SECURITY NOTE: server.cleartext is false (enforced). The app MUST use HTTPS
 * for production. If a future customer runs on HTTP (e.g. staging), add their
 * domain to the Android network_security_config.xml allowlist — never set
 * cleartext: true globally.
 */
const config: CapacitorConfig = {
  appId: 'academy.airpay.sentientia',
  appName: 'Airpay Academy',
  webDir: 'www',

  server: {
    // Remote URL mode: load the live PWA into the native WebView.
    // The Capacitor bridge is injected automatically by the plugin runtime.
    // See BUILD.md §Remote URL vs Local Bundle for trade-offs.
    url: 'https://www.airpay.academy',
    cleartext: false,
  },

  plugins: {
    PushNotifications: {
      presentationOptions: ['badge', 'alert', 'sound'],
    },

    SplashScreen: {
      launchShowDuration: 2000,
      launchAutoHide: true,
      backgroundColor: '#F2F4FB',
      androidSplashResourceName: 'splash',
      androidScaleType: 'CENTER_CROP',
      showSpinner: false,
      splashFullScreen: true,
      splashImmersive: true,
    },

    StatusBar: {
      overlaysWebView: false,
      style: 'DARK',
      backgroundColor: '#0066A7',
    },
  },
};

export default config;
