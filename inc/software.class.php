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

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

/** Software Class
**/
class Software extends CommonDBTM {
   use Glpi\Features\Clonable;

   // From CommonDBTM
   public $dohistory                   = true;

   static protected $forward_entity_to = ['Infocom', 'ReservationItem', 'SoftwareVersion'];

   static $rightname                   = 'software';
   protected $usenotepad               = true;

   public function getCloneRelations() :array {
      return [
         Infocom::class,
         Contract_Item::class,
         Document_Item::class,
         KnowbaseItem_Item::class
      ];
   }

   static function getTypeName($nb = 0) {
      return _n('Software', 'Software', $nb);
   }


   /**
    * @see CommonGLPI::getMenuShorcut()
    *
    *  @since 0.85
   **/
   static function getMenuShorcut() {
      return 's';
   }


   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {

      if (!$withtemplate) {
         switch ($item->getType()) {
            case __CLASS__ :
               if ($item->isRecursive()
                   && $item->can($item->fields['id'], UPDATE)) {
                  return __('Merging');
               }
               break;
         }
      }
      return '';
   }


   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {

      if ($item->getType() == __CLASS__) {
         $item->showMergeCandidates();
      }
      return true;
   }


   function defineTabs($options = []) {

      $ong = [];
      $this->addDefaultFormTab($ong);
      $this->addImpactTab($ong, $options);
      $this->addStandardTab('SoftwareVersion', $ong, $options);
      $this->addStandardTab('SoftwareLicense', $ong, $options);
      $this->addStandardTab('Item_SoftwareVersion', $ong, $options);
      $this->addStandardTab('Infocom', $ong, $options);
      $this->addStandardTab('Contract_Item', $ong, $options);
      $this->addStandardTab('Document_Item', $ong, $options);
      $this->addStandardTab('KnowbaseItem_Item', $ong, $options);
      $this->addStandardTab('Ticket', $ong, $options);
      $this->addStandardTab('Item_Problem', $ong, $options);
      $this->addStandardTab('Change_Item', $ong, $options);
      $this->addStandardTab('Link', $ong, $options);
      $this->addStandardTab('Notepad', $ong, $options);
      $this->addStandardTab('Reservation', $ong, $options);
      $this->addStandardTab('Domain_Item', $ong, $options);
      $this->addStandardTab('Appliance_Item', $ong, $options);
      $this->addStandardTab('Log', $ong, $options);
      $this->addStandardTab(__CLASS__, $ong, $options);

      return $ong;
   }


   function prepareInputForUpdate($input) {

      if (isset($input['is_update']) && !$input['is_update']) {
         $input['softwares_id'] = 0;
      }
      return $input;
   }


   function prepareInputForAdd($input) {

      if (isset($input['is_update']) && !$input['is_update']) {
         $input['softwares_id'] = 0;
      }

      if (isset($input["id"]) && ($input["id"] > 0)) {
         $input["_oldID"] = $input["id"];
      }
      unset($input['id']);
      unset($input['withtemplate']);

      //If category was not set by user (when manually adding a user)
      if (!isset($input["softwarecategories_id"]) || !$input["softwarecategories_id"]) {
         $softcatrule = new RuleSoftwareCategoryCollection();
         $result      = $softcatrule->processAllRules(null, null, Toolbox::stripslashes_deep($input));

         if (!empty($result)) {
            if (isset($result['_ignore_import'])) {
               $input["softwarecategories_id"] = 0;
            } else if (isset($result["softwarecategories_id"])) {
               $input["softwarecategories_id"] = $result["softwarecategories_id"];
            } else if (isset($result["_import_category"])) {
               $softCat = new SoftwareCategory();
               $input["softwarecategories_id"]
                  = $softCat->importExternal($input["_system_category"]);
            }
         } else {
            $input["softwarecategories_id"] = 0;
         }
      }
      return $input;
   }


   function cleanDBonPurge() {

      // SoftwareLicense does not extends CommonDBConnexity
      $sl = new SoftwareLicense();
      $sl->deleteByCriteria(['softwares_id' => $this->fields['id']]);

      $this->deleteChildrenAndRelationsFromDb(
         [
            Item_Project::class,
            SoftwareVersion::class,
         ]
      );
   }


