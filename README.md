# RPG-Statistiken

## Funktionen im Überblick
### Diagramme und Variabeln im ACP

### Forengeburtstag

### Inplay- und Account-Statistiken

### Top-Inplaypost Statistik

### zuletzt gewobbte Charaktere

### Neuste Themen/Beiträge

### Statistikseite

# Vorrausetzung
- Das ACP Modul <a href="https://github.com/little-evil-genius/rpgstuff_modul" target="_blank">RPG Stuff</a> <b>muss</b> vorhanden sein.
- Der <a href="https://doylecc.altervista.org/bb/downloads.php?dlid=26&cat=2" target="_blank">Accountswitcher</a> von doylecc <b>muss</b> installiert sein.

# Datenbank-Änderungen
hinzugefügte Tabelle:
- rpgstatistic_charts
- rpgstatistic_variables

# Neue Sprachdateien
- deutsch_du/admin/rpgstatistic.lang.php
- deutsch_du/rpgstatistic.lang.php

# Einstellungen

# Neue Template-Gruppe innerhalb der Design-Templates
- RPG-Statistiken Templates

# Neue Templates (nicht global!)
- rpgstatistic_chart_bar
- rpgstatistic_chart_pie
- rpgstatistic_chart_word
- rpgstatistic_chart_word_bit
- rpgstatistic_overviewtable
- rpgstatistic_overviewtable_bit
- rpgstatistic_overviewtable_topics
- rpgstatistic_page
- rpgstatistic_page_charts
- rpgstatistic_page_charts_bit
- rpgstatistic_page_forumbirthday
- rpgstatistic_page_top
- rpgstatistic_page_top_range
- rpgstatistic_page_top_range_bit
- rpgstatistic_page_top_range_user
- rpgstatistic_page_top_single
- rpgstatistic_page_top_single_bit
- rpgstatistic_wob
- rpgstatistic_wob_bit

# Neue Variablen
- headerinclude: ```<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.js"></script>```
- index_boardstats: {$rpgstatistic_overviewtable} & {$rpgstatistic_wob}
- forumbit_depth1_cat/forumbit_depth2_forum: {$forum['rpgstatistic_wob']}

# Neues CSS - rpgstatistic.css
Es wird automatisch in jedes bestehende und neue Design hinzugefügt. Man sollte es einfach einmal abspeichern - auch im Default.<br>
Nach einem MyBB Upgrade fehlt der Stylesheets im Masterstyle? Im ACP Modul "RPG Erweiterungen" befindet sich der Menüpunkt "Stylesheets überprüfen" und kann von hinterlegten Plugins den Stylesheet wieder hinzufügen.
```css
.rpgstatistic_chart_headline {
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
```

# Benutzergruppen-Berechtigungen setzen
Damit alle Admin-Accounts Zugriff auf die Verwaltung der Diagramme und Variabeln für die RPG-Statistiken haben im ACP, müssen unter dem Reiter Benutzer & Gruppen » Administrator-Berechtigungen » Benutzergruppen-Berechtigungen die Berechtigungen einmal angepasst werden. Die Berechtigungen für die Felder befinden sich im Tab 'RPG Erweiterungen'.

# Links
<b>ACP</b><br>
index.php?module=rpgstuff-rpgstatistic<br>
<br>
<b>Statistikseite</b><br>
usercp.php?action=rpgstatistic

# Demo
### ACP

### Statistikseite

### zuletzt angenommene Charaktere

### neue Themen/Beiträge-Tabelle
