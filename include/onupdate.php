<?php
/*
 You may not change or alter any portion of this comment or credits of
 supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit
 authors.

 This program is distributed in the hope that it will be useful, but
 WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

/**
 * Module: xForms
 *
 * @category        Module
 * @package         xforms
 * @author          Richard Griffith <richard@geekwright.com>
 * @author          trabis <lusopoemas@gmail.com>
 * @author          XOOPS Module Development Team
 * @copyright       Copyright (c) 2001-2017 {@link https://xoops.org XOOPS Project}
 * @license         https://www.gnu.org/licenses/gpl-2.0.html GNU Public License
 * @since           2.00
 */

use Xmf\Module\Admin;
use XoopsModules\Xforms;

/**
 * Prepares system prior to attempting to install module
 * @param \XoopsModule $module {@link XoopsModule}
 * @return bool true if ready to install, false if not
 */
function xoops_module_pre_update_xforms(\XoopsModule $module)
{
    $moduleDirName = basename(dirname(__DIR__));
    /** @var Xforms\Helper $helper */
    /** @var Xforms\Utility $utility */
    $helper  = Xforms\Helper::getInstance();
    $utility = new Xforms\Utility();

    $xoopsSuccess = $utility::checkVerXoops($module);
    $phpSuccess   = $utility::checkVerPhp($module);

    //    $migrator = new \XoopsModules\Xforms\Common\Migrate();
    //    $migrator->synchronizeSchema();

    return $xoopsSuccess && $phpSuccess;
}

/**
 * Upgrade works to update Xforms from previous versions
 *
 * @param \XoopsModule $xoopsModule
 * @param string       $prev_version version * 100
 *
 * @return bool
 * @uses XformsUtility
 *
 * @uses Xmf\Module\Admin
 */
