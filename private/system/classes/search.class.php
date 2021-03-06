<?php
// +--------------------------------------------------------------------------+
// | glFusion CMS                                                             |
// +--------------------------------------------------------------------------+
// | search.php                                                               |
// |                                                                          |
// | glFusion search class.                                                   |
// +--------------------------------------------------------------------------+
// | Copyright (C) 2008-2017 by the following authors:                        |
// |                                                                          |
// | Mark R. Evans          mark AT glfusion DOT org                          |
// |                                                                          |
// | Copyright (C) 2000-2008 by the following authors:                        |
// |                                                                          |
// | Authors: Tony Bibbs       - tony AT geeklog DOT net                      |
// |          Dirk Haun        - dirk AT haun-online DOT de                   |
// |          Sami Barakat, s.m.barakat AT gmail DOT com                      |
// +--------------------------------------------------------------------------+
// |                                                                          |
// | This program is free software; you can redistribute it and/or            |
// | modify it under the terms of the GNU General Public License              |
// | as published by the Free Software Foundation; either version 2           |
// | of the License, or (at your option) any later version.                   |
// |                                                                          |
// | This program is distributed in the hope that it will be useful,          |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of           |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the            |
// | GNU General Public License for more details.                             |
// |                                                                          |
// | You should have received a copy of the GNU General Public License        |
// | along with this program; if not, write to the Free Software Foundation,  |
// | Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.          |
// |                                                                          |
// +--------------------------------------------------------------------------+

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

/**
* glFusion Search Class
*
* @author Tony Bibbs <tony AT geeklog DOT net>
*
*/
class Search {

    // PRIVATE VARIABLES
    var $_query = '';
    var $_topic = '';
    var $_dateStart = null;
    var $_dateEnd = null;
    var $_searchDays = 0;
    var $_author = '';
    var $_type = '';
    var $_keyType = '';
    var $_results = 25;
    var $_names = array();
    var $_url_rewrite = array();
    var $_searchURL = '';
    var $_wordlength;
    var $_charset = 'utf-8';

    /**
     * Constructor
     *
     * Sets up private search variables
     *
     * @author Tony Bibbs <tony AT geeklog DOT net>
     * @access public
     *
     */
    function __construct()
    {
        global $_CONF, $_TABLES;

        // Set search criteria
        if ( isset($_GET['query']) ){
            $this->_query = strip_tags ($_GET['query']);
        } else if ( isset($_POST['query']) ) {
            $this->_query = strip_tags ($_POST['query']);
        } else {
            $this->_query = '';
        }
        $this->_query = preg_replace('/\s\s+/', ' ', $this->_query);

        if ( isset($_GET['topic']) ){
            $this->_topic = COM_applyFilter ($_GET['topic']);
        } else if ( isset($_POST['topic']) ) {
            $this->_topic = COM_applyFilter ($_POST['topic']);
        } else {
            $this->_topic = '';
        }
        if (isset ($_GET['datestart'])) {
            $this->_dateStart = COM_applyFilter ($_GET['datestart']);
        } else if (isset($_POST['datestart']) ) {
            $this->_dateStart = COM_applyFilter ($_POST['datestart']);
        } else {
            $this->_dateStart = '';
        }
        if ( $this->_validateDate($this->_dateStart) == false ) {
            $this->_dateStart = '';
        }
        if (isset ($_GET['dateend'])) {
            $this->_dateEnd = COM_applyFilter ($_GET['dateend']);
        } else if (isset($_POST['dateend']) ) {
            $this->_dateEnd = COM_applyFilter ($_POST['dateend']);
        } else {
            $this->_dateEnd = '';
        }
        if ( $this->_validateDate($this->_dateEnd) == false ) {
            $this->_dateEnd = '';
        }

        if ( isset($_GET['st']) ) {
            $st = COM_applyFilter($_GET['st'],true);
            $this->_searchDays = $st;
            if ( $st != 0 ) {
                $this->_dateEnd = date('Y-m-d');
                $this->_dateStart   = date('Y-m-d', time() - ($st * 24 * 60 * 60));
            }
        }

        if (isset ($_GET['author'])) {
            $this->_author = COM_applyFilter($_GET['author']);
        } else if ( isset($_POST['author']) ) {
            $this->_author = COM_applyFilter($_POST['author']);
        } else {
            $this->_author = '';
        }
        if ( $this->_author != '' ) {
            // In case we got a username instead of uid, convert it.  This should
            // make custom themes for search page easier.
            if (!is_numeric($this->_author) && !preg_match('/^([0-9]+)$/', $this->_author) && $this->_author != '')
                $this->_author = DB_getItem($_TABLES['users'], 'uid', "username='" . DB_escapeString ($this->_author) . "'");

            if ($this->_author < 1)
                $this->_author = '';
        }
        if ( isset($_GET['type']) ) {
            $this->_type = COM_applyFilter($_GET['type']);
        } else if ( isset($_POST['type']) ) {
            $this->_type = COM_applyFilter($_POST['type']);
        } else {
            $this->_type = 'all';
        }
        if ( isset($_GET['keyType']) ) {
            $this->_keyType = COM_applyFilter($_GET['keyType']);
        } else if ( isset($_POST['keyType']) ) {
            $this->_keyType = COM_applyFilter($_POST['keyType']);
        } else {
            $this->_keyType = $_CONF['search_def_keytype'];
        }

        if ( isset($_GET['results']) ) {
            $this->_results = COM_applyFilter($_GET['results'],true);
        } else if ( isset($_POST['results']) ) {
            $this->_results = COM_applyFilter($_POST['results']);
        } else {
            $this->_results = $_CONF['num_search_results'];
        }

        $this->_charset = COM_getCharset();
    }

