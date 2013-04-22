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

    /**
     * Extract Raw Data
     *
     * This function takes a source directory and scans it for zip files and extracts the contents
     * to the destination directory
     *
     * @param string $source_dir  the full path to the source directory that can be scanned for zip files
     * @param string $destination_dir  the full path to the destination directory where the contents should be extracted
     *
     * @return array a count of success or failed extractions
     */
    public static function extract_raw_data($source_dir, $destination_dir)
    {
        if ( ! file_exists($source_dir) )
            throw new Peruse_Exception(
                $this->errors."the source directory {$source_dir} does not exist",
                "severe"
            );

        if ( ! file_exists($destination_dir) )
            throw new Peruse_Exception(
                $this->errors."the source directory {$destination_dir} does not exist",
                "severe"
            );

        $files = glob($source_dir.'/*.zip');
        $zip = new ZipArchive;
        $success = $failed = 0;

        foreach ( $files as $file )
        {
            if ( $zip->open($file) )
                $success++;
            else
                $failed++;

            $zip->extractTo($destination_dir);
        }

        return ['success' => $success, 'failed' => $failed];
    }

    public function set_mugpath($imagepath)
    {
        $imagepath = strtolower($imagepath);
        $check = preg_match('/\/mugs\/.*\//Uis', $imagepath, $match);

        if ( ! $check )
            return false;

        $statepath = $match[0];
        if (!is_dir($statepath))
        {
            $oldumask = umask(0);
            mkdir($statepath, 0777);
            umask($oldumask);
        }
        $check = preg_match('/\/mugs\/.*\/.*\//Uis', $imagepath, $match);
        if ( ! $check)
        {
            //return false;
        }
        $countypath = $match[0];
        if (!is_dir($countypath))
        {
            $oldumask = umask(0);
            mkdir($countypath, 0777);
            umask($oldumask);
        }
        $yearpath = preg_replace('/\/week.*/', '', $imagepath);
        # check if year path exists
        if (!is_dir($yearpath))
        {
            # create mugpath if it doesn't exist
            $oldumask = umask(0);
            mkdir($yearpath, 0777);
            umask($oldumask);
        }
        # check if image path exists
        if (!is_dir($imagepath))
        {
            # create imagepath if it doesn't exist
            $oldumask = umask(0);
            mkdir($imagepath, 0777);
            umask($oldumask);
        }
        return $imagepath;
    }

    public function find_week($timestamp)
    {
        $week = date('W', $timestamp) + 1;

        return $week;
    }

    /**
     * convertImage - converts any image to a PNG
     *
     * Lets see if we can find another built in PHP function to replace this
     */
    public function convert_image($image)
    {
        // check for valid image
        $check = getimagesize($image);
        if ($check === false)
        {
            return false;
        }
        $info = @GetImageSize($image);
        $mime = $info['mime'];
        // What sort of image?
        $type = substr(strrchr($mime, '/'), 1);
        switch ($type)
        {
            case 'jpeg':
                $image_s = imagecreatefromjpeg($image);
                break;
            case 'png':
                $image_s = imagecreatefrompng($image);
                break;
            case 'bmp':
                $image_s = imagecreatefromwbmp($image);
                break;
            case 'gif':
                $image_s = imagecreatefromgif($image);
                break;
            case 'xbm':
                $image_s = imagecreatefromxbm($image);
                break;
            default:
                $image_s = imagecreatefromjpeg($image);
        }
        # ok so now I have $image_s set as the sourceImage and open as
        # now change the image extension
        $ext = '.png';
        $replace = preg_replace('/\.[a-zA-Z]*/', $ext, $image);
        # save the image with the same name but new extension
        $pngimg = imagepng($image_s, $replace);
        # if successful delete orginal source image
        if ($pngimg)
        {
            chmod($replace, 0777);
            //chown($replace, 'mugs');
            @unlink($image);
            return $pngimg;
        }
        else
        {
            return false;
        }
    }


    /**
    * mugStamp - Takes an image and adds space at the bottom for name and charges
    *
    * @todo
    * @return
    * @author Winter King
    */
    public function mugStamp($imgpath, $fullname, $charge1, $charge2 = null)
    {
        $max_width = 380;
        $font = DOCROOT.'public/includes/arial.ttf';
        $font_18_dims = imagettfbbox( 18 , 0 , $font , $charge1);
        $font_18_charge_width = $font_18_dims[2] - $font_18_dims[0];
        $font_12_dims = imagettfbbox( 12 , 0 , $font , $charge1);
        $font_12_charge_width = $font_12_dims[2] - $font_12_dims[0];
        $cropped = false;
        if($font_12_charge_width > $max_width)
        {
            unset($charge2);
            $cropped_charge = $this->charge_cropper($charge1, $max_width);
            if ($cropped_charge === false)
            {
                return false;
            }
            $cropped = true;
            $charge1 = $cropped_charge[0];
            $charge2 = @$cropped_charge[1];
        }
        if (isset($charge2))
        {
            $font = DOCROOT.'public/includes/arial.ttf';
            $font_18_dims = imagettfbbox( 18 , 0 , $font , $charge2);
            $font_18_charge_width = $font_18_dims[2] - $font_18_dims[0];
            $font_12_dims = imagettfbbox( 12 , 0 , $font , $charge2);
            $font_12_charge_width = $font_12_dims[2] - $font_12_dims[0];
            if($font_12_charge_width > $max_width)
            {
                unset($charge2);
            }
        }
        if (isset($charge1))
        {
            $font = DOCROOT.'public/includes/arial.ttf';
            $font_18_dims = imagettfbbox( 18 , 0 , $font , $charge1);
            $font_18_charge_width = $font_18_dims[2] - $font_18_dims[0];
            $font_12_dims = imagettfbbox( 12 , 0 , $font , $charge1);
            $font_12_charge_width = $font_12_dims[2] - $font_12_dims[0];
            if($font_12_charge_width > ($max_width * 2) )
            {
                return false;
            }
        }
        # todo: check to make sure the $imgpath is an image, if not then return string 'not an image'
        $charge1 = trim($charge1);
        //header('Content-Type: image/png');
        //$imgpath = DOCROOT.'public/images/scrape/ohio/summit/test.png';
        # resize image to 400x480 and save it
        $image = Image::factory($imgpath);
        $image->resize(400, 480, Image::NONE)->save();
        # open original image with GD
        // check for valid image
        $check = getimagesize($imgpath);
        if ($check === false)
        {
            return false;
        }
        $orig = imagecreatefrompng($imgpath);
        # create a blank 400x600 canvas
        $canvas = imagecreatetruecolor(400, 600);
        # allocate white
        $white = imagecolorallocate($canvas, 255, 255, 255);
        # draw a filled rectangle on it
        imagefilledrectangle($canvas, 0, 0, 400, 600, $white);
        # copy original onto white painted canvas
        imagecopy($canvas, $orig, 0, 0, 0, 0, 400, 480);

        # start text stamp
        # create a new text canvas box @ 400x120
        $txtCanvas = imagecreatetruecolor(400, 120);
        # allocate white
        $white = imagecolorallocate($txtCanvas, 255, 255, 255);
        # draw a filled rectangle on it
        imagefilledrectangle($txtCanvas, 0, 0, 400, 120, $white);
        # set font file
        $font = DOCROOT.'public/includes/arial.ttf';

        # fullname
        # find dimentions of the text box for fullname

        $dims = imagettfbbox(18 , 0 , $font , $fullname );
        # set width
        $width = $dims[2] - $dims[0];
        # check to see if the name fits
        if ($width < 390)
        {
            $fontsize = 18;
            # find center
            $center = ceil((400 - $width)/2);
            # write text
            imagettftext($txtCanvas, $fontsize, 0, $center, 35, 5, $font, $fullname);
        }
        # if it doesn't fit cut it down to size 12
        else
        {
            $fontsize = 12;
            $dims = imagettfbbox(12 , 0 , $font , $fullname );
            # set width
            $width = $dims[2] - $dims[0];
            # find center
            $center = ceil((400 - $width)/2);
            # write text
            imagettftext($txtCanvas, $fontsize, 0, $center, 35, 5, $font, $fullname);
        }
        //@todo: make a check for text that is too long for the box and cut out middle name if so

        # charge1
        # find dimentions of the text box for charge1
        $dims = imagettfbbox(18 , 0 , $font , $charge1 );
        # set width
        $width = $dims[2] - $dims[0];
        # check to see if charge1 description fits
        if ($width < 390)
        {
            $cfont = 18;
            # find center
            $center = ceil((400 - $width)/2);
            # write text
            imagettftext($txtCanvas, $cfont, 0, $center, 65, 5, $font, $charge1);
        }
        # if it doesn't fit cut it down to size 12
        else
        {
            $cfont = 12;
            $dims = imagettfbbox(12 , 0 , $font , $charge1 );
            # set width
            $width = $dims[2] - $dims[0];
            # find center
            $center = ceil((400 - $width)/2);
            # write text
            imagettftext($txtCanvas, $cfont, 0, $center, 65, 5, $font, $charge1);
        }

        # check for a 2nd charge
        if (isset($charge2))
        {
            if ($cropped === true)
            {
                $dims = imagettfbbox($cfont , 0 , $font , $charge2 );
                # set width
                $width = $dims[2] - $dims[0];
                # find center
                $center = ceil((400 - $width)/2);
                # write text
                imagettftext($txtCanvas, $cfont, 0, $center, 95, 5, $font, $charge2);
            }
            else
            {
                # charge2
                # find dimentions of the text box for charge2
                $dims = imagettfbbox(18 , 0 , $font , $charge2 );
                # set width
                $width = $dims[2] - $dims[0];
                # check to see if charge1 description fits
                if ($width < 390 && $cfont == 18)
                {
                    # find center
                    $center = ceil((400 - $width)/2);
                    # write text
                    imagettftext($txtCanvas, $cfont, 0, $center, 95, 5, $font, $charge2);
                }
                # if it doesn't fit cut it down to size 12
                else
                {
                    $cfont = 12;
                    $dims = imagettfbbox(12 , 0 , $font , $charge2 );
                    # set width
                    $width = $dims[2] - $dims[0];
                    # find center
                    $center = ceil((400 - $width)/2);
                    # write text
                    imagettftext($txtCanvas, $cfont, 0, $center, 95, 5, $font, $charge2);
                }
            }
        }
        #doesn't exist for some reason
        //imageantialias($txtCanvas);
        # copy text canvas onto the image
        imagecopy($canvas, $txtCanvas, 0, 480, 0, 0, 400, 120);
        $imgName = $fullname . ' ' . date('(m-d-Y)');
        $mugStamp = $imgpath;
        # save file
        $check = imagepng($canvas, $mugStamp);
        chmod($mugStamp, 0777); //not working for some reason
        if ($check) {return true;} else {return false;}
    }

}