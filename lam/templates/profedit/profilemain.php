<?php
namespace LAM\TOOLS\PROFILE_EDITOR;
use \htmlTable;
use \htmlTitle;
use \htmlStatusMessage;
use \LAMCfgMain;
use \htmlSubTitle;
use \htmlSpacer;
use \htmlSelect;
use \htmlButton;
use \htmlImage;
use \htmlLink;
use \htmlOutputText;
use \htmlHelpLink;
use \htmlHiddenInput;
use \htmlInputField;
use \htmlDiv;
/*
$Id$

  This code is part of LDAP Account Manager (http://www.ldap-account-manager.org/)
  Copyright (C) 2003 - 2016  Roland Gruber

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/

/**
* This is the main window of the profile editor.
*
* @package profiles
* @author Roland Gruber
*/

/** security functions */
include_once("../../lib/security.inc");
/** helper functions for profiles */
include_once("../../lib/profiles.inc");
/** access to LDAP server */
include_once("../../lib/ldap.inc");
/** access to configuration options */
include_once("../../lib/config.inc");

// start session
startSecureSession();

// die if no write access
if (!checkIfWriteAccessIsAllowed()) die();

checkIfToolIsActive('toolProfileEditor');

setlanguage();

if (!empty($_POST)) {
	validateSecurityToken();
}

$typeManager = new \LAM\TYPES\TypeManager();
$types = $typeManager->getConfiguredTypes();
$profileClasses = array();
$profileClassesTemp = array();
foreach ($types as $type) {
	if ($type->isHidden() || !checkIfWriteAccessIsAllowed($type->getId())) {
		continue;
	}
	$profileClassesTemp[$type->getAlias()] = array(
		'typeId' => $type->getId(),
		'title' => $type->getAlias(),
		'profiles' => "");
}
$profileClassesKeys = array_keys($profileClassesTemp);
natcasesort($profileClassesKeys);
$profileClassesKeys = array_values($profileClassesKeys);
for ($i = 0; $i < sizeof($profileClassesKeys); $i++) {
	$profileClasses[] = $profileClassesTemp[$profileClassesKeys[$i]];
}

// check if user is logged in, if not go to login
if (!$_SESSION['ldap'] || !$_SESSION['ldap']->server()) {
	metaRefresh("../login.php");
	exit;
}

// check if new profile should be created
elseif (isset($_POST['createProfileButton'])) {
	metaRefresh("profilepage.php?type=" . htmlspecialchars($_POST['createProfile']));
	exit;
}
// check if a profile should be edited
for ($i = 0; $i < sizeof($profileClasses); $i++) {
	if (isset($_POST['editProfile_' . $profileClasses[$i]['typeId']]) || isset($_POST['editProfile_' . $profileClasses[$i]['typeId'] . '_x'])) {
		metaRefresh("profilepage.php?type=" . htmlspecialchars($profileClasses[$i]['typeId']) .
					"&amp;edit=" . htmlspecialchars($_POST['profile_' . $profileClasses[$i]['typeId']]));
		exit;
	}
}

include '../main_header.php';
echo "<div class=\"user-bright smallPaddingContent\">\n";
echo "<form name=\"profilemainForm\" action=\"profilemain.php\" method=\"post\">\n";
echo '<input type="hidden" name="' . getSecurityTokenName() . '" value="' . getSecurityTokenValue() . '">';

$container = new htmlTable();
$container->addElement(new htmlTitle(_("Profile editor")), true);

if (isset($_POST['deleteProfile']) && ($_POST['deleteProfile'] == 'true')) {
	$type = $typeManager->getConfiguredType($_POST['profileDeleteType']);
	if ($type->isHidden()) {
		logNewMessage(LOG_ERR, 'User tried to delete hidden account type profile: ' . $_POST['profileDeleteType']);
		die();
	}
	// delete profile
	if (delAccountProfile($_POST['profileDeleteName'], $_POST['profileDeleteType'])) {
		$message = new htmlStatusMessage('INFO', _('Deleted profile.'), $type->getAlias() . ': ' . htmlspecialchars($_POST['profileDeleteName']));
		$message->colspan = 10;
		$container->addElement($message, true);
	}
	else {
		$message = new htmlStatusMessage('ERROR', _('Unable to delete profile!'), $type->getAlias() . ': ' . htmlspecialchars($_POST['profileDeleteName']));
		$message->colspan = 10;
		$container->addElement($message, true);
	}
}

