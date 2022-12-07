
 //pagingCtrl
//var myModule = angular.module('hello', ['angularUtils.directives.dirPagination']);
  var myModule = angular.module('catalog', ['angularUtils.directives.dirPagination'], function($interpolateProvider) {
    $interpolateProvider.startSymbol('<%');
    $interpolateProvider.endSymbol('%>');

  });

    myModule.controller('courseController', function ($scope, $http,$location) {
 // function courseController($scope,$http) {
         $scope.employees = [];
         $scope.sorting = [{'name':'highrate', 'display':'High rating'}, {'name':'lowrate', 'display':'Low rating'},{'name':'latest', 'display':'Latest'},{'name':'oldest', 'display':'Oldest'}];
         $scope.tab= 6;
         $scope.categorylist;
        // var url = M.cfg.wwwroot + '/local/search/courseajax.php?tab=7';            
        // $http.get(url).success( function(response) {  
        //      // console.log(response);                      
        //     $scope.categorylist =  response; 
   
        // });

        checkedvalues = [];
        var lformats = $location.search().lformats;
        // alert(typeof(checkedvalues));
        if(lformats){
          lformats = decodeURIComponent(lformats);
          checkedvalues = JSON.parse(lformats);
          $scope.checkeddatas = [];
          $.each(checkedvalues, function( index, value ) { 
                  $scope.checkeddatas[value] = value;

          })
        }

        vendorcheckedvalues = [];
        var vendors = $location.search().vendors;
        if(vendors){
          vendors = decodeURIComponent(vendors);
          vendorcheckedvalues = JSON.parse(vendors);
          $scope.vendorcheckeddatas = [];
          $.each(vendorcheckedvalues, function( index, value ) { 
                  $scope.vendorcheckeddatas[value] = value;

          })
        }

        tagcheckedvalues = [];
        var selectedtags = $location.search().selectedtag;

        if(selectedtags){
          selectedtags = decodeURIComponent(selectedtags);
          tagcheckedvalues = JSON.parse(selectedtags);
          $scope.tagcheckeddatas = [];
          $.each(tagcheckedvalues, function( index, value ) { 
                  $scope.tagcheckeddatas[value] = value;

          })
        }
        




       
        var url = M.cfg.wwwroot + '/local/search/filterslist.php';            
        $http.get(url).success( function(response) {   
                      $scope.categoryitemslist =  response; 
        });

        $scope.tabfunction = function(tab, page, search_criteria, category, enrolltype, sortid, fromtab,fromurl=false) {
            if (page<1) {
                page=1;
            }

            if(tagcheckedvalues && fromurl){
              seleteditems = tagcheckedvalues;

            }else{
              var seleteditems = [];
              $.each($("input[name='tagitem']:checked"), function(){
                console.log($(this));
                console.log($(this).val());
                seleteditems.push($(this).val());
              });
            }
            console.log(seleteditems);
            filters = JSON.stringify(seleteditems);


            // if(vendorcheckedvalues && fromurl){
            //   seletedvendors =vendorcheckedvalues;
            // }else{
            //   var seletedvendors = [];
            //   $.each($("input[name='contentvendor']:checked"), function(){
            //     seletedvendors.push($(this).val());
            //   });
            // }
            // vendorsfilter = JSON.stringify(seletedvendors);

            // if(checkedvalues && fromurl){
            //     seletedlformats = checkedvalues;
            // }else{
            //   var seletedlformats = [];
            //   $.each($("input[name='lformat']:checked"), function(){
            //     seletedlformats.push($(this).val());
            //   });
            // }
            // lformatsfilter = JSON.stringify(seletedlformats);

            if(typeof page == 'undefined'){
              page=1;
            }
            
            if(typeof search_criteria == 'undefined'){
               search_criteria=null;
            }
            
            if(typeof enrolltype == 'undefined'){
               enrolltype=0;
            }
            // var en_vendorsfilter = encodeURIComponent(vendorsfilter);
            var en_filters = encodeURIComponent(filters);
            // var en_lformatsfilter = encodeURIComponent(lformatsfilter);

           var dynamicurl = M.cfg.wwwroot + '/local/search/allcourses.php#?&selectedtag='+en_filters;
            $('.dynamicurl').html(dynamicurl);    
              $("#urlbtn").text('Copy URL');

            // if (fromtab==1) {
            //     angular.element('#enrolltype').val(0);
            //      angular.element('#search').val('');
            // }   
            $scope.sortid=sortid;
            $scope.tab=tab;
            
            if (tab) {
               $.each([ 1,2,3,4,5,6 ], function( index, value ) {                 
                     if (tab==value) {                        
                          angular.element('.tab'+tab).addClass('active');
                     }
                     else{                         
                         if(angular.element('.tab'+value).hasClass('active')){                              
                            angular.element('.tab'+value).removeClass('active');
                         }
                     }
               });
            }

          $scope.showLoader = true; 
           var url = M.cfg.wwwroot + '/local/search/courseajax.php?tab='+tab+'&page='+page+'&search='+search_criteria+'&category='+category+'&enrolltype='+enrolltype+'&sortid='+sortid+'&selectedtag='+filters;
            
            $http.get(url).success( function(response) {
                  $scope.showLoader = false;  
                 $scope.courseinfo = response;                
                 $scope.numberofrecords =  response.numberofrecords;                 
            });
          }
        


          $scope.categories = function(){ 
            $scope.categorylist;
            var url = M.cfg.wwwroot + '/local/search/courseajax.php?tab=7';            
            $http.get(url).success( function(response) {                      
              $scope.categorylist =  response;
            });
          };       
        
        
          $scope.init = function(tab){     
            $scope.showLoader = true; 
            $scope.tabfunction(tab,0,'','','','','',true);            
          };        

          $scope.pageChangeHandler = function(num,tab) {
               var categoryid=angular.element('#categoryid').val();
               
               var search_criteria=angular.element('#search').val();
               var enrolltype=angular.element('#enrolltype').val();
               var sortid=angular.element('#sortid').val();
               // alert(num+'---'+tab);
               // console.log(tab);
               if (tab == 1) {
                  var categoryid=angular.element('#categoryid').val();
                  $scope.tabfunction(tab,num,search_criteria, categoryid,enrolltype, sortid,tab);
               }else{
                  $scope.tabfunction(tab,num, search_criteria,categoryid,enrolltype, sortid,tab);
               }
          };
    
          $scope.filterbyname= function(tab){          
               var search_criteria=angular.element('#search').val();
               var sortid = angular.element('#sortid').val();
               //console.log(search_criteria);
               var enrolltype=angular.element('#enrolltype').val();
               if (tab==1) {
                    var categoryid=angular.element('#categoryid').val();
                    $scope.tabfunction(tab,0,search_criteria, categoryid,enrolltype, sortid);
               }
               else
               $scope.tabfunction(tab,0,search_criteria,0, enrolltype, sortid);
          };
          
          $scope.modelidchange = function (tab) {
               var categoryid=angular.element('#categoryid').val();
               var search_criteria=angular.element('#search').val();
               var enrolltype=angular.element('#enrolltype').val();
               var sortid=angular.element('#sortid').val();
               $scope.tabfunction(tab,0,search_criteria,categoryid,enrolltype, sortid,tab );
          }

          $scope.sortidchange = function (tab) {
               var categoryid=angular.element('#categoryid').val();
               var search_criteria=angular.element('#search').val();
               var enrolltype=angular.element('#enrolltype').val();
               var sortid=angular.element('#sortid').val();
               $scope.tabfunction(tab,0,search_criteria,categoryid,enrolltype, sortid,tab);
          }
          
          
          $scope.enrolltypechange= function (tab){             
               var categoryid=angular.element('#categoryid').val();
               var search_criteria=angular.element('#search').val();          
               var enrolltype=angular.element('#enrolltype').val();    
               var sortid=angular.element('#sortid').val();     
               $scope.tabfunction(tab,0,search_criteria,categoryid, enrolltype, sortid,tab );
          } // end of  enrolltypechange function
          $scope.moreitemslist = [];
          $scope.getitemslist= function (catid){   
              // alert(catid);
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
              
              

          } // end of  enrolltypechange function
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
              var categoryid=angular.element('#categoryid').val();
              var search_criteria=angular.element('#search').val();          
              var enrolltype=angular.element('#enrolltype').val();    
              var sortid=angular.element('#sortid').val();     
              $scope.tabfunction(6,0,search_criteria,categoryid, enrolltype, sortid,6 );
          }
     
    }); 
    
    myModule.filter('unsafe', ['$sce', function ($sce) {
        return function (val) {
            return $sce.trustAsHtml(val);
        };
    }]);
