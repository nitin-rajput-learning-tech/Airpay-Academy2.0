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
], function(Str, ModalFactory, ModalEvents, Ajax, Templates, $) {
    var team_approvals;
    return {
        init: function() {

        },
        requestsearch: function(params) {
            var learningtype = params.learningtype;

            var changed_learningtype = $('input[name="approval_learning_type"]').val();
            if(changed_learningtype){
                learningtype = changed_learningtype;
            }
            var search = params.searchvalue;
            if(typeof(search) == 'undefined' || search == null){
                return false;
            }
            //if(learningtype == 'elearning'){
                var target = '#team_requests_list';
        
                var params = {};
                params.action = 'searchdata';
                params.learningtype = learningtype;
                params.search = search;

                var promise = Ajax.call([{
                    methodname: 'local_myteam_teamapprovals_view',
                    args: params
                }]);
                promise[0].done(function(resp) {
                    var data = Templates.render('local_myteam/requestsearch', {response: resp});
                    data.then(function(response){
                         $(target).html(response);
                    });
                }).fail(function(ex) {
                    // do something with the exception
                    console.log(ex);
                });
        },
        select_learningtype: function(params) {
            var learningtype = params.learningtype;
            var pluginname = params.pluginname;

            $('input[name="approval_learning_type"]').val(learningtype);
            $('input[name="search_requests"]').val('');
            $('.team_learningtype_dropdown').html(pluginname);
            var target = '#team_requests_list';
            var params = {};
            params.action = 'change_learningtype';
            params.learningtype = learningtype;
            params.search = false;

            var promise = Ajax.call([{
                methodname: 'local_myteam_teamapprovals_view',
                args: params
            }]);
            promise[0].done(function(resp) {
                var data = Templates.render('local_myteam/requestsearch', {response: resp});
                data.then(function(response){
                     $(target).html(response);
                });
            }).fail(function(ex) {
                // do something with the exception
                console.log(ex);
            });
        },
        select_request: function(params) {
            var learningtype = params.learningtype;
            var requestid = params.requestid;
            var coursecheckedstatus = params.element.checked;
            var allocate = false;

            if(requestid > 0){
                if(coursecheckedstatus == true){
                    allocate = true;
                }
            }

            if(allocate == true){
                $('.request_approval_btn').prop( "disabled", false);
            }else{
                $('.request_approval_btn').prop( "disabled", true);
            }

        },

        approve_request: function() {
            var learning_type = $('#approval_learning_type').val();
            var requeststoapprove = [];

            $('input[name="search_learningtypes"]').val('');
            $('input[name="team_requests[]"]:checked').each(function () {
                var requestid_selected = $(this).val();
                requeststoapprove.push(requestid_selected);
            });

             console.log(requeststoapprove);
            if(!learning_type.length){
                ModalFactory.create({
                    title: Str.get_string('warning'),
                    type: ModalFactory.types.DEFAULT,
                    body: Str.get_string('select_learningtype', 'local_myteam')
                }).done(function(modal) {
                    modal.setSaveButtonText(Str.get_string('approve', 'local_myteam'));

                    //For cancel button string changed//
                    var value=Str.get_string('reject', 'local_myteam');
                    var button = modal.getFooter().find('[data-action="cancel"]');
                    modal.asyncSet(value, button.text.bind(button));

                    modal.show();
                    modal.getRoot().on(ModalEvents.hidden, function() {
                        modal.destroy();
                    });
                    modal.getRoot().on(ModalEvents.cancel, function() {
                        modal.destroy();
                    });
                });
                return false;
            }

            if(!requeststoapprove.length){
                ModalFactory.create({
                    title: Str.get_string('warning'),
                    type: ModalFactory.types.DEFAULT,
                    body: Str.get_string('select_requests', 'local_myteam')
                }).done(function(modal) {
                    modal.setSaveButtonText(Str.get_string('approve', 'local_myteam'));

                    //For cancel button string changed//
                    var value=Str.get_string('reject', 'local_myteam');
                    var button = modal.getFooter().find('[data-action="cancel"]');
                    modal.asyncSet(value, button.text.bind(button));

                    modal.show();
                    modal.getRoot().on(ModalEvents.hidden, function() {
                        modal.destroy();
                    });
                    modal.getRoot().on(ModalEvents.cancel, function() {
                        modal.destroy();
                    });
                });
                return false;
            }

            Str.get_strings([{
                key: 'learningtypeallocated',
                component: 'local_myteam',
            }]).then(function(s) {
            ModalFactory.create({
                title: Str.get_string('confirm'),
                type: ModalFactory.types.SAVE_CANCEL,
                body: Str.get_string('team_request_confirm', 'local_myteam')
            }).done(function(modal) {
                modal.setSaveButtonText(Str.get_string('approve', 'local_myteam'));

                //For cancel button string changed//
                    var value=Str.get_string('reject', 'local_myteam');
                    var button = modal.getFooter().find('[data-action="cancel"]');
                    modal.asyncSet(value, button.text.bind(button));
                modal.show();
                modal.getRoot().on(ModalEvents.hidden, function() {
                    modal.destroy();
                });

                modal.getRoot().on(ModalEvents.save, function() {
                    var params = {};
                    params.action = 'requestapproved';
                    params.requeststoapprove = JSON.stringify(requeststoapprove);
                    params.learningtype = learning_type;

                    var promise = Ajax.call([{
                        methodname: 'local_myteam_teamapprovals_actions',
                        args: params
                    }]);
                    promise[0].done(function(resp) {
                        $('#allocation_notifications').html('<div class="alert alert-info" role="alert"><button type="button" class="close" data-dismiss="alert">×</button>'+s[0]+'</div>');
                        
                        modal.hide();
                        modal.destroy();
                        window.location.href = window.location.href;

                    }).fail(function(ex) {
                        // do something with the exception
                        console.log(ex);
                    });
                });
                modal.getRoot().on(ModalEvents.cancel, function() {
                    setTimeout(function(){
                        modal.destroy();
                    }, 1000);
                });


                 //return modal;
            });
        }.bind(this));
        }
    };
});
