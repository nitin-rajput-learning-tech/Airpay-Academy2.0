<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Class containing data for course competencies page
 *
 * @package    local_search
 * @copyright  2018 hemalathacarun <hemalatha@eabyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_search\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use templatable;
use renderer_base;
use stdClass;
use moodle_url;
use context_system;
use context_course;
use core_component;
use local_search\output\searchlib;

/**
 * Class containing data for course competencies page
 *
 * @copyright  2018 hemalatha c arun <hemalatha@eabyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class allcourses {

    /**
     * function will be triggered if active tab is elearning, 
     * logic to find out the elearning courses per page and startlimit of eleaning courses based on 
     * specific page    
     * @param $perpage - number of courses per page
     * @param array $noofrecords- It holds total number of each level/tab 
     * @param int $value - total number of records of elaraning courses 
     * @return no return statement(just embeding code into called function)   
     */

    private function toset_firstlevel_perpage_andstartlimit($totalrecords, $perpage, $value){

        $std_perpage = $perpage; 
        $firsttotal= $value;
        $firstlevelstart_pageno = $this->to_getlevel_startpagenumber($totalrecords);
        $firstlevelend_pageno = $this->to_getlevel_endpagenumber($totalrecords);

        //if total number of records of elearning courses is greater than perpage,
        // so we are finding out number of remainder placeholder to append the next tab courses
        if($value> $std_perpage){
            $firstlevel_remainder= ($value % $std_perpage);
        }

        // in case total number of elearning courses less than the perpage, to find out the empty spaces
        //so that we can append the next tab courses 
        if($value < $std_perpage && $value!=0){             
            $firstlevel_space= ($std_perpage-$value);
        }

        // if specific page number is less than the elearning tab end page number
        // end page number - will be calculated based on 
        // total number of records of elarning courses/ per page courses
        if(searchlib::$page <= ($firstlevelend_pageno-1)){

            // total number of records of eleaning courses is zero
            // It will set elearning courses perpage zero
            // elearning courses startlimit and its remainder will zero 
            if($value == 0){                        
                $eleperpage = 0;
                $elestartlimit = 0;
                $firstlevel_remainder = 0;
            }
            else if($value < $std_perpage){                 
                //----if total number of elearning courses less than the perpage,
                // it wil set, 
                // elearning courses perpage = total number of records of elearning courses
                 $eleperpage = $value;
                 $elestartlimit = 0;                        
            }else{                      
                // if total number of elearning courses greater than perpage, 
                //it will set,
                //elearning course start limit = current page number * per page number
                //elearning course per page  =  per page number
                $firstlevel_remainder = ($value % $std_perpage);                    
                $elestartlimit = searchlib::$page * $std_perpage;
                $eleperpage = $std_perpage;                                 
            }
        }  // end of main if condition

        $res =array('firstlevel_perpage'=> $eleperpage,
            'firstlevel_startlimit' => $elestartlimit,
            'firstlevel_startpageno'=> $firstlevelstart_pageno,
            'firstlevel_endpageno' => $firstlevelend_pageno,
            'firstlevel_remainder'=> ($value % $std_perpage),
            'firstlevel_space' => $firstlevel_space
        );
        return $res;   

    } // end of the function toset_elearning_perpage_andstartlimit
     
  

  private function toset_secondlevel_perpage_andstartlimit($totalrecords, $perpage, $value, $firstlevel_space, $firstlevel_remainder, $firstlevelstart_pageno ){

    $std_perpage = $perpage;
    $secondlevelstart_pageno = $this->to_getlevel_startpagenumber($totalrecords);
    $secondlevelend_pageno = $this->to_getlevel_endpagenumber($totalrecords);
    
    if(searchlib::$page >=($firstlevelstart_pageno-1) && searchlib::$page <= ($secondlevelend_pageno-1)){
        //---- get learning plan courses---
        if($value == 0){
            $lpperpage=0;
            $lpstartlimit=0;                    
        }else if($value < $std_perpage){         
            if($firstlevel_space)
                $lpperpage=$firstlevel_space;
            else
                $lpperpage=$value;
            
            $lpstartlimit=0;
            
        }else{
            if(searchlib::$page == ($firstlevelstart_pageno-1)){
                if($firstlevel_space){
                    $lpperpage =  $firstlevel_space;
                }
                else{
                $lpperpage = ($std_perpage-$firstlevel_remainder);
                }
                $lpstartlimit=0;
            }else{  
            
        
            if(searchlib::$page == ($firstlevelstart_pageno-1) && $firstlevel_space){
                $lpstartlimit = $firstlevel_space;
            }
            else{
                if($firstlevel_space){
                    //------when we can accomodate(not a remainder) values in same page, so always first level page=1
                    //---- so, keeping statically $this-page-1                              
                    $lpstartlimit =((searchlib::$page -1)  * $std_perpage)+$firstlevel_space;
                }
                else{
                    if($firstlevelstart_pageno==0){
                        $firstlevelstart_pageno=$firstlevelstart_pageno;
                    }
                    else{
                        $firstlevelstart_pageno=($firstlevelstart_pageno-1);
                    }                           
                    $lpstartlimit =( searchlib::$page - ($firstlevelstart_pageno))* $std_perpage-$firstlevel_remainder;
                }
            }
            
            $lpperpage = $std_perpage;
            }
        } // end of else statement 

    }

        $res =array('secondlvl_perpage'=> $lpperpage,
          'secondlvl_startlimit' => $lpstartlimit,
          'secondlvl_startpageno'=>$secondlevelstart_pageno,
          'secondlvl_endpageno' => $secondlevelend_pageno,
          'secondlvl_remainder' => $totalrecords % $perpage
          
           );
        return $res;       
     
    } // end of toset_secondlevel_perpage_andstartlimit


        //----- $secondlevel_remainder = $previouslevel_remainder
    //----- $secondlevelstart_pageno = $previouslevelstart_pageno
    private function toset_generic_perpage_andstartlimit($totalrecords, $perpage, $secondlevelstart_pageno, $secondlevel_pageno, $secondlevel_remainder, $value ){

        $std_perpage = $perpage;
        $thirdlevelstart_pageno = $this->to_getlevel_startpagenumber($totalrecords);
        $thirdlevel_pageno = $this->to_getlevel_endpagenumber($totalrecords);
                   
       if(searchlib::$page >=($secondlevelstart_pageno-1) && searchlib::$page <= ($thirdlevel_pageno-1)){
        //---- get learning plan courses---
        if($value == 0){
            $iltperpage=0;
            $iltstartlimit=0;                   
        }
        else if($value < $std_perpage &&  searchlib::$page == ($secondlevelstart_pageno-1)){
             $thirdlevel_remainder = 0;
             $iltperpage=$value;
             $iltstartlimit=0;
            
        }else{
            if(searchlib::$page == ($secondlevelstart_pageno-1)){
                $iltperpage = ($std_perpage-$secondlevel_remainder);
                $iltstartlimit=0;
            }else{
                
                if($secondlevelstart_pageno==0){
                        $secondlevelstart_pageno=$secondlevelstart_pageno;
                    }
                    else{
                        $secondlevelstart_pageno=($secondlevelstart_pageno-1);
                    }   
            
            $iltstartlimit =( searchlib::$page - ($secondlevelstart_pageno))* $std_perpage- $secondlevel_remainder;
            $iltperpage = $std_perpage;
            }
        } // end of else statement      
      } // end of if condition
    // echo 'iltperpage'.$iltperpage;
    $res =array('lvl_perpage'=> $iltperpage,
    'lvl_startlimit' => $iltstartlimit,
    'lvl_startpageno'=>$thirdlevelstart_pageno,
    'lvl_endpageno' =>$thirdlevel_pageno,
    'lvl_remainder' => $totalrecords % $perpage

    );
    
    return $res;   


  } // end of toset_nexttab_perpage_andstartlimit function 

   
   public function get_available_catalogtypes($selectedfilter = null){
    global $DB;

    $othertagitems = array();
    $standard_catalogtypes = [];
    $filterplugins = get_plugins_with_function('applicable_filters_for_search_page');
    $filterapplicable = [];
    $defaultPlugins = [];
    foreach($filterplugins AS $filtertypes){
        foreach($filtertypes AS $pluginname => $filtertype){
            $defaultPlugins[] = constant($pluginname);
            $filtertype($filterapplicable);
        }
    }
    asort($defaultPlugins);
    if($selectedfilter){

        foreach ($selectedfilter as $itemname) {
            $filter = explode('_', $itemname);
            if(count($filter) === 2){
                $thisfilters[$filter[0]][] = $filter[1];

            }
        }
        ;
        if($moduletype = $thisfilters['moduletype']){
            foreach($moduletype AS $module ){
                $ltype = constant($module);
                if(!in_array($ltype, $standard_catalogtypes)){
                    $standard_catalogtypes[] = $ltype;
                }
            }
        }else{
            foreach($thisfilters AS $filtertype => $filter){
                foreach($filterapplicable AS $plugin => $applicablefilters){
                    if(in_array($filtertype, $applicablefilters) && !in_array($plugin, $standard_catalogtypes)){
                        $standard_catalogtypes[] = $plugin;
                    }
                }
            }
        }
        if(empty($standard_catalogtypes)){
            $standard_catalogtypes = $defaultPlugins;
        }
    } else{
        $standard_catalogtypes = $defaultPlugins;
    }
    asort($standard_catalogtypes);
    $sumofallrecords = 0;
    foreach($standard_catalogtypes as $key => $type){
      switch($type){
       
       case learningplan :
            $classname = '\local_learningplan\output\search';
            if(class_exists($classname)){
                $class = new $classname();
                $availabletypes[] =  $type;
                $learning_plans_ar = $class->get_learningpathlist_query(1,searchlib::$page*1,true,true, $thisfilters);
                $totalrecords_ineachtype[]= array('numberofrecords'=>$learning_plans_ar['numberofrecords'],'type' =>'learningplan');
                $sumofallrecords += $learning_plans_ar['numberofrecords'];
            } // end of if condition
          break;

        case classroom:
            $classname = '\local_classroom\output\search';
            if(class_exists($classname)){
                $class = new $classname();
                $availabletypes[]=  $type;
                $classroom_ar= $class->get_facetofacelist_query(1,searchlib::$page*1,true,true,$thisfilters);
                $totalrecords_ineachtype[]= array('numberofrecords'=>$classroom_ar['numberofrecords'],'type' =>'classroom');
                $sumofallrecords += $classroom_ar['numberofrecords'];
            } // end of if condition
          break;

        default:
            $classname = '\local_courses\output\search';
            if(class_exists($classname)){
                $class = new $classname();
                $availabletypes[] = $type;
                $courseslist_ar = $class->get_elearning_courselist_query(1, searchlib::$page*1, true,false, $thisfilters);
                $totalrecords_ineachtype[]= array('numberofrecords'=>$courseslist_ar['numberofrecords'],'type'=>'elearning');
                $sumofallrecords += $courseslist_ar['numberofrecords'];
            }
          break;
        
      }// end of switch case
    } // end of foreach
   $response_array = array('totalrecords_ineachtype'=>$totalrecords_ineachtype,
                            'sumofallrecords'=>$sumofallrecords);
   return $response_array; 

   } // end of get_available_catalogtype  */

     
