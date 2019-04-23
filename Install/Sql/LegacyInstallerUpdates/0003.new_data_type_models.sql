ALTER TABLE `exf_data_type` ADD `app_oid` BINARY(16) NOT NULL AFTER `data_type_alias`;
update exf_data_type set app_oid = 0x31000000000000000000000000000000;

ALTER TABLE `exf_data_type` ADD `name` VARCHAR(64) NOT NULL AFTER `app_oid`;
update exf_data_type set name = data_type_alias;

ALTER TABLE `exf_data_type` ADD `prototype` VARCHAR(128) NOT NULL AFTER `name`;
update exf_data_type set prototype = CONCAT('exface/Core/DataTypes/', data_type_alias, 'DataType.php');

REPLACE INTO `exf_attribute` (`oid`, `attribute_alias`, `attribute_name`, `object_oid`, `data`, `data_properties`, `attribute_formatter`, `data_type_oid`, `default_display_order`, `default_sorter_order`, `default_sorter_dir`, `object_label_flag`, `object_uid_flag`, `attribute_hidden_flag`, `attribute_editable_flag`, `attribute_required_flag`, `attribute_system_flag`, `attribute_sortable_flag`, `attribute_filterable_flag`, `attribute_aggregatable_flag`, `default_value`, `fixed_value`, `related_object_oid`, `related_object_special_key_attribute_oid`, `attribute_short_description`, `default_editor_uxon`, `created_on`, `modified_on`, `created_by_user_oid`, `modified_by_user_oid`, `default_aggregate_function`, `value_list_delimiter`) VALUES
(0x11e796d6e3e42c25b6e3e4b318306b9a, 'PROTOTYPE', 'Prototype', 0x32360000000000000000000000000000, 'prototype', '{}', '', 0x30000000000000000000000000000000, NULL, NULL, '', 0, 0, 0, 1, 1, 0, 1, 1, 1, '', '', 0x11e796d66279d4a3b6e3e4b318306b9a, NULL, '', '', '2017-09-11 09:52:49', '2017-10-25 04:45:08', 0x31000000000000000000000000000000, 0x31000000000000000000000000000000, '', ','),
(0x31303400000000000000000000000000, 'ALIAS', 'Alias', 0x32360000000000000000000000000000, 'data_type_alias', '', '', 0x30000000000000000000000000000000, 3, NULL, '', 0, 0, 0, 1, 1, 0, 1, 1, 1, '', '', NULL, NULL, '', '', '2007-01-01 00:00:00', '2017-10-13 15:53:55', NULL, 0x31000000000000000000000000000000, '', ','),
(0x11e796d6e3e39179b6e3e4b318306b9a, 'APP', 'App', 0x32360000000000000000000000000000, 'app_oid', '{"SQL_DATA_TYPE":"binary"}', '', 0x35000000000000000000000000000000, 2, NULL, '', 0, 0, 0, 1, 1, 0, 1, 1, 1, '', '', 0x35370000000000000000000000000000, NULL, '', '', '2017-09-11 09:52:49', '2017-10-25 04:45:08', 0x31000000000000000000000000000000, 0x31000000000000000000000000000000, '', ','),
(0x11e796d6e3e4116eb6e3e4b318306b9a, 'NAME', 'Data Type', 0x32360000000000000000000000000000, 'name', '', '', 0x30000000000000000000000000000000, 1, NULL, '', 0, 0, 0, 1, 1, 0, 1, 1, 1, '', '', NULL, NULL, '', '', '2007-01-01 00:00:00', '2017-10-13 15:53:55', NULL, 0x31000000000000000000000000000000, '', ','),
(0x11e7b02177e1799498350205857feb80, 'CONFIG_UXON', 'Configuration', 0x32360000000000000000000000000000, 'config_uxon', '', '', 0x30000000000000000000000000000000, NULL, NULL, '', 0, 0, 0, 1, 0, 0, 1, 1, 1, '', '', NULL, NULL, '', '', '2007-01-01 00:00:00', '2017-10-13 15:53:55', NULL, 0x31000000000000000000000000000000, '', ','),
(0x11e7b02177e1f58798350205857feb80, 'VALIDATION_ERROR', 'Validation Error Code', 0x32360000000000000000000000000000, 'validation_error_oid', '{"SQL_DATA_TYPE":"binary"}', '', 0x35000000000000000000000000000000, NULL, NULL, '', 0, 0, 0, 1, 0, 0, 1, 1, 1, '', '', NULL, NULL, '', '', '2007-01-01 00:00:00', '2017-10-13 15:53:55', NULL, 0x31000000000000000000000000000000, '', ',');