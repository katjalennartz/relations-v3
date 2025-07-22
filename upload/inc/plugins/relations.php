<?php

/**
 * Relations  - by risuena
 * Beziehungen von Charakteren zueinander
 *  Anfragen im Profil des Charakters
 *    Eigene Kategorien möglich
 *  Default: Familie,Freunde,Liebe,Bekannte,Ungemocht,Sonstiges -> können im ACP geändert werden
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
    "version" => "3.0",
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

  //Feld löschen wenn es existiert
  if ($db->field_exists("relas_autoaccept", "users")) {
    $db->write_query("ALTER TABLE " . TABLE_PREFIX . "users DROP relas_autoaccept");
  }

  //hier löschen wir alle templates und die Templategruppe
  $db->delete_query("templates", "title LIKE 'relas_%'");
  $db->delete_query("templategroups", "prefix = 'relas'");

  // Einstellungen entfernen
  $db->delete_query('settings', "title LIKE 'relas_%'");
  $db->delete_query('settinggroups', "name = 'relations'");
  rebuild_settings();
}

function relations_install()
{
  global $db, $lang, $mybb;
  // RPG Stuff Modul muss vorhanden sein
  if (!file_exists(MYBB_ADMIN_DIR . "/modules/rpgstuff/module_meta.php")) {
    flash_message("Das ACP Modul <a href=\"https://github.com/little-evil-genius/rpgstuff_modul\" target=\"_blank\">\"RPG Stuff\"</a> muss vorhanden sein!", 'error');
    admin_redirect('index.php?module=config-plugins');
  }

  //falls vorher nicht sauber deinstalliert
  relations_uninstall();

  relations_add_db();
  relations_addtemplates();
  relations_add_settings();

  //CSS Hinzufügen
  $css = relations_css();

  require_once MYBB_ADMIN_DIR . "inc/functions_themes.php";

  $sid = $db->insert_query("themestylesheets", $css);
  $db->update_query("themestylesheets", array("cachefile" => "css.php?stylesheet=" . $sid), "sid = '" . $sid . "'", 1);

  $tids = $db->simple_select("themes", "tid");
  while ($theme = $db->fetch_array($tids)) {
    update_theme_stylesheet_list($theme['tid']);
  }
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


  // Alerts löschen
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

function relations_addtemplates($type = 'install')
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
  $templates[] = array(
    "title" => 'relas_guest_searchpn',
    "template" => '<div class="model-form">
          <label for="e_rela_npcname">Kontakt</label>
          <input type="text" placeholder="Mail oder Discord" name="e_rela_contact" style="width:100%;" required>
          
          <br> Spamschutz "Ich bin kein Bot" eintragen.<br>
          <input type="text" value="" size="0" class="textbox" id="captchain" name="captchain" /><input type="hidden" name="captchapostplus" value="Ich bin kein Bot" id="captchapostplus" /></br>
          </div>
        ',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $templates[] = array(
    "title" => 'relas_memberprofil',
    "template" => '<div class="memrelas">
        {$relas_memberprofil_cat}
        {$relas_memberprofil_anfrage}
      </div>
		',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $templates[] = array(
    "title" => 'relas_memberprofil_anfrage',
    "template" => '<form action="member.php?action=profile&uid={$profilid}" method="post">
      <div class="memrelas-request">
        <h2 class="rela-heading2">Relationsanfrage stellen</h2>
        <div class="memrelas-request__item">
          <input type="number" placeholder="Darstellungsreihenfolge" name="sort" id="sort" value="{$sort}"/>
        </div>
        <div class="memrelas-request__item">
          {$form_select}
        </div>
        <div class="memrelas-request__item">
        <label for="descr">Beschreibung</label>
          <textarea placeholder="Kommentar zur Beziehung" id="kommentar" name="descr" id="descr"></textarea>
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

  $templates[] = array(
    "title" => 'relas_memberprofil_cat',
    "template" => '<div class="memrelas-catcon">
          <h2 class="rela-heading2">{$c_name}</h2>
            {$relas_memberprofil_subcat}
        </div>	
				',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $templates[] = array(
    "title" => 'relas_memberprofil_entrybit',
    "template" => '<div class="memrelas-subcat__item memrelas-entry bl-globalcard " {$style_item}>
          <div class="memrelas-entry__item ava">{$rela_avatar}</div>
          <div class="memrelas-entry__item name">{$rela_name}</div>
          <div class="memrelas-entry__item age">
              {$userage}
          </div>
          <div class="memrelas-entry__item job">
              {$userjob}
          </div>
          <div class="memrelas-entry__item descr">{$rela_descr}</div>
        {$relas_memberprofil_npcsearch}
        {$relas_memberprofil_npcsearchurl}
      </div>	
				',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $templates[] = array(
    "title" => 'relas_memberprofil_npcsearch',
    "template" => '<div class="memrelas-entry__item npcsearch">
            <a onclick="$(\\\'#pn{$rid}\\\').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== \\\'undefined\\\' ? modal_zindex : 9999) }); return false;" style="cursor: pointer;"><i class="fa-solid fa-hand-point-up"></i> Charakter übernehmen</a>
          </div>

          <div class="modal relamodal sendpn" id="pn{$rid}" style="display: none; padding: 10px; margin: auto; text-align: center;">
            <form action="member.php?action=profile&uid={$profilid}" id="npc_take{$rid}" method="post" >
              <div class ="model-form">
                <label for="e_rela_npcnachricht{$rid}">Nachricht</label>
                <textarea id="e_rela_npcnachricht{$rid}" name="e_rela_npcnachricht" required></textarea>
              </div>
              {$guest_searchpn}
              <div class ="model-form model-form--button">
                <input  type="hidden" name="npc_searchname" value="{$npc_searchname}" />
                <input type="submit" id="npc_send" name="npc_send" value="PN schicken" />
              </div>
            </form>
          </div>
				',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $templates[] = array(
    "title" => 'relas_memberprofil_npcsearchurl',
    "template" => '<div class="entry__item rela_linkurl">
                           <a href="{$searchurl}">Zum Gesuch</a>
                            </div>
        ',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $templates[] = array(
    "title" => 'relas_memberprofil_subcat',
    "template" => '<div class="memrelas-catcon__item memrelas-subcat {$style_sub}">
              <h3 class="rela-heading3">{$sc_name}</h3>
              {$relas_memberprofil_entrybit}
          </div>
        ',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $templates[] = array(
    "title" => 'relas_ucp',
    "template" => '<html>
	<head>
		<title>{$lang->user_cp} Relationverwaltung</title>
		{$headerinclude}
	</head>
	<body>
		{$header}
		<table width="100%" border="0" align="center">
			<tr>
				{$usercpnav}
				<td valign="top">
					<table border="0" cellspacing="{$theme[\\\'borderwidth\\\']}" cellpadding="{$theme[\\\'tablespace\\\']}" class="tborder">
						<tr>
							<td class="thead"><strong>Relations</strong></td>
						</tr>
						<tr>
							<td class="trow2">
								<div class="ucprelas-con">
									<div class="ucprelas-con__item">
										<p>Hier kannst du deine Relations verwalten. Die Einstellungen für die Alerts
											kannst du <a href="alerts.php?action=settings">hier</a> vornehmen. Hier kannst du Anfragen annehmen, oder ablehnen und NPCs eintragen. Anfragen stellen kannst du auf dem jeweiligen Profils oder hier.
										</p>
											{$relas_ucp_manage}
										</div>
									</div><!-- edn ucprelas-con__item ucprelas-request-->

									<div class="ucprelas-con__item ucprelas-request">
										<div class="ucprelas-request__item ucprelas-openrequests">
											<h2 class="rela-heading2">open requests</h2>
											{$relas_ucp_toaccept}
											{$relas_ucp_waiting}
											{$relas_ucp_notadded}
											{$relas_ucp_denied}
										</div>

										<div class="ucprelas-con__item ucprelas-request">
											<div class="ucprelas-request__item ucprelas-all">
												<h2 class="rela-heading2">Deine Relations</h2>
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

		<link rel="stylesheet" href="{$mybb->settings[\\\'bburl\\\']}/jscripts/select2/select2.css">
		<script type="text/javascript" src="{$mybb->settings[\\\'bburl\\\']}/jscripts/select2/select2.min.js?ver=1804"></script>
		<script type="text/javascript">
			<!--
				$("#username").select2({
				placeholder: "username",
				minimumInputLength: 2,
				multiple: false,
				ajax: { // instead of writing the function to execute the request we use Select2s convenient helper
					url: "{$mybb->settings[\\\'bburl\\\']}/xmlhttp.php?action=get_users",
					dataType: \\\'json\\\',
					data: function (term, page) {
						return {
							query: term, // search term
						};
					},
					results: function (data, page) { // parse the results into the format expected by Select2.
						// since we are using custom formatting functions we do not need to alter remote JSON data
						return {results: data};
					}
				},
				initSelection: function(element, callback) {
					var query = $(element).val();
					if (query !== "") {
						$.ajax("{$mybb->settings[\\\'bburl\\\']}/xmlhttp.php?action=get_users&getone=1", {
							data: {
								query: query
							},
							dataType: "json"
						}).done(function(data) { callback(data); });
					}
				},
			});

			$(\\\'[for=username]\\\').on(\\\'click\\\', function(){
				$("#username").select2(\\\'open\\\');
				return false;
			});
			// -->
		</script>
		{$footer}
	</body>
</html>
        ',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $templates[] = array(
    "title" => 'relas_ucp_alluser',
    "template" => '<div class="ucprelas__item ucprelas-user">
      <div class="ucprelas-user__item name">
        <span class="rela-username">{$username}</span> <a onclick="$(\\\'#editrela{$rid}\\\').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== \\\'undefined\\\' ? modal_zindex : 9999) }); return false;" style="cursor: pointer;">[editieren]</a> 
        <a href="usercp.php?action=relations&accepted=delete&rid={$rid}" onClick="return confirm(\\\'Möchtest du die Relation zu {$usernamenolink} wirklich löschen?\\\');">[löschen]</a>
      </div>
      <div class="ucprelas-user__item avarund" style="background-image:url(\\\'{$userimg}\\\')">
      </div>
      <div class="ucprelas-user__item cats">
        <span class="rela-maincat">{$haupt}</span>  » {$subkategorie}
      </div>
      {$kommentar}
    </div>
    <div class="modal relamodal editscname" id="editrela{$rid}" style="display: none; padding: 10px; margin: auto; text-align: center;">
      <form action="usercp.php?action=relations" id="formeditrela{$rid}" method="post" >
        {$editname}
        {$npc_editimg}
        <div class ="model-form">
          <input type="hidden" value="{$rid}" name="e_rela_id">
          <label for="e_rela_sort{$rid}">Reihenfolge</label>
          <input type="number" id="e_rela_sort{$rid}" value="{$relauser[\\\'r_sort\\\']}" name="e_rela_sort" />
        </div>
        <div class ="model-form">
          <label for="e_rela_kom{$rid}"  rows="4" cols="50">Kommentar</label>
          <textarea name="e_rela_kom" id="e_rela_kom{$rid}">{$relauser[\\\'r_kommentar\\\']}</textarea>
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

  $templates[] = array(
    "title" => 'relas_ucp_cats',
    "template" => '<div class=" rela_tabcontent" id="tab_{$rela_type}">
              <h2 class="rela-heading2">{$all[\\\'c_name\\\']}</h2>
              {$relas_ucp_subcats}
            </div>
        ',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $templates[] = array(
    "title" => 'relas_ucp_denied',
    "template" => '<div class="bl-tabcon__title">Anfrage wurde abgelehnt</div>
            <div class="ucprelas-openrequests__item ucprelas_denied">
              <p>Deine Anfrage wurde abgelehnt. Du kannst sie editieren und neu schicken, oder löschen.</p>
            {$relas_ucp_denied_bit}
            </div>',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $templates[] = array(
    "title" => 'relas_ucp_denied_bit',
    "template" => '<div class="ucprelas-denied__item ucprelas__item ucprelas-manage">
	<div class="ucprelas-manage__item name"><b >zu:</b> {$username}</div>
	<div class="ucprelas-manage__item avarund" style="background-image:url(\\\'{$user[\\\'avatar\\\']}\\\')"></div>
	<div class="ucprelas-manage__item cats"><b>{$haupt}</b> » {$subkategorie}</div>
	{$kommentar}
	<div class="ucprelas-manage__item answer">
		<a href="usercp.php?action=relations&delete={$denied[\\\'r_id\\\']}" onClick="return confirm(\\\'Möchtest du die Anfrage an {$user[\\\'username\\\']} zurücknehmen?\\\');">[löschen]</a>
		<a onclick="$(\\\'#editdenied{$denied[\\\'r_id\\\']}\\\').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== \\\'undefined\\\' ? modal_zindex : 9999) }); return false;" style="cursor: pointer;">[bearbeiten]</a>

		<div class="modal relamodal editrela" id="editdenied{$denied[\\\'r_id\\\']}" style="display: none; padding: 10px; margin: auto; text-align: center;">
			<form action="usercp.php?action=relations" id="addform{$denied[\\\'r_id\\\']}" method="post" >
			<input type="hidden" value="{$denied[\\\'r_id\\\']}" name="e_rela_id" placeholder="Darstellungsreihenfolge">
			<textarea name="e_rela_kom" placeholder="Kommentar zur Beziehung" id="npcdescr">{$denied[\\\'r_kommentar\\\']}</textarea>
			<input type="number" value="{$denied[\\\'r_sort\\\']}" name="e_rela_sort" placeholder="Darstellungsreihenfolge">
			{$cats}
			<input form="addform{$denied[\\\'r_id\\\']}" type="submit" name="e_rela" value="Senden" />
			</form>
		</div>
	</div>
</div>
        ',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $templates[] = array(
    "title" => 'relas_ucp_manage',
    "template" => '<h2 class="rela-heading2">Verwaltung</h2>
          <div class="ucprelas-manage">
            <div class="ucprelas-manage__item ucprelas-managecats">
              <h3 class="rela-heading3">Kategorien verwalten</h3>
              {$relas_ucp_managecat}
            </div>
            <div class="ucprelas-manage__item ucprelas-addcats">
              <div class="ucprelas-addcats__item ">
                <h3 class="rela-heading3">Standards erstellen</h3>
                <form  action="usercp.php?action=relations" id="catstandard" method="post" >
                  {$dostandardcats}
                </form>
              </div>
              <div class="ucprelas-addcats__item ucprelas-addmaincats">
                <h3 class="rela-heading3">Hauptkategorie erstellen</h3>
                <form action="usercp.php?action=relations" id="newcat" method="post" >
                  <label for="addMain">Bezeichnung Hauptkategorie</label>
                  <input type="text" id="addMain" name="addMain" placeholder="Neue Hauptkategorie" required>
                  <label for="addMainSort">Darstellungsreihenfolge</label>
                  <input type="number" id="addMainSort" name="addMainSort" placeholder="Darstellungsreihenfolge">
                  <input form="newcat" name="newcat" type="submit" value="Speichern" />
                </form>
              </div>
              <div class="ucprelas-addcats__item ucprelas-addsubcats">
                <h3 class="rela-heading3">Unterkategorie erstellen</h3>
                <form  action="usercp.php?action=relations" id="newsubcat" method="post" >
                  <label for="addSub">Bezeichnung Unterkategorie</label>
                  <input type="text" name="addSub" id="addSub" placeholder="Neue Unterkategorie" required>
                  <label for="addSubSort">Darstellungsreihenfolge</label>
                  <input type="number" id="addSubSort" name="addSubSort" placeholder="Darstellungsreihenfolge">
                  <label for="maincat">Hauptkategorie</label>
                  {$hauptkategorie}
                  <input form="newsubcat" name="newsubcat" type="submit" value="Speichern" />
                </form>

              </div>
            </div>
              <div class="ucprelas-manage__item ucprelas-managecats">
              <h3 class="rela-heading3">Einstellungen Alert</h3>
              <form  action="usercp.php?action=relations" id="relconfirm_save" method="post" >
                <p>Möchtest du Relations erst bestätigen, bevor sie bei anderen eingetragen werden?</p>
                <input name="relaconfirm" value="0" type="radio" {$relaconfirm_yes} id="relaconfirmyes">
                <label for="relaconfirmyes">Ja</label>
                <input name="relaconfirm" value="1" type="radio" {$relaconfirm_no} id="relaconfirmno">
                <label for="relaconfirmyes">Nein</label>
                <input form="relconfirm_save" name="relconfirm_save" type="submit" value="Speichern" />
              </form>
            </div>
            <div class="ucprelas-manage__item ucprelas-addcharas" >
              <div class="ucprelas-npcform">
                <form action="usercp.php?action=relations" method="post" >
                  <h3 class="rela-heading3">NPC hinzufügen</h3>
                  <div class="ucprelas-npcform__item">
                    <label for="npcname">Name NPC</label>
                    <input type="text" name="npcname" placeholder="NPC Name" id="npcname" value="" required>
                  </div>
                  <div class="ucprelas-npcform__item">
                    <label for="npcbirthyear">Geburtsjahr NPC</label>
                    <input type="number" name="npcbirthyear" placeholder="NPC Geburtsjahr" id="npcbirthyear" value="">
                  </div>
                  {$img}
                  <div class="ucprelas-npcform__item">
                    <label for="npcdescr">Beschreibung</label>
                    <textarea name="npcdescr" placeholder="Kommentar zur Beziehung" id="npcdescr"></textarea>
                  </div>
                  <div class="ucprelas-npcform__item">
                    <label for="addNpcSort">Darstellungsreihenfolge</label>
                    <input type="number" id="addNpcSort" name="addNpcSort" placeholder="Darstellungsreihenfolge">
                  </div>
                  <div class="ucprelas-npcform__item">
                    {$cats_npc}
                  </div>
                  <div class="ucprelas-npcform__item">
                    <label>Darf übernommen werden?</label>
                    <br>
                    <div style="text-align:center">
                      <input type="radio" name="rela_searched" checked="" id="e_rela_searched_yes" value="1">
                      <label for="e_rela_searched_yes"> Ja</label>
                      <input type="radio" name="rela_searched" id="e_rela_searched_no" value="0">
                      <label for="e_rela_searched_no"> Nein</label>
                    </div>
                  </div>
                  <div class="ucprelas-npcform__item">
                    <input type="url" name="searchurl" placeholder="URL zum Gesuch">
                  </div>
                  <div class="ucprelas-npcform__item">
                    <input type="submit" name="addnpc" value="speichern" id="addnpc" />
                  </div>
                </form>
                <form action="usercp.php?action=relations" method="post" id="addachar" >
                  <h3 class="rela-heading3">add another Character</h3>
                  <div class="ucprelas-npcform__item">
                    <input type="text" name="addname" placeholder="Charakter" id="username" value="" required>
                  </div>

                  <div class="ucprelas-npcform__item">			
                    <textarea name="addescr" placeholder="Kommentar zur Beziehung" id="npcdescr"></textarea>
                  </div>
                  <div class="ucprelas-npcform__item">
                    <input type="number" name="addsort" placeholder="Darstellungsreihenfolge">
                  </div>
                  <div class="ucprelas-npcform__item">
                    {$cats_npc}
                  </div>

                  <div class="ucprelas-npcform__item">
                    <input type="submit" name="addachar_ucp" value="speichern" id="addachar_ucp" />
                  </div>
                </form>

              </div>
            </div>',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $templates[] = array(
    "title" => 'relas_ucp_managecat',
    "template" => '<div class="ucprelas-editcat">
              <div class="editcname" id="{$cid}"><b>{$c_name} {$editcatmod}</b><a href="usercp.php?action=relations&cat=delete&cid={$cid}" onClick="return confirm(\\\'Möchtest du die Kategorie {$c_name} wirklich löschen?\\\');">[löschen]</a></div>
              <div class="ucprelas-managesubcat">{$relas_ucp_managesubcat}</div>
              <div class="modal relamodal managesubcat" id="cedit{$cid}" style="display: none; padding: 10px; margin: auto; text-align: center;">
                <form action="usercp.php?action=relations" id="editcat" method="post" >
                  <div class="model-form">
                    <label for="c_name_e{$cid}">Name</label>
                    <input type="text" value="{$c_name}" id="c_name_e{$cid}" name="c_name_e">
                    <input type="hidden" value="{$cid}" name="c_cid_e">
                  </div>
                  <div class="model-form">
                    <label for="c_sort_e{$cid}">Reihenfolge</label>
                    <input type="number" value="{$cat[\\\'c_sort\\\']}" id="c_sort_e{$cid}" name="c_sort_e">
                  </div>
                  <div class="model-form model-form--button">		
                    <input type="submit" name="editcat" value="Speichern" />            
                  </div>
                </form>
              </div>
            </div>',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $templates[] = array(
    "title" => 'relas_ucp_managesubcat',
    "template" => '<span class="editscname bl-btn" id={$subcat[\\\'sc_id\\\']}>{$sc_name} {$editscatmod} <a href="usercp.php?action=relations&scat=delete&scid={$scid}" onClick="return confirm(\\\'Möchtest du die Unterkategorie {$sc_name} wirklich löschen?\\\');">[löschen]</a></span>

          <div class="modal  relamodal editscname" id="scedit{$scid}" style="display: none; padding: 10px; margin: auto; text-align: center;">
            <form action="usercp.php?action=relations" id="editscat{$scid}" method="post" >
              <div class ="model-form">
                <label for="sc_edit_name{$scid}">Name:</label>
                <input type="text" value="{$sc_name}"  id="sc_edit_name{$scid}" name="sc_edit_name">
                <input type="hidden" value="{$scid}" name="sc_edit_id">
              </div>
              <div class ="model-form">
                <label for="sc_edit_sort{$scid}">Reihenfolge</label>
                <input type="number" value="{$sortcatvalue}" id="sc_edit_sort{$scid}" name="sc_edit_sort">
              </div>
              <div class ="model-form">
                <label for="cat{$scid}">Hauptkategorie</label>
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

  $templates[] = array(
    "title" => 'relas_ucp_notadded',
    "template" => '<div class="bl-tabcon__title">still to add</div>
            <div class="ucprelas-openrequests__item ucprelas_toadd">
              <p>Charaktere die dich eingetragen haben, du sie aber noch nicht.</p>
            {$relas_ucp_notaddedbit}
            </div>',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $templates[] = array(
    "title" => 'relas_ucp_notaddedbit',
    "template" => '<div class="ucprelas_toaccept__item ucprelas__item ucprelas-requestuser">
              <div class="ucprelas-requestuser__item name">
                <b>{$username}</b> about you:
              </div>
              <div class="ucprelas-requestuser__item avarund" style="background-image:url(\\\'{$user[\\\'avatar\\\']}\\\')">
              </div>
              <div class="ucprelas-requestuser__item cats">
                In <b>{$haupt}:</b><br/><i>{$subkategorie}</i>
              </div>
              {$kommentar}
              <div class="ucprelas-requestuser__item answer">
                <a onclick="$(\\\'#add{$notadd[\\\'r_id\\\']}\\\').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== \\\'undefined\\\' ? modal_zindex : 9999) }); return false;" style="cursor: pointer;">[hinzufügen]</a>

                <div class="modal relamodal addrela" id="add{$notadd[\\\'r_id\\\']}" style="display: none; padding: 10px; margin: auto; text-align: center;">
                  <form action="usercp.php?action=relations" id="addform{$notadd[\\\'r_id\\\']}" method="post" >
                    <input type="hidden" name="r_from" id="r_from" value="{$notadd[\\\'r_from\\\']}">
                    <textarea name="adddescr" placeholder="Kommentar zur Beziehung" id="npcdescr" ></textarea>
                    <input type="number" name="addSort" placeholder="Darstellungsreihenfolge">
                    {$cats}
                    <input form="addform{$notadd[\\\'r_id\\\']}" type="submit" name="addrela" value="Senden" />
                  </form>
                </div>
              </div>
            </div>
        ',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $templates[] = array(
    "title" => 'relas_ucp_subcats',
    "template" => '<div class="ucprelas-user__subcat">
            <h3 class="rela-heading3">{$allsubs[\\\'sc_name\\\']}</h3>
                {$relas_ucp_alluser}
        </div>
        ',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $templates[] = array(
    "title" => 'relas_ucp_tablinks',
    "template" => '<button class="relas_tablinks " onclick="openRestype(event, \\\'tab_{$all[\\\'c_id\\\']}\\\')" id="{$tabbuttonid}">{$all[\\\'c_name\\\']}</button>',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $templates[] = array(
    "title" => 'relas_ucp_toaccept',
    "template" => '<div class="ucprelas-openrequests__item ucprelas_toaccept ">
        <h3 class="rlea-heading3">Zu Bestätigen</h3>
        {$relas_ucp_toaccept_bit}
      </div>
    ',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $templates[] = array(
    "title" => 'relas_ucp_toaccept_bit',
    "template" => '<div class="ucprelas-toaccept__item ucprelas__item ucprelas-toaccept__user ">
            <div class="ucprelas-toaccept__user name">
              <b>von:</b> {$username}
            </div>
            <div class="ucprelas-toaccept__user accept__item avarund" style="background-image:url(\\\'{$user[\\\'avatar\\\']}\\\')">
            </div>
            <div class="ucprelas-toaccept__user cats"><b>{$haupt}</b> » {$subkategorie}</div>
            {$kommentar}
            <div class="ucprelas-toaccept__user answer">
              <a href="usercp.php?action=relations&accept=1&rid={$entry[\\\'r_id\\\']}" onClick="return confirm(\\\'Möchtest du die Anfrage von {$user[\\\'username\\\']} annehmen?\\\');">[bestätigen]</a>
              <a href="usercp.php?action=relations&deny=1&rid={$entry[\\\'r_id\\\']}" onClick="return confirm(\\\'Möchtest du die Anfrage von {$user[\\\'username\\\']} ablehnen?\\\');">[ablehnen]</a> 
              <a onclick="$(\\\'#add{$entry[\\\'r_id\\\']}\\\').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== \\\'undefined\\\' ? modal_zindex : 9999) }); return false;" style="cursor: pointer;">[anfragen]</a>

              <div class="modal relamodal addrela" id="add{$entry[\\\'r_id\\\']}" style="display: none; padding: 10px; margin: auto; text-align: center;">
                <form action="usercp.php?action=relations" id="addform{$entry[\\\'r_id\\\']}" method="post" >
                  <input type="hidden" name="r_from" id="r_from" value="{$entry[\\\'r_from\\\']}">
                  <label for="adddesc{$entry[\\\'r_id\\\']}">Beschreibung</label>
                  <textarea name="adddescr" placeholder="Kommentar zur Beziehung" id="addSort{$entry[\\\'r_id\\\']}"></textarea>
                  <label for="addSort{$entry[\\\'r_id\\\']}">Darstellungsreihenfolge</label>
                  <input id="addSort{$entry[\\\'r_id\\\']}" type="number" name="addSort" placeholder="Darstellungsreihenfolge">
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

  $templates[] = array(
    "title" => 'relas_ucp_waiting',
    "template" => '<div class="ucprelas-openrequests__item ucprelas_waiting  ">
            <h3 class="rlea-heading3">Wartet auf Bestätigung</h3>
            <div class="ucprelas-openrequests__item ucprelas_waiting">
              {$relas_ucp_waiting_bit}
            </div>
          </div>
        ',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $templates[] = array(
    "title" => 'relas_ucp_waiting_bit',
    "template" => '<div class="ucprelas-toaccept__item ucprelas-pending ">
              <div class="ucprelas-pending__item name"><b>An:</b> {$username}</div>
              <div class="ucprelas-pending__item avarund" style="background-image:url(\\\'{$user[\\\'avatar\\\']}\\\')"></div>
              <div class="ucprelas-pending__item cats"><b>{$haupt}</b> » {$subkategorie}</div>
              {$kommentar}
              <div class="ucprelas-pending__item answer">
                <a href="usercp.php?action=relations&reminder={$user[\\\'uid\\\']}" onClick="return confirm(\\\'Möchtest du {$user[\\\'username\\\']} an deine Anfrage erinnern?\\\');">[erinnern]</a>
                <a href="usercp.php?action=relations&delete={$entry[\\\'r_id\\\']}" onClick="return confirm(\\\'Möchtest du die Anfrage an {$user[\\\'username\\\']} zurücknehmen?\\\');">[löschen]</a>
                <a onclick="$(\\\'#waiting{$entry[\\\'r_id\\\']}\\\').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== \\\'undefined\\\' ? modal_zindex : 9999) }); return false;" style="cursor: pointer;">[edit]</a>

                <div class="modal relamodal editrela" id="waiting{$entry[\\\'r_id\\\']}" style="display: none; padding: 10px; margin: auto; text-align: center;">
                  <form action="usercp.php?action=relations" id="addform{$entry[\\\'r_id\\\']}" method="post" >
                    <input type="hidden" value="{$entry[\\\'r_id\\\']}" name="e_rela_id" placeholder="Darstellungsreihenfolge">
                    <label for="e_rela_kom{$entry[\\\'r_id\\\']}">Beschreibung</label>
                    <textarea name="e_rela_kom" placeholder="Kommentar zur Beziehung" id="e_rela_kom{$entry[\\\'r_id\\\']}">{$entry[\\\'r_kommentar\\\']}</textarea>
                    <label for="e_rela_sort{$entry[\\\'r_id\\\']}" >Darstellungsreihenfolge</label>
                    <input type="number" value="{$entry[\\\'r_sort\\\']}" id="e_rela_sort{$entry[\\\'r_id\\\']}" name="e_rela_sort" placeholder="Darstellungsreihenfolge">
                    {$cats}
                    <input form="addform{$entry[\\\'r_id\\\']}" type="submit" name="e_rela" value="Senden" />
                  </form>
                </div>
              </div>
            </div>
        ',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  if ($type == 'update') {
    foreach ($templates as $template) {
      $query = $db->simple_select("templates", "tid, template", "title = '" . $template['title'] . "' AND sid = '-2'");
      $existing_template = $db->fetch_array($query);

      if ($existing_template) {
        if ($existing_template['template'] !== $template['template']) {
          $db->update_query("templates", array(
            'template' => $template['template'],
            'dateline' => TIME_NOW
          ), "tid = '" . $existing_template['tid'] . "'");
        }
      } else {
        $db->insert_query("templates", $template);
      }
    }
  } else {
    foreach ($templates as $template) {
      $check = $db->num_rows($db->simple_select("templates", "title", "title = '" . $template['title'] . "'"));
      if ($check == 0) {
        $db->insert_query("templates", $template);
      }
    }
  }
}

function relations_add_settings($type = 'install')
{
  global $db, $mybb, $lang;
  $lang->load("relations");
  if ($type == 'install') {
    // Einstellungen
    $setting_group = array(
      'name' => 'relations',
      'title' => $lang->relations_title,
      'description' => $lang->relations_settings_descr,
      'disporder' => 7, // The order your setting group will display
      'isdefault' => 0
    );
    $gid = $db->insert_query("settinggroups", $setting_group);
  } else {
    $gid = $db->fetch_field($db->write_query("SELECT gid FROM `" . TABLE_PREFIX . "settinggroups` WHERE name like 'relas%' LIMIT 1;"), "gid");
  }

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
      'value' => '0', // Defaultn
      'disporder' => 8
    ),
    'relas_mycode' => array(
      'title' => $lang->relations_settings_mycodeTitle,
      'description' => $lang->relations_settings_mycodeDescr,
      'optionscode' => 'yesno',
      'value' => '0', // Default
      'disporder' => 9
    ),
    'relas_nachwob' => array(
      'title' => $lang->relations_settings_wob,
      'description' => $lang->relations_settings_wob_descr,
      'optionscode' => 'yesno',
      'value' => '0', // Default
      'disporder' => 10
    ),
    'relas_group' => array(
      'title' => $lang->relations_settings_group,
      'description' => $lang->relations_settings_group_descr,
      'optionscode' => 'groupselectsingle',
      'value' => '2', // Default
      'disporder' => 11
    )
  );

  if ($type == 'install') {
    foreach ($setting_array as $name => $setting) {
      $setting['name'] = $name;
      $setting['gid'] = $gid;
      $db->insert_query('settings', $setting);
    }
  }

  if ($type == 'update') {
    foreach ($setting_array as $name => $setting) {
      $setting['name'] = $name;
      $setting['gid'] = $gid;

      //alte einstellung aus der db holen
      $check = $db->write_query("SELECT * FROM `" . TABLE_PREFIX . "settings` WHERE name = '{$name}'");
      $check2 = $db->write_query("SELECT * FROM `" . TABLE_PREFIX . "settings` WHERE name = '{$name}'");
      $check = $db->num_rows($check);

      if ($check == 0) {
        $db->insert_query('settings', $setting);
        echo "Setting: {$name} wurde hinzugefügt.";
      } else {

        //die einstellung gibt es schon, wir testen ob etwas verändert wurde
        while ($setting_old = $db->fetch_array($check2)) {
          if (
            $setting_old['title'] != $setting['title'] ||
            $setting_old['description'] != $setting['description'] ||
            $setting_old['optionscode'] != $setting['optionscode'] ||
            $setting_old['disporder'] != $setting['disporder']
          ) {
            //wir wollen den value nicht überspeichern, also nur die anderen werte aktualisieren
            $update_array = array(
              'title' => $setting['title'],
              'description' => $setting['description'],
              'optionscode' => $setting['optionscode'],
              'disporder' => $setting['disporder']
            );
            $db->update_query('settings', $update_array, "name='{$name}'");
            echo "Setting: {$name} wurde aktualisiert.<br>";
          }
        }
      }
    }
  }
  rebuild_settings();
}

/**
 * Aktueller Stylesheet
 * @param int id des themes das hinzugefügt werden soll. Default: 1 -> Masterstylesheet
 * @return array - css array zum eintragen in die db
 */