// check if profiles should be imported or exported
if (isset($_POST['importexport']) && ($_POST['importexport'] === '1')) {
	$cfg = new LAMCfgMain();
	$impExpMessage = null;
	if (isset($_POST['importProfiles_' . $_POST['typeId']])) {
		// check master password
		if (!$cfg->checkPassword($_POST['passwd_' . $_POST['typeId']])) {
			$impExpMessage = new htmlStatusMessage('ERROR', _('Master password is wrong!'));
		}
		elseif (copyAccountProfiles($_POST['importProfiles_' . $_POST['typeId']], $_POST['typeId'])) {
			$impExpMessage = new htmlStatusMessage('INFO', _('Import successful'));
		}
	} else if (isset($_POST['exportProfiles'])) {
		// check master password
		if (!$cfg->checkPassword($_POST['passwd'])) {
			$impExpMessage = new htmlStatusMessage('ERROR', _('Master password is wrong!'));
		}
		elseif (copyAccountProfiles($_POST['exportProfiles'], $_POST['typeId'], $_POST['destServerProfiles'])) {
			$impExpMessage = new htmlStatusMessage('INFO', _('Export successful'));
		}
	}
	if ($impExpMessage != null) {
		$impExpMessage->colspan = 10;
		$container->addElement($impExpMessage, true);
	}
}

// get list of profiles for each account type
for ($i = 0; $i < sizeof($profileClasses); $i++) {
	$profileList = getAccountProfiles($profileClasses[$i]['typeId']);
	natcasesort($profileList);
	$profileClasses[$i]['profiles'] = $profileList;
}

if (isset($_GET['savedSuccessfully'])) {
	$message = new htmlStatusMessage("INFO", _("Profile was saved."), htmlspecialchars($_GET['savedSuccessfully']));
	$message->colspan = 10;
	$container->addElement($message, true);
}

// new profile
if (!empty($profileClasses)) {
	$container->addElement(new htmlSubTitle(_('Create a new profile')), true);
	$sortedTypes = array();
	for ($i = 0; $i < sizeof($profileClasses); $i++) {
		$sortedTypes[$profileClasses[$i]['title']] = $profileClasses[$i]['typeId'];
	}
	natcasesort($sortedTypes);
	$newContainer = new htmlTable();
	$newProfileSelect = new htmlSelect('createProfile', $sortedTypes);
	$newProfileSelect->setHasDescriptiveElements(true);
	$newProfileSelect->setWidth('15em');
	$newContainer->addElement($newProfileSelect);
	$newContainer->addElement(new htmlSpacer('10px', null));
	$newContainer->addElement(new htmlButton('createProfileButton', _('Create')), true);
	$container->addElement($newContainer, true);
}

$container->addElement(new htmlSpacer(null, '10px'), true);

// existing profiles
$container->addElement(new htmlSubTitle(_('Manage existing profiles')), true);
$existingContainer = new htmlTable();
$existingContainer->colspan = 5;

$configProfiles = getConfigProfiles();

for ($i = 0; $i < sizeof($profileClasses); $i++) {
	if ($i > 0) {
		$existingContainer->addElement(new htmlSpacer(null, '10px'), true);
	}

	$existingContainer->addElement(new htmlImage('../../graphics/' . $profileClasses[$i]['typeId'] . '.png'));
	$existingContainer->addElement(new htmlSpacer('3px', null));
	$existingContainer->addElement(new htmlOutputText($profileClasses[$i]['title']));
	$existingContainer->addElement(new htmlSpacer('3px', null));
	$select = new htmlSelect('profile_' . $profileClasses[$i]['typeId'], $profileClasses[$i]['profiles']);
	$select->setWidth('15em');
	$existingContainer->addElement($select);
	$existingContainer->addElement(new htmlSpacer('3px', null));
	$editButton = new htmlButton('editProfile_' . $profileClasses[$i]['typeId'], 'edit.png', true);
	$editButton->setTitle(_('Edit'));
	$existingContainer->addElement($editButton);
	$deleteLink = new htmlLink(null, '#', '../../graphics/delete.png');
	$deleteLink->setTitle(_('Delete'));
	$deleteLink->setOnClick("profileShowDeleteDialog('" . _('Delete') . "', '" . _('Ok') . "', '" . _('Cancel') . "', '" . $profileClasses[$i]['typeId'] . "', '" . 'profile_' . $profileClasses[$i]['typeId'] . "');");
	$existingContainer->addElement($deleteLink);
	if (count($configProfiles) > 1) {
		$importLink = new htmlLink(null, '#', '../../graphics/import.png');
		$importLink->setTitle(_('Import profiles'));
		$importLink->setOnClick("showDistributionDialog('" . _("Import profiles") . "', '" .
								_('Ok') . "', '" . _('Cancel') . "', '" . $profileClasses[$i]['typeId'] . "', 'import');");
		$existingContainer->addElement($importLink);
	}
	$exportLink = new htmlLink(null, '#', '../../graphics/export.png');
	$exportLink->setTitle(_('Export profile'));
	$exportLink->setOnClick("showDistributionDialog('" . _("Export profile") . "', '" .
							_('Ok') . "', '" . _('Cancel') . "', '" . $profileClasses[$i]['typeId'] . "', 'export', '" . 'profile_' . $profileClasses[$i]['typeId'] . "', '" . $_SESSION['config']->getName() . "');");
	$existingContainer->addElement($exportLink);
	$existingContainer->addNewLine();
}
$container->addElement($existingContainer);
$container->addElement(new htmlSpacer(null, '10px'), true);

