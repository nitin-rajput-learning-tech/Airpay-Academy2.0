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
		skillinfotable: function(userid){
            options = {targetID: 'manage_blockskill',perPage:6,cardClass: 'w_oneintwo', viewType:'table',methodName: 'block_myskills_manageblockskill_view',templateName: 'block_myskills/myskills_view'};
           

            dataoptions = {userid: userid,contextid: 1};

            filterdata = {};
            
			CardPaginate.reload(options, dataoptions,filterdata);
		},
        achinfotable: function(userid){
            options = {targetID: 'badges_tabdata',perPage:6,cardClass: 'w_oneintwo', viewType:'table',methodName: 'block_achievements_manageachievementblockviewbadges',templateName: 'block_achievements/achievementsview_badges'};
           

            dataoptions = {userid: userid,contextid: 1};

            filterdata = {};
            
            CardPaginate.reload(options, dataoptions,filterdata);
        },
	}
});