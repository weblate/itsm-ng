<?php
/**
 * ---------------------------------------------------------------------
 * GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2015-2022 Teclib' and contributors.
 *
 * http://glpi-project.org
 *
 * based on GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2003-2014 by the INDEPNET Development Team.
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * GLPI is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 */

/**
 * @since 9.1
 */

// here we are going to try to unlock the given object
// url should be of the form: 'http://.../.../unlockobject.php?unlock=1[&force=1]&id=xxxxxx'
// or url should be of the form 'http://.../.../unlockobject.php?requestunlock=1&id=xxxxxx'
// to send notification to locker of object

$AJAX_INCLUDE = 1;
include('../inc/includes.php');
header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();
Session::checkLoginUser();

$ret = 0;
if (isset($_POST['unlock']) && isset($_POST["id"])) {
    // then we may have something to unlock
    $ol = new ObjectLock();
    if ($ol->getFromDB($_POST["id"])
        && $ol->deleteFromDB(1)) {
        if (isset($_POST['force'])) {
            Log::history(
                $ol->fields['items_id'],
                $ol->fields['itemtype'],
                [0, '', ''],
                0,
                Log::HISTORY_UNLOCK_ITEM
            );
        }
        $ret = 1;
    }

} elseif (isset($_POST['requestunlock'])
           && isset($_POST["id"])) {
    // the we must ask for unlock
    $ol = new ObjectLock();
    if ($ol->getFromDB($_POST["id"])) {
        NotificationEvent::raiseEvent('unlock', $ol);
        $ret = 1;
    }
} elseif (isset($_GET['lockstatus'])
           && isset($_GET["id"])) {
    $ol = new ObjectLock();
    if ($ol->getFromDB($_GET["id"])) {
        $ret = 1; // found = still locked
    } // else will return 0 = not found
}

echo $ret;
