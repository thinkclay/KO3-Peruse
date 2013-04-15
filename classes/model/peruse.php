<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Model_Scrape
 *
 * @author Jiran Dowlati
 * 
 **/
class Model_Peruse extends Model 
{

    /**
     * Cleaner print out of arrays. 				              
     *
     */
	public function pretty_print($val)
    {
        echo '<pre>';
        print_r($val);
        echo '</pre>';
    }
    
    /**
     * Mscrape Model  - sets up a model for the DB
     *
     * @params 
     *  $scrape      Name of the scrape		 					
     *  $state		 What state we are scraping				              
     *
     */
	public function mscrape_model($scrape, $state)
	{  
	    $mscrape = Mango::factory('mscrape', array(
	    	'name' 	=> 	$scrape,
	    	'state' => 	$state
	    ))->load();
	    
	    if( !$mscrape->loaded() )
	    {
		    $mscrape = Mango::factory('mscrape', array(
	    		'name' 	=> 	$scrape,
	    		'state' => 	$state
	    	))->create();
	    }
	}
	
	// attempt to load the offender by booking_id
	public function load_offender($id)
	{
        $offender = Mango::factory('offender', array(
            'booking_id' => $id
        ))->load();
        // if they are not loaded then continue with extraction, otherwise skip this offender
        if ($offender->loaded()) {
            echo "Sorry this offender already exists"; exit;
        }	
	}
        
	
	/**
     * Get Site - Retrieves the specific site 
     *
     * @params 
     *  $target      Address of the target web site		 					
     *  $ref		 Address of the target web site's referrer				
     *  $method		 Defines request HTTP method; HEAD, GET or POST         
     *  $data_array	 A keyed array, containing query string                 
     *
     *
     * @returns 
     *  $return_array['FILE']   = Contents of fetched file, will also include the HTTP header if requested   
     *  $return_array['STATUS'] = CURL generated status of transfer     
     *  $return_array['ERROR']  = CURL generated error status  
     */
	public function get_site($target, $ref, $method, $cookie, $data_array = null)
	{
    	return Peruse_Http::factory()->http($target,$ref, $method, $cookie, $data_array);
	}
	
	/**
     * Clean HTML  - Uses Tidy to clean up the html from site we are scraping. A lot of sites have a lot of invalid html.
     *  Makes for an easier scrape when tidying it all up.
     *
     * @params 
     *  $html      The site we are cleaning up. 			              
     *
     * @returns
     *  $tidy      The cleaned up html
     *
     */
	public function clean_html($html)
	{
	   // Specify configuration
        $config = array(
            'indent' => true,
            'output-xhtml' => true,
            'wrap' => 200
        );
        
    	// Tidy
        $tidy = new tidy;
        $tidy->parseString($html, $config, 'utf8');
        $tidy->cleanRepair();
        
        return $tidy;
	}
	
	// true to remove extra white space
	public function clean_string_utf8($string_to_clean, $bool = false)
	{
		if (!is_string($string_to_clean))
			return false;
		$clean_string = strtoupper(trim(preg_replace('/[\x7f-\xff]/', '', $string_to_clean)));
		$clean_string = str_replace('"', '', $clean_string);
		if ($bool == true)
			$clean_string = preg_replace('/\s\s+/', ' ', $clean_string); // replace all extra spaces
		return htmlspecialchars_decode(trim($clean_string), ENT_QUOTES);
	}
	
	public function parse($pattern, $site)
	{
    	preg_match_all($pattern, $site, $matches);
    	if( !empty($matches[0]) || !empty($matches[1]) )
    	{
        	return $matches;
    	}
    	else
    	{
        	echo "Sorry it looks like your regex pattern might be off"; exit;
    	}
        
	}
	  
}