# Visual evidence - 2026-09-29 (cart: ADR-031 decision 3 follow-up)

**Status: screenshots NOT yet captured.** This change was made in low-CPU mode with no local
Moodle or browser session (XAMPP not touched), so CLAUDE.md s.13 stays OPEN for it until the next
browser pass captures desktop (1440) + mobile (590px) screenshots into this folder.

## What changed (user-visible)

Branch `claude/adr031-cart-gate`, plugin `local_sentientia_cart` (both trees). See
`moodle-enhancement/state-cards/sentientia_cart-state.md`, 2026-09-29.

1. `/local/sentientia_cart/admin_orders.php`: a new "Staff notes" column (`history.notes`), showing
   gateway failure reasons and the ADR-031 "Refund due" line for a paid order whose enrolment was
   withheld. Visible to `:viewallorders` holders only.
2. Buyer notification `payment_received`: lists only the courses the buyer was enrolled in; a
   withheld course is not listed, and a line says it cannot be accessed and will be refunded.
3. Site-admin notification `admin_new_order`: subject ends " - Refund due", body names the order and
   the withheld course id(s), when anything was withheld.
4. `/local/sentientia_cart/checkout.php`: a gateway error after the order went to 'pending' is shown
   on the checkout page again (it was being replaced by a redirect to an empty cart).

## Screen-check list (persona -> page -> expected)

1. [ ] [Site admin] admin_orders.php: the Staff notes column is present; a paid order with a withheld
   line reads "ADR-031: payment recorded, enrolment withheld for course id(s) N ... Refund due."
2. [ ] [Tenant admin /1, :viewallorders] admin_orders.php: the column shows notes for /1 orders only.
3. [ ] [Learner /1] Notifications popup after paying an order where one line was withheld: only the
   granted course is listed, plus the "no longer available ... will be refunded" line.
4. [ ] [Site admin] Notifications popup for the same order: "New order #N - Refund due" and the
   refund-due line with the course id.
5. [ ] [Learner] checkout.php with the gateway misconfigured: the gateway error is shown on the
   checkout page, not "Your cart is empty".

Filenames `NN-<persona>-<page>.png`, light mode.
