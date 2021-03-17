<?php
declare(strict_types=1);
namespace Simbiat;

class HomePage
{
    public function __construct(bool $displayErrors = false)
    {
        #Set output compression to 9 for consistency
        ini_set('zlib.output_compression_level', '9');
        #Enforce UTC timezone
        ini_set('date.timezone', 'UTC');
        date_default_timezone_set('UTC');
        #Enable/disable display of errors
        ini_set('display_errors', strval(intval($displayErrors)));
        ini_set('display_startup_errors', strval(intval($displayErrors)));
        #Set path to error log
        ini_set('error_log', getcwd().'/error.log');
        #Force HTTPS
        $this->forceSecure();
        #Trim URI
        $this->trimURI();
    }
    
    #Redirect to HTTPS
    private function forceSecure(int $port = 433): void
    {
        if (
                #If HTTPS is not set or is set as 'off' - assume HTTP protocol
                (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') &&
                #If the above is true, it does not mean we are on HTTP, because there can be a special rever proxy/balancer case. Thus we check X-FORWARDED-* headers
                (empty($_SERVER['HTTP_X_FORWARDED_PROTO']) || $_SERVER['HTTP_X_FORWARDED_PROTO'] !== 'https') &&
                (empty($_SERVER['HTTP_X_FORWARDED_SSL']) || $_SERVER['HTTP_X_FORWARDED_SSL'] === 'off') &&
                #This one is for Microsoft balancers and apps
                (empty($_SERVER['HTTP_FRONT_END_HTTPS']) || $_SERVER['HTTP_FRONT_END_HTTPS'] === 'off')
            ) {
            #Redirect to HTTPS, while keeping the port, in case it's not standard
            (new \Simbiat\http20\Headers)->redirect('https://'.$_SERVER['HTTP_HOST'].($port !== 433 ? ':'.$port : '').$_SERVER['REQUEST_URI'], true, true, false);
        }
    }
    
    #Function to trim request URI from whitespace, slashes, and then whitespaces before slashes
    private function trimURI(): void
    {
        $_SERVER['REQUEST_URI'] = trim(trim(trim($_SERVER['REQUEST_URI']), '/'));
    }
}
?>