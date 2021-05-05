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
    #Track if DB connection is up
    public static bool $dbup = false;
    #HTMLCache object
    public static ?\Simbiat\HTMLCache $HTMLCache = NULL;
    #HTTP headers object
    public static ?\Simbiat\http20\Headers $headers = NULL;
    
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
        #Cache headers object
        self::$headers = new \Simbiat\http20\Headers;
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
            self::$headers->redirect('https://'.$_SERVER['HTTP_HOST'].($port !== 443 ? ':'.$port : '').$_SERVER['REQUEST_URI'], true, true, false);
        }
    }
    
    #Function to force www version of the website, unless on subdomain
    private function forceWWW(int $port = 443): void
    {
        if (preg_match('/^[a-z0-9\-_~]+\.[a-z0-9\-_~]+$/', $_SERVER['HTTP_HOST']) === 1) {
            #Redirect to www version
            self::$headers->redirect('https://'.'www.'.$_SERVER['HTTP_HOST'].($port != 443 ? ':'.$port : '').$_SERVER['REQUEST_URI'], true, true, false);
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
        $request = preg_replace('/^(.*)(\?.*)?$/', '$1', $request);
        if (preg_match('/^browserconfig\.xml$/i', $request) === 1) {
            #Process MS Tile
            (new \Simbiat\http20\Meta)->msTile($GLOBALS['siteconfig']['mstile'], [], [], true, true);
        } elseif (preg_match('/^frontend\/js\/\d+\.js$/i', $request) === 1) {
            #Process JS
            (new \Simbiat\http20\Common)->reductor($GLOBALS['siteconfig']['jsdir'], 'js', false, '', 'aggressive');
        } elseif (preg_match('/^frontend\/css\/\d+\.css$/i', $request) === 1) {
            #Process CSS
            (new \Simbiat\http20\Common)->reductor($GLOBALS['siteconfig']['cssdir'], 'css', true, '', 'aggressive');
        } elseif (preg_match('/^frontend\/images\/fftracker\/.*$/i', $request) === 1) {
            #Process FFTracker images
            #Get real path
            if (preg_match('/^(frontend\/images\/fftracker\/avatar\/)(.+)$/i', $request) === 1) {
                $imgpath = preg_replace('/^(frontend\/images\/fftracker\/avatar\/)(.+)/i', 'https://img2.finalfantasyxiv.com/f/$2', $request);
                (new \Simbiat\http20\Sharing)->proxyFile($imgpath, 'week');
            } elseif (preg_match('/^(frontend\/images\/fftracker\/icon\/)(.+)$/i', $request) === 1) {
                $imgpath = preg_replace('/^(frontend\/images\/fftracker\/icon\/)(.+)/i', 'https://img.finalfantasyxiv.com/lds/pc/global/images/itemicon/$2', $request);
                (new \Simbiat\http20\Sharing)->proxyFile($imgpath, 'week');
            } else {
                $imgpath = (new \Simbiat\FFTracker)->ImageShow(preg_replace('/^frontend\/images\/fftracker\//i', '', $request));
                #Output the image
                (new \Simbiat\http20\Sharing)->fileEcho($imgpath);
            }
        } elseif (preg_match('/^(favicon\.ico)|(frontend\/images\/favicons\/favicon\.ico)$/i', $request) === 1) {
            #Process favicon
            (new \Simbiat\http20\Sharing)->fileEcho($GLOBALS['siteconfig']['favicon']);
        } elseif (preg_match('/^(bic)($|\/.*)/i', $request) === 1) {
            self::$headers->redirect('https://'.$_SERVER['HTTP_HOST'].($_SERVER['SERVER_PORT'] != 443 ? ':'.$_SERVER['SERVER_PORT'] : '').'/'.(preg_replace('/^(bic)($|\/.*)/i', 'bictracker$2', $request)), true, true, false);
        } elseif (is_file($GLOBALS['siteconfig']['maindir'].$request)) {
            #Attempt to send the file
            if (preg_match('/^('.implode('|', $GLOBALS['siteconfig']['prohibited']).').*$/i', $request) === 0) {
                return (new \Simbiat\http20\Sharing)->fileEcho($GLOBALS['siteconfig']['maindir'].$request, allowedMime: $GLOBALS['siteconfig']['allowedMime'], exit: true);
            } else {
                return 403;
            }
        } else {
            #Create HTMLCache object to check for cache
            self::$HTMLCache = (new \Simbiat\HTMLCache($GLOBALS['siteconfig']['cachedir'].'html/'));
            #Attempt to use cache
            self::$HTMLCache->get('', true, true);
        }
        #Return 0, since we did not hit anything
        return 0;
    }
    
    #Function to send headers common for all items
    public function commonHeaders(): void
    {
        self::$headers->performance()->secFetch()->security('strict', [], [], [], ['GET', 'HEAD']);
    }
    
    #Function to send HTML only headers
    public function htmlHeaders(): void
    {
        self::$headers->features(['web-share'=>'\'self\''])->contentPolicy($GLOBALS['siteconfig']['allowedDirectives'], false);
    }
    
    #Function to send common Link headers
    public function commonLinks(): void
    {
        #Update list with dynamic values
        $GLOBALS['siteconfig']['links'] = array_merge($GLOBALS['siteconfig']['links'], [
            ['rel' => 'canonical', 'href' => 'https://'.(preg_match('/^[a-z0-9\-_~]+\.[a-z0-9\-_~]+$/', $_SERVER['HTTP_HOST']) === 1 ? 'www.' : '').$_SERVER['HTTP_HOST'].($_SERVER['SERVER_PORT'] != 443 ? ':'.$_SERVER['SERVER_PORT'] : '').'/'.$_SERVER['REQUEST_URI']],
            ['rel' => 'stylesheet preload', 'href' => '/frontend/css/'.$this->filesVersion($GLOBALS['siteconfig']['cssdir'].'*').'.css', 'as' => 'style'],
            ['rel' => 'preload', 'href' => '/frontend/js/'.$this->filesVersion($GLOBALS['siteconfig']['jsdir'].'*').'.js', 'as' => 'script'],
        ]);
        #Send headers
        self::$headers->links($GLOBALS['siteconfig']['links']);
    }
    
    #Database connection
    public function dbConnect(bool $extrachecks = false): bool
    {
        #Check in case we accidentally call this for 2nd time
        if (self::$dbup === false) {
            try {
                (new \Simbiat\Database\Pool)->openConnection((new \Simbiat\Database\Config)->setUser($GLOBALS['siteconfig']['database']['user'])->setPassword($GLOBALS['siteconfig']['database']['password'])->setDB($GLOBALS['siteconfig']['database']['dbname'])->setOption(\PDO::MYSQL_ATTR_FOUND_ROWS, true)->setOption(\PDO::MYSQL_ATTR_INIT_COMMAND, $GLOBALS['siteconfig']['database']['settings']));
                self::$dbup = true;
                #In some cases these extra checks are not required
                if ($extrachecks === true) {
                    #Check if maintenance
                    if ((new \Simbiat\Database\Controller)->selectValue('SELECT `value` FROM `sys__settings` WHERE `setting`=\'maintenance\'') == 1) {
                        $this->twigProc(error: 5032);
                    }
                    #Check if banned
                    if ((new \Simbiat\usercontrol\Bans)->bannedIP() === true) {
                        $this->twigProc(error: 403);
                    }
                }
            } catch (\Exception $e) {
                self::$dbup = false;
                return false;
            }
        }
        if ($extrachecks === true) {
            #Try to start session. It's not critical for the whole site, thus it's ok for it to fail
            if (session_status() === PHP_SESSION_NONE || session_status() === PHP_SESSION_ACTIVE) {
                #Use custom session handler
                session_set_save_handler(new \Simbiat\usercontrol\Session, true);
                session_start();
            }
        }
        return true;
    }
    
    #Twig processing of the generated page
    public function twigProc(array $extraVars = [], ?int $error = NULL, string $cacheStrat = '')
    {
        #Set Twig loader
        $twigloader = new \Twig\Loader\FilesystemLoader($GLOBALS['siteconfig']['templatesdir']);
        #Initiate Twig itself (use caching only for PROD environment)
        $twig = new \Twig\Environment($twigloader, ['cache' => (self::$PROD ? $GLOBALS['siteconfig']['cachedir'].'/twig' : false)]);
        #Set default variables
        $twigVars = [
            'domain' => $GLOBALS['siteconfig']['domain'],
            'url' => $GLOBALS['siteconfig']['domain'].$_SERVER['REQUEST_URI'],
            'site_name' => $GLOBALS['siteconfig']['site_name'],
            'currentyear' => '-'.date('Y', time()),
        ];
        #Set versions of CSS and JS
        $twigVars['css_version'] = $this->filesVersion($GLOBALS['siteconfig']['cssdir'].'*');
        $twigVars['js_version'] = $this->filesVersion($GLOBALS['siteconfig']['jsdir'].'*');
        #Flag for Save-Data header
        if (isset($_SERVER['HTTP_SAVE_DATA']) && preg_match('/^on$/i', $_SERVER['HTTP_SAVE_DATA']) === 1) {
            $twigVars['save_data'] = 'true';
        } else {
            $twigVars['save_data'] = 'false';
        }
        #Set link tags
        $twigVars['link_tags'] = self::$headers->links($GLOBALS['siteconfig']['links'], 'head');
        if (self::$dbup) {
            #Update default variables with values from database
            $twigVars = array_merge($twigVars, (new \Simbiat\Database\Controller)->selectPair('SELECT `setting`, `value` FROM `sys__settings`'));
            #Get sidebar
            $twigVars['sidebar']['fflinks'] = (new \Simbiat\Database\Controller)->selectAll(
                'SELECT `characters`.* FROM (SELECT `characterid` as `id`, \'character\' as `type`, `name`, `updated`, `registered` FROM `ff__character` ORDER BY `updated` DESC LIMIT 25) `characters`
                UNION ALL
                    SELECT `freecompnies`.* FROM (SELECT `freecompanyid` as `id`, \'freecompany\' as `type`, `name`, `updated`, `registered` FROM `ff__freecompany` ORDER BY `updated` DESC LIMIT 25) `freecompnies`
                UNION ALL
                    SELECT `linkshells`.* FROM (SELECT `linkshellid` as `id`, \'linkshell\' as `type`, `name`, `updated`, `registered` FROM `ff__linkshell` ORDER BY `updated` DESC LIMIT 25) `linkshells`
                UNION ALL
                    SELECT `pvpteams`.* FROM (SELECT `pvpteamid` as `id`, \'pvpteam\' as `type`, `name`, `updated`, `registered` FROM `ff__pvpteam` ORDER BY `updated` DESC LIMIT 25) `pvpteams`
                ORDER BY `updated` DESC LIMIT 25'
            );
        } else {
            #Enforce 503 error
            $error = 503;
        }
        #Set error for Twig
        if (!empty($error)) {
            #Server error page
            $twigVars['http_error'] = $error;
            $twigVars['title'] = $twigVars['site_name'].': '.($error === 5032 ? 'Maintenance' : strval($error));
            $twigVars['h1'] = $twigVars['title'];
            self::$headers->clientReturn(($error === 5032 ? '503' : strval($error)), false);
        }
        #Merge with extra variables provided
        $twigVars = array_merge($twigVars, $extraVars);
        #Set title if it's empty
        if (empty($twigVars['title'])) {
            $twigVars['title'] = $twigVars['site_name'];
        } else {
            $twigVars['title'] = $twigVars['title'].' on '.$twigVars['site_name'];
        }
        #Set title if it's empty
        if (empty($twigVars['h1'])) {
            $twigVars['h1'] = $twigVars['title'];
        }
        #Set OG values to global ones, if empty
        if (empty($twigVars['ogdesc'])) {
            $twigVars['ogdesc'] = $GLOBALS['siteconfig']['ogdesc'];
        }
        if (empty($twigVars['ogextra'])) {
            $twigVars['ogextra'] = $GLOBALS['siteconfig']['ogextra'];
        }
        if (empty($twigVars['ogimage'])) {
            $twigVars['ogimage'] = $GLOBALS['siteconfig']['ogimage'];
        }
        #Limit Ogdesc to 120 characters
        $twigVars['ogdesc'] = mb_substr($twigVars['ogdesc'], 0, 120, 'UTF-8');
        #Add metatags
        $this->socialMeta($twigVars);
        #Render page
        $output = $twig->render('main/main.html', $twigVars);
        #Cache page if cache age is setup
        if (self::$PROD && !empty($twigVars['cache_age']) && is_numeric($twigVars['cache_age'])) {
            self::$HTMLCache->set($output, '', intval($twigVars['cache_age']), 600, true, true);
        } else {
            (new \Simbiat\http20\Common)->zEcho($output, $cacheStrat);
        }
        if (session_status() == PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        exit;
    }
    
    #Function to generate social media metas
    private function socialMeta(&$twigVars): void
    {
        #Cache object
        $meta = (new \Simbiat\http20\Meta);
        #Twitter
        $twigVars['twitter_card'] = $meta->twitter([
            'title' => (empty($twigVars['title']) ? 'Simbiat Software' : $twigVars['title']),
            'description' => (empty($twigVars['ogdesc']) ? 'Simbiat Software' : $twigVars['ogdesc']),
            'site' => 'simbiat199',
            'site:id' => '3049604752',
            'creator' => '@simbiat199',
            'creator:id' => '3049604752',
            'image' => $twigVars['domain'].'/frontend/images/favicons/simbiat.png',
            'image:alt' => 'Simbiat Software logo',
        ], [], false);
        #Facebook
        $twigVars['facebook'] = $meta->facebook(288606374482851, [100002283569233]);
        #MS Tile (for pinned sites)
        $twigVars['ms_tile'] = $meta->msTile($GLOBALS['siteconfig']['mstile'], [], [], false, false);
    }
}
?>