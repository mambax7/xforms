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
 * @author          XOOPS Module Development Team
 * @copyright       Copyright (c) 2001-2017 {@link https://xoops.org XOOPS Project}
 * @license         https://www.gnu.org/licenses/gpl-2.0.html GNU Public License
 */

use Xmf\Request;
use XoopsModules\Xforms;
use XoopsModules\Xforms\Constants;

//defined('XOOPS_ROOT_PATH') || exit('Restricted access');

require_once dirname(dirname(dirname(__DIR__))) . '/mainfile.php';

$moduleDirName      = basename(dirname(__DIR__));
$moduleDirNameUpper = mb_strtoupper($moduleDirName);

//require_once $GLOBALS['xoops']->path('./modules/xforms/class/constants.php');
//xoops_load('filechecker', 'xforms');

$op = Request::getString('op', '', 'POST');
if ('copyfile' === $op) {
    $originalFilePath = Request::getString('original_file_path', null, 'POST');
    $filePath         = Request::getString('file_path', null, 'POST');
    $redirect         = Request::getString('redirect', null, 'POST');

    $msg = Xforms\Common\FileChecker::copyFile($originalFilePath, $filePath) ? constant('CO_' . $moduleDirNameUpper . '_' . 'FC_FILECOPIED') : constant('CO_' . $moduleDirNameUpper . '_' . 'FC_FILENOTCOPIED');
    redirect_header($redirect, Constants::REDIRECT_DELAY_MEDIUM, "{$msg}: {$filePath}");
}
