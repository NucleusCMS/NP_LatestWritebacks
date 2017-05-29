<?php
class NP_LatestWritebacks extends NucleusPlugin
{
 
	function supportsFeature($what)
	{
		switch ($what) {
			case 'SqlTablePrefix':
				return 1;
			default:
				return 0;
		}
	}

//	function getEventList()
//	{
//		return array();
//	}

	function getName()
	{
		return 'Latest Writebacks';
	}

	function getAuthor()
	{
		return 'nakahara21 + Fujisaki + kimitake + shizuki';
	}

	function getURL()
	{
		return 'http://nakahara21.com/';
	}

	function getVersion()
	{
		return '1.7';
	}

	function getDescription()
	{
		// include language file for this plugin 
		$language = str_replace( array('\\','/'), '', getLanguageName()); 
		if (file_exists($this->getDirectory() . $language . '.php')) {
			include_once($this->getDirectory() . $language . '.php'); 
		} else {
			include_once($this->getDirectory() . 'english.php');
		}
		$description = _NP_LRWITEBACKS_DESC;
//		$description = 'This plugin can be used to display the last few comments'
//					 . 'and Trackbacks.<br />'
//					 . 'Usage:&lt;%LatestWritebacks(10,current,all)%&gt;';
		return $description;
	}
 
	function install()
	{
		// include language file for this plugin 
		$language = str_replace( array('\\','/'), '', getLanguageName()); 
		if (file_exists($this->getDirectory() . $language . '.php')) {
			include_once($this->getDirectory() . $language . '.php'); 
		} else {
			include_once($this->getDirectory() . 'english.php');
		}
		$this->createOption('timelocale',   _NP_LRWITEBACKS_TZLOC, 'text',     'ja_JP.' . _CHARSET);
		$this->createOption('cmdateformat', _NP_LRWITEBACKS_CDFMT, 'text',     '%Y-%m-%d %H:%M:%S');
		$this->createOption('tbdateformat', _NP_LRWITEBACKS_TEFMT, 'text',     '%m-%d');
		$this->createOption('cmlisthead',   _NP_LRWITEBACKS_CHEAD, 'textarea', '<ul class="nobullets">');
		$this->createOption('cmttemplate',  _NP_LRWITEBACKS_CBODY, 'textarea',
		'<li>&clubs;<a href="<%itemlink%>#c<%commentid%>"><%commentdate%>|<%commentator%>&gt;<%commentbody%></a></li>');
		$this->createOption('cmlistfoot',   _NP_LRWITEBACKS_CFOOT, 'textarea', '</ul>');
		$this->createOption('tblisthead',   _NP_LRWITEBACKS_THEAD, 'textarea', '<ul class="nobullets">');
		$this->createOption('tbktemplate',  _NP_LRWITEBACKS_TBODY, 'textarea',
		'<li>&hellip;<a href="<%itemlink%>#trackback"><%tbdate%>|<%blogname%> ping: "<%entrytitle%>"</a></li>');
		$this->createOption('tblistfoot',   _NP_LRWITEBACKS_TFOOT, 'textarea', '</ul>');
/*
Comment list template sample
 Header
  <ol class="recent-comment">
 Body
  <li><a href="<%itemlink%>#c<%commentid%>" title="<%commentbody%>"><%commentator%>(<%commentday%>)</a></li>
 Footer
  </ol>

TrackBack list template sample
 Header
  <ol class="recent-trackback">
 Body
  <li><a href="<%itemlink%>#tb<%tbid%>" title="<%expect%>"><%blogname%> : <%entrytitle%>(<%tbday%>)</a></li>
 Footer
  </ol>
*/
	}

	function pluginCheck($pluginName)
	{
		global $manager;
		if (!$manager->pluginInstalled('NP_' . $pluginName)) {
			return false;
		}
		$plugin =& $manager->getPlugin('NP_' . $pluginName);
		return $plugin;
	}