    /**
     * Shows an error message to anonymous users
     *
     * This is called when anonymous users attempt to access search
     * functionality that has been locked down by the glFusion admin.
     *
     * @author Tony Bibbs <tony AT geeklog DOT net>
     * @access private
     * @return string HTML output for error message
     *
     */
    function _getAccessDeniedMessage()
    {
        return (SEC_loginRequiredForm());
    }

    /**
    * Determines if user is allowed to perform a search
    *
    * glFusion has a number of settings that may prevent
    * the access anonymous users have to the search engine.
    * This performs those checks
    *
    * @author Tony Bibbs <tony AT geeklog DOT net>
    * @access private
    * @return boolean True if search is allowed, otherwise false
    *
    */
    function _isSearchAllowed()
    {
        global $_USER, $_CONF;

        if ( COM_isAnonUser() ) {
            if ( $_CONF['loginrequired'] == 1 OR $_CONF['searchloginrequired'] > 0 ) {
                return false;
            }
        }
        return true;
    }

    /**
    * Determines if user is allowed to use the search form
    *
    * glFusion has a number of settings that may prevent
    * the access anonymous users have to the search engine.
    * This performs those checks
    *
    * @author Dirk Haun <Dirk AT haun-online DOT de>
    * @access private
    * @return boolean True if form usage is allowed, otherwise false
    *
    */
    function _isFormAllowed ()
    {
        global $_CONF, $_USER;

        if ( COM_isAnonUser() AND (($_CONF['loginrequired'] == 1) OR ($_CONF['searchloginrequired'] >= 1))) {
            return false;
        }

        return true;
    }

