<?php
/**
 * @version 1.9.6
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.model');
jimport('joomla.html.pagination');

/**
 * JEM Component JEM Model
 *
 * @package JEM
 *
*/
class JEMModelMyevents extends JModelLegacy
{
	/**
	 * Events data array
	 *
	 * @var array
	 */
	var $_events = null;

	/**
	 * Events total
	 *
	 * @var integer
	 */
	var $_total_events = null;

	/**
	 * Pagination object
	 *
	 * @var object
	 */
	var $_pagination_events = null;

	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();

		$app = JFactory::getApplication();
		$jemsettings = JEMHelper::config();

		//get the number of events from database

		$limit		= $app->getUserStateFromRequest('com_jem.myevents.limit', 'limit', $jemsettings->display_num, 'int');
		$limitstart = $app->getUserStateFromRequest('com_jem.myevents.limitstart', 'limitstart', 0, 'int');

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);
	}

	/**
	 * Method to get the Events
	 *
	 * @access public
	 * @return array
	 */
	function & getEvents()
	{
		$pop = JRequest::getBool('pop');

		// Lets load the content if it doesn't already exist
		if ( empty($this->_events)) {
			$query = $this->_buildQueryEvents();
			$pagination = $this->getEventsPagination();

			if ($pop) {
				$this->_events = $this->_getList($query);
			} else {
				$pagination = $this->getEventsPagination();
				$this->_events = $this->_getList($query, $pagination->limitstart, $pagination->limit);
			}
		}

		if($this->_events) {
			$this->_events = JEMHelper::getAttendeesNumbers($this->_events);

			$count = count($this->_events);
			for($i = 0; $i < $count; $i++) {
				$item = $this->_events[$i];
				$item->categories = $this->getCategories($item->eventid);

				//remove events without categories (users have no access to them)
				if (empty($item->categories)) {
					unset($this->_events[$i]);
				}
			}
		}

		return $this->_events;
	}

	/**
	 * Method to (un)publish a event
	 *
	 * @access	public
	 * @return	boolean	True on success
	 *
	 */
	function publish($cid = array(), $publish = 1)
	{
		$user 	= JFactory::getUser();
		$userid = (int) $user->get('id');

		if (count($cid)) {
			$cids = implode(',', $cid);

			$query = 'UPDATE #__jem_events'
					. ' SET published = '. (int) $publish
					. ' WHERE id IN ('. $cids .')'
					. ' AND (checked_out = 0 OR (checked_out = ' .$userid. '))'
					;

			$this->_db->setQuery($query);

			if (!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
		}
	}

	/**
	 * Total nr of events
	 *
	 * @access public
	 * @return integer
	 */
	function getTotalEvents()
	{
		// Lets load the total nr if it doesn't already exist
		if ( empty($this->_total_events)) {
			$query = $this->_buildQueryEvents();
			$this->_total_events = $this->_getListCount($query);
		}

		return $this->_total_events;
	}

	/**
	 * Method to get a pagination object for the events
	 *
	 * @access public
	 * @return integer
	 */
	function getEventsPagination()
	{
		// Lets load the content if it doesn't already exist
		if ( empty($this->_pagination_events)) {
			jimport('joomla.html.pagination');
			$this->_pagination_events = new JPagination($this->getTotalEvents(), $this->getState('limitstart'), $this->getState('limit'));
		}

		return $this->_pagination_events;
	}

	/**
	 * Build the query
	 *
	 * @access private
	 * @return string
	 */
	protected function _buildQueryEvents()
	{
		// Get the WHERE and ORDER BY clauses for the query
		$where = $this->_buildWhere();
		$orderby = $this->_buildOrderBy();

		//Get Events from Database
		$query = 'SELECT DISTINCT a.id as eventid, a.id, a.dates, a.enddates, a.published, a.times, a.endtimes, a.title, a.created, a.locid,a.registra, a.maxplaces, a.waitinglist,'
				. ' a.recurrence_type, a.recurrence_first_id,'
				. ' l.venue, l.city, l.state, l.url,'
				. ' c.catname, c.id AS catid,'
				. ' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug,'
				. ' CASE WHEN CHAR_LENGTH(l.alias) THEN CONCAT_WS(\':\', a.locid, l.alias) ELSE a.locid END as venueslug'
				. ' FROM #__jem_events AS a'
				. ' LEFT JOIN #__jem_venues AS l ON l.id = a.locid'
				. ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.itemid = a.id'
				. ' LEFT JOIN #__jem_categories AS c ON c.id = rel.catid'
				. $where
				. ' GROUP BY a.id'
				. $orderby
				;

		return $query;
	}

	/**
	 * Build the order clause
	 *
	 * @access private
	 * @return string
	 */
	protected function _buildOrderBy()
	{
		$app = JFactory::getApplication();

		$filter_order		= $app->getUserStateFromRequest('com_jem.myevents.filter_order', 'filter_order', 'a.dates', 'cmd');
		$filter_order_Dir	= $app->getUserStateFromRequest('com_jem.myevents.filter_order_Dir', 'filter_order_Dir', 'ASC', 'word');

		$filter_order		= JFilterInput::getInstance()->clean($filter_order, 'cmd');
		$filter_order_Dir	= JFilterInput::getInstance()->clean($filter_order_Dir, 'word');

		if ($filter_order == 'a.dates') {
			$orderby = ' ORDER BY a.dates ' . $filter_order_Dir .', a.times ' . $filter_order_Dir;
		} else {
			$orderby = ' ORDER BY ' . $filter_order . ' ' . $filter_order_Dir;
		}

		return $orderby;
	}

	/**
	 * Build the where clause
	 *
	 * @access private
	 * @return string
	 */
	protected function _buildWhere()
	{
		$app 		= JFactory::getApplication();
		$task 		= JRequest::getWord('task');
		$params 	= $app->getParams();
		$settings 	= JEMHelper::globalattribs();
		$user 		= JFactory::getUser();
		// Support Joomla access levels instead of single group id
		$levels = $user->getAuthorisedViewLevels();

		$filter 		= $app->getUserStateFromRequest('com_jem.myevents.filter', 'filter', '', 'int');
		$search 		= $app->getUserStateFromRequest('com_jem.myevents.filter_search', 'filter_search', '', 'string');
		$search 		= $this->_db->escape(trim(JString::strtolower($search)));

		$where = array();

		// First thing we need to do is to select only needed events
		if ($task == 'archive') {
			$where[] = ' a.published = 2';
		} else {
			$where[] = ' (a.published = 1 OR a.published = 0)';
		}
		$where[] = ' c.published = 1';
		$where[] = ' c.access IN (' . implode(',', $levels) . ')';

		// then if the user is the owner of the event
		$where[] = ' a.created_by = '.$this->_db->Quote($user->id);

		// get excluded categories
		$excluded_cats = trim($params->get('excluded_cats', ''));

		if ($excluded_cats != '') {
			$cats_excluded = explode(',', $excluded_cats);
			$where [] = '  (c.id!=' . implode(' AND c.id!=', $cats_excluded) . ')';
		}
		// === END Excluded categories add === //

		if ($settings->get('global_show_filter') && $search) {
			switch($filter) {
				case 1:
					$where[] = ' LOWER(a.title) LIKE \'%'.$search.'%\' ';
					break;
				case 2:
					$where[] = ' LOWER(l.venue) LIKE \'%'.$search.'%\' ';
					break;
				case 3:
					$where[] = ' LOWER(l.city) LIKE \'%'.$search.'%\' ';
					break;
				case 4:
					$where[] = ' LOWER(c.catname) LIKE \'%'.$search.'%\' ';
					break;
				case 5:
				default:
					$where[] = ' LOWER(l.state) LIKE \'%'.$search.'%\' ';
			}
		}

		$where = (count($where) ? ' WHERE ' . implode(' AND ', $where) : '');

		return $where;
	}

	function getCategories($id)
	{
		$user = JFactory::getUser();
		// Support Joomla access levels instead of single group id
		$levels = $user->getAuthorisedViewLevels();

		$query = 'SELECT DISTINCT c.id, c.catname, c.access, c.checked_out AS cchecked_out,'
				. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as catslug'
				. ' FROM #__jem_categories AS c'
				. ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.catid = c.id'
				. ' WHERE rel.itemid = '.(int)$id
				. ' AND c.published = 1'
				. ' AND c.access IN (' . implode(',', $levels) . ')'
		;

		$this->_db->setQuery( $query );
		$this->_cats = $this->_db->loadObjectList();

		return $this->_cats;
	}
}
?>