   /**
    * Update validity indicator of a specific software
    *
    * @param $ID ID of the licence
    *
    * @since 0.85
    *
    * @return void
   **/
   static function updateValidityIndicator($ID) {

      $soft = new self();
      if ($soft->getFromDB($ID)) {
         $valid = 1;
         if (countElementsInTable('glpi_softwarelicenses',
                                  ['softwares_id'=>$ID,
                                   'NOT' => [ 'is_valid']]) > 0) {
            $valid = 0;
         }
         if ($valid != $soft->fields['is_valid']) {
            $soft->update(['id'       => $ID,
                               'is_valid' => $valid]);
         }
      }
   }


   /**
    * Print the Software form
    *
    * @param $ID        integer  ID of the item
    * @param $options   array    of possible options:
    *     - target filename : where to go when done.
    *     - withtemplate boolean : template or basic item
    *
    *@return boolean item found
   **/
   function showForm($ID, $options = []) {
      $title = __('New item').' - '.self::getTypeName(1);

      $form = [
         'action' => $this->getFormURL(),
         'buttons' => [
            $this->fields["is_deleted"] == 1 && self::canDelete() ? [
              'type' => 'submit',
              'name' => 'restore',
              'value' => __('Restore'),
              'class' => 'btn btn-secondary'
            ] : ($this->canUpdateItem() ? [
              'type' => 'submit',
              'name' => $this->isNewID($ID) ? 'add' : 'update',
              'value' => $this->isNewID($ID) ? __('Add') : __('Update'),
              'class' => 'btn btn-secondary'
            ] : []),
            !$this->isNewID($ID) && !$this->isDeleted() && $this->canDeleteItem() ? [
              'type' => 'submit',
              'name' => 'delete',
              'value' => __('Put in trashbin'),
              'class' => 'btn btn-danger'
            ] : (!$this->isNewID($ID) && self::canPurge() ? [
              'type' => 'submit',
              'name' => 'purge',
              'value' => __('Delete permanently'),
              'class' => 'btn btn-danger'
            ] : []),
          ],
         'content' => [
            $title => [
               'visible' => true,
               'inputs' => [
                  __('Name') => [
                     'name' => 'name',
                     'type' => 'text',
                     'value' => $this->fields['name'],
                  ],
                  __('Publisher') => [
                     'name' => 'manufacturers_id',
                     'type' => 'select',
                     'value' => $this->fields['manufacturers_id'],
                     'values' => getOptionForItems("Manufacturer"),
                     'actions' => getItemActionButtons(['info', 'add'], "Manufacturer"),
                  ],
                  __('Location') => [
                     'name' => 'locations_id',
                     'type' => 'select',
                     'value' => $this->fields['locations_id'],
                     'values' => getOptionForItems("Location", ['entities_id' => $this->fields['entities_id']]),
                     'actions' => getItemActionButtons(['info', 'add'], "Location"),
                  ],
                  __('Category') => [
                     'name' => 'softwarecategories_id',
                     'type' => 'select',
                     'value' => $this->fields['softwarecategories_id'],
                     'values' => getOptionForItems("SoftwareCategory"),
                     'actions' => getItemActionButtons(['info', 'add'], "SoftwareCategory"),
                  ],
                  __("Technician in charge of the software") => [
                     'name' => 'users_id_tech',
                     'type' => 'select',
                     'value' => $this->fields['users_id_tech'],
                     'values' => getOptionsForUsers('own_ticket', ['entities_id' => $this->fields['entities_id']]),
                     'actions' => getItemActionButtons(['info'], "User"),
                  ],
                  __("Associable to a ticket") => [
                     'name' => 'is_helpdesk_visible',
                     'type' => 'checkbox',
                     'value' => $this->fields['is_helpdesk_visible'],
                  ],
                  __("Group in charge of the software") => [
                     'name' => 'groups_id_tech',
                     'type' => 'select',
                     'value' => $this->fields['groups_id_tech'],
                     'values' => getOptionForItems("Group", ['entities_id' => $this->fields['entities_id']]), // NEED right => own_ticket
                     'actions' => getItemActionButtons(['info', 'add'], "Group"),
                  ],
                  __("User") => [
                     'name' => 'users_id',
                     'type' => 'select',
                     'value' => $this->fields['users_id'],
                     'values' => getOptionForItems("User", ['entities_id' => $this->fields['entities_id']]), // NEED right => all
                     'actions' => getItemActionButtons(['info'], "User"),
                  ],
                  __("Group") => [
                     'name' => 'groups_id',
                     'type' => 'select',
                     'value' => $this->fields['groups_id'],
                     'values' => getOptionForItems("Group", ['entities_id' => $this->fields['entities_id']]), // NEED right => all
                     'actions' => getItemActionButtons(['info', 'add'], "Group"),
                  ],
               ]
            ],
            __('Upgrade') => [
               'visible' => true,
               'inputs' => [
                  __("Upgrade") => [
                     'name' => 'is_update',
                     'type' => 'checkbox',
                     'value' => $this->fields['is_update'],
                     'id' => 'is_update_checkbox',
                     'hooks' => [
                        'click' => <<<JS
                        console.log('click !')
                        JS
                     ],
                  ],
                  __('from') => [
                     'name' => 'softwares_id',
                     'type' => 'select',
                     'value' => $this->fields['softwares_id'],
                     'values' => getOptionForItems("Software", ['entities_id' => $this->fields['entities_id']]),
                  ],
               ]
            ]
         ]
      ];

      $form['content']['form_inputs_config'] = ['inputs' =>  getHiddenInputsForItemForm($this, $this->fields)];
      
      ob_start();
      Plugin::doHook("post_item_form", ['item' => $this, 'options' => [
         'colspan'      => 2,
         'withtemplate' => '',
         'candel'       => true,
         'canedit'      => true,
         'addbuttons'   => [],
         'formfooter'   => null,
         ]]);
      $additionnalHtml = ob_get_clean();
         
      renderTwigForm($form, $additionnalHtml);
      return true;
   }


