<?php

# Copyright (c)  2012  <tobias.thelen@iais.fraunhofer.de>
#
# QuickCounterPlugin
#
# Enables [qc...] markup for quick counter widgets
#
# Version 0.1.0 (2012/06/05): base functionality
# Version 0.2.0 (2012/06/06): added participants and dates support
#
#
# Permission is hereby granted, free of charge, to any person obtaining a copy
# of this software and associated documentation files (the "Software"), to deal
# in the Software without restriction, including without limitation the rights
# to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
# copies of the Software, and to permit persons to whom the Software is
# furnished to do so, subject to the following conditions:
#
# The above copyright notice and this permission notice shall be included in all
# copies or substantial portions of the Software.
#
# THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
# IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
# FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
# AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
# LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
# OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
# SOFTWARE.

class QuickCounterPlugin extends StudipPlugin implements SystemPlugin
{

    function __construct()
    {
        parent::__construct();

        // markup for voting element
        StudipFormat::addStudipMarkup('quickcounter', '\[qc ([A-Za-z0-9\.;&_\/#äöüÄÜÖß-]+)((\s+[a-z]+=&quot;[^&]*&quot;)*)\]', NULL, 'QuickCounterPlugin::markupQuickCounter');
        StudipFormat::addStudipMarkup('quickcounterlist', '\[qclist((\s+[a-z]+=&quot;[^&]*&quot;)*)\]', NULL, 'QuickCounterPlugin::markupQuickCounterList');
        StudipFormat::addStudipMarkup('quickcountersum', '\[qcsum((\s+[a-z]+=&quot;[^&]*&quot;)*)\]', NULL, 'QuickCounterPlugin::markupQuickCounterSummary');
        StudipFormat::addStudipMarkup('quickcountertable', '\[qctable((\s+[a-z]+=&quot;[^&]*&quot;)+)\]', NULL, 'QuickCounterPlugin::markupQuickCounterTable');

        // use template mechanism to inject plugin url into jquery code
        // template is in templates/count_click.php
        $template_path = $this->getPluginPath() . '/templates';
        $this->template_factory = new Flexi_TemplateFactory($template_path);
        $template = $this->template_factory->open('count_click');
        PageLayout::addHeadElement('script', array(), $template->render());

        // 
        // <!-- UI Tools: Tabs, Tooltip, Scrollable and Overlay (4.45 Kb) -->
        // PageLayout::addScript("http://cdn.jquerytools.org/1.2.6/tiny/jquery.tools.min.js");


        // add some nice css 
        PageLayout::addStyleSheet($this->getPluginURL().'/css/quickcounter.css');

    }

    function delete_event_action() 
    {
        // check cid and counter_id parameters
        $range_id = $_REQUEST['cid'];
	if (!preg_match("/^[a-f0-9]{32}$/",$_REQUEST['event_id']) || !preg_match("/^[a-f0-9]{32}$/",$_REQUEST['cid'])) {
		echo "ERROR"; 
		die();
	}

        // check access rights
        if (!$GLOBALS['perm']->have_studip_perm('tutor', $range_id)) {
	        $st = DBManager::get()->prepare("DELETE FROM quickcounter_event WHERE event_id=? AND user_id=?");
	        $st->execute(array( $_REQUEST['event_id'], $GLOBALS['user']->id));
        } else {
		$st = DBManager::get()->prepare("DELETE FROM quickcounter_event WHERE event_id=?");
		$st->execute(array( $_REQUEST['event_id']));
        }

	if ($st->rowCount() == 1) {
	    echo "OK";
	} else {
	    echo "ERRROR";
	}
        die();
    }

    function count_action() 
    {
       // count_action is called via ajax: registers counter and sends replacements widget

       // check valid user
       // NOTE: every user can count on every counter regardless of access rights to embedding object (wiki, forum, ...)
       global $user;
       if (!is_object($user) || $user->id == 'nobody') {
		echo "invalid user.";
                die();
        }

        // check count and count_id parameters
	if (!preg_match("/^[a-zA-Z0-9 \/#\.&;]+$/",$_REQUEST['counter_name'])) {
		echo "invalid parms."; print_r($_REQUEST);
		die();
	}

        if ($_REQUEST['comment']) {
            $comment = $_REQUEST['comment'];
        } else {
            $comment = "-";
        }

	$st = DBManager::get()->prepare("INSERT INTO quickcounter_event (event_id, counter_id, counter_name, range_id, user_id, mkdate, comment) VALUES (?,?,?,?,?,?,?)");
	$st->execute(array( md5(uniqid(rand())),
                            md5($_REQUEST['cid'].$_REQUEST['counter_name']), 
                            $_REQUEST['counter_name'], 
                            $_REQUEST['cid'],
                            $user->id, 
                            time(),
                            $comment));

        echo QuickCounterPlugin::getWidget($_REQUEST['counter_name'], $_REQUEST['counter_from']);
        die();
    }

