# External Public Learner user guide — Sentientia LMS / Airpay Academy

**Audience:** Public-tenant self-registered learner (Public tenant id=77 — anyone with internet access, NOT an Airpay employee).
**Status:** v1 draft (2026-05-24).
**Cross-references:** `learner.md` (employee-side experience), `site-admin.md`.

---

## 1. Self-registration

The Public tenant exists for external learners. Sign-up at:

`/login/signup.php`

You'll need:

- A valid email address (we'll send a confirmation link)
- A name (first + last)
- A password meeting the policy (12+ characters, mix of cases + a digit + a symbol)
- Acceptance of the Privacy Policy + Terms & Conditions (see §2)

After form submit, check your email — click the confirmation link to
activate the account. Then log in normally at `/login/index.php`.

If you don't get the email within 5 minutes:
- Check spam
- Verify you typed the email correctly
- Try the "Resend confirmation" link on the login page

---

## 2. Privacy policy + T&C consent

Before sign-up completes, you must read + accept:

- **Privacy Policy** — `/local/airpay_users/privacypolicy.php`. Covers:
  - What we collect (name, email, course progress, payment history)
  - Where it's stored (AWS Mumbai region; DPDP Act 2023 compliant)
  - When it's deleted (24 months after last login, unless paid courses extend retention)
  - Who we share with (NO third-party advertising; only Razorpay/Airpay
    for payment processing)

- **Terms & Conditions** — `/local/airpay_users/termscondition.php`.
  Covers: refund policy, course access duration, intellectual property
  (content stays our copyright; your submissions are yours).

Both pages link to a "I've read + I accept" checkbox at the bottom of
the sign-up form. You can also re-read them anytime from the footer of
any page.

You can request your data anytime: `/admin/tool/dataprivacy/myrequests.php`
(opens after you're logged in).

---

## 3. Catalogue browsing

`/local/airpay_catalog/index.php`

As an external learner, you see ONLY courses marked "Public" by their
Course Author. This is typically:

- Foundational courses (intro to a topic)
- Open community webinars / recorded talks
- Skill-test prep packs
- Paid certification courses

Filters: category, language (English / Hindi), price (free / paid).

---

## 4. Paid course purchase flow

Some Public courses are paid. The flow:

1. Click "Enrol" on the catalogue card
2. You're redirected to `/local/airpay_cart/cart.php` — review your order
3. Click "Proceed to checkout"
4. Pick payment method — Airpay payment gateway (paygw_airpay) supports
   UPI, credit/debit card, net banking, and wallet payments. Currencies:
   INR (default for India learners), USD, EUR, plus ~22 others
5. Complete payment on the Airpay-hosted page
6. On success, you're redirected back to the course; access is immediate

### Receipts

Every successful payment generates an invoice (download from
`/local/airpay_cart/history.php`). For business expense claims, the
invoice has GST line items where applicable.

### Failed payments

If a payment fails, you're returned to the cart with the error message
and the cart contents preserved. Retry with a different payment method
or contact support (see §7).

---

## 5. Course access after purchase

Once paid:

- The course shows up in "My Courses" (`/my/dashboard.php`)
- Access duration depends on the course — typically 12 months from
  purchase date (shown in your purchase confirmation email)
- During the access period, you can replay videos, retake quizzes,
  and download any resources

After the access period ends:
- Course disappears from "My Courses" (but your completion record + cert stays)
- You can repurchase if you want continued access
- Your earned badges + certificates remain valid forever

---

## 6. Certificate download

When you complete a course:

`/badges/mybadges.php` — click the badge → "Download certificate" button

PDFs include:
- Your name (use your accurate name when signing up — this is what shows on certs)
- The course title
- Completion date
- A unique verification ID (anyone can verify at
  `/admin/tool/certificate/verify.php?code=<id>`)

Print-quality. Add to LinkedIn under "Licenses & certifications" — paste
the verification URL as the credential URL.

---

## 7. Limitations (vs. Airpay employee learners)

Some Sentientia features are NOT available to Public learners:

| Feature | Public learner | Airpay employee |
|---------|----------------|------------------|
| Employee directory | ❌ | ✅ (within tenant) |
| Skill self-rating + manager validation | ❌ (no manager) | ✅ |
| Internal compliance training | ❌ | ✅ |
| Cohort-driven enrolment | ❌ | ✅ |
| Welcome-email tokens (name, manager) | Partial — name only | Full |
| WhatsApp notifications | ❌ (no India phone enforcement for global learners) | ✅ |
| Push notifications (PWA) | ✅ | ✅ |
| Hindi UI | ✅ | ✅ |
| Browser-based exam proctoring | ✅ (per-course) | ✅ (per-course) |

---

## 8. Support

| Need | Where |
|------|-------|
| Forgot password | `/login/forgot_password.php` (works any time) |
| Refund request (within 7 days of purchase) | `/local/airpay_cart/refund_request.php` |
| Cert verification | Anyone can verify at `/admin/tool/certificate/verify.php?code=<id>` |
| Course content issue | "Report" button inside any activity |
| General help | Footer "Help" link → public help centre |

---

## 9. Account deletion

You can delete your account anytime: `/admin/tool/dataprivacy/myrequests.php`
→ "Delete my account".

What happens:
- Login is disabled immediately
- Personal data is erased after 30 days (cooling-off window in case of
  accidental request)
- Completed courses + certificates: anonymised but the verification record
  stays (so issued certs remain verifiable; just no link back to you)
- Outstanding paid course access: forfeited (no refund for unused access
  period after account deletion)

---

| Version | Date | Author | Notes |
|---------|------|--------|-------|
| v1 draft | 2026-05-24 | Claude (autonomous night-run) | Initial scaffold |
