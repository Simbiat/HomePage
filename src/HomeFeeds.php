<?php
declare(strict_types=1);
namespace Simbiat;

class HomeFeeds
{
    #Fcuntion to parse URI and generate appropriate feed
    public function uriParse(array $uri): array
    {
        if (empty($uri[0])) {
            return ['http_error' => 404];
        } else {
            return match(strtolower($uri[0])) {
                'sitemap' => $this->sitemap(array_slice($uri, 1)),
                'atom' => '',
                'rss' => '',
                default => ['http_error' => 404],
            };
        }
    }
    
    #Generate sitemap
    private function sitemap(array $uri): array
    {
        #Check that not empty
        if (empty($uri)) {
            #Redirect to HTML index
            (new \Simbiat\http20\Headers)->redirect('https://'.$_SERVER['HTTP_HOST'].($_SERVER['SERVER_PORT'] != 443 ? ':'.$_SERVER['SERVER_PORT'] : '').'/sitemap/html/index', true, true, false);
        } else {
            #Check for Accept header
            
            #Check that format was provided
            if (empty($uri[0])) {
                #Redirect to HTML index
                (new \Simbiat\http20\Headers)->redirect('https://'.$_SERVER['HTTP_HOST'].($_SERVER['SERVER_PORT'] != 443 ? ':'.$_SERVER['SERVER_PORT'] : '').'/sitemap/html/index', true, true, false);
            } else {
                $uri[0] = strtolower($uri[0]);
                if (in_array($uri[0], ['html', 'xml', 'txt'])) {
                    #Check if initial page was provided
                    if (empty($uri[1])) {
                        #Redirect to index
                        (new \Simbiat\http20\Headers)->redirect('https://'.$_SERVER['HTTP_HOST'].($_SERVER['SERVER_PORT'] != 443 ? ':'.$_SERVER['SERVER_PORT'] : '').'/sitemap/'.$uri[0].'/index', true, true, false);
                    } else {
                        $uri[1] = strtolower($uri[1]);
                        #Set base URL
                        $baseurl = 'https://'.$_SERVER['HTTP_HOST'].($_SERVER['SERVER_PORT'] != 443 ? ':'.$_SERVER['SERVER_PORT'] : '').'/';
                        #Prepare list of links
                        $links = [];
                        if ($uri[1] === 'index') {
                            $links = $this->sitemapIndex($baseurl.'sitemap/'.$uri[0].'/');
                        } elseif ($uri[1] === 'general') {
                            #Static links
                            $links = [
                                ['loc'=>$baseurl, 'name'=>'Home Page'],
                                ['loc'=>$baseurl.'bic/search/', 'name'=>'BIC Tracker Search'],
                                ['loc'=>$baseurl.'fftracker/search/', 'name'=>'FFXIV Tracker Search'],
                                ['loc'=>$baseurl.'sitemap/xml/index/', 'name'=>'XML Sitemap'],
                                ['loc'=>$baseurl.'sitemap/html/index/', 'name'=>'HTML Sitemap'],
                                ['loc'=>$baseurl.'sitemap/txt/index/', 'name'=>'TXT Sitemap'],
                                ['loc'=>$baseurl.'fftracker/statistics/genetics/', 'changefreq' => 'weekly', 'name'=>'Genetics'],
                                ['loc'=>$baseurl.'fftracker/statistics/astrology/', 'changefreq' => 'weekly', 'name'=>'Astrology'],
                                ['loc'=>$baseurl.'fftracker/statistics/characters/', 'changefreq' => 'weekly', 'name'=>'Characters'],
                                ['loc'=>$baseurl.'fftracker/statistics/freecompanies/', 'changefreq' => 'weekly', 'name'=>'Free Companies'],
                                ['loc'=>$baseurl.'fftracker/statistics/cities/', 'changefreq' => 'weekly', 'name'=>'Cities'],
                                ['loc'=>$baseurl.'fftracker/statistics/grandcompanies/', 'changefreq' => 'weekly', 'name'=>'Grand Companies'],
                                ['loc'=>$baseurl.'fftracker/statistics/servers/', 'changefreq' => 'weekly', 'name'=>'Servers'],
                                ['loc'=>$baseurl.'fftracker/statistics/achievements/', 'changefreq' => 'weekly', 'name'=>'Achievements'],
                                ['loc'=>$baseurl.'fftracker/statistics/timelines/', 'changefreq' => 'weekly', 'name'=>'Timelines'],
                                ['loc'=>$baseurl.'fftracker/statistics/other/', 'changefreq' => 'weekly', 'name'=>'Other'],
                            ];
                        } else {
                            if (empty($uri[2])) {
                                #Redirect to 1st page
                                (new \Simbiat\http20\Headers)->redirect('https://'.$_SERVER['HTTP_HOST'].($_SERVER['SERVER_PORT'] != 443 ? ':'.$_SERVER['SERVER_PORT'] : '').'/sitemap/'.$uri[0].'/'.$uri[1].'/1', true, true, false);
                            } else {
                                if (is_numeric($uri[2])) {
                                    #Get links
                                    $uri[2] = intval($uri[2]);
                                    $links = match($uri[1]) {
                                        'bic' => (new \Simbiat\Database\Controller)->selectAll('SELECT CONCAT(\''.$baseurl.'bictracker/bic/\', `VKEY`, \'/\') AS `loc`, `DT_IZM` AS `lastmod`, `NAMEP` AS `name` FROM `bic__list` ORDER BY `NAMEP` ASC LIMIT '.$uri[2].', 50000'),
                                        'forum' => (new \Simbiat\Database\Controller)->selectAll('SELECT CONCAT(\''.$baseurl.'thread/\', `threadid`, \'/\') AS `loc`, `date` AS `lastmod`, `title` AS `name` FROM `forum__thread` ORDER BY `title` ASC LIMIT '.$uri[2].', 50000'),
                                        'ff_achievement' => (new \Simbiat\Database\Controller)->selectAll('SELECT CONCAT(\''.$baseurl.'fftracker/achievement/\', `achievementid`, \'/\') AS `loc`, `updated` AS `lastmod`, `name` FROM `ff__achievement` ORDER BY `name` ASC LIMIT '.$uri[2].', 50000'),
                                        'ff_character' => (new \Simbiat\Database\Controller)->selectAll('SELECT CONCAT(\''.$baseurl.'fftracker/character/\', `characterid`, \'/\') AS `loc`, `updated` AS `lastmod`, `name` FROM `ff__character` ORDER BY `name` ASC LIMIT '.$uri[2].', 50000'),
                                        'ff_freecompany' => (new \Simbiat\Database\Controller)->selectAll('SELECT CONCAT(\''.$baseurl.'fftracker/freecompany/\', `freecompanyid`, \'/\') AS `loc`, `updated` AS `lastmod`, `name` FROM `ff__freecompany` ORDER BY `name` ASC LIMIT '.$uri[2].', 50000'),
                                        'ff_linkshell' => (new \Simbiat\Database\Controller)->selectAll('SELECT CONCAT(\''.$baseurl.'fftracker/linkshell/\', `linkshellid`, \'/\') AS `loc`, `updated` AS `lastmod`, `name` FROM `ff__linkshell` ORDER BY `name` ASC LIMIT '.$uri[2].', 50000'),
                                        'ff_pvpteam' => (new \Simbiat\Database\Controller)->selectAll('SELECT CONCAT(\''.$baseurl.'fftracker/pvpteam/\', `pvpteamid`, \'/\') AS `loc`, `updated` AS `lastmod`, `name` FROM `ff__pvpteam` ORDER BY `name` ASC LIMIT '.$uri[2].', 50000'),
                                        default => [],
                                    };
                                }
                            }
                        }
                        if (!empty($links) && is_array($links)) {
                            #Send alternate links. Not using `links()`, because we need to ensure only `alternate` links for the sitemap are sent
                            $linkheader = [];
                            #Generate string
                            foreach (['html', 'txt', 'xml'] as $type) {
                                if ($uri[0] !== $type) {
                                    #Have to use `match` due to need of different MIME types
                                    $linkheader[] = match($type) {
                                       'html' => '<'.$baseurl.str_ireplace($uri[0], $type, $_SERVER['REQUEST_URI']).'>; title="HTML Version"; rel="alternate"; type="text/html"',
                                       'txt' => '<'.$baseurl.str_ireplace($uri[0], $type, $_SERVER['REQUEST_URI']).'>; title="Text Version"; rel="alternate"; type="text/plain"',
                                       'xml' => '<'.$baseurl.str_ireplace($uri[0], $type, $_SERVER['REQUEST_URI']).'>; title="XML Version"; rel="alternate"; type="application/xml"',
                                    };
                                }
                            }
                            header('Link: '.implode(', ', $linkheader), true);
                            #Return sitemap
                            (new \Simbiat\http20\Sitemap)->sitemap($links, ($uri[0] === 'xml' && $uri[1] === 'index' ? 'index' : $uri[0]), true);
                        }
                    }
                }
            }
        }
        #If we reach here, it means, the requested page does not exist
        return ['http_error' => 404];
    }
    
