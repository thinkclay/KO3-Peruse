<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Model_Arrest
 *
 * @package Scrape
 * @author 	Jiran Dowlati
 * @url 	http://www.sheriff.martin.fl.us/jail.html
 */
class Model_Florida_Martin extends Model_Peruse
{
	protected $scrape 	= 'martin'; 	// name of scrape goes here
    protected $county 	= 'martin';    //	if it is a single county, put it here, otherwise remove this property
    protected $state 	= 'florida'; 	//	state goes here
    private $cookies 	= '/tmp/martin_cookies.txt';


    /**
     * Construct - For now sets a timelimit, deletes cookies if they exist and creates mscrape model in DB.
     *
     */
    public function __construct()
    {
	    /* set_time_limit(86400);         // Set a time limit. */
	    $this->cookies =  $this->cookies;
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
        $cachefile = MODPATH . 'peruse/cache/cached-'.$this->state.'-'.$this->county.'.html';
	    $cachetime = 86400; // How many seconds in a day.

        if (file_exists($cachefile) && time() - $cachetime < filemtime($cachefile)) {
            echo "<!-- Cached copy, generated ".date('H:i', filemtime($cachefile))." -->\n";
            $clean_html = file_get_contents($cachefile);
        }
        else
        {
            $index = 'http://198.136.35.4/jailinmatesearch/JailInmateSearch.asp?SelStart1=&SelStart2=&SelStart3=&SelStart4=&RunReport=Run+Report';
    	    $ref = 'http://198.136.35.4/jailinmatesearch/JailInmateSearch.asp?SelStart1=&SelStart2=&SelStart3=&SelStart4=&RunReport=Run+Report';

    	    $homepage = $this->get_site($index,$ref, 'GET', $this->cookies);
    	    $homepage = $homepage['FILE'];

    	    // Clean up html
    	    $clean_html = $this->clean_html($homepage);

    	    ob_start(); // Start the output buffer
            // Cache the contents to a file
            $cached = fopen($cachefile, 'w');
            fwrite($cached, $clean_html);
            fclose($cached);
            ob_end_flush(); // Send the output to the browser
        }

	    // Get all the links to details page
	    //$pattern = '/(<table(.*)<\/table>)/Uis';
	    $pattern = '/\<table(.+)\<hr\s\/\>/Uis';
	    $tables = $this->parse($pattern, $clean_html);
	    $tables = $tables[0];

	    foreach($tables as $table)
	    {
    	    $details = trim($table);
            $this->extraction($details);
            sleep(10);
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
    public function extraction($details)
    {
        // Parse all tables in detail page.
        $pattern = '/<table.*?<\/table\>/ism';
	    $tables = $this->parse($pattern, $details);
	    $tables = $tables[0];

	    /*
         * TOP TABLE EXTRACTION
         *
         */
	    $top_table = $tables[0];

	    // Extract all the tds of the header row to get lables
	    $pattern = '/<th.*?<\/th\>/ism';
	    $top_table_labels = $this->parse($pattern, $top_table);
	    $top_table_labels = $top_table_labels[0];

	    // Loop through lables to find position of data
	    for($i = 0; $i < count($top_table_labels); $i++)
	    {
	       if( stristr($top_table_labels[$i], "CFN") )
    	       $cfn_id = $i;
    	   if( stristr($top_table_labels[$i], "Last Name") )
    	       $lastname_id = $i;
    	   if( stristr($top_table_labels[$i], "First Name") )
    	       $firstname_id = $i;
    	   if( stristr($top_table_labels[$i], "Date Of Birth") )
    	       $dob_id = $i;
    	   if( stristr($top_table_labels[$i], "Arrest Date") )
    	       $booking_date_id = $i;
    	   if( stristr($top_table_labels[$i], "Inmate Photo") )
    	       $img_id = $i + 1;
	    }

	    // Get the TDs for the data of Top Table
	    $pattern = '/<td.*?<\/td\>/ism';
	    $top_table_data = $this->parse($pattern, $top_table);
	    $top_table_data = $top_table_data[0];

	    // First we get the booking date.
	    // Double Triming here is important to be sure all the spaces are gone.
	    $booking_date = trim(strip_tags( trim($top_table_data[$booking_date_id]) ));
	    $booking_date = strtotime($booking_date);

	    echo $booking_date . ' ';

	    // Check if its in the future which would be an error
        if ($booking_date > strtotime('midnight', strtotime("+1 day"))) {
            echo "Sorry this booking date is in the future which doesn't make sense"; exit;
        }

        $from = strtotime( date('m/d/Y', strtotime('-6 days')) ); // Looks like 1/20/2013
        $to = strtotime( date('m/d/Y', strtotime('-1 day')) );
        /* Make sure the booking date is between last week and yesterday to continue extracting and
          making images. */

        if( $booking_date >= $from || $booking_date <= $to )
        {

    		/*
             * BOTTOM TABLE EXTRACTION
             *
             */
    	    $bot_table = $tables[1];

    	    // Extract all the tds of the header row to get lables
    	    $pattern = '/<th.*?<\/th\>/ism';
    	    $bot_table_labels = $this->parse($pattern, $bot_table);
    	    $bot_table_labels = $bot_table_labels[0];

    	    // Loop through lables to find position of data
    	    for($i = 0; $i < count($bot_table_labels); $i++)
    	    {
        	   if( stristr($bot_table_labels[$i], "Charge") )
        	       $charge_id = $i;
    	    }

    	    // Get the TDs for the data of Bot Table
    	    $pattern = '/<td.*?<\/td\>/ism';
    	    $bot_table_data = $this->parse($pattern, $bot_table);
    	    $bot_table_data = $bot_table_data[0];

    	    // Set charges array
    	    $charges = array();

    	    // Extract charges
    	    for( $i = 0; $i < count($bot_table_data); $i++ )
    	    {
    	       if( $charge_id < count($bot_table_data) )
    	       {
        	       $charges[] = trim(strip_tags( trim($bot_table_data[$charge_id]) ));
        	       $charge_id = $charge_id + count($bot_table_labels);
    	       }

    	    }

    	    // Extract the other info
    	    // Double Triming here is important to be sure all the spaces are gone.


    		$booking_id   = trim(strip_tags( trim($top_table_data[$cfn_id]) ));
    		$firstname    = trim(strip_tags( trim($top_table_data[$firstname_id]) ));
    		$lastname     = trim(strip_tags( trim($top_table_data[$lastname_id]) ));
    		$dob          = trim(strip_tags( trim($top_table_data[$dob_id]) ));
    		$img          = $top_table_data[$img_id];

    		// Get the img source file
    		$pattern      = '/src="(.*?)"/ism';
    		$imagefile    = $this->parse($pattern, $img);
    		$imagefile    = trim(str_replace(' ', '%20', $imagefile[1][0]));


    		// The rest of this creates the images.

    		$imagename = date('(m-d-Y)', $booking_date).'_'.
                $lastname.'_'.
                $firstname.'_'.
                $booking_id.'.jpg';

            $imagepath = '/mugs/florida/martin/'.date('Y', $booking_date).
                '/week_'.$this->find_week($booking_date).'/';

            $new_image = $imagepath.$imagename;

            $this->set_mugpath($imagepath);

            sleep(10);

            try {
                $ch = curl_init();
                $source = $imagefile;
                curl_setopt($ch, CURLOPT_URL, $source);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $data = curl_exec ($ch);
                curl_close ($ch);

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


}