    /**
     * Shows search form
     *
     * Shows advanced search page
     *
     * @author Tony Bibbs <tony AT geeklog DOT net>
     * @access public
     * @return string HTML output for form
     *
     */
    function showForm ()
    {
        global $_CONF, $_TABLES, $_PLUGINS, $LANG09;

        $retval = '';

        // Verify current user my use the search form
        if (!$this->_isFormAllowed()) {
            return $this->_getAccessDeniedMessage();
        }

        $searchform = new Template($_CONF['path_layout'].'search');
        $searchform->set_file (array ('searchform' => 'searchform.thtml',
                                      'authors'    => 'searchauthors.thtml'));
        $searchform->set_var('search_intro', $LANG09[19]);
        $searchform->set_var('site_url', $_CONF['site_url']);
        $searchform->set_var('site_admin_url', $_CONF['site_admin_url']);
        $searchform->set_var('layout_url', $_CONF['layout_url']);
        $searchform->set_var('lang_keywords', $LANG09[2]);
        $searchform->set_var('lang_date', $LANG09[20]);
        $searchform->set_var('lang_to', $LANG09[21]);
        $searchform->set_var('date_format', $LANG09[22]);
        $searchform->set_var('lang_topic', $LANG09[3]);
        $searchform->set_var('lang_all', $LANG09[4]);
        $searchform->set_var('topic_option_list',
                            COM_topicList ('tid,topic,sortnum', $this->_topic,2,true));
        $searchform->set_var('lang_type', $LANG09[5]);
        $searchform->set_var('lang_results', $LANG09[59]);
        $searchform->set_var('lang_per_page', $LANG09[60]);

        $searchform->set_var('lang_exact_phrase', $LANG09[43]);
        $searchform->set_var('lang_all_words', $LANG09[44]);
        $searchform->set_var('lang_any_word', $LANG09[45]);

        $searchform->set_var ('query', htmlspecialchars ($this->_query));
        $searchform->set_var ('datestart', $this->_dateStart);
        $searchform->set_var ('dateend', $this->_dateEnd);

        $phrase_selected = '';
        $all_selected = '';
        $any_selected = '';
        if ($this->_keyType == 'phrase') {
            $phrase_selected = 'selected="selected"';
        } else if ($this->_keyType == 'all') {
            $all_selected = 'selected="selected"';
        } else if ($this->_keyType == 'any') {
            $any_selected = 'selected="selected"';
        }
        $searchform->set_var ('key_phrase_selected', $phrase_selected);
        $searchform->set_var ('key_all_selected', $all_selected);
        $searchform->set_var ('key_any_selected', $any_selected);

        $options = '';
        if ( isset($_CONF['comment_engine']) && $_CONF['comment_engine'] != 'internal') {
            $plugintypes = array('all' => $LANG09[4], 'stories' => $LANG09[6]);
        } else {
            $plugintypes = array('all' => $LANG09[4], 'stories' => $LANG09[6], 'comments' => $LANG09[7]);
        }
        $plugintypes = array_merge($plugintypes, PLG_getSearchTypes());

        foreach ($plugintypes as $key => $val) {
            $options .= "<option value=\"$key\"";
            if ($this->_type == $key)
                $options .= ' selected="selected"';
            $options .= ">$val</option>".LB;
        }
        $plugin_types_option = $options;
        $searchform->set_var('plugin_types', $options);

        if ($_CONF['contributedbyline'] == 1) {
            $searchform->set_var('lang_authors', $LANG09[8]);
            $searchusers = array();
            if ( $_CONF['comment_engine'] == 'internal' && isset($_TABLES['comments'])) {
                $result = DB_query("SELECT DISTINCT uid FROM {$_TABLES['comments']}");
                while ($A = DB_fetchArray($result)) {
                    $searchusers[$A['uid']] = $A['uid'];
                }
            }
            if ( isset($_TABLES['stories'])) {
                $result = DB_query("SELECT DISTINCT uid FROM {$_TABLES['stories']} WHERE (date <= '".$_CONF['_now']->toMySQL(true)."') AND (draft_flag = 0)");
                while ($A = DB_fetchArray($result)) {
                    $searchusers[$A['uid']] = $A['uid'];
                }
            }
            if (in_array('forum', $_PLUGINS) && isset($_TABLES['ff_topic'] ) ) {
                $result = DB_query("SELECT DISTINCT uid FROM {$_TABLES['ff_topic']}");
                while ( $A = DB_fetchArray($result)) {
                    $searchusers[$A['uid']] = $A['uid'];
                }
            }

            $inlist = implode(',', $searchusers);

            if (!empty ($inlist)) {
                $sql = "SELECT uid,username,fullname FROM {$_TABLES['users']} WHERE uid IN ($inlist)";
                if (isset ($_CONF['show_fullname']) && ($_CONF['show_fullname'] == 1)) {
                    /* Caveat: This will group all users with an emtpy fullname
                     *         together, so it's not exactly sorted by their
                     *         full name ...
                     */
                    $sql .= ' ORDER BY fullname,username';
                } else {
                    $sql .= ' ORDER BY username';
                }
                $result = DB_query ($sql);
                $options = '';
                $options .= '<option value="all">'.$LANG09[4].'</option>'.LB;
                while ($A = DB_fetchArray($result)) {
                    $options .= '<option value="' . $A['uid'] . '"';
                    if ($A['uid'] == $this->_author) {
                        $options .= ' selected="selected"';
                    }
                    $options .= '>' . htmlspecialchars(COM_getDisplayName($A['uid'], $A['username'], $A['fullname'])) . '</option>';
                }
                $searchform->set_var('author_option_list', $options);
                $searchform->parse('author_form_element', 'authors', true);
            } else {
                $searchform->set_var('author_form_element', '<input type="hidden" name="author" value="0"' . XHTML . '>');
            }
        } else {
            $searchform->set_var ('author_form_element',
                    '<input type="hidden" name="author" value="0"' . XHTML . '>');
        }

        $searchTimeOptions = array(
            '0' => $LANG09[4],
            '1' => $LANG09[75],
            '7' => $LANG09[76],
            '14' => $LANG09[77],
            '30' => $LANG09[78],
            '90' => $LANG09[79],
            '180' => $LANG09[80],
            '365' => $LANG09[81]);

        // search time frame
        $options = '';
        foreach ( $searchTimeOptions AS $days => $prompt ) {
            $options .= '<option value="'.$days.'"';
            if ( $this->_searchDays == $days ) {
                $options .= ' selected="selected"';
            }
            $options .= '>'.$prompt.'</option>'.LB;
        }
        $date_option = $options;
        $searchform->set_var('search_time',$options);

        // Results per page
        $options = '';
        $limits = explode(',', $_CONF['search_limits']);
        foreach ($limits as $limit) {
            $options .= "<option value=\"$limit\"";
            if ($this->_results == $limit) {
                $options .= ' selected="selected"';
            }
            $options .= ">$limit</option>" . LB;
        }
        $search_limit_option = $options;
        $searchform->set_var('search_limits', $options);

        $searchform->set_var('lang_search', $LANG09[10]);
        $searchform->parse('output', 'searchform');

        $retval .= $searchform->finish($searchform->get_var('output'));

        return $retval;
    }

    /**
     * Performs search on all stories
     *
     * @author Tony Bibbs <tony AT geeklog DOT net>
     *         Sami Barakat <s.m.barakat AT gmail DOT com>
     * @access private
     * @return object plugin object
     *
     */
    function _searchStories()
    {
        global $_CONF, $_TABLES, $_DB_dbms, $LANG09;

        // Make sure the query is SQL safe
        $query = trim(DB_escapeString(htmlspecialchars($this->_query)));

        $sql = "SELECT s.sid AS id, s.title AS title, s.introtext AS description, UNIX_TIMESTAMP(s.date) AS date, s.uid AS uid, s.hits AS hits, CONCAT('/article.php?story=',s.sid) AS url ";
        $sql .= "FROM {$_TABLES['stories']} AS s, {$_TABLES['users']} AS u ";
        $sql .= "WHERE (draft_flag = 0) AND (date <= '".$_CONF['_now']->toMySQL(true)."') AND (u.uid = s.uid) ";
        $sql .= COM_getPermSQL('AND') . COM_getTopicSQL('AND') . COM_getLangSQL('sid', 'AND') . ' ';

        if (!empty($this->_topic)) {
            $sql .= "AND (s.tid = '$this->_topic') ";
        }
        if (!empty($this->_author)) {
            $sql .= "AND (s.uid = '$this->_author') ";
        }

        $search = new SearchCriteria('stories', $LANG09[65]);
        $columns = array('introtext','bodytext','title');
        $sql .= $search->getDateRangeSQL('AND', 'UNIX_TIMESTAMP(s.date)', $this->_dateStart, $this->_dateEnd);
        list($sql,$ftsql) = $search->buildSearchSQL($this->_keyType, $query, $columns, $sql);
        $search->setSQL($sql);
        $search->setFTSQL($ftsql);
        $search->setRank(5);
        $search->setURLRewrite(true);

        return $search;
    }

