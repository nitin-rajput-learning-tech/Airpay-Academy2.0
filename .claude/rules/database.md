# Database Rules — Moodle $DB API (MariaDB 10.11.16 / Moodle 4.5.10)
# ALWAYS LOADED when working on PHP files containing DB queries.

---

## The One Rule That Prevents Every DB Bug

```
NEVER build SQL strings with string concatenation.
NEVER use PDO or mysqli directly.
ALWAYS use $DB API with {tablename} placeholders.
ALWAYS add timecreated + timemodified to every new table.
ALWAYS add costcenterid to tables storing multi-tenant data.
```

---

## Complete $DB API Reference (Airpay Patterns)

### SELECT — Single Record
```php
// Returns record or throws exception (use for IDs you trust exist)
$user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

// Returns record or false (use for optional lookups)
$record = $DB->get_record('local_pluginname_data', ['userid' => $userid]);
if (!$record) { /* handle missing */ }

// Specific fields only (faster — less data transferred)
$email = $DB->get_field('user', 'email', ['id' => $userid], MUST_EXIST);
$count = $DB->count_records('course_completions', ['userid' => $userid]);
$exists = $DB->record_exists('local_pluginname_data', ['userid' => $userid]);
```

### SELECT — Multiple Records
```php
// Simple conditions — returns array indexed by primary key (id)
$courses = $DB->get_records('course', ['visible' => 1], 'fullname ASC');

// With ORDER BY and LIMIT (page 2, 20 records per page)
$users = $DB->get_records('user', ['suspended' => 0, 'deleted' => 0],
    'lastname ASC, firstname ASC', '*', 20, 20);  // offset=20, limit=20

// Custom SQL — use :named params, never string concat
$records = $DB->get_records_sql(
    "SELECT u.id, u.firstname, u.lastname, cc.timecompleted
       FROM {user} u
  LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = :courseid
      WHERE u.deleted = 0
        AND u.profile_field_costcenterid = :cid
   ORDER BY cc.timecompleted DESC",
    ['courseid' => $courseid, 'cid' => $costcenterid]
);

// Single value from SQL
$totalenrolled = $DB->get_field_sql(
    "SELECT COUNT(ue.id) FROM {user_enrolments} ue
     JOIN {enrol} e ON e.id = ue.enrolid
     WHERE e.courseid = :cid",
    ['cid' => $courseid]
);
```

### SELECT — IN Clause (Never use implode)
```php
// ❌ NEVER DO THIS (SQL injection risk + breaks with empty array)
$ids = [1, 2, 3];
$DB->get_records_sql("SELECT * FROM {user} WHERE id IN (" . implode(',', $ids) . ")");

// ✅ ALWAYS use get_in_or_equal()
[$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'id');
$users = $DB->get_records_select('user', "id $insql", $params);

// With additional params:
[$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'uid');
$params = array_merge($inparams, ['cid' => $costcenterid]);
$records = $DB->get_records_select('local_pluginname_data',
    "userid $insql AND costcenterid = :cid", $params);

// Handle empty array safely:
if (empty($ids)) { return []; }  // guard before get_in_or_equal
[$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'id');
```

### LIKE Clause (Must escape wildcards)
```php
// ❌ WRONG — user input wildcards become search operators
$DB->get_records_sql("SELECT * FROM {course} WHERE fullname LIKE :s", ['s' => '%' . $search . '%']);

// ✅ CORRECT — escape user input first
$search_escaped = $DB->sql_like_escape($search);
$DB->get_records_sql(
    "SELECT * FROM {course} WHERE " . $DB->sql_like('fullname', ':s', false),
    ['s' => '%' . $search_escaped . '%']
);
// Third param false = case-insensitive (usually what we want)
```

### INSERT
```php
$record = new stdClass();
$record->userid        = $userid;
$record->courseid      = $courseid;
$record->costcenterid  = $costcenterid;   // ALWAYS for multi-tenant tables
$record->status        = 'active';
$record->timecreated   = time();           // ALWAYS
$record->timemodified  = time();           // ALWAYS
$id = $DB->insert_record('local_pluginname_data', $record);
// $id is the new record's integer ID
```

### UPDATE
```php
// $record MUST have ->id property
$record = $DB->get_record('local_pluginname_data', ['userid' => $userid], '*', MUST_EXIST);
$record->status       = 'completed';
$record->timemodified = time();            // ALWAYS update timemodified
$DB->update_record('local_pluginname_data', $record);

// Or update a specific field without fetching first:
$DB->set_field('local_pluginname_data', 'status', 'completed',
    ['userid' => $userid, 'courseid' => $courseid]);
```

