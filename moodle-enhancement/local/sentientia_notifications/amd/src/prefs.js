// User notification preferences form.
//
// @module     local_sentientia_notifications/prefs
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

import Ajax from 'core/ajax';
import Notification from 'core/notification';

const collectDisabledRuleTypes = () => {
    const out = [];
    document.querySelectorAll(
        '[data-region="ap-prefs-ruletype"]:checked').forEach((el) => {
        out.push(el.value);
    });
    return out;
};

const handleSubmit = (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const inapp = form.querySelector('#ap-pref-inapp').checked;
    const email = form.querySelector('#ap-pref-email').checked;
    const push  = form.querySelector('#ap-pref-push').checked;
    const digest = form.querySelector('#ap-pref-digest').value;
    const qstart = parseInt(form.querySelector('#ap-pref-quiet-start').value, 10);
    const qend   = parseInt(form.querySelector('#ap-pref-quiet-end').value, 10);
    const disabled_rule_types = collectDisabledRuleTypes();

    Ajax.call([{
        methodname: 'local_sentientia_notifications_save_prefs',
        args: {
            channel_inapp: inapp,
            channel_email: email,
            channel_push:  push,
            digest_frequency: digest,
            disabled_rule_types,
            quiet_hours_start: qstart,
            quiet_hours_end:   qend,
        },
    }])[0].then(() => {
        Notification.addNotification({
            message: 'Preferences saved.',
            type: 'success',
        });
        return null;
    }).catch(Notification.exception);
};

export const init = () => {
    const form = document.getElementById('ap-notif-prefs-form');
    if (!form || form.dataset.airpayInit === '1') return;
    form.dataset.airpayInit = '1';
    form.addEventListener('submit', handleSubmit);
};