   function getEmpty() {
      global $CFG_GLPI;
      parent::getEmpty();

      $this->fields["is_helpdesk_visible"] = $CFG_GLPI["default_software_helpdesk_visible"];
   }


   /**
    * @see CommonDBTM::getSpecificMassiveActions()
   **/
   function getSpecificMassiveActions($checkitem = null) {

      $isadmin = static::canUpdate();
      $actions = parent::getSpecificMassiveActions($checkitem);
      if ($isadmin
          && (countElementsInTable("glpi_rules", ['sub_type'=>'RuleSoftwareCategory']) > 0)) {
         $actions[__CLASS__.MassiveAction::CLASS_ACTION_SEPARATOR.'compute_software_category']
            = "<i class='ma-icon fas fa-calculator'></i>".
              __('Recalculate the category');
      }

      if (Session::haveRightsOr("rule_dictionnary_software", [CREATE, UPDATE])
           && (countElementsInTable("glpi_rules", ['sub_type'=>'RuleDictionnarySoftware']) > 0)) {
         $actions[__CLASS__.MassiveAction::CLASS_ACTION_SEPARATOR.'replay_dictionnary']
            = "<i class='ma-icon fas fa-undo'></i>".
              __('Replay the dictionary rules');
      }

      if ($isadmin) {
         KnowbaseItem_Item::getMassiveActionsForItemtype($actions, __CLASS__, 0, $checkitem);
      }

      return $actions;
   }