	function doSkinVar($skinType,
					   $numberOfWritebacks      = 5,
					   $filter                  = '',
					   $TBorCm                  = 'all',
					   $numberOfCharacters      = 60,
					   $numberOfTitleCharacters = 40,
					   $toadd                   = "...")
	{
		global $manager, $CONF, $blog;

		if (!is_numeric($numberOfWritebacks)) {
			$filter             = $numberOfWritebacks;
			$numberOfWritebacks = 5;					// defaults to 5
		}
		$b                    =& $manager->getBlog($CONF['DefaultBlog']);
		$this->defaultblogurl = $b->getURL() ;
		if (!$this->defaultblogurl)
			$this->defaultblogurl = $CONF['IndexURL'] ;
		if ($blog) {
			$b =& $blog;
		}
		$blogid = $b->getID();							//for select

		$filter = trim($filter);
		if($filter == 'current'){
			$filter = 'cblog = ' . $blogid;
		} elseif (strstr($filter, '=')) {
			$filter = str_replace('=', '', $filter);
			$filter = ' cblog IN(' . str_replace('/', ',', $filter) . ')';
		} elseif (strstr($filter, '<>')) {
			$filter = str_replace('<>', '', $filter);
			$filter = ' cblog <> ' . str_replace('/', ' AND cblog <> ', $filter);
		}

		setlocale(LC_TIME, $this->getOption('timelocale'));
		$arr_res = array();

		if ($TBorCm != 't') {
			$join  = '';
			$query = 'SELECT'
				   . ' c.cnumber as commentid,'
				   . ' c.cuser   as commentator,'
				   . ' c.cbody   as commentbody,'
				   . ' c.citem   as itemid,'
				   . ' c.cmember as memberid,'
//				   . ' c.ctime   as commentdate,'
				   . ' SUBSTRING(c.ctime, 6, 5) as commentday,'
				   . ' UNIX_TIMESTAMP(c.ctime)  as ctimest';
			if ($EzComment2 = $this->pluginCheck('EzComment2')) {
				if (method_exists($EzComment2, 'getTemplateParts')) {
					$query .= ', s.comid   as cid, '
							. 's.secflg  as secret, '
							. 's.module  as modname, '
							. 's.userID  as identity ';
					$join   = ' LEFT OUTER JOIN ' . sql_table('plug_ezcomment2') . ' as s '
							. ' ON c.cnumber = s.comid ';
				}
			}
			// select
			$query .= ' FROM ' . sql_table('comment') .' as c ' . $join;
			if ($filter) {
				$query .= ' WHERE ' . $filter;
			}
			$query .= ' ORDER by c.ctime DESC LIMIT 0, ' . $numberOfWritebacks;

			$comments = sql_query($query);

			if (sql_num_rows($comments)) {
				while ($row = sql_fetch_object($comments)) {
					$content                = (array)$row;
					$tempBody               = strip_tags($content['commentbody']);
					$tempBody               = htmlspecialchars($tempBody, ENT_QUOTES,_CHARSET);
					$tempBody               = shorten($tempBody, $numberOfCharacters, $toadd);
					$tempBody               = htmlspecialchars($tempBody, ENT_QUOTES,_CHARSET);
					$tempBody               = str_replace("\r\n", ' ', $tempBody);
					$tempBody               = str_replace("&amp;", '&', $tempBody);
					$tempBody               = str_replace("&gt;", '>', $tempBody);
					$tempBody               = str_replace("&lt;", '<', $tempBody);
					$content['commentdate'] = strftime($this->getOption('cmdateformat'), $content['ctimest']);
					$content['commentbody'] = str_replace("&amp;amp;", '&amp;', $tempBody);
					if (!empty($row->memberid)) {
						$mem                    = new MEMBER;
						$mem->readFromID(intval($row->memberid));
						$content['commentator'] = $mem->getRealName();
					}
					if ($EzComment2) {
						$bid = intval(getBlogIDFromItemID(intval($comment['itemid'])));
						if ($EzComment2->getBlogOption($bid, 'secret') == 'yes') {
							$b     = $manager->getBlog($bid);
							global $member;
							$judge = $EzComment2->setSecretJudge($bid, $member, $b);
						}
					}
					if ($judge && $content['secret']) {
						$content = $EzComment2->JudgementCommentSecrets($content, $judge);
					}

/*					$cid  = $row->cnumber;
					$ct  = $row->ctimest;
					$ctst  = date("y-m-d H:i",$ct);
					$text = strip_tags($row->cbody);
					$text = htmlspecialchars($text, ENT_QUOTES,_CHARSET);
					$ctext = shorten($text,$numberOfCharacters,$toadd);
 
					if (!$row->cmember) $myname = $row->cuser;
					else {
						$mem = new MEMBER;
						$mem->readFromID(intval($row->cmember));
						$myname = $mem->getRealName();
					}*/
 
//					$itemlink = $this->_createItemLink($row->citem, '');
//					$arr_res[$ct] =  "<li>&clubs;<a href=\"".$itemlink."#c".$cid."\">$ctst|".$myname."&gt;".$ctext."</a></li>" ;
					$itemlink                     = $this->_createItemLink($content['itemid']);
					$content['itemlink']          = $itemlink;
					$arr_res[$content['ctimest']] = TEMPLATE::fill($this->getOption('cmttemplate'), $content);
				}
			}
		}
 
//=========================
 
		if ($manager->pluginInstalled('NP_TrackBack') && $TBorCm != 'c') {
			$query = 'SELECT'
				   . ' t.id        as tbid,'
				   . ' t.title     as entrytitle,'
				   . ' t.excerpt   as expect,'
				   . ' t.url       as tburl,'
				   . ' t.tb_id     as trackbackid,'
				   . ' t.blog_name as blogname,'
				   . ' t.timestamp as tbdate,'
				   . ' SUBSTRING(t.timestamp, 6, 5) as tbday,'
				   . ' UNIX_TIMESTAMP(t.timestamp)  as ttimest'
				   . ' FROM ' . sql_table('plugin_tb') . ' t,'
				   . sql_table('item') . ' i'
				   . ' WHERE t.tb_id = i.inumber';
			if ($this->checkTBVersion()) {
				$query .= ' and t.block = 0';
			}
			if ($filter) {
				$tfilter = str_replace('c.cblog', 'i.iblog', $filter);
				$query .= ' and ' . $tfilter;
			}
			$query .= ' ORDER by t.timestamp DESC LIMIT 0, ' . $numberOfWritebacks;

			$comments = sql_query($query);

			if (sql_num_rows($comments)) {
				while ($row = sql_fetch_object($comments)) {
					$content               = (array)$row;
					$entrytitle            = strip_tags($content['entrytitle']);
					$entrytitle            = htmlspecialchars($entrytitle, ENT_QUOTES,_CHARSET);
					$entrytitle            = shorten($entrytitle, $numberOfCharacters, $toadd);
					$entrytitle            = htmlspecialchars($entrytitle, ENT_QUOTES,_CHARSET);
					$content['entrytitle'] = $entrytitle;
					$content['expect']     = str_replace("\r\n", ' ', $content['expect']);
					$blogname              = htmlspecialchars($content['blogname'], ENT_QUOTES,_CHARSET);
					$content['blogname']   = $blogname;
					$content['tbdate']     = strftime($this->getOption('tbdateformat'), $content['ttimest']);

/*					$title = strip_tags($row->title);
					$title = htmlspecialchars($title, ENT_QUOTES,_CHARSET);
					$ctitle = shorten($title,$numberOfCharacters,$toadd);
					$blogname = htmlspecialchars($row->blog_name, ENT_QUOTES,_CHARSET);
					$tbtime = $row->ttimest;
					$ttst  = date("y-m-d H:i",$tbtime);*/

//					$itemlink = $this->_createItemLink($row->tb_id, '');
//					$arr_res[$tbtime] = '<li>&hellip;<a href="'.$itemlink.'#trackback">'.$ttst.'|'.$blogname.' ping: "'.$ctitle.'"</a></li>';
					$itemlink                     = $this->_createItemLink($content['trackbackid']);
					$content['itemlink']          = $itemlink;
					$arr_res[$content['ttimest']] = TEMPLATE::fill($this->getOption('tbktemplate'), $content);
				}
			}
		}
//=========================
		krsort ($arr_res);
		$ress        = array_values($arr_res);
		$show_rescnt = min(intval($numberOfWritebacks), count($arr_res));

		switch ($TBorCm) {
			case 'c':
				$head = $this->getOption('cmlisthead');
				$foot = $this->getOption('cmlistfoot');
				break;
			case 't':
				$head = $this->getOption('tblisthead');
				$foot = $this->getOption('tblistfoot');
				break;
			default:
				$head = ' <ul class="nobullets"> ';
				$foot = ' </ul> ';
				break;
		}
//		echo ' <ul class="nobullets"> ';
		echo $head;
		for ($j=0; $j < $show_rescnt; $j++) {
			echo $ress[$j] . "\n";
		}
		echo $foot;
//		echo " </ul> ";
	}

	function checkTBVersion()
	{
		$res = sql_query('SHOW FIELDS FROM ' . sql_table('plugin_tb') );
		$fieldnames = array();
		while ($co = sql_fetch_assoc($res)) {
			$fieldnames[] = $co['Field'];
		}
		if (in_array('block', $fieldnames)) {
			return TRUE;
		} else {
			return FALSE;
		}
	}

	function _createItemLink($itemid)
	{
		global $CONF, $manager, $blog;

		$blogid  =  getBlogIDFromItemID($itemid);
		$b       =& $manager->getBlog($blogid);
		$blogurl =  $b->getURL();

		if (!$blogurl) {
			if ($blog) {
				$b_tmp   =& $manager->getBlog($CONF['DefaultBlog']);
				$blogurl =  $b_tmp->getURL();
			}
			if (!$blogurl) {
				$blogurl = $CONF['IndexURL'];
				if ($CONF['URLMode'] != 'pathinfo') {
					$blogurl = $CONF['Self'];
				}
			}
		}
		if ($CONF['URLMode'] == 'pathinfo') {
			$blogurl = preg_replace('/\/$/', '', $blogurl);
		}
		$CONF['ItemURL'] = $blogurl;

		return createItemLink($itemid);
	}
 
}
