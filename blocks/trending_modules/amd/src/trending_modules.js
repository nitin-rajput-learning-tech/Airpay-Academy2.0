/**
 *
 * @module     block_trending_modules/trending_modules
 * @class      trending_modules
 * @package    block_trending_modules
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/templates', 'core/fragment', 'core/str', 'core/modal_factory', 'core/modal_events', 'local_costcenter/cardPaginate'], 
        function ($, Ajax, Templates, Fragment, Str, ModalFactory, ModalEvents, cardPaginate){
	return trending_module = {
		init: function(){
			var search_interval = 200;
        	$(document).on('keyup', '#filter_trending_modules', function(){
        		var searchvalue = $(this).val();
                var data = $(this).data();
                var target = data.target;
                var navigatorElm = data.navigator; 
                var jsondata = JSON.stringify(data);
            	setTimeout(function(){
            		var params = {};
            		params.indexid = 0;
        			params.contextid = 1;
            		params.search = searchvalue;
            		params.jsondata = jsondata;

            		var promise = Ajax.call([{
                        methodname: 'block_trending_modules_display_content',
                        args: params
                    }]);
                    promise[0].done(function(resp) {
                    	var data = {};
                    	data.records = resp.records; 
                    	Templates.render('block_trending_modules/trending_module', data).then(function(html,js) {
                    		$(target).html(html);
                            $(navigatorElm).data('totalcount', resp.totalcount);
                    	});
                    });
            	}, search_interval);
			});
            $(document).on('click', '.block_trending_modules_navigator', function(){
                var data = $(this).data();
                if(data.action == 'right'){
                    var new_offset = data.offset + 3;
                }else if(data.action == 'left'){
                    var new_offset = data.offset - 3;
                }else{
                    return;
                }
                $('.block_trending_modules_navigator').data('offset', new_offset);
                // $('.block_trending_modules_navigator').attr('data-offset', new_offset);
                if(new_offset+data.limit >= data.totalcount){
                    $('.block_trending_modules_navigator.right_navigator').addClass('hidden');
                }else if(data.totalcount > 3){
                    $('.block_trending_modules_navigator.right_navigator').removeClass('hidden');
                }
                if(new_offset == 0){
                    $('.block_trending_modules_navigator.left_navigator').addClass('hidden');
                }else{
                    $('.block_trending_modules_navigator.left_navigator').removeClass('hidden');
                }

                var params = {};
                params.indexid = new_offset;
                params.limitnum = 3;
                params.contextid = 1;
                params.search = '';
                
                var promise = Ajax.call([{
                    methodname: 'block_trending_modules_display_content',
                    args: params
                }]);
                promise[0].done(function(resp) {
                    // console.log(resp);
                    var data = {};
                    data.records = resp.records; 
                    Templates.render('block_trending_modules/trending_module', data).then(function(html,js) {
                        $('#trending_modules_content').html(html);
                    });
                });
            });
            $(document).on('click', '.block_suggested_modules_navigator', function(){
                var data = $(this).data();
                var jsondata = JSON.stringify(data);
                if(data.action == 'right'){
                    var new_offset = data.offset + 3;
                }else if(data.action == 'left'){
                    var new_offset = data.offset - 3;
                }else{
                    return;
                }
                $('.block_suggested_modules_navigator').data('offset', new_offset);
                // $('.block_trending_modules_navigator').attr('data-offset', new_offset);
                if(new_offset+data.limit >= data.totalcount){
                    $('.block_suggested_modules_navigator.right_navigator').addClass('hidden');
                }else if(data.totalcount > 3){
                    $('.block_suggested_modules_navigator.right_navigator').removeClass('hidden');
                }
                if(new_offset == 0){
                    $('.block_suggested_modules_navigator.left_navigator').addClass('hidden');
                }else{
                    $('.block_suggested_modules_navigator.left_navigator').removeClass('hidden');
                }

                var params = {};
                params.indexid = new_offset;
                params.limitnum = 3;
                params.contextid = 1;
                params.search = '';
                params.jsondata = jsondata;
                
                var promise = Ajax.call([{
                    methodname: 'block_trending_modules_display_content',
                    args: params
                }]);
                promise[0].done(function(resp) {
                    // console.log(resp);
                    var data = {};
                    data.records = resp.records; 
                    Templates.render('block_trending_modules/trending_module', data).then(function(html,js) {
                        $('#suggested_modules_content').html(html);
                    });
                });
            });
			
		},
        display_popup :function(){
            // var dontshowelement = "<div class='trending-checkbox w-full d-inline-block text-right'><label class='trend-checkbox-label d-inline-block pull-left mr-1'><input type='checkbox' class = 'update_trending_preference' id = 'force_stop_populating_modules'/><span class='trend-checkbox-custom trend-rectangular'></span></label><div class='pull-left'>Don\'t show this</div></div>";
            Fragment.loadFragment('block_trending_modules', 'get_trending_popup', 1, {}).done(function(html, js){
                return Str.get_string('pluginname','block_trending_modules').then(function(s) {
                    ModalFactory.create({
                        title: s,
                        type: ModalFactory.types.DEFAULT,
                        body : html,
                        // footer : dontshowelement
                    }).done(function(modal) {
                        this.modal = modal;
                        this.modal.setLarge();
                        this.modal.getRoot().addClass('trending_modal_popup');
                        this.modal.getRoot().on(ModalEvents.hidden, function() {
                            var statuschange = $(".update_trending_preference").is(':checked');
                            if(statuschange){
                                params = {};
                                params.contextid = 1;
                                params.status = !statuschange;
                                var promise = Ajax.call([{
                                    methodname: 'block_trending_modules_alter_popup_status',
                                    args: params
                                }]);
                                promise[0].done(function(resp) {
                                    $('.update_trending_preference').prop('checked', true);
                                }).fail(function(ex) {
                                     console.log(ex);
                                });
                            }
                        });
                        // modal.setSaveButtonText(s[3]);
                        // modal.getRoot().on(ModalEvents.save, function(e) {
                        //     e.preventDefault();
                        //     args.confirm = true;
                        //     var params = {};
                        //     params.id = args.id;
                        //     params.contextid = args.contextid;
                        
                        //     var promise = Ajax.call([{
                        //         methodname: 'local_users_suspend_user',
                        //         args: params
                        //     }]);
                        //     promise[0].done(function(resp) {
                        //         window.location.href = window.location.href;
                        //     }).fail(function(ex) {
                        //         // do something with the exception
                        //          console.log(ex);
                        //     });
                        // }.bind(this));
                        modal.show();
                    }.bind(this));
                }.bind(this));
            });
        
        },
        update_preference : function(){
            $(document).on('click', '.update_trending_preference', function(){
                var status = $(this).prop('checked');
                params = {};
                params.contextid = 1;
                params.status = !status;
                var promise = Ajax.call([{
                    methodname: 'block_trending_modules_alter_popup_status',
                    args: params
                }]);
                promise[0].done(function(resp) {

                }).fail(function(ex) {
                    console.log(ex);
                });

            });
        },
        load: function(){
            var identifier = 'show_suggested_modules_content';
            var options = {
                targetID : identifier,
                perPage : 3,
                cardClass : 'col-md-4 col-4',
                viewType : 'card',
                methodName : 'block_trending_modules_display_paginated',
                templateName : 'block_trending_modules/trending_module_paginated'
            };
            var dataoptions = {};
            $(document).on('click', '.show_suggested_modules', function(){
                var data = $(this).data();
                var contenthtml = '<label>Search : </label><input type="text" name="search" id="search_popup_content" data-module_type='+data.moduletype+' data-module_tags='+data.tags+'><div class="d-inline-blocks card-paginate_wrap" id="'+identifier+'" data-region="'+identifier+'-preview-container"><div data-region="'+identifier+'-count-container"></div><div data-region="'+identifier+'-list-container" id ="'+identifier+'id"></div></div>';
                var filterdata = {
                    module_type : data.moduletype,
                    module_tags : data.tags,
                    show_suggestions : data.show_suggestions
                };

                Str.get_string('suggested_modules','block_trending_modules').then(function(header) {
                    ModalFactory.create({
                        title: header,
                        type: ModalFactory.types.DEFAULT,
                        body : contenthtml,
                    }).done(function(modal) {
                        this.modal = modal;
                        this.modal.setLarge();
                        modal.show();
                        this.modal.getRoot().addClass('suggested_modules');
                        cardPaginate.reload(options, dataoptions,filterdata);
                        this.modal.getRoot().on(ModalEvents.hidden, function() {
                            modal.destroy();
                        });
                    }.bind(this));
                }.bind(this));
            });
            var timer;
            $(document).on('keyup', '#search_popup_content', function(){
                clearTimeout(timer);
                var search = $(this).val();
                var data = $(this).data();
                timer = setTimeout(function(){
                    var filterdata = {
                        module_type : data.module_type,
                        module_tags : data.module_tags,
                        show_suggestions : data.show_suggestions,
                        search_query : search
                    };
                    cardPaginate.reload(options, dataoptions, filterdata);
                }, 100);
            });
            $(document).on('keydown', '#search_popup_content', function(){
                clearTimeout(timer);
            });
        }         		
   };

});