    static function getWidget($counter_name, $from=0) {
        global $user;

        // count + and -
        $counter_id = md5($_REQUEST['cid'].$counter_name);
        $st = DBManager::get()->prepare("SELECT COUNT(*) FROM quickcounter_event WHERE counter_id=? AND mkdate > ?");
        $st->execute(array($counter_id, $from));
        $count = $st->fetchColumn();

        $out = "<img src='".$GLOBALS['ASSETS_URL']."images/icons/16/black/vote.png' style='margin-bottom:-3px; margin-right:5px;'>";
        $out .= '<b>['.($count).']</b> &nbsp; ';
	$out .= '<a href="#" class="quickcounter" data-countername="'.$counter_name.'" data-counterfrom="'.$from.'">+1</a> &nbsp; ';

	return sprintf($out, $plus, $minus);
    }

    static function markupQuickCounter($markup, $matches, $contents)
    {
        $opt = QuickCounterPlugin::getopts($matches[1]);
        $from = isset($opt['from']) ? strtotime($opt['from'][0]) : 0; 

        // create a widget for given id (md5 hash - ensured by markup regex)
        return "<span class='quickcounterwidget'>".QuickCounterPlugin::getWidget($matches[1],$from)."</span>";
    }

    static function getdates() {
        $st = DBManager::get()->prepare("SELECT date FROM termine WHERE range_id=? ORDER BY date ASC");
        $st->execute(array($_REQUEST['cid']));
        $result = $st->fetchAll(PDO::FETCH_ASSOC);
        $p=array();
        foreach ($result as $r) {
            $p[]=date("d.m.Y H.i", $r['date']);
        }
        return $p;

    }
    static function getparticipants() {
        global $_fullname_sql;
        $st = DBManager::get()->prepare("SELECT Nachname, ".$_fullname_sql['full']." as fullname FROM auth_user_md5 LEFT JOIN user_info USING (user_id) LEFT JOIN seminar_user USING (user_id) WHERE seminar_id=? ORDER BY Nachname ASC");
        $st->execute(array($_REQUEST['cid']));
        $result = $st->fetchAll(PDO::FETCH_ASSOC);
        $p=array();
        foreach ($result as $r) {
            $p[]=$r['fullname'];
        }
        return $p;
    }

    static function getopts($match_opts) {
        // Optionen extrahieren
        $opts=array();
        preg_match_all('/([a-z]+)="([^"]*)"/', preg_replace('/&quot;/','"',$match_opts), $opts);
        for ($i=0; $i<count($opts[0]); $i++) {
            $opt[$opts[1][$i]]=array_map(trim,explode(',',$opts[2][$i]));
            if ($opt[$opts[1][$i]]==array("participants")) $opt[$opts[1][$i]]=QuickCounterPlugin::getparticipants();
            if ($opt[$opts[1][$i]]==array("dates")) $opt[$opts[1][$i]]=QuickCounterPlugin::getdates();
        }
        return $opt;
    }

    static function getcomments($counter_name, $from=0, $to=0) {
           if ($to==0) $to=time();
	   $counter_id = md5($_REQUEST['cid'].$counter_name);
	   $st = DBManager::get()->prepare("SELECT comment FROM quickcounter_event WHERE counter_id=? AND mkdate > ? AND mkdate < ?");
	   $st->execute(array($counter_id, $from, $to));
	   return $st->fetchAll(PDO::FETCH_NUM);
    }

    static function markupQuickCounterTable($markup, $matches, $contents) {

        $opt = QuickCounterPlugin::getopts($matches[1]);
        $from = isset($opt['from']) ? strtotime($opt['from'][0]) : 0; 

        // check
        if (!isset($opt['rows']) || !isset($opt['columns'])) return _("Fehler! Optionen rows und/oder columns nicht gesetzt!");

        // Ausgabe konstruieren
        $out="<table>";
        $out.="<tr><th>Kategorie</th>";
        foreach ($opt['columns'] as $col) {  $out.="<th>".htmlReady($col)."</th>"; }
        $out .="</tr>\n";
        foreach ($opt['rows'] as $row) {
           $out.="<tr>";
           $out.="<td>".htmlReady($row)."</td>";
           foreach ($opt['columns'] as $col) {
               $out.="<td style='vertical-align:top'>";
               $out.="<span class='quickcounterwidget'>".QuickCounterPlugin::getWidget($row.'#'.$col, $from)."</span>";
               if (isset($opt['comments']) && $opt['comments']=array('show')) {
                   $out.="<div>"; 
                   foreach (QuickCounterPlugin::getcomments($row.'#'.$col, $from, time()) as $c) {
                       if (trim($c[0])!='-') {
			       $out .= htmlReady($c[0])."<br>";
                       }
                   }
                   $out.="</div>";
               }
               $out.="</td>";
           }
           $out.="</tr>";
        }
        $out.="</table>";
        
        return $out;
    }

