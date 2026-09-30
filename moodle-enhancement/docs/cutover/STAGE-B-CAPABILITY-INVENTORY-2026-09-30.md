# Stage B capability inventory and the signed allow-list (2026-09-30)

ADR-032, section "Capabilities". This records what the capability review saw on the April 2026
production dump, the decisions the owner made from it, and what is still to do before the real cutover.

## Where the inventory came from

- Source: the April 2026 production dump, restored to a local rehearsal copy and taken through the two
  core hops (Moodle 4.1.2 to 4.5.10 to 5.1.3) with the Sentientia plugins installed.
- Command, inventory mode (writes nothing), cwd = the rehearsal site's `public\`:
  `php local/sentientia_platform/cli/repair_bizlms_capabilities.php`
- Raw output: `D:\Claude Local\rehearsal\cap_inventory.txt` (outside the repository; it holds no personal data,
  only role shortnames, capability names and permissions).
- Result: **520 role grants**, every one at system context (context id 1, level 10), on capabilities of
  **32 plugins that are not on disk**. 421 are ALLOW, 71 PREVENT, 28 PROHIBIT.

## Counts

Grants per role: administrator 214, manager 110, trainer 79, editingteacher 39, employee 21, user 19,
teacher 16, guest 11, coursecreator 10, frontpage 1 (total 520).

Grants per missing component:

| Group | Component (grants) |
|-------|--------------------|
| Blocks (8) | block_userdashboard 13, block_quick_navigation 12, block_trainerdashboard 8, block_myskills 6, block_masterinfo 5, block_trending_modules 4, block_achievements 3, block_suggested_courses 1 |
| Enrol methods (3) | enrol_program 12, enrol_learningplan 12, enrol_classroom 12 |
| BizLMS local plugins (21) | local_classroom 77, local_request 60, local_evaluation 33, local_skillrepository 30, local_courses 28, local_costcenter 27, local_program 25, local_forum 21, local_learningplan 21, local_biz_cart 18, local_onlineexams 18, local_users 17, local_notifications 12, local_recompletion 9, local_assignroles 8, local_search 8, local_groups 6, local_tags 5, local_custom_category 4, local_location 3, local_ratings 2 |

The 22nd BizLMS local plugin of the production snapshot, `local_myteam`, holds no grant in this dump.

Of the 520 rows, 19 are on one of the ten capabilities that have a Sentientia equivalent
(`capability_repair::MAP`). The other 501 have none.

| Role | Mapped capability (equivalent) | Permission | State in the inventory |
|------|--------------------------------|-----------|------------------------|
| manager | costcenter:view, courses:enrol, courses:manage, users:bulkstatuschange, users:edit | ALLOW | equivalent already held (manager archetype) |
| manager | costcenter:manage, costcenter:manage_multiorganizations | ALLOW | equivalent never granted by the script (ADR-031) |
| manager | costcenter:manage_ownorganization, costcenter:manage_owndepartments | ALLOW | equivalent not held (no archetype, ADR-031) |
| administrator | costcenter:view, courses:enrol, courses:manage, users:bulkstatuschange, users:edit, classroom:manageclassroom | ALLOW | equivalent already held (manager archetype) |
| administrator | costcenter:manage | ALLOW | equivalent never granted by the script (ADR-031) |
| administrator | costcenter:manage_ownorganization | ALLOW | equivalent not held (no archetype, ADR-031) |
| trainer | classroom:manageclassroom | ALLOW | equivalent not held |
| trainer | users:edit | PREVENT | equivalent not held |

The administrator role holds neither `manage_owndepartments` nor `manage_multiorganizations` on this dump.
No mapped capability is held at category or course context, and no other mapped capability carries a
PREVENT or PROHIBIT.

## The three decided rows

Decided by Nitin Rajput on 2026-09-30 ("do everything as recommended") and written into
`moodle-enhancement/docs/cutover/bizlms-capability-allowlist.json`:

