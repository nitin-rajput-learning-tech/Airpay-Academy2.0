/**
 * Add a create new group modal to the page.
 *
 * @module     local_learningplan/learningplan
 * @class      courseenrol
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery',
    'core/str',
    'core/modal_factory',
    'core/modal_events',
    'core/fragment',
    'core/ajax',
    'core/yui',
    'local_learningplan/jquery.dataTables'],
    function ($, Str, ModalFactory, ModalEvents, Fragment, Ajax, Y) {
        /**
        * Constructor
        *
        * @param {object} args
        *
        * Each call to init gets it's own instance of this class.
        */
        var courseenrol = function (args) {
            this.args = args;
            this.contextid = args.contextid;
            this.planid = args.planid;
            this.condition = args.condition;
            var self = this;
            self.init(args.selector);
        };
        /**
        * @var {Modal} modal
        * @private
        */
        courseenrol.prototype.modal = null;
        /**
        * @var {int} contextid
        * @private
        */
        courseenrol.prototype.contextid = -1;
        /**
        * Initialise the class.
        *
        * @private
        * @return {Promise}
        */
        courseenrol.prototype.init = function () {
            var self = this;
            var head = Str.get_string('enrolcourses', 'local_learningplan');
            return head.then(function (title) {
                // Create the modal.
                return ModalFactory.create({
                    type: ModalFactory.types.SAVE_CANCEL,
                    title: title,
                    body: self.getBody()
                });
            }.bind(self)).then(function (modal) {
                // Keep a reference to the modal.
                self.modal = modal;
                self.modal.getRoot().addClass('openLMStransition local_costcenter');
                // Forms are big, we want a big modal.
                self.modal.setLarge();
                // We want to reset the form every time it is opened.
                self.modal.getRoot().on(ModalEvents.hidden, function () {
                    self.modal.setBody(self.getBody());
                    self.modal.getRoot().animate({ "right": "-85%" }, 500);
                    setTimeout(function () {
                        modal.destroy();
                    }, 1000);
                }.bind(this));
                this.modal.getFooter().find('[data-action="cancel"]').on('click', function () {
                     window.location.href();
                });
                // We want to hide the submit buttons every time it is opened.
                self.modal.getRoot().on(ModalEvents.shown, function () {
                    self.modal.getRoot().append('<style>[data-fieldtype=submit] { display: none ! important; }</style>');
                }.bind(this));
                // We catch the modal save event, and use it to submit the form inside the modal.
                // Triggering a form submission will give JS validation scripts a chance to check for errors.
                self.modal.getRoot().on(ModalEvents.save, self.submitForm.bind(self));
                // We also catch the form submit event and use it to submit the form with ajax.
                self.modal.getRoot().on('submit', 'form', self.submitFormAjax.bind(self));
                this.modal.show();
                this.modal.getRoot().animate({ "right": "0%" }, 500);
                $(".close").click(function () {
                    window.location = window.location.href;
                });
                return this.modal;
            }.bind(this));
        };

        /**
        * @param {object} formdata
        * @method getBody
        * @private
        * @return {Promise}
        */
        courseenrol.prototype.getBody = function (formdata) {
            if (typeof formdata === "undefined") {
                formdata = {};
            }
            // Get the content of the modal.
            var params = { planid: this.planid, jsonformdata: JSON.stringify(formdata), condition: this.condition };
            return Fragment.loadFragment('local_learningplan', 'lpcourse_enrol', this.contextid, params);
        };
        /**
        * @method handleFormSubmissionResponse
        * @private
        * @return {Promise}
        */
        courseenrol.prototype.handleFormSubmissionResponse = function () {
            this.modal.hide();
            // We could trigger an event instead.
            // Yuk.
            Y.use('moodle-core-formchangechecker', function () {
                M.core_formchangechecker.reset_form_dirty_state();
            });
            document.location.reload();
        };
        /**
        * @param {object} data
        * @method handleFormSubmissionFailure
        * @private
        * @return {Promise}
        */
        courseenrol.prototype.handleFormSubmissionFailure = function (data) {
            // Oh noes! Epic fail :(
            // Ah wait - this is normal. We need to re-display the form with errors!
            this.modal.setBody(this.getBody(data));
        };
        /**
        * Private method
        *
        * @method submitFormAjax
        * @private
        * @param {Event} e Form submission event.
        */
        courseenrol.prototype.submitFormAjax = function (e) {
            // We don't want to do a real form submission.
            e.preventDefault();

            // Convert all the form elements values to a serialised string.
            var formData = this.modal.getRoot().find('form').serialize();
            // Now we can continue...
            Ajax.call([{
                methodname: 'local_learningplan_lpcourse_enrol_form',
                args: { planid: this.planid, contextid: this.contextid, jsonformdata: JSON.stringify(formData) },
                done: this.handleFormSubmissionResponse.bind(this, formData),
                fail: this.handleFormSubmissionFailure.bind(this, formData)
            }]);
        };
        /**
        * This triggers a form submission, so that any mform elements can do final tricks before the form submission is processed.
        *
        * @method submitForm
        * @param {Event} e Form submission event.
        * @private
        */
        courseenrol.prototype.submitForm = function (e) {
            e.preventDefault();
            var self = this;
            self.modal.getRoot().find('form').submit();
        };
        return /** @alias module:local_costcenter/newcostcenter */ {
            // Public variables and functions.
            /**
             * Attach event listeners to initialise this module.
             *
             * @method init
             * @param {object} args
             * @return {Promise}
             */
            init: function (args) {
                return new courseenrol(args);
            },
            load: function () {
                $(document).on('click', '.unenrolself_module', function () {
                    var args = $(this).data();
                    return Str.get_strings([{
                        key: 'confirm',
                        component: 'moodle'
                    },
                    {
                        key: args.identifier,
                        component: args.plugin,
                        param: args
                    },
                    {
                        key: 'yes',
                        component: 'moodle'
                    },
                    {
                        key: 'no',
                        component: 'moodle'
                    }
                    ]).then(function (s) {
                        ModalFactory.create({
                            title: s[0],
                            type: ModalFactory.types.SAVE_CANCEL,
                            body: s[1]
                        }).done(function (modal) {
                            this.modal = modal;
                            modal.setSaveButtonText(s[2]);
                            modal.getRoot().on(ModalEvents.save, function (e) {
                                e.preventDefault();
                                var params = {};
                                params.id = args.id;
                                params.contextid = args.contextid;
                                params.userid = args.userid;
                                var promise = Ajax.call([{
                                    methodname: args.pluginname + '_' + args.methodname,
                                    args: params
                                }]);
                                promise[0].done(function () {
                                    window.location = window.location.href;
                                }).fail(function () {
                                });
                            }.bind(this));
                            modal.show();
                        }.bind(this));
                    }.bind(this));
                });
            },
            publishLearningplan: function (args) {
                var planvalue = args.planid;
                return Str.get_strings([{
                    key: 'confirm'
                },
                {
                    key: 'learningplan_enrol_users',
                    component: 'local_learningplan',
                    param: args
                },
                {
                    key: 'confirmall',
                    component: 'local_learningplan'
                },
                {
                    key: 'confirm'
                }]).then(function (s) {
                    ModalFactory.create({
                        title: s[0],
                        type: ModalFactory.types.SAVE_CANCEL,
                        body: s[1]
                    }).done(function (modal) {
                        this.modal = modal;
                        modal.setSaveButtonText(s[3]);
                        modal.getRoot().on(ModalEvents.save, function (e) {
                            e.preventDefault();
                            var params = "?action=publishlearningplan&planid="+planvalue;
                            $.ajax({
                                method: "GET",
                                dataType: "json",
                                url: M.cfg.wwwroot + "/local/learningplan/ajax.php"+params,
                                success: function(){
                                    // modal.destroy();
                                    window.location = window.location.href;
                                }
                            });
                            // var url = M.cfg.wwwroot +
                            //  "/local/learningplan/ajax.php?action=publishlearningplan&planid=" + planvalue;
                            // window.location.href = url;
                        }.bind(this));
                        modal.show();
                    }.bind(this));
                }.bind(this));
            },
            tabsFunction: function () {
                $('.learningplan_tabs').click(function () {
                    if ($(this).find('a').hasClass('active')) {
                        return true;
                    }
                    var learningplantab = $(this).data('module');
                    var id = $(this).data('id');
                    var options = $(this).data('options');
                    var dataoptions = $(this).data('dataoptions');
                    var filterdata = $(this).data('filterdata');
                    $.ajax({
                        method: 'GET',
                        url: M.cfg.wwwroot + '/local/learningplan/ajax.php',
                        data: {
                            action: "learningplantab",
                            tab: learningplantab,
                            id: id,
                            ajax: 0
                        },
                        success: function (resp) {
                            var html = resp;
                            //  $.parseJSON(resp);
                            $('#learningplantabscontent').html(html);
                            $('#learningplantabscontent').find('div').addClass('active');
                            if (learningplantab == 'users') {
                                // $("table#learning_plan_users").dataTable({
                                //     language: {
                                //         "paginate": {
                                //             "next": ">",
                                //             "previous": "<"
                                //         },
                                //         "search": "",
                                //         "searchPlaceholder": "Search"
                                //     }
                                // });
                                var params = [];
                                params.action = 'learningplantab';
                                params.tab = 'users';
                                params.id = id;
                                params.ajax = 1;
                                return Str.get_strings([{
                                    key: 'search',
                                    component: 'moodle'
                                }]).then(function (s) {
                                    $('table#learning_plan_users').dataTable({
                                        'processing': true,
                                        'serverSide': true,
                                        "language": {
                                            "paginate": {
                                                "next": ">",
                                                "previous": "<"
                                            },
                                            "search": "",
                                            "searchPlaceholder": s[0],
                                            "processing": '<img src=' + M.cfg.wwwroot + '/local/ajax-loader.svg>'
                                        },
                                        'ajax': {
                                            "type": "POST",
                                            "url": M.cfg.wwwroot + '/local/learningplan/ajax.php',
                                            "data": params
                                        },
                                        "responsive": true,
                                        "pageLength": 5,
                                        "bLengthChange": false,
                                        "bInfo": false,
                                    });
                                }.bind(this));
                            } else if (learningplantab == 'requestedusers') {
                                // require(['local_request/requestconfirm'], function(requestconfirm) {
                                //      requestconfirm.requestDatatable();
                                //  });
                                // $('#learningplantabscontent').html(html);

                                require(['local_costcenter/cardPaginate'], function (cardPaginate) {
                                    cardPaginate.reload(options, dataoptions, filterdata);
                                });
                            }
                        }
                    });
                });
            },
            enrolUser: function (args) {
                var planvalue = args.planid;
                var userid = args.userid;
                return Str.get_strings([{
                    key: 'confirm'
                },
                {
                    key: 'learningplan_self_enrol',
                    component: 'local_learningplan',
                    param: args
                },
                {
                    key: 'confirm'
                }]).then(function (s) {
                    ModalFactory.create({
                        title: s[0],
                        type: ModalFactory.types.SAVE_CANCEL,
                        body: s[1]
                    }).done(function (modal) {
                        this.modal = modal;
                        modal.setSaveButtonText(s[2]);
                        modal.getRoot().on(ModalEvents.save, function (e) {
                            e.preventDefault();
                            var path  = M.cfg.wwwroot+"/local/learningplan/ajax.php?";
                            $.ajax({
                                method: "GET",
                                dataType: "json",
                                url: path+ "action=userselfenrol&planid=" + planvalue + "&userid=" + userid,
                                success: function () {
                                    modal.destroy();
                                    window.location.href = M.cfg.wwwroot + '/local/learningplan/view.php?id=' + planvalue;
                                }
                            });
                        }.bind(this));
                        modal.show();
                    }.bind(this));
                }.bind(this));
            },
            unEnrolUser: function (args) {
                return Str.get_strings([{
                    key: 'confirm'
                },
                {
                    key: 'learningplan_self_unenrol',
                    component: 'local_learningplan',
                    param: args
                }]).then(function (s) {
                    ModalFactory.create({
                        title: s[0],
                        type: ModalFactory.types.SAVE_CANCEL,
                        body: s[1]
                    }).done(function (modal) {
                        this.modal = modal;
                        modal.setSaveButtonText(s[0]);
                        modal.getRoot().on(ModalEvents.save, function (e) {
                            e.preventDefault();
                            var params = {};
                            params.userid = args.userid;
                            params.planid = args.planid;
                            var promise = Ajax.call([{
                                methodname: 'local_learningplan_unassign_user',
                                args: params
                            }]);
                            promise[0].done(function () {
                                window.location.href = M.cfg.wwwroot;
                            }).fail(function () {
                            });
                        }.bind(this));
                        modal.show();
                    }.bind(this));
                }.bind(this));
            },
        };
    });