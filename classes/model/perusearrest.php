<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Model_Arrest
 *
 * @package Scrape
 * @author 	Jiran Dowlati
 * @url 	http://arre.st
 */
class Model_Perusearrest extends Model_Peruse
{
	protected $scrape 	= 'perusearrest'; 	// name of scrape goes here
    protected $county 	= 'perusearrest';    //	if it is a single county, put it here, otherwise remove this property
    protected $state 	= 'perusearrest'; 	//	state goes here
    private $cookies 	=  null;
   
    
    /**
     * Construct - For now sets a timelimit, deletes cookies if they exist and creates mscrape model in DB.
     *
     */
    public function __construct()
    {
	    set_time_limit(86400);         // Set a time limit. 
	    $this->cookies =  APPPATH . 'cache/cookies/' . $this->scrape . '.txt'; 			
	    if( file_exists($this->cookies))
	    {
		    unlink($this->cookies); 	// Unlink only works for files, use rmdir for Directories. 
	    }
	    
	    // Creates a mscrape model in Mongo DB.
	    $this->mscrape_model($this->scrape, $this->state);
    }
    
    /**
     * scrape - main scrape function makes the curl calls and sends details to the extraction function
     *
     * @return true - on completed scrape
     * @return false - on failed scrape
     */
    public function scrape()
    {
	    $index = 'http://arre.st';
	    $ref = 'http://arre.st';
	    
	    $homepage = $this->get_site($index,$ref, 'GET', $this->cookies); 
	    $homepage = $homepage['FILE'];
	    
	    // Clean up html
	    $clean_html = $this->clean_html($homepage);
	    
	    // Get all the links to details page 
	    $pattern = '/http:\/\/arre.st.*.html/i';
	    $links = $this->parse($pattern, $clean_html);
	    $links = $links[0];
	    
	    for($i = 0; $i < 3; $i++)
	    {
    	    $details = $links[$i];
    	    $page = $this->get_site($details, $ref, 'POST', $this->cookies);
    	    
    	    // Split url for us to get the state and county to pass. 
    	    $explode    = explode('/', $details);
            $state      = strtolower($explode[4]);
            //Cleans up all those spaces and other nonesense for county before setting it. 
            $county     = preg_replace('/\./', '', preg_replace('/\s/', '_', urldecode(strtolower($explode[5]))));
            
            $page = $page['FILE'];
            $this->extraction($page, $state, $county);
	    }
	    
	    
    }
    
    /**
     * extraction - Validates and extracts all data
     *
     * 
     * @params 
     *  $page  - Offenders details page
     *  $state - Offender's state
     *  $county - Offender's county
     *
     * @returns
     * 
     */
    public function extraction($page, $state, $county)
    {
        // Finds the first td that has name and image
        $pattern    =   '/\<td\salign\=center\>(.*)\<\/td\>/Uis';
        $center     =   $this->parse($pattern, $page);
        $center     =   $center[0][0];
        
        // Split each center item and build array of the center tags
        $center_array = preg_split("/\<\/center\>/", $center);
        
        // First piece of array gives us full name.
        $fullname   =   $this->clean_string_utf8(strip_tags(trim($center_array[0])));
        $fullname   =   explode(" ", $fullname);
        
        // Get First and Last name 
        $lastname   =   $this->clean_string_utf8($fullname[2]);
        $firstname  =   $this->clean_string_utf8($fullname[0]);
        
        // Pick up Image File from src. 
        $pattern    =   '/src\=\"(.*?)\"/';
        $imagefile  =   $this->parse($pattern, $center_array[1]);    
        $imagefile  =   str_replace(' ', '%20', $imagefile[1][0]);        
        
        //Finds the second td that has all the other info
        $pattern    =   '/\<td\svalign\=top\>(.*)\<\/td\>/Uis';
        $top        =   $this->parse($pattern, $page);
        $top        =   $top[0][0]; 
        
        // Split each b item and build array of b tags.
        $pattern    =   '/\<\/b\>(.*)\<b\>/Uis';
        $b_array    =   $this->parse($pattern, $top);
        $b_array    =   $b_array[0];
        
        //Get Age and Booking Date
        $age        =   strip_tags(trim($b_array[2]));
        if($age == ' '){ $age = 0; }
        
        $booking_date = strip_tags(trim($b_array[3]));
        $booking_date = preg_replace('/-/', '/', $booking_date);
        $booking_date = strtotime($booking_date);
        // Check if its in the future which would be an error
        if ($booking_date > strtotime('midnight', strtotime("+1 day"))) {
            echo "Sorry this booking date is in the future which doesn't make sense"; exit;
        }     
        
        //Just extract the number for Booking ID
        $pattern    =   '/\d+/';
        $booking_id =   $this->parse($pattern, strip_tags(trim($b_array[4])));
        $booking_id =   $booking_id[0][0];
        
        // Attempt to load offender
        $this->load_offender($booking_id);
        
        // Set the Charges
        $charges = array();
        // Look for i tag to find Charge
        $pattern    =   '/\<i\>(.*)\<\/i\>/Uis';
        $charges    =   $this->parse($pattern, $top);
        $charges    =   $charges[0];
        // Clean up the charges. 
        for($i = 0; $i < count($charges); $i++)
        {
            $charges[$i] = strip_tags(trim($charges[$i]));
        }
        

        // Download the image. 
        $filename = MODPATH . "peruse/images/$firstname.jpg";
        $ch = curl_init($imagefile);
        $fp = fopen($filename, 'wb');
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);
        
        exit;
        
    }
    
    
}