    /**
     * Performs search on all comments
     *
     * @author Tony Bibbs <tony AT geeklog DOT net>
     *         Sami Barakat <s.m.barakat AT gmail DOT com>
     * @access private
     * @return object plugin object
     *
     */
    function _searchComments()
    {
        global $_CONF, $_TABLES, $_DB_dbms, $LANG09;

        // Make sure the query is SQL safe
        $query = trim(DB_escapeString(htmlspecialchars($this->_query)));

        $sql = "SELECT s.sid AS id, c.title AS title, c.comment AS description, UNIX_TIMESTAMP(c.date) AS date, c.uid AS uid, '0' AS hits, ";

        if ( $_CONF['url_rewrite'] ) {
            $sql .= "CONCAT('/article.php/',s.sid,'#comments') AS url ";
        } else {
            $sql .= "CONCAT('/article.php?story=',s.sid,'#comments') AS url ";
        }

        $sql .= "FROM {$_TABLES['users']} AS u, {$_TABLES['comments']} AS c ";
        $sql .= "LEFT JOIN {$_TABLES['stories']} AS s ON ((s.sid = c.sid) ";
        $sql .= COM_getPermSQL('AND',0,2,'s') . COM_getTopicSQL('AND',0,'s') . COM_getLangSQL('sid','AND','s') . ") ";
        $sql .= "WHERE (u.uid = c.uid) AND (s.draft_flag = 0) AND (s.commentcode >= 0) AND (s.date <= '".$_CONF['_now']->toMySQL(true)."') ";

        if (!empty($this->_topic))
            $sql .= "AND (s.tid = '".DB_escapeString($this->_topic)."') ";
        if (!empty($this->_author))
            $sql .= "AND (c.uid = ".(int) $this->_author.") ";

        $search = new SearchCriteria('comments', $LANG09[65] . ' > '. $LANG09[66]);
        $columns = array('comment','c.title');
        $sql .= $search->getDateRangeSQL('AND', 'UNIX_TIMESTAMP(c.date)', $this->_dateStart, $this->_dateEnd);
        list($sql,$ftsql) = $search->buildSearchSQL($this->_keyType, $query, $columns, $sql);
        $search->setSQL($sql);
        $search->setFTSQL($ftsql);
        $search->setRank(2);

        return $search;
    }

