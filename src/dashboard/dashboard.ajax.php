<?php
/**
 * ---------------------------------------------------------------------
 * ITSM-NG
 * Copyright (C) 2022 ITSM-NG and contributors.
 *
 * https://www.itsm-ng.org
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of ITSM-NG.
 *
 * ITSM-NG is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * ITSM-NG is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with ITSM-NG. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 */

include ('../../inc/includes.php');

if (!isset($_REQUEST["action"])) {
   exit;
}

global $CFG_GLPI;

if ($_REQUEST['action'] == 'preview' && isset($_REQUEST['statType']) && isset($_REQUEST['statSelection'])) {
   Session::checkRight("dashboard", READ);
   try {
      $statType = $_REQUEST['statType'];
      $statSelection = stripslashes($_REQUEST['statSelection']);
      
      $format = $_REQUEST['format'] ?? 'count';
      $data = Dashboard::getDashboardData(Dashboard::getWidgetUrl($format, $statType, $statSelection, $_REQUEST['options']));

      $options = Dashboard::parseOptions($format, $_REQUEST['options'] ?? [], $data);
      
      $widget = [
         'type' => $format,
         'value' => $data,
         'title' => $_REQUEST['title'] ?? $_REQUEST['statType'],
         'icon' => $options['icon'] ?? '',
         'options' => $options,
      ];
      
      renderTwigTemplate('dashboard/widget.twig', [ 'widget' => $widget ]);
   } catch (Exception $e) {
      echo $e->getMessage();
   }
} else if (($_REQUEST['action'] == 'delete') && isset($_REQUEST['coords']) && isset($_REQUEST['id'])) {
   Session::checkRight("dashboard", UPDATE);
   $dashboard = new Dashboard();
   $dashboard->getFromDB($_REQUEST['id']);
   if ($dashboard->deleteWidget(json_decode($_REQUEST['coords']))) {
      echo json_encode(["status" => "success"]);
   } else {
      echo json_encode(["status" => "error"]);
   }
   exit;
} else if (($_REQUEST['action'] == 'add') && isset($_REQUEST['coords']) && isset($_REQUEST['id'])) {
   Session::checkRight("dashboard", UPDATE);
   
   $dashboard = new Dashboard();
   $dashboard->getFromDB($_REQUEST['id']);
   
   $format = $_REQUEST['format'] ?? 'count';
   $coords = $_REQUEST['coords'];
   $title = $_REQUEST['title'] ?? $_REQUEST['statType'];
   $statType = $_REQUEST['statType'];
   $statSelection = stripslashes($_REQUEST['statSelection']);
   $options = $_REQUEST['options'] ?? [];
   if ($dashboard->addWidget($format, $coords, $title, $statType, $statSelection, $options)) {
      echo json_encode(["status" => "success"]);
   } else {
      echo json_encode(["status" => "error"]);
   }
   exit;
} else if (($_REQUEST['action'] == 'getColumns')  && isset($_REQUEST['statType'])) {
   Session::checkRight("dashboard", READ);
   $statType = $_REQUEST['statType'];
   $data = Dashboard::getDashboardData("/dashboard/comparisons/" . $statType);
   echo json_encode($data);
   exit;
}