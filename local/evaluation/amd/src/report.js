define(['jquery', 'core/str', 'core/modal_factory'],
        function($, Str, ModalFactory) {
    return {
        init: function(selector, item, id) {
            //console.log('init');
            $(selector).click(function(){
                $.ajax({
                    type: "GET",
                    url:  "reportajax.php",
                    data: { item: item,
                        sesskey: M.cfg.sesskey
                    },
                    success: function(result) {
                        //Var returned_data is ONLY available inside this fn!
                            ModalFactory.create({
                            title: 'Report Analysis',
                            body: result
                          }).done(function(modal) {
                            // Do what you want with your new modal.
                            modal.show();
                          });                          
                        $('#reportdisplay'+id).html(result);
                    }
                });
            });
        } 
    };
});