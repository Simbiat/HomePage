<?php
declare(strict_types=1);
namespace Simbiat;

class HomeRouter
{
    #Function to prepare data for user control pages
    public function usercontrol(array $uri): array
    {
        $headers = (new \Simbiat\http20\Headers);
        $html = (new \Simbiat\http20\HTML);
        #Check if URI is empty
        if (empty($uri)) {
            $headers->redirect('https://'.$_SERVER['HTTP_HOST'].($_SERVER['SERVER_PORT'] != 443 ? ':'.$_SERVER['SERVER_PORT'] : '').'/uc/registration', true, true, false);
        }
        #Prepare array
        $outputArray = [
            'service_name' => 'usercontrol',
            'h1' => 'User Control',
            'title' => 'User Control',
            'ogdesc' => 'User\'s Control Panel',
        ];
        #Start breadcrumbs
        $breadarray = [
            ['href'=>'/', 'name'=>'Home page'],
        ];
        $uri[0] = strtolower($uri[0]);
        switch ($uri[0]) {
            #Process search page
            case 'registration':
            case 'register':
            case 'login':
            case 'signin':
            case 'signup':
            case 'join':
                if (empty($_SESSION['username'])) {
                    $outputArray['subservice'] = 'registration';
                    $outputArray['h1'] = $outputArray['title'] = 'User sign in/join';
                    $outputArray['login_form'] = (new \Simbiat\usercontrol\Register)->form();
                } else {
                    #Redirect to main page if user is already authenticated
                    $headers->redirect('https://'.$_SERVER['HTTP_HOST'].($_SERVER['SERVER_PORT'] != 443 ? ':'.$_SERVER['SERVER_PORT'] : ''), false, true, false);
                }
                break;
            default:
                $outputArray['http_error'] = 404;
                break;
        }
        #Add breadcrumbs
        $breadarray = $html->breadcrumbs(items: $breadarray, links: true, headers: true);
        $outputArray['breadcrumbs']['usercontrol'] = $breadarray['breadcrumbs'];
        $outputArray['breadcrumbs']['links'] = $breadarray['links'];
        return $outputArray;
    }
    
