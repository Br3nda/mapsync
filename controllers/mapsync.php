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

class Mapsync_Controller extends Controller
{
    var $feeds = array(
//       'portaloos' => 'http://s4.demos.eaglegis.co.nz/ArcGIS/rest/services/Earthquake/lifelines/MapServer/2/query?text=&geometry=&geometryType=esriGeometryPoint&inSR=&spatialRel=esriSpatialRelIntersects&relationParam=&objectIds=&where=1%3D1&time=&returnIdsOnly=false&returnGeometry=true&maxAllowableOffset=&outSR=4326&outFields=*&f=json',
      'water' => 'http://s4.demos.eaglegis.co.nz/ArcGIS/rest/services/Earthquake/lifelines/MapServer/3/query?text=&geometry=&geometryType=esriGeometryPoint&inSR=&spatialRel=esriSpatialRelIntersects&relationParam=&objectIds=&where=1%3D1&time=&returnIdsOnly=false&returnGeometry=true&maxAllowableOffset=&outSR=4326&outFields=*&f=json',
//       'fuel' => 'http://s4.demos.eaglegis.co.nz/ArcGIS/rest/services/Earthquake/lifelines/MapServer/4/query?text=&geometry=&geometryType=esriGeometryPoint&inSR=&spatialRel=esriSpatialRelIntersects&relationParam=&objectIds=&where=1%3D1&time=&returnIdsOnly=false&returnGeometry=true&maxAllowableOffset=&outSR=4326&outFields=*&f=json',

    );

    private function get_map_data($feed) 
    {
	
	$url = $this->feeds[$feed];
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
		->where('objectid', $report->attributes->OBJECTID)
		->orderby('asset_name')
		->find_all();
	if (count($existing)) {
		$report->incident_id = $existing[0]->incident_id;
		$this->update_existing_report($report);
        }
	else {
		$this->save_new_report($report);
	}
    }

    public function index()
    {
      foreach($this->feeds as $name=>$url) {
	echo "Getting $name\n";
	  $data = $this->get_map_data($name);
	  foreach($data as $report) {
// // 	    var_dump($report);
	    $report->feed_name = $name;
	    $this->sync_report_data($report);
	  }
	  $this->remove_vanished($name);
      }
      //remove those that aren't on the feed anymore
      	
    }
    function remove_vanished($feed_name) {
      $known = ORM::factory('mapsync')
		->where('feed_name', $feed_name)
		->orderby('asset_name')
		->find_all();
      foreach($known as $i) {	
	if(!in_array($i->incident_id, $this->updated)) {
	  print "This needs removing";
	}
      }
    }


    function update_existing_report($report) 
    {
	$this->updated[] = $report->incident_id;
    }

    function save_new_report($report) 
    {
	      print "Saving new report\n";
	      $title = sprintf("%s: %s %02d/%s", strtoupper($report->feed_name), $report->attributes->Asset_Name, date('d'), date('F'));
	      print "$title<hr/>\n";
// 	      var_dump($report);

// 	      // Yes! everything is valid
// 	      $location_id = $post->location_id;
// 	      // STEP 1a: SAVE LOCATION
	      $location = new Location_Model();
 	      $location->location_name = $report->attributes->Asset_Addr;
	      $location->country_id = 171;
	      $location->latitude = $report->geometry->y;
	      $location->longitude = $report->geometry->x;
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
	      $incident->incident_description = sprintf("Water is available here. %s.", $report->attributes->Asset_Addr);
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
	    
	    $sync = new Mapsync_Model();
	    $sync->objectid = $report->attributes->OBJECTID;
	    $sync->feed_name = $report->feed_name;
	    $sync->asset_name = $report->attributes->Asset_Name;
	    $sync->incident_id = $incident->id;
	    $sync->save();
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