    static function markupQuickCounterList($markup, $matches, $contents)
    {
        global $_fullname_sql;
        $opt = QuickCounterPlugin::getopts($matches[1]);
        $from = isset($opt['from']) ? strtotime($opt['from'][0]) : 0; 
        $to = isset($opt['to']) ? strtotime($opt['to'][0]) : time(); 

        $out="";
        $st = DBManager::get()->prepare("SELECT *, ".$_fullname_sql['full']." as fullname FROM quickcounter_event LEFT JOIN auth_user_md5 USING (user_id) LEFT JOIN user_info USING (user_id) WHERE range_id=? AND quickcounter_event.mkdate > ? AND quickcounter_event.mkdate < ? ORDER BY quickcounter_event.mkdate DESC");
        $st->execute(array( $_REQUEST['cid'], $from, $to));
        $result = $st->fetchAll(PDO::FETCH_ASSOC);
        $out.="<h3>"._("Z&auml;hlereintr&auml;ge");
        if ($from!=0) $out.=" "._("ab")." ".date("d.m.Y, H:i", $from);
        if ((time()-$to) > 60) $out.=" "._("bis")." ".date("d.m.Y, H:i", $to);
        $out.="</h3>";
        $out .= "<table>";
        $out .= "<tr><th>"._("Datum")."</th><th>"._("NutzerIn")."</th><th>"._("Kategorie")."</th><th>"._("Kommentar")."</th><th>"._("Aktionen")."</th></tr>";
        foreach ($result as $r) {
             $out .= "<tr>";
             $out .= "<td>".date("H:i:s, d.m.Y", $r['quickcounter_event.mkdate'])."</td>";
             $out .= "<td><a href='".URLHelper::getLink('about.php?username='.$r['username'])."'>".htmlReady($r['fullname'])."</a></td>";
             $out .= "<td>".preg_replace("/\//"," -> ",preg_replace("/#/",": ",$r['counter_name']))."</td>";
             $out .= "<td>".htmlReady($r['comment'])."</td>";
             // Nutzer selbst darf eigene loeschen, ab tutor darf alles loeschen
	     if ($r['user_id']==$GLOBALS['user']->id || $GLOBALS['perm']->have_studip_perm('tutor', $_REQUEST['cid'])) {
                 $out .= "<td style='text-align:center'><a href='#' class='delete_event_id' data-id='".$r['event_id']."'>".Assets::img('icons/16/blue/trash.png')."</a></td>";
             } else {
                 $out .= "<td>&nbsp;</td>";
             }
             $out .= "</tr>";
        }
        $out .= "</table>";
        return $out;
    }


    static function markupQuickCounterSummary($markup, $matches, $contents) {
        $out="";
 
        $opt = QuickCounterPlugin::getopts($matches[1]);
        $from = isset($opt['from']) ? strtotime($opt['from'][0]) : 0; 
        $to = isset($opt['to']) ? strtotime($opt['to'][0]) : time(); 

        // Tabelle berechnen
        $st = DBManager::get()->prepare("SELECT counter_name, comment FROM quickcounter_event WHERE range_id=? AND mkdate > ? AND mkdate < ? ORDER BY counter_name ASC");
        $st->execute(array( $_REQUEST['cid'], $from, $to));
        $result = $st->fetchAll(PDO::FETCH_ASSOC);
        $table=array();
        $columns=array();
        $sums=array();
        foreach ($result as $r) {
            $parts=explode('#',$r['counter_name']);
            if (count($parts)==1) { # no category
                    $column = _("Z&auml;hler");
            } else {
		    $column = $parts[1];
            }
	    $columns[$column]=1;
            $category = $parts[0];
            if (!isset($table[$category])) $table[$category]=array();
            if (!isset($table[$category][$column])) $table[$category][$column]=array('count'=>0, 'comments'=>array());
            $table[$category][$column]['count']++;
            $table[$category][$column]['comments'][]=$comments;
            $sums[$column]++;
            $sums['total']++;
            $sums[$category]++;
         }

         // Tabelle ausgeben
         $out.="<h3>"._("Zusammenfassung");
         if ($from!=0) $out.=" "._("ab")." ".date("d.m.Y, H:i", $from);
         if ((time()-$to) > 60) $out.=" "._("bis")." ".date("d.m.Y, H:i", $to);
         $out.="</h3>";
         $out.="<table>";
         $out.="<tr><th>Kategorie</th>";
         foreach ($columns as $c=>$val) { $out.="<th>".htmlReady($c)."</th>"; }
	 $out.="<th>"._("Summe")."</th>";
         $out.="</tr>";
         $lastcat="";
         foreach($table as $category => $counts) {
            $out.="<tr>";
	    $out .= "<td>".htmlReady($category)."</td>";
            foreach ($columns as $c=>$val) { 
                 $out.="<td style='text-align:right;'>".$table[$category][$c]['count']."</td>"; 
            }
            $out.="<td style='text-align:right;'>".$sums[$category]."</td>";
            $out.="<tr>";
         }
         $out.="<tr><td>"._("Summe")."</td>";
         foreach($columns as $c => $val) {
            $out.="<td style='text-align:right; border-top:thin black solid;'>".$sums[$c]."</td>"; 
         }
         $out.="<td style='text-align:right;border-top:thin black solid;'>".$sums['total']."</td>";
        
         $out.="</table>";
         return $out;
    }
}
