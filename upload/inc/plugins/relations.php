<?php

/**
 * Relations  - by risuena
 * Beziehungen von Charakteren zueinander
 *  Anfragen im Profil des Charakters
 *    Eigene Kategorien möglich
 *    Bestätigung nötig
 *    Verwaltung im UCP
 *    Eintragen von NPCs mit Bild auf Wunsch
 *
 * Kontakt: https://github.com/katjalennartz
 */

// enable for Debugging:
// error_reporting ( -1 );
// ini_set ( 'display_errors', true );

// Disallow direct access to this file for security reasons
if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

function relations_info()
{
    global $lang;
    $lang->load("relations");

    return array(
        "name" => $lang->relations_title,
        "description" => $lang->relations_descr,
        "website" => $lang->relations_website,
        "author" => $lang->relations_author,
        "authorsite" => $lang->relations_website,
        "version" => "2.0",
        "compatibility" => "18*"
    );
}

function relations_uninstall()
{
    global $db;
    //Tabellen deinstallieren, wenn sie existieren
    if ($db->table_exists("relas_entries")) {
        $db->drop_table("relas_entries");
    }
    if ($db->table_exists("relas_categories")) {
        $db->drop_table("relas_categories");
    }
    if ($db->table_exists("relas_subcategories")) {
        $db->drop_table("relas_subcategories");
    }

    //hier löschen wir alle templates und die Templategruppe
    $db->delete_query("templates", "title LIKE 'relas_%'");
    $db->delete_query("templategroups", "prefix = 'relas'");
}


function relations_install()
{
    global $db;

    //table Rela
    $db->query("CREATE TABLE `" . TABLE_PREFIX . "relas_entries` (
        `r_id` int(10) NOT NULL AUTO_INCREMENT,
        `r_to` int(10) NOT NULL,
        `r_from` int(10) NOT NULL,
        `r_kategorie` varchar(150) NOT NULL,
        `r_kommentar` varchar(2555) NOT NULL,
        `r_accepted` int(10) NOT NULL,
        `r_npc` int(11) NOT NULL,
        `r_npcname` varchar(150) NOT NULL,
        `r_npcimg` varchar(250) NOT NULL, 
        `r_sort` int(10) NOT NULL,
        PRIMARY KEY (`r_id`)
    ) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_general_ci;");

    //table Category
    $db->query("CREATE TABLE `" . TABLE_PREFIX . "relas_categories` (
        `c_id` int(10) NOT NULL AUTO_INCREMENT,
        `c_name` varchar(100) NOT NULL,
        `c_sort` int(10) NOT NULL,
        `c_uid` int(10) NOT NULL,
        PRIMARY KEY (`c_id`)
        ) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_general_ci;");

    //table subcat
    $db->query("CREATE TABLE `" . TABLE_PREFIX . "relas_subcategories` (
        `sc_id` int(10) NOT NULL AUTO_INCREMENT,
        `sc_name` varchar(100) NOT NULL,
        `sc_cid` int(10) NOT NULL,
        `sc_sort` int(10) NOT NULL,
        `sc_uid` int(10) NOT NULL,
        PRIMARY KEY (`sc_id`)
    ) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_general_ci;");

    relations_addtemplates();
}

function relations_is_installed()
{
    global $db;
    if ($db->table_exists("relas_entries")) {
        return true;
    }
    return false;
}

function relations_activate()
{
    global $db, $mybb, $lang;
    $lang->load("relations");

    // Einstellungen
    $setting_group = array(
        'name' => 'relations',
        'title' => $lang->relations_title,
        'description' => $lang->relations_settings_descr,
        'disporder' => 7, // The order your setting group will display
        'isdefault' => 0
    );
    $gid = $db->insert_query("settinggroups", $setting_group);

    $setting_array = array(
        'relas_alert_confirm' => array(
            'title' => $lang->relations_settings_alertConfirmTitle,
            'description' => $lang->relations_settings_alertConfirmDescr,
            'optionscode' => 'yesno',
            'value' => '1', // Default
            'disporder' => 1
        ),
        'relas_alert' => array(
            'title' => $lang->relations_settings_alertAlertTitle,
            'description' => $lang->relations_settings_alertAlertDescr,
            'optionscode' => 'yesno',
            'value' => '1', // Default
            'disporder' => 2
        ),
        'relas_alert_delete' => array(
            'title' => $lang->relations_settings_alertDeleteTitle,
            'description' => $lang->relations_settings_alertDeleteDescr,
            'optionscode' => 'yesno',
            'value' => '1', // Default
            'disporder' => 3
        ),
        'relas_img_guests' => array(
            'title' => $lang->relations_settings_guestAvaTitle,
            'description' => $lang->relations_settings_guestAvaDescr,
            'optionscode' => 'yesno',
            'value' => '0', // Default
            'disporder' => 4
        ),
        'relas_img_width' => array(
            'title' => $lang->relations_settings_imgWidthTitle,
            'description' => $lang->relations_settings_imgWidthDescr,
            'optionscode' => 'numeric',
            'value' => '35', // Default
            'disporder' => 5
        ),
        'relas_npc' => array(
            'title' => $lang->relations_settings_npcTitle,
            'description' => $lang->relations_settings_npcDescr,
            'optionscode' => 'yesno',
            'value' => '1', // Default
            'disporder' => 6
        ),
        // mit bild?
        'relas_npc_img' => array(
            'title' => $lang->relations_settings_npcImgTitle,
            'description' => $lang->relations_settings_npcImgDescr,
            'optionscode' => 'yesno',
            'value' => '0', // Default
            'disporder' => 7
        ),
        'relas_html' => array(
            'title' => $lang->relations_settings_htmlTitle,
            'description' => $lang->relations_settings_htmlDescr,
            'optionscode' => 'yesno',
            'value' => '0', // Default
            'disporder' => 8
        ),
        'relas_mycode' => array(
            'title' => $lang->relations_settings_mycodeTitle,
            'description' => $lang->relations_settings_mycodeDescr,
            'optionscode' => 'yesno',
            'value' => '0', // Default
            'disporder' => 9
        )
    );

    foreach ($setting_array as $name => $setting) {
        $setting['name'] = $name;
        $setting['gid'] = $gid;
        $db->insert_query('settings', $setting);
    }
    rebuild_settings();

    //Variable ins Template einfügen
    include MYBB_ROOT . "/inc/adminfunctions_templates.php";
    find_replace_templatesets("member_profile", "#" . preg_quote('</fieldset>') . "#i", '</fieldset>{$relas_memberprofil}');

    //Alerts Hinzufügen
    //Testen ob das MyAlertPlugin installiert ist
    if (function_exists('myalerts_is_activated') && myalerts_is_activated()) {
        $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();
        if (!$alertTypeManager) {
            $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);
        }

        $alertTypeRelationRequest = new MybbStuff_MyAlerts_Entity_AlertType();
        $alertTypeRelationRequest->setCanBeUserDisabled(true);
        $alertTypeRelationRequest->setCode("relation_request");
        $alertTypeRelationRequest->setEnabled(true);
        $alertTypeManager->add($alertTypeRelationRequest);

        $alertTypeRelationRequest = new MybbStuff_MyAlerts_Entity_AlertType();
        $alertTypeRelationRequest->setCanBeUserDisabled(true);
        $alertTypeRelationRequest->setCode("relation_confirm");
        $alertTypeRelationRequest->setEnabled(true);
        $alertTypeManager->add($alertTypeRelationRequest);

        $alertTypeRelationDeny = new MybbStuff_MyAlerts_Entity_AlertType();
        $alertTypeRelationDeny->setCanBeUserDisabled(true);
        $alertTypeRelationDeny->setCode("relation_deny");
        $alertTypeRelationDeny->setEnabled(true);
        $alertTypeManager->add($alertTypeRelationDeny);

        $alertTypeRelationReminder = new MybbStuff_MyAlerts_Entity_AlertType();
        $alertTypeRelationReminder->setCanBeUserDisabled(true);
        $alertTypeRelationReminder->setCode("relation_reminder");
        $alertTypeRelationReminder->setEnabled(true);
        $alertTypeManager->add($alertTypeRelationReminder);

        $alertTypeRelationDelete = new MybbStuff_MyAlerts_Entity_AlertType();
        $alertTypeRelationDelete->setCanBeUserDisabled(true);
        $alertTypeRelationDelete->setCode("relation_delete");
        $alertTypeRelationDelete->setEnabled(true);
        $alertTypeManager->add($alertTypeRelationDelete);
    }
}

function relations_deactivate()
{
    global $db;
    include MYBB_ROOT . "/inc/adminfunctions_templates.php";
    //hier entfernen wir die Variable aus dem member_profile template
    find_replace_templatesets("member_profile", "#" . preg_quote('{$relas_memberprofil}') . "#i", '');
    // Einstellungen entfernen
    $db->delete_query('settings', "name IN (
      'relas_alert_confirm',
      'relas_alert_delete',
      'relas_img_guests',
      'relas_img_width',
      'relas_npc',
      'relas_npc_img',
      'relas_html',
      'relas_mycode'
      )");
    $db->delete_query('settinggroups', "name = 'relations'");
    rebuild_settings();

    // Alerts deaktivieren
    if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
        $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();

        if (!$alertTypeManager) {
            $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);
        }
        $alertTypeManager->deleteByCode('relation_request');
        $alertTypeManager->deleteByCode('relation_reminder');
        $alertTypeManager->deleteByCode('relation_delete');
        $alertTypeManager->deleteByCode('relation_confirm');
        $alertTypeManager->deleteByCode('relation_deny');
    }
}

