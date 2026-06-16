/**
 * deep-link-handler.ts — URL routing for the Sentientia LMS native wrapper
 *
 * PURPOSE
 * ───────
 * Handles deep links from two sources:
 *   1. Push notification taps (via sentientia:deeplink DOM events from push-bridge.ts)
 *   2. External URL opens (e.g. WhatsApp links that open the app)
 *
 * URL SCHEMES SUPPORTED
 * ─────────────────────
 * Custom scheme:
 *   sentientia://course/{courseId}             → Course detail page
 *   sentientia://notification/{notificationId} → Dashboard notification anchor
 *   sentientia://dashboard                     → My dashboard
 *   sentientia://catalog                       → Course catalog
 *   sentientia://profile                       → User profile
 *
 * HTTPS Universal Links (iOS) / App Links (Android):
 *   https://www.airpay.academy/** → handled in-app
 *
 * CONFIGURATION REQUIRED (outside this file)
 * ──────────────────────────────────────────
 * iOS Universal Links: AASA file at https://www.airpay.academy/.well-known/apple-app-site-association
 * Android App Links: assetlinks.json at https://www.airpay.academy/.well-known/assetlinks.json
 * See BUILD.md §6 for full setup instructions.
 *
 * @module sentientia-app/deep-link-handler
 */

import { App, URLOpenListenerEvent } from '@capacitor/app';
import { Browser } from '@capacitor/browser';

const MOODLE_BASE_URL = 'https://www.airpay.academy';
const APP_SCHEME = 'sentientia';

interface Route {
  pattern: RegExp;
  buildUrl: (match: RegExpMatchArray) => string;
}

const ROUTES: Route[] = [
  {
    pattern: new RegExp(`^${APP_SCHEME}://course/(\\d+)$`),
    buildUrl: (m) => `${MOODLE_BASE_URL}/course/view.php?id=${m[1]}`,
  },
  {
    pattern: new RegExp(`^${APP_SCHEME}://notification/(\\d+)$`),
    buildUrl: (m) => `${MOODLE_BASE_URL}/my/dashboard.php#notification-${m[1]}`,
  },
  {
    pattern: new RegExp(`^${APP_SCHEME}://dashboard$`),
    buildUrl: () => `${MOODLE_BASE_URL}/my/dashboard.php`,
  },
  {
    pattern: new RegExp(`^${APP_SCHEME}://catalog$`),
    buildUrl: () => `${MOODLE_BASE_URL}/local/sentientia_catalog/`,
  },
  {
    pattern: new RegExp(`^${APP_SCHEME}://profile$`),
    buildUrl: () => `${MOODLE_BASE_URL}/user/profile.php`,
  },
  {
    // HTTPS App Link / Universal Link — pass through as-is
    pattern: new RegExp(`^https://www\\.airpay\\.academy/`),
    buildUrl: (m) => m[0],
  },
];

function navigateInApp(url: string): void {
  console.info('[deep-link-handler] Navigating to:', url);
  window.location.href = url;
}

async function openExternal(url: string): Promise<void> {
  console.info('[deep-link-handler] Opening external:', url);
  await Browser.open({ url });
}

async function routeUrl(url: string): Promise<boolean> {
  if (!url) { return false; }

  for (const route of ROUTES) {
    const match = url.match(route.pattern);
    if (match) {
      navigateInApp(route.buildUrl(match));
      return true;
    }
  }

  if (url.startsWith('https://') || url.startsWith('http://')) {
    await openExternal(url);
    return true;
  }

  console.warn('[deep-link-handler] Unrecognised URL scheme, ignoring:', url);
  return false;
}

/**
 * Initialise the deep-link handler.
 * Call once when the app loads — registers Capacitor + DOM event listeners.
 */
export function initDeepLinkHandler(): void {
  // Capacitor App URL open (from OS when app opened via external URL)
  App.addListener('appUrlOpen', async (event: URLOpenListenerEvent) => {
    await routeUrl(event.url);
  });

  // DOM event from push-bridge.ts (notification tap → deep link)
  document.addEventListener('sentientia:deeplink', async (event: Event) => {
    const url = (event as CustomEvent<{ url: string }>).detail?.url;
    if (url) { await routeUrl(url); }
  });

  // Handle launch URL (app opened via deep link while completely closed)
  App.getLaunchUrl().then((launchUrl) => {
    if (launchUrl?.url) {
      setTimeout(() => { routeUrl(launchUrl.url).catch(console.error); }, 500);
    }
  }).catch(console.error);

  console.info('[deep-link-handler] Deep-link handler initialised');
}
