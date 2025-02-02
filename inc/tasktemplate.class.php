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

use itsmng\Timezone;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

/**
 * Template for task
 * @since 9.1
**/
class TaskTemplate extends CommonDropdown {

   // From CommonDBTM
   public $dohistory          = true;
   public $can_be_translated  = true;

   static $rightname          = 'taskcategory';



   static function getTypeName($nb = 0) {
      return _n('Task template', 'Task templates', $nb);
   }


   function getAdditionalFields() {

      return [
         __('Content') => [
            'name'  => 'content',
            'type'  => 'richtextarea',
            'value' => $this->fields['content'],
            'col_lg' => 12,
            'col_md' => 12,
         ],
         TaskCategory::getTypeName(1) => [
            'name'  => 'taskcategories_id',
            'type'  => 'select',
            'values' => getOptionForItems('TaskCategory'),
            'value' => $this->fields['taskcategories_id']
         ],
         __('Status') => [
            'name'  => 'state',
            'type'  => 'select',
            'values' => getOptionForItems('State'),
            'value' => $this->fields['state']
         ],
         __('Private') => [
            'name'  => 'is_private',
            'type'  => 'checkbox',
            'value' => $this->fields['is_private']
         ],
         __('Duration') => [
            'name'  => 'actiontime',
            'type'  => 'select',
            // 'values' => Timezone::GetTimeStamp([
            //    'min'             => 1,
            //    'max'             => 100*HOUR_TIMESTAMP,
            //    'value'           => $this->fields["actiontime"],
            //    'addfirstminutes' => true,
            //    'inhours'         => true
            // ])
            'values' => []

         ],
         __('By') => [
            'name'  => 'users_id_tech',
            'type'  => 'select',
            'values' => getOptionsForUsers('own_ticket'),
            'value' => $this->fields['users_id_tech']
         ],
         Group::getTypeName(1) => [
            'name'  => 'groups_id_tech',
            'type'  => 'select',
            'values' => getOptionForItems('Group', ['is_task' => 1]),
            'value' => $this->fields['groups_id_tech']
         ],
      ];
   }


   function rawSearchOptions() {
      $tab = parent::rawSearchOptions();

      $tab[] = [
         'id'                 => '4',
         'name'               => __('Content'),
         'field'              => 'content',
         'table'              => $this->getTable(),
         'datatype'           => 'text',
         'htmltext'           => true
      ];

      $tab[] = [
         'id'                 => '3',
         'name'               => TaskCategory::getTypeName(1),
         'field'              => 'name',
         'table'              => getTableForItemType('TaskCategory'),
         'datatype'           => 'dropdown'
      ];

      $tab[] = [
         'id'                 => '5',
         'table'              => $this->getTable(),
         'field'              => 'is_private',
         'name'               => __('Private'),
         'datatype'           => 'bool'
      ];

      $tab[] = [
         'id'                 => '7',
         'table'              => 'glpi_users',
         'field'              => 'name',
         'linkfield'          => 'users_id_tech',
         'name'               => __('By'),
         'datatype'           => 'dropdown',
         'right'              => 'own_ticket'
      ];

      $tab[] = [
         'id'                 => '8',
         'table'              => 'glpi_groups',
         'field'              => 'completename',
         'linkfield'          => 'groups_id_tech',
         'name'               => Group::getTypeName(1),
         'condition'          => ['is_task' => 1],
         'datatype'           => 'dropdown'
      ];

      $tab[] = [
         'id'                 => '9',
         'table'              => $this->getTable(),
         'field'              => 'actiontime',
         'name'               => __('Total duration'),
         'datatype'           => 'actiontime',
         'massiveaction'      => false
      ];

      $tab[] = [
         'id'                 => '10',
         'table'              => $this->getTable(),
         'field'              => 'state',
         'name'               => __('Status'),
         'searchtype'         => 'equals',
         'datatype'           => 'specific'
      ];

      return $tab;
   }


   static function getSpecificValueToDisplay($field, $values, array $options = []) {

      if (!is_array($values)) {
         $values = [$field => $values];
      }

      switch ($field) {
         case 'state' :
            return Planning::getState($values[$field]);
      }
      return parent::getSpecificValueToDisplay($field, $values, $options);
   }


   static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = []) {

      if (!is_array($values)) {
         $values = [$field => $values];
      }
      $options['display'] = false;

      switch ($field) {
         case 'state':
            return Planning::dropdownState($name, $values[$field], false);
      }
      return parent::getSpecificValueToSelect($field, $name, $values, $options);
   }

   /**
    * @see CommonDropdown::displaySpecificTypeField()
   **/
   function displaySpecificTypeField($ID, $field = []) {

      switch ($field['type']) {
         case 'state' :
            Planning::dropdownState("state", $this->fields["state"]);
            break;
         case 'users_id_tech' :
            User::dropdown([
               'name'   => "users_id_tech",
               'right'  => "own_ticket",
               'value'  => $this->fields["users_id_tech"],
               'entity' => $this->fields["entities_id"],
            ]);
            break;
         case 'groups_id_tech' :
            Group::dropdown([
               'name'     => "groups_id_tech",
               'condition' => ['is_task' => 1],
               'value'     => $this->fields["groups_id_tech"],
               'entity'    => $this->fields["entities_id"],
            ]);
            break;
         case 'actiontime' :
            $toadd = [];
            for ($i=9; $i<=100; $i++) {
               $toadd[] = $i*HOUR_TIMESTAMP;
            }
            Dropdown::showTimeStamp(
               "actiontime", [
                  'min'             => 0,
                  'max'             => 8*HOUR_TIMESTAMP,
                  'value'           => $this->fields["actiontime"],
                  'addfirstminutes' => true,
                  'inhours'         => true,
                  'toadd'           => $toadd
               ]
            );
            break;
      }
   }
}
