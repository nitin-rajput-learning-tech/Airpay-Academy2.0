/*
* This file is a part of e abyas Info Solutions.
*
* Copyright e abyas Info Solutions Pvt Ltd, India.
*
* This trainerdashboard is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 3 of the License, or
* (at your option) any later version.
*
* This trainerdashboard is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this trainerdashboard.  If not, see <http://www.gnu.org/licenses/>.
*
* @author 2019 e abyas  <info@eabyas.com>
*/
/**
 * Defines form and confirmation pops (types view and confirmation pops)
 *
 * @package    block_trainerdashboard
 * @copyright  2019 e abyas  <info@eabyas.com>
 */
define(['jquery',
        'core/str',
        'core/modal_factory',
        'core/modal_events',
        'core/fragment',
        'core/ajax',
        'core/yui',
        'core/templates',
        'core/notification',
        'core/custom_interaction_events',
        'block_trainerdashboard/trainerdashboard'],
        function($, Str, ModalFactory, ModalEvents, fragment, Ajax, Y, Templates, Notification, CustomEvents,trainerdashboard) {
        var SELECTORS = {
            ELEMENT: '[data-fg]',
        };
        var DATAATTRIBUTES = {
            ELEFG: 'fg',
            PLUGIN: 'plugin',
            METHOD: 'method',
            PARAMS: 'params',
            TRIGGERTYPE: 'triggertype',
            JSONFORMDATA: 'jsonformdata',
            
        };
        var Fragment = function(fgelement) {
            this.contextid = M.cfg.contextid;
            this.fgelement = fgelement;
            this.id = fgelement.data('id') || 0;
            this.pluginname = fgelement.data(DATAATTRIBUTES.PLUGIN);
            this.method = fgelement.data(DATAATTRIBUTES.METHOD);
            this.level = fgelement.data(DATAATTRIBUTES.ELEFG);
            this.args = {};
            this.args.contextid = this.contextid;
            this.args.id = this.id;
            var params = {};
            if (typeof fgelement.data(DATAATTRIBUTES.PARAMS) !== 'undefined') {
                params = fgelement.data(DATAATTRIBUTES.PARAMS);
            }
            if (typeof fgelement.data(DATAATTRIBUTES.JSONFORMDATA) !== 'undefined') {
                this.args.jsonformdata =  JSON.stringify(fgelement.data(DATAATTRIBUTES.JSONFORMDATA));
            }
       
            this.args.params = JSON.stringify(params);
            this.args.triggertype = fgelement.data(DATAATTRIBUTES.TRIGGERTYPE);
            this.init();
        };

        Fragment.prototype.contextid = -1;

        Fragment.prototype.id = 0;

        Fragment.prototype.level = 'c';

        Fragment.prototype.strings = {};

        Fragment.prototype.args = {};
        /**
         * [init description]
         * @method init
         * @return {[type]} [description]
         */
        Fragment.prototype.init = function () {
            var self = this;
            var stringsPromise = this.getStrings();
            var type = ModalFactory.types.SAVE_CANCEL;
            if (self.level == 'r') {
                type = ModalFactory.types.DEFAULT;
            }
            if (self.level == 'vmtotaltrainings' || self.level == 'vmcmpltdtrainings' || self.level == 'vmupcmngtrainings') {
                var modalPromise = ModalFactory.create({
                    type: ModalFactory.types.DEFAULT,
                    footer: this.getFooter(),
                });
            }else{
                var modalPromise = ModalFactory.create({
                    type: type,
                });
            }

 
            $.when(stringsPromise, modalPromise).then(function(strings, modal) {
                // Keep a reference to the modal.
                this.modal = modal;
                this.modal.setTitle(strings[0]);

                // We want to desrtroy the form every time it is opened.
                if (self.level == 'r') {
                    this.modal.setBody(this.getBody());
                    this.modal.setLarge();
                    // We want to desrtroy the form every time it is opened.
                    this.modal.getRoot().on(ModalEvents.hidden, function() {
                        this.modal.destroy();
                    }.bind(this));
                }else if (self.level == 'vmtotaltrainings' || self.level == 'vmcmpltdtrainings' || self.level == 'vmupcmngtrainings') {
                    this.modal.setBody(this.getBody());
                    this.modal.setLarge();
                    // We want to desrtroy the form every time it is opened.
                    this.modal.getRoot().on(ModalEvents.hidden, function() {
                        this.modal.destroy();
                    }.bind(this));
                }
                else {

                    self.modal.getRoot().addClass('openLMStransition');
                   
                    this.modal.getRoot().on(ModalEvents.hidden, function() {
                    //this.modal.destroy();
                    this.modal.getRoot().animate({"right":"-85%"}, 500);
                    setTimeout(function(){
                    modal.destroy();
                    }, 1000);
                    }.bind(this));

                    this.modal.setBody(this.getBody());
                    // Forms are big, we want a big modal.
                    this.modal.setLarge();
                    if (this.level == 'c') {
                        var submitlabel = strings[1];
                    } else if (this.level == 'u') {
                        var submitlabel = strings[1];
                    }

                    this.modal.setSaveButtonText(submitlabel);
                    // We catch the modal save event, and use it to submit the form inside the modal.
                    // Triggering a form submission will give JS validation scripts a chance to check for errors.
                    this.modal.getRoot().on(ModalEvents.save, this.submitForm.bind(this));
                    // We also catch the form submit event and use it to submit the form with ajax.
                    this.modal.getRoot().on('submit', 'form', this.submitFormAjax.bind(this));
                
                }
                this.modal.show();
                this.modal.getRoot().animate({"right":"0%"}, 500);

            }.bind(this));

        };
        /**
         * [getStrings description]
         * @method getStrings
         * @param  {[type]}   StringData [description]
         * @return {[type]}              [description]
         */
        Fragment.prototype.getStrings = function() {
            var self = this;
            var StringData = this.requiredStrings();
            var RequiredStrings = [];
            var i = 0;
            $.each (StringData, function(key, value) {
                RequiredStrings[i] = {key: key, component: self.pluginname, param: value};
                i++;
            });
            var stringsPromise = Str.get_strings(RequiredStrings);
            return stringsPromise;
        };

        /**
         * @method getBody
         * @private
         * @return {Promise}
         */
        Fragment.prototype.getBody = function(formdata) {
            if (typeof formdata === "undefined") {
                formdata = {};
            }

            this.custommethod=this.method;
            // Get the content of the modal.
            this.args.jsonformdata = JSON.stringify(formdata);
            return fragment.loadFragment(this.pluginname, this.custommethod, this.contextid, this.args);
        };
        /**
        * @method getFooter
        * @private
        * @return {Promise}
        */
        Fragment.prototype.getFooter = function() {

            $footer = '<button type="button" class="btn btn-secondary" data-action="cancel" style="display:none;">Close</button>';
        return $footer;
        };

        /**
         * @method handleFormSubmissionResponse
         * @private
         * @return {Promise}
         */
        Fragment.prototype.handleFormSubmissionResponse = function() {
            this.modal.hide();
            this.modal.destroy();
            // We could trigger an event instead.
            // Yuk.
            Y.use('moodle-core-formchangechecker', function() {
                M.core_formchangechecker.reset_form_dirty_state();
            });

            var PluginModule = require(this.pluginname  + '/' + this.pluginname.split('_')[1]);

            if (typeof PluginModule.childform === 'function') {
                PluginModule.childform(this);
            } else {
                this.handleNotifications('success', {});
            }
        };

        /**
         * @method handleFormSubmissionFailure
         * @private
         * @return {Promise}
         */
        Fragment.prototype.handleFormSubmissionFailure = function(data) {
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
        Fragment.prototype.submitFormAjax = function(e) {
            // We don't want to do a real form submission.
            e.preventDefault();

            // Convert all the form elements values to a serialised string.
            var formData = this.modal.getRoot().find('form').serialize();
            this.args.jsonformdata = JSON.stringify(formData);
            
 
            this.custommethod=this.method;
            
            // Now we can continue...
            var value=Ajax.call([{
                methodname: 'blocks_trainerdashboard' + '_' + this.custommethod,
                args: this.args,
                done: this.handleFormSubmissionResponse.bind(this, formData),
                fail: this.handleFormSubmissionFailure.bind(this, formData)
            }]);
            var body = $('[data-region="body"]');
            var TEMPLATES = {
                LOADING: 'core/loading',
                BACKDROP: 'core/modal_backdrop',
            };
            if (value[0].state() == 'pending') {
                // We're still waiting for the body promise to resolve so
                // let's show a loading icon.
                var height = body.innerHeight();
                if (height < 100) {
                    height = 100;
                }

                body.animate({height: height + 'px'}, 150);

                body.html('');
                contentPromise = Templates.render(TEMPLATES.LOADING, {})
                    .then(function(html) {
                        var loadingIcon = $(html).hide();
                        body.html(loadingIcon);
                        loadingIcon.fadeIn(150);

                        // We only want the loading icon to fade out
                        // when the content for the body has finished
                        // loading.
                        return $.when(loadingIcon.promise(), value);
                    })
                    .then(function(loadingIcon) {
                        // Once the content has finished loading and
                        // the loading icon has been shown then we can
                        // fade the icon away to reveal the content.
                        return loadingIcon.promise();
                    })
                    .then(function() {
                        return value;
                    });
            }
        };

        /**
         * This triggers a form submission, so that any mform elements can do final tricks before the form submission is processed.
         *
         * @method submitForm
         * @param {Event} e Form submission event.
         * @private
         */
        Fragment.prototype.submitForm = function(e) {
            e.preventDefault();
            this.modal.getRoot().find('form').submit();
        };

        /**
         * [requiredStrings description]
         * @method requiredStrings
         * @return {[type]}        [description]
         */
        Fragment.prototype.requiredStrings = function() {
            var StringData = {};
            var PluginModule = require(this.pluginname  + '/' + this.pluginname.split('_')[1]);
            if (typeof PluginModule.requiredStrings === 'function') {
                StringData = PluginModule.requiredStrings(this);
            }
            this.strings = StringData;
            return StringData;
        };
    return {
        init: function() {
            $(document).on('click', SELECTORS.ELEMENT, function(e) {
                e.preventDefault();
                var fgelement = $(this);
                new Fragment(fgelement);
            });
        },
    };
});