    /**
     * Kicks off the appropriate search(es)
     *
     * Initiates the search engine and returns HTML formatted
     * results. It also provides support to plugins using a
     * search API.
     *
     * @author Sami Barakat <s.m.barakat AT gmail DOT com>
     * @access public
     * @return string HTML output for search results
     *
     */
    function doSearch()
    {
        global $_CONF, $LANG01, $LANG09, $LANG31, $_TABLES, $_USER;

        if ( !isset($_CONF['comment_engine'])) $_CONF['comment_engine'] = 'internal';

        $debug_info = '';
        $retval = '';
        $list_top = '';

        // Verify current user can perform requested search
        if (!$this->_isSearchAllowed())
            return $this->_getAccessDeniedMessage();

        // Make sure there is a query string
        // Full text searches have a minimum word length of 3 by default

        if (empty($this->_query)) {
            if ( (empty($this->_author) || $this->_author==0 )  &&
                 (empty($this->_type)   || $this->_type=='all') &&
                 (empty($this->_topic)  || $this->_topic=='all') &&
                 (empty($this->_dateStart) || empty($this->_dateEnd))
             ) {
                $retval = $this->showForm();
                $retval .= '<div style="margin-bottom:5px;border-bottom:1px solid #ccc;"></div><p>' . $LANG09[41] . '</p>' . LB;

                return $retval;
            }
        } elseif ( strlen($this->_query) < 3 ) {
            $retval = $this->showForm();
            $retval .= '<div style="margin-bottom:5px;border-bottom:1px solid #ccc;"></div><p>' . $LANG09[41] . '</p>' . LB;

            return $retval;
        }

        // Build the URL strings
        $this->_searchURL = $_CONF['site_url'] . '/search.php?query=' . urlencode($this->_query) .
            ((!empty($this->_keyType))    ? '&amp;keyType=' . urlencode($this->_keyType) : '' ) .
            ((!empty($this->_dateStart))  ? '&amp;datestart=' . urlencode($this->_dateStart) : '' ) .
            ((!empty($this->_dateEnd))    ? '&amp;dateend=' . urlencode($this->_dateEnd) : '' ) .
            ((!empty($this->_topic))      ? '&amp;topic=' . urlencode($this->_topic) : '' ) .
            ((!empty($this->_author))     ? '&amp;author=' . urlencode($this->_author) : '' ) .
            ((!empty($this->_searchDays)) ? '&amp;st=' . urlencode($this->_searchDays) : '' )
            ;

        $url = "{$this->_searchURL}&amp;type={$this->_type}&amp;mode=";
        $obj = new ListFactory($url.'search', $_CONF['search_limits'], $_CONF['num_search_results']);
        $obj->setField('ID', 'id', false);
        $obj->setField('URL', 'url', false);

        $show_num  = $_CONF['search_show_num'];
        $show_type = $_CONF['search_show_type'];
        $show_user = $_CONF['search_show_user'];
        $show_hits = $_CONF['search_show_hits'];
        $style = isset($_CONF['search_style']) ? $_CONF['search_style'] : 'google';
        if ( !COM_isAnonUser() ) {
            $userStyle = DB_getItem($_TABLES['userprefs'],'search_result_format','uid='.(int) $_USER['uid']);
            if ( $userStyle != '' ) {
                $style = $userStyle;
            }
        }

        if ($style == 'table') {
            $obj->setStyle('table');
            //             Title        Name           Display     Sort   Format
            $obj->setField($LANG09[62], ROW_NUMBER,    $show_num,  false, '<b>%d.</b>');
            $obj->setField($LANG09[5],  SQL_TITLE,     $show_type, true,  '<b>%s</b>');
            $obj->setField($LANG09[16], 'title',       true,       true);
            $obj->setField($LANG09[63], 'description', true,       false);
            $obj->setField($LANG09[17], 'date',        true,       true);
            $obj->setField($LANG09[18], 'uid',         $show_user, true);
            $obj->setField($LANG09[50], 'hits',        $show_hits, true);
            $this->_wordlength = 7;
        } else if ($style == 'google') {
            $obj->setStyle('inline');
            $obj->setField('',          ROW_NUMBER,    $show_num,  false, '<span style="font-size:larger; font-weight:bold;">%d.&nbsp;</span>');
            $obj->setField($LANG09[16], 'title',       true,       true,  '<span style="font-size:larger; font-weight:bold;">%s</span><br/>');
            $obj->setField('',          'description', true,       false, '%s<br/>');
            $obj->setField('',          '_html',       true,       false, '<span style="color:green;">');
            $obj->setField($LANG09[18], 'uid',         $show_user, true,  $LANG01[104].' %s ');
            $obj->setField($LANG09[17], 'date',        true,       true,  $LANG01[36].' %s');
            $obj->setField($LANG09[5],  SQL_TITLE,     $show_type, true,  ' - %s');
            $obj->setField($LANG09[50], 'hits',        $show_hits, true,  ' - %s '.$LANG09[50]);
            $obj->setField('',          '_html',       true,       false, '</span>');
            $this->_wordlength = 50;
        }
        $obj->setDefaultSort('date');

        $obj->setRowFunction(array($this, 'searchFormatCallBack'));

        // Start search timer
        $searchtimer = new timerobject();
        $searchtimer->setPercision(4);
        $searchtimer->startTimer();

        // Have plugins do their searches
        $page = isset($_REQUEST['page']) ? COM_applyFilter($_REQUEST['page'], true) : 1;
        $result_plugins = PLG_doSearch($this->_query, $this->_dateStart, $this->_dateEnd, $this->_topic, $this->_type, $this->_author, $this->_keyType, $page, 5);
        $result_plugins_comment = PLG_doSearchComment($this->_query, $this->_dateStart, $this->_dateEnd, $this->_topic, $this->_type, $this->_author, $this->_keyType, $page, 5);
        $result_plugins = array_merge($result_plugins, $result_plugins_comment);

        // Add core searches
        if ($this->_type == 'all' || $this->_type == 'stories')
            $result_plugins[] = $this->_searchStories();
        if (($this->_type == 'all' || $this->_type == 'comments') && $_CONF['comment_engine'] == 'internal' )
            $result_plugins[] = $this->_searchComments();

        // Loop through all plugins separating the new API from the old
        $new_api = 0;
        $old_api = 0;
        $num_results = 0;

        if ( !isset($_CONF['search_use_fulltext']) ) {
            $_CONF['search_use_fulltext'] = false;
        }

        foreach ($result_plugins as $result) {
            if (is_a($result, 'SearchCriteria')) {
                $debug_info .= $result->getName() . " using APIv2, ";

                $type = $result->getType();
                if ( $type == 'sql' ) {
                    if ($_CONF['search_use_fulltext'] == true && $result->getFTSQL() != '') {
                        $debug_info .= "search using FULLTEXT\n";
                        $sql = $result->getFTSQL();
                    } else {
                        $debug_info .= "search using LIKE\n";
                        $sql = $result->getSQL();
                    }

                    $sql = $this->_convertsql($sql);

                    $obj->setQuery($result->getLabel(), $result->getName(), $sql, $result->getRank());
                    $this->_url_rewrite[ $result->getName() ] = $result->UrlRewriteEnable() ? true : false;
                } else if ($type == 'text') {
                    $obj->setQueryText($result->getLabel(), $result->getName(), $this->_query, $result->getNumResults(), $result->getRank());
                }
                $new_api++;
            } else if (is_a($result, 'Plugin') && $result->num_searchresults != 0) {
                // Some backwards compatibility
                $debug_info .= $result->plugin_name . " using APIv1, search using backwards compatibility\n";

                // Find the column heading names that closely match what we are looking for
                // There may be issues here on different languages, but this _should_ capture most of the data
                $col_title = $this->_findColumn($result->searchheading, array($LANG09[16],$LANG31[4],'Question'));//Title,Subject
                $col_desc = $this->_findColumn($result->searchheading, array($LANG09[63],'Answer'));
                $col_date = $this->_findColumn($result->searchheading, array($LANG09[17]));//'Date','Date Added','Last Updated','Date & Time'
                $col_user = $this->_findColumn($result->searchheading, array($LANG09[18],'Submited by'));
                $col_hits = $this->_findColumn($result->searchheading, array($LANG09[50],$LANG09[23],'Downloads','Clicks'));//'Hits','Views'
                $col_url  = $this->_findColumn($result->searchheading, array('URL'));//'Hits','Views'

                $label = str_replace($LANG09[59], '', $result->searchlabel);

                if ( $result->num_itemssearched > 0 ) {
                    $_page = isset($_REQUEST['page']) ? COM_applyFilter($_REQUEST['page'], true) : 1;
                    if (isset($_REQUEST['results'])) {
                        $_per_page = COM_applyFilter($_REQUEST['results'], true);
                    } else {
                        $_per_page = $obj->getPerPage();
                    }
                    $obj->addTotalRank(3);
                    $pp = round((3 / $obj->getTotalRank()) * $_per_page);
                    $offset = ($_page - 1) * $pp;
                    $limit  = $pp;

                    $obj->addToTotalFound($result->num_itemssearched);

                    $counter = 0;

                    // Extract the results
                    foreach ($result->searchresults as $old_row) {
                        if ( $counter >= $offset && $counter <= ($offset+$limit) ) {
                            if ($col_date != -1) {
                                // Convert the date back to a timestamp
                                $date = $old_row[$col_date];
                                $date = substr($date, 0, strpos($date, '@'));
                                if ($date == '') {
                                    $date = $old_row[$col_date];
                                } else {
                                    $date = strtotime($date);
                                }
                            }

                            $api_results = array(
                                        SQL_NAME =>       $result->plugin_name,
                                        SQL_TITLE =>      $label,
                                        'title' =>        $col_title == -1 ? $_CONF['search_no_data'] : $old_row[$col_title],
                                        'description' =>  $col_desc == -1 ? $_CONF['search_no_data'] : $old_row[$col_desc],
                                        'date' =>         $col_date == -1 ? '&nbsp;' : $date,
                                        'uid' =>          $col_user == -1 ? '' : $old_row[$col_user],
                                        'hits' =>         $col_hits == -1 ? '0' : str_replace(',', '', $old_row[$col_hits]),
                                        'url' =>          $old_row[$col_url]
                                    );

                            $obj->addResult($api_results);
                        }
                        $counter++;
                    }
                }
                $old_api++;
            }
        }

        // Find out how many plugins are on the old/new system
        $debug_info .= "\nAPIv1: $old_api\nAPIv2: $new_api";

        // Execute the queries
        $results = $obj->ExecuteQueries();

        // Searches are done, stop timer
        $searchtime = $searchtimer->stopTimer();

        $escquery = htmlspecialchars($this->_query);
        if ($this->_keyType == 'any') {
            $searchQuery = str_replace(' ', "</b>' " . $LANG09[57] . " '<b>", $escquery);
            $searchQuery = "<b>'$searchQuery'</b>";
        } else if ($this->_keyType == 'all') {
            $searchQuery = str_replace(' ', "</b>' " . $LANG09[56] . " '<b>", $escquery);
            $searchQuery = "<b>'$searchQuery'</b>";
        } else {
            $searchQuery = $LANG09[55] . " '<b>$escquery</b>'";
        }
        // Clean the query string so that sprintf works as expected
        $searchQuery = str_replace("%", "%%", $searchQuery);

        $searchText = "{$LANG09[25]} $searchQuery. ";

        $retval .= $this->showForm();

        if (count($results) == 0) {
            $retval .= '<div style="margin-bottom:5px;border-bottom:1px solid #ccc;"></div>';
            $retval .= $LANG09[74];
        } else {
            $retval .= $obj->getFormattedOutput($results, $LANG09[11], $list_top, '');
        }

        return $retval;
    }