// generate content
$tabindex = 1;
parseHtml(null, $container, array(), false, $tabindex, 'user');

echo "</form>\n";
echo "</div>\n";

for ($i = 0; $i < sizeof($profileClasses); $i++) {
	$typeId = $profileClasses[$i]['typeId'];
	$tmpArr = array();
	foreach ($configProfiles as $profile) {
		if ($profile != $_SESSION['config']->getName()) {
			$accountProfiles = getAccountProfiles($typeId, $profile);
			if (!empty($accountProfiles)) {
				for ($p = 0; $p < sizeof($accountProfiles); $p++) {
					$tmpArr[$profile][$accountProfiles[$p]] = $profile . '##' . $accountProfiles[$p];
				}
			}
		}
	}

	//import dialog
	echo "<div id=\"importDialog_$typeId\" class=\"hidden\">\n";
	echo "<form id=\"importDialogForm_$typeId\" method=\"post\" action=\"profilemain.php\">\n";

	$container = new htmlTable();
	$container->addElement(new htmlOutputText(_('Profiles')), true);

	$select = new htmlSelect('importProfiles_' . $typeId, $tmpArr, array(), count($tmpArr, 1) < 15 ? count($tmpArr, 1) : 15);
	$select->setMultiSelect(true);
	$select->setHasDescriptiveElements(true);
	$select->setContainsOptgroups(true);
	$select->setWidth('290px');

	$container->addElement($select);
	$container->addElement(new htmlHelpLink('362'), true);

	$container->addElement(new htmlSpacer(null, '10px'), true);

	$container->addElement(new htmlOutputText(_("Master password")), true);
	$exportPasswd = new htmlInputField('passwd_' . $typeId);
	$exportPasswd->setIsPassword(true);
	$container->addElement($exportPasswd);
	$container->addElement(new htmlHelpLink('236'));
	$container->addElement(new htmlHiddenInput('importexport', '1'));
	$container->addElement(new htmlHiddenInput('typeId', $typeId), true);
	addSecurityTokenToMetaHTML($container);

	parseHtml(null, $container, array(), false, $tabindex, 'user');

	echo '</form>';
	echo "</div>\n";
}

//export dialog
echo "<div id=\"exportDialog\" class=\"hidden\">\n";
echo "<form id=\"exportDialogForm\" method=\"post\" action=\"profilemain.php\">\n";

$container = new htmlTable();

$container->addElement(new htmlOutputText(_('Profile name')), true);
$expStructGroup = new htmlTable();
$expStructGroup->addElement(new htmlSpacer('10px', null));
$expStructGroup->addElement(new htmlDiv('exportName', ''));
$container->addElement($expStructGroup, true);
$container->addElement(new htmlSpacer(null, '10px'), true);

$container->addElement(new htmlOutputText(_("Target server profile")), true);
foreach ($configProfiles as $key => $value) {
	$tmpProfiles[$value] = $value;
}
natcasesort($tmpProfiles);
$tmpProfiles['*' . _('Global templates')] = 'templates*';

$findProfile = array_search($_SESSION['config']->getName(), $tmpProfiles);
if ($findProfile !== false) {
	unset($tmpProfiles[$findProfile]);
}
$select = new htmlSelect('destServerProfiles', $tmpProfiles, array(), count($tmpProfiles) < 10 ? count($tmpProfiles) : 10);
$select->setHasDescriptiveElements(true);
$select->setSortElements(false);
$select->setMultiSelect(true);

$container->addElement($select);
$container->addElement(new htmlHelpLink('363'), true);

$container->addElement(new htmlSpacer(null, '10px'), true);

$container->addElement(new htmlOutputText(_("Master password")), true);
$exportPasswd = new htmlInputField('passwd');
$exportPasswd->setIsPassword(true);
$container->addElement($exportPasswd);
$container->addElement(new htmlHelpLink('236'));
$container->addElement(new htmlHiddenInput('importexport', '1'), true);
addSecurityTokenToMetaHTML($container);

parseHtml(null, $container, array(), false, $tabindex, 'user');

echo '</form>';
echo "</div>\n";

// form for delete action
echo '<div id="deleteProfileDialog" class="hidden"><form id="deleteProfileForm" action="profilemain.php" method="post">';
	echo _("Do you really want to delete this profile?");
	echo '<br><br><div class="nowrap">';
	echo _("Profile name") . ': <div id="deleteText" style="display: inline;"></div></div>';
	echo '<input id="profileDeleteType" type="hidden" name="profileDeleteType" value="">';
	echo '<input id="profileDeleteName" type="hidden" name="profileDeleteName" value="">';
	echo '<input type="hidden" name="deleteProfile" value="true">';
	echo '<input type="hidden" name="' . getSecurityTokenName() . '" value="' . getSecurityTokenValue() . '">';
echo '</form></div>';

include '../main_footer.php';

?>
