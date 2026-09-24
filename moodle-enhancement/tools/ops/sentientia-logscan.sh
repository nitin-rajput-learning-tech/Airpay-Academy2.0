#!/usr/bin/env bash
# Retrieved from the UAT box (ip-10-0-135-185) on 2026-09-24 for W2-04 -- until now it existed
# only on that box, unversioned, as our only live monitoring artefact.
#   installed at: /usr/local/bin/sentientia-logscan.sh (root:root 0755)
#   root crontab: 15 6 * * * /usr/local/bin/sentientia-logscan.sh
# To reinstall on a rebuilt box:
#   sudo install -m 0755 moodle-enhancement/tools/ops/sentientia-logscan.sh /usr/local/bin/
#   sudo mkdir -p /var/log/sentientia-uat
#   (sudo crontab -l; echo "15 6 * * * /usr/local/bin/sentientia-logscan.sh") | sudo crontab -
# Body below is verbatim from the box.
OUT=/var/log/sentientia-uat/logscan-$(date +%Y%m%d).txt
{
  echo "=== Sentientia UAT log scan $(date -Is) ==="
  echo "--- resources ---"; uptime; free -m | sed -n 2p; df -h / | tail -1
  echo "--- apache error.log: PHP errors in the last 24h (by message) ---"
  find /var/log/apache2 -name '*error.log*' -mtime -1 -exec zgrep -h -iE "PHP (Fatal|Warning|Notice|Deprecated)|Exception|dml_" {} + 2>/dev/null | sed -E 's/^\[[^]]+\] //; s/\[client [^]]+\] //' | cut -c1-160 | sort | uniq -c | sort -rn | head -20
  echo "--- apache access: 5xx in the last 24h ---"
  find /var/log/apache2 -name '*access.log*' -mtime -1 -exec zgrep -h -E '" 5[0-9]{2} ' {} + 2>/dev/null | awk '{print $7}' | sort | uniq -c | sort -rn | head -10
  echo "--- moodle tasks ---"
  cd / && sudo -u www-data php -r 'define("CLI_SCRIPT",1); require "/var/www/html/moodle5.2/config.php"; echo "scheduled failing: ", $DB->count_records_select("task_scheduled","faildelay > 0"), "  adhoc queued: ", $DB->count_records("task_adhoc"), "  adhoc failing: ", $DB->count_records_select("task_adhoc","faildelay > 0"), PHP_EOL; foreach ($DB->get_records_sql("SELECT classname, COUNT(1) n FROM {task_log} WHERE result = 1 AND timestart > :t GROUP BY classname", ["t"=>time()-86400]) as $r) echo "  failed 24h: ", $r->classname, " x", $r->n, PHP_EOL; echo "last task run: ", date("c", (int)$DB->get_field_sql("SELECT MAX(lastruntime) FROM {task_scheduled}")), PHP_EOL;'
  echo "=== end ==="
} > "$OUT" 2>&1
find /var/log/sentientia-uat -name 'logscan-*.txt' -mtime +30 -delete
