<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Model_Arrest
 *
 * @package Scrape
 * @author 	Jiran Dowlati
 * @url 	http://arre.st
 */
class Model_Missouri_Kansas extends Model_Peruse
{
	protected $scrape 	= 'kcpd'; 	// name of scrape goes here
    protected $county 	= 'kansas';    //	if it is a single county, put it here, otherwise remove this property
    protected $state 	= 'missouri'; 	//	state goes here
    private $cookies 	= null;

    private $raw_dir    = '/Volumes/Data/Projects/PHP/MSJ/data/raw/kcpd/Tue';
    private $tmp_dir    = '/Volumes/Data/Projects/PHP/MSJ/data/tmp';
    private $final_dir  = '/Volumes/Data/Projects/PHP/MSJ/data/final';
    private $errors     = '';

    public function __construct()
    {
        $this->errors = "Error with scrape: ".$this->scrape."\r\n".
            " for state: ".$this->state."\r\n".
            " and county: ".$this->county."\r\n".
            " with message: ";

        $params = array(
            'name' => $this->scrape,
            'state' => $this->state,
            'county' => $this->county
        );

        // create mscrape model if one doesn't already exist
        $scrape = Mango::factory('peruse_scrape', $params)->load();

        // If this is our first time runnig the scrape, lets create it with
        // our base information, and contact info of the county managers so we can look them up quickly
        if ( ! $scrape->loaded() )
        {
            $scrape = Mango::factory('peruse_scrape', $params);
            $scrape->contacts = array(
                array(
                    'name'          => 'Mike Grigsby',
                    'role'          => 'Manager at Information Technology Unit',
                    'address'       => 'Kanas City Police Department',
                    'phone'         => '(816) 413-3616',
                    'email'         => 'mike.grigsby@kcpd.org',
                    'coordinator'   => 'Clay McIlrath'
                ),
                array(
                    'name'          => 'Caleb Lewis',
                    'email'         => 'caleb.lewis@ago.mo.gov',
                ),
                array(
                    'name'          => 'Virginia S Murray',
                    'email'         => 'virginia.murray@kcpd.org'
                )
            );
            $scrape->create();
        }
    }

    /**
     * scrape - main scrape function makes the curl calls and sends details to the extraction function
     *
     * @return true - on completed scrape
     * @return false - on failed scrape
     */
    public function scrape()
    {
        $extracted_files = Model_Peruse::extract_raw_data($this->raw_dir, $this->tmp_dir);

        if ( $extracted_files['failed'] )
            throw new Peruse_Exception(
                $this->errors."failed extracting ".$extracted_files['failed'],
                "moderate"
            );

        $text_file = file_get_contents($this->raw_dir.'/kcpdslammer.txt');
        $offenders = preg_split('/$\R?^/m', $text_file); // create a new array out of each line break
        $offenders_merged = [];

        foreach ( $offenders as $offender )
        {
            /*
              0 => string 'BAKER,MAURICE D' (length=15)
              1 => string '4/14/2013' (length=9)
              2 => string '0132' (length=4)
              3 => string 'OPER MOTOR VEH WHILE' (length=20)
              4 => string 'H5217024.jpg
             */
            $offender_data = explode('|', $offender);

            if ( $key = trim(preg_replace('/\.jpg/i', '', $offender_data[4])) )
            {
                $name = explode(',', $offender_data[0]);

                $offenders_merged[$key]['scrape'] = $this->scrape;
                $offenders_merged[$key]['scrape_time'] = time();
                $offenders_merged[$key]['state'] = $this->state;
                $offenders_merged[$key]['county'] = $this->county;
                $offenders_merged[$key]['img_src'] = $this->tmp_dir.'/'.$key.'.jpg';

                $offenders_merged[$key]['booking_id'] = $this->county.'_'.$key;
                $offenders_merged[$key]['firstname'] = explode(' ', $name[1])[0];
                $offenders_merged[$key]['lastname'] = $name[0];
                $date = $offenders_merged[$key]['booking_date'] = strtotime($offender_data[1]);
                $offenders_merged[$key]['charges'][] = $offender_data[3];
            }
        }

        $this->_process_offenders($offenders_merged);
    }

    private function _process_offenders($offenders)
    {
        foreach ( $offenders as $offender )
        {
            $imagename = date('(m-d-Y)', $offender['booking_date']).'_'.
                $offender['lastname'].'_'.
                $offender['firstname'].'_'.
                $offender['booking_id'].'.jpg';

            $imagepath = '/mugs/missouri/kansas/'.date('Y', $offender['booking_date']).
                '/week_'.$this->find_week($offender['booking_date']).'/';

            $new_image = $imagepath.$imagename;

            $this->set_mugpath($imagepath);

            if ( file_exists($offender['img_src']) )
            {
                copy($offender['img_src'], $new_image);
                $this->convert_image($new_image);
                $imagepath = str_replace('.jpg', '.png', $new_image);
                $img = Image::factory($imagepath);

                $check = $this->mugStamp(
                    $imagepath,
                    $offender['firstname'].' '.$offender['lastname'],
                    $offender['charges'][0],
                    @$offender['charges'][1]
                );
            }
        }


        // # database validation
        // $offender = Mango::factory('offender', array('booking_id' => $booking_id))->load();

        // if ( $offender->loaded() )
        //     return;


    }





}