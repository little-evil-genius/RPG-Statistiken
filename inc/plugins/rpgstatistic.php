<?php
// Direktzugriff auf die Datei aus Sicherheitsgründen sperren
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// HOOKS
$plugins->add_hook('admin_config_settings_change', 'rpgstatistic_settings_change');
$plugins->add_hook('admin_settings_print_peekers', 'rpgstatistic_settings_peek');
$plugins->add_hook('admin_rpgstuff_action_handler', 'rpgstatistic_admin_rpgstuff_action_handler');
$plugins->add_hook('admin_rpgstuff_permissions', 'rpgstatistic_admin_rpgstuff_permissions');
$plugins->add_hook('admin_rpgstuff_menu', 'rpgstatistic_admin_rpgstuff_menu');
$plugins->add_hook('admin_load', 'rpgstatistic_admin_manage');
$plugins->add_hook('admin_rpgstuff_update_stylesheet', 'rpgstatistic_admin_update_stylesheet');
$plugins->add_hook('admin_rpgstuff_update_plugin', 'rpgstatistic_admin_update_plugin');
$plugins->add_hook('global_start', 'rpgstatistic_mybbArray', 0);
$plugins->add_hook('global_intermediate', 'rpgstatistic_global');
$plugins->add_hook('build_forumbits_forum', 'rpgstatistic_forumbits');
$plugins->add_hook('global_intermediate', 'rpgstatistic_global');
$plugins->add_hook('misc_start', 'rpgstatistic_misc');
$plugins->add_hook('fetch_wol_activity_end', 'rpgstatistic_online_activity');
$plugins->add_hook('build_friendly_wol_location_end', 'rpgstatistic_online_location');
 
