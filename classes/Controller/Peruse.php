<?php defined('SYSPATH') or die('No direct script access.');

/**
 * New Scrape - Main module for webscraping mugshot images and data
 *
 * @TODO
 * @package default
 * @author 	Jiran Dowlati
 */
class Controller_Peruse extends Controller
{
	public function action_index()
	{
		echo "Index Action of Controller called";
	}

	/**
     * Scrape is what calls the specific model to scrape.
     *
     */
    public function action_scrape()
    {
        $state  = $this->request->param('id');
        $county = $this->request->param('id2');
    	
        $county = ucwords($county);
    	$state = ucwords($state);
    	
    	$class = 'Model_' . $state . '_' . $county;
    	
    	$scrape = new $class;

    	$scrape->scrape();


		## check for any duplicate booking_ids and delete them
		//$county->bid_dupe_check($scrape);
		## check for duplicate firstname, lastname, and booking_id and flag them
		//$county->profile_dupe_check($scrape);
    }
}
