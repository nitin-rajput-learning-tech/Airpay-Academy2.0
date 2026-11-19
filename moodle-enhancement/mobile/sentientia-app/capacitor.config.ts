import { CapacitorConfig } from '@capacitor/cli';

/**
 * Capacitor production config — Remote URL mode.
 *
 * STATUS (corrected 2026-11-19): this scaffold was never built and is superseded by
 * ADR-040 (docs/adr/ADR-040-native-mobile-app.md: Flutter or KMP, chosen by spike). Do not
 * build or ship it. server.url below pointed at the LIVE BizLMS site (Moodle 4.1.2), which
 * Sentientia replaces only at cutover. It now holds a
 * placeholder on the reserved .invalid domain (RFC 6761), which never resolves, so a build
 * made by mistake loads nothing. Put the Sentientia site there only for a test build
 * against UAT, and never the live BizLMS address.
 *
 * REMOTE URL MODE (this file) vs LOCAL BUNDLE MODE (capacitor.config.local.ts):
 *
 * Remote URL mode:
 *   - The app shell loads the PWA directly from the Sentientia site in server.url
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
    // Remote URL mode: load the Sentientia PWA into the native WebView.
    // The Capacitor bridge is injected automatically by the plugin runtime.
    // See BUILD.md §Remote URL vs Local Bundle for trade-offs.
    // Placeholder (2026-11-19): was the live BizLMS address. See the status note above.
    url: 'https://sentientia-site.invalid',
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