function relations_css($themeid = 1)
{
  global $db;
  $css = array(
    'name' => 'relations.css',
    'tid' => 1,
    'attachedto' => '',
    "stylesheet" =>    '
                /* empty :( */
      
        ',
    'cachefile' => $db->escape_string(str_replace('/', '', 'relations.css')),
    'lastmodified' => time()
  );
  return $css;
}

function relations_add_db($type = 'install')
{
  global $db;
  //table Rela
  if (!$db->table_exists("relas_entries")) {
    $db->query("CREATE TABLE `" . TABLE_PREFIX . "relas_entries` (
            `r_id` int(10) NOT NULL AUTO_INCREMENT,
            `r_to` int(10) NOT NULL DEFAULT 0,
            `r_from` int(10) NOT NULL DEFAULT 0,
            `r_kategorie` varchar(150) NOT NULL DEFAULT '',
            `r_kommentar` varchar(2555) NOT NULL DEFAULT '',
            `r_accepted` int(10) NOT NULL DEFAULT 0,
            `r_npc` int(1) NOT NULL DEFAULT 0,
            `r_npcname` varchar(150) NOT NULL DEFAULT '',
            `r_npcimg` varchar(250) NOT NULL DEFAULT '', 
            `r_npcbirthyear` int(10) NOT NULL DEFAULT '0', 
            `r_searched` varchar(250) NOT NULL DEFAULT '', 
            `r_searchurl` varchar(250) NOT NULL DEFAULT '',    
            `r_sort` int(10) NOT NULL DEFAULT 1,
            `r_overview` varchar(250) NOT NULL DEFAULT '', 
            PRIMARY KEY (`r_id`)
        ) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_general_ci;");
  }
  // ALTER TABLE `mybb_relas_entries` ADD `r_npcage` INT NOT NULL DEFAULT '0' AFTER `r_npcname`;

  //table Category
  if (!$db->table_exists("relas_categories")) {
    $db->query("CREATE TABLE `" . TABLE_PREFIX . "relas_categories` (
            `c_id` int(10) NOT NULL AUTO_INCREMENT,
            `c_name` varchar(100) NOT NULL DEFAULT '',
            `c_sort` int(10) NOT NULL DEFAULT 1,
            `c_uid` int(10) NOT NULL DEFAULT 0,
            PRIMARY KEY (`c_id`)
            ) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_general_ci;");
  }

  //table subcat
  if (!$db->table_exists("relas_subcategories")) {
    $db->query("CREATE TABLE `" . TABLE_PREFIX . "relas_subcategories` (
            `sc_id` int(10) NOT NULL AUTO_INCREMENT,
            `sc_name` varchar(100) NOT NULL DEFAULT '',
            `sc_cid` int(10) NOT NULL  DEFAULT 0,
            `sc_sort` int(10) NOT NULL DEFAULT 1,
            `sc_uid` int(10) NOT NULL  DEFAULT 0,
            PRIMARY KEY (`sc_id`)
        ) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_general_ci;");
  }

  //einfügen der einstellung ob relas direkt rausgehen
  if (!$db->field_exists("relas_autoaccept", "users")) {
    $db->add_column("users", "relas_autoaccept", "INT(1) NOT NULL DEFAULT '0'");
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
  global $db, $eas, $mybb, $templates, $memprofile, $relas_memberprofil, $userage, $userjob, $lang;
  $relas_memberprofil_entrybit = $relas_memberprofil_subcat = $style_item =  $style_item = $style_sub = $relas_memberprofil_anfrage = $sort = "";
  //Einstellungen bekommen die wir brauchen
  $lang->load("relations");
  $opt_img_guest = intval($mybb->settings['relas_img_guests']);
  $opt_npc_img = intval($mybb->settings['relas_npc_img']);
  $opt_img_width = intval($mybb->settings['relas_img_width']);
  $opt_accept = intval($mybb->settings['relas_alert']);
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

        $style_sub  = "substyle";
        // $style_item = ' style="width: 280px;"';
      } else {
        $style_sub = "";
      }
      while ($entry = $db->fetch_array($get_entries)) {
        $searchurl = "";
        $relas_memberprofil_npcsearchurl = "";
        $relas_memberprofil_npcsearch = "";
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
          //Alter des NPCs
          if ($entry['r_npcbirthyear'] != "0") {
            $userage = $mybb->settings['scenetracker_ingametime'] - $entry['r_npcbirthyear'] . " Jahre";
          } else {
            $userage = "";
          }
          //wird der NPC gesucht? 
          if ($entry['r_searched'] == 1) {
            $rid = $entry['r_id'];
            $npc_searchname =  $entry['r_npcname'];
            //captcha
            $guest_searchpn = "";
            if ($mybb->user['uid'] == 0) {
              eval("\$guest_searchpn = \"" . $templates->get("relas_guest_searchpn") . "\";");
            }
            eval("\$relas_memberprofil_npcsearch = \"" . $templates->get("relas_memberprofil_npcsearch") . "\";");
          } else {
            $relas_memberprofil_npcsearch = "";
          }

          //gibt es ein Gesuch zum NPC
          if ($entry['r_searchurl'] != "") {
            $searchurl = $entry['r_searchurl'];
            eval("\$relas_memberprofil_npcsearchurl = \"" . $templates->get("relas_memberprofil_npcsearchurl") . "\";");
          } else {
            $searchurl = "";
            $relas_memberprofil_npcsearchurl = "";
          }
        } else { // kein npc, sondern existierender user
          //die daten des freunds
          $userage = relations_getage($entry['r_to']);
          $friend = get_user($entry['r_to']);
          if ($friend['uid'] == "") $friend['uid'] = -1;

          // dürfen gäste avatare sehen
          if ($thisuser == 0 && $opt_img_guest == 0) {
            $rela_avatar = "<div class=\"entry__item ava\"><i class=\"fa-solid fa-circle-user\"></i></div>";
          } else {
            //ausgabe bild
            $rela_avatar = "<div class=\"entry__item ava\" style=\"background-image: url('{$friend['avatar']}');\"></div>";
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
  if ($mybb->get_input('npc_send')) {
    // Set PM Handler
    require_once MYBB_ROOT . "inc/datahandlers/pm.php";
    $pmhandler = new PMDataHandler();
    $subject = $db->escape_string($mybb->get_input('npc_searchname'));
    if ($mybb->get_input('e_rela_contact') != "") {

      $contact = "<br><br>Kontakt: " . $db->escape_string($mybb->get_input('e_rela_contact'));
    }
    $message = $db->escape_string($mybb->get_input('e_rela_npcnachricht')) . $contact;

    if ($mybb->user['uid'] == 0) {
      $interessent = "1";
      if ($mybb->input['captchain'] != 'Ich bin kein Bot' && !$mybb->user['uid']) {
        error('Du hast die Sicherheitsfrage leider falsch beantwortet!<br /><a href="javascript:history.back()">Zur&uuml;ck</a>');
      }
    } else {
      $interessent = $mybb->user['uid'];
    }

    $pm_change = array(
      "subject" => "Relations Gesuch:" . $subject,
      "message" =>  $message,
      //to: wer muss die anfrage bestätigen
      "fromid" => $interessent,
      //from: wer hat die anfrage gestellt
      "toid" => $profilid
    );

    // $pmhandler->admin_override = true;
    $pmhandler->set_data($pm_change);
    if (!$pmhandler->validate_pm())
      return false;
    else {
      $pmhandler->insert_pm();
      //update accountswitcher chache
      if ($db->field_exists("as_uid", "users")) {
        $eas->update_accountswitcher_cache();
      }
    }
  }
  //ausgabe formular für anfrage 
  //nur wenn kein Gast (Gäste dürfen keine anfragen stellen) und wenn nicht auf dem eigenen Profil
  if ($thisuser != 0 && $thisuser != $profilid) {
    //select bauen für anfragen formular
    //Hiermit stellen wir fest, ob der anfragende überhaupt schon kategorien hat.
    $form_select = relations_getCats($thisuser);
    if ($form_select != "false") {
      if ($mybb->user['uid'] != 0 && !is_member(7)) {
	if ($mybb->settings['relas_nachwob'] == 1) {
        	eval("\$relas_memberprofil_anfrage = \"" . $templates->get("relas_memberprofil_anfrage") . "\";");
	} else if ($mybb->settings['relas_nachwob'] == 0 && !is_member($mybb->settings['relas_group'])) {
		eval("\$relas_memberprofil_anfrage = \"" . $templates->get("relas_memberprofil_anfrage") . "\";");
	}
      }
    } else {
      $relas_memberprofil_anfrage = "<div class=\"rela-noncats\">Du hast noch keine Kategorien in deinem Profil angelegt. Bitte tu dies zuerst. Dann kannst du Anfragen an andere Charaktere schicken.</div>";
    }
  }
  //ausgabe template gesamt mem

  eval("\$relas_memberprofil = \"" . $templates->get("relas_memberprofil") . "\";");

  //Das Behandeln der Anfrage
  //Button anfragen wurde gedrückt (und kein Gast):
  if ($mybb->get_input('anfragen') && $mybb->user['uid'] != 0) {
    $touser = $mybb->get_input('getto', MyBB::INPUT_INT);
    $touser_info = get_user($touser);
    $autoaccept = $touser_info['relas_autoaccept'];

    //Wir stellen ein Array mit den eingegeben Daten zusammen
    $rela_anfrage = array(
      "r_from" => $mybb->get_input('getfrom', MyBB::INPUT_INT),
      "r_to" => $touser,
      "r_kategorie" => $db->escape_string($mybb->get_input('kategorie', MyBB::INPUT_STRING)),
      "r_kommentar" => $db->escape_string($mybb->get_input('descr', MyBB::INPUT_STRING)),
      "r_sort" => $mybb->get_input('sort', MyBB::INPUT_INT),
      "r_accepted" => $autoaccept,
      "r_npc" => 0
    );
    $db->insert_query("relas_entries", $rela_anfrage);
    if ($opt_accept == 1) {
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
    }
    redirect('member.php?action=profile&uid=' . $mybb->get_input('getto', MyBB::INPUT_INT));
  }
}


/*
 * Fügt die Verwaltung der Relas ins user CP ein
 */
$plugins->add_hook("usercp_menu", "relations_usercp_menu", 40);
function relations_usercp_menu()
{
  global $usercpmenu, $lang;
  $lang->load("relations");
  $usercpmenu .= "<tr><td class=\"trow1 smalltext\"><a href=\"./usercp.php?action=relations\" class=\"usercp_nav_item usercp_nav_mecool\">{$lang->relations_ucpnav}</a></td></tr>";
}

/*
 * Verwaltung der Relations im User CP
 * Kategorien verwalten
 * NPCs hinzufügen
 * Akzeptieren/Ablehnen von Beziehungen
 * Einen anderen Charakter erinnern
 * Löschen und ändern */
$plugins->add_hook("usercp_start", "relations_usercp");
function relations_usercp()
{
  global $mybb, $db, $lang, $templates, $anfragen, $header, $relas_ucp_alluser, $theme, $headerinclude, $header, $footer, $usercpnav, $relas_ucp, $relas_ucp_toaccept, $relas_ucp_notadded;

  if ($mybb->input['action'] != "relations") {
    return false;
  }
  $relas_ucp_cats =  $relas_ucp_tablinks  = $img = $relas_ucp_waiting =  $relas_ucp_all  = "";
  //Einstellungen
  $opt_npc = intval($mybb->settings['relas_npc']);
  $opt_npc_img = intval($mybb->settings['relas_npc_img']);
  $opt_img_width = intval($mybb->settings['relas_img_width']);
  //müssen Relas akzeptiert werden? 
  $opt_accept = intval($mybb->settings['relas_alert']);


  // die user id des users
  $thisuser = intval($mybb->user['uid']);
  $relaconfirm_settings = $db->fetch_field($db->simple_select("users", "relas_autoaccept", "uid = '$thisuser'"), "relas_autoaccept");
  if ($relaconfirm_settings == 0) {
    $relaconfirm_yes = " checked";
    $relaconfirm_no = "";
  } else {
    $relaconfirm_yes = "";
    $relaconfirm_no = " checked";
  }

  //HauptKategorien bekommen
  $get_cats = $db->simple_select("relas_categories", "*", "c_uid = {$thisuser}", array('order_by' => 'c_sort'));
  $get_catsforselect = $db->simple_select("relas_categories", "*", "c_uid = {$thisuser}", array('order_by' => 'c_sort'));

  //Standardkategorien frontend
  $get_catstest = $db->simple_select("relas_categories", "*", "c_uid = {$thisuser}", array('order_by' => 'c_sort'));

  if ($db->num_rows($get_catstest) <= 0) {
    $dostandardcats = "<input form=\"catstandard\" name=\"standardcat\" type=\"submit\" value=\"Erstellen\" onClick=\"return confirm('Standardkategorien anlegen? Achtung: Wenn du schon welche erstellt hast, werden sie zusätzlich hinzugefügt.');\"/>";
  } else {
    $dostandardcats = "<p><span class=\"alert_warn\">Achtung!</span><br />
                Du hast schon eigene Kategorien, wenn du den Button drückst, werden die Standardkategorien <b>zusätzlich</b> hinzugefügt.<br />
                <input form=\"catstandard\" name=\"standardcat\" type=\"submit\" value=\"Erstellen\" onClick=\"return confirm('Standardkategorien anlegen? Achtung: Wenn du schon welche erstellt hast, werden sie zusätzlich hinzugefügt.');\"/></p>";
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
  $hauptkategorie = "<select name=\"cat\" id=\"maincat\" required>";
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
    $relas_ucp_managesubcat = $sortcatvalue = "";
    if (!empty($cat['sc_sort'])) $sortcatvalue = $cat['sc_sort'];

    //edit link bauen (Popup anzeigen bei klick)
    $editcatmod = "<a onclick=\"$('#cedit{$cid}').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== 'undefined' ? modal_zindex : 9999) }); return false;\" style=\"cursor: pointer;\">[e]</a>";
    //Dazu gehörende Unterkategorien durchgehen
    while ($subcat = $db->fetch_array($get_subcats)) {

      $sc_name = $subcat['sc_name'];
      $scid = $subcat['sc_id'];
      $sccid = $subcat['sc_cid'];
      $hauptkategoriesub = "<select name=\"cat\" id=\"cat$scid\" required>";
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
if ($mybb->user['uid'] != 0 && !is_member(7)) {
	if ($mybb->settings['relas_nachwob'] == 1) {
		eval("\$relas_ucp_manage = \"" . $templates->get("relas_ucp_manage") . "\";");
	} else if ($mybb->settings['relas_nachwob'] == 0 && !is_member($mybb->settings['relas_group'])) {
		eval("\$relas_ucp_manage = \"" . $templates->get("relas_ucp_manage") . "\";");
	} else {
		$relas_ucp_manage = "";
	}
}

	
//  if (
//    ($mybb->settings['relas_nachwob'] == 1 && !is_member($mybb->settings['relas_group']))
//    && $mybb->user['uid'] != 0
//    && !is_member(7)
//  ) {
//    eval("\$relas_ucp_manage = \"" . $templates->get("relas_ucp_manage") . "\";");
//  } else {
//    $relas_ucp_manage = "";
//  }

  //Verarbeitung der Formulardaten 
  //Standardkategorien erstelle
  if (isset($mybb->input['standardcat'])) {
    $standards = $mybb->settings['standardcat'];
    $standard = array(
      "family" => "mom,dad,children,siblings,other",
      "friends" => "best friends,good friends,friends",
      "known" => "like,neutral,dislike",
      "love" => "relationship,well looking,kissed,flirt,one night stand,affair",
      "hate" => "dislike,hate",
      "other" => "past loves,past friendships,past affairs"
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
  if (isset($mybb->input['newcat'])) {
    $insert = array(
      "c_name" => $db->escape_string($mybb->get_input('addMain', MyBB::INPUT_STRING)),
      "c_sort" => $mybb->get_input('addMainSort', MyBB::INPUT_INT),
      "c_uid" => $thisuser
    );
    $db->insert_query('relas_categories', $insert);
    redirect('usercp.php?action=relations');
  }
  // Neue Unterkategorie erstellen
  if (isset($mybb->input['newsubcat'])) {
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
  if (isset($mybb->input['editcat'])) {
    $update = array(
      "c_name" => $db->escape_string($mybb->get_input('c_name_e', MyBB::INPUT_STRING)),
      "c_sort" => $mybb->get_input('c_sort_e', MyBB::INPUT_INT),
      "c_uid" => $thisuser
    );
    $db->update_query('relas_categories', $update, "c_id = {$mybb->get_input('c_cid_e', MyBB::INPUT_INT)}");
    redirect('usercp.php?action=relations');
  }
  //Einstellungen Alert speicher/ändern
  if (isset($mybb->input['relconfirm_save'])) {
    $update = array(
      "relas_autoaccept" => $db->escape_string($mybb->get_input('relaconfirm', MyBB::INPUT_INT))
    );
    $db->update_query('users', $update, "uid = '{$thisuser}'");
    redirect('usercp.php?action=relations');
  }


  //Hauptkategorie löschen
  if ($mybb->get_input('cat') == 'delete') {
    //Welche cid
    $cid = $mybb->get_input('cid', MyBB::INPUT_INT);
    //Unterkategorien der Hauptkategorie löschen
    $db->delete_query("relas_subcategories", "sc_cid= {$cid}");
    //Hauptkategorie löschen
    $db->delete_query("relas_categories", "c_id= {$cid}");
    redirect('usercp.php?action=relations');
  }

  //Unterkategorie ändern
  if (isset($mybb->input['editsubcat'])) {
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
  if ($mybb->get_input('scat') == 'delete') {

    //welche scid
    $scid = $mybb->get_input('scid', MyBB::INPUT_INT);
    //dazugehörige Unterkategorien löschen
    $db->delete_query("relas_subcategories", "sc_id= {$scid}");
    redirect('usercp.php?action=relations');
  }

  // NPC hinzufügen    
  if (isset($mybb->input['addnpc'])) {
    if (!relations_checkCats($thisuser)) {
      echo "<script>alert('Du kannst keinen Chara anfragen/hinzufügen, solange du keine Kategorien erstellt hast.')
            window.location = 'usercp.php?action=relations';</script>";
    } else {
      $insert = array(
        "r_to" => 0,
        "r_from" => $thisuser,
        "r_npc" => 1,
        "r_npcname" => $db->escape_string($mybb->get_input('npcname', MyBB::INPUT_STRING)),
        "r_npcbirthyear" => $mybb->get_input('npcbirthyear', MyBB::INPUT_INT),
        "r_searched" => $mybb->get_input('rela_searched', MyBB::INPUT_INT),
        "r_npcimg" => $db->escape_string($mybb->get_input('npcimg', MyBB::INPUT_STRING)),
        "r_searchurl" => $db->escape_string($mybb->get_input('searchurl', MyBB::INPUT_STRING)),
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
  if (isset($mybb->input['error_edit_cat'])) {
    if (!relations_checkCats($thisuser)) {
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
  $toacceptcnt = 0;
  $relas_ucp_toaccept = "";
  $relas_ucp_toaccept_bit = "";
  while ($entry = $db->fetch_array($toaccept)) {
    $toacceptcnt++;
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
    eval("\$relas_ucp_toaccept_bit .= \"" . $templates->get("relas_ucp_toaccept_bit") . "\";");
  }
  if ($toacceptcnt > 0) {
    eval("\$relas_ucp_toaccept .= \"" . $templates->get("relas_ucp_toaccept") . "\";");
  }
  $relas_ucp_waiting_bit = "";
  $relas_ucp_waiting = "";
  //Diese Anfragen wurden von dem User an andere Charas gestellt und wurden noch nicht bestätigt
  $waiting = $db->write_query("SELECT * FROM " . TABLE_PREFIX . "relas_entries WHERE r_from = '{$thisuser}' AND r_accepted = 0");
  $cats = "";
  $waitcnt = 0;
  while ($entry = $db->fetch_array($waiting)) {
    $waitcnt++;
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

    $get_cats = $db->simple_select("relas_categories", "*", "c_uid = '{$thisuser}'");
    $cats = "<select name=\"kategorie\" size=\"3\" id=\"kategorien\" required>";
    while ($cat = $db->fetch_array($get_cats)) {
      $cats .= "<optgroup label=\"{$cat['c_name']}\">";
      $get_subcats = $db->simple_select("relas_subcategories", "*", "sc_cid={$cat['c_id']}");
      while ($subcatb = $db->fetch_array($get_subcats)) {
        if ($subcatb['sc_id'] == $entry['r_kategorie']) {
          $selected = "SELECTED";
        } else {
          $selected = "";
        }
        $cats .= "<option value=\"{$subcatb['sc_id']}\" {$selected}>{$subcatb['sc_name']}</option>";
      }
    }
    $cats .= "</select>";
    $cat = $db->fetch_array($db->simple_select("relas_categories", "*",  "c_id = '{$subcat['sc_cid']}'"));
    $haupt = $cat['c_name'];
    eval("\$relas_ucp_waiting_bit .= \"" . $templates->get("relas_ucp_waiting_bit") . "\";");
  }
  if ($waitcnt > 0) {
    eval("\$relas_ucp_waiting = \"" . $templates->get("relas_ucp_waiting") . "\";");
  }
  //Haben dich eingetragen aber du sie nicht
  $not_added = $db->write_query("SELECT * FROM " . TABLE_PREFIX . "relas_entries WHERE r_to = {$thisuser} AND r_accepted = 1 AND r_from not in (SELECT r_to FROM " . TABLE_PREFIX . "relas_entries WHERE r_from =  {$thisuser})");
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
  $relas_ucp_denied = "";
  //TODO abgelehnte Anfragen -> r_accepted = -1 -> ändern und erneut schicken oder löschen
  //Haben dich eingetragen aber du sie nicht
  $denied_query = $db->write_query("SELECT * FROM " . TABLE_PREFIX . "relas_entries WHERE r_from = '{$thisuser}' AND r_accepted = -1");
  if ($db->num_rows($denied_query) > 0) {
    $relas_ucp_notaddedbit = "";
    while ($denied = $db->fetch_array($denied_query)) {
      $user = get_user($denied['r_from']);
      $username = build_profile_link($user['username'], $user['uid']);
      //Wir wollen die Unterkategorie
      $subcat_add = $db->fetch_array($db->simple_select("relas_subcategories", "*", "sc_id = {$denied['r_kategorie']}"));
      $subkategorie = $subcat_add['sc_name'];
      //Zeige Kommentar zur Beziehung, wenn vorhanden.
      if ($notadd['r_kommentar'] != "") {
        $kommentar = "	<div class=\"ucprelas-requestuser__item kommentar\">{$denied['r_kommentar']} </div>";
      } else {
        $kommentar = "";
      }
      $get_cats = $db->simple_select("relas_categories", "*", "c_uid = {$thisuser}");
      $cats = "<select name=\"kategorie\" size=\"3\" id=\"kategorien\" required>";
      while ($cat = $db->fetch_array($get_cats)) {
        $cats .= "<optgroup label=\"{$cat['c_name']}\">";
        $get_subcats = $db->simple_select("relas_subcategories", "*", "sc_cid={$cat['c_id']}");
        while ($subcat = $db->fetch_array($get_subcats)) {
          if ($subcat['sc_id'] == $denied['r_kategorie']) {
            $selected = "SELECTED";
          } else {
            $selected = "";
          }
          $cats .= "<option value=\"{$subcat['sc_id']}\" {$selected}>{$subcat['sc_name']}</option>";
        }
      }
      $cats .= "</select>";

      eval("\$relas_ucp_denied_bit .= \"" . $templates->get("relas_ucp_notaddedbit") . "\";");
    }
    eval("\$relas_ucp_denied = \"" . $templates->get("relas_ucp_notadded") . "\";");
  }

  //Akzeptieren
  if ($mybb->get_input('accept') == 1) {
    //input
    $update = array(
      "r_accepted" => 1,
    );
    $rid = $mybb->get_input('rid', MyBB::INPUT_INT);
    $touid = $db->fetch_field($db->simple_select("relas_entries", "r_from", "r_id = {$rid}"), "r_from");
    //Alert losschicken
    if ($opt_accept == 1) {
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
    }

    //speichern
    $db->update_query('relas_entries', $update, "r_id = {$rid}");
    redirect('usercp.php?action=relations');
  }

  //Ablehnen
  if ($mybb->get_input('deny') == 1) {
    //id der Unterkategorie
    $rid = $mybb->get_input('rid', MyBB::INPUT_INT);

    $touid = $db->fetch_field($db->simple_select("relas_entries", "r_from", "r_id = {$rid}"), "r_from");
    //Alert losschicken
    if ($opt_accept == 1) {
      if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
        $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('relation_deny');
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
    $update = array(
      "r_accepted" => -1,
    );
    $db->update_query('relas_entries', $update, "r_id = {$rid}");
    redirect('usercp.php?action=relations');
  }

  //rela hinzufügen - bei bestätigung
  if (isset($mybb->input['addrela'])) {
    if (!relations_checkCats($thisuser)) {
      echo "<script>alert('Du kannst keinen Chara anfragen/hinzufügen, solange du keine Kategorien erstellt hast.')
            window.location = 'usercp.php?action=relations';</script>";
    } else {
      //id der Unterkategorie
      $touser = $mybb->get_input('r_from', MyBB::INPUT_INT);
      $touser_info = get_user($touser);
      $autoaccept = $touser_info['relas_autoaccept'];

      $rela_anfrage = array(
        "r_from" => $thisuser,
        "r_to" => $mybb->get_input('r_from', MyBB::INPUT_INT),
        "r_kategorie" => $db->escape_string($mybb->get_input('kategorie', MyBB::INPUT_STRING)),
        "r_kommentar" => $db->escape_string($mybb->get_input('adddescr', MyBB::INPUT_STRING)),
        "r_sort" => $mybb->get_input('addSort', MyBB::INPUT_INT),
        "r_accepted" => $autoaccept,
        "r_npc" => 0
      );
      $db->insert_query("relas_entries", $rela_anfrage);
      if ($opt_accept == 1) {
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
      }
      redirect('usercp.php?action=relations');
    }
  }

  //rela hinzufügen - einen charakter übers profil hinzufügen
  if (isset($mybb->input['addachar_ucp'])) {
    //id der Unterkategorie
    if (!relations_checkCats($thisuser)) {
      echo "<script>alert('Du kannst keinen Chara anfragen/hinzufügen, solange du keine Kategorien erstellt hast.')
            window.location = 'usercp.php?action=relations';</script>";
    } else {
      $name = $db->escape_string($mybb->get_input('addname', MyBB::INPUT_STRING));
      $query = $db->simple_select("users", "*", "username='{$name}'");
      $uid = $db->fetch_field($query, "uid");

      $touser_info = get_user($uid);
      $autoaccept = $touser_info['relas_autoaccept'];

      $rela_anfrage = array(
        "r_from" => $thisuser,
        "r_to" => $uid,
        "r_kategorie" => $db->escape_string($mybb->get_input('kategorie', MyBB::INPUT_STRING)),
        "r_kommentar" => $db->escape_string($mybb->get_input('addescr', MyBB::INPUT_STRING)),
        "r_sort" => $mybb->get_input('addsort', MyBB::INPUT_INT),
        "r_accepted" => $autoaccept,
        "r_npc" => 0
      );
      $db->insert_query("relas_entries", $rela_anfrage);
      if ($opt_accept == 1) {
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
      }
      redirect('usercp.php?action=relations');
    }
  }

  //eintrag zurücknehmen 
  if (isset($mybb->input['delete'])) {
    //id der Unterkategorie
    $rid = $mybb->get_input('delete', MyBB::INPUT_INT);

    $db->delete_query('relas_entries', "r_id = {$rid}");
    redirect('usercp.php?action=relations');
  }

  //Unterkategorie ändern
  if (isset($mybb->input['reminder'])) {
    //id der Unterkategorie
    $touid = $mybb->get_input('reminder', MyBB::INPUT_INT);

    //Alert losschicken
    if ($opt_accept == 1) {
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
    $subcat_all = $db->simple_select("relas_subcategories", "*", "sc_cid = {$all['c_id']}", array('order_by' => 'sc_sort'));
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
    if (!empty($all['c_name'])) {
      $all_cname = $all['c_name'];
    }
    eval("\$relas_ucp_cats .= \"" . $templates->get("relas_ucp_cats") . "\";");
    eval("\$relas_ucp_tablinks .= \"" . $templates->get("relas_ucp_tablinks") . "\";");
  }

  //Hier wollen wir die Einträge sammeln, die keine Kategorie haben.
  $get_nocats = $db->write_query("SELECT * FROM " . TABLE_PREFIX . "relas_entries WHERE  r_kategorie NOT IN (SELECT sc_id FROM " . TABLE_PREFIX . "relas_subcategories) AND r_from = '{$thisuser}'");
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
  if (isset($mybb->input['e_rela'])) {
    //id der Unterkategorie
    $rid = $mybb->get_input('e_rela_id', MyBB::INPUT_INT);
    $rela_anfrage = array(
      "r_npcname" => $db->escape_string($mybb->get_input('e_rela_npcname', MyBB::INPUT_STRING)),
      "r_npcbirthyear" => $mybb->get_input('e_rela_npcbirthyear', MyBB::INPUT_INT),
      "r_searched" => $mybb->get_input('e_rela_searched', MyBB::INPUT_INT),
      "r_npcimg" => $db->escape_string($mybb->get_input('e_rela_npcimg', MyBB::INPUT_STRING)),
      "r_kategorie" => $db->escape_string($mybb->get_input('kategorie', MyBB::INPUT_STRING)),
      "r_searchurl" => $db->escape_string($mybb->get_input('searchurl', MyBB::INPUT_STRING)),
      "r_kommentar" => $db->escape_string($mybb->get_input('e_rela_kom', MyBB::INPUT_STRING)),
      "r_sort" => $mybb->get_input('e_rela_sort', MyBB::INPUT_INT),
    );
    $db->update_query("relas_entries", $rela_anfrage, "r_id = {$rid}");

    redirect('usercp.php?action=relations');
  }

  //rela löschen
  if ($mybb->get_input('accepted') == 'delete') {
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
  global $db, $templates, $mybb, $relas_ucp_alluser, $cats, $birthyear;
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
      $birthyear = $relauser['r_npcbirthyear'];
      $searchchecked = $relauser['r_searched'];
      if ($searchchecked == 1) {
        $searchchecked_y = "CHECKED";
        $searchchecked_n = "";
      } else {
        $searchchecked_y = "";
        $searchchecked_n = "CHECKED";
      }
      // echo "r_npcbirthyear" . $relauser['r_npcbirthyear'];
      //TODO

      $editname = "<div class =\"model-form\">
                      <label for=\"e_rela_npcname{$rid}\">NPC Name</label>
                      <input id=\"e_rela_npcname{$rid}\" type=\"text\" value=\"{$username}\" name=\"e_rela_npcname\" required>
                      </div>
                      <div class =\"model-form\">
                      <label>Darf übernommen werden?</label>
                      <span style=\"justify-self:start;\">
                        <input type=\"radio\" name=\"e_rela_searched\" {$searchchecked_y} id=\"e_rela_searched_yes\" value=\"1\">
                        <span> Ja</span>
                        <input type=\"radio\" name=\"e_rela_searched\" {$searchchecked_n} id=\"e_rela_searched_no\" value=\"0\">
                        <span> Nein<span>
                        </span>
                      </div>
                      <div class =\"model-form\">
                      <label for=\"e_rela_searchurl{$rid}\">URL zu Gesuch</label>
                      <input type=\"url\" id=\"e_rela_searchurl{$rid}\" value=\"" . $relauser['r_searchurl'] . "\" name=\"searchurl\">
                      </div>
                      <div class =\"model-form\">
                      <label for=\"e_rela_npcbirthyear{$rid}\">NPC Geburtsjahr</label>
                      <input type=\"number\" id=\"e_rela_npcbirthyear{$rid}\" value=\"" . $relauser['r_npcbirthyear'] . "\" name=\"e_rela_npcbirthyear\">
                      </div>";
      //Wenn Bilder für npcs erlaubt:
      if ($opt_npc_img == 1) {
        $userimg = $relauser['r_npcimg'];
        $npc_editimg = " <div class =\"model-form\">
                          <label for=\"e_rela_npcimg{$rid}\">NPC Img</label>
                          <input id=\"e_rela_npcimg{$rid}\" type=\"text\" value=\"{$userimg}\" name=\"e_rela_npcimg\">
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

function relations_checkCats($uid)
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
function relations_user_delete()
{
  global $db, $cache, $mybb, $user;
  $todelete = (int)$user['uid'];
  $username = $db->escape_string($user['username']);
  // $age
  // // mybb_application_ucp_userfields
  // // SELECT substring(value,1,4), value FROM `mybb_application_ucp_userfields` where fieldid = 3 and uid = 3
  $birthyear = $db->fetch_field($db->simple_select("application_ucp_userfields", "substring(value,1,4) as year", "fieldid = 3 and uid = '$todelete'"), "year");
  $update_other_relas = array(
    'r_to' => 0,
    'r_npc' => 1,
    'r_npcname' => $username,
    'r_npcbirthyear' => $birthyear
  );

  $db->update_query('relas_entries', $update_other_relas, "r_to='" . (int)$user['uid'] . "'");
  $db->delete_query('relas_entries', "r_from = " . (int)$user['uid'] . "");
  $db->delete_query('relas_categories', "c_uid = " . (int)$user['uid'] . "");
  $db->delete_query('relas_subcategories', "sc_uid = " . (int)$user['uid'] . "");

  // add_task_log($task, "Reservierungen bereinigt uid war {$user['uid']} {$username}");
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
        $alertContent['fromuser']
      );
    }
    /**
     * Initialize the language, we need the variables $l['myalerts_setting_alertname'] for user cp! 
     * and if need initialize other stuff
     * @return void
     */
    public function init()
    {

      $this->lang->load('relations');
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
        $alertContent['fromuser']
      );
    }
    /**
     * Initialize the language, we need the variables $l['myalerts_setting_alertname'] for user cp! 
     * and if need initialize other stuff
     * @return void
     */
    public function init()
    {
      $this->lang->load('relations');
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
      $this->lang->load('relations');
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
      $this->lang->load('relations');
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

  // $ingameTime = new DateTime($mybb->settings['memberstats_ingamemonat_tag_end'] . "." . $lastingamemonth . "." . $mybb->settings['memberstats_ingamejahr']);
  // scenetracker_ingametime
  $computedAge = "";
  if (isset($mybb->settings['scenetracker_ingametime'])) {
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
  }
  return  $computedAge;
}


/**************************** 
 * 
 *  My Alert Integration
 * 
 * *************************** */
if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
  $plugins->add_hook("global_start", "relations_alert");
}



/*********************
 * UPDATE KRAM
 *********************/

// #####################################
// ### LARAS BIG MAGIC - RPG STUFF MODUL - THE FUNCTIONS ###
// #####################################

// Benutzergruppen-Berechtigungen im ACP
$plugins->add_hook("admin_rpgstuff_permissions", "relations_admin_rpgstuff_permissions");
function relations_admin_rpgstuff_permissions(&$admin_permissions)
{
  global $lang;
  $lang->load('relations');

  $admin_permissions['relations'] = $lang->relations_permission;

  return $admin_permissions;
}


$plugins->add_hook('admin_rpgstuff_update_plugin', "relations_admin_update_plugin");
// relations_admin_update_plugin
function relations_admin_update_plugin(&$table)
{
  global $db, $mybb, $lang;

  $lang->load('rpgstuff_plugin_updates');

  // UPDATE KRAM
  // Update durchführen
  if ($mybb->input['action'] == 'add_update' and $mybb->get_input('plugin') == "relations") {

    //Settings updaten
    relations_add_settings("update");
    rebuild_settings();

    //templates hinzufügen
    relations_addtemplates("update");

    //templates bearbeiten wenn nötig
    relations_replace_templates();

    //Datenbank updaten
    relations_add_db("update");

    //Stylesheet hinzufügen wenn nötig:
    //array mit updates bekommen.
    $update_data_all = relations_stylesheet_update();
    //alle Themes bekommen
    $theme_query = $db->simple_select('themes', 'tid, name');
    require_once MYBB_ADMIN_DIR . "inc/functions_themes.php";

    while ($theme = $db->fetch_array($theme_query)) {
      //wenn im style nicht vorhanden, dann gesamtes css hinzufügen
      $templatequery = $db->write_query("SELECT * FROM `" . TABLE_PREFIX . "themestylesheets` where tid = '{$theme['tid']}' and name ='relations.css'");

      if ($db->num_rows($templatequery) == 0) {
        $css = relations_css($theme['tid']);

        $sid = $db->insert_query("themestylesheets", $css);
        $db->update_query("themestylesheets", array("cachefile" => "relations.css"), "sid = '" . $sid . "'", 1);
        update_theme_stylesheet_list($theme['tid']);
      }

      //testen ob updatestring vorhanden - sonst an css in theme hinzufügen
      $update_data_all = relations_stylesheet_update();
      //array durchgehen mit eventuell hinzuzufügenden strings
      foreach ($update_data_all as $update_data) {
        //hinzuzufügegendes css
        $update_stylesheet = $update_data['stylesheet'];
        //String bei dem getestet wird ob er im alten css vorhanden ist
        $update_string = $update_data['update_string'];
        //updatestring darf nicht leer sein
        if (!empty($update_string)) {
          //checken ob updatestring in css vorhanden ist - dann muss nichts getan werden
          $test_ifin = $db->write_query("SELECT stylesheet FROM " . TABLE_PREFIX . "themestylesheets WHERE tid = '{$theme['tid']}' AND name = 'relations.css' AND stylesheet LIKE '%" . $update_string . "%' ");
          //string war nicht vorhanden
          if ($db->num_rows($test_ifin) == 0) {
            //altes css holen
            $oldstylesheet = $db->fetch_field($db->write_query("SELECT stylesheet FROM " . TABLE_PREFIX . "themestylesheets WHERE tid = '{$theme['tid']}' AND name = 'relations.css'"), "stylesheet");
            //Hier basteln wir unser neues array zum update und hängen das neue css hinten an das alte dran
            $updated_stylesheet = array(
              "cachefile" => $db->escape_string('relations.css'),
              "stylesheet" => $db->escape_string($oldstylesheet . "\n\n" . $update_stylesheet),
              "lastmodified" => TIME_NOW
            );
            $db->update_query("themestylesheets", $updated_stylesheet, "name='relations.css' AND tid = '{$theme['tid']}'");
            echo "In Theme mit der ID {$theme['tid']} wurde CSS hinzugefügt -  $update_string <br>";
          }
        }
        update_theme_stylesheet_list($theme['tid']);
      }
    }
  }

  // Zelle mit dem Namen des Themes
  $table->construct_cell("<b>" . htmlspecialchars_uni("Szenentracker") . "</b>", array('width' => '70%'));

  // Überprüfen, ob Update nötig ist 
  $update_check = relations_is_updated();

  if ($update_check) {
    $table->construct_cell($lang->plugins_actual, array('class' => 'align_center'));
  } else {
    $table->construct_cell("<a href=\"index.php?module=rpgstuff-plugin_updates&action=add_update&plugin=relations\">" . $lang->plugins_update . "</a>", array('class' => 'align_center'));
  }

  $table->construct_row();
}

/**
 * Funktion um CSS nachträglich oder nach einem MyBB Update wieder hinzuzufügen
 */
$plugins->add_hook('admin_rpgstuff_update_stylesheet', "relations_admin_update_stylesheet");
function relations_admin_update_stylesheet(&$table)
{
  global $db, $mybb, $lang;

  $lang->load('rpgstuff_stylesheet_updates');

  require_once MYBB_ADMIN_DIR . "inc/functions_themes.php";

  // HINZUFÜGEN
  if ($mybb->input['action'] == 'add_master' and $mybb->get_input('plugin') == "relations") {

    $css = relations_css();

    $sid = $db->insert_query("themestylesheets", $css);
    $db->update_query("themestylesheets", array("cachefile" => "relations.css"), "sid = '" . $sid . "'", 1);

    $tids = $db->simple_select("themes", "tid");
    while ($theme = $db->fetch_array($tids)) {
      update_theme_stylesheet_list($theme['tid']);
    }

    flash_message($lang->stylesheets_flash, "success");
    admin_redirect("index.php?module=rpgstuff-stylesheet_updates");
  }
  // Zelle mit dem Namen des Themes
  $table->construct_cell("<b>" . htmlspecialchars_uni("Szenentracker-Manager") . "</b>", array('width' => '70%'));

  // Ob im Master Style vorhanden
  $master_check = $db->query("SELECT tid FROM " . TABLE_PREFIX . "themestylesheets 
    WHERE name = 'relations.css' 
    AND tid = 1");

  if ($db->num_rows($master_check) > 0) {
    $masterstyle = true;
  } else {
    $masterstyle = false;
  }

  if (!empty($masterstyle)) {
    $table->construct_cell($lang->stylesheets_masterstyle, array('class' => 'align_center'));
  } else {
    $table->construct_cell("<a href=\"index.php?module=rpgstuff-stylesheet_updates&action=add_master&plugin=relations\">" . $lang->stylesheets_add . "</a>", array('class' => 'align_center'));
  }
  $table->construct_row();
}


/**
 * Stylesheet der eventuell hinzugefügt werden muss
 */
function relations_stylesheet_update()
{
  // Update-Stylesheet
  // wird an bestehende Stylesheets immer ganz am ende hinzugefügt
  //arrays initialisieren
  $update_array_all = array();

  // //array für css welches hinzugefügt werden soll - neuer eintrag in array für jedes neue update
  // $update_array_all[] = array(
  //   'stylesheet' => "",
  //   'update_string' => 'update-userfilter'
  // );

  return $update_array_all;
}

/**
 * Funktion um alte Templates des Plugins bei Bedarf zu aktualisieren
 */
function relations_replace_templates()
{
  global $db;
  //Wir wollen erst einmal die templates, die eventuellverändert werden müssen
  $update_template_all = relations_updated_templates();
  if (!empty($update_template_all)) {
    //diese durchgehen
    foreach ($update_template_all as $update_template) {
      //anhand des templatenames holen
      $old_template_query = $db->simple_select("templates", "tid, template", "title = '" . $update_template['templatename'] . "'");
      //in old template speichern
      while ($old_template = $db->fetch_array($old_template_query)) {
        //was soll gefunden werden? das mit pattern ersetzen (wir schmeißen leertasten, tabs, etc raus)

        if ($update_template['action'] == 'replace') {
          $pattern = relations_createRegexPattern($update_template['change_string']);
        } elseif ($update_template['action'] == 'add') {
          //bei add wird etwas zum template hinzugefügt, wir müssen also testen ob das schon geschehen ist
          $pattern = relations_createRegexPattern($update_template['action_string']);
        } elseif ($update_template['action'] == 'overwrite') {
          $pattern = relations_createRegexPattern($update_template['change_string']);
        }

        //was soll gemacht werden -> momentan nur replace 
        if ($update_template['action'] == 'replace') {
          //wir ersetzen wenn gefunden wird
          if (preg_match($pattern, $old_template['template'])) {
            $template = preg_replace($pattern, $update_template['action_string'], $old_template['template']);
            $update_query = array(
              "template" => $db->escape_string($template),
              "dateline" => TIME_NOW
            );
            $db->update_query("templates", $update_query, "tid='" . $old_template['tid'] . "'");
            echo ("Template -replace- {$update_template['templatename']} in {$old_template['tid']} wurde aktualisiert <br>");
          }
        }
        if ($update_template['action'] == 'add') { //hinzufügen nicht ersetzen
          //ist es schon einmal hinzugefügt wurden? nur ausführen, wenn es noch nicht im template gefunden wird
          if (!preg_match($pattern, $old_template['template'])) {
            $pattern_rep = relations_createRegexPattern($update_template['change_string']);
            $template = preg_replace($pattern_rep, $update_template['action_string'], $old_template['template']);
            $update_query = array(
              "template" => $db->escape_string($template),
              "dateline" => TIME_NOW
            );
            $db->update_query("templates", $update_query, "tid='" . $old_template['tid'] . "'");
            echo ("Template -add- {$update_template['templatename']} in  {$old_template['tid']} wurde aktualisiert <br>");
          }
        }
        if ($update_template['action'] == 'overwrite') { //komplett ersetzen
          //checken ob das bei change string angegebene vorhanden ist - wenn ja wurde das template schon überschrieben, wenn nicht überschreiben wir das ganze template
          if (!preg_match($pattern, $old_template['template'])) {
            $template = $update_template['action_string'];
            $update_query = array(
              "template" => $db->escape_string($template),
              "dateline" => TIME_NOW
            );
            $db->update_query("templates", $update_query, "tid='" . $old_template['tid'] . "'");
            echo ("Template -overwrite- {$update_template['templatename']} in  {$old_template['tid']} wurde aktualisiert <br>");
          }
        }
      }
    }
  }
}


/**
 * Hier werden Templates gespeichert, die im Laufe der Entwicklung aktualisiert wurden
 * @return array - template daten die geupdatet werden müssen
 * templatename: name des templates mit dem was passieren soll
 * change_string: nach welchem string soll im alten template gesucht werden
 * action: Was soll passieren - add: fügt hinzu, replace ersetzt (change)string, overwrite ersetzt gesamtes template
 * action_strin: Der string der eingefügt/mit dem ersetzt/mit dem überschrieben werden soll
 */
function relations_updated_templates()
{
  global $db;

  //data array initialisieren 
  $update_template = array();

  // $update_template[] = array(
  //   "templatename" => 'relations_ucp_main',
  //   "change_string" => ' ',
  //   "action" => 'replace',
  //   "action_string" => '{$relations_ucp_filterscenes}'
  // );

  return $update_template;
}


/**
 * Funktion um ein pattern für preg_replace zu erstellen
 * und so templates zu vergleichen.
 * @return string - pattern für preg_replace zum vergleich
 */
function relations_createRegexPattern($html)
{
  // Entkomme alle Sonderzeichen und ersetze Leerzeichen mit flexiblen Platzhaltern
  $pattern = preg_quote($html, '/');

  // Ersetze Leerzeichen in `class`-Attributen mit `\s+` (flexible Leerzeichen)
  $pattern = preg_replace('/\s+/', '\\s+', $pattern);

  // Passe das Muster an, um Anfang und Ende zu markieren
  return '/' . $pattern . '/si';
}

/**
 * Update Check
 * @return boolean false wenn Plugin nicht aktuell ist
 * überprüft ob das Plugin auf der aktuellen Version ist
 */
function relations_is_updated()
{
  global $db, $mybb;

  //Testen ob im CSS etwas fehlt
  $update_data_all = relations_stylesheet_update();
  //alle Themes bekommen
  $theme_query = $db->simple_select('themes', 'tid, name');
  while ($theme = $db->fetch_array($theme_query)) {
    //wenn im style nicht vorhanden, dann gesamtes css hinzufügen
    $templatequery = $db->write_query("SELECT * FROM `" . TABLE_PREFIX . "themestylesheets` where tid = '{$theme['tid']}' and name ='relations.css'");
    //relations.css ist in keinem style nicht vorhanden
    if ($db->num_rows($templatequery) == 0) {
      echo ("Nicht im {$theme['tid']} vorhanden <br>");
      return false;
    } else {
      //relations.css ist in einem style nicht vorhanden
      //css ist vorhanden, testen ob alle updatestrings vorhanden sind
      $update_data_all = relations_stylesheet_update();
      //array durchgehen mit eventuell hinzuzufügenden strings
      foreach ($update_data_all as $update_data) {
        //String bei dem getestet wird ob er im alten css vorhanden ist
        $update_string = $update_data['update_string'];
        //updatestring darf nicht leer sein
        if (!empty($update_string)) {
          //checken ob updatestring in css vorhanden ist - dann muss nichts getan werden
          $test_ifin = $db->write_query("SELECT stylesheet FROM " . TABLE_PREFIX . "themestylesheets WHERE tid = '{$theme['tid']}' AND name = 'relations.css' AND stylesheet LIKE '%" . $update_string . "%' ");
          //string war nicht vorhanden
          if ($db->num_rows($test_ifin) == 0) {
            echo ("Mindestens Theme {$theme['tid']} muss aktualisiert werden <br>");
            return false;
          }
        }
      }
    }
  }

  //Testen ob eins der Templates aktualisiert werden muss
  //Wir wollen erst einmal die templates, die eventuellverändert werden müssen
  $update_template_all = relations_updated_templates();
  //alle themes durchgehen
  foreach ($update_template_all as $update_template) {
    //entsprechendes Tamplate holen
    $old_template_query = $db->simple_select("templates", "tid, template, sid", "title = '" . $update_template['templatename'] . "'");
    while ($old_template = $db->fetch_array($old_template_query)) {
      //pattern bilden
      if ($update_template['action'] == 'replace') {
        $pattern = relations_createRegexPattern($update_template['change_string']);
        $check = preg_match($pattern, $old_template['template']);
      } elseif ($update_template['action'] == 'add') {
        //bei add wird etwas zum template hinzugefügt, wir müssen also testen ob das schon geschehen ist
        $pattern = relations_createRegexPattern($update_template['action_string']);
        $check = !preg_match($pattern, $old_template['template']);
      } elseif ($update_template['action'] == 'overwrite') {
        //checken ob das bei change string angegebene vorhanden ist - wenn ja wurde das template schon überschrieben
        $pattern = relations_createRegexPattern($update_template['change_string']);
        $check = !preg_match($pattern, $old_template['template']);
      }
      //testen ob der zu ersetzende string vorhanden ist
      //wenn ja muss das template aktualisiert werden.
      if ($check) {
        $templateset = $db->fetch_field($db->simple_select("templatesets", "title", "sid = '{$old_template['sid']}'"), "title");
        echo ("Template {$update_template['templatename']} im Set {$templateset}'(SID: {$old_template['sid']}') muss aktualisiert werden.");
        return false;
      }
    }
  }
  return true;
}
