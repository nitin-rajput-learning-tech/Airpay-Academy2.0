define(['jquery'],
    function($) {
        return {
            init: function() {
                $( document ).ready(function() {
                    var quick_first_row_count = $('.quick_nav_list_wrapper.first_row .quick_nav_list').length;
                    var quick_second_row_count = $('.quick_nav_list_wrapper.second_row .quick_nav_list').length;
                    var quick_third_row_count = $('.quick_nav_list_wrapper.third_row .quick_nav_list').length;
                    var quick_fourth_row_count = $('.quick_nav_list_wrapper.fourth_row .quick_nav_list').length;

                    if(quick_first_row_count === 2){
                        $('.quick_nav_list_wrapper.first_row .quick_nav_list').addClass('one_of_three_columns');
                    }else{  
                        $('.quick_nav_list_wrapper.first_row .quick_nav_list').addClass('three_of_three_columns');
                    }

                    if(quick_second_row_count === 2){
                        $('.quick_nav_list_wrapper.second_row .quick_nav_list').addClass('one_of_three_columns');
                    }else{
                        $('.quick_nav_list_wrapper.second_row .quick_nav_list').addClass('three_of_three_columns');
                    }

                    if(quick_third_row_count === 3){
                        $('.quick_nav_list_wrapper.third_row .quick_nav_list').addClass('one_of_three_columns');
                    }else if(quick_third_row_count === 2){
                        $('.quick_nav_list_wrapper.third_row .quick_nav_list').addClass('half_of_three_columns');
                    }else{
                        $('.quick_nav_list_wrapper.third_row .quick_nav_list').addClass('three_of_three_columns'); 
                    }

                    if(quick_fourth_row_count === 2){
                        $('.quick_nav_list_wrapper.fourth_row .quick_nav_list').addClass('one_of_three_columns');
                    }else{  
                        $('.quick_nav_list_wrapper.fourth_row .quick_nav_list').addClass('three_of_three_columns');
                    }
                });
            },
            load: function() {
                //Load this module
            }
        };
    });