    #Helper function to generate index page for sitemap
    private function sitemapIndex(string $baseurl): array
    {
        #Sitemap for general links (non-countable)
        $links = [
            ['loc'=>$baseurl.'general/', 'name'=>'General links'],
        ];
        #Get countable links
        $counts = (new \Simbiat\Database\Controller)->selectAll('
            SELECT \'forum\' AS `link`, \'Forums\' AS `name`, COUNT(*) AS `count` FROM `forum__thread`
            UNION ALL
            SELECT \'bic\' AS `link`, \'Russian Bank Codes\' AS `name`, COUNT(*) AS `count` FROM `bic__list`
            UNION ALL
            SELECT \'ff_character\' AS `link`, \'FFXIV Characters\' AS `name`, COUNT(*) AS `count` FROM `ff__character`
            UNION ALL
            SELECT \'ff_freecompany\' AS `link`, \'FFXIV Free Companies\' AS `name`, COUNT(*) AS `count` FROM `ff__freecompany`
            UNION ALL
            SELECT \'ff_linkshell\' AS `link`, \'FFXIV Linkshells\' AS `name`, COUNT(*) AS `count` FROM `ff__linkshell`
            UNION ALL
            SELECT \'ff_pvpteam\' AS `link`, \'FFXIV PvP Teams\' AS `name`, COUNT(*) AS `count` FROM `ff__pvpteam`
            UNION ALL
            SELECT \'ff_achievement\' AS `link`, \'FFXIV Achievements\' AS `name`, COUNT(*) AS `count` FROM `ff__achievement`
        ');
        #Generate links
        foreach ($counts as $linktype) {
            if ($linktype['count'] <= 50000) {
                $links[] = ['loc'=>$baseurl.$linktype['link'].'/', 'name'=>$linktype['name']];
            } else {
                $pages = intval(ceil($linktype['count']/50000));
                for ($page = 1; $page <= $pages; $page++) {
                    $links[] = ['loc'=>$baseurl.$linktype['link'].'/'.$page.'/', 'name'=>$linktype['name'].', Page '.$page];
                }
            }
        }
        #Return links
        return $links;
    }
}
?>