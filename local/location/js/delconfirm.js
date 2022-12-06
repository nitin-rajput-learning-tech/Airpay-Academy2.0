M.util.moodle_location_confirm_dialog = function(e, args) {
    var target = e.currentTarget;
    if (e.preventDefault) {
        e.preventDefault();
    }
    YUI().use('moodle-core-notification-confirm', function(Y) {
        var confirmationDialogue = new M.core.confirm({
            width: '300px',
            center: true,
            modal: true,
            visible: false,
            draggable: false,
            title: M.util.get_string('confirmation', 'admin'),
            noLabel: M.util.get_string('cancel', 'moodle'),
            question: args.message
        });

        // The dialogue was submitted with a positive value indication.
        confirmationDialogue.on('complete-yes', function(e) {
            // Handle any callbacks.
            if (args.callback) {
                if (!Y.Lang.isFunction(args.callback)) {
                    Y.log('Callbacks to show_confirm_dialog must now be functions. Please update your code to pass in a function instead.',
                            'warn', 'M.util.cobaltk12_show_confirm_dialog');
                    return;
                }
                var scope = e.target;
                if (Y.Lang.isObject(args.scope)) {
                    scope = args.scope;
                }
                var callbackargs = args.callbackargs || [];
                args.callback.apply(scope, callbackargs);
                return;
            }
            var targetancestor = null,
                targetform = null;

            if (target.test('a')) {
                window.location = ''+target.get('href')+'?id='+args.callbackargs.id+'&delete=1&confirm=1&sesskey='+M.cfg.sesskey+'';
                 //window.location = ''+target.get('href')+'?id='+args.callbackargs.id+'&sesskey='+M.cfg.sesskey+'&signup_id='+args.callbackargs.id+'';
            } else {
                Y.log('Element of type ' + target.get('tagName') +
                        ' is not supported by the M.util.cobaltk12_show_confirm_dialog function. Use A',
                        'warn', 'javascript-static');
            }
        }, this);

        if (args.cancellabel) {
            confirmationDialogue.set('noLabel', args.cancellabel);
        }

        if (args.continuelabel) {
            confirmationDialogue.set('yesLabel', args.continuelabel);
        }

        confirmationDialogue.render()
                .show();
    });
};