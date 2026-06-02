# Visual evidence — 2026-06-02

## eAbyas/epsilon theme decoupling — pixel-confirmation

After the file-serve decoupling (lib.php → `load('airpayux')`, 7 setting files migrated
`theme_epsilon`→`theme_airpayux`) + `theme/epsilon` deletion, the logged-in **dashboard**
(`/my/dashboard.php`, user nitinrajput17) was loaded in-browser:

- ✅ Page renders fully styled (airpayux theme intact — sidebar, cards, search, gradients).
- ✅ The **"airpay academy" navbar logo renders** (no broken image / 404).
- ✅ No layout breakage from the decoupling.

This is the **pixel-level belt-and-suspenders** on top of the authoritative proof:
- File API (4/4): all 7 setting fileareas resolve under `theme_airpayux`.
- File-migration rehearsal (6/6): clean component re-point, blobs preserved.
- Epsilon-delete health check: airpayux loads, login page HTTP 200.

Note: the live local **login page uses the standard username/password form** (not the OTP
phone form) because `auth_otp` is **not installed on this local env** (active auth = `email`).
Production has `auth_otp` → the OTP login. Theme login templates were not changed (only a
docblock comment). See the OTP explanation in the session log.

## Block D (dark-mode AA-contrast pass) — DEFERRED

Not completed this session: the Claude Chrome extension lost host permission on
`localhost:8080` mid-session (timeout → internal error → permission-lost in sequence) right
after login, before the dark-mode toggle could be exercised. Re-run when the extension is
stable: log in → click the sidebar **Dark Mode** toggle → screenshot desktop + 590px →
check text/background AA contrast → save here.
