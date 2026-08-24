# uat-purge.ps1 - the "purge caches" half of the loop, against UAT.
# Prereq: tunnel up (`ssh uat-tunnel` in another window).
ssh uat-lms "php /var/www/sentientia/moodle5.2/public/admin/cli/purge_caches.php"
