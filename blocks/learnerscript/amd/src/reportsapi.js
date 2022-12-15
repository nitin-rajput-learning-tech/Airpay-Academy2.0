define(['jquery',
    'jqueryui',
    'core/ajax'
], function($, $jqui, Ajax,) {
    return {
        init: function(reportid, reporttype, basicparams, instanceid) {
            var promise = Ajax.call([{
                // methodname: 'block_learnerscript_generate_plotgraph',
                methodname: 'learnerscript_reportsapi',
                args: {
                    reportid: reportid,
                    reporttype: reporttype,
                    instanceid: instanceid,
                    basicparams: basicparams
                }
            }]);
            promise[0].done(function(data) {
            // var params = {};
            // var reportinstance = 137;
            // params['filters'] = args.filters;
            // params['basicparams'] = args.basicparams || JSON.stringify(smartfilter.BasicparamsData(reportinstance));                
            //     alert(params);
                data = JSON.parse(data);
                // alert(data.data);
                var content = JSON.stringify(data);
                $(".apicontent").text(content);
            });
        }
    }
});