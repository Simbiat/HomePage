<?php
declare(strict_types=1);
namespace Simbiat;

#Some functions in this class can be realized in .htaccess files, but I am implimenting the logic in PHP for more control and less dependencies on server software (not all web servers support htaccess files)
class HomePage
{
    #Static value indicating whether this is a live version of the site. It's static in case a function wil be called after initial object was destroyed
    public static bool $PROD = false;
    #Allow access to canonical value of the host
    public static string $canonical = '';
    
    public function __construct(bool $PROD = false)
    {
        #Set output compression to 9 for consistency
        ini_set('zlib.output_compression_level', '9');
        #Enforce UTC timezone
        ini_set('date.timezone', 'UTC');
        date_default_timezone_set('UTC');
        #Set path to error log
        ini_set('error_log', getcwd().'/error.log');
        #Update static value
        self::$PROD = $PROD;
        #Enable/disable display of errors
        ini_set('display_errors', strval(intval(!self::$PROD)));
        ini_set('display_startup_errors', strval(intval(!self::$PROD)));
    }
    
    public function canonical(): void
    {
        #Force HTTPS
        $this->forceSecure();
        #Force WWW
        $this->forceWWW();
        #Trim URI
        $this->trimURI();
        #Set canonical link, that may be used in the future
        self::$canonical = 'https://'.(preg_match('/^[a-z0-9\-_~]+\.[a-z0-9\-_~]+$/', $_SERVER['HTTP_HOST']) === 1 ? 'www.' : '').$_SERVER['HTTP_HOST'].($_SERVER['SERVER_PORT'] != 443 ? ':'.$_SERVER['SERVER_PORT'] : '').'/';
    }
    
    #Redirect to HTTPS
    private function forceSecure(int $port = 443): void
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
            (new \Simbiat\http20\Headers)->redirect('https://'.$_SERVER['HTTP_HOST'].($port !== 443 ? ':'.$port : '').$_SERVER['REQUEST_URI'], true, true, false);
        }
    }
    
    #Function to force www version of the website, unless on subdomain
    private function forceWWW(int $port = 443): void
    {
        if (preg_match('/^[a-z0-9\-_~]+\.[a-z0-9\-_~]+$/', $_SERVER['HTTP_HOST']) === 1) {
            #Redirect to www version
            (new \Simbiat\http20\Headers)->redirect('https://'.'www.'.$_SERVER['HTTP_HOST'].($port !== 443 ? ':'.$port : '').$_SERVER['REQUEST_URI'], true, true, false);
        }
    }
    
    #Function to trim request URI from whitespace, slashes, and then whitespaces before slashes
    private function trimURI(): void
    {
        $_SERVER['REQUEST_URI'] = rawurldecode(trim(trim(trim($_SERVER['REQUEST_URI']), '/')));
    }
    
    #Function returns version of the file based on numbe rof fiels and date of the newest file
    public function filesVersion(string $glob, bool $countfiles = false): string
    {
        #Get file list
        $filelist = glob($glob);
        #Generate the version
        return ($countfiles === true ? count($filelist).'.' : '').max(array_map('filemtime', array_filter($filelist, 'is_file')));
    }
    
    #Function to process some special files
    public function filesRequests(string $request): int
    {
        #Remove query string, if present (that is everything after ?)
        $request = preg_replace('/^(.*)(\?.*)$/', '', $request);
        if (preg_match('/browserconfig\.xml/i', $request) === 1) {
            #Process MS Tile
            (new \Simbiat\http20\Meta)->msTile($GLOBALS['siteconfig']['mstile'], [], [], true, true);
        } elseif (preg_match('/frontend\/js\/\d+\/js\.js/i', $request) === 1) {
            #Process JS
            (new \Simbiat\http20\Common)->reductor($GLOBALS['siteconfig']['jsdir'], 'js', false, '', 'aggressive');
        } elseif (preg_match('/frontend\/css\/\d+\/css\.css/i', $request) === 1) {
            #Process CSS
            (new \Simbiat\http20\Common)->reductor($GLOBALS['siteconfig']['cssdir'], 'css', true, '', 'aggressive');
        } elseif (preg_match('/frontend\/images\/fftracker\/.*/i', $request) === 1) {
            #Process FFTracker images
            #Get real path
            if (preg_match('/(frontend\/images\/fftracker\/avatar\/)(.+)/i', $request) === 1) {
                $imgpath = preg_replace('/(frontend\/images\/fftracker\/avatar\/)(.+)/i', 'https://img2.finalfantasyxiv.com/f/$2', $request);
                (new \Simbiat\http20\Sharing)->proxyFile($imgpath, 'week');
            } elseif (preg_match('/(frontend\/images\/fftracker\/icon\/)(.+)/i', $request) === 1) {
                $imgpath = preg_replace('/(frontend\/images\/fftracker\/icon\/)(.+)/i', 'https://img.finalfantasyxiv.com/lds/pc/global/images/itemicon/$2', $request);
                (new \Simbiat\http20\Sharing)->proxyFile($imgpath, 'week');
            } else {
                $imgpath = (new \Simbiat\FFTracker)->ImageShow(preg_replace('/frontend\/images\/fftracker\//i', '', $request));
                #Output the image
                (new \Simbiat\http20\Sharing)->fileEcho($imgpath);
            }
        } elseif (preg_match('/favicon\.ico/i', $request) === 1) {
            #Process favicon
            (new \Simbiat\http20\Sharing)->fileEcho($GLOBALS['siteconfig']['favicon']);
        } elseif (is_file($GLOBALS['siteconfig']['maindir'].$request)) {
            #Attempt to send the file
            if (preg_match('/^.*('.implode('|', $GLOBALS['siteconfig']['prohibited']).').*$/i', $request) === 0) {
                return (new \Simbiat\http20\Sharing)->fileEcho($GLOBALS['siteconfig']['maindir'].$request, allowedMime: $GLOBALS['siteconfig']['allowedMime'], exit: false);
            } else {
                (new \Simbiat\http20\Headers)->clientReturn('403', false);
                return 403;
            }
        }
        #We did not hit any other potential files, so return 0
        return 0;
    }
}
?>