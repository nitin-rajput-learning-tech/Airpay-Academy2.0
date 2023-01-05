//pagingCtrl
var myModule = angular.module('catalog', ['angularUtils.directives.dirPagination'], function($interpolateProvider) {
    $interpolateProvider.startSymbol('<%');
    $interpolateProvider.endSymbol('%>');
});
myModule.controller('courseController', function ($scope, $http,$location) {
    $scope.tab = 6;

    var url = M.cfg.wwwroot + '/local/search/filterslist.php';
    $http.get(url).success( function(response) {
        $scope.filteritemslist =  response;
    });

    $scope.tabfunction = function(tab, page, search_criteria) {
        if (page<1) {
            page=1;
        }

        if(typeof page == 'undefined'){
          page=1;
        }

        if(typeof search_criteria == 'undefined'){
           search_criteria=null;
        }

        checkedfilters = [];
        $scope.selectedfilters = [];
        $.each($(".filter_section .module_filter_params"), function(index, value){
            values = [];
            type = $(value).data('filtertype');
            checkedfilters = $(value).find("input[type='checkbox']:checked");
            $.each(checkedfilters, function( filterindex, filtervalue ) {
                values.push($(filtervalue).val());
            });
            if(!$.isEmptyObject(filters))
                $scope.selectedfilters.push({type, values});
        });
        // $.each(checkedfilters, function( index, value ) {
        //     $scope.selectedfilters.push($(value).val());
        // });
        filters = JSON.stringify($scope.selectedfilters);

        var en_selectedfilters = encodeURIComponent(filters);
        var dynamicurl = M.cfg.wwwroot + '/local/search/allcourses.php#?&selectedfilters='+en_selectedfilters;
        $('.dynamicurl').html(dynamicurl);
        $("#urlbtn").text('Copy URL');
        $scope.tab=tab;

        $scope.showLoader = true;
        var url = M.cfg.wwwroot + '/local/search/courseajax.php?tab='+tab+'&page='+page+'&search='+search_criteria+'&selectedfilters='+en_selectedfilters;
        $http.get(url).success( function(response) {
            $scope.showLoader = false;
            $scope.courseinfo = response;
            $scope.numberofrecords =  response.numberofrecords;
        });
    }
    $scope.init = function(tab){
        $scope.showLoader = true;
        $scope.tabfunction(tab, 0, '');
    };
    $scope.pageChangeHandler = function(num,tab) {
        var search_criteria=angular.element('#search').val();
        $scope.tabfunction(tab, num, search_criteria);

    };
    $scope.filterbyname= function(tab){
        var search_criteria = angular.element('#search').val();
        $scope.tabfunction(tab,0,search_criteria);
    };
    $scope.moreitemslist = [];
    $scope.getitemslist= function (catid){

        var url = M.cfg.wwwroot + '/local/search/filterslist.php?catid='+catid+'&action=itemslist';
        var className = $('#viewmoreless_'+catid).attr('class');
        if(className == "viewmore"){
            $("#viewmoreless_"+catid).text('View Less');
            $("#viewmoreless_"+catid).removeClass('viewmore');
            $("#viewmoreless_"+catid).addClass('viewless');
            $http.get(url).success( function(response) {
                $scope.moreitemslist[catid] =  response;
            });
        }else if(className == "viewless"){
            $(".moreitemslist_"+catid).remove();
            $("#viewmoreless_"+catid).removeClass('viewless');
            $("#viewmoreless_"+catid).addClass('viewmore');
            $("#viewmoreless_"+catid).text('View More');
        }
    }
    $scope.copytoClipboard= function (){
        var str =    $("#dynamicurl").html();
        var str = str.replace(/&amp;/g, '&');
        const el = document.createElement('textarea');
        el.value = str;
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);
        $("#urlbtn").text('Copied URL');
    }
    $scope.clearFilters= function (){
        $("input[type='checkbox']:checked").prop('checked', false);
        $("#search").val('');
        $scope.tabfunction(6,0,'');
    }

});
myModule.filter('unsafe', ['$sce', function ($sce) {
    return function (val) {
        return $sce.trustAsHtml(val);
    };
}]);