// Die Informationen, die im Pluginmanager angezeigt werden
function rpgstatistic_info()
{
	return array(
		"name"		=> "RPG-Statistiken",
		"description"	=> "Erweitert das Forum um verschiedene global einsetzbare Statistikwerte.",
		"website"	=> "https://github.com/little-evil-genius/RPG-Statistiken",
		"author"	=> "little.evil.genius",
		"authorsite"	=> "https://storming-gates.de/member.php?action=profile&uid=1712",
		"version"	=> "1.0",
		"compatibility" => "18*"
	);
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin installiert wird (optional).
function rpgstatistic_install(){

    global $db, $cache, $mybb, $lang;

    // SPRACHDATEI
    $lang->load("rpgstatistic");

    // RPG Stuff Modul muss vorhanden sein
    if (!file_exists(MYBB_ADMIN_DIR."/modules/rpgstuff/module_meta.php")) {
		flash_message($lang->rpgstatistic_error_rpgstuff, 'error');
		admin_redirect('index.php?module=config-plugins');
	}

    // Accountswitcher muss vorhanden sein
    if (!function_exists('accountswitcher_is_installed')) {
		flash_message($lang->rpgstatistic_error_accountswitcher, 'error');
		admin_redirect('index.php?module=config-plugins');
	}

    // DATENBANKTABELLE & FELDER
    rpgstatistic_database();

	// EINSTELLUNGEN HINZUFÜGEN
    $maxdisporder = $db->fetch_field($db->query("SELECT MAX(disporder) FROM ".TABLE_PREFIX."settinggroups"), "MAX(disporder)");
	$setting_group = array(
		'name'          => 'rpgstatistic',
		'title'         => 'RPG-Statistiken',
        'description'   => 'Einstellungen für die RPG-Statistiken',
		'disporder'     => $maxdisporder+1,
		'isdefault'     => 0
	);
	$db->insert_query("settinggroups", $setting_group);  
		
    rpgstatistic_settings();
	rebuild_settings();

	// TEMPLATES ERSTELLEN
	// Template Gruppe für jedes Design erstellen
    $templategroup = array(
        "prefix" => "rpgstatistic",
        "title" => $db->escape_string("RPG-Statistiken"),
    );
    $db->insert_query("templategroups", $templategroup);
    rpgstatistic_templates();
    
    // STYLESHEET HINZUFÜGEN
	require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
    // Funktion
    $stylesheet = rpgstatistic_stylesheet();
    $db->insert_query('themestylesheets', $stylesheet);
    cache_stylesheet(1, "rpgstatistic.css", $stylesheet['stylesheet']);
    update_theme_stylesheet_list("1");
}
 
// Funktion zur Überprüfung des Installationsstatus; liefert true zurürck, wenn Plugin installiert, sonst false (optional).
function rpgstatistic_is_installed() {

    global $db;

    if ($db->table_exists("rpgstatistic_charts")) {
        return true;
    }
    return false;

} 
 
// Diese Funktion wird aufgerufen, wenn das Plugin deinstalliert wird (optional).
function rpgstatistic_uninstall() {
    
	global $db, $cache;

    //DATENBANKEN LÖSCHEN
    if($db->table_exists("rpgstatistic_charts"))
    {
        $db->drop_table("rpgstatistic_charts");
    }
    if($db->table_exists("rpgstatistic_variables"))
    {
        $db->drop_table("rpgstatistic_variables");
    }

    // TEMPLATGRUPPE LÖSCHEN
    $db->delete_query("templategroups", "prefix = 'rpgstatistic'");

    // TEMPLATES LÖSCHEN
    $db->delete_query("templates", "title LIKE 'rpgstatistic%'");
    
    // EINSTELLUNGEN LÖSCHEN
    $db->delete_query('settings', "name LIKE 'rpgstatistic%'");
    $db->delete_query('settinggroups', "name = 'rpgstatistic'");
    rebuild_settings();

    // STYLESHEET ENTFERNEN
	require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
	$db->delete_query("themestylesheets", "name = 'rpgstatistic.css'");
	$query = $db->simple_select("themes", "tid");
	while($theme = $db->fetch_array($query)) {
		update_theme_stylesheet_list($theme['tid']);
	}
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin aktiviert wird.
function rpgstatistic_activate() {
    
    require MYBB_ROOT."/inc/adminfunctions_templates.php";

    // VARIABLEN EINFÜGEN
    find_replace_templatesets('headerinclude','#'.preg_quote('{$stylesheets}').'#','<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.js"></script> {$stylesheets}');
    find_replace_templatesets('index', '#'.preg_quote('{$header}').'#', '{$header}{$rpgstatistic_overviewtable}');
    find_replace_templatesets('index', '#'.preg_quote('{$boardstats}').'#', '{$boardstats}{$rpgstatistic_wob}');
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin deaktiviert wird.
function rpgstatistic_deactivate() {
    
    require MYBB_ROOT."/inc/adminfunctions_templates.php";

    // VARIABLEN ENTFERNEN
	find_replace_templatesets("headerinclude", "#".preg_quote('<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.js"></script>')."#i", '', 0);
	find_replace_templatesets("index", "#".preg_quote('{$rpgstatistic_overviewtable}')."#i", '', 0);
    find_replace_templatesets("index", "#".preg_quote('{$rpgstatistic_wob}')."#i", '', 0);
    find_replace_templatesets("forumbit_depth1_cat", "#".preg_quote('{$forum[\'rpgstatistic_wob\']}')."#i", '', 0);
    find_replace_templatesets("forumbit_depth2_forum", "#".preg_quote('{$forum[\'rpgstatistic_wob\']}')."#i", '', 0);
}

######################
### HOOK FUNCTIONS ###
######################

// EINSTELLUNGEN VERSTECKEN
function rpgstatistic_settings_change(){
    
    global $db, $mybb, $rpgstatistic_settings_peeker;

    $result = $db->simple_select('settinggroups', 'gid', "name='rpgstatistic'", array("limit" => 1));
    $group = $db->fetch_array($result);
    $rpgstatistic_settings_peeker = ($mybb->get_input('gid') == $group['gid']) && ($mybb->request_method != 'post');
}
function rpgstatistic_settings_peek(&$peekers){

    global $rpgstatistic_settings_peeker;

    if ($rpgstatistic_settings_peeker) {
        $peekers[] = 'new Peeker($(".setting_rpgstatistic_top"), $("#row_setting_rpgstatistic_top_option"),/1/,true)';
        $peekers[] = 'new Peeker($(".setting_rpgstatistic_wobUser"), $("#row_setting_rpgstatistic_wobUser_db, #row_setting_rpgstatistic_wobUser_limit, #row_setting_rpgstatistic_wobUser_defaultavatar, #row_setting_rpgstatistic_wobUser_guest, #row_setting_rpgstatistic_wobUser_guest_avatar, #row_setting_rpgstatistic_wobUser_forumbit"),/1/,true)';
        $peekers[] = 'new Peeker($(".setting_rpgstatistic_guest"), $("#row_setting_rpgstatistic_guest_avatar, #row_setting_rpgstatistic_defaultavatar"),/1/,true)';
        $peekers[] = 'new Peeker($(".setting_rpgstatistic_guest_avatar"), $("#row_setting_rpgstatistic_defaultavatar"),/1/,true)';
        $peekers[] = 'new Peeker($(".setting_rpgstatistic_overview"), $("#row_setting_rpgstatistic_overview_forums, #row_setting_rpgstatistic_overview_display, #row_setting_rpgstatistic_overview_limit, #row_setting_rpgstatistic_overview_re, #row_setting_rpgstatistic_overview_subject, #row_setting_rpgstatistic_overview_username, #row_setting_rpgstatistic_overview_prefix"),/1/,true)'; 
        $peekers[] = 'new Peeker($(".setting_rpgstatistic_page"), $("#row_setting_rpgstatistic_page_allowgroups, #row_setting_rpgstatistic_page_toplimit, #row_setting_rpgstatistic_page_nav, #row_setting_rpgstatistic_page_menu, #row_setting_rpgstatistic_page_menu_tpl"),/1/,true)'; 
        $peekers[] = 'new Peeker($("#setting_rpgstatistic_page_menu"), $("#row_setting_rpgstatistic_page_menu_tpl"), /^0/, false)';
    }
}

// ADMIN BEREICH - KONFIGURATION //

// action handler fürs acp konfigurieren
function rpgstatistic_admin_rpgstuff_action_handler(&$actions) {
	$actions['rpgstatistic'] = array('active' => 'rpgstatistic', 'file' => 'rpgstatistic');
}

// Benutzergruppen-Berechtigungen im ACP
function rpgstatistic_admin_rpgstuff_permissions(&$admin_permissions) {

	global $lang, $mybb;
	
    $lang->load('rpgstatistic');

    $admin_permissions['rpgstatistic'] = $lang->rpgstatistic_permission;

	return $admin_permissions;
}

// im Menü einfügen
function rpgstatistic_admin_rpgstuff_menu(&$sub_menu) {
    
	global $lang, $mybb;
	
    $lang->load('rpgstatistic');

    $sub_menu[] = [
        "id" => "rpgstatistic",
        "title" => $lang->rpgstatistic_nav,
        "link" => "index.php?module=rpgstuff-rpgstatistic"   
    ];
}

// Statistiken & Variabeln
function rpgstatistic_admin_manage() {

	global $mybb, $db, $lang, $page, $run_module, $action_file, $cache, $parser, $parser_array;

    if ($page->active_action != 'rpgstatistic') {
		return false;
	}

    if ($run_module == 'rpgstuff' && $action_file == 'rpgstatistic') {

        $lang->load('rpgstatistic');

        // Add to page navigation
		$page->add_breadcrumb_item($lang->rpgstatistic_breadcrumb_main, "index.php?module=rpgstuff-rpgstatistic");

        // Diagrammarten
        $typeselect_list = array(
            "0" => $lang->rpgstatistic_chart_form_type_select,
            "1" => $lang->rpgstatistic_chart_form_type_select_bar,
            "2" => $lang->rpgstatistic_chart_form_type_select_pie,
            "3" => $lang->rpgstatistic_chart_form_type_select_pieLegend,
            "4" => $lang->rpgstatistic_chart_form_type_select_word
        );

        // keine Optionen mehr
        $nonefields_list = array(
            "full" => $lang->rpgstatistic_chart_form_fieldNone,
        );

        // Auszählungsdaten
        $application_plugin = ($db->table_exists("application_ucp_fields")) ? $lang->rpgstatistic_chart_form_data_select_applicationfield : '';
        $dataselect_list = array(
            "0" => $lang->rpgstatistic_chart_form_data_select,
            "1" => $lang->rpgstatistic_chart_form_data_select_profilefield,
            "2" => $application_plugin,
            "3" => $lang->rpgstatistic_chart_form_data_select_usergroups
        );
        $dataselect_list = array_diff($dataselect_list, array(""));

        // Gruppenoptionen
        $usergroupsOption_list = array(
            "0" => $lang->rpgstatistic_chart_form_usergroupsOption_select,
            "1" => $lang->rpgstatistic_chart_form_usergroupsOption_select_primary,
            "2" => $lang->rpgstatistic_chart_form_usergroupsOption_select_secondary,
            "3" => $lang->rpgstatistic_chart_form_usergroupsOption_select_both
        );
        
        // ÜBERSICHT DIAGRAMME
		if ($mybb->get_input('action') == "" || !$mybb->get_input('action')) {

            $page->add_breadcrumb_item($lang->rpgstatistic_breadcrumb_overview_charts);
            $page->output_header($lang->rpgstatistic_breadcrumb_main." - ".$lang->rpgstatistic_overview_charts_header);

            $sub_tabs = rpgstatistic_acp_tabmenu();
			$page->output_nav_tabs($sub_tabs, 'overview_charts');

			// Show errors
			if (isset($errors)) {
				$page->output_inline_error($errors);
			}

            // Übersichtsseite
			$form = new Form("index.php?module=rpgstuff-rpgstatistic", "post", "", 1);
            $form_container = new FormContainer($lang->rpgstatistic_overview_charts_container);
            $form_container->output_row_header($lang->rpgstatistic_overview_charts_container_chart, array('style' => 'text-align: left;'));
            $form_container->output_row_header($lang->rpgstatistic_overview_options_container, array('style' => 'text-align: center; width: 10%;'));

            // Alle Diagramme
			$query_charts = $db->query("SELECT * FROM ".TABLE_PREFIX."rpgstatistic_charts
            ORDER BY title ASC, cid ASC
            ");

            while ($chart = $db->fetch_array($query_charts)) {

                // Darstellung (Typ) - Balken, Kreis oder wort/zahl
                if ($chart['type'] == 1) {
                    $type = $lang->rpgstatistic_overview_charts_type_bar;
                } else if ($chart['type'] == 2) {
                    $type = $lang->rpgstatistic_overview_charts_type_pie;
                } else if ($chart['type'] == 3) {
                    $type = $lang->rpgstatistic_overview_charts_type_pieLegend;
                } else if ($chart['type'] == 4) {
                    $type = $lang->rpgstatistic_overview_charts_type_word;                
                }

                // Daten
                if (!empty($chart['field'])) {
                    // wenn Zahl => klassisches Profilfeld
                    if (is_numeric($chart['field'])) {
                        // Name
                        $fieldname = $db->fetch_field($db->simple_select("profilefields", "name", "fid = '".$chart['field']."'"), "name");
                        $fieldsystem = $lang->rpgstatistic_overview_charts_data_profilefield;

                        // Daten Optionen
                        $options_raw = $db->fetch_field($db->simple_select("profilefields", "type", "fid = '".$chart['field']."'"),"type");
                        $expoptions = explode("\n", $options_raw);             
                        unset($expoptions[0]);

                        if (!empty($chart['ignorOption'])) {
                            $ignor_option = array_map('trim', explode(',', $chart['ignorOption']));
                            foreach ($ignor_option as $index) {
                                unset($expoptions[$index]);
                            }             
                        }

                        $data_options = implode(", ", $expoptions);
                    } 
                    // Katjas Steckbriefplugin
                    else {
                        // Name
                        $fieldname = $db->fetch_field($db->simple_select("application_ucp_fields", "label", "fieldname = '".$chart['field']."'"), "label");             
                        $fieldsystem = $lang->rpgstatistic_overview_charts_data_applicationfield;

                        $options_raw = $db->fetch_field($db->simple_select("application_ucp_fields", "options", "fieldname = '".$chart['field']."'"),"options");                                                         
                        $expoptions = array_map('trim', explode(',', $options_raw));

                        if (!empty($chart['ignorOption'])) {
                            $ignor_option = array_map('trim', explode(',', $chart['ignorOption']));
                            foreach ($ignor_option as $index) {
                                unset($expoptions[$index - 1]);
                            }             
                        }

                        $data_options = implode(", ", $expoptions);
                    }

                    // Datenausgabe
                    $dataoption = $lang->sprintf($lang->rpgstatistic_overview_charts_data, $fieldsystem, $fieldname)."<br>".$lang->sprintf($lang->rpgstatistic_overview_charts_data_dataoptions, $data_options);
                } else {

                    // Gruppenzuordnung
                    // primär
                    if($chart['usergroupsOption'] == 1) {
                        $observe_option = $lang->rpgstatistic_overview_charts_usergroupsOption_primary;
                    } 
                    // nur sekundär
                    else if($chart['usergroupsOption'] == 2) {
                        $observe_option = $lang->rpgstatistic_overview_charts_usergroupsOption_secondary;
                    } 
                    // beides
                    else {
                        $observe_option = $lang->rpgstatistic_overview_charts_usergroupsOption_both;           
                    }

                    // Usergruppen Namen
                    $usergroupIDs = explode(",", $chart['usergroups']);
                    $usergroupsData = [];
                    foreach($usergroupIDs as $gid) {				
                        $usergroupsData[] = $db->fetch_field($db->simple_select("usergroups", "title", "gid = '".$gid."'"), "title");           
                    }
           
                    $data_options = implode(", ", $usergroupsData);

                    // Datenausgabe
                    $dataoption = $lang->sprintf($lang->rpgstatistic_overview_charts_data, $lang->rpgstatistic_overview_charts_data_usergroups, $observe_option)."<br>".$lang->sprintf($lang->rpgstatistic_overview_charts_data_dataoptions, $data_options);           
                }

                // Farben-Vorschau           
                if ($chart['type'] != 4) {

                    if ($chart['customProperties'] != 1) {       
                        $colors_array = array_map('trim', explode(',', $chart['colors']));
    
                        $datacolor = "";
                        foreach ($colors_array as $color) {
                            $datacolor .= '<div style="display: flex;justify-content: center;align-items: center;"><div style="height: 11px;width: 10px;background:'.$color.';margin-right: 5px;"></div><div>'.$color.'</div></div>';       
                        }
    
                        $data_colors = "<br><div style=\"display:flex;gap:5px;\">".$lang->sprintf($lang->rpgstatistic_overview_charts_color, $datacolor)."</div>";
                    } else {
                        $colors_string = str_replace(",", ", ", $chart['colors']);
                        $data_colors = "<br>".$lang->sprintf($lang->rpgstatistic_overview_charts_color, $colors_string);           
                    }

                } else {
                    $data_colors = "";           
                }

                // AUSGABE DER INFOS
                $form_container->output_cell("<strong><span style=\"font-size:0.9rem;\"><a href=\"index.php?module=rpgstuff-rpgstatistic&amp;action=edit_chart&amp;cid=".$chart['cid']."\">".$chart['title']."</a></span></strong> 
                ".$lang->sprintf($lang->rpgstatistic_overview_charts_variable, $chart['identification'])."<br />
                ".$lang->sprintf($lang->rpgstatistic_overview_charts_type, $type)."</br>
                ".$dataoption."
                ".$data_colors."
                ");

                // Optionen
				$popup = new PopupMenu("rpgstatistic_charts_".$chart['cid'], $lang->rpgstatistic_overview_options_popup);	
                $popup->add_item(
                    $lang->rpgstatistic_overview_options_popup_edit,
                    "index.php?module=rpgstuff-rpgstatistic&amp;action=edit_chart&amp;cid=".$chart['cid']
                );
                $popup->add_item(
                    $lang->rpgstatistic_overview_options_popup_delete,
                    "index.php?module=rpgstuff-rpgstatistic&amp;action=delete_chart&amp;cid=".$chart['cid']."&amp;my_post_key={$mybb->post_code}", 
					"return AdminCP.deleteConfirmation(this, '".$lang->rpgstatistic_delete_chart_notice."')"
                );
                $form_container->output_cell($popup->fetch(), array('style' => 'text-align: center; width: 10%;'));

                $form_container->construct_row();
            }

            // keine Diagramme bisher
			if($db->num_rows($query_charts) == 0){
                $form_container->output_cell($lang->rpgstatistic_overview_charts_noElements, array("colspan" => 2, 'style' => 'text-align: center;'));
                $form_container->construct_row();
			}

            $form_container->end();

            $form->end();
            $page->output_footer();
			exit;
        }

        // HINZUFÜGEN DIAGRAMM
        if ($mybb->get_input('action') == "add_chart") {
            
            if ($mybb->request_method == "post") {

                $errors = rpgstatistic_validate_chart_form();

                // No errors - insert
                if (empty($errors)) {

                    if (!empty($mybb->get_input('usergroups', MyBB::INPUT_ARRAY))) {
                        $usergroups = implode(",", $mybb->get_input('usergroups', MyBB::INPUT_ARRAY));
                        $usergroupsOption = $mybb->get_input('usergroupsOption');
                        $input_field = "";
                    } else {
                        $usergroups = "";
                        if (!empty($mybb->get_input('applicationfield'))) {
                            $input_field = $mybb->get_input('applicationfield');
                        } else {
                            $input_field = $mybb->get_input('profilefield');
                        }
                        $ignorOption = $mybb->get_input('ignorOption');
                    }

                    if ($mybb->get_input('type') == 4) {
                        $colors = "";
                    } else {
                        $colors = $mybb->get_input('colors');
                    }
    
                    // Daten speichern
                    $new_chart = array(
                        "title" => $db->escape_string($mybb->get_input('title')),
                        "identification" => $db->escape_string($mybb->get_input('identification')),
                        "type" => (int)$mybb->get_input('type'),
                        "field" => $db->escape_string($input_field),
                        "ignorOption" => $db->escape_string($ignorOption),
                        "usergroups" => $db->escape_string($usergroups),
                        "usergroupsOption" => (int)$usergroupsOption,
                        "colors" => $db->escape_string($colors),
                        "customProperties" => (int)$mybb->get_input('customProperties'),
                    );                    
                    
                    $db->insert_query("rpgstatistic_charts", $new_chart);

                    log_admin_action($mybb->get_input('title'));
        
                    flash_message($lang->rpgstatistic_add_chart_flash, 'success');
                    admin_redirect("index.php?module=rpgstuff-rpgstatistic");
                }
            }

            $page->add_breadcrumb_item($lang->rpgstatistic_breadcrumb_add_charts);
			$page->output_header($lang->rpgstatistic_breadcrumb_main." - ".$lang->rpgstatistic_add_chart_header);

            $sub_tabs = rpgstatistic_acp_tabmenu();
            $page->output_nav_tabs($sub_tabs, 'add_chart');

			// Show errors
			if (isset($errors)) {
				$page->output_inline_error($errors);
			}

            // DATEN AUSLESEN
            // Profilfelder auslesen
            $query_profilefield = $db->query("SELECT * FROM ".TABLE_PREFIX."profilefields
            WHERE fid NOT IN (SELECT field FROM ".TABLE_PREFIX."rpgstatistic_charts WHERE field != '')
            AND (type LIKE 'select%'
            OR type LIKE 'radio%'
            OR type LIKE 'multiselect%'
            OR type LIKE 'checkbox%')
            ORDER BY disporder ASC, name ASC
            ");
        
            $profilefield_list = [];
            while($fields = $db->fetch_array($query_profilefield)) {
                $profilefield_list[$fields['fid']] = $fields['name'];
            }
    
            // Gruppen
            $query_usedgroups = $db->query("SELECT usergroups FROM ".TABLE_PREFIX."rpgstatistic_charts
            WHERE usergroups != ''
            ");
            $usedgroups = [];
            while($usedg = $db->fetch_array($query_usedgroups)) {
                $usedgroups[] = $usedg['usergroups'];
            }
            if (count($usedgroups) > 0) {
                $usergroup_sql = "WHERE gid NOT IN (".implode(",",$usedgroups).") AND gid != '1'";
            } else {
                $usergroup_sql = "WHERE gid != '1'";
            }
            // Benutzergruppen auslesen
            $query_usergroups = $db->query("SELECT gid, title FROM ".TABLE_PREFIX."usergroups
            ".$usergroup_sql."
            ORDER BY disporder ASC   
            ");
            $usergroups_list = [];
            while($group = $db->fetch_array($query_usergroups)) {
                $usergroups_list[$group['gid']] = $group['title'];
            }

            // Build the form
            $form = new Form("index.php?module=rpgstuff-rpgstatistic&amp;action=add_chart", "post", "", 1);
            $form_container = new FormContainer($lang->rpgstatistic_add_chart_container);
            echo $form->generate_hidden_field("my_post_key", $mybb->post_code);
    
            // Title
            $form_container->output_row(
                $lang->rpgstatistic_chart_form_title,
                $lang->rpgstatistic_chart_form_title_desc,
                $form->generate_text_box('title', $mybb->get_input('title'))
            );

            // Identifikator
            $form_container->output_row(
                $lang->rpgstatistic_chart_form_identification,
                $lang->rpgstatistic_chart_form_identification_desc,
                $form->generate_text_box('identification', $mybb->get_input('identification'))
            );
    
            // Diagrammtyp
            $form_container->output_row(
                $lang->rpgstatistic_chart_form_type,
                $lang->rpgstatistic_chart_form_type_desc,
                $form->generate_select_box('type', $typeselect_list, $mybb->get_input('type'), array('id' => 'type')),
				'type'
            );
            // Diagrammfarben  
            $color_options = array(
                "<small class=\"input\">".$lang->rpgstatistic_chart_form_colors_desc."</small><br />".
                $form->generate_text_area('colors', $mybb->get_input('colors'), array('id' => 'colors')),
                $form->generate_check_box("customProperties", 1, $lang->rpgstatistic_chart_form_colors_variables, array("checked" => $mybb->get_input('customProperties'))),
            );
            $form_container->output_row(
                $lang->rpgstatistic_chart_form_colors, 
                "", 
                "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $color_options)."</div>",
				'colors', array(), array('id' => 'row_colors')
            );

            // Feld oder Usergruppe
            $form_container->output_row(
                $lang->rpgstatistic_chart_form_data,
                $lang->rpgstatistic_chart_form_data_desc,
                $form->generate_select_box('data', $dataselect_list, $mybb->get_input('data'), array('id' => 'data')),
				'data'
            );

            // PROFIL-/STECKBRIEFFELDER
            // Steckbrieffelder
            if ($db->table_exists("application_ucp_fields")) {
        
                // Passende Profilfelder auslesen
                $query_applicationfields = $db->query("SELECT * FROM ".TABLE_PREFIX."application_ucp_fields
                WHERE fieldtyp IN ('select','radio','multiselect','select_multiple','checkbox')
                AND fieldname NOT IN (SELECT field FROM ".TABLE_PREFIX."rpgstatistic_charts WHERE field != '')
                ORDER BY sorting ASC, label ASC       
                ");
                $applicationfield_list = [];
                while($fields = $db->fetch_array($query_applicationfields)) {
                    $applicationfield_list[$fields['fieldname']] = $fields['label'];
                }

                $count_applicationfields = $db->num_rows($query_applicationfields);
                if ($count_applicationfields > 0) {
                    $form_container->output_row(
                        $lang->rpgstatistic_chart_form_applicationfield, 
                        $lang->rpgstatistic_chart_form_field_desc, 
                        $form->generate_select_box('applicationfield', $applicationfield_list, $mybb->get_input('applicationfield'), array('id' => 'applicationfield', 'size' => 5)),
                        'applicationfield', array(), array('id' => 'row_applicationfield')
                    );
                } else {
                    $form_container->output_row(
                        $lang->rpgstatistic_chart_form_applicationfield,
                        $lang->rpgstatistic_chart_form_field_desc,
                        $form->generate_select_box('applicationfield', $nonefields_list, $mybb->get_input('applicationfield'), array('id' => 'applicationfield')),
                        'applicationfield', array(), array('id' => 'row_applicationfield')
                    );
                }
            }
            // Profilfelder
            $count_profilefield = $db->num_rows($query_profilefield);
            if ($count_profilefield > 0) {
                $form_container->output_row(
                    $lang->rpgstatistic_chart_form_profilefield, 
                    $lang->rpgstatistic_chart_form_field_desc, 
                    $form->generate_select_box('profilefield', $profilefield_list, $mybb->get_input('profilefield'), array('id' => 'profilefield', 'size' => 5)),
                    'profilefield', array(), array('id' => 'row_profilefield')
                );
            } else {
                $form_container->output_row(
                    $lang->rpgstatistic_chart_form_profilefield,
                    $lang->rpgstatistic_chart_form_field_desc,
                    $form->generate_select_box('profilefield', $nonefields_list, $mybb->get_input('profilefield'), array('id' => 'profilefield')),
                    'profilefield', array(), array('id' => 'row_profilefield')
                );
            }
            // Auszuschließende Optionen
            $form_container->output_row(
                $lang->rpgstatistic_chart_form_ignorOption,
                $lang->rpgstatistic_chart_form_ignorOption_desc,
                $form->generate_text_box('ignorOption', $mybb->get_input('ignorOption'), array('id' => 'ignorOption')), 
                'ignorOption', array(), array('id' => 'row_ignorOption')
            );

            // BENUTZERGRUPPEN
            $form_container->output_row(
                $lang->rpgstatistic_chart_form_usergroups, 
                $lang->rpgstatistic_chart_form_usergroups_desc, 
                $form->generate_select_box('usergroups[]', $usergroups_list, $mybb->get_input('usergroups', MyBB::INPUT_ARRAY), array('id' => 'usergroups', 'multiple' => true, 'size' => 5)),
                'usergroups', array(), array('id' => 'row_usergroups')
            );
            // Gruppenzugehörigkeit
            $form_container->output_row(
                $lang->rpgstatistic_chart_form_usergroupsOption,
                $lang->rpgstatistic_chart_form_usergroupsOption_desc,
                $form->generate_select_box('usergroupsOption', $usergroupsOption_list, $mybb->get_input('usergroupsOption'), array('id' => 'usergroupsOption')),
				'usergroupsOption', array(), array('id' => 'row_usergroupsOption')
            );

            $form_container->end();
            $buttons[] = $form->generate_submit_button($lang->rpgstatistic_add_chart_button);
            $form->output_submit_wrapper($buttons);
            $form->end();

            echo '<script type="text/javascript" src="./jscripts/peeker.js?ver=1821"></script>
			<script type="text/javascript">
			$(function() {
                new Peeker($("#data"), $("#row_usergroups, #row_usergroupsOption"), /^3/, false);
                new Peeker($("#data"), $("#row_applicationfield"), /^2/, false);
                new Peeker($("#data"), $("#row_profilefield"), /^1/, false);
                new Peeker($("#data"), $("#row_ignorOption"), /^1|^2/, false);
                new Peeker($("#type"), $("#row_colors"), /^1|^2|^3/, false);
				});
				</script>';

            $page->output_footer();
            exit;
        }

        // BEARBEITEN DIAGRAMM
        if ($mybb->get_input('action') == "edit_chart") {

            // Get the data
            $cid = $mybb->get_input('cid', MyBB::INPUT_INT);
            $chart_query = $db->simple_select("rpgstatistic_charts", "*", "cid = '".$cid."'");
            $chart = $db->fetch_array($chart_query);

            if ($mybb->request_method == "post") {

                $errors = rpgstatistic_validate_chart_form($mybb->get_input('cid', MyBB::INPUT_INT));

                if (empty($errors)) {

                    $cid = $mybb->get_input('cid', MyBB::INPUT_INT);

                    if (!empty($mybb->get_input('usergroups', MyBB::INPUT_ARRAY))) {
                        $usergroups = implode(",", $mybb->get_input('usergroups', MyBB::INPUT_ARRAY));
                        $usergroupsOption = $mybb->get_input('usergroupsOption');
                        $input_field = "";
                    } else {
                        $usergroups = "";
                        if (!empty($mybb->get_input('applicationfield'))) {
                            $input_field = $mybb->get_input('applicationfield');
                        } else {
                            $input_field = $mybb->get_input('profilefield');
                        }
                        $ignorOption = $mybb->get_input('ignorOption');
                    }

                    if ($mybb->get_input('type') == 4) {
                        $colors = "";
                    } else {
                        $colors = $mybb->get_input('colors');
                    }
    
                    // Daten speichern
                    $update_chart = array(
                        "title" => $db->escape_string($mybb->get_input('title')),
                        "identification" => $db->escape_string($mybb->get_input('identification')),
                        "type" => (int)$mybb->get_input('type'),
                        "field" => $db->escape_string($input_field),
                        "ignorOption" => $db->escape_string($ignorOption),
                        "usergroups" => $db->escape_string($usergroups),
                        "usergroupsOption" => (int)$usergroupsOption,
                        "colors" => $db->escape_string($colors),
                        "customProperties" => (int)$mybb->get_input('customProperties'),
                    );                 
                    $db->update_query("rpgstatistic_charts", $update_chart, "cid='".$cid."'");

                    log_admin_action($mybb->get_input('title'));
        
                    flash_message($lang->rpgstatistic_edit_chart_flash, 'success');
                    admin_redirect("index.php?module=rpgstuff-rpgstatistic");
                }
            }

            $page->add_breadcrumb_item($lang->rpgstatistic_breadcrumb_edit_charts);
			$page->output_header($lang->rpgstatistic_breadcrumb_main." - ".$lang->rpgstatistic_edit_chart_header);

            $sub_tabs = rpgstatistic_acp_tabmenu();
            $sub_tabs['edit_chart'] = [
                "title" => $lang->rpgstatistic_tabs_edit_charts,
                "link" => "index.php?module=rpgstuff-rpgstatistic&amp;action=edit_chart&amp;cid=".$chart['cid'],
                "description" => $lang->sprintf($lang->rpgstatistic_tabs_edit_charts_desc, $chart['title'])
            ];
            $page->output_nav_tabs($sub_tabs, 'edit_chart');

			// Show errors
			if (isset($errors)) {
				$page->output_inline_error($errors);
				$title = $mybb->get_input('title');
				$identification = $mybb->get_input('identification');
				$type = $mybb->get_input('type', MyBB::INPUT_INT);
                $field = $mybb->get_input('field');
                $ignorOption = $mybb->get_input('ignorOption');
                $usergroups = $mybb->get_input('usergroups', MyBB::INPUT_ARRAY);
                $usergroupsOption = $mybb->get_input('usergroupsOption');
                $colors = $mybb->get_input('colors');
				$customProperties = $mybb->get_input('customProperties', MyBB::INPUT_INT);
                $data = $mybb->get_input('data');
                
                if ($data == 1) {
                    $field = $mybb->get_input('profilefield');
                }
                if ($data == 2) {
                    $field = $mybb->get_input('applicationfield');
                }
			} else {
				$title = $chart['title'];
				$identification = $chart['identification'];
				$type = $chart['type'];
                $field = $chart['field'];
                $ignorOption = $chart['ignorOption'];
                $usergroups = array_map('trim', explode(',', $chart['usergroups']));
                $usergroupsOption = $chart['usergroupsOption'];
                $colors = $chart['colors'];
				$customProperties = $chart['customProperties'];

                if (!empty($field)) {
                    if (is_numeric($field)) {
                        $data = 1;
                    } else {
                        $data = 2;
                    }
                } else {
                    $data = 3;
                }
            }

            // DATEN AUSLESEN
            // Profilfelder auslesen
            $query_profilefield = $db->query("SELECT * FROM ".TABLE_PREFIX."profilefields
            WHERE fid NOT IN (SELECT field FROM ".TABLE_PREFIX."rpgstatistic_charts WHERE field != '' AND cid != '".$cid."')
            AND (type LIKE 'select%'
            OR type LIKE 'radio%'
            OR type LIKE 'multiselect%'
            OR type LIKE 'checkbox%')
            ORDER BY disporder ASC, name ASC
            ");
        
            $profilefield_list = [];
            while($fields = $db->fetch_array($query_profilefield)) {
                $profilefield_list[$fields['fid']] = $fields['name'];
            }
    
            // Gruppen
            $query_usedgroups = $db->query("SELECT usergroups FROM ".TABLE_PREFIX."rpgstatistic_charts
            WHERE usergroups != ''
            AND cid != '".$cid."'
            ");
            $usedgroups = [];
            while($usedg = $db->fetch_array($query_usedgroups)) {
                $usedgroups[] = $usedg['usergroups'];
            }
            if (count($usedgroups) > 0) {
                $usergroup_sql = "WHERE gid NOT IN (".implode(",",$usedgroups).") AND gid != '1'";
            } else {
                $usergroup_sql = "WHERE gid != '1'";
            }
            // Benutzergruppen auslesen
            $query_usergroups = $db->query("SELECT gid, title FROM ".TABLE_PREFIX."usergroups
            ".$usergroup_sql."
            ORDER BY disporder ASC   
            ");
            $usergroups_list = [];
            while($group = $db->fetch_array($query_usergroups)) {
                $usergroups_list[$group['gid']] = $group['title'];
            }
    
            // Build the form
            $form = new Form("index.php?module=rpgstuff-rpgstatistic&amp;action=edit_chart", "post", "", 1);
            $form_container = new FormContainer($lang->sprintf($lang->rpgstatistic_edit_chart_container, $chart['title']));
            echo $form->generate_hidden_field("my_post_key", $mybb->post_code);
            echo $form->generate_hidden_field("cid", $cid);
    
            // Title
            $form_container->output_row(
                $lang->rpgstatistic_chart_form_title,
                $lang->rpgstatistic_chart_form_title_desc,
                $form->generate_text_box('title', $title)
            );

            // Identifikator
            $form_container->output_row(
                $lang->rpgstatistic_chart_form_identification,
                $lang->rpgstatistic_chart_form_identification_desc,
                $form->generate_text_box('identification', $identification)
            );
    
            // Diagrammtyp
            $form_container->output_row(
                $lang->rpgstatistic_chart_form_type,
                $lang->rpgstatistic_chart_form_type_desc,
                $form->generate_select_box('type', $typeselect_list, $type, array('id' => 'type')),
				'type'
            );
            // Diagrammfarben  
            $color_options = array(
                "<small class=\"input\">".$lang->rpgstatistic_chart_form_colors_desc."</small><br />".
                $form->generate_text_area('colors', $colors, array('id' => 'colors')),
                $form->generate_check_box("customProperties", 1, $lang->rpgstatistic_chart_form_colors_variables, array("checked" => $customProperties)),
            );
            $form_container->output_row(
                $lang->rpgstatistic_chart_form_colors, 
                "", 
                "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $color_options)."</div>",
				'colors', array(), array('id' => 'row_colors')
            );

            // Feld oder Usergruppe
            $form_container->output_row(
                $lang->rpgstatistic_chart_form_data,
                $lang->rpgstatistic_chart_form_data_desc,
                $form->generate_select_box('data', $dataselect_list, $data, array('id' => 'data')),
				'data'
            );

            // PROFIL-/STECKBRIEFFELDER
            // Steckbrieffelder
            if ($db->table_exists("application_ucp_fields")) {
        
                // Passende Profilfelder auslesen
                $query_applicationfields = $db->query("SELECT * FROM ".TABLE_PREFIX."application_ucp_fields
                WHERE fieldtyp IN ('select','radio','multiselect','select_multiple','checkbox')
                AND fieldname NOT IN (SELECT field FROM ".TABLE_PREFIX."rpgstatistic_charts WHERE field != '' AND cid != '".$cid."')
                ORDER BY sorting ASC, label ASC       
                ");
                $applicationfield_list = [];
                while($fields = $db->fetch_array($query_applicationfields)) {
                    $applicationfield_list[$fields['fieldname']] = $fields['label'];
                }

                $count_applicationfields = $db->num_rows($query_applicationfields);
                if ($count_applicationfields > 0) {
                    $form_container->output_row(
                        $lang->rpgstatistic_chart_form_applicationfield, 
                        $lang->rpgstatistic_chart_form_field_desc, 
                        $form->generate_select_box('applicationfield', $applicationfield_list, $field, array('id' => 'applicationfield', 'size' => 5)),
                        'applicationfield', array(), array('id' => 'row_applicationfield')
                    );
                } else {
                    $form_container->output_row(
                        $lang->rpgstatistic_chart_form_applicationfield,
                        $lang->rpgstatistic_chart_form_field_desc,
                        $form->generate_select_box('applicationfield', $nonefields_list, $field, array('id' => 'applicationfield')),
                        'applicationfield', array(), array('id' => 'row_applicationfield')
                    );
                }
            }
            // Profilfelder
            $count_profilefield = $db->num_rows($query_profilefield);
            if ($count_profilefield > 0) {
                $form_container->output_row(
                    $lang->rpgstatistic_chart_form_profilefield, 
                    $lang->rpgstatistic_chart_form_field_desc, 
                    $form->generate_select_box('profilefield', $profilefield_list, $field, array('id' => 'profilefield', 'size' => 5)),
                    'profilefield', array(), array('id' => 'row_profilefield')
                );
            } else {
                $form_container->output_row(
                    $lang->rpgstatistic_chart_form_profilefield,
                    $lang->rpgstatistic_chart_form_field_desc,
                    $form->generate_select_box('profilefield', $nonefields_list, $field, array('id' => 'profilefield')),
                    'profilefield', array(), array('id' => 'row_profilefield')
                );
            }
            // Auszuschließende Optionen
            $form_container->output_row(
                $lang->rpgstatistic_chart_form_ignorOption,
                $lang->rpgstatistic_chart_form_ignorOption_desc,
                $form->generate_text_box('ignorOption', $ignorOption, array('id' => 'ignorOption')), 
                'ignorOption', array(), array('id' => 'row_ignorOption')
            );

            // BENUTZERGRUPPEN
            $form_container->output_row(
                $lang->rpgstatistic_chart_form_usergroups, 
                $lang->rpgstatistic_chart_form_usergroups_desc, 
                $form->generate_select_box('usergroups[]', $usergroups_list, $usergroups, array('id' => 'usergroups', 'multiple' => true, 'size' => 5)),
                'usergroups', array(), array('id' => 'row_usergroups')
            );
            // Gruppenzugehörigkeit
            $form_container->output_row(
                $lang->rpgstatistic_chart_form_usergroupsOption,
                $lang->rpgstatistic_chart_form_usergroupsOption_desc,
                $form->generate_select_box('usergroupsOption', $usergroupsOption_list, $usergroupsOption, array('id' => 'usergroupsOption')),
				'usergroupsOption', array(), array('id' => 'row_usergroupsOption')
            );

            $form_container->end();
            $buttons[] = $form->generate_submit_button($lang->rpgstatistic_edit_chart_button);
            $form->output_submit_wrapper($buttons);
            $form->end();

            echo '<script type="text/javascript" src="./jscripts/peeker.js?ver=1821"></script>
			<script type="text/javascript">
			$(function() {
                new Peeker($("#data"), $("#row_usergroups, #row_usergroupsOption"), /^3/, false);
                new Peeker($("#data"), $("#row_applicationfield"), /^2/, false);
                new Peeker($("#data"), $("#row_profilefield"), /^1/, false);
                new Peeker($("#data"), $("#row_ignorOption"), /^1|^2/, false);
                new Peeker($("#type"), $("#row_colors"), /^1|^2|^3/, false);
				});
				</script>';

            $page->output_footer();
            exit;
        }

        // LÖSCHEN DIAGRAMM
        if ($mybb->get_input('action') == "delete_chart") {
            
            // Get the data
            $cid = $mybb->get_input('cid', MyBB::INPUT_INT);

			// Error Handling
			if (empty($cid)) {
				flash_message($lang->rpgstatistic_error_invalid, 'error');
				admin_redirect("index.php?module=rpgstuff-rpgstatistic");
			}

			// Cancel button pressed?
			if (isset($mybb->input['no']) && $mybb->input['no']) {
				admin_redirect("index.php?module=rpgstuff-rpgstatistic");
			}

			if ($mybb->request_method == "post") {

                // Eintrag in der DB löschen
                $db->delete_query('rpgstatistic_charts', "cid = '".$cid."'");

				flash_message($lang->rpgstatistic_delete_chart_flash, 'success');
				admin_redirect("index.php?module=rpgstuff-rpgstatistic");
			} else {
				$page->output_confirm_action(
					"index.php?module=rpgstuff-rpgstatistic&amp;action=delete_chart&amp;cid=".$cid,
					$lang->rpgstatistic_delete_chart_notice
				);
			}
			exit;
        }

        // ÜBERSICHT VARIABELN
        if ($mybb->get_input('action') == "variables") {

            $page->add_breadcrumb_item($lang->rpgstatistic_breadcrumb_overview_variables);
			$page->output_header($lang->rpgstatistic_breadcrumb_main." - ".$lang->rpgstatistic_overview_variables_header);

            $sub_tabs = rpgstatistic_acp_tabmenu();
			$page->output_nav_tabs($sub_tabs, 'overview_variables');

			// Show errors
			if (isset($errors)) {
				$page->output_inline_error($errors);
			}

            // Übersichtsseite
			$form = new Form("index.php?module=rpgstuff-rpgstatistic", "post", "", 1);
            $form_container = new FormContainer($lang->rpgstatistic_overview_variables_container);
            $form_container->output_row_header($lang->rpgstatistic_overview_variables_container_variable, array('style' => 'text-align: left;'));
            $form_container->output_row_header($lang->rpgstatistic_overview_options_container, array('style' => 'text-align: center; width: 10%;'));

            // Alle Variabeln
			$query_variables = $db->query("SELECT * FROM ".TABLE_PREFIX."rpgstatistic_variables
            ORDER BY vid ASC
            ");

            while ($variable = $db->fetch_array($query_variables)) {

                // Daten
                if (!empty($variable['field'])) {
                    // wenn Zahl => klassisches Profilfeld
                    if (is_numeric($variable['field'])) {
                        // Name
                        $fieldname = $db->fetch_field($db->simple_select("profilefields", "name", "fid = '".$variable['field']."'"), "name");
                        $fieldsystem = $lang->rpgstatistic_overview_variables_data_profilefield;
                    } 
                    // Katjas Steckbriefplugin
                    else {
                        // Name
                        $fieldname = $db->fetch_field($db->simple_select("application_ucp_fields", "label", "fieldname = '".$variable['field']."'"), "label");             
                        $fieldsystem = $lang->rpgstatistic_overview_variables_data_applicationfield;
                    }

                    // Datenausgabe
                    $dataoption = $lang->sprintf($lang->rpgstatistic_overview_variables_data, $fieldsystem, $fieldname);
                    $data_condition = $variable['conditionField'];
                } else {

                    // Gruppenzuordnung
                    // primär
                    if($variable['conditionUsergroup'] == 1) {
                        $condition_option = $lang->rpgstatistic_overview_variables_condition_primary;
                    } 
                    // nur sekundär
                    else if($variable['conditionUsergroup'] == 2) {
                        $condition_option = $lang->rpgstatistic_overview_variables_condition_secondary;
                    } 
                    // beides
                    else {
                        $condition_option = $lang->rpgstatistic_overview_variables_condition_both;           
                    }
                    
                    $usergroupname = $db->fetch_field($db->simple_select("usergroups", "title", "gid = '".$variable['usergroup']."'"), "title");

                    // Datenausgabe
                    $dataoption = $lang->sprintf($lang->rpgstatistic_overview_variables_data, $lang->rpgstatistic_overview_variables_data_usergroups, $usergroupname);
                    $data_condition = $condition_option;
                }

                // AUSGABE DER INFOS
                $form_container->output_cell("<strong><span style=\"font-size:0.9rem;\"><a href=\"index.php?module=rpgstuff-rpgstatistic&amp;action=edit_variable&amp;vid=".$variable['vid']."\">".$variable['title']."</a></span></strong> 
                ".$lang->sprintf($lang->rpgstatistic_overview_variables_variable, $variable['identification'])."<br />
                ".$dataoption."<br>".
                $lang->sprintf($lang->rpgstatistic_overview_variables_data_condition, $data_condition)."
                ");

                // Optionen
				$popup = new PopupMenu("rpgstatistic_variables_".$variable['vid'], $lang->rpgstatistic_overview_options_popup);	
                $popup->add_item(
                    $lang->rpgstatistic_overview_options_popup_edit,
                    "index.php?module=rpgstuff-rpgstatistic&amp;action=edit_variable&amp;vid=".$variable['vid']
                );
                $popup->add_item(
                    $lang->rpgstatistic_overview_options_popup_delete,
                    "index.php?module=rpgstuff-rpgstatistic&amp;action=delete_variable&amp;vid=".$variable['vid']."&amp;my_post_key={$mybb->post_code}", 
					"return AdminCP.deleteConfirmation(this, '".$lang->rpgstatistic_delete_variable_notice."')"
                );
                $form_container->output_cell($popup->fetch(), array('style' => 'text-align: center; width: 10%;'));

                $form_container->construct_row();
            }

            // keine Variabeln bisher
			if($db->num_rows($query_variables) == 0){
                $form_container->output_cell($lang->rpgstatistic_overview_variables_noElements, array("colspan" => 2, 'style' => 'text-align: center;'));
                $form_container->construct_row();
			}

            $form_container->end();

            $form->end();
            $page->output_footer();
			exit;
        }

        // HINZUFÜGEN VARIABLE
        if ($mybb->get_input('action') == "add_variable") {

            if ($mybb->request_method == "post") {

                $errors = rpgstatistic_validate_variable_form();

                if(empty($errors)) {

                    if (!empty($mybb->get_input('usergroup'))) {
                        $usergroup = $mybb->get_input('usergroup');
                        $conditionUsergroup = $mybb->get_input('conditionUsergroup');
                        $input_field = "";
                        $conditionField = "";
                    } else {
                        $usergroup = 0;
                        $conditionUsergroup = 0;
                        if (!empty($mybb->get_input('applicationfield'))) {
                            $input_field = $mybb->get_input('applicationfield');
                        } else {
                            $input_field = $mybb->get_input('profilefield');
                        }
                        $conditionField = $mybb->get_input('conditionField');
                    }
    
                    // Daten speichern
                    $new_variable = array(
                        "title" => $db->escape_string($mybb->get_input('title')),
                        "identification" => $db->escape_string($mybb->get_input('identification')),
                        "field" => $db->escape_string($input_field),
                        "conditionField" => $db->escape_string($conditionField),
                        "usergroup" => (int)$usergroup,
                        "conditionUsergroup" => (int)$conditionUsergroup
                    );                    
                    
                    $db->insert_query("rpgstatistic_variables", $new_variable);

                    log_admin_action($mybb->get_input('title'));
        
                    flash_message($lang->rpgstatistic_add_variable_flash, 'success');
                    admin_redirect("index.php?module=rpgstuff-rpgstatistic&amp;action=variables");
                }
                
            }

            $page->add_breadcrumb_item($lang->rpgstatistic_breadcrumb_add_variables);
			$page->output_header($lang->rpgstatistic_breadcrumb_main." - ".$lang->rpgstatistic_add_variable_header);

            $sub_tabs = rpgstatistic_acp_tabmenu();
            $page->output_nav_tabs($sub_tabs, 'add_variable');

			// Show errors
			if (isset($errors)) {
				$page->output_inline_error($errors);
			}

            // DATEN AUSLESEN
            // Profilfelder auslesen
            $query_profilefield = $db->query("SELECT * FROM ".TABLE_PREFIX."profilefields
            WHERE fid NOT IN (SELECT field FROM ".TABLE_PREFIX."rpgstatistic_variables WHERE field != '')
            AND (type LIKE 'select%'
            OR type LIKE 'radio%'
            OR type LIKE 'multiselect%'
            OR type LIKE 'checkbox%')
            ORDER BY disporder ASC, name ASC
            ");
        
            $profilefield_list = [];
            while($fields = $db->fetch_array($query_profilefield)) {
                $profilefield_list[$fields['fid']] = $fields['name'];
            }
    
            // Gruppen
            $query_usedgroups = $db->query("SELECT usergroup FROM ".TABLE_PREFIX."rpgstatistic_variables
            WHERE usergroup != ''
            ");
            $usedgroups = [];
            while($usedg = $db->fetch_array($query_usedgroups)) {
                $usedgroups[] = $usedg['usergroup'];
            }
            if (count($usedgroups) > 0) {
                $usergroup_sql = "WHERE gid NOT IN (".implode(",",$usedgroups).") AND gid != '1'";
            } else {
                $usergroup_sql = "WHERE gid != '1'";
            }
            // Benutzergruppen auslesen
            $query_usergroups = $db->query("SELECT gid, title FROM ".TABLE_PREFIX."usergroups
            ".$usergroup_sql."
            ORDER BY disporder ASC   
            ");
            $usergroups_list = [];
            while($group = $db->fetch_array($query_usergroups)) {
                $usergroups_list[$group['gid']] = $group['title'];
            }

            // Build the form
            $form = new Form("index.php?module=rpgstuff-rpgstatistic&amp;action=add_variable", "post", "", 1);
            $form_container = new FormContainer($lang->rpgstatistic_add_variable_container);
            echo $form->generate_hidden_field("my_post_key", $mybb->post_code);
    
            // Title
            $form_container->output_row(
                $lang->rpgstatistic_variable_form_title,
                $lang->rpgstatistic_variable_form_title_desc,
                $form->generate_text_box('title', $mybb->get_input('title'))
            );

            // Identifikator
            $form_container->output_row(
                $lang->rpgstatistic_variable_form_identification,
                $lang->rpgstatistic_variable_form_identification_desc,
                $form->generate_text_box('identification', $mybb->get_input('identification'))
            );

            // Feld oder Usergruppe
            $form_container->output_row(
                $lang->rpgstatistic_variable_form_data,
                $lang->rpgstatistic_variable_form_data_desc,
                $form->generate_select_box('data', $dataselect_list, $mybb->get_input('data'), array('id' => 'data')),
				'data'
            );

            // PROFIL-/STECKBRIEFFELDER
            // Steckbrieffelder
            if ($db->table_exists("application_ucp_fields")) {
        
                // Passende Profilfelder auslesen
                $query_applicationfields = $db->query("SELECT * FROM ".TABLE_PREFIX."application_ucp_fields
                WHERE fieldtyp IN ('select','radio','multiselect','select_multiple','checkbox')
                AND fieldname NOT IN (SELECT field FROM ".TABLE_PREFIX."rpgstatistic_variables WHERE field != '')
                ORDER BY sorting ASC, label ASC       
                ");
                $applicationfield_list = [];
                while($fields = $db->fetch_array($query_applicationfields)) {
                    $applicationfield_list[$fields['fieldname']] = $fields['label'];
                }

                $count_applicationfields = $db->num_rows($query_applicationfields);
                if ($count_applicationfields > 0) {
                    $form_container->output_row(
                        $lang->rpgstatistic_variable_form_applicationfield, 
                        $lang->rpgstatistic_variable_form_field_desc, 
                        $form->generate_select_box('applicationfield', $applicationfield_list, $mybb->get_input('applicationfield'), array('id' => 'applicationfield', 'size' => 5)),
                        'applicationfield', array(), array('id' => 'row_applicationfield')
                    );
                } else {
                    $form_container->output_row(
                        $lang->rpgstatistic_variable_form_applicationfield,
                        $lang->rpgstatistic_variable_form_field_desc,
                        $form->generate_select_box('applicationfield', $nonefields_list, $mybb->get_input('applicationfield'), array('id' => 'applicationfield')),
                        'applicationfield', array(), array('id' => 'row_applicationfield')
                    );
                }
            }
            // Profilfelder
            $count_profilefield = $db->num_rows($query_profilefield);
            if ($count_profilefield > 0) {
                $form_container->output_row(
                    $lang->rpgstatistic_variable_form_profilefield, 
                    $lang->rpgstatistic_variable_form_field_desc, 
                    $form->generate_select_box('profilefield', $profilefield_list, $mybb->get_input('profilefield'), array('id' => 'profilefield', 'size' => 5)),
                    'profilefield', array(), array('id' => 'row_profilefield')
                );
            } else {
                $form_container->output_row(
                    $lang->rpgstatistic_variable_form_profilefield,
                    $lang->rpgstatistic_variable_form_field_desc,
                    $form->generate_select_box('profilefield', $nonefields_list, $mybb->get_input('profilefield'), array('id' => 'profilefield')),
                    'profilefield', array(), array('id' => 'row_profilefield')
                );
            }
            // Bedingung
            $form_container->output_row(
                $lang->rpgstatistic_chart_form_conditionField,
                $lang->rpgstatistic_chart_form_conditionField_desc,
                $form->generate_text_box('conditionField', $mybb->get_input('conditionField'), array('id' => 'conditionField')), 
                'conditionField', array(), array('id' => 'row_conditionField')
            );

            // BENUTZERGRUPPEN
            $form_container->output_row(
                $lang->rpgstatistic_variable_form_usergroup, 
                $lang->rpgstatistic_variable_form_usergroup_desc, 
                $form->generate_select_box('usergroup', $usergroups_list, $mybb->get_input('usergroup'), array('id' => 'usergroup', 'size' => 5)),
                    'usergroup', array(), array('id' => 'row_usergroup')
            );
            // Gruppenzugehörigkeit
            $form_container->output_row(
                $lang->rpgstatistic_variable_form_conditionUsergroup,
                $lang->rpgstatistic_variable_form_conditionUsergroup_desc,
                $form->generate_select_box('conditionUsergroup', $usergroupsOption_list, $mybb->get_input('conditionUsergroup'), array('id' => 'usergroupsOption')),
				'conditionUsergroup', array(), array('id' => 'row_conditionUsergroup')
            );

            $form_container->end();
            $buttons[] = $form->generate_submit_button($lang->rpgstatistic_add_chart_button);
            $form->output_submit_wrapper($buttons);
            $form->end();

            echo '<script type="text/javascript" src="./jscripts/peeker.js?ver=1821"></script>
			<script type="text/javascript">
			$(function() {
                new Peeker($("#data"), $("#row_usergroup, #row_conditionUsergroup"), /^3/, false);
                new Peeker($("#data"), $("#row_applicationfield"), /^2/, false);
                new Peeker($("#data"), $("#row_profilefield"), /^1/, false);
                new Peeker($("#data"), $("#row_conditionField"), /^1|^2/, false);
				});
				</script>';

            $page->output_footer();
            exit;
        }

        // BEARBEITEN VARIABLE
        if ($mybb->get_input('action') == "edit_variable") {

            // Get the data
            $vid = $mybb->get_input('vid', MyBB::INPUT_INT);
            $variable_query = $db->simple_select("rpgstatistic_variables", "*", "vid = '".$vid."'");
            $variable = $db->fetch_array($variable_query);

            if ($mybb->request_method == "post") {

                $errors = rpgstatistic_validate_variable_form($mybb->get_input('vid', MyBB::INPUT_INT));

                if(empty($errors)) {

                    $vid = $mybb->get_input('vid', MyBB::INPUT_INT);

                    if (!empty($mybb->get_input('usergroup'))) {
                        $usergroup = $mybb->get_input('usergroup');
                        $conditionUsergroup = $mybb->get_input('conditionUsergroup');
                        $input_field = "";
                        $conditionField = "";
                    } else {
                        $usergroup = 0;
                        $conditionUsergroup = 0;
                        if (!empty($mybb->get_input('applicationfield'))) {
                            $input_field = $mybb->get_input('applicationfield');
                        } else {
                            $input_field = $mybb->get_input('profilefield');
                        }
                        $conditionField = $mybb->get_input('conditionField');
                    }
    
                    // Daten speichern
                    $update_variable = array(
                        "title" => $db->escape_string($mybb->get_input('title')),
                        "identification" => $db->escape_string($mybb->get_input('identification')),
                        "field" => $db->escape_string($input_field),
                        "conditionField" => $db->escape_string($conditionField),
                        "usergroup" => (int)$usergroup,
                        "conditionUsergroup" => (int)$conditionUsergroup
                    );                      
                    $db->update_query("rpgstatistic_variables", $update_variable, "vid='".$vid."'");

                    log_admin_action($mybb->get_input('title'));
        
                    flash_message($lang->rpgstatistic_edit_variable_flash, 'success');
                    admin_redirect("index.php?module=rpgstuff-rpgstatistic&amp;action=variables");
                }
                
            }

            $page->add_breadcrumb_item($lang->rpgstatistic_breadcrumb_edit_variables);
			$page->output_header($lang->rpgstatistic_breadcrumb_main." - ".$lang->rpgstatistic_edit_variable_header);

            $sub_tabs = rpgstatistic_acp_tabmenu();
            $sub_tabs['edit_chart'] = [
                "title" => $lang->rpgstatistic_tabs_edit_variables,
                "link" => "index.php?module=rpgstuff-rpgstatistic&amp;action=edit_chart&amp;vid=".$variable['vid'],
                "description" => $lang->sprintf($lang->rpgstatistic_tabs_edit_variables_desc, $variable['title'])
            ];
            $page->output_nav_tabs($sub_tabs, 'edit_chart');

			// Show errors
			if (isset($errors)) {
				$page->output_inline_error($errors);
				$title = $mybb->get_input('title');
				$identification = $mybb->get_input('identification');
                $data = $mybb->get_input('data');
                $usergroup = $mybb->get_input('usergroup', MyBB::INPUT_INT);
				$conditionUsergroup = $mybb->get_input('conditionUsergroup', MyBB::INPUT_INT);
                
                if ($data == 1) {
                    $field = $mybb->get_input('profilefield');
                }
                if ($data == 2) {
                    $field = $mybb->get_input('applicationfield');
                }
                $conditionField = $mybb->get_input('conditionField');
			} else {
				$title = $variable['title'];
				$identification = $variable['identification'];
                $field = $variable['field'];
                $usergroup = $variable['usergroup'];
				$conditionUsergroup = $variable['conditionUsergroup'];
                $conditionField = $variable['conditionField'];

                if (!empty($field)) {
                    if (is_numeric($field)) {
                        $data = 1;
                    } else {
                        $data = 2;
                    }
                } else {
                    $data = 3;
                }
            }

            // DATEN AUSLESEN
            // Profilfelder auslesen
            $query_profilefield = $db->query("SELECT * FROM ".TABLE_PREFIX."profilefields
            WHERE fid NOT IN (SELECT field FROM ".TABLE_PREFIX."rpgstatistic_variables WHERE field != '' AND vid != '".$vid."')
            AND (type LIKE 'select%'
            OR type LIKE 'radio%'
            OR type LIKE 'multiselect%'
            OR type LIKE 'checkbox%')
            ORDER BY disporder ASC, name ASC
            ");
        
            $profilefield_list = [];
            while($fields = $db->fetch_array($query_profilefield)) {
                $profilefield_list[$fields['fid']] = $fields['name'];
            }
    
            // Gruppen
            $query_usedgroups = $db->query("SELECT usergroup FROM ".TABLE_PREFIX."rpgstatistic_variables
            WHERE usergroup != ''
            AND vid != '".$vid."'
            ");
            $usedgroups = [];
            while($usedg = $db->fetch_array($query_usedgroups)) {
                $usedgroups[] = $usedg['usergroup'];
            }
            if (count($usedgroups) > 0) {
                $usergroup_sql = "WHERE gid NOT IN (".implode(",",$usedgroups).") AND gid != '1'";
            } else {
                $usergroup_sql = "WHERE gid != '1'";
            }
            // Benutzergruppen auslesen
            $query_usergroups = $db->query("SELECT gid, title FROM ".TABLE_PREFIX."usergroups
            ".$usergroup_sql."
            ORDER BY disporder ASC   
            ");
            $usergroups_list = [];
            while($group = $db->fetch_array($query_usergroups)) {
                $usergroups_list[$group['gid']] = $group['title'];
            }

            // Build the form
            $form = new Form("index.php?module=rpgstuff-rpgstatistic&amp;action=edit_variable", "post", "", 1);
            $form_container = new FormContainer($lang->sprintf($lang->rpgstatistic_edit_chart_container, $variable['title']));
            echo $form->generate_hidden_field("my_post_key", $mybb->post_code);
            echo $form->generate_hidden_field("vid", $vid);
    
            // Title
            $form_container->output_row(
                $lang->rpgstatistic_variable_form_title,
                $lang->rpgstatistic_variable_form_title_desc,
                $form->generate_text_box('title', $title)
            );

            // Identifikator
            $form_container->output_row(
                $lang->rpgstatistic_variable_form_identification,
                $lang->rpgstatistic_variable_form_identification_desc,
                $form->generate_text_box('identification', $identification)
            );

            // Feld oder Usergruppe
            $form_container->output_row(
                $lang->rpgstatistic_variable_form_data,
                $lang->rpgstatistic_variable_form_data_desc,
                $form->generate_select_box('data', $dataselect_list, $data, array('id' => 'data')),
				'data'
            );

            // PROFIL-/STECKBRIEFFELDER
            // Steckbrieffelder
            if ($db->table_exists("application_ucp_fields")) {
        
                // Passende Profilfelder auslesen
                $query_applicationfields = $db->query("SELECT * FROM ".TABLE_PREFIX."application_ucp_fields
                WHERE fieldtyp IN ('select','radio','multiselect','select_multiple','checkbox')
                AND fieldname NOT IN (SELECT field FROM ".TABLE_PREFIX."rpgstatistic_variables WHERE field != '' AND vid != '".$vid."')
                ORDER BY sorting ASC, label ASC       
                ");
                $applicationfield_list = [];
                while($fields = $db->fetch_array($query_applicationfields)) {
                    $applicationfield_list[$fields['fieldname']] = $fields['label'];
                }

                $count_applicationfields = $db->num_rows($query_applicationfields);
                if ($count_applicationfields > 0) {
                    $form_container->output_row(
                        $lang->rpgstatistic_variable_form_applicationfield, 
                        $lang->rpgstatistic_variable_form_field_desc, 
                        $form->generate_select_box('applicationfield', $applicationfield_list, $field, array('id' => 'applicationfield', 'size' => 5)),
                        'applicationfield', array(), array('id' => 'row_applicationfield')
                    );
                } else {
                    $form_container->output_row(
                        $lang->rpgstatistic_variable_form_applicationfield,
                        $lang->rpgstatistic_variable_form_field_desc,
                        $form->generate_select_box('applicationfield', $nonefields_list, $field, array('id' => 'applicationfield')),
                        'applicationfield', array(), array('id' => 'row_applicationfield')
                    );
                }
            }
            // Profilfelder
            $count_profilefield = $db->num_rows($query_profilefield);
            if ($count_profilefield > 0) {
                $form_container->output_row(
                    $lang->rpgstatistic_variable_form_profilefield, 
                    $lang->rpgstatistic_variable_form_field_desc, 
                    $form->generate_select_box('profilefield', $profilefield_list, $field, array('id' => 'profilefield', 'size' => 5)),
                    'profilefield', array(), array('id' => 'row_profilefield')
                );
            } else {
                $form_container->output_row(
                    $lang->rpgstatistic_variable_form_profilefield,
                    $lang->rpgstatistic_variable_form_field_desc,
                    $form->generate_select_box('profilefield', $nonefields_list, $field, array('id' => 'profilefield')),
                    'profilefield', array(), array('id' => 'row_profilefield')
                );
            }
            // Bedingung
            $form_container->output_row(
                $lang->rpgstatistic_chart_form_conditionField,
                $lang->rpgstatistic_chart_form_conditionField_desc,
                $form->generate_text_box('conditionField', $conditionField, array('id' => 'conditionField')), 
                'conditionField', array(), array('id' => 'row_conditionField')
            );

            // BENUTZERGRUPPEN
            $form_container->output_row(
                $lang->rpgstatistic_variable_form_usergroup, 
                $lang->rpgstatistic_variable_form_usergroup_desc, 
                $form->generate_select_box('usergroup', $usergroups_list, $usergroup, array('id' => 'usergroup', 'size' => 5)),
                    'usergroup', array(), array('id' => 'row_usergroup')
            );
            // Gruppenzugehörigkeit
            $form_container->output_row(
                $lang->rpgstatistic_variable_form_conditionUsergroup,
                $lang->rpgstatistic_variable_form_conditionUsergroup_desc,
                $form->generate_select_box('conditionUsergroup', $usergroupsOption_list, $conditionUsergroup, array('id' => 'usergroupsOption')),
				'conditionUsergroup', array(), array('id' => 'row_conditionUsergroup')
            );

            $form_container->end();
            $buttons[] = $form->generate_submit_button($lang->rpgstatistic_edit_chart_button);
            $form->output_submit_wrapper($buttons);
            $form->end();

            echo '<script type="text/javascript" src="./jscripts/peeker.js?ver=1821"></script>
			<script type="text/javascript">
			$(function() {
                new Peeker($("#data"), $("#row_usergroup, #row_conditionUsergroup"), /^3/, false);
                new Peeker($("#data"), $("#row_applicationfield"), /^2/, false);
                new Peeker($("#data"), $("#row_profilefield"), /^1/, false);
                new Peeker($("#data"), $("#row_conditionField"), /^1|^2/, false);
				});
				</script>';

            $page->output_footer();
            exit;
        }

        // LÖSCHEN VARIABLE
        if ($mybb->get_input('action') == "delete_variable") {
            
            // Get the data
            $vid = $mybb->get_input('vid', MyBB::INPUT_INT);

			// Error Handling
			if (empty($vid)) {
				flash_message($lang->rpgstatistic_error_invalid, 'error');
				admin_redirect("index.php?module=rpgstuff-rpgstatistic&amp;action=variables");
			}

			// Cancel button pressed?
			if (isset($mybb->input['no']) && $mybb->input['no']) {
				admin_redirect("index.php?module=rpgstuff-rpgstatistic&amp;action=variables");
			}

			if ($mybb->request_method == "post") {
                // Eintrag in der DB löschen
                $db->delete_query('rpgstatistic_variables', "vid = '".$vid."'");

				flash_message($lang->rpgstatistic_delete_variable_flash, 'success');
				admin_redirect("index.php?module=rpgstuff-rpgstatistic&amp;action=variables");
			} else {
                $page->output_confirm_action(
					"index.php?module=rpgstuff-rpgstatistic&amp;action=delete_variable&amp;vid=".$vid,
					$lang->rpgstatistic_delete_variable_notice
				);
			}
			exit;
        }
    }
}

// Stylesheet zum Master Style hinzufügen
function rpgstatistic_admin_update_stylesheet(&$table) {

    global $db, $mybb, $lang;
	
    $lang->load('rpgstuff_stylesheet_updates');

    require_once MYBB_ADMIN_DIR."inc/functions_themes.php";

    // HINZUFÜGEN
    if ($mybb->input['action'] == 'add_master' AND $mybb->get_input('plugin') == "rpgstatistic") {

        $css = rpgstatistic_stylesheet();
        
        $sid = $db->insert_query("themestylesheets", $css);
        $db->update_query("themestylesheets", array("cachefile" => "rpgstatistic.css"), "sid = '".$sid."'", 1);
    
        $tids = $db->simple_select("themes", "tid");
        while($theme = $db->fetch_array($tids)) {
            update_theme_stylesheet_list($theme['tid']);
        } 

        flash_message($lang->stylesheets_flash, "success");
        admin_redirect("index.php?module=rpgstuff-stylesheet_updates");
    }

    // Zelle mit dem Namen des Themes
    $table->construct_cell("<b>".htmlspecialchars_uni("RPG-Statistiken")."</b>", array('width' => '70%'));

    // Ob im Master Style vorhanden
    $master_check = $db->fetch_field($db->query("SELECT tid FROM ".TABLE_PREFIX."themestylesheets 
    WHERE name = 'rpgstatistic.css' 
    AND tid = 1
    "), "tid");
    
    if (!empty($master_check)) {
        $masterstyle = true;
    } else {
        $masterstyle = false;
    }

    if (!empty($masterstyle)) {
        $table->construct_cell($lang->stylesheets_masterstyle, array('class' => 'align_center'));
    } else {
        $table->construct_cell("<a href=\"index.php?module=rpgstuff-stylesheet_updates&action=add_master&plugin=rpgstatistic\">".$lang->stylesheets_add."</a>", array('class' => 'align_center'));
    }
    
    $table->construct_row();
}

// Plugin Update
function rpgstatistic_admin_update_plugin(&$table) {

    global $db, $mybb, $lang;
	
    $lang->load('rpgstuff_plugin_updates');

    // UPDATE
    if ($mybb->input['action'] == 'add_update' AND $mybb->get_input('plugin') == "rpgstatistic") {

        // Einstellungen überprüfen => Type = update
        rpgstatistic_settings('update');
        rebuild_settings();

        // Templates 
        rpgstatistic_templates('update');

        // Stylesheet
        $update_data = rpgstatistic_stylesheet_update();
        $update_stylesheet = $update_data['stylesheet'];
        $update_string = $update_data['update_string'];
        if (!empty($update_string)) {

            // Ob im Master Style die Überprüfung vorhanden ist
            $masterstylesheet = $db->fetch_field($db->query("SELECT stylesheet FROM ".TABLE_PREFIX."themestylesheets WHERE tid = 1 AND name = 'rpgstatistic.css'"), "stylesheet");
            $pos = strpos($masterstylesheet, $update_string);
            if ($pos === false) { // nicht vorhanden 
            
                $theme_query = $db->simple_select('themes', 'tid, name');
                while ($theme = $db->fetch_array($theme_query)) {
        
                    $stylesheet_query = $db->simple_select("themestylesheets", "*", "name='".$db->escape_string('rpgstatistic.css')."' AND tid = ".$theme['tid']);
                    $stylesheet = $db->fetch_array($stylesheet_query);
        
                    if ($stylesheet) {

                        require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
        
                        $sid = $stylesheet['sid'];
            
                        $updated_stylesheet = array(
                            "cachefile" => $db->escape_string($stylesheet['name']),
                            "stylesheet" => $db->escape_string($stylesheet['stylesheet']."\n\n".$update_stylesheet),
                            "lastmodified" => TIME_NOW
                        );
            
                        $db->update_query("themestylesheets", $updated_stylesheet, "sid='".$sid."'");
            
                        if(!cache_stylesheet($theme['tid'], $stylesheet['name'], $updated_stylesheet['stylesheet'])) {
                            $db->update_query("themestylesheets", array('cachefile' => "css.php?stylesheet=".$sid), "sid='".$sid."'", 1);
                        }
            
                        update_theme_stylesheet_list($theme['tid']);
                    }
                }
            } 
        }

        // Datenbanktabellen & Felder
        rpgstatistic_database();

        flash_message($lang->plugins_flash, "success");
        admin_redirect("index.php?module=rpgstuff-plugin_updates");
    }

    // Zelle mit dem Namen des Themes
    $table->construct_cell("<b>".htmlspecialchars_uni("RPG-Statistiken")."</b>", array('width' => '70%'));

    // Überprüfen, ob Update erledigt
    $update_check = rpgstatistic_is_updated();

    if (!empty($update_check)) {
        $table->construct_cell($lang->plugins_actual, array('class' => 'align_center'));
    } else {
        $table->construct_cell("<a href=\"index.php?module=rpgstuff-plugin_updates&action=add_update&plugin=rpgstatistic\">".$lang->plugins_update."</a>", array('class' => 'align_center'));
    }
    
    $table->construct_row();
}

// globales Array $mybb erweitern
function rpgstatistic_mybbArray() {
 
    if (!property_exists($GLOBALS['mybb'], '__rpgstatistic_ready')) {
        class MyBB_Extended extends MyBB {
            public array $rpgstatistic = [];
        }
        $GLOBALS['mybb'] = unserialize(
            preg_replace('/^O:4:"MyBB"/', 'O:13:"MyBB_Extended"', serialize($GLOBALS['mybb']))
        );
    }

}

// Global ausgeben
function rpgstatistic_global() {

    global $mybb, $lang, $templates, $characters_bit, $rpgstatistic_wob, $rpgstatistic_overviewtable, $overviewtableBit;

    $lang->load('rpgstatistic');

    if (!isset($mybb->rpgstatistic)) {
        $mybb->rpgstatistic = [];
    }

    // GLOBAL ABRUFBAR

    $chartData = rpgstatistic_build_charts();
    $variablesData = rpgstatistic_build_variables();
    $birthdayData = rpgstatistic_forumbirthday();
    $userData = rpgstatistic_userstatistic();
    $inplayData = rpgstatistic_inplaystatistic();
    $topData = rpgstatistic_topstatistic();

    $rpgstatisticData = array_merge($chartData, $variablesData, $birthdayData, $inplayData, $userData, $topData);

    foreach ($rpgstatisticData as $key => $value) {
        $mybb->rpgstatistic[$key] = $value;
    }

    // NICHT GLOBAL ABRUFBAR

    $rpgstatistic_wob = "";
    if ($mybb->settings['rpgstatistic_wobUser'] == 1) {
        if ($mybb->settings['rpgstatistic_wobUser_guest'] == 0 && $mybb->user['uid'] == 0) {
            $rpgstatistic_wob = "";
        } else {
            $characters_bit = rpgstatistic_wob();
            eval("\$rpgstatistic_wob = \"".$templates->get("rpgstatistic_wob")."\";");
        }
    }

    $rpgstatistic_overviewtable = "";
    if ($mybb->settings['rpgstatistic_overview'] == 1) {
        $overviewtableBit = rpgstatistic_overview();
        eval("\$rpgstatistic_overviewtable = \"".$templates->get("rpgstatistic_overviewtable")."\";");
    }
}

// WoB Forumbit
function rpgstatistic_forumbits(&$forum) {

    global $db, $mybb, $lang, $templates, $theme, $characters_bit;

    if ($forum['fid'] != $mybb->settings['rpgstatistic_wobUser_forumbit'] || $mybb->settings['rpgstatistic_wobUser'] == 0) {
        $forum['rpgstatistic_wob'] = "";
        return;    
    }

    if ($mybb->settings['rpgstatistic_wobUser_guest'] == 0 && $mybb->user['uid'] == 0) {
        $forum['rpgstatistic_wob'] = "";
        return;    
    }

    $lang->load('rpgstatistic');
    
    $characters_bit = rpgstatistic_wob();
    eval("\$forum['rpgstatistic_wob'] = \"".$templates->get("rpgstatistic_wob")."\";");
}

// Statistikseite
function rpgstatistic_misc() {

    global $db, $mybb, $lang, $templates, $theme, $header, $headerinclude, $footer, $page, $topOption;

    // return if the action key isn't part of the input
    if ($mybb->get_input('action', MYBB::INPUT_STRING) !== 'rpgstatistic' || $mybb->settings['rpgstatistic_page'] == 0) {
        return;
    }

    if (!is_member($mybb->settings['rpgstatistic_page_allowgroups'])) {
        error_no_permission();
    }

    $lang->load('rpgstatistic');

    $listsnav = $mybb->settings['rpgstatistic_page'];
    $listsmenu = $mybb->settings['rpgstatistic_page_menu'];
    $listsmenu_tpl = $mybb->settings['rpgstatistic_page_menu_tpl'];

    if($mybb->get_input('action') == "rpgstatistic"){

		// Listenmenü
		if($listsmenu != 0){
            // Jules Plugin
            if ($listsmenu == 1) {
                $lang->load("lists");
                $query_lists = $db->simple_select("lists", "*");
                $menu_bit = "";
                while($list = $db->fetch_array($query_lists)) {
                    eval("\$menu_bit .= \"".$templates->get("lists_menu_bit")."\";");
                }
                eval("\$lists_menu = \"".$templates->get("lists_menu")."\";");
            } else {
                eval("\$lists_menu = \"".$templates->get($listsmenu_tpl)."\";");
            }
        } else {
            $lists_menu = "";
        }

        // NAVIGATION
		if(!empty($listsnav)){
            add_breadcrumb("Listen", $listsnav);
            add_breadcrumb("RPG-Statistiken", "misc.php?action=rpgstatistic");
		} else{
            add_breadcrumb("RPG-Statistiken", "misc.php?action=rpgstatistic");
		}

        // Forengeburtstag
        $forumbirthday = "";
        if (!empty($mybb->settings['rpgstatistic_forumbirthday'])) {
            $onlyDays = $mybb->rpgstatistic['forumbirthday_days'];
            $fullDate = $mybb->rpgstatistic['forumbirthday_fullDate'];
            $forumbirthdayDate = $mybb->rpgstatistic['forumbirthday'];
            eval("\$forumbirthday = \"".$templates->get("rpgstatistic_page_forumbirthday")."\";");
        }

        // Userstatistik
        $countPlayer = $mybb->rpgstatistic['countPlayer'];
        $countCharacter = $mybb->rpgstatistic['countCharacter'];
        $averageCharacter = $mybb->rpgstatistic['averageCharacter'];

        // Inplaystatistik
        $inplayscenes = $mybb->rpgstatistic['inplayscenes'];
        $inplayposts = $mybb->rpgstatistic['inplayposts'];
        $allCharacters = $mybb->rpgstatistic['allCharacters'];
        $averageCharacters = $mybb->rpgstatistic['averageCharacters'];
        $allWords = $mybb->rpgstatistic['allWords'];
        $averageWords = $mybb->rpgstatistic['averageWords'];

        // Topstatistik
        $topstatistic = "";
        if ($mybb->settings['rpgstatistic_top'] == 1 || $mybb->settings['rpgstatistic_top_option'] != '') {

            $selected_topoptions = explode(',', $mybb->settings['rpgstatistic_top_option']);
            $selected_topoptions = array_map('trim', $selected_topoptions);

            $toplimit = $mybb->settings['rpgstatistic_page_toplimit'];

            // Top X Ranking
            if ($toplimit > 1) {

                $topOptions = [
                    "topUser" => $lang->sprintf($lang->rpgstatistic_page_top_range_topUser, $toplimit), 
                    "topUserMonth" => $lang->sprintf($lang->rpgstatistic_page_top_range_topUserMonth, $toplimit), 
                    "topUserDay" => $lang->sprintf($lang->rpgstatistic_page_top_range_topUserDay, $toplimit), 
                    "topCharacter" => $lang->sprintf($lang->rpgstatistic_page_top_range_topCharacter, $toplimit),  
                    "topCharacterMonth" => $lang->sprintf($lang->rpgstatistic_page_top_range_topCharacterMonth, $toplimit), 
                    "topCharacterDay" => $lang->sprintf($lang->rpgstatistic_page_top_range_topCharacterDay, $toplimit), 
                ];

                $topOptionsUser = [
                    "topUser" => $lang->rpgstatistic_page_top_range_player, 
                    "topUserMonth" => $lang->rpgstatistic_page_top_range_player,
                    "topUserDay" => $lang->rpgstatistic_page_top_range_player,
                    "topCharacter" => $lang->rpgstatistic_page_top_range_character, 
                    "topCharacterMonth" => $lang->rpgstatistic_page_top_range_character, 
                    "topCharacterDay" => $lang->rpgstatistic_page_top_range_character, 
                ];

                $topData = rpgstatistic_topstatistic($toplimit);

                $topOption = "";
                foreach ($selected_topoptions as $option) {

                    $optionData = $topData[$option];
                    $rangeName = $topOptions[$option];
                    $rangeUser = $topOptionsUser[$option];

                    $topUser = "";
                    for ($i = 1; $i <= $toplimit; $i++) {

                        $entry = $optionData["topUser".$i];
                        $count = $entry[0];
                        $user = $entry[1];

                        eval("\$topUser .= \"".$templates->get("rpgstatistic_page_top_range_user")."\";");
                    }

                    eval("\$topOption .= \"".$templates->get("rpgstatistic_page_top_range_bit")."\";");
                }

                eval("\$topBit = \"".$templates->get("rpgstatistic_page_top_range")."\";");
            } 
            // Top 1
            else {

                $topOptions = [
                    "topUser" => $lang->rpgstatistic_page_top_single_topUser, 
                    "topUserMonth" => $lang->rpgstatistic_page_top_single_topUserMonth, 
                    "topUserDay" => $lang->rpgstatistic_page_top_single_topUserDay, 
                    "topCharacter" => $lang->rpgstatistic_page_top_single_topCharacter,  
                    "topCharacterMonth" => $lang->rpgstatistic_page_top_single_topCharacterMonth, 
                    "topCharacterDay" => $lang->rpgstatistic_page_top_single_topCharacterDay, 
                ];

                $topOption = "";
                foreach ($selected_topoptions as $option) {

                    $optionData = $mybb->rpgstatistic[$option];
                    $optionName = $topOptions[$option];

                    eval("\$topOption .= \"".$templates->get("rpgstatistic_page_top_single_bit")."\";");
                }
                eval("\$topBit = \"".$templates->get("rpgstatistic_page_top_single")."\";");
            }

            eval("\$topstatistic = \"".$templates->get("rpgstatistic_page_top")."\";");
        }

        // Diagramme
        $chartData = rpgstatistic_build_charts();
        $charts = "";
        if (!empty($chartData)) {
            $chartBit = "";
            foreach ($chartData as $identification => $data) {
                $chart = $chartData[$identification];
                eval("\$chartBit .= \"".$templates->get("rpgstatistic_page_charts_bit")."\";");
            }
            eval("\$charts = \"".$templates->get("rpgstatistic_page_charts")."\";");
        }

        // TEMPLATE FÜR DIE SEITE
        eval("\$page = \"".$templates->get("rpgstatistic_page")."\";");
        output_page($page);
        die();
    }
}

// ONLINE LOCATION
function rpgstatistic_online_activity($user_activity) {

	global $parameters, $user;

	$split_loc = explode(".php", $user_activity['location']);
	if(isset($user['location']) && $split_loc[0] == $user['location']) { 
		$filename = '';
	} else {
		$filename = my_substr($split_loc[0], -my_strpos(strrev($split_loc[0]), "/"));
	}

	switch ($filename) {
		case 'misc':
			if ($parameters['action'] == "rpgstatistic") {
				$user_activity['activity'] = "rpgstatistic";
			}
            break;
	}

	return $user_activity;
}
function rpgstatistic_online_location($plugin_array) {

	global $lang, $db, $mybb;
    
    // SPRACHDATEI LADEN
    $lang->load("rpgstatistic");

	if ($plugin_array['user_activity']['activity'] == "rpgstatistic") {
		$plugin_array['location_name'] = $lang->rpgstatistic_online_location;
	}

	return $plugin_array;
}

#################################
### STATISTIKEN UND VARIABELN ###
#################################

// eigene Diagramme bauen
function rpgstatistic_build_charts(){

    global $db, $templates, $labels_chart, $data_chart, $colors, $backgroundColor, $statistic_typ, $chartname, $mybb;

    $excludedaccounts_sql = "";
    if (!empty($mybb->settings['rpgstatistic_excludedaccounts'])) {
        $excludedaccounts = $mybb->settings['rpgstatistic_excludedaccounts'];
        $excludedaccounts_sql = "AND uid NOT IN (".$excludedaccounts.")";
    }
      
    // erst einmal Indientifikatoren bekommen
    $allidentification_query = $db->query("SELECT identification FROM ".TABLE_PREFIX."rpgstatistic_charts");
    
    $all_identification = [];
    while($allidentification = $db->fetch_array($allidentification_query)) {
        $all_identification[] = $allidentification['identification'];
    }

    if (count($all_identification) == 0) {
        return[];
    }
  
    // Rückgabe als Array, also einzelne Variablen die sich ansprechen lassen
    $array = [];
      
    foreach ($all_identification as $identification) {

        // Variabel aufrufen => $var['chart_identification']
        $arraylabel = "chart_".$identification;

        // Infos ziehen von der Statistik
        $chart_query = $db->query("SELECT * FROM ".TABLE_PREFIX."rpgstatistic_charts
        WHERE identification = '".$identification."'
        ");

        $word_bit = "";
        while($chart = $db->fetch_array($chart_query)) {

            // LEER LAUFEN LASSEN
            $cid = "";
            $title = "";
            $type = "";
            $field = "";
            $ignorOption = "";
            $usergroups = "";
            $usergroupsOption = "";
            $colors = "";
            $customProperties = "";
            $legend = "false";
            $statistic_typ = "";
            $backgroundColor = "";
            $chartname = "";
            $maxCount = "";

            // MIT INFOS FÜLLEN
            $cid = $chart['cid'];
            $title = $chart['title'];
            $type = $chart['type'];
            $field = $chart['field'];
            $ignorOption = $chart['ignorOption'];
            $usergroups = $chart['usergroups'];
            $usergroupsOption = $chart['usergroupsOption'];
            $colors = $chart['colors'];
            $customProperties = $chart['customProperties'];

            $chartname = "rpgstatistic_".$identification;

            // Daten ermitteln
            // Profilfeld/Steckbrieffeld
            $data_options = "";
            if (!empty($field)) {

                // wenn Zahl => klassisches Profilfeld
                if (is_numeric($field)) {

                    $options_raw = $db->fetch_field($db->simple_select("profilefields", "type", "fid = '".$field."'"),"type");
                    $expoptions = explode("\n", $options_raw);
                    $fieldtyp = $expoptions['0'];
                    unset($expoptions[0]);

                    // gewünschte Optionen rauslöschen
                    if (!empty($ignorOption)) {
                        $ignor_option = array_map('trim', explode(',', $ignorOption));
                        foreach ($ignor_option as $index) {
                            unset($expoptions[$index]);
                        }
                    }

                    $data_options = [];
                    foreach ($expoptions as $option) {

                        if ($fieldtyp != "multiselect" && $fieldtyp != "checkbox") {
                            $userdata_query = $db->query("SELECT * FROM ".TABLE_PREFIX."userfields
                            WHERE fid".$field." = '".$option."'
                            ".str_replace('uid', 'ufid', $excludedaccounts_sql)."
                            ");
                        } else {
                            $userdata_query = $db->query("SELECT * FROM ".TABLE_PREFIX."userfields
                            WHERE (concat('\n',fid".$field.",'\n') LIKE '%\n".$option."\n%')
                            ".str_replace('uid', 'ufid', $excludedaccounts_sql)."
                            ");
                        }
                        $count = $db->num_rows($userdata_query);

                        $data_options[$option] = $count;
                    }

                } 
                // Katjas Steckbriefplugin
                else {
                    $options_raw = $db->fetch_field($db->simple_select("application_ucp_fields", "options", "fieldname = '".$field."'"),"options");
                    $fieldtyp = $db->fetch_field($db->simple_select("application_ucp_fields", "fieldtyp", "fieldname = '".$field."'"), "fieldtyp");
                    $expoptions = array_map('trim', explode(',', $options_raw));

                    if (!empty($ignorOption)) {
                        $ignor_option = array_map('trim', explode(',', $ignorOption));
                        foreach ($ignor_option as $index) {
                            unset($expoptions[$index - 1]);
                        }
                    }

                    $data_options = [];
                    foreach ($expoptions as $option) {

                        $fieldid = $db->fetch_field($db->simple_select("application_ucp_fields", "id", "fieldname = '".$field."'"), "id");

                        if ($fieldtyp != "select_multiple" && $fieldtyp != "checkbox") {
                            $userdata_query = $db->query("SELECT * FROM ".TABLE_PREFIX."application_ucp_userfields
                            WHERE fieldid = '".$fieldid."'
                            AND value = '".$option."'
                            ".$excludedaccounts_sql."
                            ");
                        } else {
                            $userdata_query = $db->query("SELECT * FROM ".TABLE_PREFIX."application_ucp_userfields
                            WHERE fieldid = '".$fieldid."'
                            AND (concat(',',value,',') LIKE '%,".$option.",%')
                            ".$excludedaccounts_sql."
                            ");
                        }
                        $count = $db->num_rows($userdata_query);

                        $data_options[$option] = $count;
                    }
                }
            }
            // Benutzergruppen
            else {

                // Usergruppen Namen
                $usergroups = explode(",", $usergroups);
                $data_options = [];
                foreach($usergroups as $usergroup) {	

                    // nur primär
                    if ($usergroupsOption == 1) {
                        $usergroupsOption_sql = "WHERE usergroup = '".$usergroup."'";
                    } 
                    // nur sekundär
                    else if ($usergroupsOption == 2) {
                        $usergroupsOption_sql = "WHERE (concat(',',additionalgroups,',') LIKE '%,".$usergroup.",%')";
                    }
                    // beides
                    else {
                        $usergroupsOption_sql = "WHERE (usergroup = '".$usergroup."' OR (concat(',',additionalgroups,',') LIKE '%,".$usergroup.",%'))";
                    }

                    $userdata_query = $db->query("SELECT * FROM ".TABLE_PREFIX."users
                    ".$usergroupsOption_sql."
                    ".$excludedaccounts_sql."
                    ");
                    $count = $db->num_rows($userdata_query);

                    $data_options[$usergroup] = $count;
                }
            }
              
            $labels_array = [];
            $data_array = [];
            foreach ($data_options as $fieldname => $fieldcount) {

                // Gruppennamen
                if (!empty($usergroups)) {
                    $fieldname = $db->fetch_field($db->simple_select("usergroups", "title", "gid = '".$fieldname."'"), "title");
                }

                $labels_array[] = $fieldname;
                $data_array[] = (int)$fieldcount;

                eval("\$word_bit .= \"".$templates->get("rpgstatistic_chart_word_bit")."\";");
            }

            $labels_chart = json_encode($labels_array, JSON_UNESCAPED_UNICODE);
            $data_chart = json_encode($data_array, JSON_UNESCAPED_UNICODE);

            // höchster Wert
            $maxCount = max($data_options)+30; 

            // Farben
            if (!empty($colors)) {

                // CSS Variables
                if ($customProperties == 1) {

                    $colors_array = explode(",", $colors);

                    $propertyValue = "";
                    $colors_js_array = "[";
                    foreach ($colors_array as $color) {
                        $bodytag = trim(str_replace(["var(", ")"], "", $color));

                        $colorname = ltrim($bodytag, '-');

                        $propertyValue .= "var ".$colorname." = style.getPropertyValue('--".$colorname."');\n";

                        $colors_js_array .= $colorname.",";
                    }

                    $colors_js_array = rtrim($colors_js_array, ',') . "]";
                    $colors = $colors_js_array;

                } else {
                    $colors_array = explode(",", $colors);
                    $colors = "[";
                    foreach ($colors_array as $color) {
                        $colors .= "'" . $color . "',";
                    }
                    $colors = substr($colors, 0, -1);
                    $colors .= "]";

                    $propertyValue = "";
                }
            } else {
                $colors = "";
                $propertyValue = "";
            }

            // Balken
            if ($type == 1) {
                $labels_chart = $labels_chart;
                $data_chart = $data_chart;
                $backgroundColor = $colors;
                eval("\$statistic_typ .= \"".$templates->get("rpgstatistic_chart_bar")."\";");
            } 
            // Kreis
            else if ($type == 2 || $type == 3) {
                if ($type == 3) {
                    $legend = "true";
                }
                $labels_chart = $labels_chart;
                $data_chart = $data_chart;
                $backgroundColor = $colors;
                eval("\$statistic_typ .= \"".$templates->get("rpgstatistic_chart_pie")."\";");
            }
            // Wort/Zahl
            else {
                eval("\$statistic_typ .= \"".$templates->get("rpgstatistic_chart_word")."\";");
            }
        }

        $array[$arraylabel] = $statistic_typ;  
    }
    return $array;  
}

// eigene Variabeln bauen
function rpgstatistic_build_variables(){

    global $db, $templates, $count_accounts, $allaccounts_formatted, $mybb;

    $excludedaccounts_sql = "";
    if (!empty($mybb->settings['rpgstatistic_excludedaccounts'])) {
        $excludedaccounts = $mybb->settings['rpgstatistic_excludedaccounts'];
        $excludedaccounts_sql = "AND uid NOT IN (".$excludedaccounts.")";
    }
      
    // erst einmal Indientifikatoren bekommen
    $allidentification_query = $db->query("SELECT identification FROM ".TABLE_PREFIX."rpgstatistic_variables");
    
    $all_identification = [];
    while($allidentification = $db->fetch_array($allidentification_query)) {
        $all_identification[] = $allidentification['identification'];
    }

    if (count($all_identification) == 0) {
        return[];
    }
  
    $array = [];
      
    foreach ($all_identification as $identification) {

        // Variabel aufrufen => $var['count_identification']
        $arraylabel = "count_".$identification;

        // Infos ziehen von der Statistik
        $variable_query = $db->query("SELECT * FROM ".TABLE_PREFIX."rpgstatistic_variables
        WHERE identification = '".$identification."'
        ");
        while($variable = $db->fetch_array($variable_query)) {

            // LEER LAUFEN LASSEN
            $field = "";
            $usergroup = "";
            $conditionField = "";
            $conditionUsergroup = "";
            $count_accounts = "";

            // MIT INFOS FÜLLEN
            $field = $variable['field'];
            $usergroup = $variable['usergroup'];
            $conditionField = $variable['conditionField'];
            $conditionUsergroup = $variable['conditionUsergroup'];

            if (!empty($field)) {

                // Profilfeld
                if (is_numeric($field)) {
                    
                    $options_raw = $db->fetch_field($db->simple_select("profilefields", "type", "fid = '".$field."'"),"type");
                    $expoptions = explode("\n", $options_raw);
                    $fieldtyp = $expoptions['0'];

                    if ($fieldtyp != "multiselect" && $fieldtyp != "checkbox") {
                        $userdata_query = $db->query("SELECT * FROM ".TABLE_PREFIX."userfields
                        WHERE fid".$field." = '".$conditionField."'
                        ".str_replace('uid', 'ufid', $excludedaccounts_sql)."
                        ");
                    } else {
                        $userdata_query = $db->query("SELECT * FROM ".TABLE_PREFIX."userfields
                        WHERE (concat('\n',fid".$field.",'\n') LIKE '%\n".$conditionField."\n%')
                        ".str_replace('uid', 'ufid', $excludedaccounts_sql)."
                        ");
                    }
                    $count_accounts = $db->num_rows($userdata_query);
                } else {
                    
                    $fieldtyp = $db->fetch_field($db->simple_select("application_ucp_fields", "fieldtyp", "fieldname = '".$field."'"), "fieldtyp");
                    $fieldid = $db->fetch_field($db->simple_select("application_ucp_fields", "id", "fieldname = '".$field."'"), "id");
                    
                    if ($fieldtyp != "select_multiple" && $fieldtyp != "checkbox") {
                        $userdata_query = $db->query("SELECT * FROM ".TABLE_PREFIX."application_ucp_userfields
                        WHERE fieldid = '".$fieldid."'
                        AND value = '".$conditionField."'
                        ".$excludedaccounts_sql."
                        ");
                    } else {
                        $userdata_query = $db->query("SELECT * FROM ".TABLE_PREFIX."application_ucp_userfields
                        WHERE fieldid = '".$fieldid."'
                        AND (concat(',',value,',') LIKE '%,".$conditionField.",%')
                        ".$excludedaccounts_sql."
                        ");
                    }
                    $count_accounts = $db->num_rows($userdata_query);
                }

            } else if ($usergroup != 0) {

                // nur primär
                if ($conditionUsergroup == 1) {
                    $conditionUsergroup_sql = "WHERE usergroup = '".$usergroup."'";
                } 
                // nur sekundär
                else if ($conditionUsergroup == 2) {
                    $conditionUsergroup_sql = "WHERE (concat(',',additionalgroups,',') LIKE '%,".$usergroup.",%')";
                }
                // beides
                else {
                    $conditionUsergroup_sql = "WHERE (usergroup = '".$usergroup."' OR (concat(',',additionalgroups,',') LIKE '%,".$usergroup.",%'))";                
                }

                $userdata_query = $db->query("SELECT * FROM ".TABLE_PREFIX."users
                ".$conditionUsergroup_sql."
                ".$excludedaccounts_sql."
                ");
                $count_accounts = $db->num_rows($userdata_query);
            }

            $allaccounts_formatted = "";
            if($count_accounts > 0) {
                $allaccounts_formatted = number_format($count_accounts, '0', ',', '.');
            } else {
                $allaccounts_formatted = 0;
            }
        }

        $array[$arraylabel] = $allaccounts_formatted;  
    }

    return $array; 
}

// Forengeburtstag - Tagesanzahl
function rpgstatistic_forumbirthday() {

    global $mybb, $lang;

    if (empty($mybb->settings['rpgstatistic_forumbirthday'])) {
        return[];
    }

    $forumbirthday_raw = trim($mybb->settings['rpgstatistic_forumbirthday']);
    $forumbirthday = DateTime::createFromFormat('d.m.Y', $forumbirthday_raw);

    if (!$forumbirthday) {
        return [];
    }

    $errors = DateTime::getLastErrors();
    if (is_array($errors) && isset($errors['error_count']) && $errors['error_count'] > 0) {
        return [];
    }

    $lang->load('rpgstatistic');
    
    $timezone = new DateTimeZone('Europe/Berlin');

    $forumbirthday->setTime(0, 0, 0);

    $birthday_array = [];

    $today = new DateTime('now', $timezone);
    $today->setTime(23, 59, 59);

    // Differenz berechnen
    $interval = $forumbirthday->diff($today);
    $days = $interval->days +1;
    $days = number_format($days, '0', ',', '.');

    $today_plus_one = clone $today;
    $today_plus_one->modify('+1 day');
    $interval_plus_one = $forumbirthday->diff($today_plus_one);

    $y = $interval_plus_one->y;
    $m = $interval_plus_one->m;
    $d = $interval_plus_one->d;

    // Jahre
    if ($y == 1) {
        $yearText = "1 Jahr";
    } else {
        $yearText = $y." Jahre";
    }

    // Monate
    if ($m == 1) {
        $monthText = "1 Monat";
    } else {
        $monthText = $m." Monate";
    }

    // Tage
    if ($d == 1) {
        $dayText = "1 Tag";
    } else {
        $dayText = $d." Tage";
    }

    $fullDate = $yearText.", ".$monthText." und ".$dayText;

    $birthday_array = [
        "forumbirthday" => $mybb->settings['rpgstatistic_forumbirthday'],
        "forumbirthday_days" => $days,
        "forumbirthday_fullDate" => $fullDate    
    ];

    return $birthday_array;
}

// Userstatistik
function rpgstatistic_userstatistic() {

    global $db, $mybb;

    $excludedaccounts_sql = "";
    if (!empty($mybb->settings['rpgstatistic_excludedaccounts'])) {
        $excludedaccounts = $mybb->settings['rpgstatistic_excludedaccounts'];
        $excludedaccounts_sql = "AND uid NOT IN (".$excludedaccounts.")";
    }

    $userstatistic_array = [];

    $player_query = $db->query("SELECT as_uid FROM ".TABLE_PREFIX."users
    WHERE as_uid = 0
    ".$excludedaccounts_sql."
    ");
    $countPlayer = $db->num_rows($player_query);

    if($countPlayer > 0) {
        $countPlayer_formatted = number_format($countPlayer, '0', ',', '.');
    } else {
        $countPlayer_formatted = 0;
    }

    $characters_query = $db->query("SELECT uid FROM ".TABLE_PREFIX."users
    ".str_replace('AND', 'WHERE', $excludedaccounts_sql)."
    ");
    $countCharacters = $db->num_rows($characters_query);

    if($countCharacters > 0) {
        $averageCharacters =  round($countCharacters/$countPlayer, 2);
        $countCharacters_formatted = number_format($countCharacters, '0', ',', '.');
        $averageCharacters_formatted = number_format($averageCharacters, '0', ',', '.');
    } else {
        $countCharacters_formatted = 0;
        $averageCharacters_formatted = 0;
    }

    $userstatistic_array = [
        "countPlayer" => $countPlayer_formatted,
        "countCharacter" => $countCharacters_formatted,
        "averageCharacter" => $averageCharacters_formatted
    ];

    return $userstatistic_array;
}

// Inplaystatistik
function rpgstatistic_inplaystatistic() {

    global $mybb, $db;

    $excludedaccounts_sql = "";
    if (!empty($mybb->settings['rpgstatistic_excludedaccounts'])) {
        $excludedaccounts = $mybb->settings['rpgstatistic_excludedaccounts'];
        $excludedaccounts_sql = "AND uid NOT IN (".$excludedaccounts.")";
    }

    $inplaystatistic_array = [
        "inplayscenes" => 0,
        "inplayposts" => 0,
        "allCharacters" => 0,
        "averageCharacters" => 0,
        "allWords" => 0,
        "averageWords" => 0
    ];

    $sceneTIDs = [];

    $inplayarea = rpgstatistic_inplayforums();
    $inplayforums = rpgstatistic_get_relevant_forums($inplayarea);
    $inplayFids = implode(',', array_map('intval', $inplayforums));

    if ($inplayFids == 0) { 
        return $inplaystatistic_array;
    }
    
    $count_inplayposts = $count_inplayposts = $count_words = $count_character = 0;

    $inplayTids_query = $db->query("SELECT tid FROM ".TABLE_PREFIX."threads t
    WHERE t.fid IN (".$inplayFids.")
    AND t.visible = 1
    ".$excludedaccounts_sql."
    ");

    while ($scenes = $db->fetch_array($inplayTids_query)){
        $sceneTIDs[] = $scenes['tid']; 
    } 

    if (empty($sceneTIDs)) { 
        return $inplaystatistic_array;
    }

    $lastPid = 0;
    $batchSize = 100;

    do {
    
        $query_allinplaypost = $db->query("SELECT p.pid, p.message FROM ".TABLE_PREFIX."posts p
        WHERE p.tid IN (".implode(",", $sceneTIDs).")
        AND p.visible = 1    
        AND p.pid > ".$lastPid."
        ".$excludedaccounts_sql."
        ORDER BY p.pid ASC
        LIMIT ".$batchSize."
        ");

        $fetched = 0;
        while ($post = $db->fetch_array($query_allinplaypost)) {
            $fetched++;
            $lastPid = $post['pid'];
            $count_inplayposts++;

            // sichere Zählung
            $clean = rpgstatistic_count_words_characters($post['message']);
            $count_words += $clean['words'];
            $count_character += $clean['characters'];
        }

    } while ($fetched > 0);
        
    // Inplayposts
    if($count_inplayposts > 0) {
        $inplayposts_formatted = number_format($count_inplayposts, '0', ',', '.');
    } else {
        $inplayposts_formatted = 0;
    }

    // Inplayszenen
    $count_inplayscenes = count($sceneTIDs);
    if($count_inplayscenes > 0) {
        $inplayscenes_formatted = number_format($count_inplayscenes, '0', ',', '.');
    } else {
        $inplayscenes_formatted = 0;
    }

    // Geschriebene Zeichen
    if($count_character > 0) {
        $charactersall_formatted = number_format($count_character, '0', ',', '.');
    } else {
        $charactersall_formatted = 0;	
    }
    // Durchschnittliche Zeichen
    if($count_character > 0) {
        $averageCharacters = round($count_character/$count_inplayposts, 2);
        $averageCharacters_formatted = number_format($averageCharacters, 2, ',', '.');
    } else {
        $averageCharacters_formatted = 0;
    }

    // Geschriebene Wörter
    if($count_words > 0) {
        $wordsall_formatted = number_format($count_words, '0', ',', '.');
    } else {
        $wordsall_formatted = 0;
    }
    // Durchschnittliche Wörter
    if($count_words > 0) {
        $averageWords =  round($count_words/$count_inplayposts, 2);
        $averageWords_formatted = number_format($averageWords, 2, ',', '.');
    } else {
        $averageWords_formatted = 0;
    }

    $inplaystatistic_array = [
        "inplayscenes" => $inplayscenes_formatted,
        "inplayposts" => $inplayposts_formatted,
        "allCharacters" => $charactersall_formatted,
        "averageCharacters" => $averageCharacters_formatted,
        "allWords" => $wordsall_formatted,
        "averageWords" => $averageWords_formatted
    ];

    return $inplaystatistic_array;
}

// Top Inplaystatistik
function rpgstatistic_topstatistic($toplimit = 1) {

    global $mybb, $db, $lang;

    if ($mybb->settings['rpgstatistic_top'] == 0 || $mybb->settings['rpgstatistic_top_option'] == '') {
        return[];
    }

    $lang->load('rpgstatistic');

    $excludedaccounts_sql = "";
    if (!empty($mybb->settings['rpgstatistic_excludedaccounts'])) {
        $excludedaccounts = $mybb->settings['rpgstatistic_excludedaccounts'];
        $excludedaccounts_sql = "AND p.uid NOT IN (".$excludedaccounts.")";
    }

    $selected_options = explode(',', $mybb->settings['rpgstatistic_top_option']);
    $selected_options = array_map('trim', $selected_options);

    $toplimit = intval($toplimit);
    if ($toplimit > 1) {
        $limit = $toplimit;

        $topOptions = ["topUser", "topUserMonth", "topUserDay", "topCharacter", "topCharacterMonth", "topCharacterDay"];
        foreach ($topOptions as $option) {
            ${$option} = [];
            for ($i = 1; $i <= $limit; $i++) {
                ${$option}["topUser".$i] = ["", ""];
            }
        }
    } else {
        $topUser = $topUserMonth = $topUserDay = $topCharacter = $topCharacterMonth = $topCharacterDay = $lang->rpgstatistic_page_top_single_none;
        $limit = 1;
    }

    $topstatistic_array = [];

    $inplayarea = rpgstatistic_inplayforums();
    $inplayforums = rpgstatistic_get_relevant_forums($inplayarea);
    $inplayFids = implode(',', array_map('intval', $inplayforums));

    // Datumkram
    // Heute
    $timezone = new DateTimeZone('Europe/Berlin');
    $startToday = new DateTime('now', $timezone);
    $startToday->setTime(0, 0, 0);
    $startTodayTimestamp = $startToday->getTimestamp();
    $endToday = new DateTime('now', $timezone);
    $endToday->setTime(23, 59, 59);
    $endTodayTimestamp = $endToday->getTimestamp();

    // erster Tag aktueller Monat
    $startOfMonth = new DateTime('first day of this month', $timezone);
    $startOfMonth->setTime(0, 0, 0);
    $startOfMonthTimestamp = $startOfMonth->getTimestamp();

    // letzter Tag aktueller Monat
    $lastOfMonth = new DateTime('last day of next month', $timezone);
    $lastOfMonth->setTime(23, 59, 59);
    $lastOfMonthTimestamp = $lastOfMonth->getTimestamp();

    // TOP-SPIELER
    // Welcher Spieler hat die meisten Inplayposts insgesamt
    if (in_array('topUser', $selected_options)) {
        
        $topUser_query = $db->query("SELECT 
            IF(u.as_uid > 0, u.as_uid, u.uid) AS main_uid,
            COUNT(*) AS post_count
        FROM ".TABLE_PREFIX."posts p
        LEFT JOIN ".TABLE_PREFIX."users u ON u.uid = p.uid
        WHERE fid IN (".$inplayFids.") 
        AND visible = 1 
        ".$excludedaccounts_sql."
        GROUP BY main_uid
        ORDER BY post_count DESC 
        ");

        if ($limit == 1) {
            if ($topuser = $db->fetch_array($topUser_query)) {
                $topUser_mainUid = $topuser['main_uid'];
                $topUser_name = rpgstatistic_playername($topUser_mainUid);
                $topUser_posts = $topuser['post_count'];
                $topUser = $lang->sprintf($lang->rpgstatistic_page_top_single_bit, $topUser_name, $topUser_posts);
            } else {
                $topUser = $lang->rpgstatistic_page_top_single_none;
            } 
        } else {
            $topUser = [];
            $range = 1;
            while ($top = $db->fetch_array($topUser_query)) {
                $uid = $top['main_uid'];
                $name = rpgstatistic_playername($uid);
                $posts = $top['post_count'];
                $topUser["topUser".$range] = [$posts, $name];
                $range++;
            }
            for ($i = $range; $i <= $limit; $i++) {
                $topUser["topUser".$i] = ["", ""];
            }
        }
    }

    // Welcher Spieler hat die meisten Inplayposts im aktuellen Monat
    if (in_array('topUserMonth', $selected_options)) {
        $topUserMonth_query = $db->query("SELECT 
            IF(u.as_uid > 0, u.as_uid, u.uid) AS main_uid,
            COUNT(*) AS post_count
        FROM ".TABLE_PREFIX."posts p
        LEFT JOIN ".TABLE_PREFIX."users u ON u.uid = p.uid
        WHERE fid IN (".$inplayFids.") 
        AND visible = 1 
        ".$excludedaccounts_sql."
        AND dateline BETWEEN ".$startOfMonthTimestamp." AND ".$lastOfMonthTimestamp."
        GROUP BY main_uid
        ORDER BY post_count DESC 
        LIMIT ".$limit."
        ");

        if ($limit == 1) {
            if ($topuserMonth = $db->fetch_array($topUserMonth_query)) {
                $topUserMonth_mainUid = $topuserMonth['main_uid'];
                $topUserMonth_name = rpgstatistic_playername($topUserMonth_mainUid);
                $topUserMonth_posts = $topuserMonth['post_count'];
                $topUserMonth = $lang->sprintf($lang->rpgstatistic_page_top_single_bit, $topUserMonth_name, $topUserMonth_posts);
            } else {
                $topUserMonth = $lang->rpgstatistic_page_top_single_none;
            } 
        } else {
            $topUserMonth = [];
            $range = 1;
            while ($topMonth = $db->fetch_array($topUserMonth_query)) {
                $uid = $topMonth['main_uid'];
                $name = rpgstatistic_playername($uid);
                $posts = $topMonth['post_count'];
                $topUserMonth["topUser".$range] = [$posts, $name];
                $range++;
            }
            for ($i = $range; $i <= $limit; $i++) {
                $topUserMonth["topUser".$i] = ["", ""];
            }
        }
    }

    // Welcher Spieler hat die meisten Inplayposts am aktuellen Tag
    if (in_array('topUserDay', $selected_options)) {
        $topUserDay_query = $db->query("SELECT 
            IF(u.as_uid > 0, u.as_uid, u.uid) AS main_uid,
            COUNT(*) AS post_count
        FROM ".TABLE_PREFIX."posts p
        LEFT JOIN ".TABLE_PREFIX."users u ON u.uid = p.uid
        WHERE fid IN (".$inplayFids.") 
        AND visible = 1 
        ".$excludedaccounts_sql."
        AND dateline BETWEEN ".$startTodayTimestamp." AND ".$endTodayTimestamp."
        GROUP BY main_uid
        ORDER BY post_count DESC 
        LIMIT ".$limit."
        ");

        if ($limit == 1) {
            if ($topuserDay = $db->fetch_array($topUserDay_query)) {
                $topUserDay_mainUid = $topuserDay['main_uid'];
                $topUserDay_name = rpgstatistic_playername($topUserDay_mainUid);     
                $topUserDay_posts = $topuserDay['post_count'];
                $topUserDay = $lang->sprintf($lang->rpgstatistic_page_top_single_bit, $topUserDay_name, $topUserDay_posts);
            } else {
                $topUserDay = $lang->rpgstatistic_page_top_single_none;
            }
        } else {
            $topUserDay = [];
            $range = 1;
            while ($topDay = $db->fetch_array($topUserDay_query)) {
                $uid = $topDay['main_uid'];
                $name = rpgstatistic_playername($uid);
                $posts = $topDay['post_count'];
                $topUserDay["topUser".$range] = [$posts, $name];
                $range++;
            }
            for ($i = $range; $i <= $limit; $i++) {
                $topUserDay["topUser".$i] = ["", ""];
            }
        }
    }

    // TOP-CHARAKTER
    // Welcher Charakter hat die meisten Inplayposts insgesamt
    if (in_array('topCharacter', $selected_options)) {
        $topCharacter_query = $db->query("SELECT uid, COUNT(*) AS post_count FROM ".TABLE_PREFIX."posts p
        WHERE fid IN (".$inplayFids.") 
        AND visible = 1 
        ".$excludedaccounts_sql."
        GROUP BY uid 
        ORDER BY post_count DESC 
        LIMIT ".$limit."  
        ");

        if ($limit == 1) {
            if ($topcharacter = $db->fetch_array($topCharacter_query)) {
                $topCharacter_uid = $topcharacter['uid'];
                $topCharacter_name = build_profile_link(get_user($topCharacter_uid)['username'], $topCharacter_uid);   
                $topCharacter_posts = $topcharacter['post_count'];
                $topCharacter = $lang->sprintf($lang->rpgstatistic_page_top_single_bit, $topCharacter_name, $topCharacter_posts);
            } else {
                $topCharacter = $lang->rpgstatistic_page_top_single_none;
            } 
        } else {
            $topCharacter = [];
            $range = 1;
            while ($topcharacter = $db->fetch_array($topCharacter_query)) {
                $uid = $topcharacter['uid'];
                $accountname = build_profile_link(get_user($uid)['username'], $uid);
                $playername = rpgstatistic_playername($uid);
                if (get_user($uid)['username'] != $playername) {
                    $name = $lang->sprintf($lang->rpgstatistic_page_top_range_characterPlayer, $accountname, $playername);
                } else {
                    $name = $accountname;
                }
                $posts = $topcharacter['post_count'];
                $topCharacter["topUser".$range] = [$posts, $name];
                $range++;
            }
            for ($i = $range; $i <= $limit; $i++) {
                $topCharacter["topUser".$i] = ["", ""];
            }
        }
    }

    // Welcher Charakter hat die meisten Inplayposts im aktuellen Monat
    if (in_array('topCharacterMonth', $selected_options)) {
        $topCharacterMonth_query = $db->query("SELECT uid, COUNT(*) AS post_count FROM ".TABLE_PREFIX."posts p
        WHERE fid IN (".$inplayFids.") 
        AND visible = 1 
        ".$excludedaccounts_sql."
        AND dateline BETWEEN ".$startOfMonthTimestamp." AND ".$lastOfMonthTimestamp."
        GROUP BY uid 
        ORDER BY post_count DESC 
        LIMIT ".$limit."
        ");

        if ($limit == 1) {
            if ($topcharacterMonth = $db->fetch_array($topCharacterMonth_query)) {
                $topCharacterMonth_uid = $topcharacterMonth['uid'];
                $topCharacterMonth_name = build_profile_link(get_user($topCharacterMonth_uid)['username'], $topCharacterMonth_uid);
                $topCharacterMonth_posts = $topcharacterMonth['post_count'];
                $topCharacterMonth = $lang->sprintf($lang->rpgstatistic_page_top_single_bit, $topCharacterMonth_name, $topCharacterMonth_posts);
            } else {
                $topCharacterMonth = $lang->rpgstatistic_page_top_single_none;
            } 
        } else {
            $topCharacterMonth = [];
            $range = 1;
            while ($topcharacterMonth = $db->fetch_array($topCharacterMonth_query)) {
                $uid = $topcharacterMonth['uid'];
                $accountname = build_profile_link(get_user($uid)['username'], $uid);
                $playername = rpgstatistic_playername($uid);
                if (get_user($uid)['username'] != $playername) {
                    $name = $lang->sprintf($lang->rpgstatistic_page_top_range_characterPlayer, $accountname, $playername);
                } else {
                    $name = $accountname;
                }
                $posts = $topcharacterMonth['post_count'];
                $topCharacterMonth["topUser".$range] = [$posts, $name];
                $range++;
            }
            for ($i = $range; $i <= $limit; $i++) {
                $topCharacterMonth["topUser".$i] = ["", ""];
            }
        }
    }

    // Welcher Charakter hat die meisten Inplayposts am aktuellen Tag
    if (in_array('topCharacterDay', $selected_options)) {
        $topCharacterDay_query = $db->query("SELECT uid, COUNT(*) AS post_count FROM ".TABLE_PREFIX."posts p
        WHERE fid IN (".$inplayFids.") 
        AND visible = 1 
        ".$excludedaccounts_sql."
        AND dateline BETWEEN ".$startTodayTimestamp." AND ".$endTodayTimestamp."
        GROUP BY uid 
        ORDER BY post_count DESC 
        LIMIT ".$limit."
        ");

        if ($limit == 1) {
            if ($topcharacterDay = $db->fetch_array($topCharacterDay_query)) {
                $topCharacterDay_uid = $topcharacterDay['uid'];
                $topCharacterDay_name = build_profile_link(get_user($topCharacterDay_uid)['username'], $topCharacterDay_uid);
                $topCharacterDay_posts = $topcharacterDay['post_count'];
                $topCharacterDay = $lang->sprintf($lang->rpgstatistic_page_top_single_bit, $topCharacterDay_name, $topCharacterDay_posts);
            } else {
                $topCharacterDay = $lang->rpgstatistic_page_top_single_none;
            } 
        } else {
            $topCharacterDay = [];
            $range = 1;
            while ($topcharacterDay = $db->fetch_array($topCharacterDay_query)) {
                $uid = $topcharacterDay['uid'];
                $accountname = build_profile_link(get_user($uid)['username'], $uid);
                $playername = rpgstatistic_playername($uid);
                if (get_user($uid)['username'] != $playername) {
                    $name = $lang->sprintf($lang->rpgstatistic_page_top_range_characterPlayer, $accountname, $playername);
                } else {
                    $name = $accountname;
                }
                $posts = $topcharacterDay['post_count'];
                $topCharacterDay["topUser".$range] = [$posts, $name];
                $range++;
            }
            for ($i = $range; $i <= $limit; $i++) {
                $topCharacterDay["topUser".$i] = ["", ""];
            }
        }
    }

    $topstatistic_array = [
        "topUser" => $topUser,
        "topUserMonth" => $topUserMonth,
        "topUserDay" => $topUserDay,
        "topCharacter" => $topCharacter,
        "topCharacterMonth" => $topCharacterMonth,
        "topCharacterDay" => $topCharacterDay,
    ];
    $topstatistic_array = array_intersect_key($topstatistic_array, array_flip($selected_options));

    return $topstatistic_array;
}

// zuletzt gewobbte Charaktere
function rpgstatistic_wob() {

    global $mybb, $db, $theme, $templates, $characters_bit;

    if ($mybb->settings['rpgstatistic_wobUser_guest'] == 0 && $mybb->user['uid'] == 0) {
        return;
    }

    $dbwob = $mybb->settings['rpgstatistic_wobUser_db'];
    $itemslimit = $mybb->settings['rpgstatistic_wobUser_limit'];
    $defaultAvatar = $mybb->settings['rpgstatistic_wobUser_defaultavatar'];
    $guest_avatar = $mybb->settings['rpgstatistic_wobUser_guest_avatar'];

    $excludedaccounts_sql = "";
    if (!empty($mybb->settings['rpgstatistic_excludedaccounts'])) {
        $excludedaccounts = $mybb->settings['rpgstatistic_excludedaccounts'];
        $excludedaccounts_sql = "AND uid NOT IN (".$excludedaccounts.")";
    }

    $wobaccounts_query = $db->query("SELECT * FROM ".TABLE_PREFIX."users
    WHERE ".$dbwob." != ''
    ".$excludedaccounts_sql."
    ORDER BY ".$dbwob." DESC
    LIMIT ".$itemslimit."
    ");

    $characters_bit = "";
    while ($wob = $db->fetch_array($wobaccounts_query)) {

        // Leer laufen lassen
        $uid = "";
        $avatarUrl = "";
        $characternameFormatted = "";
        $characternameLink = ""; 
        $characternameFormattedLink = "";
        $characternameFirst = "";
        $characternameLast = "";

        // Profilfelder & Users Tabelle
        $uid = $wob['uid'];
        $userfields_query = $db->simple_select("userfields", "*", "ufid = ".$uid);
        $userfields = $db->fetch_array($userfields_query);
        if (!is_array($userfields)) {
            $userfields = [];
        }
        $character = array_merge($wob, $userfields);

        // Avatar
        if (empty($character['avatar']) || ($guest_avatar == 1 && $mybb->user['uid'] == 0)) {
            $avatarUrl = $theme['imgdir']."/".$defaultAvatar;
        } else {
            $avatarUrl = $character['avatar'];
        }

        // CHARACTER NAME
        // Nur Gruppenfarbe
        $characternameFormatted = format_name($wob['username'], $wob['usergroup'], $wob['displaygroup']);	
        // Nur Link
        $characternameLink = build_profile_link($wob['username'], $uid);
        // mit Gruppenfarbe + Link
        $characternameFormattedLink = build_profile_link(format_name($wob['username'], $wob['usergroup'], $wob['displaygroup']), $uid);	
        // Name gesplittet
        $fullname = explode(" ", $character['username']);
        $characternameFirst = array_shift($fullname);
        $characternameLast = implode(" ", $fullname); 

        // Steckbrieffelder
        if ($db->table_exists("application_ucp_fields")) {
            if (!function_exists('application_ucp_build_view')) {
                require_once MYBB_ROOT . 'inc/plugins/application_ucp.php';
                $applicationfields = application_ucp_build_view($uid, "profile", "array");
                $character = array_merge($character, $applicationfields);
            }
        }

        // Uploadsystem
        if ($db->table_exists("uploadsystem")) {
            if (!function_exists('uploadsystem_build_view')) {
                require_once MYBB_ROOT . 'inc/plugins/uploadsystem.php';
                $uploadfields = uploadsystem_build_view($uid);
                $character = array_merge($character, $uploadfields);
            }
        }   

        eval("\$characters_bit .= \"".$templates->get("rpgstatistic_wob_bit")."\";");
    }

    return $characters_bit;
}

// neuste Themen/Beiträge
function rpgstatistic_overview() {

    global $db, $mybb, $lang, $templates, $overviewtableBit, $topics, $bit;

    $lang->load('rpgstatistic');

    $overviewforums = $mybb->settings['rpgstatistic_overview_forums'];
    $overviewdisplay = $mybb->settings['rpgstatistic_overview_display'];
    $overviewlimit = $mybb->settings['rpgstatistic_overview_limit'];

    $overview_forums = rpgstatistic_get_relevant_forums($overviewforums, 'overview');
    $overview_sql = implode(',', array_map('intval', $overview_forums));

    $postsBit = "";
    $threadsBit = "";
    $topics = "";

    // gemeinsame Ausgabe
    if ($overviewdisplay == 0) {

        $newsquery = $db->query("SELECT p.tid, p.pid, p.subject, p.uid, p.username, p.fid, p.dateline, t.prefix FROM ".TABLE_PREFIX."posts p
        LEFT JOIN ". TABLE_PREFIX."threads t ON (t.tid = p.tid)
        WHERE p.visible = 1
        AND p.fid IN (".$overview_sql.")
        ORDER BY p.dateline DESC
        LIMIT ".$overviewlimit."   
        ");

        $topics = rpgstatistic_overviewtable_build_topics($newsquery);   
        $bitName = "Neuste Themen & Beiträge";
        eval("\$threadsBit = \"".$templates->get("rpgstatistic_overviewtable_bit")."\";");
    } 
    // Nur Themen
    else if ($overviewdisplay == 1) {

        $threadsquery = $db->query("SELECT t.tid, t.firstpost, t.subject, t.uid, t.username, t.prefix, t.firstpost, t.fid, t.dateline FROM ".TABLE_PREFIX."threads t
        WHERE t.visible = 1
        AND t.fid IN (".$overview_sql.")
        ORDER BY t.dateline DESC
        LIMIT ".$overviewlimit."   
        ");

        $topics = rpgstatistic_overviewtable_build_topics($threadsquery, 'firstpost'); 
        $bitName = "Neuste Themen";
        eval("\$threadsBit = \"".$templates->get("rpgstatistic_overviewtable_bit")."\";");
    }
    // Nur Beiträge
    else if ($overviewdisplay == 2) {

        $postsquery = $db->query("SELECT p.tid, p.pid, p.subject, p.uid, p.username, p.fid, p.dateline, t.prefix FROM ".TABLE_PREFIX."posts p
        LEFT JOIN ". TABLE_PREFIX."threads t ON (t.tid = p.tid)
        WHERE p.visible = 1
        AND p.pid != t.firstpost
        AND t.fid IN (".$overview_sql.")
        ORDER BY p.dateline DESC
        LIMIT ".$overviewlimit."    
        ");

        $topics = rpgstatistic_overviewtable_build_topics($postsquery);    
        $bitName = "Neuste Beiträge";
        eval("\$postsBit = \"".$templates->get("rpgstatistic_overviewtable_bit")."\";");
    }
    // beides => getrennte ausgabe
    else if ($overviewdisplay == 3) {

        $threadsquery = $db->query("SELECT t.tid, t.firstpost, t.subject, t.uid, t.username, t.prefix, t.firstpost, t.fid, t.dateline FROM ".TABLE_PREFIX."threads t
        WHERE t.visible = 1
        AND t.fid IN (".$overview_sql.")
        ORDER BY t.dateline DESC
        LIMIT ".$overviewlimit."   
        ");

        if ($threadsquery) {
            $topics = rpgstatistic_overviewtable_build_topics($threadsquery, 'firstpost'); 
            $bitName = "Neuste Themen";
            eval("\$threadsBit = \"".$templates->get("rpgstatistic_overviewtable_bit")."\";");
        }

        $postsquery = $db->query("SELECT p.tid, p.pid, p.subject, p.uid, p.username, p.fid, p.dateline, t.prefix FROM ".TABLE_PREFIX."posts p
        LEFT JOIN ". TABLE_PREFIX."threads t ON (t.tid = p.tid)
        WHERE p.visible = 1
        AND p.pid != t.firstpost
        AND t.fid IN (".$overview_sql.")
        ORDER BY p.dateline DESC
        LIMIT ".$overviewlimit."    
        ");

        if ($postsquery) {
            $topics = rpgstatistic_overviewtable_build_topics($postsquery);    
            $bitName = "Neuste Beiträge";
            eval("\$postsBit = \"".$templates->get("rpgstatistic_overviewtable_bit")."\";");
        }
    }

    $overviewtableBit = $threadsBit.$postsBit;

    return $overviewtableBit;
}

#########################
### PRIVATE FUNCTIONS ###
#########################

// ACP - Tabmenu
function rpgstatistic_acp_tabmenu() {

    global $lang;

    $lang->load('rpgstatistic');

    // Tabs bilden
    // Übersichtsseite Diagramme
    $sub_tabs['overview_charts'] = [
        "title" => $lang->rpgstatistic_tabs_overview_charts,
        "link" => "index.php?module=rpgstuff-rpgstatistic",
        "description" => $lang->rpgstatistic_tabs_overview_charts_desc
    ];
    // Neues Diagramm
    $sub_tabs['add_chart'] = [
        "title" => $lang->rpgstatistic_tabs_add_charts,
        "link" => "index.php?module=rpgstuff-rpgstatistic&amp;action=add_chart",
        "description" => $lang->rpgstatistic_tabs_add_charts_desc
    ];
    // Übersichtsseite Variabeln
    $sub_tabs['overview_variables'] = [
        "title" => $lang->rpgstatistic_tabs_overview_variables,
        "link" => "index.php?module=rpgstuff-rpgstatistic&amp;action=variables",
        "description" => $lang->rpgstatistic_tabs_overview_variables_desc
    ];
    // Neue Variable
    $sub_tabs['add_variable'] = [
        "title" => $lang->rpgstatistic_tabs_add_variables,
        "link" => "index.php?module=rpgstuff-rpgstatistic&amp;action=add_variable",
        "description" => $lang->rpgstatistic_tabs_add_variables_desc
    ];

    return $sub_tabs;
}

// ACP - Errors Form Chart
function rpgstatistic_validate_chart_form($cid = ''){

    global $mybb, $lang, $db;

    $errors = [];
    $colorCheck = false;

    // Titel
    if (empty($mybb->get_input('title'))) {
        $errors[] = $lang->rpgstatistic_chart_form_error_title;
    }

    // Identifikation
    $identification = $mybb->get_input('identification');
    if (empty($identification)) {
        $errors[] = $lang->rpgstatistic_chart_form_error_identification;
    } else {
        if (!empty($cid)) {
            $identificationCheck = $db->fetch_field(
                $db->simple_select("rpgstatistic_charts", "identification", "identification = '".$db->escape_string($identification)."' AND cid != '".$cid."'"),
                "identification"
            );
        } else {
            $identificationCheck = $db->fetch_field(
                $db->simple_select("rpgstatistic_charts", "identification", "identification = '".$db->escape_string($identification)."'"),
                "identification"
            );
        }
        if (!empty($identificationCheck)) {
            $errors[] = $lang->rpgstatistic_chart_form_error_identification_prove;
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $identification)) {
            $errors[] = $lang->rpgstatistic_chart_form_error_identification_wrong;
        }
    }

    // Typ
    $type = $mybb->get_input('type');
    if (empty($type)) {
        $errors[] = $lang->rpgstatistic_chart_form_error_type;
    }

    // Farbencheck
    $colors_raw = $mybb->get_input('colors');
    if (in_array($type, [1, 2, 3]) && empty($colors_raw)) {
        $errors[] = $lang->rpgstatistic_chart_form_error_colors;
    } elseif (!empty($colors_raw)) {
        $colorCheck = true;
        $colors = array_map('trim', explode(',', $colors_raw));
    }

    // Datenquelle
    $data = $mybb->get_input('data');
    if (empty($data)) {
        $errors[] = $lang->rpgstatistic_chart_form_error_data;
    } else {
        // Datenquelle: Profilfeld
        if ($data == 1) {
            $profilefield = $mybb->get_input('profilefield');
            if (empty($profilefield)) {
                $errors[] = $lang->rpgstatistic_chart_form_error_profilefield;
            } elseif ($colorCheck) {
                $options_raw = $db->fetch_field($db->simple_select("profilefields", "type", "fid = '".$db->escape_string($profilefield)."'"),"type");
                $expoptions = explode("\n", $options_raw);
                unset($expoptions[0]);

                if (!empty($mybb->get_input('ignorOption'))) {
                    $ignor_option = array_map('trim', explode(',', $mybb->get_input('ignorOption')));
                    foreach ($ignor_option as $index) {
                        unset($expoptions[$index]);
                    }
                }

                $errors = array_merge($errors, rpgstatistic_color_count($colors, $expoptions));
            }
        }

        // Datenquelle: Steckbrieffeld
        elseif ($data == 2) {
            $applicationfield = $mybb->get_input('applicationfield');
            if (empty($applicationfield)) {
                $errors[] = $lang->rpgstatistic_chart_form_error_profilefield;
            } elseif ($colorCheck) {
                $options_raw = $db->fetch_field(
                    $db->simple_select("application_ucp_fields", "options", "fieldname = '".$db->escape_string($applicationfield)."'"),
                    "options"
                );
                $expoptions = array_map('trim', explode(',', $options_raw));

                if (!empty($mybb->get_input('ignorOption'))) {
                    $ignor_option = array_map('trim', explode(',', $mybb->get_input('ignorOption')));
                    foreach ($ignor_option as $index) {
                        unset($expoptions[$index - 1]);
                    }
                }

                $errors = array_merge($errors, rpgstatistic_color_count($colors, $expoptions));
            }
        }

        // Datenquelle: Benutzergruppen
        elseif ($data == 3) {
            $usergroups = $mybb->get_input('usergroups', MyBB::INPUT_ARRAY);
            if (empty($usergroups)) {
                $errors[] = $lang->rpgstatistic_chart_form_error_usergroups;
            } else {
                if ($colorCheck) {
                    $errors = array_merge($errors, rpgstatistic_color_count($colors, $usergroups));
                }
                if (count($usergroups) < 2) {
                    $errors[] = $lang->rpgstatistic_chart_form_error_usergroups_few;
                }
            }
            if (empty($mybb->get_input('usergroupsOption'))) {
                $errors[] = $lang->rpgstatistic_chart_form_error_usergroupsOption;
            }
        }
    }

    return $errors;
}

// ACP - Errors Form Chart - Farben
function rpgstatistic_color_count($colors, $dataOptions) {

    global $lang;

    $errors = [];
    $countDiff = count($dataOptions) - count($colors);
    if ($countDiff > 0) {
        if ($countDiff == 1) {
            $errors[] = $lang->rpgstatistic_chart_form_error_colors_few_one;
        } else {
            $errors[] = $lang->rpgstatistic_chart_form_error_colors_few_more;
        }
    }

    return $errors;
}

// ACP - Errors Form Variable
function rpgstatistic_validate_variable_form($vid = ''){

    global $mybb, $lang, $db;

    $errors = [];

    // Titel
    if (empty($mybb->get_input('title'))) {
        $errors[] = $lang->rpgstatistic_variable_form_error_title;
    }

    // Identifikation
    $identification = $mybb->get_input('identification');
    if (empty($identification)) {
        $errors[] = $lang->rpgstatistic_variable_form_error_identification;
    } else {
        if (!empty($vid)) {
            $identificationCheck = $db->fetch_field(
                $db->simple_select("rpgstatistic_variables", "identification", "identification = '".$db->escape_string($identification)."' AND vid != '".$vid."'"),
                "identification"
            );
        } else {
            $identificationCheck = $db->fetch_field(
                $db->simple_select("rpgstatistic_variables", "identification", "identification = '".$db->escape_string($identification)."'"),
                "identification"
            );
        }
        if (!empty($identificationCheck)) {
            $errors[] = $lang->rpgstatistic_variable_form_error_identification_prove;
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $identification)) {
            $errors[] = $lang->rpgstatistic_variable_form_error_identification_wrong;
        }
    }

    // Datenquelle
    $data = $mybb->get_input('data');
    if (empty($data)) {
        $errors[] = $lang->rpgstatistic_variable_form_error_data;
    } else {
        // Datenquelle: Profilfeld
        if ($data == 1) {
            $profilefield = $mybb->get_input('profilefield');
            if (empty($profilefield)) {
                $errors[] = $lang->rpgstatistic_variable_form_error_profilefield;
            } else {
                $conditionField = $mybb->get_input('conditionField');
                if (empty($conditionField)) {
                    $errors[] = $lang->rpgstatistic_variable_form_error_conditionField;
                }
            }
        }

        // Datenquelle: Steckbrieffeld
        elseif ($data == 2) {
            $applicationfield = $mybb->get_input('applicationfield');
            if (empty($applicationfield)) {
                $errors[] = $lang->rpgstatistic_variable_form_error_applicationfield;
            } else {
                $conditionField = $mybb->get_input('conditionField');
                if (empty($conditionField)) {
                    $errors[] = $lang->rpgstatistic_variable_form_error_conditionField;
                }
            }
        }

        // Datenquelle: Benutzergruppen
        elseif ($data == 3) {
            $usergroup = $mybb->get_input('usergroup');
            if (empty($usergroup)) {
                $errors[] = $lang->rpgstatistic_variable_form_error_usergroups;
            }
            if (empty($mybb->get_input('conditionUsergroup'))) {
                $errors[] = $lang->rpgstatistic_variable_form_error_conditionUsergroup;
            }
        }
    }

    return $errors;
}

// Inplayforen holen
function rpgstatistic_inplayforums() {

    global $mybb;
    
    $inplaytracker = $mybb->settings['rpgstatistic_inplaytracker'];

    // Inplaytracker 2.0 von sparksfly
    if ($inplaytracker == 0) {
        $inplay = $mybb->settings['inplaytracker_forum'];
        $archive = $mybb->settings['inplaytracker_archiv'];

        $inplayarea = $inplay.",".$archive;
    } 
    // Inplaytracker 3.0 von sparks fly
    else if ($inplaytracker == 1) {
        $inplay = $mybb->settings['ipt_inplay'];
        $archive = $mybb->settings['ipt_archive'];

        $inplayarea = $inplay.",".$archive;
    }
    // Szenentracker von risuena
    else if ($inplaytracker == 2) {
        $inplay = $mybb->settings['scenetracker_ingame'];
        $archive = $mybb->settings['scenetracker_archiv'];
        $exludedfids = $mybb->settings['scenetracker_exludedfids'];

        $inplay_arr = array_filter(array_map('trim', explode(',', $inplay)));
        $archive_arr = array_filter(array_map('trim', explode(',', $archive)));
        $excluded_arr = array_filter(array_map('trim', explode(',', $exludedfids)));

        if (count($excluded_arr) === 1 && ($excluded_arr[0] === '-1' || $excluded_arr[0] === '')) {
            $excluded_arr = [];
        }
        
        $inplay_arr = array_diff($inplay_arr, $excluded_arr);
        $archive_arr = array_diff($archive_arr, $excluded_arr);

        $inplayarea_arr = array_merge($inplay_arr, $archive_arr);
        $inplayarea = implode(',', $inplayarea_arr);
    }
    // Inplayszenen-Manager von little.evil.genius
    else if ($inplaytracker == 3) {
        $inplay = $mybb->settings['inplayscenes_inplayarea'];
        $archive = $mybb->settings['inplayscenes_archive'];
        $sideplays = $mybb->settings['inplayscenes_sideplays'];
        $sideplaysarchive = $mybb->settings['inplayscenes_sideplays'];
        $exludedfids = $mybb->settings['inplayscenes_excludedarea'];

        $inplay_arr = array_filter(array_map('trim', explode(',', $inplay)));
        $archive_arr = array_filter(array_map('trim', explode(',', $archive)));
        $sideplays_arr = array_filter(array_map('trim', explode(',', $sideplays)));
        $sideplaysarchive_arr = array_filter(array_map('trim', explode(',', $sideplaysarchive)));
        $excluded_arr = array_filter(array_map('trim', explode(',', $exludedfids)));

        if (count($excluded_arr) === 1 && ($excluded_arr[0] === '-1' || $excluded_arr[0] === '')) {
            $excluded_arr = [];
        }

        $inplay_arr = array_diff($inplay_arr, $excluded_arr);
        $archive_arr = array_diff($archive_arr, $excluded_arr);
        $sideplays_arr = array_diff($sideplays_arr, $excluded_arr);
        $sideplaysarchive_arr = array_diff($sideplaysarchive_arr, $excluded_arr);

        $inplayarea_arr = array_merge($inplay_arr, $archive_arr, $sideplays_arr, $sideplaysarchive_arr);
        $inplayarea = implode(',', $inplayarea_arr);
    }
    // Inplaytracker 1.0 von Ales
    else if ($inplaytracker == 4) {

    }
    // Inplaytracker 2.0 von Ales
    else if ($inplaytracker == 5) {
        $inplay = $mybb->settings['ipt_inplay_id'];
        $archive = $mybb->settings['ipt_archive_id'];

        $inplayarea = $inplay.",".$archive;
    }

    $inplayarea = str_replace("-1", "", $inplayarea);

    return $inplayarea;
}

// Unterforen holen
function rpgstatistic_get_relevant_forums($relevantforums, $mode = '') {

    global $db, $mybb;

    if ($relevantforums == '') {
        return [0];
    }

    $relevantforums = trim($relevantforums, ',');

    // Liste der Foren, die man sehen kann (man muss sie nicht lesen können) => Overview relevant
    $viewable_sql = "";
    if (!empty($mode)) {
        $viewable_forums = rpgstatistic_get_viewable_forums();
        if (!empty($viewable_forums)) {
            $viewable_sql = "AND fid IN (".$viewable_forums.")";
        }
    }

    // "-1" => alle Foren
    if ($relevantforums == '-1') {

        $Allquery = $db->query("SELECT fid FROM ".TABLE_PREFIX."forums ".str_replace('AND', 'WHERE', $viewable_sql));
    
        $relevant_forums = [];
        while ($forum = $db->fetch_array($Allquery)) {
            $relevant_forums[] = $forum['fid'];
        }

        if (empty($relevant_forums)) {
            return[0];
        }

        return $relevant_forums;
    }

    // Standard => einzelne Foren
    $relevantarea = array_map('trim', explode(',', $relevantforums));
    $relevantarea = array_filter($relevantarea);

    $relevant_forums = [];
    foreach ($relevantarea as $fid) {

        $query = $db->query("SELECT fid FROM ".TABLE_PREFIX."forums 
        WHERE (CONCAT(',', parentlist, ',') LIKE '%,".(int)$fid.",%')
        ".$viewable_sql."
        ");

        while ($forum = $db->fetch_array($query)) {
            $relevant_forums[] = (int)$forum['fid'];
        }
    }

    $relevant_forums = array_filter(array_unique($relevant_forums));

    if (empty($relevant_forums)) {
        return [0];
    }

    return $relevant_forums;
}

// alle Foren die man sehen kann (egal ob man Threads lesen darf oder nicht)
function rpgstatistic_get_viewable_forums() {

	global $forum_cache, $permissioncache, $mybb;

	if(!is_array($forum_cache))
	{
		cache_forums();
	}

	if(!is_array($permissioncache))
	{
		$permissioncache = forum_permissions();
	}

	$viewable = array();
	foreach($forum_cache as $fid => $forum)
	{
		if($permissioncache[$forum['fid']])
		{
			$perms = $permissioncache[$forum['fid']];
		}
		else
		{
			$perms = $mybb->usergroup;
		}

		$pwverified = 1;

		if(!forum_password_validated($forum, true))
		{
			$pwverified = 0;
		}
		else
		{
			// Check parents for passwords
			$parents = explode(",", $forum['parentlist']);
			foreach($parents as $parent)
			{
				if(!forum_password_validated($forum_cache[$parent], true))
				{
					$pwverified = 0;
					break;
				}
			}
		}

		if($perms['canview'] == 1 && $pwverified == 1)
		{
			$viewable[] = $forum['fid'];
		}
	}

	$viewableforums = implode(',', $viewable);

	return $viewableforums;
}

// Message bereinigen
function rpgstatistic_clean_message($message) {

    $message = preg_replace('#<style.*?>.*?</style>#is', '', $message);
    $message = strip_tags($message);
    $message = html_entity_decode($message, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $message = preg_replace('~\[[^\]]+\]~', '', $message);
    $message = preg_replace('/\s+/', ' ', $message);
    $message = trim($message);

    return $message;
}

// Zeichen und Wörter zahlen
function rpgstatistic_count_words_characters($message, $maxLength = 50000) {
    $totalWords = 0;
    $totalChars = 0;

    $messageClean = rpgstatistic_clean_message($message);

    $length = strlen($messageClean);
    for ($i = 0; $i < $length; $i += $maxLength) {
        $chunk = substr($messageClean, $i, $maxLength);

        // Wortanzahl
        preg_match_all('/\b[\p{L}\p{N}äöüÄÖÜß]+\b/u', $chunk, $matches);
        $word_count = count($matches[0]);

        // Zeichenanzahl (ohne Leerzeichen)
        $character_count = mb_strlen(str_replace([' ', "\n", "\r", "\t"], '', $chunk));

        $totalWords += $word_count;
        $totalChars += $character_count;
    }

    return [
        'words' => $totalWords,
        'characters' => $totalChars
    ];
}

// While overview
function rpgstatistic_overviewtable_build_topics($query, $column = '') {

    global $db, $templates, $mybb;

    // EINSTELLUNGEN
    $re_setting = $mybb->settings['rpgstatistic_overview_re'];
    $subject_setting = $mybb->settings['rpgstatistic_overview_subject'];
    $username_setting = $mybb->settings['rpgstatistic_overview_username'];
    $prefix_setting = $mybb->settings['rpgstatistic_overview_prefix'];

    $topics = "";
    while ($bit = $db->fetch_array($query)) {
        
        // Leer laufen lassen
        $prefix = "";
        $fullsubject = "";
        $subject = "";
        $tid = "";
        $pid = "";
        $uid = "";
        $accountname = "";
        $username = "";
        $usergroup = "";
        $displaygroup = "";
        $dateline = "";
        $datelineRelative = "";
        $date = "";
        $time = "";
        $datelineDate = "";
        $fid = "";
        $forumname = "";
        
        // Mit Infos füllen
        if ($prefix_setting == 1) {
            $prefix = $threadprefix = '';
            if($bit['prefix'] != 0) {
                $threadprefix = build_prefixes($bit['prefix']);
                if(!empty($threadprefix)) {
                    $prefix = $threadprefix['displaystyle'].'&nbsp;';
                }
            }        
        }

        if ($re_setting == 1) {
            $subject = preg_replace('/^(RE:\s*)+/i', '', $bit['subject']);
        } else {
            $subject = $bit['subject'];
        }

        if ($subject_setting != 0) {
            if(my_strlen($subject) > $subject_setting) {
                $subject = my_substr($subject, 0, $subject_setting)."..";
            } else {
                $subject = $subject;
            } 
        } else {
            $subject = $bit['subject'];        
        }

        $tid = $bit['tid'];
        if (!empty($column)) {
            $pid = $bit['firstpost'];
        } else {
            $pid = $bit['pid'];
        }
        $uid = $bit['uid'];
        $accountname = $bit['username'];
        $usergroup = get_user($uid)['usergroup'];
        $displaygroup = get_user($uid)['displaygroup'];
            
        if ($username_setting == 1) {
            $username = build_profile_link(format_name($accountname, $usergroup, $displaygroup), $uid);
        } else {
            $username = build_profile_link($accountname, $uid);        
        }

        $dateline = $bit['dateline'];
        $fid = $bit['fid'];

        $datelineRelative = my_date('relative', $dateline);

        $date = my_date($mybb->settings['dateformat'], $dateline);
        $time = my_date($mybb->settings['timeformat'], $dateline);
        $datelineDate = $date." um ".$time;

        $forumname = get_forum($fid)['name'];

        eval("\$topics .= \"".$templates->get("rpgstatistic_overviewtable_topics")."\";");
    }

    return $topics;
}

// Spitzname
function rpgstatistic_playername($uid){
    
    global $db, $mybb;

    $playername_setting = $mybb->settings['rpgstatistic_playername'];

    if (!empty($playername_setting)) {
        if (is_numeric($playername_setting)) {
            $playername_fid = "fid".$playername_setting;
            $playername = $db->fetch_field($db->simple_select("userfields", $playername_fid ,"ufid = '".$uid."'"), $playername_fid);
        } else {
            $playername_fid = $db->fetch_field($db->simple_select("application_ucp_fields", "id", "fieldname = '".$playername_setting."'"), "id");
            $playername = $db->fetch_field($db->simple_select("application_ucp_userfields", "value", "uid = '".$uid."' AND fieldid = '".$playername_fid."'"), "value");
        }
    } else {
        $playername = "";
    }

    if (!empty($playername)) {
        $playerName = $playername;
    } else {
        $playerName = get_user($uid)['username'];
    }

    return $playerName;
}

#######################################
### DATABASE | SETTINGS | TEMPLATES ###
#######################################

// DATENBANKTABELLEN
function rpgstatistic_database() {

    global $db;
    
    if (!$db->table_exists("rpgstatistic_charts")) {
        $db->query("CREATE TABLE ".TABLE_PREFIX."rpgstatistic_charts(
            `cid` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `title` VARCHAR(500) NOT NULL,
            `identification` VARCHAR(500) NOT NULL,
            `type` int(1) unsigned NOT NULL,
            `field` VARCHAR(500) NOT NULL,
            `ignorOption` VARCHAR(500) NOT NULL,
            `usergroups` VARCHAR(500) NOT NULL,
            `usergroupsOption` int(1) unsigned NOT NULL,
            `colors` VARCHAR(500) NOT NULL,
            `customProperties` int(1) unsigned NOT NULL,
            PRIMARY KEY(`cid`),
            KEY `cid` (`cid`)
            ) ENGINE=InnoDB ".$db->build_create_table_collation().";"
        );
    }

    if (!$db->table_exists("rpgstatistic_variables")) {
        $db->query("CREATE TABLE ".TABLE_PREFIX."rpgstatistic_variables(
            `vid` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `title` VARCHAR(500) NOT NULL,
            `identification` VARCHAR(500) NOT NULL,
            `field` VARCHAR(500) NOT NULL,
            `conditionField` VARCHAR(500) NOT NULL,
            `usergroup` int(10) unsigned NOT NULL,
            `conditionUsergroup` int(1) unsigned NOT NULL,
            PRIMARY KEY(`vid`),
            KEY `vid` (`vid`)
            ) ENGINE=InnoDB ".$db->build_create_table_collation().";"
        );
    }
}

// EINSTELLUNGEN
function rpgstatistic_settings($type = 'install') {

    global $db; 

    $setting_array = array(
		'rpgstatistic_excludedaccounts' => array(
			'title' => 'Ausgeschlossene Accounts',
            'description' => 'Gibt es Accounts, welche nicht beachtet werden sollen? Zum Beispiel Adminaccounts oder NPCs. Gib durch Kommata getrennt die UIDs der Accounts an.',
            'optionscode' => 'text',
            'value' => '1', // Default
            'disporder' => 1
		),
		'rpgstatistic_playername' => array(
			'title' => 'Spitzname',
            'description' => 'Wie lautet die FID / der Identifikator von dem Profilfeld/Steckbrieffeld für den Spitznamen?<br><b>Hinweis:</b> Bei klassischen Profilfeldern muss eine Zahl eintragen werden. Bei dem Steckbrief-Plugin von Risuena muss der Name/Identifikator des Felds eingetragen werden.',
            'optionscode' => 'text',
            'value' => '', // Default
            'disporder' => 2
		),
		'rpgstatistic_forumbirthday' => array(
			'title' => 'Forengeburtstag',
            'description' => 'Wann hat das Forum Geburtstag bzw. eröffnet? Das Format ist wie folgt: TT.MM.JJJJ.',
            'optionscode' => 'text',
            'value' => '', // Default
            'disporder' => 3
		),
		'rpgstatistic_inplaytracker' => array(
			'title' => 'Inplaytrackersystem',
            'description' => 'Welches Inplaytracker-Plugin wird verwendet?',
            'optionscode' => 'select\n0=Inplaytracker 2.0 von sparks fly\n1=Inplaytracker 3.0 von sparks fly\n2=Szenentracker von risuena\n3=Inplayszenen-Manager von little.evil.genius\n5=Inplaytracker 2.0 von Ales',
            'value' => '0', // Default
            'disporder' => 4
		),
		'rpgstatistic_top' => array(
			'title' => 'Top-Inplaypost Statistik',
            'description' => 'Soll es Variabeln geben für verschiedene Top-Inplaystatistiken?',
            'optionscode' => 'yesno',
            'value' => '0', // Default
            'disporder' => 5
		),
		'rpgstatistic_top_option' => array(
			'title' => 'Top-Inplaypost Optionen',
            'description' => 'Wähle aus, welche Top-Inplaypostwerte angezeigt werden sollen. Nach Spieler:in oder Charakter, jeweils insgesamt, im aktuellen Monat oder am aktuellen Tag.',
            'optionscode' => 'checkbox\ntopUser=Top-Spieler:in insgesamt\ntopUserMonth=Top-Spieler:in Monat\ntopUserDay=Top-Spieler:in Tag\ntopCharacter=Top-Charakter insgesamt\ntopCharacterMonth=Top-Charakter Monat\ntopCharacterDay=Top-Charakter Tag',
            'value' => '', // Default
            'disporder' => 6
		),
		'rpgstatistic_wobUser' => array(
			'title' => 'Zuletzt gewobbten Accounts',
            'description' => 'Soll es eine Anzeige geben von den zuletzt gewobbten Accounts geben?',
            'optionscode' => 'yesno',
            'value' => '0', // Default
            'disporder' => 7
		),
		'rpgstatistic_wobUser_db' => array(
			'title' => 'Spalte WoB Datum',
            'description' => 'Wie lautet die Spalte in der Datenbanktabelle "users", in der das Datum des WoB-Tages gespeichert wird?',
            'optionscode' => 'text',
            'value' => 'wobSince', // Default
            'disporder' => 8
		),
		'rpgstatistic_wobUser_limit' => array(
			'title' => 'Anzahl der zuletzt gewobbten Accounts',
            'description' => 'Wie viele Accounts sollen ausgegeben werden?',
            'optionscode' => 'numeric',
            'value' => '3', // Default
            'disporder' => 9
		),
		'rpgstatistic_wobUser_guest' => array(
			'title' => 'Gästeberechtigung',
            'description' => 'Dürfen Gäste die zuletzt gewobbten Accounts sehen',
            'optionscode' => 'yesno',
            'value' => '1', // Default
            'disporder' => 10
		),
		'rpgstatistic_wobUser_guest_avatar' => array(
			'title' => 'Avatar für Gäste',
            'description' => 'Soll der klassische Avatar vor Gästen versteckt werden?',
            'optionscode' => 'yesno',
            'value' => '1', // Default
            'disporder' => 11
		),
		'rpgstatistic_wobUser_defaultavatar' => array(
			'title' => 'Standard-Avatar',
            'description' => 'Wie heißt die Bilddatei, für die Standard-Avatare? Damit der Avatar für jedes Design richtig angezeigt wird, sollte der Namen in allen Designs gleich sein. Sprich in jedem Themen-Pfad muss eine Datei mit diesem Namen vorhanden sein.',
            'optionscode' => 'text',
            'value' => 'default_avatar.png', // Default
            'disporder' => 12
		),
		'rpgstatistic_wobUser_forumbit' => array(
			'title' => 'Zwischen den Foren',
            'description' => 'Wenn die Option bestehen soll, die zuletzt gewobbten Accounts auch zwischden den Foren anzeigen zulassen muss hier das entsprechende Forum ausgewählt werden.',
            'optionscode' => 'forumselectsingle',
            'value' => '-1', // Default
            'disporder' => 13
		),
		'rpgstatistic_overview' => array(
			'title' => 'Neuste Themen/Beiträge',
            'description' => 'Solle eine gewisse Anzahl von den neusten Themen und Beiträge angezeigt werden?',
            'optionscode' => 'yesno',
            'value' => '0', // Default
            'disporder' => 14
		),
		'rpgstatistic_overview_forums' => array(
			'title' => 'ausgelesene Foren',
            'description' => 'Aus welchen Bereichen des Forums sollen die neusten Themen und Beiträge erfasst werden? Es reicht aus, die übergeordneten Kategorien/Foren zu markieren.',
            'optionscode' => 'forumselect',
            'value' => '-1', // Default
            'disporder' => 15
		),
		'rpgstatistic_overview_display' => array(
			'title' => 'Darstellungsart',
            'description' => 'Sollen die neusten Themen und Beiträge gemeinsam über eine Variable ausggeben werden oder getrennt?',
            'optionscode' => 'select\n0=gemeinsam Ausgabe\n1=nur neuste Themen\n2=nur neuste Beiträge\n3=einzelne Ausgabe',
            'value' => '0', // Default
            'disporder' => 16
		),
		'rpgstatistic_overview_limit' => array(
			'title' => 'Anzahl der Neusten-Einträge',
            'description' => 'Wie viele Einträge sollen jeweils ausgegeben werden?',
            'optionscode' => 'numeric',
            'value' => '5', // Default
            'disporder' => 17
		),
		'rpgstatistic_overview_re' => array(
			'title' => '"RE:" Anzeige',
            'description' => 'Soll bei den Antwort-Beiträgen das "RE:" angezeigt werden?',
            'optionscode' => 'yesno',
            'value' => '1', // Default
            'disporder' => 18
		),
		'rpgstatistic_overview_subject' => array(
			'title' => 'Maximale Zeichenanzahl im Thementitel',
            'description' => 'Bei wie vielen Zeichen soll der Titel eines Themas gekürzt werden? (0 = vollständiger Titel)',
            'optionscode' => 'numeric',
            'value' => '25', // Default
            'disporder' => 19
		),
		'rpgstatistic_overview_username' => array(
			'title' => 'farbige Benutzernamen',
            'description' => 'Sollen die Benutzernamen farbig dargestellt werden?',
            'optionscode' => 'yesno',
            'value' => '1', // Default
            'disporder' => 20
		),
		'rpgstatistic_overview_prefix' => array(
			'title' => 'Threadpräfixe',
            'description' => 'Sollen Präfixe angezeigt werden?',
            'optionscode' => 'yesno',
            'value' => '0', // Default
            'disporder' => 21
		),
        'rpgstatistic_page' => array(
			'title' => 'Seite für RPG-Statistik',
            'description' => 'Soll es eine eigene Seite geben mit allen erstellen Diagrammen und Statistiken-Werten?',
            'optionscode' => 'yesno',
            'value' => '0', // Default
            'disporder' => 22
		),
        'rpgstatistic_page_allowgroups' => array(
            'title' => 'Erlaubte Gruppen',
			'description' => 'Welche Gruppen dürfen diese Seite sehen?',
			'optionscode' => 'groupselect',
			'value' => '4', // Default
			'disporder' => 23
        ),
		'rpgstatistic_page_toplimit' => array(
			'title' => 'Top-Inplaypost Ranking',
            'description' => 'Soll auf der Statistikseite ein Inplaypost-Ranking (Erweiterung der Top-Inplaypost Statistik Optionen) erscheinen? Falls ja, gib die gewünschte Anzahl an Platzierungen an (z.B. 5 für ein Top5-Ranking).',
            'optionscode' => 'numeric',
            'value' => '1', // Default
            'disporder' => 24
		),
		'rpgstatistic_page_nav' => array(
			'title' => "Listen PHP",
			'description' => "Wie heißt die Hauptseite der Listen-Seite? Dies dient zur Ergänzung der Navigation. Falls nicht gewünscht einfach leer lassen.",
			'optionscode' => 'text',
			'value' => 'lists.php', // Default
			'disporder' => 25
		),
		'rpgstatistic_page_menu' => array(
			'title' => 'Listen Menü',
			'description' => 'Soll über die Variable {$lists_menu} das Menü der Listen aufgerufen werden?<br>Wenn ja, muss noch angegeben werden, ob eine eigene PHP-Datei oder das Automatische Listen-Plugin von sparks fly genutzt?',
			'optionscode' => 'select\n0=eigene Listen/PHP-Datei\n1=Automatische Listen-Plugin\n2=keine Menü-Anzeige',
			'value' => '0', // Default
			'disporder' => 26
		),
        'rpgstatistic_page_menu_tpl' => array(
            'title' => 'Listen Menü Template',
            'description' => 'Damit das Listen Menü richtig angezeigt werden kann, muss hier einmal der Name von dem Tpl von dem Listen-Menü angegeben werden.',
            'optionscode' => 'text',
            'value' => 'lists_nav', // Default
            'disporder' => 27
        ),
    );

    $gid = $db->fetch_field($db->write_query("SELECT gid FROM ".TABLE_PREFIX."settinggroups WHERE name = 'rpgstatistic' LIMIT 1;"), "gid");

    if ($type == 'install') {
        foreach ($setting_array as $name => $setting) {
          $setting['name'] = $name;
          $setting['gid'] = $gid;
          $db->insert_query('settings', $setting);
        }  
    }

    if ($type == 'update') {

        // Einzeln durchgehen 
        foreach ($setting_array as $name => $setting) {
            $setting['name'] = $name;
            $check = $db->write_query("SELECT name FROM ".TABLE_PREFIX."settings WHERE name = '".$name."'"); // Überprüfen, ob sie vorhanden ist
            $check = $db->num_rows($check);
            $setting['gid'] = $gid;
            if ($check == 0) { // nicht vorhanden, hinzufügen
              $db->insert_query('settings', $setting);
            } else { // vorhanden, auf Änderungen überprüfen
                
                $current_setting = $db->fetch_array($db->write_query("SELECT title, description, optionscode, disporder FROM ".TABLE_PREFIX."settings 
                WHERE name = '".$db->escape_string($name)."'
                "));
            
                $update_needed = false;
                $update_data = array();
            
                if ($current_setting['title'] != $setting['title']) {
                    $update_data['title'] = $setting['title'];
                    $update_needed = true;
                }
                if ($current_setting['description'] != $setting['description']) {
                    $update_data['description'] = $setting['description'];
                    $update_needed = true;
                }
                if ($current_setting['optionscode'] != $setting['optionscode']) {
                    $update_data['optionscode'] = $setting['optionscode'];
                    $update_needed = true;
                }
                if ($current_setting['disporder'] != $setting['disporder']) {
                    $update_data['disporder'] = $setting['disporder'];
                    $update_needed = true;
                }
            
                if ($update_needed) {
                    $db->update_query('settings', $update_data, "name = '".$db->escape_string($name)."'");
                }
            }
        }
    }

    rebuild_settings();
}

// TEMPLATES
function rpgstatistic_templates($mode = '') {

    global $db;

    $templates[] = array(
        'title'		=> 'rpgstatistic_chart_bar',
        'template'	=> $db->escape_string('<div class="rpgstatistic_chart_headline">{$title}</div>
        <div class="rpgstatistic_chart">
        <canvas id="{$chartname}"></canvas>
        </div>

        <script>
        document.addEventListener(\'DOMContentLoaded\', function () {
		var style = getComputedStyle(document.body);
		var text = style.getPropertyValue(\'--rpgstatistic_chart-text\');
		{$propertyValue}
		var myData = {
			labels: {$labels_chart},
			datasets: [{
				backgroundColor: {$backgroundColor},
				hoverBackgroundColor: {$backgroundColor},
				data: {$data_chart}
			}]
		};

		var myoption = {
			maintainAspectRatio: false,
			legend: { display: false },
			responsive: true,
			tooltips: { enabled: true },
			hover: { animationDuration: 1 },
			animation: {
				duration: 1,
				onComplete: function () {
					var chartInstance = this.chart,
						ctx = chartInstance.ctx;
					ctx.textAlign = \'center\';
					ctx.fillStyle = text;
					ctx.textBaseline = \'bottom\';
					this.data.datasets.forEach(function (dataset, i) {
						var meta = chartInstance.controller.getDatasetMeta(i);
						meta.data.forEach(function (bar, index) {
							var data = dataset.data[index];
							ctx.fillText(data, bar._model.x, bar._model.y - 5);
						});
					});
				}
			},
			scales: {
				yAxes: [{
					display: true,
					gridLines: { display: false },
					ticks: {
						max: {$maxCount},
						display: false,
						beginAtZero: true,
						color: text 
					}
				}],
				xAxes: [{
					gridLines: { display: false },
					ticks: {
						beginAtZero: true,
						fontColor: text
					}
				}]
			}
		};

		var ctx = document.getElementById(\'{$chartname}\');
		if (ctx) {
			var myChart = new Chart(ctx.getContext(\'2d\'), {
				type: \'bar\',
				data: myData,
				options: myoption
			});
		}
        });
        </script>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );
     
    $templates[] = array(
        'title'		=> 'rpgstatistic_chart_pie',
        'template'	=> $db->escape_string('<div class="rpgstatistic_chart_headline">{$title}</div>
        <div class="rpgstatistic_chart">
        <canvas id="{$chartname}"></canvas>
        </div>

        <script>
        document.addEventListener(\'DOMContentLoaded\', function () {
        var style = getComputedStyle(document.body);
        var text = style.getPropertyValue(\'--rpgstatistic_chart-text\');
        {$propertyValue}
	
        var myData = {
        labels: {$labels_chart},
        datasets: [{
			data: {$data_chart},
			backgroundColor: {$backgroundColor},
			hoverBackgroundColor: {$backgroundColor},
			borderWidth: 0
		}]
        };
	
        var myoption = {
		maintainAspectRatio: false,
		legend: {
			display: {$legend},
			position: \'right\',
			labels: {
				fontColor: text,
				fontSize: 12
			},
		},
		responsive: true,
        };

        var ctx = document.getElementById(\'{$chartname}\');
        if (ctx) {
        var ctx = document.getElementById(\'{$chartname}\').getContext(\'2d\');
            var myChart = new Chart(ctx, {
                type: \'pie\', // Define chart type
                data: myData, // Chart data
                options: myoption // Chart Options [This is optional paramenter use to add some extra things in the chart].
            });

            }
            });
            </script>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );
     
    $templates[] = array(
        'title'		=> 'rpgstatistic_chart_word',
        'template'	=> $db->escape_string('<div class="rpgstatistic_chart_headline">{$title}</div>
        <div class="rpgstatistic_chart_word_bit">{$word_bit}</div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );
     
    $templates[] = array(
        'title'		=> 'rpgstatistic_chart_word_bit',
        'template'	=> $db->escape_string('<div class="rpgstatistic_chart_word_count">{$fieldcount}
        <div class="rpgstatistic_chart_word_name">{$fieldname}</div> 
        </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );
     
    $templates[] = array(
        'title'		=> 'rpgstatistic_overviewtable',
        'template'	=> $db->escape_string('<div class="rpgstatistic_overviewtable">
        <div class="rpgstatistic_overviewtable_headline"><b>{$lang->rpgstatistic_overviewtable}</b></div>
        <div class="rpgstatistic_overviewtable_content">
        {$overviewtableBit}
        </div>
        </div>
        <br />'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );
     
    $templates[] = array(
        'title'		=> 'rpgstatistic_overviewtable_bit',
        'template'	=> $db->escape_string('<div class="rpgstatistic_overviewtable_bit">
        <div class="rpgstatistic_overviewtable_bit-headline"><b>{$bitName}</b></div>
        <div class="rpgstatistic_overviewtable_bit-content">
		<div class="rpgstatistic_overviewtable_bit-item"><b>{$lang->rpgstatistic_overviewtable_topic}</b></div>
		<div class="rpgstatistic_overviewtable_bit-item"><b>{$lang->rpgstatistic_overviewtable_user}</b></div>
        </div>
        {$topics}
        </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );
     
    $templates[] = array(
        'title'		=> 'rpgstatistic_overviewtable_topics',
        'template'	=> $db->escape_string('<div class="rpgstatistic_overviewtable_bit-content">
        <div class="rpgstatistic_overviewtable_bit-item">{$prefix}<a href="showthread.php?tid={$tid}&pid={$pid}#pid{$pid}">{$subject}</a><br>{$forumname}</div>
        <div class="rpgstatistic_overviewtable_bit-item user">{$username}<br>{$datelineDate}</div>
        </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );
     
    $templates[] = array(
        'title'		=> 'rpgstatistic_page',
        'template'	=> $db->escape_string('<html>
        <head>
        <title>{$mybb->settings[\'bbname\']} - {$lang->rpgstatistic_page}</title>
		{$headerinclude}
        </head>
        <body>
		{$header}
		<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
			<tr>
				<td class="thead"><strong>{$lang->rpgstatistic_page}</strong></td>
			</tr>
			<tr>
				<td class="trow1">
					<div class="rpgstatistic_page">
						{$forumbirthday}
						
						<div class="rpgstatistic_page_subline">{$lang->rpgstatistic_page_user}</div>
						<div class="rpgstatistic_page_statistic">
							<div class="rpgstatistic_page-stat">
								<div class="rpgstatistic_page-stat_question">{$countPlayer}</div>
								<div class="rpgstatistic_page-stat_answer">{$lang->rpgstatistic_page_user_player}</div>		
							</div>
							<div class="rpgstatistic_page-stat">
								<div class="rpgstatistic_page-stat_question">{$countCharacter}</div>
								<div class="rpgstatistic_page-stat_answer">{$lang->rpgstatistic_page_user_character}</div>		
							</div>
							<div class="rpgstatistic_page-stat">
								<div class="rpgstatistic_page-stat_question">{$averageCharacter}</div>
								<div class="rpgstatistic_page-stat_answer">{$lang->rpgstatistic_page_user_averageCharacter}</div>		
							</div>
						</div>
						
						<div class="rpgstatistic_page_subline">{$lang->rpgstatistic_page_inplay}</div>
						<div class="rpgstatistic_page_statistic">
							<div class="rpgstatistic_page-stat">
								<div class="rpgstatistic_page-stat_question">{$inplayscenes}</div>
								<div class="rpgstatistic_page-stat_answer">{$lang->rpgstatistic_page_inplay_scenes}</div>		
							</div>
							<div class="rpgstatistic_page-stat">
								<div class="rpgstatistic_page-stat_question">{$inplayposts}</div>
								<div class="rpgstatistic_page-stat_answer">{$lang->rpgstatistic_page_inplay_posts}</div>		
							</div>
							<div class="rpgstatistic_page-stat">
								<div class="rpgstatistic_page-stat_question">{$allCharacters}</div>
								<div class="rpgstatistic_page-stat_answer">{$lang->rpgstatistic_page_inplay_allCharacters}</div>		
							</div>
							<div class="rpgstatistic_page-stat">
								<div class="rpgstatistic_page-stat_question">{$averageCharacters}</div>
								<div class="rpgstatistic_page-stat_answer">{$lang->rpgstatistic_page_inplay_averageCharacters}</div>		
							</div>
							<div class="rpgstatistic_page-stat">
								<div class="rpgstatistic_page-stat_question">{$allWords}</div>
								<div class="rpgstatistic_page-stat_answer">{$lang->rpgstatistic_page_inplay_allWords}</div>		
							</div>
							<div class="rpgstatistic_page-stat">
								<div class="rpgstatistic_page-stat_question">{$averageWords}</div>
								<div class="rpgstatistic_page-stat_answer">{$lang->rpgstatistic_page_inplay_averageWords}</div>		
							</div>
						</div>
						{$topstatistic}
						{$charts}
					</div>
				</td>
			</tr>
		</table>
		{$footer}
        </body>
        </html>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );
     
    $templates[] = array(
        'title'		=> 'rpgstatistic_page_charts',
        'template'	=> $db->escape_string('<div class="rpgstatistic_page_subline">{$lang->rpgstatistic_page_charts}</div>
        <div class="rpgstatistic_page_statistic">{$chartBit}</div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );
     
    $templates[] = array(
        'title'		=> 'rpgstatistic_page_charts_bit',
        'template'	=> $db->escape_string('<div class="rpgstatistic_page_chart">{$chart}</div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );
     
    $templates[] = array(
        'title'		=> 'rpgstatistic_page_forumbirthday',
        'template'	=> $db->escape_string('<div class="rpgstatistic_page_statistic">
        <div class="rpgstatistic_page-stat">
		<div class="rpgstatistic_page-stat_question">{$forumbirthdayDate}</div>
		<div class="rpgstatistic_page-stat_answer">{$fullDate}</div>		
        </div>
        </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );
     
    $templates[] = array(
        'title'		=> 'rpgstatistic_page_top',
        'template'	=> $db->escape_string('<div class="rpgstatistic_page_subline">{$lang->rpgstatistic_page_top}</div>{$topBit}'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );
     
    $templates[] = array(
        'title'		=> 'rpgstatistic_page_top_range',
        'template'	=> $db->escape_string('<div class="rpgstatistic_page_statistic">{$topOption}</div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );
     
    $templates[] = array(
        'title'		=> 'rpgstatistic_page_top_range_bit',
        'template'	=> $db->escape_string('<div class="rpgstatistic_page_range">
        <div class="rpgstatistic_page_range-headline"><b>{$rangeName}</b></div>
        <div class="rpgstatistic_page_range-content">
        <div class="rpgstatistic_page_range-item"><b>{$rangeUser}</b></div>
		<div class="rpgstatistic_page_range-item"><b>{$lang->rpgstatistic_page_top_range_post}</b></div>
        </div>
        {$topUser}
        </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );
     
    $templates[] = array(
        'title'		=> 'rpgstatistic_page_top_range_user',
        'template'	=> $db->escape_string('<div class="rpgstatistic_page_top_range-user">
        <div class="rpgstatistic_page_top_range_user-item">{$user}</div>
        <div class="rpgstatistic_page_top_range_user-item">{$count}</div>
        </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );
     
    $templates[] = array(
        'title'		=> 'rpgstatistic_page_top_single',
        'template'	=> $db->escape_string('<div class="rpgstatistic_page_statistic">{$topOption}</div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );
     
    $templates[] = array(
        'title'		=> 'rpgstatistic_page_top_single_bit',
        'template'	=> $db->escape_string('<div class="rpgstatistic_page-stat">
        <div class="rpgstatistic_page-stat_question">{$optionData}</div>
        <div class="rpgstatistic_page-stat_answer">{$optionName}</div>		
        </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );
     
    $templates[] = array(
        'title'		=> 'rpgstatistic_wob',
        'template'	=> $db->escape_string('<div class="rpgstatistic_wob">
        <div class="rpgstatistic_wob-headline">{$lang->rpgstatistic_wob}</div>
        <div class="rpgstatistic_wobcharas">
        {$characters_bit}
        </div>
        </div>
        <br/>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );
     
    $templates[] = array(
        'title'		=> 'rpgstatistic_wob_bit',
        'template'	=> $db->escape_string('<div class="rpgstatistic_wobcharas_character">	
        <div class="rpgstatistic_wobcharas_username">
		{$characternameFormattedLink}
        </div>
        <div class="rpgstatistic_wobcharas_avatar">
		<img src="{$avatarUrl}">
        </div>
        </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    if ($mode == "update") {

        foreach ($templates as $template) {
            $query = $db->simple_select("templates", "tid, template", "title = '".$template['title']."' AND sid = '-2'");
            $existing_template = $db->fetch_array($query);

            if($existing_template) {
                if ($existing_template['template'] !== $template['template']) {
                    $db->update_query("templates", array(
                        'template' => $template['template'],
                        'dateline' => TIME_NOW
                    ), "tid = '".$existing_template['tid']."'");
                }
            }   
            else {
                $db->insert_query("templates", $template);
            }
        }
	
    } else {
        foreach ($templates as $template) {
            $check = $db->num_rows($db->simple_select("templates", "title", "title = '".$template['title']."'"));
            if ($check == 0) {
                $db->insert_query("templates", $template);
            }
        }
    }
}

// STYLESHEET MASTER
function rpgstatistic_stylesheet() {

    global $db;
    
    $css = array(
		'name' => 'rpgstatistic.css',
		'tid' => 1,
		'attachedto' => '',
		'stylesheet' =>	'.rpgstatistic_chart_headline {
        margin-bottom: 5px;
        color: #333;
        font-size: small;
        font-weight: bold;
        text-transform: uppercase;
        text-align: center;
        width: 100%;
        }

        .rpgstatistic_chart {
        height: 150px;
        width: 100%;
        }

        .rpgstatistic_chart canvas {
        width: 100% !important;
        height: 100% !important;
        }

        .rpgstatistic_chart_word_bit {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        gap: 10px;
        align-items: center;
        }

        .rpgstatistic_chart_word_count {
        justify-content: center;
        align-items: center;
        display: flex;
        flex-flow: column;
        }

        .rpgstatistic_chart_word_name {
        color: #293340;
        font-weight: bold;
        text-transform: uppercase;
        }

        .rpgstatistic_wob {
        background: #fff;
        width: 100%;
        margin: auto auto;
        border: 1px solid #ccc;
        padding: 1px;
        -moz-border-radius: 7px;
        -webkit-border-radius: 7px;
        border-radius: 7px;
        }

        .rpgstatistic_wob-headline {
        background: #0066a2 url(../../../images/thead.png) top left repeat-x;
        color: #ffffff;
        border-bottom: 1px solid #263c30;
        padding: 8px;
        -moz-border-radius-topleft: 6px;
        -moz-border-radius-topright: 6px;
        -webkit-border-top-left-radius: 6px;
        -webkit-border-top-right-radius: 6px;
        border-top-left-radius: 6px;
        border-top-right-radius: 6px;
        }

        .rpgstatistic_wobcharas {
        background: #f5f5f5;
        border: 1px solid;
        border-color: #fff #ddd #ddd #fff;
        padding: 10px 0;
        -moz-border-radius-bottomleft: 6px;
        -moz-border-radius-bottomright: 6px;
        -webkit-border-bottom-left-radius: 6px;
        -webkit-border-bottom-right-radius: 6px;
        border-bottom-left-radius: 6px;
        border-bottom-right-radius: 6px;
        display: flex;
        flex-wrap: nowrap;
        justify-content: center;
        }

        .rpgstatistic_wobcharas_character {
        width: 30%;
        text-align: center;
        }

        .rpgstatistic_wobcharas_username {
        font-size: 16px;
        font-weight: bold;
        }

        .rpgstatistic_wobcharas_avatar img {
        padding: 5px;
        border: 1px solid #ddd;
        background: #fff;
        }

        .rpgstatistic_overviewtable {
        background: #fff;
        width: 100%;
        margin: auto auto;
        border: 1px solid #ccc;
        padding: 1px;
        -moz-border-radius: 7px;
        -webkit-border-radius: 7px;
        border-radius: 7px;
        }

        .rpgstatistic_overviewtable_headline {
        background: #0066a2 url(../../../images/thead.png) top left repeat-x;
        color: #fff;
        border-bottom: 1px solid #263c30;
        padding: 8px;
        -moz-border-radius-topleft: 6px;
        -moz-border-radius-topright: 6px;
        -webkit-border-top-left-radius: 6px;
        -webkit-border-top-right-radius: 6px;
        border-top-left-radius: 6px;
        border-top-right-radius: 6px;
        }

        .rpgstatistic_overviewtable_content {
        display: flex;
        flex-wrap: nowrap;
        align-items: flex-start;
        background: #f5f5f5;
        }

        .rpgstatistic_overviewtable_bit {
        width: 100%;
        }

        .rpgstatistic_overviewtable_bit-headline {
        background: #0f0f0f url(../../../images/tcat.png) repeat-x;
        color: #fff;
        border-top: 1px solid #444;
        border-bottom: 1px solid #000;
        padding: 6px;
        }

        .rpgstatistic_overviewtable_bit-content {
        display: flex;
        justify-content: space-between;
        font-size: 11px;
        padding: 5px 3px;
        }

        .rpgstatistic_overviewtable_bit-item.user {
        text-align: end;
        }

        .rpgstatistic_page_subline {
        background: #0f0f0f url(../../../images/tcat.png) repeat-x;
        color: #fff;
        border-top: 1px solid #444;
        border-bottom: 1px solid #000;
        padding: 6px;
        font-size: 12px;
        font-weight: bold;
        }

        .rpgstatistic_page_statistic {
        display: flex;
        flex-flow: wrap;
        margin: 10px 0;
        justify-content: space-around;
        }

        .rpgstatistic_page-stat {
        padding: 10px 5px;
        width: 30%;
        }

        .rpgstatistic_page-stat_answer {
        text-align: center;
        color: #333;
        font-size: small;
        font-weight: bold;
        text-transform: uppercase;
        }

        .rpgstatistic_page-stat_question {
        text-align: center;
        }

        .rpgstatistic_page_range {
        width: 33%;
        }

        .rpgstatistic_page_range-headline {
        background: #0066a2 url(../../../images/thead.png) top left repeat-x;
        color: #fff;
        border-bottom: 1px solid #263c30;
        padding: 8px;
        -moz-border-radius-topleft: 6px;
        -moz-border-radius-topright: 6px;
        -webkit-border-top-left-radius: 6px;
        -webkit-border-top-right-radius: 6px;
        border-top-left-radius: 6px;
        border-top-right-radius: 6px;
        }

        .rpgstatistic_page_range-content {
        display: flex;
        flex-wrap: nowrap;
        align-items: flex-start;
        background: #f5f5f5;
        justify-content: space-around;
        font-size: 11px;
        padding: 5px 3px;
        }

        .rpgstatistic_page_top_range-user {
        display: flex;
        flex-wrap: nowrap;
        align-items: flex-start;
        background: #f5f5f5;
        justify-content: space-around;
        font-size: 11px;
        padding: 5px 3px;
        }

        .rpgstatistic_page_range-item {
        width: 50%;
        text-align: center;
        }

        .rpgstatistic_page_top_range_user-item {
        width: 50%;
        text-align: center;
        }

        .rpgstatistic_page_chart {
        width: 50%;
        }
        ',
		'cachefile' => 'rpgstatistic.css',
		'lastmodified' => TIME_NOW
	);

    return $css;
}

// STYLESHEET UPDATE
function rpgstatistic_stylesheet_update() {

    // Update-Stylesheet
    // wird an bestehende Stylesheets immer ganz am ende hinzugefügt
    $update = '';

    // Definiere den  Überprüfung-String (muss spezifisch für die Überprüfung sein)
    $update_string = '';

    return array(
        'stylesheet' => $update,
        'update_string' => $update_string
    );
}

// UPDATE CHECK
function rpgstatistic_is_updated(){

    global $db, $mybb;

	if ($db->table_exists("rpgstatistic_charts")) {
        return true;
    }
    return false;
}
