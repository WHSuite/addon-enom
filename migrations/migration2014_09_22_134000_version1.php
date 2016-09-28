<?php
namespace Addon\Enom\Migrations;

use \App\Libraries\BaseMigration;

class Migration2014_09_22_134000_version1 extends BaseMigration
{
    public function up($addon_id)
    {
        $registrar = new \Registrar();
        $registrar->name = 'Enom';
        $registrar->slug = 'enom';
        $registrar->addon_id = $addon_id;
        $registrar->status = '1';
        $registrar->save();

        // Add settings category
        $setting_category = new \SettingCategory();
        $setting_category->slug = 'enom';
        $setting_category->title = 'enom_settings';
        $setting_category->is_visible = '1';
        $setting_category->sort = '99';
        $setting_category->addon_id = $addon_id;
        $setting_category->save();

        // Add settings
        $setting_uid = new \Setting();
        $setting_uid->slug = 'enom_uid';
        $setting_uid->title = 'enom_uid';
        $setting_uid->field_type = 'text';
        $setting_uid->setting_category_id = $setting_category->id;
        $setting_uid->editable = '1';
        $setting_uid->addon_id = $addon_id;
        $setting_uid->sort = '1';
        $setting_uid->save();

        $setting_password = new \Setting();
        $setting_password->slug = 'enom_password';
        $setting_password->title = 'password';
        $setting_password->field_type = 'text';
        $setting_password->setting_category_id = $setting_category->id;
        $setting_password->editable = '1';
        $setting_password->addon_id = $addon_id;
        $setting_password->sort = '2';
        $setting_password->save();

        $setting_enable_sandbox = new \Setting();
        $setting_enable_sandbox->slug = 'enom_enable_sandbox';
        $setting_enable_sandbox->title = 'enom_enable_sandbox';
        $setting_enable_sandbox->field_type = 'checkbox';
        $setting_enable_sandbox->rules = 'min:0|max:1';
        $setting_enable_sandbox->setting_category_id = $setting_category->id;
        $setting_enable_sandbox->editable = '1';
        $setting_enable_sandbox->addon_id = $addon_id;
        $setting_enable_sandbox->sort = '3';
        $setting_enable_sandbox->save();


    }

    public function down($addon_id)
    {
        // Remove all settings
        \Setting::where('addon_id', '=', $addon_id)->delete();

        // Remove all settings groups
        \SettingCategory::where('addon_id', '=', $addon_id)->delete();

        // Remove registrar
        \Registrar::where('addon_id', '=', $addon_id)->delete();
    }
}
