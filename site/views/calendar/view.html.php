<?php
/**
 * @version 1.9.6
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;


/**
 * Calendar-View
 */
class JemViewCalendar extends JViewLegacy
{
	/**
	 * Calendar-View
	 */
	function display($tpl = null)
	{
		$app = JFactory::getApplication();

		// Load tooltips behavior
		JHtml::_('behavior.tooltip');
		JHtml::_('behavior.framework');

		//initialize variables
		$document 	= JFactory::getDocument();
		$menu 		= $app->getMenu();
		$menuitem	= $menu->getActive();
		$jemsettings = JemHelper::config();
		$params 	= $app->getParams();
		
		// Load css
		JHtml::_('stylesheet', 'com_jem/jem.css', array(), true);
		JHtml::_('stylesheet', 'com_jem/calendar.css', array(), true);
				
		$document->addCustomTag('<!--[if IE]><style type="text/css">.floattext{zoom:1;}, * html #jem dd { height: 1%; }</style><![endif]-->');

		$evlinkcolor = $params->get('eventlinkcolor');
		$evbackgroundcolor = $params->get('eventbackgroundcolor');
		$currentdaycolor = $params->get('currentdaycolor');
		$eventandmorecolor = $params->get('eventandmorecolor');

		$style = '
		div[id^=\'catz\'] a {
			color:' . $evlinkcolor . ';
		}
		div[id^=\'catz\'] {
			background-color:'.$evbackgroundcolor .';
		}
		.eventandmore {
			background-color:'.$eventandmorecolor .';
		}

		.today .daynum {
			background-color:'.$currentdaycolor.';
		}';

		$document->addStyleDeclaration($style);

		// add javascript (using full path - see issue #590)
		JHtml::_('script', 'media/com_jem/js/calendar.js');

		$year 	= (int)JRequest::getVar('yearID', strftime("%Y"));
		$month 	= (int)JRequest::getVar('monthID', strftime("%m"));

		//get data from model and set the month
		$model = $this->getModel();
		$model->setDate(mktime(0, 0, 1, $month, 1, $year));

		$rows = $this->get('Data');

		//Set Page title
		$pagetitle   = $params->def('page_title', $menuitem->title);
		$params->def('page_heading', $pagetitle);
		$pageclass_sfx = $params->get('pageclass_sfx');

		// Add site name to title if param is set
		if ($app->getCfg('sitename_pagetitles', 0) == 1) {
			$pagetitle = JText::sprintf('JPAGETITLE', $app->getCfg('sitename'), $pagetitle);
		}
		elseif ($app->getCfg('sitename_pagetitles', 0) == 2) {
			$pagetitle = JText::sprintf('JPAGETITLE', $pagetitle, $app->getCfg('sitename'));
		}

		$document->setTitle($pagetitle);
		$document->setMetaData('title', $pagetitle);

		//init calendar
		$cal = new JEMCalendar($year, $month, 0, $app->getCfg('offset'));
		$cal->enableMonthNav('index.php?view=calendar');
		$cal->setFirstWeekDay($params->get('firstweekday', 1));
		$cal->enableDayLinks(false);

		$this->rows        = $rows;
		$this->params      = $params;
		$this->jemsettings = $jemsettings;
		$this->cal         = $cal;
		$this->pageclass_sfx = htmlspecialchars($pageclass_sfx);

		parent::display($tpl);
	}
}
?>
