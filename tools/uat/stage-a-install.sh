#!/usr/bin/env bash
# stage-a-install.sh - Sentientia LMS 5.2 UAT Stage A fresh install (checklist §3).
# Runs ON UAT-Sentientia-LMS. Captures everything to a timestamped log (Stage A
# is the first-ever 5.2 runtime validation - the log IS the evidence).
#
# Usage (all secrets via env, nothing on the command line / in this file):
#   DBPASS='...' ADMINPASS='...' bash stage-a-install.sh
#
# Optional overrides:
#   DBHOST, DBNAME (default sentientia_uat), DBUSER (default db_user),
#   WWWROOT, DATAROOT, DOCROOT, ADMINEMAIL
set -u

DBHOST="${DBHOST:-lms-sentientia-uat-db.crpst4qn6rtu.ap-south-1.rds.amazonaws.com}"
DBNAME="${DBNAME:-sentientia_uat}"
DBUSER="${DBUSER:-db_user}"
WWWROOT="${WWWROOT:-https://academy2.airpay.ninja}"
DATAROOT="${DATAROOT:-/var/sentientiadata}"
DOCROOT="${DOCROOT:-/var/www/html/moodle5.2/public}"
DIRROOT="$(dirname "$DOCROOT")"                       # /var/www/sentientia/moodle5.2
ADMINEMAIL="${ADMINEMAIL:-nitin.rajput@airpay.co.in}"
LOG="$HOME/stage-a-$(date +%Y%m%d-%H%M%S).log"

: "${DBPASS:?Set DBPASS in the environment (never on the command line)}"
: "${ADMINPASS:?Set ADMINPASS in the environment (strong; goes to the vault)}"

exec > >(tee -a "$LOG") 2>&1
echo "=== Sentientia Stage A install $(date -Is) ==="
echo "docroot=$DOCROOT  wwwroot=$WWWROOT  dataroot=$DATAROOT  db=$DBNAME@$DBHOST"

fail() { echo "FAIL: $*"; echo "Log: $LOG"; exit 1; }

cd "$DIRROOT" 2>/dev/null || cd /   # www-data cannot read our home dir; avoid setup.php chdir() warnings.

# ---------- 0. Pre-flight ----------
[ -f "$DOCROOT/version.php" ] || fail "no Moodle at $DOCROOT (package not extracted?)"
PHPV=$(php -r 'echo PHP_VERSION;') || fail "php CLI not found"
echo "php: $PHPV"
case "$PHPV" in 8.[3-9].*) ;; *) fail "PHP 8.3+ required, found $PHPV";; esac

MISSING=""
for ext in intl mbstring curl zip gd xml soap mysqli sodium exif; do
    php -m | grep -qi "^$ext$" || MISSING="$MISSING $ext"
done
[ -z "$MISSING" ] || fail "missing PHP extensions:$MISSING"
MIV=$(php -r 'echo (int)ini_get("max_input_vars");')
[ "$MIV" -ge 5000 ] || echo "WARN: max_input_vars=$MIV (<5000) - fix php.ini before heavy admin forms"

SUDO=""
PHPW="php"   # How to run Moodle CLI scripts: as www-data when we can, so dataroot files get the web user's ownership.
if sudo -n true 2>/dev/null; then SUDO="sudo"; PHPW="sudo -u www-data php"; else
    echo "WARN: no passwordless sudo - dataroot/config ownership steps may fail"
fi

# ---------- 1. Close the open install wizard FIRST: write config.php ----------
if [ -f "$DIRROOT/config.php" ]; then
    echo "config.php already exists - leaving it (inspect manually if bindings differ)"
else
    CONF="$DIRROOT/config.php"
    cat > /tmp/sentientia-config.php <<PHPEOF
<?php  // Sentientia LMS UAT - written by stage-a-install.sh $(date -Is)
unset(\$CFG);
global \$CFG;
\$CFG = new stdClass();
\$CFG->dbtype    = 'mysqli';
\$CFG->dblibrary = 'native';
\$CFG->dbhost    = '$DBHOST';
\$CFG->dbname    = '$DBNAME';
\$CFG->dbuser    = '$DBUSER';
\$CFG->dbpass    = '$DBPASS';
\$CFG->prefix    = 'mdl_';
\$CFG->dboptions = ['dbpersist' => 0, 'dbport' => 3306, 'dbcollation' => 'utf8mb4_unicode_ci'];
\$CFG->wwwroot   = '$WWWROOT';
\$CFG->dataroot  = '$DATAROOT';
\$CFG->admin     = 'admin';
\$CFG->directorypermissions = 0770;
// UAT: keep outbound email OFF until OAuth2 SMTP is configured deliberately (151-email incident rule).
\$CFG->noemailever = true;
\$CFG->sslproxy = true;   // TLS terminates at the LB; Apache serves :80 behind it.
require_once(__DIR__ . '/lib/setup.php');
PHPEOF
    if [ -w "$DIRROOT" ]; then cp /tmp/sentientia-config.php "$CONF";
    else $SUDO cp /tmp/sentientia-config.php "$CONF" || fail "cannot write $CONF (need sudo)"; fi
    rm -f /tmp/sentientia-config.php
    $SUDO chown www-data:www-data "$CONF" 2>/dev/null || true
    $SUDO chmod 640 "$CONF" 2>/dev/null || true
    echo "config.php written - the public install wizard is now DISABLED"