function xoops_module_update_xforms(\XoopsModule $xoopsModule, $prev_version)
{
    $moduleDirName      = basename(dirname(__DIR__));
    $moduleDirNameUpper = mb_strtoupper($moduleDirName);

    /** @var Xforms\Helper $helper */ 
    /** @var Xforms\Utility $utility */
    /** @var Xforms\Common\Configurator $configurator */
    $helper       = Xforms\Helper::getInstance();
    $utility      = new Xforms\Utility();
    $configurator = new Xforms\Common\Configurator();

    $helper->loadLanguage('common');

    /*
     =============================================================
     Upgrade for Xforms < 2.0
     =============================================================
     =====================================
     - rename xforms_forms to xforms_form
     - init following columns in xforms_form:
     =====================================
     form_save_db       tinyint(1)
     form_send_to_other varchar(255)
     form_send_copy     tinyint(1)
     form_email_header  text
     form_email_footer  text
     form_email_uheader text
     form_email_ufooter text
     form_display_style varchar(1)
     form_begin         int(10)
     form_end           int(10)
     form_active        tinyint(1)
     =====================================
     - rename xforms_formelements to xforms_element
     - add index disp_ele_by_form
     - change all ele_type 'select2' column data to 'country'
     =====================================
     - create the xforms_userdata table
     =====================================
     - remove old .css, .js, and .image
       and (sub)directories if they exist
     - remove old element files (./admin/ele_*.php)
     =====================================
     =============================================================
    */

    $success = true;

    $helper->loadLanguage('modinfo');
    $modulePrefix = $helper->getModule()->getVar('dirname');

    // only execute this if user is an Admin
    if (!$helper->isUserAdmin()) {
        $xoopsModule->setErrors(_NOPERM);

        return false;
    }

    require_once $helper->path('include/functions.php');

    if ($prev_version < 200) {
        //    if (true) {

        $migrate = new \Xmf\Database\Tables();

        /*********************************
         * Forms table modifications
         *********************************/
        $oldTableName    = "{$modulePrefix}_forms";
        $mainTableName   = "{$modulePrefix}_form";
        $oldTableExists  = $migrate->useTable($oldTableName);
        $mainTableExists = $migrate->useTable($mainTableName);

        if (!$oldTableExists) {
            $xoopsModule->setErrors(sprintf(_MI_XFORMS_INST_NO_TABLE, $oldTableName));

            return false;
        } elseif ($mainTableExists) {
            $xoopsModule->setErrors(sprintf(_MI_XFORMS_INST_TABLE_EXISTS, $mainTableName));

            return false;
        }
        // rename table to new table name
        $success = $migrate->renameTable($oldTableName, $mainTableName);
        $success &= $migrate->executeQueue();
        if (false === $success) {
            $xoopsModule->setErrors($migrate->getLastError());

            return false;
        }

        // modify Form table - add columns
        $columnArray = [
            ['form_save_db', "tinyint(1) NOT NULL default '1'"],
            ['form_send_to_other', "varchar(255) NOT NULL default ''"],
            ['form_send_copy', "tinyint(1) NOT NULL default '1'"],
            ['form_email_header', 'text NOT NULL'],
            ['form_email_footer', 'text NOT NULL'],
            ['form_email_uheader', 'text NOT NULL'],
            ['form_email_ufooter', 'text NOT NULL'],
            ['form_display_style', "varchar(1) NOT NULL default 'f'"],
            ['form_begin', "int(10) unsigned NOT NULL default '0'"],
            ['form_end', "int(10) unsigned NOT NULL default '0'"],
            ['form_active', "tinyint(1) NOT NULL default '1'"],
        ];

        $migrate->resetQueue();
        $migrate->useTable($mainTableName);
        foreach ($columnArray as $column) {
            if (false === $migrate->addColumn($mainTableName, $column[0], $column[1])) {
                $xoopsModule->setErrors($migrate->getLastError());

                return false;
            }
        }

        if (false === $migrate->executeQueue()) {
            $xoopsModule->setErrors($migrate->getLastError());

            return false;
        }

        /*********************************
         * Elements table modifications
         *********************************/
        // rename the old element table
        $migrate->resetQueue();
        $oldTableName    = "{$modulePrefix}_formelements";
        $oldTableExists  = $migrate->useTable($oldTableName);
        $mainTableName   = "{$modulePrefix}_element";
        $mainTableExists = $migrate->useTable($mainTableName);

        if (!$oldTableExists) {
            $xoopsModule->setErrors(sprintf(_MI_XFORMS_INST_NO_TABLE, $oldTableName));

            return false;
        } elseif ($mainTableExists) {
            $xoopsModule->setErrors(sprintf(_MI_XFORMS_INST_TABLE_EXISTS, $mainTableName));

            return false;
        }
        // rename table to new table name
        $success = $migrate->renameTable($oldTableName, $mainTableName);
        $success &= $migrate->executeQueue();
        if (false === $success) {
            $xoopsModule->setErrors($migrate->getLastError());

            return false;
        }

        // add index to improve performance
        $migrate->resetQueue();
        $migrate->useTable($mainTableName);
        $success &= $migrate->addIndex('disp_ele_by_form', $mainTableName, 'form_id, ele_display');
        $success &= $migrate->executeQueue();
        if (false === $success) {
            $xoopsModule->setErrors($module->getLastError());
        }

        // change all 'select2' elements to 'country'
        $migrate->resetQueue();
        $success = $migrate->useTable($mainTableName);
        $success &= $migrate->update($mainTableName, ['ele_type' => 'country'], new \Criteria('ele_type', 'select2'));
        // change ele_id from smallint(5) to mediumint(8)
        $success &= $migrate->alterColumn($mainTableName, 'ele_id', 'mediumint(8) NOT NULL auto_increment');
        //change ele_caption from varchar(255) to text
        $success &= $migrate->alterColumn($mainTableName, 'ele_caption', 'text NOT NULL');
        $success &= $migrate->executeQueue();
        if (false === $success) {
            $xoopsModule->setErrors($module->getLastError());
        }

        /*********************************
         * Create the UserData table
         *********************************/
        $migrate->resetQueue();
        $success = $mainTableName = "{$modulePrefix}_userdata";
        $success &= $migrate->addTable($mainTableName);

        // add UserData table columns
        $columnArray = [
            ['udata_id', 'int(11) unsigned NOT NULL auto_increment'],
            ['uid', "mediumint(8) unsigned NOT NULL default '0'"],
            ['form_id', 'smallint(5) NOT NULL'],
            ['ele_id', 'mediumint(8) NOT NULL'],
            ['udata_time', "int(10) unsigned NOT NULL default '0'"],
            ['udata_ip', "varchar(100) NOT NULL default '0.0.0.0'"],
            ['udata_agent', "varchar(500) NOT NULL default ''"],
            ['udata_value', 'text NOT NULL'],
        ];
        foreach ($columnArray as $column) {
            if (false === $migrate->addColumn($mainTableName, $column[0], $column[1])) {
                $xoopsModule->setErrors($migrate->getLastError());

                return false;
            }
        }

        // add primary key to table
        $success = $migrate->addPrimaryKey($mainTableName, 'udata_id');
        $success &= $migrate->executeQueue();
        if (false === $success) {
            $xoopsModule->setErrors($migrate->getLastError());

            return false;
        }

        unset($migrate);

        /*********************************
         * Remove previous .css, .js and .images
         * directories since they're being
         * moved to ./assets
         *********************************/
        //        require_once $GLOBALS['xoops']->path('modules/' . $xoopsModule->dirname() . '/include/functions.php');
        $old_directories = [
            $GLOBALS['xoops']->path('modules/' . $xoopsModule->getVar('dirname', 'n') . '/css/'),
            $GLOBALS['xoops']->path('modules/' . $xoopsModule->getVar('dirname', 'n') . '/js/'),
            $GLOBALS['xoops']->path('modules/' . $xoopsModule->getVar('dirname', 'n') . '/images/'),
        ];
        foreach ($old_directories as $old_dir) {
            $dirInfo = new \SplFileInfo($old_dir);
            if ($dirInfo->isDir()) {
                // directory exists so try and delete it
                $success &= xformsDeleteDirectory($old_dir);
            }
        }
        if (false === $success) {
            $xoopsModule->setErrors(_MI_XFORMS_INST_NO_DEL_DIRS);

            return false;
        }
    }
    /*********************************
     * Remove ./template/*.html (except index.html) files
     * since they're being replaced by *.tpl files
     *********************************/
    // remove old files
    $directory = $helper->path('templates/');
    //    $directory = $GLOBALS['xoops']->path('modules/' . $xoopsModule->getVar('dirname', 'n') . '/templates/');
    $dirInfo = new \SplFileInfo($directory);
    // validate is a directory
    if ($dirInfo->isDir()) {
        $fileList = array_diff(scandir($directory, SCANDIR_SORT_NONE), ['..', '.', 'index.html']);
        foreach ($fileList as $k => $v) {
            if (!preg_match('/.tpl+$/i', $v)) {
                $fileInfo = new \SplFileInfo($directory . $v);
                if ($fileInfo->isDir()) {
                    // recursively handle subdirectories
                    if (!($success = xformsDeleteDirectory($directory . $v))) {
                        break;
                    }
                } elseif ($fileInfo->isFile()) {
                    // delete the file
                    if (!($success = unlink($fileInfo->getRealPath()))) {
                        break;
                    }
                }
            }
        }
    } else {
        // couldn't find template directory - that's bad
        $xoopsModule->setErrors(sprintf(_MI_XFORMS_INST_DIR_NOT_FOUND, htmlspecialchars($directory, ENT_QUOTES | ENT_HTML5)));
        $success = false;
    }

    if ($success) { // ok, continue
        /*********************************
         * Remove ./admin/ele_*.php files
         * since they're being replaced by ./admin/elements/ele_*.php files
         *********************************/
        $directory = $GLOBALS['xoops']->path('modules/' . $xoopsModule->getVar('dirname', 'n') . '/admin/');
        $dirInfo   = new \SplFileInfo($directory);
        // validate directory exists
        if ($dirInfo->isDir()) {
            $fileList = array_diff(scandir($directory, SCANDIR_SORT_NONE), ['..', '.', 'index.html']);
            foreach ($fileList as $k => $v) {
                if (preg_match('/^(ele_).*(\.php)$/i', $v)) {
                    $fileInfo = new \SplFileInfo($directory . $v);
                    if ($fileInfo->isFile()) {
                        // delete the file
                        if (!($success = unlink($fileInfo->getRealPath()))) {
                            break;
                        }
                    }
                }
            }
        } else {
            // couldn't find ./admin directory - that's bad
            $xoopsModule->setErrors(sprintf(_MI_XFORMS_INST_DIR_NOT_FOUND, htmlspecialchars($directory, ENT_QUOTES | ENT_HTML5)));
            $success = false;
        }
    }

    return $success;
}