    /**
     * CallBack function for the ListFactory class
     *
     * This function gets called by the ListFactory class and formats
     * each row accordingly for example pulling usernames from the
     * users table and displaying a link to their profile.
     *
     * @author Sami Barakat <s.m.barakat AT gmail DOT com>
     * @access public
     * @param array $row An array of plain data to format
     * @return array A reformatted version of the input array
     *
     */
    function searchFormatCallBack($preSort, $row)
    {
        global $_CONF, $_USER, $LANG21;

        $dt = new Date('now',$_USER['tzid']);

        $filter = \sanitizer::getInstance();
        $filter->setReplaceTags(false);
        $filter->setCensorData(true);
        $filter->setPostmode('html');

        if ($preSort) {
            $row[SQL_TITLE] = is_array($row[SQL_TITLE]) ? implode($_CONF['search_separator'],$row[SQL_TITLE]) : $row[SQL_TITLE];

            if (isset($row['uid']) && is_numeric($row['uid']))
            {
                if (empty($this->_names[ $row['uid'] ]))
                {
                    $this->_names[ $row['uid'] ] = htmlspecialchars(COM_getDisplayName( $row['uid'] ));
                    if ($row['uid'] != 1)
                        $this->_names[$row['uid']] = COM_createLink($this->_names[ $row['uid'] ],
                                    $_CONF['site_url'] . '/users.php?mode=profile&amp;uid=' . $row['uid']);
                }
                $row['uid'] = $this->_names[ $row['uid'] ];
            }
        } else {
            $row[SQL_TITLE] = COM_createLink($row[SQL_TITLE], $this->_searchURL.'&amp;type='.$row[SQL_NAME].'&amp;mode=search');

            $row['url'] = (isset($row['url'][0]) && $row['url'][0] == '/' ? $_CONF['site_url'] : '') . $row['url'];
            if (isset($this->_url_rewrite[$row[SQL_NAME]]) && $this->_url_rewrite[$row[SQL_NAME]])
                $row['url'] = COM_buildUrl($row['url']);

            $query_sep = (strpos($row['url'],'?')) ? '&' : '?';

            if ( strpos($row['url'],'#') != 0 ) {
                $pos = strpos($row['url'],'#');
                $begin_url = substr($row['url'],0,$pos);
                $end_url  = substr($row['url'],$pos);
            } else {
                $begin_url = $row['url'];
                $end_url = '';
            }
            $this->_query = trim($this->_query);
            $row['url'] = $begin_url . $query_sep . 'query=' . urlencode($this->_query) . $end_url;

            if ( $row['title'] == '' ) {
                $row['title'] = $LANG21[61];
            }

            $filter->setPostmode('text');
            $title = COM_getTextContent($row['title']);
            $row['title'] = $filter->displayText($title);
            $row['title'] = str_replace('$', '&#36;', $row['title']);
            if ($row['title'] == '') $row['title'] = 'No title available';
            $row['title'] = COM_createLink($row['title'], $row['url']);

            if ( $row['description'] == '' ) {
                $row['description'] = $_CONF['search_no_data'];
            } else {
                $row['description'] = $row['description'];
            }

            $row['description'] = PLG_replaceTags($row['description']);

            if ($row['description'] != $_CONF['search_no_data']) {
                $row['description'] = $this->_shortenText($this->_query, $row['description'], $this->_wordlength);
            }
            $dt->setTimestamp($row['date']);
            $row['date'] = $dt->format($_CONF['daytime'],true);
            $row['hits'] = COM_NumberFormat($row['hits']).' '; // simple solution to a silly problem!
        }

        return $row;
    }

