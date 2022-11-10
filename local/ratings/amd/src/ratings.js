/**
 * Add a create new group modal to the page.
 *
 * @module     local_ratings/ratings
 * @class      Ratings
 * @package    local_ratings
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/str','core/templates', 'jquery', 'jqueryui'],
        function(Ajax, Str, templates, $) {
    var ratePicker = function (target, options){
        var self = this;
        if (typeof options === 'undefined'){
            options = {};
        }
        options.max = typeof options.max === 'undefined' ? 5 : options.max;
        options.rgbOn = typeof options.rgbOn === 'undefined' ? "#f1c40f" : options.rgbOn;
        options.spaceWidth = typeof options.spaceWidth === 'undefined' ? '1px' : options.spaceWidth;
        options.fontsize = typeof options.fontsize === 'undefined' ? '25px' : options.fontsize;
        options.rgbOff = typeof options.rgbOff === 'undefined' ? "#ecf0f1" : options.rgbOff;
        options.rgbSelection = typeof options.rgbSelection === 'undefined' ? "#ffcf10" : options.rgbSelection;
        options.cursor = typeof options.cursor === 'undefined' ? "pointer" : options.cursor;
        options.indicator = typeof options.indicator === 'undefined' ? "fa fa-star" : "fa "+options.indicator;
        var stars = typeof $(target).data('stars') == 'undefined' ? 0 : $(target).data('stars');

        $(target).css('cursor', options.cursor);
        $(target).append($("<input>", {type : "hidden", name : target.replace("#", ""), value : stars}));

        $(target).append($("<i>", {class : options.indicator, style : "display:none; font-size: "+options.fontsize+"; color: transparent;"}));
        for (var i = 1; i <= options.max; i++){
            var icon = $("<i>", {class : options.indicator, style : "font-size: "+options.fontsize+"; margin-left: "+options.spaceWidth+"; color:" + (i <= stars ? options.rgbOn : options.rgbOff)});
            $(target).append(icon);

        }
        self.set_target_width(target, options);
        $(target).append($("<i>", {class : options.indicator, style : "display:none; font-size: "+options.fontsize+"; color: transparent;"}));
        $.each($(target + " > i"), function (index, item){
            $(item).click(function (){
                $("[name=" + target.replace("#", "") + "]").val(index);
                for (var i = 1; i <= options.max; i++){
                    $($(target + "> i")[i]).css("color", i <= index ? options.rgbOn : options.rgbOff);
                }
                if (!(options.rate === 'undefined')){
                    // options.rate(index > options.max ? options.max :     index);
                    stars = index;
                    self.set_user_ratings(target, index);
                }
            });
            $(item).mouseover(function (){
                for (var i = 1; i <= options.max; i++){
                    $($(target + " > i")[i]).css("color", i <= index ? options.rgbSelection : options.rgbOff);
                }
            });
            $(item).mouseleave(function(){
                $("[name=" + target.replace("#", "") + "]").val(index);
                for (var i = 1; i <= options.max; i++){
                    $($(target + "> i")[i]).css("color", i <= stars ? options.rgbOn : options.rgbOff);
                }
            });
        });
    };
    ratePicker.prototype.set_target_width = function(target, options){
        var indicator = options.indicator.replace(/\ /g, '.');
        var spaceWidth = options.spaceWidth;
        var item_width = $('.'+indicator).width();
        $(target).width(((item_width+spaceWidth) * (options.max+1))+'px');
    };
    ratePicker.prototype.set_user_ratings = function(target, stars){
        var data = $(target).data();
        $.ajax({
            url: M.cfg.wwwroot+"/local/ratings/update.php",
            method: "POST",
            data: { itemid : data.itemid, ratearea : data.ratearea, rating : stars },
            success: function(result){
                $(".overall_ratings_"+data.itemid).html(' '+result+' ');
                $('.rating_tooltip').html('');

            }
        });
        // var promise = Ajax.call([{
        //     methodname: 'local_ratings_set_module_rating',
        //     args: params
        // }]);
        // promise[0].done(function(resp) {
        //     self.html(resp);
        // });
    }
    return {
        init : function(target, options){
            options = JSON.parse(options);
            return new ratePicker(target, options);
        },
        updatevalues: function (args){
            var action = args.action;
            $.ajax({url:M.cfg.wwwroot+"/local/ratings/index.php?likearea="+args.likearea+"&item="+args.itemid+"&action="+args.action,
                beforeSend: function() {
                    $("#loading_image").show();
                },
                success:function(data){
                    if (action) {
                        $(".fa-thumbs-down").css('color','#0769ad');
                        $(".fa-thumbs-up").css('color','#6d6f71');
                    }else{
                        $(".fa-thumbs-up").css('color','#0769ad');
                        $(".fa-thumbs-down").css('color','#6d6f71');
                    }
                    $(".count_unlikearea_"+args.itemid).html(data.dislike);
                    $(".count_likearea_"+args.itemid).html(data.like);
                    $("#loading_image").hide();
                }
            });
        },
    	trigger: function(){
            $(document).ready(function(){
                var content = $('#ratingList').html();
    			if(content == ''){
    				$('#ratingList').html(' ');
    				var self = $('#ratingList');
	    			var data = $('#ratingList').data();
	    			var params = {};
	    			params.itemid = data.itemid;
	    			params.ratearea = data.ratearea;
	    			params.contextid = 1;

	    			var promise = Ajax.call([{
	                    methodname: 'local_ratings_get_ratings_info',
	                    args: params
	                }]);
	                promise[0].done(function(resp) {
                        templates.render('local_ratings/detailed_info_loaded', resp).then(function(html,js) {
	                        self.html(html);
                        });
	                });
	            }
    		});
            // $(document).on('mouseover', '.overall_users.mt-10', function(){
            $('.overall_users.mt-10').hover(function(){
                var content = $(this).find('.rating_tooltip').html();
                if(content == ''){
                    $(this).find('.rating_tooltip').html(' ');
                    var self = $(this).find('.rating_tooltip');
                    var data = $(this).find('.rating_tooltip').data();
                    var params = {};
                    params.itemid = data.itemid;
                    params.ratearea = data.ratearea;
                    params.contextid = 1;

                    var promise = Ajax.call([{
                        methodname: 'local_ratings_get_ratings_info',
                        args: params
                    }]);
                    promise[0].done(function(resp) {
                        templates.render('local_ratings/detailed_info', resp).then(function(html,js) {
                            // console.log(html);
                            self.html(html);
                        });
                        // self.html(resp);
                    });
                }
            });
    	},
    	comment_item: function(args) {
                Str.get_strings([{
                    key: 'postcomment',
                    component: 'local_ratings'
                }]).then(function(s) {
                var userid = args.userid;
                var itemid = args.itemid;
                var commentarea = args.commentarea;
                var comment = $("#post_comment_" + commentarea + '_' + itemid).data('comment');
                var formid = "form_comment_item" + userid + '_' + itemid + commentarea;
                var container = $("#post_comment_" + commentarea + '_' + itemid).parents('div');
                if ($('#ratings_comment_' + commentarea + '_' + itemid).length < 1 ) {
                    $("#post_comment_" + commentarea + '_' + itemid).append('<div id="ratings_comment_' + commentarea + '_' + itemid + '" class="postcomment"><div class="commentloading"></div><form id="' + formid + '" ><textarea id="text_' + formid + '" type="text" name="comment">' + comment + '</textarea><input type="submit" value="Submit"></form></div>');
                }
                var dlg = $('#ratings_comment_' + commentarea + '_' + itemid).dialog({
                    resizable: true,
                    autoOpen: false,
                    width: "20%",
                    title: s[0],
                    modal: false,
                    dialogClass: 'commentdialog',
                    show: {
                        effect: "slide",
                        duration: 1000
                    },
                    position: {
                        my: "left",
                        at: "right",
                        of: "#post_comment_" + commentarea + '_' + itemid,
                        within: container
                    },
                    open: function(event, ui) {
                        $(this).closest(".ui-dialog")
                            .find(".ui-dialog-titlebar-close")
                            .removeClass("ui-dialog-titlebar-close")
                            .html("<span class='ui-button-icon-primary ui-icon ui-icon-closethick'></span>");
                            var Closebutton = $('.ui-icon-closethick').parent();
                            $(Closebutton).attr({
                                "title" : "Close"
                            });
                        $(".sendmessage").not(this).each(function() {
                            $(this).remove();
                        });
                    },
                    close: function(event, ui) {
                        $(this).dialog('destroy').remove();
                    }
                });
                dlg.dialog("open");
                $("#" + formid).submit(function(e) {
                    e.preventDefault();
                    var newcomment = document.getElementById("text_" + formid).value;
                    if (!newcomment) {
                         return Str.get_strings([{
                                key: 'commentsupplyvalue',
                                component: 'local_ratings'
                            }
                            ]).then(function(s) {
                                $('.commentloading').html(s[0]);
                            }.bind(this));
                    } else {
                        url = require('core/url');
                        return Str.get_strings([{
                                key: 'commentsavings',
                                component: 'local_ratings'
                            }
                            ]).then(function(s) {
                                $('.commentloading').html('<center><img src="' + url.imageUrl("loader", "local_ratings") + '" alt="'+s[0]+'" title="'+s[0]+'"/></center>');
                            }.bind(this));
                        $("#" + formid).hide();
                        // console.log();
                        // console.log($("#" + formid).serializeObject());
                        var promise = Ajax.call([{
                            methodname: 'local_ratings_save_comment',
                            args: {
                                userid : userid,
                                itemid : itemid,
                                commentarea : commentarea,
                                comment : newcomment
                            }
                        }]);
                        promise[0].done(function(response) {
                        	dlg.dialog("close");
                        	$("#post_comment_" + commentarea + '_' + itemid).data('comment', newcomment);
                            content = newcomment.slice(0,50);
                            $("#comment_value_" + commentarea + '_' + itemid).html(content);
                            $("#comment_value_" + commentarea + '_' + itemid).prop('title', newcomment);
	                    }).fail(function(ex) {
	                         console.log(ex);
	                    });
                    }
                    
                })
            });
        },
        // trigger: function(){
        //         // alert('hi');
        //     // $('.rating_enable_wrapper').load(function(){
        //     //     alert('hi1');
        //     // });
        //     // $(document).ready(function(){
        //     //     $(window).on('load', '.rating_enable_wrapper', function(){
        //     //     });
        //     // });
        // },
    	load: function(){

    	}
    }
});