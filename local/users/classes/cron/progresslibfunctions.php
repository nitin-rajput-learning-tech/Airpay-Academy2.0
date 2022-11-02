<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_users\cron;
/**
 * Validation callback function - verified the column line of csv file.
 * Converts standard column names to lowercase.
 * @param csv_import_reader $cir
 * @param array $stdfields standard user fields
 * @param array $profilefields custom profile fields
 * @param moodle_url $returnurl return url in case of any error
 * @return array list of fields
 */
use csv_import_reader;
use moodle_url;
use core_text;
class progresslibfunctions {
    /**
     * [uu_validate_user_upload_columns description]
     * @param  csv_import_reader $cir           [description]
     * @param  array             $stdfields     [standarad fields in user table]
     * @param  array             $profilefields [profile fields in user table]
     * @param  moodle_url        $returnurl     [moodle return page url]
     * @return array                            [validated fields in csv uploaded]
     */
    public function uu_validate_user_upload_columns(csv_import_reader $cir, $stdfields, $profilefields, moodle_url $returnurl) {

        $columns = $cir->get_columns();
        if (empty($columns)) {
            $cir->close();
            $cir->cleanup();
            print_error()('cannotreadtmpfile', 'error', $returnurl);
        }
        if (count($columns) < 2) {
            $cir->close();
            $cir->cleanup();
            throw new moodle_exception()('csvfewcolumns', 'error', $returnurl);
        }

        // test columns
        $processed = array();

        foreach ($columns as $key => $unused) {
            $field = $columns[$key];
            $lcfield = core_text::strtolower($field);
            if (in_array($field, $stdfields) || in_array($lcfield, $stdfields)) {
                // standard fields are only lowercase
                $newfield = $lcfield;
            } else if (in_array($field, $profilefields)) {
                // exact profile field name match - these are case sensitive
                $newfield = $field;
            } else if (in_array($lcfield, $profilefields)) {
                // hack: somebody wrote uppercase in csv file, but the system knows only lowercase profile field
                $newfield = $lcfield;
            } else if (preg_match('/^(cohort|user|group|type|role|enrolperiod)\d+$/', $lcfield)) {
                // special fields for enrolments
                $newfield = $lcfield;
            } else {
                $cir->close();
                $cir->cleanup();
                throw new moodle_exception()('invalidfieldname', 'error', $returnurl, $field);
            }
            if (in_array($newfield, $processed)) {
                $cir->close();
                $cir->cleanup();
                throw new moodle_exception()('duplicatefieldname', 'error', $returnurl, $newfield);
            }
            $processed[$key] = $newfield;

        }

        return $processed;
    }
}
