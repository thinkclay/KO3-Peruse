<?php defined('SYSPATH') or die('No direct script access.');


class Peruse_Web
{

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
        return new Peruse_Web();
    }

    /**
     * HTTP function
     *
     * Does all our awesome curl stuff
     *
     * @param  array  $options an array of options (so we can simulate named params)
     * @return array  an array containing successes and/or failure data
     */
    public function http(array $options)
    {
        $target = $options['target'];
        $referrer = $options['referrer'];
        $method = isset($options['method']) ? $options['method'] : 'GET';
        $cookie = isset($options['cookie']) ? $options['cookie'] : '/tmp/cookie';
        $data_array = isset($options['data']) ? $options['data'] : FALSE;
        $incl_head = isset($options['inc_head']) ? $options['method'] : FALSE;
        $user_agent = isset($options['browser']) ? $this->user_agent('browser') : $this->user_agent();

        $ch = curl_init();

        // Processing Data if it exists
        if ( is_array($data_array) )
        {
            foreach ( $data_array as $key => $value )
            {
                if ( count($value) > 0 )
                    $temp_string[] = $key . "=" . urlencode($value);
                else
                    $temp_string[] = $key;
            }

            // Bring in array elements into string
            $query_string = implode('&', $temp_string);
        }

        switch ($method)
        {
            case 'HEAD' :
                curl_setopt($ch, CURLOPT_HEADER, TRUE);   // No http head
                curl_setopt($ch, CURLOPT_NOBODY, TRUE);   // No body
                break;

            case 'POST' :
                if ( isset($query_string) )
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $query_string);

                curl_setopt($ch, CURLOPT_POST, TRUE);
                curl_setopt($ch, CURLOPT_HTTPGET, FALSE);
                break;

            default : // or GET
                if ( isset($query_string) )
                    $target = $target . '?' . $query_string;

                curl_setopt($ch, CURLOPT_HTTPGET, TRUE);
                curl_setopt($ch, CURLOPT_POST, FALSE);
                curl_setopt($ch, CURLOPT_HEADER, $incl_head);
                curl_setopt($ch, CURLOPT_NOBODY, FALSE);
        }

        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
        curl_setopt($ch, CURLOPT_URL, $target);
        curl_setopt($ch, CURLOPT_REFERER, $referrer);
        curl_setopt($ch, CURLOPT_VERBOSE, FALSE);           // Minimize logs
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);    // No certificate
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);     // Follow redirects
        curl_setopt($ch, CURLOPT_MAXREDIRS, 4);             // Limit redirections to four
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);     // Return in string
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );

        // Create return array
        $return_array['result'] = curl_exec($ch);
        $return_array['status'] = curl_getinfo($ch);
        $return_array['error']  = curl_error($ch);

        // Close CURL handel
        curl_close($ch);

        return $return_array;
    }

    public function user_agent($browser = NULL)
    {
        $ua_list = [
            'win_ff' => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.6) Gecko/20070725 Firefox/2.0.0.6',
            'mac_ff' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:24.0) Gecko/20100101 Firefox/24.0',
            'nix_ff' => 'Mozilla/5.0 (X11; Ubuntu; Linux armv7l; rv:17.0) Gecko/20100101 Firefox/17.0',

            'win_ie' => 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; WOW64; Trident/6.0)',

            'win_cr' => 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/28.0.1468.0 Safari/537.36',
            'win_cr' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/27.0.1453.93 Safari/537.36',
            'nix_cr' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/535.24 (KHTML, like Gecko) Chrome/19.0.1055.1 Safari/535.24',

            'win_op' => 'Opera/9.80 (Windows NT 6.0) Presto/2.12.388 Version/12.14',
            'mac_op' => 'Opera/9.80 (Macintosh; Intel Mac OS X 10.6.8; U; fr) Presto/2.9.168 Version/11.52',
            'nix_op' => 'Opera/9.80 (X11; Linux i686; U; es-ES) Presto/2.8.131 Version/11.11',

            'win_sf' => 'Mozilla/5.0 (Windows; U; Windows NT 6.1; tr-TR) AppleWebKit/533.20.25 (KHTML, like Gecko) Version/5.0.4 Safari/533.20.27',
            'mac_sf' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_6_8) AppleWebKit/537.13+ (KHTML, like Gecko) Version/5.1.7 Safari/534.57.2',

            'bot_google' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'
        ];

        if ( $browser )
            return $ua_list[$browser];

        else
            return array_rand($ua_list);
    }
}