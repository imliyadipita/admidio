<?php
/**
 ***********************************************************************************************
 * Create and edit guestbook comments
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * id            - ID des Eintrages, dem ein Kommentar hinzugefuegt werden soll
 * cid           - ID des Kommentars der editiert werden soll
 * headline      - Ueberschrift, die ueber den Einraegen steht
 *                 (Default) Gaestebuch
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');

// Initialize and check the parameters
$getGboId    = admFuncVariableIsValid($_GET, 'id',       'int');
$getGbcId    = admFuncVariableIsValid($_GET, 'cid',      'int');
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', array('defaultValue' => $gL10n->get('GBO_GUESTBOOK')));

// check if the module is enabled and disallow access if it's disabled
if ((int) $gSettingsManager->get('enable_guestbook_module') === 0)
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// Es muss ein (nicht zwei) Parameter uebergeben werden: Entweder id oder cid...
if($getGboId > 0 && $getGbcId > 0)
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    // => EXIT
}

// set create or edit mode
if($getGboId > 0)
{
    $id       = $getGboId;
    $mode     = '4';
    $headline = $gL10n->get('GBO_CREATE_COMMENT');
}
else
{
    $id       = $getGbcId;
    $mode     = '8';
    $headline = $gL10n->get('GBO_EDIT_COMMENT');
}

// Erst einmal die Rechte abklopfen...
if(((int) $gSettingsManager->get('enable_guestbook_module') === 2 || !$gSettingsManager->getBool('enable_gbook_comments4all')) && $getGboId > 0)
{
    // Falls anonymes kommentieren nicht erlaubt ist, muss der User eingeloggt sein zum kommentieren
    require(__DIR__ . '/../../system/login_valid.php');

    if (!$gCurrentUser->commentGuestbookRight())
    {
        // der User hat kein Recht zu kommentieren
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }
}

if($getGbcId > 0)
{
    // Zum editieren von Kommentaren muss der User auch eingeloggt sein
    require(__DIR__ . '/../../system/login_valid.php');

    if (!$gCurrentUser->editGuestbookRight())
    {
        // der User hat kein Recht Kommentare zu editieren
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }

}

$gNavigation->addUrl(CURRENT_URL, $headline);

// Gaestebuchkommentarobjekt anlegen
$gbComment = new TableGuestbookComment($gDb);

if($getGbcId > 0)
{
    $gbComment->readDataById($getGbcId);

    // Pruefung, ob der Eintrag zur aktuellen Organisation gehoert
    if((int) $gbComment->getValue('gbo_org_id') !== (int) $gCurrentOrganization->getValue('org_id'))
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }
}

if(isset($_SESSION['guestbook_comment_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte ins Objekt schreiben
    $gbComment->setArray($_SESSION['guestbook_comment_request']);
    unset($_SESSION['guestbook_comment_request']);
}

// Wenn der User eingeloggt ist und keine cid uebergeben wurde
// koennen zumindest Name und Emailadresse vorbelegt werden...
if($getGbcId === 0 && $gValidLogin)
{
    $gbComment->setValue('gbc_name', $gCurrentUser->getValue('FIRST_NAME') . ' ' . $gCurrentUser->getValue('LAST_NAME'));
    $gbComment->setValue('gbc_email', $gCurrentUser->getValue('EMAIL'));
}

if (!$gValidLogin && $gSettingsManager->getInt('flooding_protection_time') > 0)
{
    // Falls er nicht eingeloggt ist, wird vor dem Ausfuellen des Formulars noch geprueft ob der
    // User innerhalb einer festgelegten Zeitspanne unter seiner IP-Adresse schon einmal
    // einen GB-Eintrag erzeugt hat...
    $ipAddress = $_SERVER['REMOTE_ADDR'];

    $sql = 'SELECT COUNT(*) AS count
              FROM '.TBL_GUESTBOOK_COMMENTS.'
             WHERE unix_timestamp(gbc_timestamp_create) > unix_timestamp() - ? -- $gSettingsManager->getInt(\'flooding_protection_time\')
               AND gbc_ip_address = ? -- $gbComment->getValue(\'gbc_ip_address\')';
    $pdoStatement = $gDb->queryPrepared($sql, array($gSettingsManager->getInt('flooding_protection_time'), $gbComment->getValue('gbc_ip_address')));

    if($pdoStatement->fetchColumn() > 0)
    {
        // Wenn dies der Fall ist, gibt es natuerlich keinen Gaestebucheintrag...
        $gMessage->show($gL10n->get('GBO_FLOODING_PROTECTION', array($gSettingsManager->getInt('flooding_protection_time'))));
        // => EXIT
    }
}

// create html page object
$page = new HtmlPage($headline);

// add back link to module menu
$guestbookCommentCreateMenu = $page->getMenu();
$guestbookCommentCreateMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

// show form
$form = new HtmlForm('guestbook_comment_edit_form', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/guestbook/guestbook_function.php', array('id' => $id, 'headline' => $getHeadline, 'mode' => $mode)), $page);
if ($gCurrentUser->getValue('usr_id') > 0)
{
    // registered users should not change their name
    $form->addInput(
        'gbc_name', $gL10n->get('SYS_NAME'), $gbComment->getValue('gbc_name'),
        array('maxLength' => 60, 'property' => HtmlForm::FIELD_DISABLED)
    );
}
else
{
    $form->addInput(
        'gbc_name', $gL10n->get('SYS_NAME'), $gbComment->getValue('gbc_name'),
        array('maxLength' => 60, 'property' => HtmlForm::FIELD_REQUIRED)
    );
}
$form->addInput(
    'gbc_email', $gL10n->get('SYS_EMAIL'), $gbComment->getValue('gbc_email'),
    array('type' => 'email', 'maxLength' => 254)
);
$form->addEditor(
    'gbc_text', $gL10n->get('SYS_COMMENT'), $gbComment->getValue('gbc_text'),
    array('property' => HtmlForm::FIELD_REQUIRED, 'toolbar' => 'AdmidioGuestbook')
);

// if captchas are enabled then visitors of the website must resolve this
if (!$gValidLogin && $gSettingsManager->getBool('enable_mail_captcha'))
{
    $form->openGroupBox('gb_confirmation_of_entry', $gL10n->get('SYS_CONFIRMATION_OF_INPUT'));
    $form->addCaptcha('captcha_code');
    $form->closeGroupBox();
}

// show information about user who creates the recordset and changed it
$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => THEME_URL.'/icons/disk.png'));
$form->addHtml(admFuncShowCreateChangeInfoById(
    (int) $gbComment->getValue('gbc_usr_id_create'), $gbComment->getValue('gbc_timestamp_create'),
    (int) $gbComment->getValue('gbc_usr_id_change'), $gbComment->getValue('gbc_timestamp_change')
));

// add form to html page and show page
$page->addHtml($form->show(false));
$page->show();
