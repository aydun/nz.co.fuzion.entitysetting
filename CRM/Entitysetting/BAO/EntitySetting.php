<?php

class CRM_Entitysetting_BAO_EntitySetting extends CRM_Entitysetting_DAO_EntitySetting {

  /**
   * Create a new EntitySetting based on array-data
   *
   * @param array $params key-value pairs
   * @return CRM_Entitysetting_DAO_EntitySetting|NULL
   */
  public static function create($params) {
    $className = 'CRM_Entitysetting_BAO_EntitySetting';
    $entityName = 'EntitySetting';

    $instance = new $className();
    $instance->entity_id = $params['entity_id'];
    $instance->entity_type = $params['entity_type'];
    $instance->find(TRUE);
    $params['setting_data'] = array($params['key'] => $params['settings']);

    if($instance->setting_data) {
      $params['setting_data'] = array_merge(json_decode($instance->setting_data, TRUE), $params['setting_data']);
    }
    if(is_array($params['setting_data'])) {
      $params['setting_data'] = json_encode($params['setting_data']);
    }
    $hook = empty($params['id']) ? 'create' : 'edit';

    CRM_Utils_Hook::pre($hook, $entityName, CRM_Utils_Array::value('id', $params), $params);
    $instance->copyValues($params);
    $instance->save();
    CRM_Utils_Hook::post($hook, $entityName, $instance->id, $instance);

    return $instance;
  }

  /**
   * Load up settings metadata from files
   */
  static function loadMetadata($metaDataFolder) {
    $settingMetaData = $entitySettings = array();
    $settingsFiles = CRM_Utils_File::findFiles($metaDataFolder, '*.entity_setting.php');
    foreach ($settingsFiles as $file) {
      $settings = include $file;
      foreach ($settings as $setting) {
        $entitySettings[$setting['entity']][] = $setting;
      }
    }
    foreach ($entitySettings as $entity => $entitySetting) {
      CRM_Core_BAO_Cache::setItem($entitySetting,'CiviCRM setting Spec', $entity);
    }
    return $entitySettings;
  }

  /**
   * This provides information about the enitity settings, allowing setting form generation
   *
   * Function is intended for configuration rather than runtime access to settings
   *
   * @params string $entity e.g contribution_page
   * @params string $key namespace marker - usually the module name
   *
   * @return array $result - the following information as appropriate for each setting
   * - name
   * - type
   * - default
   * - add (CiviCRM version added)
   * - is_domain
   * - is_contact
   * - description
   * - help_text
   */
  static function getSettingSpecification($entity, $key = '', $force = 0) {
    $metadata = CRM_Core_BAO_Cache::getItem('CiviCRM Entity setting Specs', $entity);
    if ($metadata === NULL || $force) {
      $metaDataFolders = $metadata = array();
      self::hookAlterEntitySettingsFolders($metaDataFolders);
      foreach ($metaDataFolders as $metaDataFolder) {
        $metadata = $metadata + self::loadMetaData($metaDataFolder, $entity);
      }
      CRM_Core_BAO_Cache::setItem($metadata, 'CiviCRM Entity setting Specs', $entity);
    }
    return $metadata;
  }

/**
 * get settings for entity
 */
  static function getSettings($params) {
    $settings = self::getSettingSpecification($params['entity']);
    return $settings[$params['entity']];
  }

  /**
   *
   */
  static function hookAlterEntitySettingsFolders(&$metaDataFolders) {
    return CRM_Utils_Hook::singleton()->invoke(1, $metaDataFolders,
        self::$_nullObject, self::$_nullObject, self::$_nullObject, self::$_nullObject,
        'civicrm_alterEntitySettingsFolders'
    );
  }
  /**
   * shortcut to preferably being able to use core pseudoconstant fn
   * @todo - this is copy & paste from the pseudoconstant fn - would prefer to extract & re-use
   */
  static function getoptions($fieldSpec, $params = array(), $context = NULL) {
    $flip = !empty($params['flip']);
    // Merge params with defaults
    $params += array(
      'grouping' => FALSE,
      'localize' => FALSE,
      'onlyActive' => ($context == 'validate' || $context == 'get') ? FALSE : TRUE,
      'fresh' => FALSE,
    );
    if (isset($fieldSpec['enumValues'])) {
      // use of a space after the comma is inconsistent in xml
      $enumStr = str_replace(', ', ',', $fieldSpec['enumValues']);
      $output = explode(',', $enumStr);
      return array_combine($output, $output);
    }

    elseif (!empty($fieldSpec['pseudoconstant'])) {
      $pseudoconstant = $fieldSpec['pseudoconstant'];
      // Merge params with schema defaults
      $params += array(
        'condition' => CRM_Utils_Array::value('condition', $pseudoconstant, array()),
        'keyColumn' => CRM_Utils_Array::value('keyColumn', $pseudoconstant),
        'labelColumn' => CRM_Utils_Array::value('labelColumn', $pseudoconstant),
      );

      // Fetch option group from option_value table
      if(!empty($pseudoconstant['optionGroupName'])) {
        if ($context == 'validate') {
          $params['labelColumn'] = 'name';
        }
        // Call our generic fn for retrieving from the option_value table
        return CRM_Core_OptionGroup::values(
          $pseudoconstant['optionGroupName'],
          $flip,
          $params['grouping'],
          $params['localize'],
          $params['condition'] ? ' AND ' . implode(' AND ', (array) $params['condition']) : NULL,
          $params['labelColumn'] ? $params['labelColumn'] : 'label',
          $params['onlyActive'],
          $params['fresh'],
          $params['keyColumn'] ? $params['keyColumn'] : 'value'
        );
      }
    }
  }

  static function getKey($settingSpec) {
    return str_replace('.', '-', $settingSpec['key'] . '__' . $settingSpec['name']);
  }
}
