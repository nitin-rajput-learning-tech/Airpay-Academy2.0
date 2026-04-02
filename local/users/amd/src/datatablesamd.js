/**
 * Add a create new group modal to the page.
 *
 * @module     core_group/AjaxForms
 * @class      AjaxForms
 * @package    core_group
 * @copyright  2017 Damyon Wiese <damyon@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'core/str',
    'core/modal_factory',
    'core/modal_events',
    'core/ajax',
    'core/templates',
    'jquery',
    'jqueryui'
], function(Str, ModalFactory, ModalEvents, Ajax, Templates, $) {
    var users;
    return users = {
        init: function(args) {
            
        },
        deletesyncStatistics: function(args){
            var id = [];
          // var value = $('input[type=checkbox]').prop('checked');
            $(":checkbox:checked").each(function(i){
                id[i] = parseInt($(this).val());
            });
            if(id.length > 0){
                return Str.get_strings([{
                    key: 'confirm'
                },
                {
                    key: 'deleteconfirmsynch',
                    component: 'local_users',
                    param :args
                },
                {
                    key: 'deleteconfirmsynch',
                    component: 'local_users'
                },
                {
                    key: 'delete'
                }]).then(function(s) {
                    ModalFactory.create({
                        title: s[0],
                        type: ModalFactory.types.SAVE_CANCEL,
                        body: s[1]
                    }).done(function(modal) {
                        this.modal = modal;
                        modal.setSaveButtonText(s[3]);
                        modal.getRoot().on(ModalEvents.save, function(e) {
                            e.preventDefault();
                            args.confirm = true;
                            var params = {};
                            params.contextid = args.contextid;
                            params.ids = JSON.stringify(id);
                            var promise = Ajax.call([{
                                methodname: 'local_users_'+args.action,
                                args: params
                            }]);
                            promise[0].done(function(resp) {
                                window.location.href = window.location.href;
                            }).fail(function(ex) {
                                 console.log(ex);
                            });
                        }.bind(this));
                        modal.show();
                    }.bind(this));
                }.bind(this));
            }else{
            }
            Str.get_string('selectonecheckbox_msg', 'local_users').then(function(s) {
                alert(s);
            });

        },
        load: function(){

        }
    };
});