   /**
    * @since 0.85
    *
    * @see CommonDBTM::processMassiveActionsForOneItemtype()
   **/
   static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item,
                                                       array $ids) {

      switch ($ma->getAction()) {
         case 'merge' :
            $input = $ma->getInput();
            if (isset($input['item_items_id'])) {
               $items = [];
               foreach ($ids as $id) {
                  $items[$id] = 1;
               }
               if ($item->can($input['item_items_id'], UPDATE)) {
                  if ($item->merge($items)) {
                     $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_OK);
                  } else {
                     $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_KO);
                     $ma->addMessage($item->getErrorMessage(ERROR_ON_ACTION));
                  }
               } else {
                  $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_NORIGHT);
                  $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
               }
            } else {
               $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_KO);
            }
            return;

         case 'compute_software_category' :
            $softcatrule = new RuleSoftwareCategoryCollection();
            foreach ($ids as $id) {
               $params = [];
               //Get software name and manufacturer
               if ($item->can($id, UPDATE)) {
                  $params["name"]             = $item->fields["name"];
                  $params["manufacturers_id"] = $item->fields["manufacturers_id"];
                  $params["comment"]          = $item->fields["comment"];
                  $output = [];
                  $output = $softcatrule->processAllRules(null, $output, $params);
                  //Process rules
                  if (isset($output['softwarecategories_id'])
                      && $item->update(['id' => $id,
                                             'softwarecategories_id'
                                                  => $output['softwarecategories_id']])) {
                     $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                  } else {
                     $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                     $ma->addMessage($item->getErrorMessage(ERROR_ON_ACTION));
                  }
               } else {
                  $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_NORIGHT);
                  $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
               }
            }
            return;

         case 'replay_dictionnary' :
            $softdictionnayrule = new RuleDictionnarySoftwareCollection();
            $allowed_ids        = [];
            foreach ($ids as $id) {
               if ($item->can($id, UPDATE)) {
                  $allowed_ids[] = $id;
               } else {
                  $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_NORIGHT);
                  $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
               }
            }
            if ($softdictionnayrule->replayRulesOnExistingDB(0, 0, $allowed_ids)>0) {
               $ma->itemDone($item->getType(), $allowed_ids, MassiveAction::ACTION_OK);
            } else {
               $ma->itemDone($item->getType(), $allowed_ids, MassiveAction::ACTION_KO);
            }

            return;
      }
      parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
   }


   function rawSearchOptions() {
      // Only use for History (not by search Engine)
      $tab = parent::rawSearchOptions();

      $tab[] = [
         'id'                 => '2',
         'table'              => $this->getTable(),
         'field'              => 'id',
         'name'               => __('ID'),
         'massiveaction'      => false,
         'datatype'           => 'number'
      ];

      $tab = array_merge($tab, Location::rawSearchOptionsToAdd());

      $tab[] = [
         'id'                 => '16',
         'table'              => $this->getTable(),
         'field'              => 'comment',
         'name'               => __('Comments'),
         'datatype'           => 'text'
      ];

      $tab[] = [
         'id'                 => '62',
         'table'              => 'glpi_softwarecategories',
         'field'              => 'completename',
         'name'               => __('Category'),
         'datatype'           => 'dropdown'
      ];

      $tab[] = [
         'id'                 => '19',
         'table'              => $this->getTable(),
         'field'              => 'date_mod',
         'name'               => __('Last update'),
         'datatype'           => 'datetime',
         'massiveaction'      => false
      ];

      $tab[] = [
         'id'                 => '121',
         'table'              => $this->getTable(),
         'field'              => 'date_creation',
         'name'               => __('Creation date'),
         'datatype'           => 'datetime',
         'massiveaction'      => false
      ];

      $tab[] = [
         'id'                 => '23',
         'table'              => 'glpi_manufacturers',
         'field'              => 'name',
         'name'               => __('Publisher'),
         'datatype'           => 'dropdown'
      ];

      $tab[] = [
         'id'                 => '24',
         'table'              => 'glpi_users',
         'field'              => 'name',
         'linkfield'          => 'users_id_tech',
         'name'               => __('Technician in charge of the software'),
         'datatype'           => 'dropdown',
         'right'              => 'own_ticket'
      ];

      $tab[] = [
         'id'                 => '49',
         'table'              => 'glpi_groups',
         'field'              => 'completename',
         'linkfield'          => 'groups_id_tech',
         'name'               => __('Group in charge of the software'),
         'condition'          => ['is_assign' => 1],
         'datatype'           => 'dropdown'
      ];

      $tab[] = [
         'id'                 => '64',
         'table'              => $this->getTable(),
         'field'              => 'template_name',
         'name'               => __('Template name'),
         'datatype'           => 'text',
         'massiveaction'      => false,
         'nosearch'           => true,
         'nodisplay'          => true,
         'autocomplete'       => true,
      ];

      $tab[] = [
         'id'                 => '70',
         'table'              => 'glpi_users',
         'field'              => 'name',
         'name'               => User::getTypeName(1),
         'datatype'           => 'dropdown',
         'right'              => 'all'
      ];

      $tab[] = [
         'id'                 => '71',
         'table'              => 'glpi_groups',
         'field'              => 'completename',
         'name'               => Group::getTypeName(1),
         'condition'          => ['is_itemgroup' => 1],
         'datatype'           => 'dropdown'
      ];

      $tab[] = [
         'id'                 => '61',
         'table'              => $this->getTable(),
         'field'              => 'is_helpdesk_visible',
         'name'               => __('Associable to a ticket'),
         'datatype'           => 'bool'
      ];

      $tab[] = [
         'id'                 => '63',
         'table'              => $this->getTable(),
         'field'              => 'is_valid',
                              //TRANS: Indicator to know is all licenses of the software are valids
         'name'               => __('Valid licenses'),
         'datatype'           => 'bool'
      ];

      $tab[] = [
         'id'                 => '80',
         'table'              => 'glpi_entities',
         'field'              => 'completename',
         'name'               => Entity::getTypeName(1),
         'massiveaction'      => false,
         'datatype'           => 'dropdown'
      ];

      $newtab = [
         'id'                 => '72',
         'table'              => 'glpi_items_softwareversions',
         'field'              => 'id',
         'name'               => _x('quantity', 'Number of installations'),
         'forcegroupby'       => true,
         'usehaving'          => true,
         'datatype'           => 'count',
         'massiveaction'      => false,
         'joinparams'         => [
            'jointype'   => 'child',
            'beforejoin' => [
               'table'      => 'glpi_softwareversions',
               'joinparams' => ['jointype' => 'child'],
            ],
            'condition'  => "AND NEWTABLE.`is_deleted_item` = 0
                             AND NEWTABLE.`is_deleted` = 0
                             AND NEWTABLE.`is_template_item` = 0",
         ]
      ];

      if (Session::getLoginUserID()) {
         $newtab['joinparams']['condition'] .= getEntitiesRestrictRequest(' AND', 'NEWTABLE');
      }
      $tab[] = $newtab;

      $tab[] = [
         'id'                 => '73',
         'table'              => 'glpi_items_softwareversions',
         'field'              => 'date_install',
         'name'               => __('Installation date'),
         'datatype'           => 'date',
         'massiveaction'      => false,
         'joinparams'         => [
            'jointype'   => 'child',
            'beforejoin' => [
               'table'      => 'glpi_softwareversions',
               'joinparams' => ['jointype' => 'child'],
            ],
            'condition'  => "AND NEWTABLE.`is_deleted_item` = 0
                             AND NEWTABLE.`is_deleted` = 0
                             AND NEWTABLE.`is_template_item` = 0",
         ]
      ];

      $tab = array_merge($tab, SoftwareLicense::rawSearchOptionsToAdd());

      $name = _n('Version', 'Versions', Session::getPluralNumber());
      $tab[] = [
         'id'                 => 'versions',
         'name'               => $name
      ];

      $tab[] = [
         'id'                 => '5',
         'table'              => 'glpi_softwareversions',
         'field'              => 'name',
         'name'               => __('Name'),
         'forcegroupby'       => true,
         'massiveaction'      => false,
         'displaywith'        => ['softwares_id'],
         'joinparams'         => [
            'jointype'           => 'child'
         ],
         'datatype'           => 'dropdown'
      ];

      $tab[] = [
         'id'                 => '31',
         'table'              => 'glpi_states',
         'field'              => 'completename',
         'name'               => __('Status'),
         'datatype'           => 'dropdown',
         'forcegroupby'       => true,
         'massiveaction'      => false,
         'joinparams'         => [
            'beforejoin'         => [
               'table'              => 'glpi_softwareversions',
               'joinparams'         => [
                  'jointype'           => 'child'
               ]
            ]
         ],
      ];

      $tab[] = [
         'id'                 => '170',
         'table'              => 'glpi_softwareversions',
         'field'              => 'comment',
         'name'               => __('Comments'),
         'forcegroupby'       => true,
         'datatype'           => 'text',
         'massiveaction'      => false,
         'joinparams'         => [
            'jointype'           => 'child'
         ]
      ];

      $tab[] = [
         'id'                 => '4',
         'table'              => 'glpi_operatingsystems',
         'field'              => 'name',
         'datatype'           => 'dropdown',
         'name'               => OperatingSystem::getTypeName(1),
         'forcegroupby'       => true,
         'joinparams'         => [
            'beforejoin'         => [
               'table'              => 'glpi_softwareversions',
               'joinparams'         => [
                  'jointype'           => 'child'
               ]
            ]
         ],
      ];

      $tab = array_merge($tab, Notepad::rawSearchOptionsToAdd());
      $tab = array_merge($tab, Certificate::rawSearchOptionsToAdd());

      return $tab;
   }


   /**
    * Make a select box for  software to install
    *
    * @param $myname          select name
    * @param $entity_restrict restrict to a defined entity
    *
    * @return integer random part of elements id
   **/
   static function dropdownSoftwareToInstall($myname, $entity_restrict) {
      global $CFG_GLPI;

      // Make a select box
      $rand  = mt_rand();
      $where = getEntitiesRestrictCriteria('glpi_softwares', 'entities_id',
                                          $entity_restrict, true);
      $rand = Dropdown::show('Software', ['condition' => $where]);

      $paramsselsoft = ['softwares_id' => '__VALUE__',
                             'myname'       => $myname];

      Ajax::updateItemOnSelectEvent("dropdown_softwares_id$rand", "show_".$myname.$rand,
                                    $CFG_GLPI["root_doc"]."/ajax/dropdownInstallVersion.php",
                                    $paramsselsoft);

      echo "<span id='show_".$myname.$rand."'>&nbsp;</span>\n";

      return $rand;
   }


   /**
    * Make a select box for license software to associate
    *
    * @param $myname          select name
    * @param $entity_restrict restrict to a defined entity
    *
    * @return integer random part of elements id
   **/
   static function dropdownLicenseToInstall($myname, $entity_restrict) {
      global $CFG_GLPI, $DB;

      $iterator = $DB->request([
         'SELECT'          => [
            'glpi_softwares.id',
            'glpi_softwares.name'
         ],
         'DISTINCT'        => true,
         'FROM'            => 'glpi_softwares',
         'INNER JOIN'      => [
            'glpi_softwarelicenses' => [
               'ON' => [
                  'glpi_softwarelicenses' => 'softwares_id',
                  'glpi_softwares'        => 'id'
               ]
            ]
         ],
         'WHERE'           => [
            'glpi_softwares.is_deleted'    => 0,
            'glpi_softwares.is_template'  => 0
         ] + getEntitiesRestrictCriteria('glpi_softwarelicenses', 'entities_id', $entity_restrict, true),
         'ORDERBY'         => 'glpi_softwares.name'
      ]);

      $values = [];
      while ($data = $iterator->next()) {
         $softwares_id          = $data["id"];
         $values[$softwares_id] = $data["name"];
      }
      $rand = Dropdown::showFromArray('softwares_id', $values, ['display_emptychoice' => true]);

      $paramsselsoft = ['softwares_id'    => '__VALUE__',
                             'entity_restrict' => $entity_restrict,
                             'myname'          => $myname];

      Ajax::updateItemOnSelectEvent("dropdown_softwares_id$rand", "show_".$myname.$rand,
                                    $CFG_GLPI["root_doc"]."/ajax/dropdownSoftwareLicense.php",
                                    $paramsselsoft);

      echo "<span id='show_".$myname.$rand."'>&nbsp;</span>\n";

      return $rand;
   }


   /**
    * Create a new software
    *
    * @param name                          the software's name (need to be addslashes)
    * @param manufacturer_id               id of the software's manufacturer
    * @param entity                        the entity in which the software must be added
    * @param comment                       (default '')
    * @param is_recursive         boolean  must the software be recursive (false by default)
    * @param is_helpdesk_visible           show in helpdesk, default : from config (false by default)
    *
    * @return the software's ID
   **/
   function addSoftware($name, $manufacturer_id, $entity, $comment = '',
                        $is_recursive = false, $is_helpdesk_visible = null) {
      global $CFG_GLPI;

      $input["name"]                = $name;
      $input["manufacturers_id"]    = $manufacturer_id;
      $input["entities_id"]         = $entity;
      $input["is_recursive"]        = ($is_recursive ? 1 : 0);
      // No comment
      if (is_null($is_helpdesk_visible)) {
         $input["is_helpdesk_visible"] = $CFG_GLPI["default_software_helpdesk_visible"];
      } else {
         $input["is_helpdesk_visible"] = $is_helpdesk_visible;
      }

      //Process software's category rules
      $softcatrule = new RuleSoftwareCategoryCollection();
      $result      = $softcatrule->processAllRules(null, null, Toolbox::stripslashes_deep($input));

      if (!empty($result)) {
         if (isset($result['_ignore_import'])) {
            $input["softwarecategories_id"] = 0;
         } else if (isset($result["softwarecategories_id"])) {
            $input["softwarecategories_id"] = $result["softwarecategories_id"];
         } else if (isset($result["_import_category"])) {
            $softCat = new SoftwareCategory();
            $input["softwarecategories_id"]
               = $softCat->importExternal($input["_system_category"]);
         }
      } else {
         $input["softwarecategories_id"] = 0;
      }

      return $this->add($input);
   }


   /**
    * Add a software. If already exist in trashbin restore it
    *
    * @param name                            the software's name
    * @param manufacturer                    the software's manufacturer
    * @param entity                          the entity in which the software must be added
    * @param comment                         comment (default '')
    * @param is_recursive           boolean  must the software be recursive (false by default)
    * @param is_helpdesk_visible             show in helpdesk, default = config value (false by default)
   */
   function addOrRestoreFromTrash($name, $manufacturer, $entity, $comment = '',
                                  $is_recursive = false, $is_helpdesk_visible = null) {
      global $DB;

      //Look for the software by his name in GLPI for a specific entity
      $manufacturer_id = 0;
      if ($manufacturer != '') {
         $manufacturer_id = Dropdown::import('Manufacturer', ['name' => $manufacturer]);
      }

      $iterator = $DB->request([
         'SELECT' => [
            'glpi_softwares.id',
            'glpi_softwares.is_deleted'
         ],
         'FROM'   => 'glpi_softwares',
         'WHERE'  => [
            'name'               => $name,
            'manufacturers_id'   => $manufacturer_id,
            'is_template'        => 0
         ] + getEntitiesRestrictCriteria('glpi_softwares', 'entities_id', $entity, true)
      ]);

      if (count($iterator)) {
         //Software already exists for this entity, get his ID
         $data = $iterator->next();
         $ID   = $data["id"];

         // restore software
         if ($data['is_deleted']) {
            $this->removeFromTrash($ID);
         }

      } else {
         $ID = 0;
      }

      if (!$ID) {
         $ID = $this->addSoftware($name, $manufacturer_id, $entity, $comment, $is_recursive,
                                  $is_helpdesk_visible);
      }
      return $ID;
   }


   /**
    * Put software in trashbin because it's been removed by GLPI software dictionnary
    *
    * @param $ID        the ID of the software to put in trashbin
    * @param $comment   the comment to add to the already existing software's comment (default '')
    *
    * @return boolean (success)
   **/
   function putInTrash($ID, $comment = '') {
      global $CFG_GLPI;

      $this->getFromDB($ID);
      $input["id"]         = $ID;
      $input["is_deleted"] = 1;

      //change category of the software on deletion (if defined in glpi_configs)
      if (isset($CFG_GLPI["softwarecategories_id_ondelete"])
          && ($CFG_GLPI["softwarecategories_id_ondelete"] != 0)) {

         $input["softwarecategories_id"] = $CFG_GLPI["softwarecategories_id_ondelete"];
      }

      //Add dictionnary comment to the current comment
      $input["comment"] = (($this->fields["comment"] != '') ? "\n" : '') . $comment;

      return $this->update($input);
   }


   /**
    * Restore a software from trashbin
    *
    * @param $ID  the ID of the software to put in trashbin
    *
    * @return boolean (success)
   **/
   function removeFromTrash($ID) {

      $res         = $this->restore(["id" => $ID]);
      $softcatrule = new RuleSoftwareCategoryCollection();
      $result      = $softcatrule->processAllRules(null, null, $this->fields);

      if (!empty($result)
          && isset($result['softwarecategories_id'])
          && ($result['softwarecategories_id'] != $this->fields['softwarecategories_id'])) {

         $this->update(['id'                    => $ID,
                             'softwarecategories_id' => $result['softwarecategories_id']]);
      }

      return $res;
   }


   /**
    * Show softwares candidates to be merged with the current
    *
    * @return void
   **/
   function showMergeCandidates() {
      global $DB;

      $ID   = $this->getField('id');
      $this->check($ID, UPDATE);
      $rand = mt_rand();

      echo "<div class='center'>";
      $iterator = $DB->request([
         'SELECT'    => [
            'glpi_softwares.id',
            'glpi_softwares.name',
            'glpi_entities.completename AS entity'
         ],
         'FROM'      => 'glpi_softwares',
         'LEFT JOIN' => [
            'glpi_entities'   => [
               'ON' => [
                  'glpi_softwares'  => 'entities_id',
                  'glpi_entities'   => 'id'
               ]
            ]
         ],
         'WHERE'     => [
            'glpi_softwares.id'           => ['!=', $ID],
            'glpi_softwares.name'         => addslashes($this->fields['name']),
            'glpi_softwares.is_deleted'   => 0,
            'glpi_softwares.is_template'  => 0
         ] + getEntitiesRestrictCriteria(
            'glpi_softwares',
            'entities_id',
            getSonsOf("glpi_entities", $this->fields["entities_id"]),
            false
         ),
         'ORDERBY'   => 'entity'
      ]);
      $nb = count($iterator);

      if ($nb) {
         $link = Toolbox::getItemTypeFormURL('Software');
         Html::openMassiveActionsForm('mass'.__CLASS__.$rand);
         $massiveactionparams
            = ['num_displayed' => min($_SESSION['glpilist_limit'], $nb),
                    'container'     => 'mass'.__CLASS__.$rand,
                    'specific_actions'
                                    => [__CLASS__.MassiveAction::CLASS_ACTION_SEPARATOR.'merge'
                                                => __('Merge')],
                                    'item'          => $this];
         Html::showMassiveActions($massiveactionparams);

         echo "<table class='tab_cadre_fixehov'>";
         echo "<tr><th width='10'>";
         echo Html::getCheckAllAsCheckbox('mass'.__CLASS__.$rand);
         echo "</th>";
         echo "<th>".__('Name')."</th>";
         echo "<th>".Entity::getTypeName(1)."</th>";
         echo "<th>"._n('Installation', 'Installations', Session::getPluralNumber())."</th>";
         echo "<th>".SoftwareLicense::getTypeName(Session::getPluralNumber())."</th></tr>";

         while ($data = $iterator->next()) {
            echo "<tr class='tab_bg_2'>";
            echo "<td>".Html::getMassiveActionCheckBox(__CLASS__, $data["id"])."</td>";
            echo "<td><a href='".$link."?id=".$data["id"]."'>".$data["name"]."</a></td>";
            echo "<td>".$data["entity"]."</td>";
            echo "<td class='right'>".Item_SoftwareVersion::countForSoftware($data["id"])."</td>";
            echo "<td class='right'>".SoftwareLicense::countForSoftware($data["id"])."</td></tr>\n";
         }
         echo "</table>\n";
         $massiveactionparams['ontop'] =false;
         Html::showMassiveActions($massiveactionparams);
         Html::closeForm();

      } else {
         echo __('No item found');
      }

      echo "</div>";
   }


   /**
    * Merge softwares with current
    *
    * @param $item array of software ID to be merged
    * @param boolean display html progress bar
    *
    * @return boolean about success
   **/
   function merge($item, $html = true) {
      global $DB;

      $ID = $this->getField('id');

      if ($html) {
         echo "<div class='center'>";
         echo "<table class='tab_cadrehov'><tr><th>".__('Merging')."</th></tr>";
         echo "<tr class='tab_bg_2'><td>";
         Html::createProgressBar(__('Work in progress...'));
         echo "</td></tr></table></div>\n";
      }

      $item = array_keys($item);

      // Search for software version
      $req = $DB->request("glpi_softwareversions", ["softwares_id" => $item]);
      $i   = 0;

      if ($nb = $req->numrows()) {
         foreach ($req as $from) {
            $found = false;

            foreach ($DB->request("glpi_softwareversions",
                                  ["softwares_id" => $ID,
                                        "name"         => $from["name"]]) as $dest) {
               // Update version ID on License
               $DB->update(
                  'glpi_softwarelicenses', [
                     'softwareversions_id_buy' => $dest['id']
                  ], [
                     'softwareversions_id_buy' => $from['id']
                  ]
               );

               $DB->update(
                  'glpi_softwarelicenses', [
                     'softwareversions_id_use' => $dest['id']
                  ], [
                     'softwareversions_id_use' => $from['id']
                  ]
               );

               // Move installation to existing version in destination software
               $found = $DB->update(
                  'glpi_items_softwareversions', [
                     'softwareversions_id' => $dest['id']
                  ], [
                     'softwareversions_id' => $from['id']
                  ]
               );
            }

            if ($found) {
               // Installation has be moved, delete the source version
               $result = $DB->delete(
                  'glpi_softwareversions', [
                     'id'  => $from['id']
                  ]
               );
            } else {
               // Move version to destination software
               $result = $DB->update(
                  'glpi_softwareversions', [
                     'softwares_id' => $ID,
                     'entities_id'  => $this->getField('entities_id')
                  ], [
                     'id' => $from['id']
                  ]
               );
            }

            if ($result) {
               $i++;
            }
            if ($html) {
               Html::changeProgressBarPosition($i, $nb+1);
            }
         }
      }

      // Move software license
      $result = $DB->update(
         'glpi_softwarelicenses', [
            'softwares_id' => $ID
         ], [
            'softwares_id' => $item
         ]
      );

      if ($result) {
         $i++;
      }

      if ($i == ($nb+1)) {
         //error_log ("All merge operations ok.");
         $soft = new self();
         foreach ($item as $old) {
            $soft->putInTrash($old, __('Software deleted after merging'));
         }
      }
      if ($html) {
         Html::changeProgressBarPosition($i, $nb+1, __('Task completed.'));
      }
      return $i == ($nb+1);
   }


   static function getDefaultSearchRequest() {
      return [
         'sort' => 0
      ];
   }

   static function getIcon() {
      return "fas fa-cube";
   }

}
