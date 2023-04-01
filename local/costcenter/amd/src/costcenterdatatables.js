/**
 *
 * @module     local_costcenter/NewSubdept
 * @package    local_costcenter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'local_costcenter/jquery.dataTables',
    'core/str',
    'core/modal_factory',
    'core/modal_events',
    'core/ajax',
    'jquery',
    'jqueryui'
], function(dataTable, Str, ModalFactory, ModalEvents, Ajax, $) {
    var users;
    return users = {
        init: function(args) {            
        },
        costcenterDatatable: function(args) {
            Str.get_strings([{
                key: 'search',
                component: 'local_costcenter',
            }]).then(function(str) {
                $('#department-index').dataTable({
                    "searching": false,
                    //"responsive": true,
                    "aaSorting": [],
                    "lengthMenu": [[5, 10, 25,50,100, -1], [5,10,25, 50,100, "All"]],
                    "aoColumnDefs": [{ 'bSortable': false, 'aTargets': [ 0 ] }],
                    language: {
                        search: "_INPUT_",
                        searchPlaceholder: str[0],
                        "paginate": {
                            "next": ">",
                            "previous": "<"
                        }
                    }       
                });
            }.bind(this));
        },
        costcenterDelete: function(args) {
            return Str.get_strings([{
                key: 'confirm'
            },
            {
                key: 'suspendconfirm',
                component: 'local_users',
                param :args
            },
            {
                key: 'suspendallconfirm',
                component: 'local_users'
            },
            {
                key: 'confirm'
            }]).then(function(s) {
                ModalFactory.create({
                    title: s[0],
                    type: ModalFactory.types.SAVE_CANCEL,
                    body: args.actionstatusmsg
                }).done(function(modal) {
                    this.modal = modal;
                    modal.setSaveButtonText(s[3]);
                    modal.getRoot().on(ModalEvents.save, function(e) {
                        e.preventDefault();
                        args.confirm = true;
                        var params = {};
                        params.id = args.id;
                        // params.contextid = args.contextid;
                    
                        var promise = Ajax.call([{
                            methodname: 'local_costcenter_delete_costcenter',
                            args: params
                        }]);
                        promise[0].done(function(resp) {
                            window.location.href = window.location.href;
                        }).fail(function(ex) {
                            // do something with the exception
                             //console.log(ex);
                        });
                    }.bind(this));
                    modal.show();
                }.bind(this));
            }.bind(this));
        },
        accountchange: function(){

            var params = {};

            params.accountid = $('#id_parentid').find("option:selected").val();
            params.contextid = $('#id_parentid').data('contextid');
             params.actions = "accountselect";
            var promise = Ajax.call([{
                methodname: 'local_costcenter_generate_shortcode',
                args: params
            }]);
            promise[0].done(function(resp){
                $('.shortnamestatic').html(resp);
                $('#id_concatshortname').val(resp);

            });

        },
    };
});