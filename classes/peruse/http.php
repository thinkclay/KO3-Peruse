<?php defined('SYSPATH') or die('No direct script access.');

/***********************************************************************
Webbot defaults (scope = global)                                       
----------------------------------------------------------------------*/
# Define how your webbot will appear in server logs
define("WEBBOT_NAME", "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:18.0) Gecko/20100101 Firefox/18.0");

# Length of time cURL will wait for a response (seconds)
define("CURL_TIMEOUT", 25);

# DEFINE METHOD CONSTANTS
define("HEAD", "HEAD");
define("GET",  "GET");
define("POST", "POST");

# DEFINE HEADER INCLUSION
define("EXCL_HEAD", FALSE);
define("INCL_HEAD", TRUE);

class Peruse_Http {

    /**
     * Follows factory pattern, returns Theme object in order to be able to chain functions and so on.
     *
     *  $view = Theme::view($file);
     *
     * @param   string  view filename
     * @param   array   array of values
     * @return  Theme object
     */
	public static function factory()
	{
		return new Peruse_Http();
	}
	
    /***********************************************************************
    function http_get($target, $ref)                                        
    -------------------------------------------------------------           
    	@DESCRIPTION:                                                            
            Downloads an ASCII file without the http header                 
    	
    	@INPUT:                                                                  
            $target       The target file (to download)                     
            $ref          The server referer variable                       
    
    	@OUTPUT:                                                                 
            $return_array['FILE']   = Contents of fetched file, will also include the HTTP header if requested   
            $return_array['STATUS'] = CURL generated status of transfer     
            $return_array['ERROR']  = CURL generated error status           
    ***********************************************************************/
    public function http_get($target, $ref)
    {
    	return http($target, $ref, $method="GET", $data_array="", EXCL_HEAD);
    }
    
    /*
    
    http($url, $ref, $method, $data_array, $incl_head)                      
    -------------------------------------------------------------			
     @DESCRIPTION:															
    	This function returns a web page (HTML only) for a web page through	
    	the execution of a simple HTTP GET request.							
    	All HTTP redirects are automatically followed.						
                                                                                                                          
                                                                            
     @INPUTS:																	
    	$target      Address of the target web site		 					
    	$ref		 Address of the target web site's referrer				
    	$method		 Defines request HTTP method; HEAD, GET or POST         
    	$data_array	 A keyed array, containing query string                 
    	$incl_head	 TRUE  = include http header                            
                     FALSE = don't include http header                      
                                                                            
     @RETURNS:																
        $return_array['FILE']   = Contents of fetched file, will also include the HTTP header if requested      
        $return_array['STATUS'] = CURL generated status of transfer         
        $return_array['ERROR']  = CURL generated error status               
    
     */
    
    public function http($target, $ref, $method, $cookie, $data_array, $incl_head = EXCL_HEAD)
    {
    	//Intiliaze CURL
    	$ch = curl_init();
    
    	//Processing Data if it exists
    	if( is_array($data_array) )
    	{
    		foreach( $data_array as $key => $value )
    		{
    			if( count($value) > 0)
    				$temp_string[] = $key . "=" . urlencode($value);
    			else
    				$temp_string[] = $key;
    		}
    
    		//Bring in array elements into string
    		$query_string = implode('&', $temp_string);
    	}
    
    	//HEAD Method configuration
    	if( $method == HEAD )
    	{
    		curl_setopt($ch, CURLOPT_HEADER, TRUE); 	// No http head
    		curl_setopt($ch, CURLOPT_NOBODY, TRUE); 	// No body
    	}
    	else 
    	{
    		//GET Method config
    		if($method == GET)
    		{
    			if( isset($query_string) )
    				$target = $target . '?' . $query_string;
    			curl_setopt($ch, CURLOPT_HTTPGET, TRUE);
    			curl_setopt($ch, CURLOPT_POST, FALSE);
    			
    		}
    		//POST Method config
    		if($method == POST)
    		{
    			if( isset($query_string) )
    				curl_setopt($ch, CURLOPT_POSTFIELDS, $query_string);
    			curl_setopt($ch, CURLOPT_POST, TRUE);
    			curl_setopt($ch, CURLOPT_HTTPGET, FALSE);
    		}
    
    		curl_setopt($ch, CURLOPT_HEADER, $incl_head);   // Include head as needed
          	curl_setopt($ch, CURLOPT_NOBODY, FALSE);        // Return body
    	}   
    	    
    	curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);	    // Cookie Management
    	curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
    	curl_setopt($ch, CURLOPT_TIMEOUT, CURL_TIMEOUT); 	// Timeout
    	curl_setopt($ch, CURLOPT_USERAGENT, WEBBOT_NAME);   // Webbot name
    	curl_setopt($ch, CURLOPT_URL, $target);             // Target site
    	curl_setopt($ch, CURLOPT_REFERER, 'http://facebook.com');            // Referer value
    	curl_setopt($ch, CURLOPT_VERBOSE, FALSE);           // Minimize logs
    	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);    // No certificate
    	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);     // Follow redirects
    	curl_setopt($ch, CURLOPT_MAXREDIRS, 4);             // Limit redirections to four
    	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);     // Return in string

    	# Create return array
        $return_array['FILE']   = curl_exec($ch); 
        $return_array['STATUS'] = curl_getinfo($ch);
        $return_array['ERROR']  = curl_error($ch);
    
        //Close CURL handel
        curl_close($ch);
    
        return $return_array;
    }
}

?>