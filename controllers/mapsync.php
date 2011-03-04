<?php defined('SYSPATH') or die('No direct script access.');
/**
 * KML Controller
 * Generates KML with PlaceMarkers and Category Styles
 *
 * PHP version 5
 * LICENSE: This source file is subject to LGPL license
 * that is available through the world-wide-web at the following URI:
 * http://www.gnu.org/copyleft/lesser.html
 * @author		 Ushahidi Team <team@ushahidi.com>
 * @package		Ushahidi - http://source.ushahididev.com
 * @module		 Feed Controller
 * @copyright	Ushahidi - http://www.ushahidi.com
 * @license		http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License (LGPL)
*
*/

/*<?php
chdir ('../../');
require('system/core/Bootstrap.php');
exit;

$conn = mysql_connect('localhost', 'root', 'root');
mysql_query("DELETE FROM incident_sync");
mysql_select_db('ccnz');


mysql_close();




*/
class Mapsync_Controller extends Controller
{
    private function get_map_data() 
    {
	//water
	$url = 'http://s4.demos.eaglegis.co.nz/ArcGIS/rest/services/Earthquake/lifelines/MapServer/3/query?text=&geometry=&geometryType=esriGeometryPoint&inSR=&spatialRel=esriSpatialRelIntersects&relationParam=&objectIds=&where=1%3D1&time=&returnIdsOnly=false&returnGeometry=true&maxAllowableOffset=&outSR=4326&outFields=&f=json';
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$response = curl_exec($ch);
	$decoded = json_decode($response);
	return $decoded->features;
    }

    private function sync_report_data($report) {
	var_dump($report);

	$existing = ORM::factory('mapsync')
		->where('feed_name', $report->feed_name)
		->where('asset_name', $report->attributes->Asset_Name)
		->orderby('asset_name')
		->find_all();
	var_dump($existing);
	/*
        if(mysql_num_rows($result)) {
		update_existing_report($report);
        }
	else {
		save_new_report($report);
	}
*/
    }
    public function index()
    {
      $data = $this->get_map_data();
      $i=0;
      foreach($data as $report) {
	$i++;
	$report->feed_name = 'water';
	$this->sync_report_data($report);
    }  
/*
		// How Many Items Should We Retrieve?
		if (isset($_GET['l']) AND !empty($_GET['l']))
		{
			$limit = (int) $_GET['l'];
		}
		else
		{
			$limit = 100;
		}

		// Has a category been specified?
		if (isset($_GET['category_id']) && is_numeric($_GET['category_id']))
		{
			$category_id = (int) $_GET['category_id'];
		}
		else
		{
			$category_id = NULL;
		}

		if (!is_null($category_id))
		{
			$categories = ORM::factory('category')
				->where('category_visible', '1')
				->where('id', $category_id)
				->find_all();

			$incidents = ORM::factory('incident')
				->join('incident_category', 'incident_category.incident_id', 'incident.id', 'INNER')
				->where('incident_category.category_id', $category_id)
				->where('incident_active', '1')
				->orderby('incident_date', 'desc')
				->limit($limit)
				->find_all();
		}
		else
		{
			$categories = ORM::factory('category')
				->where('category_visible', '1')
				->find_all();

			$incidents = ORM::factory('incident')
				->where('incident_active', '1')
				->orderby('incident_date', 'desc')
				->limit($limit)
				->find_all();
		}

		header("Content-Type: application/vnd.google-earth.kml+xml");
		header("Content-Disposition: attachment; filename=".time().".kml");
		header("Last-Modified: ".gmdate("D, d M Y H:i:s")." GMT");
		header("Cache-Control: cache, must-revalidate");
		header("Pragma: public");

		$view = new View("kml");
		$view->kml_name = htmlspecialchars(Kohana::config('settings.site_name'));
		$view->items = $incidents;
		$view->categories = $categories;
		$view->render(TRUE);
*/
	}



function update_existing_report($report) {
}

function save_new_report($report) {
	print "Saving new report\n";
	$title = sprintf("%s: %s %02d/%02d", strtoupper($report->feed_name), $report->attributes->Asset_Name, date('d'), date('m'));
	print "$title\n";
	$query = sprintf("INSERT INTO incident (incident_title, incident_date) VALUES ('%s', %d)",
		se($title),
		strtotime('now'));
	print "\t$query\n";
	mysql_query($query);

	$query = sprintf("INSERT INTO incident_sync (feed_name, asset_name) VALUES ('%s', '%s')",
		se($report->feed_name),
		se($report->attributes->Asset_Name));
	print "\t$query\n";
	mysql_query($query);
}


}
