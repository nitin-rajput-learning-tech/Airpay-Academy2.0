/**
 * push-bridge.ts — Capacitor push notification bridge for Sentientia LMS
 *
 * PURPOSE
 * ───────
 * The existing local_sentientia_pwa plugin (Phase B.2–B.3) implements web push
 * via the standard VAPID/RFC-8291 stack:
 *   - Browser calls PushManager.subscribe() → gets endpoint + p256dh + auth
 *   - POSTed to `local_sentientia_pwa_save_subscription` WS (see db/services.php)
 *   - Moodle sends push via FCM/APNs WPS bridges using hand-rolled ES256/AES-GCM
 *     crypto (ADR-003, docs/audits/B25-CRYPTO-AUDIT-2026-05-21.md)
 *
 * In the native wrapper, Capacitor PushNotifications provides FCM (Android) and
 * APNs (iOS) tokens directly — bypassing the browser's PushManager. This bridge:
 *   1. Listens for the Capacitor push registration event
 *   2. Posts the FCM/APNs token to the same Moodle WS endpoint using a sentinel
 *      URL (sentientia-native://push/{platform}/{token})
 *   3. Handles foreground notification display
 *   4. Routes notification taps to the deep-link handler
 *
 * BACKEND EXTENSION REQUIRED
 * ──────────────────────────
 * The Moodle backend needs a small extension to accept native tokens alongside
 * Web Push endpoints. See BUILD.md §5.3 for the required changes to
 * save_subscription.php and push_sender.php.
 *
 * NO CREDENTIALS IN THIS FILE. All API keys are:
 *   - Android (FCM): google-services.json at android/app/ (see BUILD.md)
 *   - iOS (APNs): Apple Developer Portal + Xcode entitlements
 *   - Moodle session: shared via WebView cookie (remote URL mode)
 *
 * @module sentientia-app/push-bridge
 */

import {
  PushNotifications,
  PushNotificationSchema,
  Token,
  ActionPerformed,
} from '@capacitor/push-notifications';
import { App } from '@capacitor/app';

declare const Capacitor: { getPlatform: () => 'ios' | 'android' | 'web' };

const MOODLE_SAVE_SUBSCRIPTION_WS = 'local_sentientia_pwa_save_subscription';

/**
 * Build a sentinel endpoint URL for native push tokens.
 * The backend detects the sentientia-native:// scheme and routes to FCM/APNs
 * instead of the VAPID Web Push encryption pipeline.
 */
function buildNativeEndpoint(platform: 'ios' | 'android', token: string): string {
  return `sentientia-native://push/${platform}/${encodeURIComponent(token)}`;
}

/**
 * Register the native push token with the Moodle subscription endpoint.
 * The p256dh and auth fields are empty — the backend detects the native scheme
 * and skips VAPID validation.
 */
async function registerTokenWithMoodle(token: Token): Promise<void> {
  const platform = Capacitor.getPlatform() as 'ios' | 'android';
  const endpoint = buildNativeEndpoint(platform, token.value);

  // Retrieve sesskey from Moodle (injected into DOM by Moodle core)
  const sesskey = (window as Record<string, unknown>).M?.cfg?.sesskey as string | undefined;
  if (!sesskey) {
    console.error('[push-bridge] M.cfg.sesskey not found — user may not be logged in');
    return;
  }

  try {
    const response = await fetch('/lib/ajax/service.php?sesskey=' + encodeURIComponent(sesskey), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify([{
        index: 0,
        methodname: MOODLE_SAVE_SUBSCRIPTION_WS,
        args: {
          endpoint: endpoint,
          p256dh: '', // Native token — VAPID keys not applicable
          auth: '',   // Native token — VAPID auth not applicable
          user_agent: navigator.userAgent.substring(0, 250),
        },
      }]),
      credentials: 'include', // Include session cookie
    });

    const data = await response.json();
    if (data?.[0]?.error) {
      console.error('[push-bridge] Moodle WS error:', data[0].exception?.message);
    } else {
      console.info('[push-bridge] Native push token registered. ID:', data?.[0]?.data?.id);
    }
  } catch (err) {
    console.error('[push-bridge] Failed to register push token with Moodle:', err);
  }
}

/**
 * Handle a foreground push notification.
 * Dispatches a DOM event so Moodle page JS can show a toast/banner.
 */
function handleForegroundNotification(notification: PushNotificationSchema): void {
  console.info('[push-bridge] Foreground notification received:', notification.title);
  document.dispatchEvent(new CustomEvent('sentientia:push:foreground', {
    detail: { title: notification.title, body: notification.body, data: notification.data },
    bubbles: true,
  }));
}

/**
 * Handle a notification tap — routes to the deep-link handler via DOM event.
 */
function handleNotificationTap(action: ActionPerformed): void {
  const data = action.notification.data as Record<string, string> | undefined;
  const url = data?.url;
  if (!url) { return; }
  document.dispatchEvent(new CustomEvent('sentientia:deeplink', {
    detail: { url },
    bubbles: true,
  }));
}

/**
 * Initialise the push notification bridge.
 * Call once after the Moodle session is active.
 * In remote URL mode, Capacitor makes window.Capacitor available on the Moodle
 * page JS context — no additional setup needed.
 */
export async function initPushBridge(): Promise<void> {
  // Only initialise on native — web uses local_sentientia_pwa/amd/src/subscribe.js
  if (typeof Capacitor === 'undefined' || Capacitor.getPlatform() === 'web') {
    console.info('[push-bridge] Web platform — native push bridge not needed.');
    return;
  }

  const permResult = await PushNotifications.requestPermissions();
  if (permResult.receive !== 'granted') {
    console.warn('[push-bridge] Push permission denied');
    return;
  }

  await PushNotifications.register();

  PushNotifications.addListener('registration', (token: Token) => {
    registerTokenWithMoodle(token).catch(console.error);
  });

  PushNotifications.addListener('registrationError', (error) => {
    console.error('[push-bridge] Push registration failed:', error);
  });

  PushNotifications.addListener('pushNotificationReceived', handleForegroundNotification);
  PushNotifications.addListener('pushNotificationActionPerformed', handleNotificationTap);

  // Also catch URL opens when app was completely closed
  App.addListener('appUrlOpen', (event) => {
    document.dispatchEvent(new CustomEvent('sentientia:deeplink', {
      detail: { url: event.url },
      bubbles: true,
    }));
  });

  console.info('[push-bridge] Native push bridge initialised on', Capacitor.getPlatform());
}
