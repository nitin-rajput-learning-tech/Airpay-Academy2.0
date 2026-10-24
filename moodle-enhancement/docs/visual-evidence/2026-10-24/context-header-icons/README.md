# Context-header Message / contact buttons: core's `t/` pix names (theme 2026102400 / 1.0.61-beta)

## The defect

On UAT, `/message/notificationpreferences.php` and `/user/preferences.php` (and every page whose context header shows
a user) requested `/theme/image.php/sentientia/core/<rev>/message`, which returns 404. The broken image was the icon of
the header's "Message" button:

    img.icon.iconsmall < span < a#message-user-button.btn < div.btn-group.header-button-group < div.page-context-header

This was found by a read-only console sweep of Meera's UAT session in Chrome. The local 5.3 sweep saw the same
"Failed to load resource: 404" on both pages.

`classes/output/traits/context_header.php` is an older copy of core's code. It passed the button images as `message`,
`addcontact` and `removecontact`. Core's `context_header::format_button_images()` (identical on 5.1 and 5.3) tries
`resolve_image_location('t/' . image)`. That call does not look for SVGs by default, and 5.x ships only
`pix/t/*.svg`. So the bare name was treated as an external image, and `pix_icon('message')` asked for an image that
does not exist.

## The fix

The theme passes `t/message`, `t/addcontact` and `t/removecontact`, exactly as core 5.3's `core_renderer::context_header()`
does. These resolve through the icon map (Font Awesome).

## Verified (local 5.3 runtime, e2e_learner)

| Page | Before | After |
|---|---|---|
| /message/notificationpreferences.php | console: 1 x 404 | 0 errors |
| /user/preferences.php | console: 1 x 404 | 0 errors; Message button shows its speech-bubble icon (`after-user-preferences-desktop.png`) |

The local tree's notification preferences page also prints `messageprovider:notification_alert` debug output. That
tree predates c3cc82ba6, which added the string. The repo and UAT both have it, so this is not a new defect.
