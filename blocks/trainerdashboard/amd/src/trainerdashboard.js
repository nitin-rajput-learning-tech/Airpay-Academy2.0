/**
 * This file is part of eAbyas
 *
 * Copyright eAbyas Info Solutons Pvt Ltd, India
 *
 * This trainerdashboard is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This trainerdashboard is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this trainerdashboard.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author eabyas  <info@eabyas.in>
 * @package Bizlms 
 * @subpackage block_trainerdashboard
 */
define([
    'block_trainerdashboard/fragment',
    'core/mustache',
    'core/modal_factory',
    'core/modal_events',
    'core/str',
    'core/ajax',
    'core/templates',
    'core/fragment',
    'core/notification',
    'jquery',
    'jqueryui',
], function(Fragment,Mustache,ModalFactory, ModalEvents, Str, Ajax, Templates, fragment, notification, $) {
    return  {
        init: function(args) {
             Fragment.init();
        },
        requiredStrings: function(FragmentModule) {
            var params=JSON.parse(FragmentModule.args.params);
            var StringData = {};
            switch (FragmentModule.level) {
                case 'vmtotaltrainings':
                            StringData.vmtotaltrainingsstring = params.value;
                            StringData.vmtotaltrainings = '';
                break;
                case 'vmcmpltdtrainings':
                            StringData.vmcmpltdtrainingsstring = params.value;
                            StringData.vmcmpltdtrainings = '';
                break;
                case 'vmupcmngtrainings':
                            StringData.vmupcmngtrainingsstring = params.value;
                            StringData.vmupcmngtrainings = '';
                break;
                default:
                break;
            }
            return StringData;
        },
        
    };
});