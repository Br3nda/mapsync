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

    private function sync_report_data($report) 
    {

	$existing = ORM::factory('mapsync')
		->where('feed_name', $report->feed_name)
		->where('asset_name', $report->attributes->Asset_Name)
		->orderby('asset_name')
		->find_all();
	if (count($existing)) {
		$this->update_existing_report($report);
        }
	else {
		$this->save_new_report($report);
	}
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
	
    }  

    function update_existing_report($report) 
    {
	print "Update new report\n";
    }

    function save_new_report($report) 
    {
	      print "Saving new report\n";
	      $title = sprintf("%s: %s %02d/%02d", strtoupper($report->feed_name), $report->attributes->Asset_Name, date('d'), date('m'));
	      print "$title<hr/>\n";
// 	      var_dump($report);

// 	      // Yes! everything is valid
// 	      $location_id = $post->location_id;
// 	      // STEP 1a: SAVE LOCATION
	      $location = new Location_Model();
// 	      $location->location_name = $post->location_name;
	      $location->latitude = $report->geometry->x;
	      $location->longitude = $report->geometry->y;
	      $location->location_date = date("Y-m-d H:i:s",time());
	      $location->save();
// 	      var_dump($location);
// 
// 	      // STEP 2: SAVE INCIDENT
	      $incident = new Incident_Model();
	      $incident->location_id = $location->id;
	      //$incident->locale = $post->locale;

// 	      $incident->user_id = $_SESSION['auth_user']->id;
	      $incident->incident_title = $title;
	      $incident->incident_description = "Auto imported";
	      $incident->incident_date = date("Y-m-d H:i:s",time());

// 	      // Is this new or edit?
// 	      if (!empty($id))        // edit
// 	      {
// 		      $incident->incident_datemodify = date("Y-m-d H:i:s",time());
// 	      }
// 	      else            // new
// 	      {
// 		      $incident->incident_dateadd = date("Y-m-d H:i:s",time());
// 	      }

	      // Incident Evaluation Info
	      $incident->incident_active = FALSE;
	      $incident->incident_verified = TRUE;
	      $incident->incident_source = 'Auto Imported';
//                                 $incident->incident_information = $post->incident_information;
//                                 $incident->incident_zoom = (int) $post->incident_zoom;
	      //Save
	      $incident->save();

	    // STEP 3: SAVE CATEGORIES
	    ORM::factory('Incident_Category')->where('incident_id',$incident->id)->delete_all();            // Delete Previous Entries

	    $categories = array(66);
	    foreach($categories as $item)
	    {
		    $incident_category = new Incident_Category_Model();
		    $incident_category->incident_id = $incident->id;
		    $incident_category->category_id = $item;
		    $incident_category->save();
	    }

	    // Action::report_edit - Edited a Report
	    Event::run('ushahidi_action.report_edit', $incident);


// 	      $query = sprintf("INSERT INTO incident (incident_title, incident_date) VALUES ('%s', %d)",
// 		      se($title),
// 		      strtotime('now'));
// 	      print "\t$query\n";
// 	      mysql_query($query);
// 
// 	      $query = sprintf("INSERT INTO incident_sync (feed_name, asset_name) VALUES ('%s', '%s')",
// 		      se($report->feed_name),
// 		      se($report->attributes->Asset_Name));
// 	      print "\t$query\n";
// 	      mysql_query($query);
      }


}