### INSERT OR UPDATE Pattern
```php
// Most common need: update if exists, insert if not
function save_user_progress(int $userid, int $courseid, string $status): int {
    global $DB;
    $existing = $DB->get_record('local_pluginname_data',
        ['userid' => $userid, 'courseid' => $courseid]);

    if ($existing) {
        $existing->status       = $status;
        $existing->timemodified = time();
        $DB->update_record('local_pluginname_data', $existing);
        return $existing->id;
    }

    $record = new stdClass();
    $record->userid       = $userid;
    $record->courseid     = $courseid;
    $record->status       = $status;
    $record->timecreated  = time();
    $record->timemodified = time();
    return $DB->insert_record('local_pluginname_data', $record);
}
```

### DELETE
```php
$DB->delete_records('local_pluginname_data', ['id' => $id]);
$DB->delete_records('local_pluginname_data', ['userid' => $userid]);
// Delete with custom WHERE:
$DB->delete_records_select('local_pluginname_data',
    "timecreated < :cutoff AND status = :status",
    ['cutoff' => strtotime('-1 year'), 'status' => 'expired']);
```

### TRANSACTIONS
```php
// Required for any multi-step DB operation that must be atomic
$transaction = $DB->start_delegated_transaction();
try {
    $enrolid = $DB->insert_record('enrol', $enrolobj);
    $DB->insert_record('user_enrolments', (object)[
        'enrolid'  => $enrolid,
        'userid'   => $userid,
        'status'   => ENROL_USER_ACTIVE,
        'timestart' => time(),
    ]);
    $DB->update_record('local_pluginname_data', $progressobj);
    $transaction->allow_commit();
} catch (\Throwable $e) {
    $transaction->rollback($e);  // Automatically rethrows
}
```

---

## XMLDB Schema (db/install.xml) — Complete Template

```xml
<?xml version="1.0" encoding="UTF-8" ?>
<XMLDB PATH="local/pluginname/db" VERSION="20260404"
       COMMENT="Airpay Academy - local_pluginname tables">
  <TABLES>
    <TABLE NAME="local_pluginname_data"
           COMMENT="Core data table for local_pluginname">
      <FIELDS>
        <FIELD NAME="id"           TYPE="int"  LENGTH="10" NOTNULL="true" SEQUENCE="true"
               COMMENT="Primary key"/>
        <FIELD NAME="userid"       TYPE="int"  LENGTH="10" NOTNULL="true" DEFAULT="0"
               COMMENT="FK to mdl_user.id"/>
        <FIELD NAME="courseid"     TYPE="int"  LENGTH="10" NOTNULL="true" DEFAULT="0"
               COMMENT="FK to mdl_course.id"/>
        <FIELD NAME="costcenterid" TYPE="int"  LENGTH="10" NOTNULL="true" DEFAULT="0"
               COMMENT="BizLMS tenant: 1=Airpay, 77=Public"/>
        <FIELD NAME="status"       TYPE="char" LENGTH="20" NOTNULL="true" DEFAULT="active"
               COMMENT="active|completed|expired"/>
        <FIELD NAME="score"        TYPE="number" LENGTH="10" DECIMALS="2" NOTNULL="false"
               COMMENT="Score 0-100"/>
        <FIELD NAME="timecreated"  TYPE="int"  LENGTH="10" NOTNULL="true" DEFAULT="0"/>
        <FIELD NAME="timemodified" TYPE="int"  LENGTH="10" NOTNULL="true" DEFAULT="0"/>
      </FIELDS>
      <KEYS>
        <KEY NAME="primary"   TYPE="primary" FIELDS="id"/>
        <KEY NAME="fk_user"   TYPE="foreign" FIELDS="userid"   REFTABLE="user"   REFFIELDS="id"/>
        <KEY NAME="fk_course" TYPE="foreign" FIELDS="courseid" REFTABLE="course" REFFIELDS="id"/>
      </KEYS>
      <INDEXES>
        <INDEX NAME="idx_costcenter"    UNIQUE="false" FIELDS="costcenterid"
               COMMENT="BizLMS tenant filtering — every tenant-scoped query hits this"/>
        <INDEX NAME="idx_userid_course" UNIQUE="false" FIELDS="userid, courseid"
               COMMENT="Common lookup pattern"/>
        <INDEX NAME="idx_status"        UNIQUE="false" FIELDS="status"
               COMMENT="Status filtering for dashboards"/>
      </INDEXES>
    </TABLE>
  </TABLES>
</XMLDB>
```