public function main_toget_catalogtypes($perpage, $selectedfilter = array()){
    global $DB;
    $othertagitems = array();
    if($selectedlformats){
       $selectedtag[] = ELE;
    }
    $response = $this->get_available_catalogtypes($selectedfilter);
    $totalrecords_ineachtype = $response['totalrecords_ineachtype'];
    $sumofallrecords = $response['sumofallrecords'];
    $finallist= array();
    foreach($totalrecords_ineachtype as $index => $record){ 
        $totalrecords += $record['numberofrecords'];
        $remainder =  $totalrecords % $perpage;
        if($index == 0){
            $res = $this->toset_firstlevel_perpage_andstartlimit($totalrecords, $perpage, $record['numberofrecords']);
            $firstlvl_perpage =$res['firstlevel_perpage'];
            $firstlvl_startlimit = $res['firstlevel_startlimit'];
            $iteration_startpageno = $res['firstlevel_startpageno'];
            $iteration_endpageno  = $res['firstlevel_endpageno'];
            $iteration_space = $res['firstlevel_space'];
            $iteration_remainder = $res['firstlevel_remainder'];
            if($firstlvl_perpage){
                $firstresult_ar = $this->to_finding_specific_catalogtype($record['type'], $firstlvl_perpage,$firstlvl_startlimit, $selectedfilter);
                $finallist = $firstresult_ar + $finallist;
                $ss[] = $firstresult_ar;
            }
        } else if ($index ==1) {
            $secondres_ar=$this->toset_secondlevel_perpage_andstartlimit($totalrecords, $perpage, $record['numberofrecords'], $temp_space, $temp_remainder, $temp_startpageno);
            $secondlvl_perpage= $secondres_ar['secondlvl_perpage'];
            $secondlvl_startlimit = $secondres_ar['secondlvl_startlimit'];
            if($secondlvl_perpage){
                $secondresult_ar = $this->to_finding_specific_catalogtype($record['type'], $secondlvl_perpage,$secondlvl_startlimit, $selectedfilter);
                $ss[] = $secondresult_ar;
                $finallist = $secondresult_ar+$finallist;
            }
            $iteration_startpageno=$secondres_ar['secondlvl_startpageno'];
            $iteration_endpageno = $secondres_ar['secondlvl_endpageno'];
            $iteration_remainder = $secondres_ar['secondlvl_remainder'];
        } else {
            $lvlresult_ar=$this->toset_generic_perpage_andstartlimit($totalrecords, $perpage, $temp_startpageno, $temp_endpageno, $temp_remainder,  $record['numberofrecords']);
            $lvl_perpage= $lvlresult_ar['lvl_perpage'];
            $lvl_startlimit = $lvlresult_ar['lvl_startlimit'];
            if($lvl_perpage){
                $lvlresult_list = $this->to_finding_specific_catalogtype($record['type'], $lvl_perpage,$lvl_startlimit, $selectedfilter);
                $finallist = $lvlresult_list+$finallist;
                $ss[] = $lvlresult_list;
            }
            $iteration_startpageno=$lvlresult_ar['lvl_startpageno'];
            $iteration_endpageno = $lvlresult_ar['lvl_endpageno'];
            $iteration_remainder = $lvlresult_ar['lvl_remainder'];
        }
        $temp_startpageno = $iteration_startpageno;
        $temp_endpageno = $iteration_endpageno;
        $temp_space = $iteration_space;
        $temp_remainder = $iteration_remainder;
    }//end of foreach
    if($ss){
        foreach($ss as $key=>$records){
            foreach($records as $key=> $record){
                if($record && ( is_numeric($key))){
                    $response_array[] = $record;
                }
            }
        }
    }
     
    $response_array['numberofrecords'] = $sumofallrecords;  
    
    return $response_array;  
  } // end of  main_toget_catalogtypes function


 private function to_finding_specific_catalogtype($recordtype, $level_perpage,$level_startlimit, $selectedfilter = array()){
        $finallist = array();
        switch($recordtype){
            case 'elearning' :
                $classname = '\local_courses\output\search';
                if(class_exists($classname)){
                    $class = new $classname();
                    $courselist = $class->export_for_template($level_perpage, $level_startlimit,$selectedfilter);
                    $finallist =$this->get_array_format($courselist);
                }
            break;
            case 'learningplan' :
                $classname = '\local_learningplan\output\search';
                if(class_exists($classname)){
                    $class = new $classname();
                    $lplist = $class->export_for_template($level_perpage, $level_startlimit,$selectedfilter);
                    $finallist = $this->get_array_format($lplist);
                }
            break;
            case 'classroom' :
                $classname = '\local_classroom\output\search';
                if(class_exists($classname)){
                    $class = new $classname();
                    $lplist = $class->export_for_template($level_perpage, $level_startlimit,$selectedfilter);
                    $finallist = $this->get_array_format($lplist);
                }
            break;
       } // end of switch statement

      return $finallist;

   } // end  of  to_finding_specific_catalogtype function

    private function get_array_format($lists){
      $response=array();
      
      foreach($lists as $key=>$record){     
        if($record && ( is_numeric($key))){
          $response[] = $record;
                  
        }     
      }

      return  $response;    
    } // end of get_array_format


 private function to_get_level_total($arraykeys, $noofrecords){
      $totalrecords=0;
      foreach($arraykeys as $key){
        $totalrecords = $totalrecords + $noofrecords[$key];     
      }   
      
      return $totalrecords;
    } // end of function


    /**
    * to get the starting pagenumber of called level/tabs('elearning, classroom, program, certification, * learningpath')
    *    
    * @param array $arraykeys - It holds the array keys from starting to till called tab or level.
    * Example array(1,2) - means its in second tab classroom
    * @param array $noofrecords- It holds total number of each level/tab 
    * @return int starting page number of specific tab   
    */
    private function to_getlevel_startpagenumber($totalrecords){
       /* $totalrecords=0;
        foreach($arraykeys as $key){
            $totalrecords = $totalrecords + $noofrecords[$key];     
        } */
        
        $std_perpage=15;
        if($totalrecords==0){
            $level_pageno=0;
        }else if($totalrecords<$std_perpage){
            $level_pageno=1;
        }else{
            $level_pageno =floor($totalrecords/$std_perpage);
            $level_remainder= ($totalrecords % $std_perpage);
            
            $level_pageno = $level_pageno+1;
        }
        
        return $level_pageno;
    } // end of function


 private function to_getlevel_endpagenumber($totalrecords){

        $std_perpage=15;
        if($totalrecords==0){
            $level_pageno=0;
        }
        else if($totalrecords<$std_perpage){
            $level_pageno=1;
        }
        else{
         $level_pageno =floor($totalrecords/$std_perpage);
         $level_remainder= ($totalrecords % $std_perpage);
            if($level_remainder){
                    $level_pageno = $level_pageno+1;
            }
        }
        
        return $level_pageno;
    } // end of function
    

    /**
     * to get the starting pagenumber of called level/tabs('elearning, classroom, program, certification, * learningpath')
     *    
     * @param array $arraykeys - It holds the array keys from starting to till called tab or level.
     * Example array(1,2) - means its in second tab classroom
     * @param array $noofrecords- It holds total number of each level/tab 
     * @return int starting page number of specific tab   
     */
    private function to_get_level_startpagenumber($arraykeys, $noofrecords){
        $totalrecords=0;
        foreach($arraykeys as $key){
            $totalrecords = $totalrecords + $noofrecords[$key];     
        }
        
        $std_perpage=15;
        if($totalrecords==0){
            $level_pageno=0;
        }else if($totalrecords<$std_perpage){
            $level_pageno=1;
        }else{
            $level_pageno =floor($totalrecords/$std_perpage);
            $level_remainder= ($totalrecords % $std_perpage);
            
            $level_pageno = $level_pageno+1;
        }
        
        return $level_pageno;
    } // end of function
    
    
    private function to_get_level_endpagenumber($arraykeys, $noofrecords){
        $totalrecords=0;
        foreach($arraykeys as $key){
            $totalrecords = $totalrecords + $noofrecords[$key];     
        }
        
        $std_perpage=15;
        if($totalrecords==0){
            $level_pageno=0;
        }
        else if($totalrecords<$std_perpage){
            $level_pageno=1;
        }
        else{
         $level_pageno =floor($totalrecords/$std_perpage);
         $level_remainder= ($totalrecords % $std_perpage);
            if($level_remainder){
                    $level_pageno = $level_pageno+1;
            }
        }
        
        return $level_pageno;
    } // end of function

} // end of class