    /**
     * Shortens a long text string to only a few words
     *
     * Returns a shorter version of the in putted text centred
     * around the keyword. The keyword is highlighted in bold.
     * Adds '...' to the beginning or the end of the shortened
     * version depending where the text was cut. Works on a
     * word basis, so long words wont get cut.
     *
     * @author Sami Barakat <s.m.barakat AT gmail DOT com>
     * @access private
     * @param string $keyword The word to centre around
     * @param string $text The complete text string
     * @param integer $num_words The number of words to display, best to use an odd number
     * @return string A short version of the text
     *
     */
    function _shortenText($keyword, $text, $num_words = 7)
    {
        $text = COM_getTextContent($text);

        // parse some general bbcode / auto tags
        $bbcode = array(
            "/\[b\](.*?)\[\/b\]/is" => "$1",
            "/\[u\](.*?)\[\/u\]/is" => "$1",
            "/\[i\](.*?)\[\/i\]/is" => "$1",
            "/\[quote\](.*?)\[\/quote\]/is" => "$1",
            "/\[code\](.*?)\[\/code\]/is" => " $1 ",
            "/\[p\](.*?)\[\/p\]/is" => " $1 ",
            "/\[url\=(.*?)\](.*?)\[\/url\]/is" => "$2",
            "/\[wiki:(.*?) (.*?)[\]]/is" => "$2"
        );
        $text = @preg_replace(array_keys($bbcode), array_values($bbcode), $text);

        $words = explode(' ', $text);
        $word_count = count($words);
        if ($word_count <= $num_words) {
            return COM_highlightQuery($text, $keyword, 'b');
        }

        $rt = '';
        $pos = $this->_stripos($text, $keyword);
        if ($pos !== false) {
            $pos_space = utf8_strpos($text, ' ', $pos);
            if (empty($pos_space)) {
                // Keyword at the end of text
                $key = $word_count - 1;
                $start = 0 - $num_words;
                $end = 0;
                $rt = '<b>...</b> ';
            } else {
                $str = utf8_substr($text, $pos, $pos_space - $pos);
                $m = (int) (($num_words - 1) / 2);
                $key = $this->_arraySearch($keyword, $words);
                if ($key === false) {
                    // Keyword(s) not found - show start of text
                    $key = 0;
                    $start = 0;
                    $end = $num_words - 1;
                } elseif ($key <= $m) {
                    // Keyword at the start of text
                    $start = 0 - $key;
                    $end = $num_words - 1;
                    $end = ($key + $m <= $word_count - 1)
                         ? $key : $word_count - $m - 1;
                    $abs_length = abs($start) + abs($end) + 1;
                    if ($abs_length < $num_words) {
                        $end += ($num_words - $abs_length);
                    }
                } else {
                    // Keyword in the middle of text
                    $start = 0 - $m;
                    $end = ($key + $m <= $word_count - 1)
                         ? $m : $word_count - $key - 1;
                    $abs_length = abs($start) + abs($end) + 1;
                    if ($abs_length < $num_words) {
                        $start -= ($num_words - $abs_length);
                    }
                    $rt = '<b>...</b> ';
                }
            }
        } else {
            $key = 0;
            $start = 0;
            $end = $num_words - 1;
        }

        for ($i = $start; $i <= $end; $i++) {
            $rt .= $words[$key + $i] . ' ';
        }
        if ($key + $i != $word_count) {
            $rt .= ' <b>...</b>';
        }
        return COM_highlightQuery($rt, $keyword, 'b');
    }