    #Function to prepare data for FFTracker depending on the URI
    public function fftracker(array $uri): array
    {
        $fftracker = (new \Simbiat\FFTracker);
        $html = (new \Simbiat\http20\HTML);
        $headers = (new \Simbiat\http20\Headers);
        #Check if URI is empty
        if (empty($uri)) {
            $headers->redirect('https://'.$_SERVER['HTTP_HOST'].($_SERVER['SERVER_PORT'] != 443 ? ':'.$_SERVER['SERVER_PORT'] : '').'/fftracker/search', true, true, false);
        }
        #Prepare array
        $outputArray = [
            'service_name' => 'fftracker',
            'h1' => 'Final Fantasy XIV Tracker',
            'title' => 'Final Fantasy XIV Tracker',
            'ogdesc' => 'Tracker for Final Fantasy XIV entities and respective statistics',
        ];
        #Start breadcrumbs
        $breadarray = [
            ['href'=>'/', 'name'=>'Home page'],
        ];
        $uri[0] = strtolower($uri[0]);
        switch ($uri[0]) {
            #Process search page
            case 'search':
                $outputArray['subservice'] = 'search';
                #Set search value
                if (!isset($uri[1])) {
                    $uri[1] = '';
                }
                $decodedSearch = rawurldecode($uri[1]);
                #Continue breadcrumb
                $breadarray[] = ['href'=>'/fftracker/search', 'name'=>'Search'];
                if (!empty($uri[1])) {
                    $breadarray[] = ['href'=>'/fftracker/search/'.$uri[1], 'name'=>'Search for '.$decodedSearch];
                } else {
                    #Cache due to random entities
                    $outputArray['cache_age'] = 86400;
                }
                #Set specific values
                $outputArray['searchvalue'] = $decodedSearch;
                $outputArray['searchresult'] = $fftracker->Search($decodedSearch);
                break;
            #Process statistics
            case 'statistics':
                #Check if type is set
                if (empty($uri[1])) {
                    $headers->redirect('https://'.$_SERVER['HTTP_HOST'].($_SERVER['SERVER_PORT'] != 443 ? ':'.$_SERVER['SERVER_PORT'] : '').'/fftracker/statistics/genetics', true, true, false);
                } else {
                    $uri[1] = strtolower($uri[1]);
                    if (in_array($uri[1], ['genetics', 'astrology', 'characters', 'freecompanies', 'cities', 'grandcompanies', 'servers', 'achievements', 'timelines', 'other', 'bugs'])) {
                        $outputArray['subservice'] = 'statistics';
                        #Set statistics type
                        $outputArray['ff_stat_type'] = $uri[1];
                        #Continue breadcrumb
                        $tempname = ucfirst(preg_replace('/s$/i', 's\'', preg_replace('/companies/i', ' Companies', $uri[1])).' statistics');
                        $breadarray[] = ['href'=>'/fftracker/statistics/'.$uri[1], 'name'=>$tempname];
                        #Get the data
                        $outputArray[$uri[0]] = $fftracker->Statistics($uri[1]);
                        #Update meta
                        $outputArray['h1'] .= ': Statistics';
                        $outputArray['title'] .= ': Statistics';
                        $outputArray['ogdesc'] = $tempname.' on '.$outputArray['ogdesc'];
                        $outputArray['cache_age'] = 86400;
                    } else {
                        $outputArray['http_error'] = 404;
                    }
                }
                break;
            #Process lists
            case 'crossworldlinkshells':
            case 'crossworld_linkshells':
                #Redirect to linkshells list, since we do not differentiate between them that much
                $headers->redirect('https://'.$_SERVER['HTTP_HOST'].($_SERVER['SERVER_PORT'] != 443 ? ':'.$_SERVER['SERVER_PORT'] : '').'/fftracker/linkshells/'.(empty($uri[1]) ? '' : $uri[1]), true, true, false);
                break;
            case 'freecompanies':
            case 'linkshells':
            case 'characters':
            case 'achievements':
            case 'pvpteams':
                #Check if page was provided and is numeric
                if (empty($uri[1]) || !is_numeric($uri[1]) || intval($uri[1]) < 1) {
                    $headers->redirect('https://'.$_SERVER['HTTP_HOST'].($_SERVER['SERVER_PORT'] != 443 ? ':'.$_SERVER['SERVER_PORT'] : '').'/fftracker/'.$uri[0].'/1', true, true, false);
                }
                #Ensure that use INT
                $uri[1] = intval($uri[1]);
                #Get data
                $outputArray['searchresult'] = $fftracker->listEntities($uri[0], ($uri[1]-1)*100, 100);
                #Check that we requested page is not more than what was requested
                $lastpage = intval(ceil($outputArray['searchresult']['statistics']['count']/100));
                if ($uri[1] > $lastpage) {
                    #Bad page
                    unset($outputArray['searchresult']);
                    $outputArray['http_error'] = 404;
                } else {
                    #Try to get out earlier based on date of last update of the list. Unlikely, that will help, but still.
                    $headers->lastModified($outputArray['searchresult']['statistics']['updated'], true);
                    $outputArray['subservice'] = 'list';
                    #Adjust list type to human-readable value
                    $tempname = match($uri[0]) {
                        'freecompanies' => 'Free Companies',
                        'pvpteams' => 'PvP Teams',
                        default => ucfirst($uri[0]),
                    };
                    #Continue breadcrumb
                    $breadarray[] = ['href'=>'/fftracker/'.$uri[0].'/'.$uri[1], 'name'=>$tempname.', page '.$uri[1]];
                    #Update meta
                    $outputArray['h1'] .= ': '.$tempname.', page '.$uri[1];
                    $outputArray['title'] .= ': '.$tempname.', page '.$uri[1];
                    $outputArray['ogdesc'] = 'List of '.$tempname.' on '.$outputArray['ogdesc'];
                    #Prepare pagination
                    $pagination = $html->pagination($uri[1], $lastpage, links: true, headers: true);
                    $outputArray['pagination_top'] = $pagination['pagination'];
                    $outputArray['pagination']['links'] = $pagination['links'];
                    $outputArray['pagination_bottom'] = $html->pagination($uri[1], $lastpage);
                }
                break;
            case 'crossworldlinkshell':
            case 'crossworld_linkshell':
                #Redirect to linkshell page, since we do not differentiate between them that much
                $headers->redirect('https://'.$_SERVER['HTTP_HOST'].($_SERVER['SERVER_PORT'] != 443 ? ':'.$_SERVER['SERVER_PORT'] : '').'/fftracker/linkshell/'.(empty($uri[1]) ? '' : $uri[1]), true, true, false);
                break;
            case 'achievement':
            case 'character':
            case 'freecompany':
            case 'linkshell':
            case 'pvpteam':
                #Check if id was provided and has valid format
                if (empty($uri[1]) || preg_match('/^[0-9a-f]{1,40}$/i', $uri[1]) !== 1) {
                    $outputArray['http_error'] = 404;
                } else {
                    #Grab data
                    $outputArray[$uri[0]] = $fftracker->TrackerGrab($uri[0], $uri[1]);
                    #Check if ID was found
                    if (empty($outputArray[$uri[0]])) {
                        $outputArray['http_error'] = 404;
                    } else {
                        $outputArray['subservice'] = $uri[0];
                        #Try to exit early based on modification date
                        $headers->lastModified($outputArray[$uri[0]]['updated'], true);
                        #Continue breadcrumb by adding link to list (1 page)
                        $breadarray[] = match($uri[0]) {
                            'freecompany' => ['href'=>'/fftracker/freecompanies/1', 'name'=>'Free Companies'],
                            'pvpteam' => ['href'=>'/fftracker/'.$uri[0].'s/1', 'name'=>'PvP Teams'],
                            default => ['href'=>'/fftracker/'.$uri[0].'s/1', 'name'=>ucfirst($uri[0]).'s'],
                        };
                        #Continue breadcrumb by adding link to current entity
                        $breadarray[] = ['href' => '/fftracker/'.$uri[0].'/'.$outputArray[$uri[0]][$uri[0].'id'].'/'.rawurlencode($outputArray[$uri[0]]['name']), 'name' => $outputArray[$uri[0]]['name']];
                        #Generate levels' list if we have members
                        if (!empty($outputArray[$uri[0]]['members'])) {
                            $outputArray[$uri[0]]['levels'] = array_unique(array_column($outputArray[$uri[0]]['members'], 'rank'));
                        }
                        #Update meta
                        $outputArray['h1'] = $outputArray[$uri[0]]['name'];
                        $outputArray['title'] = $outputArray[$uri[0]]['name'];
                        $outputArray['ogdesc'] = $outputArray[$uri[0]]['name'].' on '.$outputArray['ogdesc'];
                        #Setup OG profile for characters
                        if ($uri[0] === 'character') {
                            $outputArray['ogtype'] = 'profile';
                            $profname = explode(' ', $outputArray[$uri[0]]['name']);
                            $outputArray['ogextra'] = '
                                <meta property="og:type" content="profile" />
                                <meta property="profile:first_name" content="'.htmlspecialchars($profname[0]).'" />
                                <meta property="profile:last_name" content="'.htmlspecialchars($profname[1]).'" />
                                <meta property="profile:username" content="'.htmlspecialchars($outputArray[$uri[0]]['name']).'" />
                                <meta property="profile:gender" content="'.htmlspecialchars(($outputArray[$uri[0]]['genderid'] === 1 ? 'male' : 'female')).'" />
                            ';
                        }
                        #Link header/tag for API
                        $altlink = [['rel' => 'alternate', 'type' => 'application/json', 'title' => 'JSON representation', 'href' => '/api/fftracker/'.$uri[0].'/'.$outputArray[$uri[0]][$uri[0].'id']]];
                        #Send HTTP header
                        $headers->links($altlink);
                        #Add link to HTML
                        $outputArray['link_extra'] = $headers->links($altlink, 'head');
                        #Cache age for achivements (due to random characters)
                        if ($uri[0] === 'achievement') {
                            $outputArray['cache_age'] = 259200;
                        }
                    }
                }
                break;
            default:
                $outputArray['http_error'] = 404;
                break;
        }
        #If we have 404 - generate random entities as suggestions
        if (!empty($outputArray['http_error']) && $outputArray['http_error'] === 404) {
            $outputArray['ff_suggestions'] = $fftracker->GetRandomEntities($fftracker->maxlines);
            #Cache due to random entities
            $outputArray['cache_age'] = 259200;
        }
        #Add breadcrumbs
        $breadarray = $html->breadcrumbs(items: $breadarray, links: true, headers: true);
        $outputArray['breadcrumbs']['fftracker'] = $breadarray['breadcrumbs'];
        $outputArray['breadcrumbs']['links'] = $breadarray['links'];
        return $outputArray;
    }
    