fi
$PHPW -l "$DIRROOT/config.php" >/dev/null || fail "config.php syntax error"

# ---------- 2. dataroot ----------
if [ ! -d "$DATAROOT" ]; then
    $SUDO mkdir -p "$DATAROOT" || fail "cannot create $DATAROOT (need sudo)"
fi
$SUDO chown -R www-data:www-data "$DATAROOT" 2>/dev/null || true
$SUDO chmod 0770 "$DATAROOT" 2>/dev/null || true
echo "dataroot ready: $(ls -ld "$DATAROOT")"

# ---------- 3. Database reachability + existence ----------
export DBHOST DBUSER DBPASS DBNAME   # the probe below reads these via getenv()
php -r '
    mysqli_report(MYSQLI_REPORT_OFF);
    $h=getenv("DBHOST"); $u=getenv("DBUSER"); $p=getenv("DBPASS"); $d=getenv("DBNAME");
    $m=@mysqli_connect($h,$u,$p,null,3306);
    if(!$m){fwrite(STDERR,"DB connect FAILED: ".mysqli_connect_error()."\n");exit(1);}
    $r=mysqli_query($m,"SHOW DATABASES LIKE \"".$d."\"");
    if(mysqli_num_rows($r)==0){
        if(!mysqli_query($m,"CREATE DATABASE `".$d."` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"))
            {fwrite(STDERR,"CREATE DATABASE failed: ".mysqli_error($m)."\n");exit(1);}
        echo "database $d CREATED (utf8mb4/utf8mb4_unicode_ci)\n";
    } else { echo "database $d exists\n"; }
' || fail "database step failed"
export DBHOST DBUSER DBPASS DBNAME

# ---------- 4. Install (the first 5.2 runtime validation - capture verbatim) ----------
if $PHPW -r "define('CLI_SCRIPT',1); require '$DIRROOT/config.php'; exit(empty(\$CFG->version)?1:0);" 2>/dev/null; then
    echo "Moodle tables already installed - skipping install_database.php"
else
    $PHPW "$DIRROOT/admin/cli/install_database.php" --agree-license \
        --fullname="Sentientia LMS (UAT)" --shortname="SENTIENTIA-UAT" \
        --adminuser=admin --adminpass="$ADMINPASS" --adminemail="$ADMINEMAIL" \
        || fail "install_database.php failed - THIS OUTPUT IS THE P5 EVIDENCE, send the log"
fi

# ---------- 5. Upgrade + caches + post-install CLIs (guidebook steps 9-10) ----------
$PHPW "$DIRROOT/admin/cli/upgrade.php" --non-interactive --allow-unstable || fail "upgrade.php failed"
$PHPW "$DIRROOT/admin/cli/purge_caches.php" || fail "purge_caches failed"
for cli in repair_task_registrations.php enable_oneclick_enrol.php; do
    found=$(find "$DOCROOT/local" -maxdepth 3 -name "$cli" 2>/dev/null | head -1)
    if [ -n "$found" ]; then $PHPW "$found" --apply 2>/dev/null || $PHPW "$found" || echo "WARN: $cli nonzero exit"; \
    else echo "WARN: $cli not found under local/ - run manually per guidebook"; fi
done

# ---------- 6. Smoke ----------
for path in /login/index.php /admin/environment.php; do
    code=$(curl -sk -o /dev/null -w '%{http_code}' -H "Host: academy2.airpay.ninja" "http://127.0.0.1$path")
    echo "smoke http://localhost$path -> $code"
done
code=$(curl -s -o /dev/null -w '%{http_code}' "$WWWROOT/login/index.php")
echo "smoke $WWWROOT/login/index.php -> $code (via LB)"
wizard=$(curl -s -o /dev/null -w '%{http_code}' "$WWWROOT/install.php")
echo "install.php via LB -> $wizard (must NOT be a 200 wizard anymore; 30x/404/error page = good)"

echo "=== Stage A install script finished $(date -Is) - full log: $LOG ==="