    /**
    * Search array of words for keyword(s)
    *
    * @param   string  $needle    keyword(s), separated by spaces
    * @param   array   $haystack  array of words to search through
    * @return  mixed              index in $haystack or false when not found
    * @access  private
    *
    */
    function _arraySearch($needle, $haystack)
    {
        $keywords = explode(' ', $needle);
        $num_keywords = count($keywords);

        foreach ($haystack as $key => $value) {
            if ($this->_stripos($value, $keywords[0]) !== false) {
                if ($num_keywords == 1) {
                    return $key;
                } else {
                    $matched_all = true;
                    for ($i = 1; $i < $num_keywords; $i++) {
                        if ($this->_stripos($haystack[$key + $i], $keywords[$i]) === false) {
                            $matched_all = false;
                            break;
                        }
                    }
                    if ($matched_all) {
                        return $key;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Finds the similarities between heading names
     *
     * Returns the index of a heading that matches a
     * number of similar heading names. Used for backwards
     * compatibility in the doSearch() function.
     *
     * @author Sami Barakat <s.m.barakat AT gmail DOT com>
     * @access private
     * @param array $headings All the headings
     * @param array $find An array of alternative headings to find
     * @return integer The index of the alternative heading
     *
     */
    function _findColumn($headings, $find)
    {
        // We can't use normal for loops here as some of the
        // heading indexes start from 1, so foreach works better
        foreach ($find as $fh)
        {
            $j = 0;
            foreach ($headings as $h)
            {
                if (preg_match("/$fh/i", $h) > 0)
                    return $j;
                $j++;
            }
        }
        return -1;
    }

    /**
     * Converts the MySQL CONCAT function to the MSSQL equivalent
     *
     * @author Sami Barakat <s.m.barakat AT gmail DOT com>
     * @access private
     * @param string $sql The SQL to convert
     * @return string MSSQL friendly SQL
     *
     */
    function _convertsql($sql)
    {
        global $_DB_dbms;
        if ($_DB_dbms == 'mssql')
        {
            if (is_string($sql))
                $sql = preg_replace("/CONCAT\(([^\)]+)\)/ie", "preg_replace('/,?(\'[^\']+\'|[^,]+),/i', '\\\\1 + ', '\\1')", $sql);
            else if (is_array($sql))
                $sql['mssql'] = preg_replace("/CONCAT\(([^\)]+)\)/ie", "preg_replace('/,?(\'[^\']+\'|[^,]+),/i', '\\\\1 + ', '\\1')", $sql['mssql']);
        }
        return $sql;
    }

    function _highlightQuery( $text, $query, $class = 'highlight')
    {
        $query = str_replace( '+', ' ', $query );

        // escape all the other PCRE special characters
        $query = preg_quote( $query );
        // ugly workaround:
        // Using the /e modifier in preg_replace will cause all double quotes to
        // be returned as \" - so we replace all \" in the result with unescaped
        // double quotes. Any actual \" in the original text therefore have to be
        // turned into \\" first ...
        $text = str_replace( '\\"', '\\\\"', $text );

        if ( $this->_keyType == 'phrase' ) {
            $squery = str_replace('/',' ',$query);
            $mywords = array($squery);
        } else {
            $squery = str_replace('/',' ',$query);
            $mywords = explode( ' ', $squery );
        }
        foreach( $mywords as $searchword )
        {
            if( !empty( $searchword ))
            {
                $searchword = preg_quote( str_replace( "'", "\'", $searchword ));
                if ( $this->_charset == 'utf-8' ) {
                    $text = @preg_replace( '/(\>(((?>[^><]+)|(?R))*)\<)/uie', "preg_replace('/(?>$searchword+)/ui','<span class=\"$class\">\\\\0</span>','\\0')", '<!-- x -->' . $text . '<!-- x -->' );
                } else {
                    $text = @preg_replace( '/(\>(((?>[^><]+)|(?R))*)\<)/ie', "preg_replace('/(?>$searchword+)/i','<span class=\"$class\">\\\\0</span>','\\0')", '<!-- x -->' . $text . '<!-- x -->' );
                }
            }
        }

        // ugly workaround, part 2
        $text = str_replace( '\\"', '"', $text );
        return $text;
    }

    function _stripos($haystack, $needle)
    {

        if ( $this->_charset == 'utf-8' ) {
            if ( MBYTE_strlen($needle) > 0 ) {
                $haystack = MBYTE_strtolower($haystack);
                return MBYTE_strpos($haystack,$needle);
            } else {
                return false;
            }
        }

        if (function_exists('stripos')) {
            return stripos($haystack, $needle);
        } else {
            return strpos(strtolower($haystack), strtolower($needle));
        }
    }

    function _validateDate( $dateString )
    {
        $delim = substr($dateString, 4, 1);
        if (!empty($delim)) {
            $DS = explode($delim, $dateString);
            if ( intval($DS[0]) < 1970 ) {
                return false;
            }
            if ( intval($DS[1]) < 1 || intval($DS[1]) > 12 ) {
                return false;
            }
            if ( intval($DS[2]) < 1 || intval($DS[2]) > 31 ) {
                return false;
            }
        } else {
            return false;
        }
        return true;
    }
}

?>