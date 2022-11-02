define(['jquery', 'core/ajax', 'core/templates', 'core/notification', 'core/str', 'core/modal_factory', 'core/modal_events'],
        function($, ajax, templates, notification, str, ModalFactory, ModalEvents) {
    var gettags = function(args){
        var header = str.get_string('suggestedmodules', 'local_tags');
        var data = {};
        data.params = args;
        data.action = 'gettagsdata';
        data.id = 1;
        $.ajax({
            method: "POST",
            dataType: "json",
            data: data,
            url: M.cfg.wwwroot + "/local/tags/ajax.php",
            success: function (data) {

                ModalFactory.create({
                    title: header,
                    body: data
                }).done(function(modal) {
                    // Do what you want with your new modal.
                    modal.show();
                    modal.getRoot().on(ModalEvents.hidden, function() {
                        modal.setBody('');
                    }.bind(this));
                    modal.getRoot().find('[data-action="hide"]').on('click', function() {
                        modal.hide();
                        setTimeout(function(){
                             modal.destroy();
                        }, 500);
                    });
                });
            }
        });
    };

    return {
        load: function () {            
        },
        displaydialog: function(args){
            return new gettags(args);
        },
    };
});