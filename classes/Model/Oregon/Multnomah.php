<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Model_Arrest
 *
 * @package Scrape
 * @author 	Jiran Dowlati
 * @url 	http://www.sheriff.martin.fl.us/jail.html
 */
class Model_Oregon_Multnomah extends Model_Peruse
{
	protected $scrape 	= 'multnomah'; 	// name of scrape goes here
    protected $county 	= 'multnomah';    //	if it is a single county, put it here, otherwise remove this property
    protected $state 	= 'oregon'; 	//	state goes here
    private $cookies 	=  '/tmp/multnomah_cookies.txt';


    /**
     * Construct - For now sets a timelimit, deletes cookies if they exist and creates mscrape model in DB.
     *
     */
    public function __construct()
    {
	    /* set_time_limit(86400);         // Set a time limit. */
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
        // Set Login Links.
        $login = 'http://www.mcso.us/PAID/Account/Login';
        $ref = 'http://www.mcso.us/PAID/Account/Login'; 
         
        // Get the login page  
        $index = $this->get_site($login,$ref, 'GET', $this->cookies, TRUE);
        $index = $index['FILE'];
        
        // Preg match so we can get changing values.
        preg_match('/name=\"__RequestVerificationToken\".*?value\=\"(.+?)\"/ism', $index, $matches);
        // Set post fields.
        $fields = array(
            '__RequestVerificationToken' => urlencode($matches[1]),
            'UserName' => urlencode('Busted'),
            'Password' => urlencode('Summer3')
        );
        
        // Simulate login
        $login = $this->get_site($login,$ref, 'POST', $this->cookies, $fields, TRUE);
        
        // Get Booking Table
        $booking = 'http://www.mcso.us/PAID/Home/SearchResults';
        $ref = 'http://www.mcso.us/PAID/Home/SearchResults';
        // Set Search Type to 3 so we can just get the last week of Bookings
        $booking_fields = array(
            'SearchType' => 3
        );
        // Simulate Form Submission to get the bookings
        $booking = $this->get_site($booking, $ref, 'POST', $this->cookies, $booking_fields);
        $booking = $booking['FILE'];
        
        //Pick up all the trs in the tbody first
        $pattern = '/tbody.*\<tr\>(.+)<\/tr\>/ism';
        $bookings = $this->parse($pattern, $booking);
        $bookings = $bookings[0][0];
        
        // Go through again so we can have each tr as a array key.
        $pattern = '/\<tr\>(.*?)<\/tr\>/ism';
        $bookings = $this->parse($pattern, $bookings);
        $bookings = $bookings[1];
        foreach ($bookings as $offender)
        {
            // Get just the number as a booking id!
            preg_match('/href=".*ing\/(\d+)"/ism', $offender, $matches);
            $booking_id = $matches[1];
            $this->extraction($booking_id);
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
    public function extraction($booking_id)
    { 
         // Get Details Page
        $details = 'http://www.mcso.us/PAID/Home/Booking/' . $booking_id;
        $ref = 'http://www.mcso.us/PAID/Home/SearchResults';
        // Simulate Form Submission to get the bookings
        $details = $this->get_site($details, $ref, 'GET', $this->cookies);
        $details = $details['FILE'];
        
        
        // Get the labels to get where the info is going to be.
        $pattern = '/<label.*?>(.*?)<\/label>/ism';
        $labels = $this->parse($pattern, $details);
        $labels = $labels[1];
        foreach ($labels as $key => $value)
        {
            $value = strip_tags( trim($value) );
            if($value == 'Name')
                $name_id = $key;
            if($value == 'Age')
                $age_id = $key;
            if($value == 'Booking Date')
                $booking_date_id = $key;
        }
        
        // Get all the divs with values in them.
        $pattern = '/<div\sclass="col-1-3\sdisplay-value">(.*?)<\/div>/ism';
        $divs = $this->parse($pattern, $details);
        $divs = $divs[1];
        // Compare to label ids to get correct info!
        foreach ($divs as $key => $value)
        {
            $value = strip_tags( trim($value) );
            if($key == $name_id)
                $fullname = $value;
            if($key == $age_id)
                $age = $value;
            if($key == $booking_date_id)
                $booking_date = $value;
        }
        
        // Extract last and first name.
        $fullname = explode(', ', $fullname);
        $lastname = $fullname[0];
        $firstname = explode(' ', $fullname[1]);
        $firstname = $firstname[0];
        
        $booking_date = strtotime($booking_date);
        
        // Pick up the charges now. 
        $pattern = '/class="charge-description-display">(.*?)<\/span>/ism';
        $charges = $this->parse($pattern, $details);
        $charges = $charges[1];
        
        // This section now creates the images.
        
        // Source of image
        $imagefile = "http://www.mcso.us/PAID/Home/HighResolutionMugshotImage/$booking_id";
        
        $imagename = date('(m-d-Y)', $booking_date).'_'.
            $lastname.'_'.
            $firstname.'_'.
            $booking_id.'.jpg';
        
        $imagepath = '/mugs/oregon/multnomah/'.date('Y', $booking_date).
                '/week_'.$this->find_week($booking_date).'/';
       
        $new_image = $imagepath.$imagename;
            
        $this->set_mugpath($imagepath);
        
        
         try {
            // We do get site because image is behind login so we are getting binary to download
            $data = $this->get_site($imagefile, $ref, 'GET', $this->cookies);
            $data = $data['FILE'];
            
            $destination = $new_image;
            $file = fopen($destination, "w+");
            fputs($file, $data);
            fclose($file);
            sleep(10);
            $this->convert_image($new_image);
            $imagepath = str_replace('.jpg', '.png', $new_image);
            $img = Image::factory($imagepath);

            $check = $this->mugStamp(
                $imagepath,
                $firstname.' '.$lastname,
                $charges[0],
                @$charges[1]
            );
        } catch(Exception $e)
        {
            var_dump($e);
        }
        
    }
}