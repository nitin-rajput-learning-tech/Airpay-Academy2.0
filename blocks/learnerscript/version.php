<?php
/**
 * This file is part of eAbyas
 *
 * Copyright eAbyas Info Solutons Pvt Ltd, India
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author eabyas  <info@eabyas.in>
 * @package BizLMS
 * @subpackage block_learnerscript
 */
defined('MOODLE_INTERNAL') || die();

// SENTIENTIA-CORE-MOD (vendor): version .5 -> .6 (2026-10-08) so the rebuilt amd/build (FX-08 modal port and its
// single-destroy follow-up) reaches existing sites through Admin > Notifications and the cache purge that follows.
// Kept in the vendor's decimal series: a later upstream release (2019052009 or higher) still upgrades cleanly.
// No schema change, no upgrade step. Record: docs/core-mods/2026-10-08-learnerscript-modal-factory-port.md
$plugin->version = 2019052008.6; // Plugin version.
$plugin->requires = 2019052000; // require Moodle version (3.7).
$plugin->component = 'block_learnerscript'; // Full name of the plugin (used for diagnostics)
$plugin->dependencies = array();