function relations_addtemplates()
{
    global $db;

    //add templates and stylesheets
    // Add templategroup
    $templategrouparray = array(
        'prefix' => 'relas',
        'title'  => $db->escape_string('Relations'),
        'isdefault' => 1
    );
    $db->insert_query("templategroups", $templategrouparray);

    //Templates hinzufügen
    $template[0] = array(
        "title" => 'relas_memberprofil',
        "template" => '
		<div class="memrelas">
        {$relas_memberprofil_cat}
        {$relas_memberprofil_anfrage}
        </div>
		',
        "sid" => "-2",
        "version" => "1.0",
        "dateline" => TIME_NOW
    );

    $template[1] = array(
        "title" => 'relas_memberprofil_cat',
        "template" => '
        <div class="memrelas-catcon">
            <h1>{$c_name}</h1>
            {$relas_memberprofil_subcat}
        </div>			
		',
        "sid" => "-2",
        "version" => "1.0",
        "dateline" => TIME_NOW
    );

    $template[2] = array(
        "title" => 'relas_memberprofil_subcat',
        "template" => '
        <div class="memrelas-catcon__item memrelas-subcat">
        <h2>{$sc_name}</h2>
        {$relas_memberprofil_entrybit}
        </div>		
				',
        "sid" => "-2",
        "version" => "1.0",
        "dateline" => TIME_NOW
    );

    $template[3] = array(
        "title" => 'relas_memberprofil_entrybit',
        "template" => '
        <div class="memrelas-subcat__item memrelas-entry" >
            {$rela_avatar}
            <div class="memrelas-entry__item">
            {$rela_name}
            </div>
            {$rela_descr}
        </div>		
				',
        "sid" => "-2",
        "version" => "1.0",
        "dateline" => TIME_NOW
    );

    $template[4] = array(
        "title" => 'relas_memberprofil_anfrage',
        "template" => '
        <form action="member.php?action=profile&uid={$profilid}" method="post">
<div class="memrelas-request">
	<h2>Relationsanfrage stellen</h2>
    <div class="memrelas-request__item">
	<input type="number" placeholder="Darstellungsreihenfolge" name="sort" id="sort" value="{$sort}"/>
	</div>
	<div class="memrelas-request__item">
	{$form_select}
	</div>
	<div class="memrelas-request__item">
	<input type="text" placeholder="Kommentar zur Beziehung" id="kommentar" name="descr"/>
	<input type="hidden" name="getto" value="{$profilid}" />
	<input type="hidden" name="getfrom" value="{$thisuser}" />
	</div>
	<div class="memrelas-request__item">
	 <input type="submit" name="anfragen" value="anfragen" id="rela_button"/>
	</div>
</div>
 </form>
				',
        "sid" => "-2",
        "version" => "1.0",
        "dateline" => TIME_NOW
    );

    $template[5] = array(
        "title" => 'relas_ucp_subcats',
        "template" => '
        <h2>{$allsubs[\\\'sc_name\\\']}</h2>
        {$relas_ucp_alluser}
         ',
        "sid" => "-2",
        "version" => "1.0",
        "dateline" => TIME_NOW
    );

    $template[6] = array(
        "title" => 'relas_ucp',
        "template" => '
        <html>
        <head>
        <title>Relationsverwaltung</title>
        {$headerinclude}
          
        </head>
        <body>
        {$header}
        <table width="100%" border="0" align="center" class="tborder">
        <tr>
        {$usercpnav}
        <td valign="top">
        <div class="ucprelas-con">
        <div class="ucprelas-con__item">
          <h1>Relations</h1>
          <p>Hier kannst du deine Relations verwaltung. Die Einstellungen für die Alerts
        kannst du <a href="alerts.php?action=settings">hier</a> vornehmen. Hier kannst du Anfragen annehmen, oder ablehnen und NPCs eintragen. Anfragen stellen kannst du auf dem jeweiligen Profil eines Charakters stellen.
          </p>
          <h2>Verwaltung</h2>
            <div class="ucprelas-con__item ucprelas-manage">
                <div class="ucprelas-manage__item">
                    <h3>Kategorien verwalten</h3>
                    {$relas_ucp_managecat}
                </div>
                <div class="ucprelas-manage__item ucprelas-addcats">
                    <h3>Hauptkategorie erstellen</h3>
                    <div class="ucprelas-addcats__item">
                        <form action="usercp.php?action=relations" id="newcat" method="post" >
                            <input type="text" name="addMain" placeholder="Neue Hauptkategorie">
                            <input type="number" name="addMainSort" placeholder="Darstellungsreihenfolge">
                            <input form="newcat" name="newcat" type="submit" value="Speichern" />
                        </form>
                    </div>
                    <div class="ucprelas-addcats__item">
                    <h3>Unterkategorie erstellen</h3>
                    <form  action="usercp.php?action=relations" id="newsubcat" method="post" >
                        <input type="text" name="addSub" id="npcname" placeholder="Neue Unterkategorie">
                        <input type="number" name="addSubSort" placeholder="Darstellungsreihenfolge">
                        {$hauptkategorie}
                        <input form="newsubcat" name="newsubcat" type="submit" value="Speichern" />
                    </form>
    
                    </div>
                </div>
                <form class="ucprelas-manage__item" action="usercp.php?action=relations" method="post" >
                  <div class="ucprelas-npcform">
                     <h3>Npc Eintragen</h3>
                    <div class="ucprelas-npcform__item">
                    <input type="text" name="npcname" placeholder="NPC Name" id="npcname" value="">
                    </div>
                    {$img}
                    <div class="ucprelas-npcform__item">			
                    <input type="text" name="npcdescr" placeholder="Kommentar zur Beziehung" id="npcdescr" value="">	
                    </div>
                    <div class="ucprelas-npcform__item">
                    <input type="number" name="addNpcSort" placeholder="Darstellungsreihenfolge">
                    </div>
                    <div class="ucprelas-npcform__item">
                    {$cats_npc}
                    </div>
                    <div class="ucprelas-npcform__item">
                        <input type="submit" name="addnpc" value="speichern" id="addnpc" />
                    </div>
                        
                  </div>
                </form>
            </div>
        </div><!-- edn ucprelas-con__item ucprelas-request-->
            
        <div class="ucprelas-con__item ucprelas-request">
          <div class="ucprelas-request__item ucprelas-openrequests">
                  <h2>offene Anfragen</h2>
                <div class="ucprelas-openrequests__item ucprelas_toaccept">
                    <h3>an dich</h3>
                    {$relas_ucp_toaccept}
                  </div>
                   <div class="ucprelas-openrequests__item ucprelas_waiting">
                    <h3>von dir</h3>
                      {$relas_ucp_waiting}
                  </div>
          </div>
         
             <div class="ucprelas-con__item ucprelas-request">
          <div class="ucprelas-request__item ucprelas-all">
                  <h2>angenommene Relations</h2>
                <div class="ucprelas-all__item ucprelas-alltabs">
                    {$relas_ucp_tablinks}
                    <div class="ucprelas-alltabs__item ">
                        {$relas_ucp_cats}
                        {$relas_ucp_all}
                    </div>
                    
    
                  </div>
          </div>
          </div>
        </div><!--ucprelas-con-->
        
      
        </td>
        </tr>
        </table>
    <script>
            function openRestype(evt, relatype) {
                  // Declare all variables
                  var i, rela_tabcontent, relas_tablinks;
    
                  // Get all elements with class="tabcontent" and hide them
                  rela_tabcontent = document.getElementsByClassName("rela_tabcontent");
                  for (i = 0; i < rela_tabcontent.length; i++) {
                    rela_tabcontent[i].style.display = "none";
                  }
    
                  // Get all elements with class="tablinks" and remove the class "active"
                 relas_tablinks = document.getElementsByClassName("relas_tablinks");
                 for (i = 0; i < relas_tablinks.length; i++) {
                       relas_tablinks[i].className = relas_tablinks[i].className.replace(" active", "");
                }
    
                  // Show the current tab, and add an "active" class to the button that opened the tab
                  document.getElementById(relatype).style.display = "block";
                  evt.currentTarget.className += " active";
            }
    
        </script>
    <script>
    // Get the element with id="defaultOpen" and click on it
    document.getElementById("but_tabdefault").click();
    </script>
        {$footer}
        </body>
        
        </html>
        ',
        "sid" => "-2",
        "version" => "1.0",
        "dateline" => TIME_NOW
    );

    $template[7] = array(
        "title" => 'relas_ucp_alluser',
        "template" => '
        <div class="ucprelas_toaccept__item ucprelas-user">
        <div class="ucprelas-user__item name">
            {$username} <a onclick="$(\\\'#editrela{$rid}\\\').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== \\\'undefined\\\' ? modal_zindex : 9999) }); return false;" style="cursor: pointer;"><i class="fas fa-edit"></i> </a>		
        </div>
        <div class="ucprelas-user__item avarund" style="background-image:url(\\\'{$userimg}\\\')">
        </div>
                <div class="ucprelas-user__item cats">
                <b>{$haupt}:</b><br/> {$subkategorie}
        </div>
    {$kommentar}
    
    </div>
    
    <div class="modal editscname" id="editrela{$rid}" style="display: none; padding: 10px; margin: auto; text-align: center;">
        <form action="usercp.php?action=relations" id="formeditrela{$rid}" method="post" >
            {$editname}
            {$npc_editimg}
            <div class ="model-form">
                <input type="hidden" value="{$rid}" name="e_rela_id">
            <label for="e_rela_sort">Reihenfolge</label>
            <input type="number" value="{$relauser[\\\'r_sort\\\']}" name="e_rela_sort" />
            </div>
            <div class ="model-form">
            <label for="e_rela_kom">Kommentar</label>
            <input type="text" value="{$relauser[\\\'r_kommentar\\\']}" name="e_rela_kom" />
            </div>
            <div class ="model-form">
            <label for="kategorien">Cat</label>
                {$cats}
            </div>
            <div class ="model-form model-form--button">
            <input form="formeditrela{$rid}" type="submit" name="e_rela" value="Speichern" />
            </div>
        </form>
    </div>
        ',
        "sid" => "-2",
        "version" => "1.0",
        "dateline" => TIME_NOW
    );

    $template[8] = array(
        "title" => 'relas_ucp_cats',
        "template" => '
        <div class=" rela_tabcontent" id="tab_{$rela_type}">
        <h1>{$all[\\\'c_name\\\']}<h1>
        {$relas_ucp_subcats}
    </div>
        ',
        "sid" => "-2",
        "version" => "1.0",
        "dateline" => TIME_NOW
    );



    $template[9] = array(
        "title" => 'relas_ucp_managecat',
        "template" => '
        <div class="ucprelas_editcat">
        <span class="editcname" id="{$cid}"> {$c_name} {$editcatmod} <a href="usercp.php?action=relations&cat=delete&cid={$cid}" onClick="return confirm(\\\'Möchtest du die Kategorie {$c_name} wirklich löschen?\\\');">[x]</a></span> <br />
        <div class="managesubcat">{$relas_ucp_managesubcat}</div>
    </div>
    
    <div class="modal" id="cedit{$cid}" style="display: none; padding: 10px; margin: auto; text-align: center;">
        <form action="usercp.php?action=relations" id="editcat" method="post" >
        <!--<form action="usercp.php?action=relations" id="editcat{$cid}"  class="">-->
            <div class="model-form">
            <label for="c_edit">Name</label>
            <input type="text" value="{$c_name}" name="c_name_e">
            <input type="hidden" value="{$cid}" name="c_cid_e">
            </div>
            <div class="model-form">
            <label for="c_sort">Reihenfolge</label>
            <input type="number" value="{$cat[\\\'c_sort\\\']}" name="c_sort_e">
            </div>
            <div class="model-form model-form--button">		
                <input type="submit" name="editcat" value="Speichern" />            
            </div>
        </form>
    </div>
        ',
        "sid" => "-2",
        "version" => "1.0",
        "dateline" => TIME_NOW
    );



    $template[10] = array(
        "title" => 'relas_ucp_managesubcat',
        "template" => '
        <span class="editscname"  id={$subcat[\\\'sc_id\\\']}>{$sc_name} {$editscatmod} <a href="usercp.php?action=relations&scat=delete&scid={$scid}" onClick="return confirm(\\\'Möchtest du die Unterkategorie {$sc_name} wirklich löschen?\\\');">[x]</a></span>

        <div class="modal editscname" id="scedit{$scid}" style="display: none; padding: 10px; margin: auto; text-align: center;">
            <form action="usercp.php?action=relations" id="editscat{$scid}" method="post" >
                <div class ="model-form">
                <label for="sc_edit">Name</label>
                <input type="text" value="{$sc_name}" name="sc_edit_name">
                <input type="hidden" value="{$scid}" name="sc_edit_id">
                </div>
                <div class ="model-form">
                <label for="sc_sort">Reihenfolge</label>
                <input type="number" value="{$cat[\\\'sc_sort\\\']}" name="sc_edit_sort">
                </div>
                <div class ="model-form">
                <label for="kategorien">Hauptkategorie</label>
                    {$hauptkategoriesub}
                </div>
                <div class ="model-form model-form--button">
                <input form="editscat{$scid}" type="submit" name="editsubcat" value="Speichern" />
                </div>
            </form>
        </div>
        ',
        "sid" => "-2",
        "version" => "1.0",
        "dateline" => TIME_NOW
    );

    $template[11] = array(
        "title" => 'relas_ucp_tablinks',
        "template" => '
        <button class="relas_tablinks " onclick="openRestype(event, \\\'tab_{$all[\\\'c_id\\\']}\\\')" id="{$tabbuttonid}">{$all[\\\'c_name\\\']}</button>
        ',
        "sid" => "-2",
        "version" => "1.0",
        "dateline" => TIME_NOW
    );

    $template[12] = array(
        "title" => 'relas_ucp_toaccept',
        "template" => '
        <div class="ucprelas_toaccept__item ucprelas-requestuser">
        <div class="ucprelas-requestuser__item name">
            von: {$username}
        </div>
        <div class="ucprelas-requestuser__item avarund" style="background-image:url(\\\'{$user[\\\'avatar\\\']}\\\')">
        </div>
                <div class="ucprelas-requestuser__item cats">
                <b>{$haupt}:</b><br/> {$subkategorie}
        </div>
    {$kommentar}
            <div class="ucprelas-requestuser__item answer">
                <a href="usercp.php?action=relations&accept=1&rid={$entry[\\\'r_id\\\']}" onClick="return confirm(\\\'Möchtest du die Anfrage von {$user[\\\'username\\\']} annehmen?\\\');"><i class="fas fa-check-circle"></i></a>
                <a href="usercp.php?action=relations&deny=1&rid={$entry[\\\'r_id\\\']}" onClick="return confirm(\\\'Möchtest du die Anfrage von {$user[\\\'username\\\']} ablehnen? \\\');"><i class="fas fa-times-circle"></i></a> 
                <a onclick="$(\\\'#add{$entry[\\\'r_id\\\']}\\\').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== \\\'undefined\\\' ? modal_zindex : 9999) }); return false;" style="cursor: pointer;"><i class="fas fa-plus-circle"></i></a>
                
            <div class="modal addrela" id="add{$entry[\\\'r_id\\\']}" style="display: none; padding: 10px; margin: auto; text-align: center;">
                <form action="usercp.php?action=relations" id="addform{$entry[\\\'r_id\\\']}" method="post" >
                <input type="hidden" name="r_from" id="r_from" value="{$entry[\\\'r_from\\\']}">
                <input type="text" name="adddescr" placeholder="Kommentar zur Beziehung" id="npcdescr" value="">	
                <input type="number" name="addSort" placeholder="Darstellungsreihenfolge">
                {$cats}
                <input form="addform{$entry[\\\'r_id\\\']}" type="submit" name="addrela" value="Senden" />
                </form>
            </div>
                
        </div>
        
    
    </div>
        ',
        "sid" => "-2",
        "version" => "1.0",
        "dateline" => TIME_NOW
    );

    $template[13] = array(
        "title" => 'relas_ucp_waiting',
        "template" => '
        <div class="ucprelas_toaccept__item ucprelas-requestuser">
        <div class="ucprelas-requestuser__item name">
            An: {$username}
        </div>
        <div class="ucprelas-requestuser__item avarund" style="background-image:url(\\\'{$user[\\\'avatar\\\']}\\\')">
        </div>
                <div class="ucprelas-requestuser__item cats">
                <b>{$haupt}:</b><br/> {$subkategorie}
        </div>
    {$kommentar}
            <div class="ucprelas-requestuser__item answer">
                <a href="usercp.php?action=relations&reminder={$user[\\\'uid\\\']}" onClick="return confirm(\\\'Möchtest du {$user[\\\'username\\\']} an deine Anfrage erinnern?\\\');"><i class="fas fa-bell"></i> </a>
                <a href="usercp.php?action=relations&delete={$entry[\\\'r_id\\\']}" onClick="return confirm(\\\'Möchtest du die Anfrage an {$user[\\\'username\\\']} zurücknehmen?\\\');"><i class="fas fa-times-circle"></i></a>
                
            
        </div>
        
    
    </div>
        ',
        "sid" => "-2",
        "version" => "1.0",
        "dateline" => TIME_NOW
    );


    foreach ($template as $row) {
        $db->insert_query("templates", $row);
    }

    //CSS Hinzufügen
    $css = array(
        'name' => 'relations.css',
        'tid' => 1,
        'attachedto' => '',
        "stylesheet" =>    '
        :root {
            --rela_highlightcolor1: #a02323;
            --rela_highlightcolor1: #a02323;
            --rela_backgroundcolor: #b1b1b1;
            --rela_textcolor: #c5c5c5;
        }
      
        ',
        'cachefile' => $db->escape_string(str_replace('/', '', 'relations.css')),
        'lastmodified' => time()
    );

    require_once MYBB_ADMIN_DIR . "inc/functions_themes.php";

    $sid = $db->insert_query("themestylesheets", $css);
    $db->update_query("themestylesheets", array("cachefile" => "css.php?stylesheet=" . $sid), "sid = '" . $sid . "'", 1);

    $tids = $db->simple_select("themes", "tid");
    while ($theme = $db->fetch_array($tids)) {
        update_theme_stylesheet_list($theme['tid']);
    }
}


/*
 * Funktion relations_profile
 * Anzeige auf Profil des Users & sowie anfragen stellen
 * Anfragen bei einem User
 */
$plugins->add_hook("member_profile_end", "relations_profile");
function relations_profile()
{
    global $db, $mybb, $templates, $memprofile, $relas_memberprofil, $userage, $userjob;
    //Einstellungen bekommen die wir brauchen
    $opt_img_guest = intval($mybb->settings['relas_img_guests']);
    $opt_npc_img = intval($mybb->settings['relas_npc_img']);
    $opt_img_width = intval($mybb->settings['relas_img_width']);
    $opt_toaccept = intval($mybb->settings['relas_alert_confirm']);

    if ($opt_toaccept == 1) {
        $toaccept = "AND r_accepted = 1 ";
    } else {
        $toaccept = "";
    }
    //ein Paar Variablen, die wir häufig benutzten
    $profilid = intval($memprofile['uid']); //auf welchem Profil sind wir
    $thisuser = intval($mybb->user['uid']); //der user der online ist

    $get_cats = $db->simple_select("relas_categories", "*", "c_uid = {$profilid}", array('order_by' => 'c_sort'));
    //Für die Ausgabe brauchen wir erst einmal alle Hauptkategorien
    $relas_memberprofil_cat = "";
    while ($cat = $db->fetch_array($get_cats)) {
        $cid = $cat['c_id'];
        $get_subcats = $db->simple_select("relas_subcategories", "*", "sc_cid={$cid}", array('order_by' => 'sc_sort'));
        $c_name = $cat['c_name'];
        //Dann alle Unterkategorien der Hauptkategorie
        $relas_memberprofil_subcat = "";
        $entrycnt = 0;
        while ($subcat = $db->fetch_array($get_subcats)) {
            //die id der subkategorie
            $scid = $subcat['sc_id'];
            $sc_name = $subcat['sc_name'];
            //dazu dann alle einträge des users auf dessen profil wir sind
            $get_entries = $db->simple_select("relas_entries", "*", "r_from = {$profilid} AND r_kategorie={$scid} {$toaccept}", array('order_by' => 'r_sort'));
            $relas_memberprofil_entrybit = "";
            //die id der subkategorie
            $scid = $subcat['sc_id'];
            $sc_name = $subcat['sc_name'];
            //ausgabe template subcat
            if ($db->num_rows($get_entries) > 1) {
                $style_sub = 'style="
                                grid-column: 1 / -1;
                                display: flex;
                                flex-wrap: wrap;
                                justify-content: space-between;"';
                $style_item = ' style="width: 280px;"';
            }
            while ($entry = $db->fetch_array($get_entries)) {
                $entrycnt = 1;
                //handelt es sich bei dem eintrag um einen npc?
                if ($entry['r_npc'] == 1) {
                    $userage = "";
                    $userjob = "";
                    //dürfen bilder für npcs verwendet werden
                    if ($opt_npc_img == 1) {
                        //dürfen gäste bilder sehen
                        if ($thisuser == 0 && $opt_img_guest == 0) {
                            $rela_avatar = "<div class=\"entry__item avarund\"><i class=\"fa-solid fa-circle-user\"></i></div>";
                        } else {
                            //ausgabe des bilds
                            $rela_avatar = "<div class=\"entry__item avarund\" style=\"background-image: url('{$entry['r_npcimg']}');\"></div>";
                        }
                    } else {
                        //bilder dürfen nicht verwendet werden
                        // $rela_avatar = "";
                        $rela_avatar = "<div class=\"entry__item avarund\"><i class=\"fa-solid fa-circle-user\"></i></div>";
                    }
                    //name des npcs (wir wollen keinen link)
                    $rela_name = $entry['r_npcname'];
                } else { // kein npc, sondern existierender user
                    //die daten des freunds
                    $userage = relations_getage($entry['r_to']);
                    $friend = get_user($entry['r_to']);
                    if ($friend['uid'] == "") $friend['uid'] = -1;
                    // $get_job = $db->write_query("SELECT je_uid, je_position,js_title, je_abteilung, je_profilstring FROM `" . TABLE_PREFIX . "jl_entry` LEFT JOIN mybb_jl_subcat ON je_jsid = js_id LEFT JOIN mybb_jl_maincat ON js_mid = jm_id WHERE je_uid = {$friend['uid']}");
                    // $userjob = "";
                    // $cnt_job = 0;
                    // while ($job = $db->fetch_array($get_job)) {
                    //     $cnt_job++;
                    //     if ($cnt_job >= 1) {
                    //         $space = "<br/>";
                    //     } else {
                    //         $space = "";
                    //     }
                    //     if ($job['je_profilstring'] == "") {
                    //         $userjob .= "{$job['je_abteilung']} ({$job['js_title']})" . $space;
                    //     } else {
                    //         $userjob .= "{$job['je_profilstring']}" . $space;
                    //     }
                    // }

                    // dürfen gäste avatare sehen
                    if ($thisuser == 0 && $opt_img_guest == 0) {
                        $rela_avatar = "";
                    } else {
                        //ausgabe bild
                        $rela_avatar = "<div class=\"entry__item avarund\" style=\"background-image: url('{$friend['avatar']}');\"></div>";
                    }
                    //Existierender user -> wir wollen zum Profil verlinken
                    $rela_name = build_profile_link($friend['username'], $friend['uid']);
                }
                //Wenn eine beschreibung der rela vorhanden ist
                if ($entry['r_kommentar'] != "") {
                    $rela_descr = "<div class=\"entry__item\">{$entry['r_kommentar']}</div>";
                } else {
                    $rela_descr = "";
                }

                //ausgabe template des eintrags
                eval("\$relas_memberprofil_entrybit .= \"" . $templates->get("relas_memberprofil_entrybit") . "\";");
            }
            if ($db->num_rows($get_entries) > 0) {
                eval("\$relas_memberprofil_subcat .= \"" . $templates->get("relas_memberprofil_subcat") . "\";");
            }
        }

        //ausgabe template Hauptkategorie
        if ($entrycnt > 0) {
            eval("\$relas_memberprofil_cat .= \"" . $templates->get("relas_memberprofil_cat") . "\";");
        }
        $entrycnt = 0;
    }


    //ausgabe formular für anfrage 
    //nur wenn kein Gast (Gäste dürfen keine anfragen stellen) und wenn nicht auf dem eigenen Profil
    if ($thisuser != 0 && $thisuser != $profilid) {
        //select bauen für anfragen formular
        //Hiermit stellen wir fest, ob der anfragende überhaupt schon kategorien hat.
        $form_select = relations_getCats($thisuser);
        if ($form_select != "false") {
            eval("\$relas_memberprofil_anfrage = \"" . $templates->get("relas_memberprofil_anfrage") . "\";");
        } else {
            $relas_memberprofil_anfrage = "Du hast noch keine Kategorien in deinem Profil angelegt. Bitte tu dies zuerst. Dann kannst du Anfragen an andere Charaktere schicken.";
        }
    }
    //ausgabe template gesamt mem
    eval("\$relas_memberprofil = \"" . $templates->get("relas_memberprofil") . "\";");

    //Das Behandeln der Anfrage
    //Button anfragen wurde gedrückt (und kein Gast):
    if ($mybb->input['anfragen'] && $mybb->user['uid'] != 0) {
        //Wir stellen ein Array mit den eingegeben Daten zusammen
        $rela_anfrage = array(
            "r_from" => $mybb->get_input('getfrom', MyBB::INPUT_INT),
            "r_to" => $mybb->get_input('getto', MyBB::INPUT_INT),
            "r_kategorie" => $db->escape_string($mybb->get_input('kategorie', MyBB::INPUT_STRING)),
            "r_kommentar" => $db->escape_string($mybb->get_input('descr', MyBB::INPUT_STRING)),
            "r_sort" => $mybb->get_input('sort', MyBB::INPUT_INT),
            "r_accepted" => 0,
            "r_npc" => 0
        );
        $db->insert_query("relas_entries", $rela_anfrage);

        if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
            $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('relation_request');
            if ($alertType != NULL && $alertType->getEnabled()) {
                //constructor for MyAlert gets first argument, $user (not sure), second: type  and third the objectId 
                $alert = new MybbStuff_MyAlerts_Entity_Alert($mybb->get_input('getto', MyBB::INPUT_INT), $alertType);
                //some extra details
                $alert->setExtraDetails([
                    'fromuser' => $mybb->user['uid']
                ]);
                //add the alert
                MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
            }
        }
        redirect('member.php?action=profile&uid=' . $mybb->get_input('getto', MyBB::INPUT_INT));
    }
}


/*
 * Fügt die Verwaltung der Relas ins user CP ein
 */
$plugins->add_hook("usercp_menu", "relas_usercp_menu", 40);
function relas_usercp_menu()
{
    global $usercpmenu, $lang;
    $lang->load("relations");
    $usercpmenurela = "<tr><td class=\"trow1 smalltext\"><a href=\"./usercp.php?action=relations\" class=\"usercp_nav_item usercp_nav_mecool\">{$lang->relations_ucpnav}</a></td></tr>";
}

/*
 * Verwaltung der Relations im User CP
 * Kategorien verwalten
 * NPCs hinzufügen
 * Akzeptieren/Ablehnen von Beziehungen
 * Einen anderen Charakter erinnern
 * Löschen und ändern */
$plugins->add_hook("usercp_start", "relas_usercp");
function relas_usercp()
{
    global $mybb, $db, $templates, $anfragen, $header, $relas_ucp_alluser, $themes, $headerinclude, $header, $footer, $usercpnav, $relas_ucp, $relas_ucp_toaccept, $relas_ucp_notadded;

    if ($mybb->input['action'] != "relations") {
        return false;
    }
    //Einstellungen
    $opt_npc = intval($mybb->settings['relas_npc']);
    $opt_npc_img = intval($mybb->settings['relas_npc_img']);
    $opt_img_width = intval($mybb->settings['relas_img_width']);
    //müssen Relas akzeptiert werden? 
    $opt_toaccept = intval($mybb->settings['relas_alert_confirm']);
    if ($opt_toaccept == 1) {
        $toaccept = "AND r_accepted = 1 ";
    } else {
        $toaccept = "";
    }
    // die user id des users
    $thisuser = intval($mybb->user['uid']);

    //HauptKategorien bekommen
    $get_cats = $db->simple_select("relas_categories", "*", "c_uid = {$thisuser}", array('order_by' => 'c_sort'));
    $get_catsforselect = $db->simple_select("relas_categories", "*", "c_uid = {$thisuser}", array('order_by' => 'c_sort'));

    //Standardkategorien frontend
    $get_catstest = $db->simple_select("relas_categories", "*", "c_uid = {$thisuser}", array('order_by' => 'c_sort'));

    if ($db->num_rows($get_catstest) <= 0) {
        $dostandardcats = "<input form=\"catstandard\" name=\"standardcat\" type=\"submit\" value=\"Erstellen\" onClick=\"return confirm('Standardkategorien anlegen? Achtung: Wenn du schon welche erstellt hast, werden sie zusätzlich hinzugefügt.');\"/>";
    } else {
        $dostandardcats = "<span class=\"alert_warn\">Achtung!</span><br />
                Du hast schon eigene Kategorien, wenn du den Button drückst, werden die Standardkategorien <b>zusätzlich</b> hinzugefügt.<br />
                <input form=\"catstandard\" name=\"standardcat\" type=\"submit\" value=\"Erstellen\" onClick=\"return confirm('Standardkategorien anlegen? Achtung: Wenn du schon welche erstellt hast, werden sie zusätzlich hinzugefügt.');\"/>";
    }

    //template zurücksetzen
    $relas_ucp_managecat = "";
    $savecats = "";
    //Select mit Haupt und Unterkategorien
    $cats_npc = relations_getCats($thisuser);
    if ($cats_npc == "false") {
        $cats_npc = "<b>Lege erst Kategorien an!</b>";
    }
    //Select für Hauptkategorien bauen
    $hauptkategorie = "<select name=\"cat\" required>";
    while ($cat = $db->fetch_array($get_catsforselect)) {

        $hauptkategorie .= "<option value=\"{$cat['c_id']}\">{$cat['c_name']}</option>";
    }
    $hauptkategorie .= "</select>"; //abschließen
    //Hauptkategorien durchgehen für Verwaltung dieser

    while ($cat = $db->fetch_array($get_cats)) {
        //IDs
        $cid = $cat['c_id'];
        //namen
        $c_name = $cat['c_name'];
        //Unterkategorien bekommen
        $get_subcats = $db->simple_select("relas_subcategories", "*", "sc_cid={$cid}", array('order_by' => 'sc_sort'));
        //Template zurücksetzen
        $relas_ucp_managesubcat = "";
        //edit link bauen (Popup anzeigen bei klick)
        $editcatmod = "<a onclick=\"$('#cedit{$cid}').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== 'undefined' ? modal_zindex : 9999) }); return false;\" style=\"cursor: pointer;\">[e]</a>";
        //Dazu gehörende Unterkategorien durchgehen
        while ($subcat = $db->fetch_array($get_subcats)) {

            $sc_name = $subcat['sc_name'];
            $scid = $subcat['sc_id'];
            $sccid = $subcat['sc_cid'];
            $hauptkategoriesub = "<select name=\"cat\" required>";
            $get_catsforselect = $db->simple_select("relas_categories", "*", "c_uid = {$thisuser}", array('order_by' => 'c_sort'));

            while ($cat_haupt = $db->fetch_array($get_catsforselect)) {
                if ($cat_haupt['c_id'] == $cid) {
                    $selectedsub = "selected";
                } else {
                    $selectedsub = "";
                }
                $hauptkategoriesub .= "<option value=\"{$cat_haupt['c_id']}\" {$selectedsub}>{$cat_haupt['c_name']}</option>";
            }
            $hauptkategoriesub .= "</select>"; //abschließen

            //edit link bauen (Popup anzeigen bei link)
            $editscatmod = "<a onclick=\"$('#scedit{$scid}').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== 'undefined' ? modal_zindex : 9999) }); return false;\" style=\"cursor: pointer;\">[e]</a>";
            eval("\$relas_ucp_managesubcat .= \"" . $templates->get("relas_ucp_managesubcat") . "\";");
        }
        $hauptkategoriesub = "";

        eval("\$relas_ucp_managecat .= \"" . $templates->get("relas_ucp_managecat") . "\";");
    }

    //Verarbeitung der Formulardaten 
    //Standardkategorien erstelle
    if ($mybb->input['standardcat']) {
        $standards = $mybb->settings['standardcat'];

        /*HIER DIE DEFAULT KATEGORIEN ÄNDERN*/
        $standard = array(
            "family" => "mom,dad,",
            "friends" => "best friends,good friends,friends",
            "known" => "like,neutral,dislike",
            "love" => "relationship,kissed,flirt,ons,affair",
            "hate" => "dislike,hate",
            "other" => "past relations,past friendships"
        );

        $cnt = 0;
        foreach ($standard as $cat => $subcats) {
            $cnt = $cnt + 1;
            $entersubs = explode(",", $subcats);
            $insert = array(
                "c_name" => $cat,
                "c_sort" => $cnt,
                "c_uid" => $thisuser
            );
            $db->insert_query('relas_categories', $insert);
            $cid = $db->insert_id();

            $cnt_sub = 0;
            foreach ($entersubs as $subcat) {
                $cnt_sub = $cnt_sub + 1;
                $insert_sub = array(
                    "sc_name" => $subcat,
                    "sc_sort" =>  $cnt_sub,
                    "sc_cid" =>  $cid,
                    "sc_uid" => $thisuser
                );
                $db->insert_query('relas_subcategories', $insert_sub);
            }
        }
        redirect('usercp.php?action=relations');
    }
    //Neue Kategorie erstellen
    if ($mybb->input['newcat']) {
        $insert = array(
            "c_name" => $db->escape_string($mybb->get_input('addMain', MyBB::INPUT_STRING)),
            "c_sort" => $mybb->get_input('addMainSort', MyBB::INPUT_INT),
            "c_uid" => $thisuser
        );
        $db->insert_query('relas_categories', $insert);
        redirect('usercp.php?action=relations');
    }
    // Neue Unterkategorie erstellen
    if ($mybb->input['newsubcat']) {
        $insert = array(
            "sc_name" => $db->escape_string($mybb->get_input('addSub', MyBB::INPUT_STRING)),
            "sc_sort" => $mybb->get_input('addSubSort', MyBB::INPUT_INT),
            "sc_cid" => $mybb->get_input('cat', MyBB::INPUT_INT),
            "sc_uid" => $thisuser
        );
        $db->insert_query('relas_subcategories', $insert);
        redirect('usercp.php?action=relations');
    }

    //Hauptkategorie ändern
    if ($mybb->input['editcat']) {
        $update = array(
            "c_name" => $db->escape_string($mybb->get_input('c_name_e', MyBB::INPUT_STRING)),
            "c_sort" => $mybb->get_input('c_sort_e', MyBB::INPUT_INT),
            "c_uid" => $thisuser
        );
        $db->update_query('relas_categories', $update, "c_id = {$mybb->get_input('c_cid_e', MyBB::INPUT_INT)}");
        redirect('usercp.php?action=relations');
    }

    //Hauptkategorie löschen
    if ($mybb->input['cat'] == 'delete') {
        //Welche cid
        $cid = $mybb->get_input('cid', MyBB::INPUT_INT);
        //Unterkategorien der Hauptkategorie löschen
        $db->delete_query("relas_subcategories", "sc_cid= {$cid}");
        //Hauptkategorie löschen
        $db->delete_query("relas_categories", "c_id= {$cid}");
        redirect('usercp.php?action=relations');
    }

    //Unterkategorie ändern
    if ($mybb->input['editsubcat']) {
        //id der Unterkategorie
        $scid = $mybb->get_input('sc_edit_id', MyBB::INPUT_INT);
        //array mit werten der inputs bauen
        $update = array(
            "sc_name" => $db->escape_string($mybb->get_input('sc_edit_name', MyBB::INPUT_STRING)),
            "sc_sort" => $mybb->get_input('sc_edit_sort', MyBB::INPUT_INT),
            "sc_cid" => $mybb->get_input('cat', MyBB::INPUT_INT),
            "sc_uid" => $thisuser
        );
        //speichern
        $db->update_query('relas_subcategories', $update, "sc_id = {$scid}");
        redirect('usercp.php?action=relations');
    }

    //Subkategorie löschen
    if ($mybb->input['scat'] == 'delete') {

        //welche scid
        $scid = $mybb->get_input('scid', MyBB::INPUT_INT);
        //dazugehörige Unterkategorien löschen
        $db->delete_query("relas_subcategories", "sc_id= {$scid}");
        redirect('usercp.php?action=relations');
    }

    // NPC hinzufügen    
    if ($mybb->input['addnpc']) {
        if (!checkCats($thisuser)) {
            echo "<script>alert('Du kannst keinen Chara anfragen/hinzufügen, solange du keine Kategorien erstellt hast.')
            window.location = 'usercp.php?action=relations';</script>";
        } else {
            $insert = array(
                "r_to" => 0,
                "r_from" => $thisuser,
                "r_npc" => 1,
                "r_npcname" => $db->escape_string($mybb->get_input('npcname', MyBB::INPUT_STRING)),
                "r_npcimg" => $db->escape_string($mybb->get_input('npcimg', MyBB::INPUT_STRING)),
                "r_kategorie" => $db->escape_string($mybb->get_input('kategorie', MyBB::INPUT_INT)),
                "r_kommentar" => $db->escape_string($mybb->get_input('npcdescr', MyBB::INPUT_STRING)),
                "r_accepted" => 1,
                "r_sort" => $mybb->get_input('addNpcSort', MyBB::INPUT_INT),
            );
            $db->insert_query('relas_entries', $insert);
            redirect('usercp.php?action=relations');
        }
    }

    // Kategorie editieren nach fehler    
    if ($mybb->input['error_edit_cat']) {
        if (!checkCats($thisuser)) {
            echo "<script>alert('Du kannst keinen Chara anfragen/hinzufügen, solange du keine Kategorien erstellt hast.')
                window.location = 'usercp.php?action=relations';</script>";
        } else {
            $update = array(
                "r_kategorie" => $db->escape_string($mybb->get_input('kategorie', MyBB::INPUT_INT)),
            );
            $db->update_query('relas_entries', $update, "r_id = {$mybb->get_input('e_id', MyBB::INPUT_INT)}");
            redirect('usercp.php?action=relations');
        }
    }

    // Ausgabe der offenen Anfragen 
    //Diese müssen vom User noch akzeptiert werden 
    $toaccept = $db->write_query("SELECT * FROM " . TABLE_PREFIX . "relas_entries WHERE r_to = {$thisuser} AND r_accepted = 0");
    while ($entry = $db->fetch_array($toaccept)) {
        //Die Infos des Users bekommen, der die Anfrage geschickt hat.
        $user = get_user($entry['r_from']);
        $username = build_profile_link($user['username'], $user['uid']);
        //Wir wollen die Unterkategorie

        //fehler: es wurde keine Kategorie angegeben
        if ($entry['r_kategorie'] == "") {
            //redirect zur Fehlerseite? 
            $subkategorie = "Du hast keine Kategorie ausgewählt. Bitte erstelle Kategorien und editiere diesen Eintrag.";
        } else {
            $subcat = $db->fetch_array($db->simple_select("relas_subcategories", "*", "sc_id = {$entry['r_kategorie']}"));
            $subkategorie = $subcat['sc_name'];
        }
        //Zeige Kommentar zur Beziehung, wenn vorhanden.
        if ($entry['r_kommentar'] != "") {
            $kommentar = "	<div class=\"ucprelas-requestuser__item kommentar\">{$entry['r_kommentar']} </div>";
        } else {
            $kommentar = "";
        }
        //Welche Hauptkategorie? 
        if ($subcat['sc_cid'] == "") {
            $subcat['sc_cid'] = "0";
        }
        $cats = relations_getCats($thisuser);
        if ($cats == "false") {
            $cats = "<b>Lege erst Kategorien an!</b>";
        }
        $cat = $db->fetch_array($db->simple_select("relas_categories", "*",  "c_id = {$subcat['sc_cid']}"));
        $haupt = $cat['c_name'];
        eval("\$relas_ucp_toaccept .= \"" . $templates->get("relas_ucp_toaccept") . "\";");
    }

    //Diese Anfragen wurden von dem User an andere Charas gestellt und wurden noch nicht bestätigt
    $waiting = $db->write_query("SELECT * FROM " . TABLE_PREFIX . "relas_entries WHERE r_from = {$thisuser} AND r_accepted = 0");
    $cats = "";
    while ($entry = $db->fetch_array($waiting)) {
        //Die Infos des Users bekommen, an den die Anfrage geschickt wurde.
        $user = get_user($entry['r_to']);
        $username = build_profile_link($user['username'], $user['uid']);
        //Wir wollen die Unterkategorie
        //fehler: es wurde keine Kategorie angegeben
        if ($entry['r_kategorie'] == "") {
            //redirect zur Fehlerseite? 
            $cats = relations_getCats($thisuser);
            if ($cats == "false") {
                $cats = "<b>Lege erst Kategorien an!</b>";
            }
            $subkategorie = "<form action=\"usercp.php?action=relations\" method=\"post\">
            Du hast keine Kategorie ausgewählt. Bitte erstelle erst Kategorien und editiere diesen Eintrag.<br/>{$cats}<br/>
            <input type=\"hidden\" name=\"e_id\"/ value=\"{$entry['r_id']}\">
            <input type=\"submit\" name=\"error_edit_cat\"/ value=\"speichern\"> </form>";
        } else {
            $subcat = $db->fetch_array($db->simple_select("relas_subcategories", "*", "sc_id = {$entry['r_kategorie']}"));
            $subkategorie = $subcat['sc_name'];
        }
        //Zeige Kommentar zur Beziehung, wenn vorhanden.
        if ($entry['r_kommentar'] != "") {
            $kommentar = "	<div class=\"ucprelas-requestuser__item kommentar\">{$entry['r_kommentar']} </div>";
        } else {
            $kommentar = "";
        }
        //Welche Hauptkategorie? 
        if ($subcat['sc_cid'] == "") {
            $subcat['sc_cid'] = 0;
        }
        $cat = $db->fetch_array($db->simple_select("relas_categories", "*",  "c_id = {$subcat['sc_cid']}"));
        $haupt = $cat['c_name'];
        eval("\$relas_ucp_waiting .= \"" . $templates->get("relas_ucp_waiting") . "\";");
    }

    //Haben dich eingetragen aber du sie nicht
    $not_added = $db->write_query("SELECT * FROM mybb_relas_entries WHERE r_to = {$thisuser} AND r_accepted = 1 AND r_from not in (SELECT r_to FROM mybb_relas_entries WHERE r_from =  {$thisuser})");
    if ($db->num_rows($not_added) > 0) {
        $relas_ucp_notaddedbit = "";
        while ($notadd = $db->fetch_array($not_added)) {
            $user = get_user($notadd['r_from']);
            $username = build_profile_link($user['username'], $user['uid']);
            //Wir wollen die Unterkategorie
            $subcat_add = $db->fetch_array($db->simple_select("relas_subcategories", "*", "sc_id = {$notadd['r_kategorie']}"));
            $subkategorie = $subcat_add['sc_name'];
            //Zeige Kommentar zur Beziehung, wenn vorhanden.
            if ($notadd['r_kommentar'] != "") {
                $kommentar = "	<div class=\"ucprelas-requestuser__item kommentar\">{$notadd['r_kommentar']} </div>";
            } else {
                $kommentar = "";
            }
            //Welche Hauptkategorie? 
            $cats = relations_getCats($thisuser);
            if ($cats == "false") {
                $cats = "<b>Keine Kategorie angegeben.</b>";
            }
            //Welche Hauptkategorie? 
            if ($subcat_add['sc_cid'] == "") {
                $subcat_add['sc_cid'] = 0;
            }
            $cat_add = $db->fetch_array($db->simple_select("relas_categories", "*",  "c_id = {$subcat_add['sc_cid']}"));
            $haupt = $cat_add['c_name'];

            eval("\$relas_ucp_notaddedbit .= \"" . $templates->get("relas_ucp_notaddedbit") . "\";");
        }
        eval("\$relas_ucp_notadded = \"" . $templates->get("relas_ucp_notadded") . "\";");
    }


    //Akzeptieren
    if ($mybb->input['accept'] == 1) {
        //input
        $update = array(
            "r_accepted" => 1,
        );
        $rid = $mybb->get_input('rid', MyBB::INPUT_INT);
        $touid = $db->fetch_field($db->simple_select("relas_entries", "r_from", "r_id = {$rid}"), "r_from");
        //Alert losschicken
        if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
            $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('relation_confirm');
            if ($alertType != NULL && $alertType->getEnabled()) {
                //constructor for MyAlert gets first argument, $user (not sure), second: type  and third the objectId 
                $alert = new MybbStuff_MyAlerts_Entity_Alert($touid, $alertType);
                //some extra details
                $alert->setExtraDetails([
                    'fromuser' => $mybb->user['uid']
                ]);
                //add the alert
                MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
            }
        }

        //speichern
        $db->update_query('relas_entries', $update, "r_id = {$rid}");
        redirect('usercp.php?action=relations');
    }

    //Ablehnen
    if ($mybb->input['deny'] == 1) {
        //id der Unterkategorie
        $rid = $mybb->get_input('rid', MyBB::INPUT_INT);

        $touid = $db->fetch_field($db->simple_select("relas_entries", "r_from", "r_id = {$rid}"), "r_from");
        //Alert losschicken
        if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
            $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('relation_confirm');
            if ($alertType != NULL && $alertType->getEnabled()) {
                //constructor for MyAlert gets first argument, $user (not sure), second: type  and third the objectId 
                $alert = new MybbStuff_MyAlerts_Entity_Alert($touid, $alertType);
                //some extra details
                $alert->setExtraDetails([
                    'fromuser' => $mybb->user['uid']
                ]);
                //add the alert
                MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
            }
        }

        $db->delete_query('relas_entries', "r_id = {$rid}");
        redirect('usercp.php?action=relations');
    }

    //rela hinzufügen - bei bestäigung
    if ($mybb->input['addrela']) {
        if (!checkCats($thisuser)) {
            echo "<script>alert('Du kannst keinen Chara anfragen/hinzufügen, solange du keine Kategorien erstellt hast.')
            window.location = 'usercp.php?action=relations';</script>";
        } else {
            //id der Unterkategorie
            $rela_anfrage = array(
                "r_from" => $thisuser,
                "r_to" => $mybb->get_input('r_from', MyBB::INPUT_INT),
                "r_kategorie" => $db->escape_string($mybb->get_input('kategorie', MyBB::INPUT_STRING)),
                "r_kommentar" => $db->escape_string($mybb->get_input('adddescr', MyBB::INPUT_STRING)),
                "r_sort" => $mybb->get_input('addSort', MyBB::INPUT_INT),
                "r_accepted" => 0,
                "r_npc" => 0
            );
            $db->insert_query("relas_entries", $rela_anfrage);

            if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
                $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('relation_request');
                if ($alertType != NULL && $alertType->getEnabled()) {
                    //constructor for MyAlert gets first argument, $user (not sure), second: type  and third the objectId 
                    $alert = new MybbStuff_MyAlerts_Entity_Alert($mybb->get_input('r_from', MyBB::INPUT_INT), $alertType);
                    //some extra details
                    $alert->setExtraDetails([
                        'fromuser' => $mybb->user['uid']
                    ]);
                    //add the alert
                    MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
                }
            }
            redirect('usercp.php?action=relations');
        }
    }

    //rela hinzufügen - einen charakter übers profil hinzufügen
    if ($mybb->input['addachar_ucp']) {
        //id der Unterkategorie
        if (!checkCats($thisuser)) {
            echo "<script>alert('Du kannst keinen Chara anfragen/hinzufügen, solange du keine Kategorien erstellt hast.')
            window.location = 'usercp.php?action=relations';</script>";
        } else {
            $name = $mybb->get_input('addname', MyBB::INPUT_STRING);
            $query = $db->simple_select("users", "*", "username='{$name}'");
            $uid = $db->fetch_field($query, "uid");

            $rela_anfrage = array(
                "r_from" => $thisuser,
                "r_to" => $uid,
                "r_kategorie" => $db->escape_string($mybb->get_input('kategorie', MyBB::INPUT_STRING)),
                "r_kommentar" => $db->escape_string($mybb->get_input('addescr', MyBB::INPUT_STRING)),
                "r_sort" => $mybb->get_input('addsort', MyBB::INPUT_INT),
                "r_accepted" => 0,
                "r_npc" => 0
            );
            $db->insert_query("relas_entries", $rela_anfrage);

            if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
                $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('relation_request');
                if ($alertType != NULL && $alertType->getEnabled()) {
                    //constructor for MyAlert gets first argument, $user (not sure), second: type  and third the objectId 
                    $alert = new MybbStuff_MyAlerts_Entity_Alert($uid, $alertType);
                    //some extra details
                    $alert->setExtraDetails([
                        'fromuser' => $mybb->user['uid']
                    ]);
                    //add the alert
                    MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
                }
            }
            redirect('usercp.php?action=relations');
        }
    }

    //eintrag zurücknehmen
    if ($mybb->input['delete']) {
        //id der Unterkategorie
        $rid = $mybb->get_input('delete', MyBB::INPUT_INT);

        $db->delete_query('relas_entries', "r_id = {$rid}");
        redirect('usercp.php?action=relations');
    }

    //Unterkategorie ändern
    if ($mybb->input['reminder']) {
        //id der Unterkategorie
        $touid = $mybb->get_input('reminder', MyBB::INPUT_INT);

        //Alert losschicken
        if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
            $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('relation_reminder');
            if ($alertType != NULL && $alertType->getEnabled()) {
                //constructor for MyAlert gets first argument, $user (not sure), second: type  and third the objectId 
                $alert = new MybbStuff_MyAlerts_Entity_Alert($touid, $alertType);
                //some extra details
                $alert->setExtraDetails([
                    'fromuser' => $mybb->user['uid']
                ]);
                //add the alert
                MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
            }
        }
    }

    $kommentar = "";
    /****
     * Die Ausgabe der angenommen Relations
     ***/
    //Brauchen wir um das erste Tab anzeigen zu lassen
    $first = "0";
    //Wir holen uns erst alle Hauptkategorien des Users.
    $get_cats_output = $db->simple_select("relas_categories", "*", "c_uid = {$thisuser}", array('order_by' => 'c_sort'));

    while ($all = $db->fetch_array($get_cats_output)) {
        //Jetzt alle UNterkategorien der Hauptkategorie
        $subcat_all = $db->simple_select("relas_subcategories", "*", "sc_cid = {$all['c_id']}");
        $relas_ucp_subcats = "";

        while ($allsubs = $db->fetch_array($subcat_all)) {
            //Dazu alle Relaeinträge holen
            $get_accepted = $db->write_query("SELECT * FROM " . TABLE_PREFIX . "relas_entries WHERE r_from = {$thisuser} AND r_accepted = 1 AND r_kategorie = {$allsubs['sc_id']} ");
            $relas_ucp_alluser = "";
            relations_getuserucp($get_accepted, $all, $allsubs);

            eval("\$relas_ucp_subcats .= \"" . $templates->get("relas_ucp_subcats") . "\";");
        }

        $rela_type = $all['c_id'];
        //Damit wir wissen was das erste Tab ist und dieses angezeigt wird beim laden der seite
        $first++;
        if ($first == 1) {
            $tabbuttonid = "but_tabdefault";
            $defaultTab = false;
        } else {
            $tabbuttonid = "but_" . $all['c_id'];
        }
        eval("\$relas_ucp_cats .= \"" . $templates->get("relas_ucp_cats") . "\";");
        eval("\$relas_ucp_tablinks .= \"" . $templates->get("relas_ucp_tablinks") . "\";");
    }

    //Hier wollen wir die Einträge sammeln, die keine Kategorie haben.
    $get_nocats = $db->write_query("SELECT * FROM " . TABLE_PREFIX . "relas_entries WHERE  r_kategorie NOT IN (SELECT sc_id FROM " . TABLE_PREFIX . "relas_subcategories) AND r_from = {$thisuser}");
    if ($db->num_rows($get_nocats) > 0) {
        $all['c_id'] = 0;
        $tabbuttonid = "but_" . $all['c_id'];
        $all['c_name'] = "Keine Kategorie";
        $rela_type = $all['c_id'];
        eval("\$relas_ucp_tablinks .= \"" . $templates->get("relas_ucp_tablinks") . "\";");

        $leer = array();
        $relas_ucp_alluser = "";
        relations_getuserucp($get_nocats, $all, $leer);
        $no_cat = "<p>Für diese Einträge gibt es keine Kategorie, sie werden nicht im Profil angezeigt. Bitte editiere sie und trage eine Kategorie ein.</p>";
        eval("\$relas_ucp_subcats = \"" . $templates->get("relas_ucp_subcats") . "\";");
        eval("\$relas_ucp_cats .= \"" . $templates->get("relas_ucp_cats") . "\";");
    }



    //rela editieren 
    if ($mybb->input['e_rela']) {
        //id der Unterkategorie
        $rid = $mybb->get_input('e_rela_id', MyBB::INPUT_INT);
        $rela_anfrage = array(
            "r_npcname" => $db->escape_string($mybb->get_input('e_rela_npcname', MyBB::INPUT_STRING)),
            "r_npcimg" => $db->escape_string($mybb->get_input('e_rela_npcimg', MyBB::INPUT_STRING)),
            "r_kategorie" => $db->escape_string($mybb->get_input('kategorie', MyBB::INPUT_STRING)),
            "r_kommentar" => $db->escape_string($mybb->get_input('e_rela_kom', MyBB::INPUT_STRING)),
            "r_sort" => $mybb->get_input('e_rela_sort', MyBB::INPUT_INT),
        );
        $db->update_query("relas_entries", $rela_anfrage, "r_id = {$rid}");

        redirect('usercp.php?action=relations');
    }

    //rela löschen
    if ($mybb->input['accepted'] == 'delete') {
        //usercp.php?action=relations&accepted=delete&rid=7
        $rid = $mybb->get_input('rid', MyBB::INPUT_INT);
        $db->delete_query("relas_entries", "r_id = {$rid}");
        //TODO ALERT
        redirect('usercp.php?action=relations');
    }

    eval("\$relas_ucp = \"" . $templates->get("relas_ucp") . "\";");
    output_page($relas_ucp);
}

function relations_getuserucp($query, $all, $allsubs)
{
    global $db, $templates, $mybb, $relas_ucp_alluser, $cats;
    $thisuser = $mybb->user['uid'];
    //Einstellungen
    $opt_npc = intval($mybb->settings['relas_npc']);
    $opt_npc_img = intval($mybb->settings['relas_npc_img']);
    $opt_img_width = intval($mybb->settings['relas_img_width']);

    while ($relauser = $db->fetch_array($query)) {

        // eval("\$relas_ucp_alluser .= \"" . $templates->get("relas_ucp_alluser") . "\";");
        //Welche id hat der eintrag?
        $rid = $relauser['r_id'];
        //NPCs weerden beim edit anders behandelt (man kann den namen und das bild ändern)
        if ($relauser['r_npc'] == 1) {
            $usernamenolink = $relauser['r_npcname'];
            $username = $relauser['r_npcname'];
            $editname = " <div class =\"model-form\">
                      <label for=\"e_rela_npcname\">NPC Name</label>
                      <input type=\"text\" value=\"{$username}\" name=\"e_rela_npcname\" required>
                      </div>";
            //Wenn Bilder für npcs erlaubt:
            if ($opt_npc_img == 1) {
                $userimg = $relauser['r_npcimg'];
                $npc_editimg = " <div class =\"model-form\">
                          <label for=\"e_rela_npcimg\">NPC Img</label>
                          <input type=\"text\" value=\"{$userimg}\" name=\"e_rela_npcimg\">
                          </div>";
            } else {
                $userimg = "";
                $npc_editimg = "";
            }
        } else {
            // echo "in funktion";
            //kein NPC 
            $npc_editimg = "";
            $user = get_user($relauser['r_to']);
            //username als link
            $usernamenolink = $user['username'];
            $username = build_profile_link($user['username'], $user['uid']);
            $userimg = $user['avatar'];
            $editname = $username;
        }
        // $selected ="";
        $haupt = $all['c_name'];
        $subkategorie = $allsubs['sc_name'];
        $kommentar = "<div class=\"ucprelas-user__item kommentar\">" . ($relauser['r_kommentar']) . "</div>";

        $get_cats = $db->simple_select("relas_categories", "*", "c_uid = {$thisuser}");
        $cats = "<select name=\"kategorie\" size=\"3\" id=\"kategorien\" required>";
        while ($cat = $db->fetch_array($get_cats)) {
            $cats .= "<optgroup label=\"{$cat['c_name']}\">";
            $get_subcats = $db->simple_select("relas_subcategories", "*", "sc_cid={$cat['c_id']}");
            while ($subcat = $db->fetch_array($get_subcats)) {
                if ($subcat['sc_id'] == $relauser['r_kategorie']) {
                    $selected = "SELECTED";
                } else {
                    $selected = "";
                }
                $cats .= "<option value=\"{$subcat['sc_id']}\" {$selected}>{$subcat['sc_name']}</option>";
            }
        }
        $cats .= "</select>";
        // return $templates->get("relas_ucp_alluser");
        eval("\$relas_ucp_alluser .= \"" . $templates->get("relas_ucp_alluser") . "\";");
    }
}
/**
 * Hilfsfunktion Kategorien Select bauen
 */
function relations_getCats($uid)
{
    global $db;
    //Hiermit stellen wir fest, ob der anfragende überhaupt schon kategorien hat.
    $get_cats = $db->simple_select("relas_categories", "*", "c_uid = {$uid}");
    $test_cat = $db->num_rows($get_cats);
    if ($test_cat > 0) {
        //Vorbereitung für das Formular - Wir brauchen ein Select mit allen Haupt-und Unterkategorien
        $form_select = "<select name=\"kategorie\" size=\"3\" id=\"kategorien\" required>";
        //Die Hauptkategorien durchgehen
        while ($cat = $db->fetch_array($get_cats)) {
            //Wir wollen das Select etwas gruppieren
            $form_select .= "<optgroup label=\"{$cat['c_name']}\">";
            //Die dazugehörigen Subkategorien
            $get_subcats = $db->simple_select("relas_subcategories", "*", "sc_cid={$cat['c_id']}");
            while ($subcat = $db->fetch_array($get_subcats)) {
                $form_select .= "<option value=\"{$subcat['sc_id']}\">{$subcat['sc_name']}</option>";
            }
        }
        $form_select .= "</select>";
    } else {
        $form_select = "false";
    }
    return $form_select;
}

function checkCats($uid)
{
    global $db;
    $get_cats = $db->simple_select("relas_categories", "*", "c_uid = {$uid}");
    $get_subcats = $db->simple_select("relas_subcategories", "*", "sc_uid = {$uid}");
    if ($db->num_rows($get_cats) == 0 || $db->num_rows($get_subcats) == 0) {

        return false;
    }

    return true;
}
/*
 *  Verwaltung der Standardcats im Tool Menü des ACP hinzufügen
 *  freien index finden
 */
// $plugins->add_hook("admin_tools_menu", "relationstools_menu");
// function relationstools_menu(&$sub_menu)
// {
//     $key = count($sub_menu) * 10 + 10; /* We need a unique key here so this works well. */
//     $sub_menu[$key] = array(
//         'id'    => 'relations',
//         'title'    => 'Relations Verwaltung',
//         'link'    => 'index.php?module=tools-relations'
//     );
//     return $sub_menu;
// }

// $plugins->add_hook("admin_tools_action_handler", "relationstools_action_handler");
// function relationstools_action_handler($actions)
// {
//     $actions['relations'] = array('active' => 'relations', 'file' => 'relations.php');
//     return $actions;
// }

/**
 * Was passiert wenn ein User gelöscht wird
 * Relas bei anderen zu npc umtragen
 * die relas des users löschen
 */
$plugins->add_hook("admin_user_users_delete_commit_end", "user_delete");
function user_delete()
{
    global $db, $cache, $mybb, $user;
    $todelete = (int)$user['uid'];
    $username = $db->escape_string($user['username']);
    $update_other_relas = array(
        'r_to' => 0,
        'r_npc' => 1,
        'r_npcname' => $username
    );

    $db->update_query('relas_entries', $update_other_relas, "r_to='" . (int)$user['uid'] . "'");
    $db->delete_query('relas_entries', "r_from = " . (int)$user['uid'] . "");
    $db->delete_query('relas_categories', "c_uid = " . (int)$user['uid'] . "");
    $db->delete_query('relas_subcategories', "sc_uid = " . (int)$user['uid'] . "");

    // add_task_log($task, "Reservierungen bereinigt uid war {$user['uid']} {$username}");
}


/**************************** 
 * 
 *  My Alert Integration
 * 
 * *************************** */
if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
    $plugins->add_hook("global_start", "relations_alert");
}

function relations_alert()
{
    global $mybb, $lang;
    $lang->load('relations');

    /**
     * We need our MyAlert Formatter
     * Alert Formater for Reminder
     */
    class MybbStuff_MyAlerts_Formatter_RelationReminderFormatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
    {
        /**
         * Build the output string for listing page and the popup.
         * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to format.
         * @return string The formatted alert string.
         */
        public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert)
        {
            $alertContent = $alert->getExtraDetails();
            return $this->lang->sprintf(
                $this->lang->relation_reminder,
                $outputAlert['from_user'],
                $alertContent['uid'],
                $outputAlert['dateline']
            );
        }
        /**
         * Initialize the language, we need the variables $l['myalerts_setting_alertname'] for user cp! 
         * and if need initialize other stuff
         * @return void
         */
        public function init()
        {
            if (!$this->lang->relations) {
                $this->lang->load('relations');
            }
        }
        /**
         * We want to define where we want to link to. 
         * @param MybbStuff_MyAlerts_Entity_Alert $alert for which alert.
         * @return string return the link.
         */
        public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert)
        {
            $alertContent = $alert->getExtraDetails();
            return $this->mybb->settings['bburl'] . '/usercp.php?action=relations';
        }
    }
    if (class_exists('MybbStuff_MyAlerts_AlertFormatterManager')) {
        $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();
        if (!$formatterManager) {
            $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::createInstance($mybb, $lang);
        }
        $formatterManager->registerFormatter(
            new MybbStuff_MyAlerts_Formatter_RelationReminderFormatter($mybb, $lang, 'relation_reminder')
        );
    }

    /**
     * We need our MyAlert Formatter
     * Alert Formater for Request
     */
    class MybbStuff_MyAlerts_Formatter_RelationRequestFormatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
    {
        /**
         * Build the output string for listing page and the popup.
         * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to format.
         * @return string The formatted alert string.
         */
        public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert)
        {
            $alertContent = $alert->getExtraDetails();
            return $this->lang->sprintf(
                $this->lang->relation_request,
                $outputAlert['from_user'],
                $alertContent['uid'],
                $outputAlert['dateline']
            );
        }
        /**
         * Initialize the language, we need the variables $l['myalerts_setting_alertname'] for user cp! 
         * and if need initialize other stuff
         * @return void
         */
        public function init()
        {
            if (!$this->lang->relations) {
                $this->lang->load('relations');
            }
        }
        /**
         * We want to define where we want to link to. 
         * @param MybbStuff_MyAlerts_Entity_Alert $alert for which alert.
         * @return string return the link.
         */
        public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert)
        {
            $alertContent = $alert->getExtraDetails();
            return $this->mybb->settings['bburl'] . '/usercp.php?action=relations';
        }
    }
    if (class_exists('MybbStuff_MyAlerts_AlertFormatterManager')) {
        $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();
        if (!$formatterManager) {
            $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::createInstance($mybb, $lang);
        }
        $formatterManager->registerFormatter(
            new MybbStuff_MyAlerts_Formatter_RelationRequestFormatter($mybb, $lang, 'relation_request')
        );
    }

    /**
     * We need our MyAlert Formatter
     * Alert Formater for Delete
     */
    class MybbStuff_MyAlerts_Formatter_RelationDeleteFormatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
    {
        /**
         * Build the output string for listing page and the popup.
         * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to format.
         * @return string The formatted alert string.
         */
        public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert)
        {
            $alertContent = $alert->getExtraDetails();
            return $this->lang->sprintf(
                $this->lang->relation_delete,
                $outputAlert['from_user'],
                $alertContent['uid'],
                $outputAlert['dateline']
            );
        }
        /**
         * Initialize the language, we need the variables $l['myalerts_setting_alertname'] for user cp! 
         * and if need initialize other stuff
         * @return void
         */
        public function init()
        {
            if (!$this->lang->relations) {
                $this->lang->load('relations');
            }
        }
        /**
         * We want to define where we want to link to. 
         * @param MybbStuff_MyAlerts_Entity_Alert $alert for which alert.
         * @return string return the link.
         */
        public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert)
        {
            $alertContent = $alert->getExtraDetails();
            return $this->mybb->settings['bburl'] . '/usercp.php?action=relations';
        }
    }
    if (class_exists('MybbStuff_MyAlerts_AlertFormatterManager')) {
        $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();
        if (!$formatterManager) {
            $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::createInstance($mybb, $lang);
        }
        $formatterManager->registerFormatter(
            new MybbStuff_MyAlerts_Formatter_RelationDeleteFormatter($mybb, $lang, 'relation_delete')
        );
    }

    /**
     * We need our MyAlert Formatter
     * Alert Formater for Confirm
     */
    class MybbStuff_MyAlerts_Formatter_RelationConfirmFormatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
    {
        /**
         * Build the output string for listing page and the popup.
         * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to format.
         * @return string The formatted alert string.
         */
        public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert)
        {
            $alertContent = $alert->getExtraDetails();
            return $this->lang->sprintf(
                $this->lang->relation_confirm,
                $outputAlert['from_user'],
                $alertContent['uid'],
                $outputAlert['dateline']
            );
        }
        /**
         * Initialize the language, we need the variables $l['myalerts_setting_alertname'] for user cp! 
         * and if need initialize other stuff
         * @return void
         */
        public function init()
        {
            if (!$this->lang->relations) {
                $this->lang->load('relations');
            }
        }
        /**
         * We want to define where we want to link to. 
         * @param MybbStuff_MyAlerts_Entity_Alert $alert for which alert.
         * @return string return the link.
         */
        public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert)
        {
            $alertContent = $alert->getExtraDetails();
            return $this->mybb->settings['bburl'] . '/usercp.php?action=relations';
        }
    }
    if (class_exists('MybbStuff_MyAlerts_AlertFormatterManager')) {
        $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();
        if (!$formatterManager) {
            $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::createInstance($mybb, $lang);
        }
        $formatterManager->registerFormatter(
            new MybbStuff_MyAlerts_Formatter_RelationConfirmFormatter($mybb, $lang, 'relation_confirm')
        );
    }


    /**
     * We need our MyAlert Formatter
     * Alert Formater for Deny
     */
    class MybbStuff_MyAlerts_Formatter_RelationDenyFormatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
    {
        /**
         * Build the output string for listing page and the popup.
         * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to format.
         * @return string The formatted alert string.
         */
        public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert)
        {
            $alertContent = $alert->getExtraDetails();
            return $this->lang->sprintf(
                $this->lang->relation_deny,
                $outputAlert['from_user'],
                $alertContent['uid'],
                $outputAlert['dateline']
            );
        }
        /**
         * Initialize the language, we need the variables $l['myalerts_setting_alertname'] for user cp! 
         * and if need initialize other stuff
         * @return void
         */
        public function init()
        {
            if (!$this->lang->relations) {
                $this->lang->load('relations');
            }
        }
        /**
         * We want to define where we want to link to. 
         * @param MybbStuff_MyAlerts_Entity_Alert $alert for which alert.
         * @return string return the link.
         */
        public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert)
        {
            $alertContent = $alert->getExtraDetails();
            return $this->mybb->settings['bburl'] . '/usercp.php?action=relations';
        }
    }
    if (class_exists('MybbStuff_MyAlerts_AlertFormatterManager')) {
        $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();
        if (!$formatterManager) {
            $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::createInstance($mybb, $lang);
        }
        $formatterManager->registerFormatter(
            new MybbStuff_MyAlerts_Formatter_RelationDenyFormatter($mybb, $lang, 'relation_deny')
        );
    }
}



function relations_getage($uid)
{
    global $mybb, $db;
    $lastingamemonth = array_pop(explode(",", str_replace(" ", "", $mybb->settings['memberstats_ingamemonat'])));

    // $ingameTime = new DateTime($mybb->settings['memberstats_ingamemonat_tag_end'] . "." . $lastingamemonth . "." . $mybb->settings['memberstats_ingamejahr']);
    // scenetracker_ingametime
    $ingame =  explode(",", str_replace(" ", "", $mybb->settings['scenetracker_ingametime']));
    foreach ($ingame as $monthyear) {
        $ingamelastday = $monthyear . "-" .  sprintf("%02d", $mybb->settings['scenetracker_ingametime_tagend']);
    }

    $ingameTime = new DateTime($ingamelastday);

    $ufid = $db->fetch_field($db->simple_select("application_ucp_userfields", "*", "uid = {$uid} AND fieldid = 3"), "value");

    // $ufid = $db->fetch_field($db->simple_select("userfields", "fid4", "ufid = {$uid}"), "fid4");
    $birthdate = new DateTime($ufid);

    $age = $ingameTime->diff($birthdate);
    $computedAge = $age->format("%Y Jahre");
    return  $computedAge;
}
