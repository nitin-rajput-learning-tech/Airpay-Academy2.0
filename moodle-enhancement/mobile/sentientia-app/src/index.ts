/**
 * index.ts -- Sentientia LMS native wrapper entry point
 *
 * This module is the single entry point for the Capacitor native bridge code.
 * It wires together:
 *   - push-bridge.ts -- native FCM/APNs token registration via existing Moodle WS
 *   - deep-link-handler.ts -- App Link / Universal Link / sentientia:// routing
 *
 * REMOTE URL MODE (production default)
 * In remote URL mode, Capacitor injects its JS bridge into the live Moodle
 * page loaded from https://www.airpay.academy. This compiled bundle can be
 * loaded by the Moodle theme via an AMD module from the airpayux theme,
 * or via a <script> tag injected by core_renderer.php when the native
 * Capacitor bridge is detected on the page:
 *
 *   // In core_renderer.php or theme head:
 *   if (strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'SentientiaApp') !== false) {
 *       $PAGE->requires->js('/local/sentientia_pwa/capacitor-native.js');
 *   }
 *
 * LOCAL BUNDLE MODE
 * www/index.html redirects to the production URL -- this module is not used
 * in that flow. It is compiled for remote URL mode only.
 *
 * INITIALISATION TIMING
 * Both bridges depend on the Moodle page session being active (M.cfg.sesskey
 * present). We wait for DOMContentLoaded to ensure Moodle's JS has run.
 *
 * NO CREDENTIALS IN THIS FILE.
 *
 * @module sentientia-app/index
 */

import { initPushBridge } from './push-bridge';
import { initDeepLinkHandler } from './deep-link-handler';

declare const Capacitor: { isNativePlatform: () => boolean } | undefined;

/**
 * Bootstrap both native bridges.
 * Safe to call in a browser context -- each bridge self-guards against
 * running on web and exits early without side effects.
 */
async function bootstrap(): Promise<void> {
  // Guard: only run when Capacitor native bridge is present.
  if (typeof Capacitor === 'undefined' || !Capacitor.isNativePlatform()) {
    // Web browser -- local_sentientia_pwa handles push subscriptions via
    // amd/src/subscribe.js (VAPID Web Push path). No native bridge needed.
    return;
  }

  console.info('[sentientia-app] Capacitor native bridge detected -- bootstrapping.');

  // Deep link handler first -- registers OS URL open listener before push
  // registration completes (avoids a race where a notification tap fires
  // before the listener is attached).
  initDeepLinkHandler();

  // Push notification bridge -- requests permission, registers FCM/APNs token,
  // and wires notification tap -> deep-link-handler events.
  await initPushBridge();

  console.info('[sentientia-app] Native bridges ready.');
}

// Wait for Moodle page to fully load (M.cfg.sesskey must be available).
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => { bootstrap().catch(console.error); });
} else {
  bootstrap().catch(console.error);
}

export { initPushBridge, initDeepLinkHandler };
