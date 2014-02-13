<?php
/******************************************************************************
/** @class Message
 *  @brief Simple presentation of messages to the user
 *
 *  This class creates a new html page with a simple headline and a message. It's
 *  designed to easily integrate this class into your code. An object @b $gMessage 
 *  of this class is created in the common.php. You can set a url that should be
 *  open after user confirmed the message or you can show a question with two
 *  default buttons yes and no. There is also an option to automatically leave the 
 *  message after some time.
 *  @par Examples
 *  @code // show a message with a back button, the object $gMessage is created in common.php
 *  $gMessage->show($gL10n->get('SYS_MESSAGE_TEXT_ID'));
 *
 *  // show a message and set a link to a page that should be shown after user click ok
 *  $gMessage->setForwardUrl('http://www.example.de/mypage.php');
 *  $gMessage->show($gL10n->get('SYS_MESSAGE_TEXT_ID'));
 *
 *  // show a message with yes and no button and set a link to a page that should be shown after user click yes
 *  $gMessage->setForwardYesNo('http://www.example.de/mypage.php');
 *  $gMessage->show($gL10n->get('SYS_MESSAGE_TEXT_ID'));@endcode
 */
 /*****************************************************************************
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

class Message 
{
    private $inline;            // wird ermittelt, ob bereits eine Ausgabe an den Browser erfolgt ist
    private $forwardUrl;        // Url auf die durch den Weiter-Button verwiesen wird
    private $timer;             // Anzahl ms bis automatisch zu forwardUrl weitergeleitet wird
    private $includeThemeBody;  // bindet header, body_top & body_bottom in der Anzeige mit ein
    
    private $showButtons;       // Buttons werden angezeigt
    private $showYesNoButtons;  // Anstelle von Weiter werden Ja/Nein-Buttons angezeigt
    private $showCloseButton;   // Anstelle von Weiter wird ein Schliessen-Buttons angezeigt
    
    /** Constructor that initialize the class member parameters
     */
    public function __construct()
    {
        $this->inline           = false;
        $this->showButtons      = true;
        $this->showYesNoButtons = false;
        $this->showCloseButton  = false;
        $this->includeThemeBody = true;
    }

    /** No button will be shown in the message window.
     */
    public function hideButtons()
    {
        $this->showButtons = false;
    }
    
    /** Adds a Close button to the message page. This is only useful if the message 
     *  is shown as a popup window.
     */
    public function setCloseButton()
    {
        $this->showCloseButton = true;
    }
    
    /** If set no theme files like my_header.php, my_body_top.php and my_body_bottom.php 
     *  will be integrated in the page. This setting is usefull if the message should be loaded in 
     *  a small window.
     */
    public function setExcludeThemeBody()
    {
        $this->includeThemeBody = false;
    }
    
    /** Set a URL to which the user should be directed if he confirmed the message.
     *  It's possible to set a timer after that the page of the url will be
     *  automatically displayed without user inteaction.
     *  @param $url   The full url to which the user should be directed.
     *  @param $timer Optional a timer in millisec after the user will be 
     *                automatically redirected to the $url.
     */
    public function setForwardUrl($url, $timer = 0)
    {
        $this->forwardUrl = $url;
        
        if($timer > 0 && is_numeric($timer))
        {
            $this->timer = $timer;
        }
        else
        {
            $this->timer = 0;
        }
    }
    
    /** Add two buttons with the labels @b yes and @b no to the message. If the user
     *  choose yes he will be redirected to the $url. If he chooses no he will be
     *  directed back to the previous page.
     *  @param $url The full url to which the user should be directed if he chooses @b yes.
     */
    public function setForwardYesNo($url)
    {
        $this->forwardUrl       = $url;
        $this->showYesNoButtons = true;
    }
    
	/** Create a html page if necessary and show the message with the configured buttons.
	 *  @param $content  The message text that should be shown. The content could have html.
	 *  @param $headline Optional a headline for the message. Default will be SYS_NOTE.
	 *  @return Returns the complete html page with the message.
	 */
    public function show($content, $headline = null)
    {
        // noetig, da dies bei den includes benoetigt wird
        global $gForum, $gLayout, $gDb, $gDbConnection, $g_adm_db, $gL10n;
        global $gValidLogin, $g_root_path, $gPreferences, $gHomepage, $gMessages, $gProfileFields;
        global $g_organization, $gCurrentOrganization, $gCurrentUser, $gCurrentSession;
		
		// first perform a rollback in database if there is an open transaction
		$gDb->rollback();

        // Ueberschrift setzen, falls diese vorher nicht explizit gesetzt wurde
        if(strlen($headline) == 0)
        {
            $headline = $gL10n->get('SYS_NOTE');
        }

        // Variablen angeben
        if($this->inline == false)
        {
            // nur pruefen, wenn vorher nicht schon auf true gesetzt wurde
            $this->inline = headers_sent();
        }
        
        if($this->inline == false)
        {
            // Html-Kopf ausgeben
            $gLayout['title']    = $headline;
            $gLayout['includes'] = $this->includeThemeBody;
            if ($this->timer > 0)
            {
                $gLayout['header'] = '<script language="JavaScript1.2" type="text/javascript"><!--
                    window.setTimeout("window.location.href=\''. $this->forwardUrl. '\'", '. $this->timer. ');
                    //--></script>';
            }
    
            require(SERVER_PATH. '/adm_program/system/overall_header.php');       
        }
        else
        {
            header('Content-type: text/html; charset=utf-8'); 
        }
        
        echo '<h1 class="admHeadline">'.$headline.'</h1>
		<div class="admMessage">
			<p>'. $content.'</p>';
                
			if($this->showButtons == true)
			{
				echo '<ul class="admIconTextLinkList">';
					if(strlen($this->forwardUrl) > 0)
					{
						if($this->showYesNoButtons == true)
						{
							echo '<li>
								<button id="admButtonYes" class="admButton" type="button" onclick="self.location.href=\''. $this->forwardUrl. '\'"><img src="'. THEME_PATH. '/icons/ok.png" 
									alt="'.$gL10n->get('SYS_YES').'" />&nbsp;&nbsp;'.$gL10n->get('SYS_YES').'&nbsp;&nbsp;&nbsp;</button>
							</li>
							<li>
								<button id="admButtonNo" class="admButton" type="button" onclick="history.back()"><img src="'. THEME_PATH. '/icons/error.png" 
									alt="'.$gL10n->get('SYS_NO').'" />&nbsp;'.$gL10n->get('SYS_NO').'</button>
							</li>';
						}
						else
						{
							// Wenn weitergeleitet wird, dann auch immer einen Weiter-Button anzeigen
							echo '<li>
								<span class="admIconTextLink">
									<a href="'. $this->forwardUrl. '">'.$gL10n->get('SYS_NEXT').'</a>
									<a href="'. $this->forwardUrl. '"><img src="'. THEME_PATH. '/icons/forward.png" alt="'.$gL10n->get('SYS_NEXT').'" title="'.$gL10n->get('SYS_NEXT').'" /></a>
								</span>
							</li>';
						}
					}
					else
					{
						// Wenn nicht weitergeleitet wird, dann immer einen Zurueck-Button anzeigen 
						// bzw. ggf. einen Fenster-Schließen-Button                       
						if($this->showCloseButton == true)
						{
							echo '<li>
								<span class="admIconTextLink">
									<a href="javascript:window.close()"><img src="'. THEME_PATH. '/icons/door_in.png" alt="'.$gL10n->get('SYS_CLOSE').'" title="'.$gL10n->get('SYS_CLOSE').'" /></a>
									<a href="javascript:window.close()">'.$gL10n->get('SYS_CLOSE').'</a>
								</span>
							</li>';
						}
						else
						{
							echo '<li>
								<span class="admIconTextLink">
									<a href="javascript:history.back()"><img 
										src="'. THEME_PATH. '/icons/back.png" alt="'.$gL10n->get('SYS_BACK').'" title="'.$gL10n->get('SYS_BACK').'" /></a>
									<a href="javascript:history.back()">'.$gL10n->get('SYS_BACK').'</a>
								</span>
							</li>';
						}
					}
				echo '</ul>';
			}
		echo '</div>';
        
        if($this->inline == false)
        {
            require(SERVER_PATH. '/adm_program/system/overall_footer.php');
            exit();
        }
    }
}
?>