    #Function to prepare data for BICTracker depending on the URI
    public function bictracker(array $uri): array
    {
        $headers = (new \Simbiat\http20\Headers);
        $bictracker = (new \bicDBF\bicDBF);
        #Check if URI is empty
        if (empty($uri)) {
            $headers->redirect('https://'.$_SERVER['HTTP_HOST'].($_SERVER['SERVER_PORT'] != 443 ? ':'.$_SERVER['SERVER_PORT'] : '').'/bictracker/search', true, true, false);
        }
        $uri[0] = strtolower($uri[0]);
        #Gracefully handle legacy links
        if ($uri[0] !== 'search' && mb_strlen(rawurldecode($uri[0])) === 8) {
            #Assume legacy '/bic/vkey' link type was used and redirect to proper link
            $headers->redirect('https://'.$_SERVER['HTTP_HOST'].($_SERVER['SERVER_PORT'] != 443 ? ':'.$_SERVER['SERVER_PORT'] : '').'/bictracker/bic/'.$uri[0], true, true, false);
        }
        #Tell that content is intended for Russians
        header('Content-Language: ru-RU');
        #Prepare array
        $outputArray = [
            'service_name' => 'bictracker',
            'h1' => 'BIC Tracker',
            'title' => 'BIC Tracker',
            'ogdesc' => 'Representation of Bank Identification Codes from Central Bank of Russia',
        ];
        #Start breadcrumbs
        $breadarray = [
            ['href'=>'/', 'name'=>'Home page'],
        ];
        switch ($uri[0]) {
            #Process search page
            case 'search':
                $outputArray['subservice'] = $uri[0];
                $outputArray['maxresults'] = 50;
                #Continue breadcrumbs
                $breadarray[] = ['href'=>'/bictracker/search', 'name'=>'Search'];
                #Set search value
                if (!isset($uri[1])) {
                    $uri[1] = '';
                }
                #Sanitize search value
                $decodedSearch = preg_replace('/[^\P{Cyrillic}a-zA-Z0-9!@#\$%&\*\(\)\-\+=|\?<>]/', '', rawurldecode($uri[1]));
                #Check if search value was provided
                if (empty($uri[1])) {
                    #Get statistics
                    $outputArray = array_merge($outputArray, $bictracker->Statistics());
                } else {
                    #Continue breadcrumbs
                    $breadarray[] = ['href'=>'/bictracker/search/'.$uri[1], 'name'=>'Search for '.$decodedSearch];
                    #Get search results
                    $outputArray['searchresult'] = $bictracker->Search($uri[1]);
                }
                break;
            case 'bic':
                $outputArray['subservice'] = $uri[0];
                if (empty($uri[1])) {
                    $outputArray['http_error'] = 404;
                } else {
                    #Sanitize vkey
                    $vkey = preg_replace('/[^a-zA-Z0-9!@#\$%&\*\(\)\-\+=|\?<>]/', '', rawurldecode($uri[1]));
                    #Try to get details
                    $outputArray['bicdetails'] = $bictracker->getCurrent($vkey);
                    #Check if key was found
                    if (empty($outputArray['bicdetails'])) {
                        $outputArray['http_error'] = 404;
                    } else {
                        #Try to exit early based on modification date
                        if (!empty($outputArray['bicdetails']['DT_IZM'])) {
                            $headers->lastModified(strtotime($outputArray['bicdetails']['DT_IZM']), true);
                        }
                        #Continue breadcrumbs
                        $breadarray[] = ['href' => '/bictracker/bic/'.$uri[1], 'name' => $outputArray['bicdetails']['NAMEP']];
                        #Set cache due to query complexity
                        $outputArray['cache_age'] = 259200;
                        #Update meta
                        $outputArray['title'] = $outputArray['bicdetails']['NAMEP'];
                        $outputArray['h1'] = $outputArray['bicdetails']['NAMEP'];
                        $outputArray['ogdesc'] = $outputArray['bicdetails']['NAMEP'].' ('.$outputArray['bicdetails']['NEWNUM'].') in BIC Tracker';
                        #Link header/tag for API
                        $altlink = [['rel' => 'alternate', 'type' => 'application/json', 'title' => 'JSON representation', 'href' => '/api/bictracker/bic/'.rawurlencode($vkey)]];
                        #Send HTTP header
                        $headers->links($altlink);
                        #Add link to HTML
                        $outputArray['link_extra'] = $headers->links($altlink, 'head');
                    }
                }
                break;
            default:
                $outputArray['http_error'] = 404;
                break;
        }
        #Add breadcrumbs
        $breadarray = (new \Simbiat\http20\HTML)->breadcrumbs(items: $breadarray, links: true, headers: true);
        $outputArray['breadcrumbs']['bictracker'] = $breadarray['breadcrumbs'];
        $outputArray['breadcrumbs']['links'] = $breadarray['links'];
        return $outputArray;
    }
}
?>