---

## db/upgrade.php — Complete Template

```php
<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_pluginname_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // Pattern: check version, make change, savepoint
    if ($oldversion < 2026040401) {
        // Add a new column to existing table
        $table = new xmldb_table('local_pluginname_data');
        $field = new xmldb_field('score', XMLDB_TYPE_NUMBER, '10', null, null, null, null, 'status');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add a new index
        $index = new xmldb_index('idx_score', XMLDB_INDEX_NOTUNIQUE, ['score']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026040401, 'local', 'pluginname');
    }

    if ($oldversion < 2026040402) {
        // Create a new table
        $table = new xmldb_table('local_pluginname_log');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('action', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('idx_userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026040402, 'local', 'pluginname');
    }

    return true;
}
```

---

## Caching Pattern (For Production — 3,500 Users)

```php
// db/caches.php — register cache definitions
$definitions = [
    'dashboard_stats' => [
        'mode'       => cache_store::MODE_APPLICATION,
        'ttl'        => 300,   // 5 minutes
        'simplekeys' => true,
        'staticacceleration' => true,  // in-memory for current request
    ],
    'tenant_courses' => [
        'mode'       => cache_store::MODE_APPLICATION,
        'ttl'        => 600,   // 10 minutes
        'simplekeys' => true,
    ],
];

// Usage pattern
function get_tenant_stats(int $costcenterid): array {
    global $DB;
    $cache = \cache::make('local_pluginname', 'dashboard_stats');
    $key   = "stats_{$costcenterid}";
    $data  = $cache->get($key);

    if ($data === false) {
        $data = $DB->get_records_sql("... expensive query ...", ['cid' => $costcenterid]);
        $cache->set($key, $data);
    }
    return $data;
}

// Invalidate on data change (call in any write operation):
function invalidate_stats_cache(int $costcenterid): void {
    $cache = \cache::make('local_pluginname', 'dashboard_stats');
    $cache->delete("stats_{$costcenterid}");
}
```

---

## Multi-tenant Scoping (BizLMS — Mandatory)

```php
// Getting current user's tenant (production-compatible: use open_path, not open_costcenterid)
global $USER;
$parts = explode('/', $USER->open_path ?? '');
$costcenterid = (int)($parts[1] ?? 0);  // '/1/2/3' → 1, '/77' → 77, '/177' → 177

// VALIDATE — only allow known tenant IDs
$valid_tenants = [1, 77, 177];  // Airpay, Public, ZEEA
if (!in_array($costcenterid, $valid_tenants, true)) {
    throw new \moodle_exception('invalidtenant', 'local_pluginname');
}

// Standard tenant-scoped query pattern:
$records = $DB->get_records_sql(
    "SELECT r.*
       FROM {local_pluginname_data} r
       JOIN {user} u ON u.id = r.userid
      WHERE r.costcenterid = :cid
        AND u.deleted = 0
        AND u.suspended = 0",
    ['cid' => $costcenterid]
);
```

---

## Performance Decision Matrix

| Data size | Update frequency | Solution |
|-----------|-----------------|---------|
| < 100 rows | Real-time | Direct $DB query, no cache |
| 100-10K rows | Every page load | Application cache, 5 min TTL |
| > 10K rows | Per-request | Session cache + application cache |
| Aggregate stats | Dashboard-level | Application cache, 10 min TTL + cron refresh |
| User-specific | Per-request | get_fast_modinfo() or $DB with specific indexed cols |

---

## Required DB Rules (No Exceptions)

```
NEVER: raw SQL without $DB API
NEVER: string concat in SQL ("WHERE id = " . $id)
NEVER: implode() for IN clauses — use get_in_or_equal()
NEVER: ALTER TABLE / DROP TABLE on production without [CONFIRM]
NEVER: INSERT without setting timecreated AND timemodified
NEVER: multi-tenant query without costcenterid filter
ALWAYS: {tablename} not raw table names
ALWAYS: use MUST_EXIST when record absence is a programming error
ALWAYS: add index for every FK and every column used in WHERE
ALWAYS: wrap multi-step inserts in start_delegated_transaction()
```
