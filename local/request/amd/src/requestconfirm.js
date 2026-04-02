/**
 * Add a create new group modal to the page.
 *
 * @module     local_request/requestconfirm
 * @class      requestconfirm
 * @package    local_costcenter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery',
        'core/str',
        'core/modal_factory',
        'core/modal_events',
        'core/fragment',
        'core/ajax',
        'core/yui',
        'local_request/jquery.dataTables'],
        function($, Str, ModalFactory, ModalEvents, Fragment, Ajax, Y, dataTable) {

    /**
     * Constructor
     *
     * @param {String} selector used to find triggers for the new group modal.
     * @param {int} contextid
     *
     * Each call to init gets it's own instance of this class.
     */
    var requestconfirm = function(args) {
         
        this.componentid = args.componentid;
        this.component = args.component;
        this.id =args.id;
        this.action =args.action;
   
       // var self = this;
        this.init(args);
    };

    /**
     * @var {Modal} modal
     * @private
     */
    requestconfirm.prototype.modal = null;

    /**
     * @var {int} contextid
     * @private
     */
    requestconfirm.prototype.contextid = -1;


    requestconfirm.prototype.getrequeststring = function(){

        return Str.get_strings([{
                key: 'confirm'
            },
            {
                key: 'request_confirm_message',
                component: 'local_request',
                param :args
            },
            {
                key: 'confirm'
            }]) ;
    };


    requestconfirm.prototype.handleModalSubmissionResponse = function(data,modal, args){
      
        if(data==0){
            var msg=Str.get_string('alreadyrequested', 'local_request');
        }
        else if(data==-1){
            var msg=Str.get_string('capacity_check', 'local_request');
        }
        else{
            var msg=Str.get_string('success_'+args.action, 'local_request');
        }
        if(args.action=="approve" && args.component=='Classroom'){
            if(data <1 || data==''){
		        modal.setTitle(Str.get_string('information', 'local_request'));
                modal.setBody(msg);
                modal.getFooter().find('[data-action="cancel"], [data-action="save"]').hide();
            }else{
                this.modal.hideFooter();
                modal.setBody(data);
                modal.setTitle(Str.get_string('information', 'local_request'));
                $('[data-action="hide"]').css('display','block');
            }
        }else if(args.action=="approve" && args.component=='Certification'){ 
            
                modal.setTitle(Str.get_string('information', 'local_request'));
                modal.setBody(msg);
                modal.getFooter().find('[data-action="cancel"], [data-action="save"]').hide();
            
        }else if(args.action!='add'){
          window.location.reload();
        }else{
            modal.setBody(msg);
            //modal.getFooter().find('.modal-body button').hide();
            modal.getFooter().find('[data-action="cancel"], [data-action="save"]').hide();
        }
        $(".close").click(function(){
              location.reload();

        });
         //window.location = $(this).attr('href');
    }; /* end of function */

    
    requestconfirm.prototype.submitajax = function(args, modal){

            this.modal = modal;
            userid = args.userid;
            if(args.action == 'approve'){
                modal.setSaveButtonText(Str.get_string('approve', 'local_request')); 
            }else if(args.action == 'deny'){
                modal.setSaveButtonText(Str.get_string('reject', 'local_request')); 
            }else if(args.action == 'delete'){
                modal.setSaveButtonText(Str.get_string('delete', 'local_request')); 
            }else{
                modal.setSaveButtonText(Str.get_string('confirm', 'local_request')); 
            }
            modal.getRoot().on(ModalEvents.save, function(e) {
            e.preventDefault();
                args.confirm = true;
            $.ajax({
                method: "POST",
                dataType: "json",
                url: M.cfg.wwwroot + "/local/request/ajax.php",
                data: { component: args.component, componentid: args.componentid, action :args.action, id:args.id}
              
            }).done(function(response){
              requestconfirm.prototype.handleModalSubmissionResponse(response, modal, args);

            })
          
        });
    };

    requestconfirm.prototype.getbodystring= function(args){
       
       var string ='';
       if(args.component == 'learningplan'){
            args.component = 'Learning path';
       }
       if(args.action && args.component){
        var string= Str.get_string('confirmmsgfor_'+args.action, 'local_request', args);
       }

     return string;
    }; 
    
    requestconfirm.prototype.init = function(args) { 
        var deferred = $.Deferred();
        this.action = args.action;
        var componentid = args.componentid;
        var userid = args.userid;
        var component = args.component;
         
        ModalFactory.create({
            title: 'Confirm',
            type: ModalFactory.types.SAVE_CANCEL,
            body: requestconfirm.prototype.getbodystring(args)       
        }).done(function(modal) {
            requestconfirm.prototype.submitajax(args, modal);
            modal.show();
        });


     
              
    }; /* end of iit function */



    /**
     * Initialise the class.
     *
     * @param {String} selector used to find triggers for the new group modal.
     * @private
     * @return {Promise}
     */
    requestconfirm.prototype.init11 = function(args) {        
        
            var componentid = args.componentid;
            var userid = args.userid;
            var component = args.component;
            return Str.get_strings([{
                key: 'confirm'
            },
            {
                key: 'request_confirm_message',
                component: 'local_request',
                param :args
            },
            {
                key: 'confirm'
            }]).then(function(s) {
                ModalFactory.create({
                    title: s[0],
                    type: ModalFactory.types.SAVE_CANCEL ,
                    body: s[1]
                }).done(function(modal) {
                    this.modal = modal;
                    modal.setSaveButtonText(s[2]);
                    modal.getRoot().on(ModalEvents.save, function(e) {
                        e.preventDefault();
                        // args.confirm = true;
                        $.ajax({
                            method: "GET",
                            dataType: "json",
                            url: M.cfg.wwwroot + "/local/request/ajax.php?component='"+args.component+"'&componentid="+componentid+"&userid="+userid,
                            success: function(data){
                       
                                if(data==0){
                                     var msg=Str.get_string('alreadyrequested', 'local_request');
                                }
                                else{
                                    var msg=Str.get_string('requestsent', 'local_request');
                                }

                                modal.setBody(msg);
                                //modal.getFooter().find('.modal-body button').hide();
                                modal.getFooter().find('[data-action="cancel"], [data-action="save"]').hide();
                               // modal.destroy();
                                //window.location.href = window.location.href;
                                    $(".close").click(function(){
                                            location.reload();
                                    });
                            }
                        });
                    }.bind(this));
                    modal.show();
                }.bind(this));
            }.bind(this));
        
    };

  

    return /** @alias module:local_costcenter/newcostcenter */ {
        // Public variables and functions.
        /**
         * Attach event listeners to initialise this module.
         *
         * @method init
         * @param {string} selector The CSS selector used to find nodes that will trigger this module.
         * @param {int} contextid The contextid for the course.
         * @return {Promise}
         */
        init: function( args) {            
            // alert(args.contextid);
            return new requestconfirm(args);
        },

        requestDatatable: function() {
            params = [];
            params.action = 'viewrequest';
            params.classroomid = $('#viewrequest').data('classroomid');
            var oTable = $('#viewrequest').dataTable({
                'processing': false,
                'serverSide': false,
                "language": {
                    "paginate": {
                    "next": ">",
                    "previous": "<"
                    },
                    "search": "",
                    "searchPlaceholder": "Search"
                },
                "sorting": false,
                "responsive": true,
                "pageLength": 5,
                "bLengthChange": false,
                "bInfo" : false,
            });
        }, 

        load: function(){

        },

    };
});
        /*publishLearningplan: function(args){
            console.log(args);
            var planvalue = args.planid;
            return Str.get_strings([{
                key: 'confirm'
            },
            {
                key: 'learningplan_enrol_users',
                component: 'local_learningplan',
                param :args
            },
            {
                key: 'confirmall',
                component: 'local_learningplan'
            },
            {
                key: 'confirm'
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
                        // args.confirm = true;
                        $.ajax({
                            method: "GET",
                            dataType: "json",
                            url: M.cfg.wwwroot + "/local/learningplan/ajax.php?action=publishlearningplan&planid="+planvalue,
                            success: function(data){
                                modal.destroy();
                                window.location.href = window.location.href;
                            }
                        });
                    }.bind(this));
                    modal.show();
                }.bind(this));
            }.bind(this));
        },
        tabsFunction: function(args){
            // console.log(args);
            // alert(args.id);
            $('.learningplan_tabs').click(function(){
                if ($(this).find('a').hasClass('active')){
                    return true;
                }
                var mylink = this;
                console.log(mylink);
                var learningplantab = $(this).data('module');
                var id = $(this).data('id');
                // console.log(id);
                // alert(id);
                $.ajax({
                    method: 'GET',
                    // dataType: "json",
                    url: M.cfg.wwwroot + '/local/learningplan/ajax.php',
                    data: {
                        action: "learningplantab",
                        tab: learningplantab,
                        id: id
                    },
                    success:function(resp){
                        var html = $.parseJSON(resp);
                        $('#learningplantabscontent').html(html);
                        $('#learningplantabscontent').find('div').addClass('active');
                        if(learningplantab == 'users'){
                            $("table#learning_plan_users").dataTable({
                                language: {
                                    "paginate": {
                                        "next": ">",
                                        "previous": "<"
                                    },
                                    "search": "",
                                    "searchPlaceholder": "Search"
                                }
                            });
                        }
                        // console.log(mylink);
                    }
                });
            });
        },
        enrolUser : function(args){
            // console.log(args);
            // alert('here');
            var planvalue = args.planid;
            var userid = args.userid;
            return Str.get_strings([{
                key: 'confirm'
            },
            {
                key: 'learningplan_self_enrol',
                component: 'local_learningplan',
                param :args
            },
            {
                key: 'confirm'
            }]).then(function(s) {
                ModalFactory.create({
                    title: s[0],
                    type: ModalFactory.types.SAVE_CANCEL,
                    body: s[1]
                }).done(function(modal) {
                    this.modal = modal;
                    modal.setSaveButtonText(s[2]);
                    modal.getRoot().on(ModalEvents.save, function(e) {
                        e.preventDefault();
                        // args.confirm = true;
                        $.ajax({
                            method: "GET",
                            dataType: "json",
                            url: M.cfg.wwwroot + "/local/learningplan/ajax.php?action=userselfenrol&planid="+planvalue+"&userid="+userid,
                            success: function(data){
                                modal.destroy();
                                window.location.href = window.location.href;
                            }
                        });
                    }.bind(this));
                    modal.show();
                }.bind(this));
            }.bind(this));
        }, */