1. **Grant** `trainer`, system context, `local/classroom:manageclassroom` to
   `local/sentientia_classroom:manage`, ALLOW. BizLMS trainers manage classrooms on production today
   (CLAUDE.md: never break current production behaviour). Their attendance stays limited to their own
   sessions because the attendance rule keys on `local/sentientia_classroom:update` (merged 2026-09-30).
2. **Decline** `trainer`, system context, `local/users:edit` (PREVENT). Trainers hold no
   `local/sentientia_users:edit`, so the PREVENT has nothing to narrow.
3. **Keep the four ADR-031 declines**: `manager` and `administrator`, system context,
   `local/costcenter:manage_ownorganization` and `:manage_owndepartments`. ADR-031 withholds organisation
   create, edit and delete from tenant admins. (`administrator` holds no `manage_owndepartments` on this
   dump, so that one decline matches no row; the script reports it as a note, not an error.)

Also decided: a component decline for every missing plugin (33): the 22 BizLMS `local_*` plugins, the
eight blocks and the three enrol methods. Reason recorded: code not deployed; no Sentientia equivalent (or,
for the `local_*` plugins, the Sentientia archetypes grant the replacements); enrolments on the three
BizLMS enrol methods are converted to manual enrolments by the G6 import step. A component decline covers
only capabilities with no Sentientia equivalent, so the ten mapped capabilities above are decided per role,
not by plugin.

Both open decisions of the draft are resolved in the file (`open_decisions` is empty; the resolutions are
under `resolved_decisions`).

## Check-mode result

Command (cwd `D:\Claude Local\rehearsal\moodle51\public`, writes nothing):

```
php local/sentientia_platform/cli/repair_bizlms_capabilities.php --allowlist="D:/Claude Local/airpay-ld-os/moodle-enhancement/docs/cutover/bizlms-capability-allowlist.json"
```

- Exit code **0**: `RESULT: every grant is decided (exit 0)`.
- Allow-list accepted: approved by Nitin Rajput on 2026-09-30, 1 grant, 38 declines (33 by component, 5 by
  role grant), sha256 `22f98a0f6c66f1ab937e96344d59ec407b1f5b8adc4d63b0f813eb653e7d733d`.
- `--apply` would make exactly **one grant**: `local/sentientia_classroom:manage` to `trainer` in context 1,
  permission 1, from `local/classroom:manageclassroom`.
- Three rows withheld by the script (ADR-031): manager `costcenter:manage`, manager
  `costcenter:manage_multiorganizations`, administrator `costcenter:manage`.
- Two decline lines matched no row (notes, not errors): `administrator local/costcenter:manage_owndepartments`
  and `local_myteam`. They stay in the file on purpose: the real live backup may hold them.
- Nothing was written and `--apply` was not run. The rehearsal copy was only read.

The sha256 above is the hash of the file as signed; any edit changes it and needs a new signature.
`tools/check-bizlms-fixture-copies.php` keeps the two test copies equal to the signed file.

## Before the real cutover: re-inventory the live backup

This allow-list is built from the April 2026 dump, not from the live database as it will be at cutover. Roles
and overrides on live may have changed since April. At the Stage B rehearsal, on the restored real live backup:

1. Run the inventory again (no options) and read it.
2. Run the check against the signed file. If the exit code is 0 and the only grant is still the trainer one,
   the signature stands.
3. If the exit code is 2 (new undecided grants), or a role holds `manageclassroom`, a mapped capability at a
   category or course context, or a PREVENT or PROHIBIT on a mapped capability that is not in the table above,
   Nitin decides those rows and the file is re-signed, then copied over both test fixtures
   (`php tools/check-bizlms-fixture-copies.php` must print OK).
4. A refused line (exit 1) or a guard refusal (exit 3) is a stop, not a workaround.

`--apply` needs `--confirm=<fingerprint>` and maintenance mode, and runs first in the cutover slice, before
anything reads a role.
