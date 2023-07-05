AOS.init();

document.addEventListener("DOMContentLoaded", function() {

    /////// Prevent closing from click inside dropdown
    document.querySelectorAll('.dropdown-menu').forEach(function(element) {
        element.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });

    // make it as accordion for smaller screens
    if (window.innerWidth < 992) {
        // close all inner dropdowns when parent is closed
        document.querySelectorAll('.navbar .dropdown').forEach(function(everydropdown) {
            everydropdown.addEventListener('hidden.bs.dropdown', function() {
                // after dropdown is hidden, then find all submenus
                this.querySelectorAll('.megasubmenu').forEach(function(everysubmenu) {
                    // hide every submenu as well
                    everysubmenu.style.display = 'none';
                });
            })
        });

        document.querySelectorAll('.has-submenu a').forEach(function(element) {
            element.addEventListener('click', function(e) {
                let nextEl = this.nextElementSibling;
                if (nextEl && nextEl.classList.contains('megasubmenu')) {
                    // prevent opening link if link needs to open dropdown
                    e.preventDefault();
                    if (nextEl.style.display == 'block') {
                        nextEl.style.display = 'none';
                    } else {
                        nextEl.style.display = 'block';
                    }
                }
            });
        }) // end foreach

        document.querySelectorAll('.dropdown-menu a').forEach(function(element){
    element.addEventListener('click', function (e) {
        let nextEl = this.nextElementSibling;
        if(nextEl && nextEl.classList.contains('submenu')) {	
          // prevent opening link if link needs to open dropdown
          e.preventDefault();
          if(nextEl.style.display == 'block'){
            nextEl.style.display = 'none';
          } else {
            nextEl.style.display = 'block';
          }

        }
    });
  })
    }
    // end if innerWidth
});
// DOMContentLoaded  end

$(document).ready(function() {

    $(".navbar-toggler").on('click', function() {
        $(".menu-overlay").fadeIn(500);

    });
    $(".menu-overlay").click(function(event) {
        $(".navbar-toggler").trigger("click");
        $(".menu-overlay").fadeOut(500);
    });


    /* $(window).scroll(function(){
		if($(this).scrollTop()>120){
		  $('.navbar-default').addClass("fixed-top");
		}
		else{
		  $('.navbar-default').removeClass("fixed-top");
		}
	 }); */

    $('.forgetext').off('click').on('click', function(event) {
        $('.signupbody').hide();
        $('.forgotbody').show();
    });


});