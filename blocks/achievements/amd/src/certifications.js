define([
    'local_skillrepository/jquery.dataTables',
    'core/str',
    'core/modal_factory',
    'core/modal_events',
    'core/ajax',
    'core/fragment',
    'local_costcenter/cardPaginate',
    'jquery',
    'jqueryui',
], function (dataTable, Str, ModalFactory, ModalEvents, Ajax, Fragment,CardPaginate, $) {
    return{
        achinfotable: function(userid){
            options = {targetID: 'certifications_tabdata',perPage:6,cardClass: 'w_oneintwo', viewType:'table',methodName: 'block_achievements_manageachievementblockviewcertifications',templateName: 'block_achievements/achievementsview_certifications'};
           

            dataoptions = {userid: userid,contextid: 1};

            filterdata = {};
            
            CardPaginate.reload(options, dataoptions,filterdata);
        },
    }
});