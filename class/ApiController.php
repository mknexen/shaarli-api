<?php

class ApiController extends AbstractApiController
{

    /**
     * Parsing request and execute the action
     */
    public function route()
    {

        // Les actions disponible
        $actions = array(
            'feed',
            'feeds',
            'latest',
            'top',
            'search',
            'discussion',
            'bestlinks',
            'random',
            'keywords',
            'syncfeeds',
            'getfavicon',
            'ping',
        );

        $action = $this->getRequestAction();

        if (!in_array($action, $actions)) {
            $this->error('Bad request (invalid action)');
        }

        // Les formats disponibles
        $formats = array(
            'json',
            'rss',
            'opml',
        );

        // Default format: json
        $format = (isset($_GET['format']) && in_array($_GET['format'], $formats)) ? $_GET['format']: 'json';

        $arguments = $_GET;

        if ($action == 'syncfeeds') {
            $this->syncfeeds();
            $this->outputJSON(array('success' => 1));
        }

        $api = new ShaarliApi();

        if (method_exists($api, $action)) {
            try {
                
                // Execute the action
                $results = $api->$action($arguments);
            } catch (ShaarliApiException $e) {
                $this->error($e->getMessage());
            } catch (\Exception $e) {
                exit();
            }
        } else {
            $this->error('Bad request (invalid action)');
        }


        if ($action == 'getfavicon') {
            if (isset($results['favicon']) && file_exists($results['favicon'])) {
                header('Content-Type: image/png');
                header('Cache-Control: private, max-age=10800, pre-check=10800');
                header('Pragma: private');
                header('Expires: ' . date(DATE_RFC822, strtotime('7 day')));

                readfile($results['favicon']);
            }

            exit();
        }

        // Render results
        if ($format == 'json') {
            $this->outputJSON($results, (isset($_GET['pretty']) && $_GET['pretty'] == 1));
        }
        // RSS
        elseif ($format == 'rss') {
            if ($action == 'latest') {
                $config = array(
                    'title' => 'Shaarli API - Latest entries',
                );
            } elseif ($action == 'search') {
                $config = array(
                    'title' => 'Shaarli API - Search feed',
                );
            } elseif ($action == 'bestlinks') {
                $config = array(
                    'title' => 'Shaarli API - Best links',
                );
            } else {
                $this->error('Bad request (RSS format unavailable for this action)');
                exit();
            }

            $this->outputRSS($results, $config);
            exit();
        }
        // OPML
        elseif ($format == 'opml') {
            if ($action == 'feeds') {
                $this->outputOPML($results);
                exit();
            } else {
                $this->error('Bad request (OPML format unavailable for this action)');
            }
        }

        exit();
    }

    /**
     * syncfeeds action
     */
    public function syncfeeds()
    {
        $api = new ShaarliApi();
        $api->syncfeeds(shaarli_api_nodes());
        $api->syncWithOpmlFiles(shaarli_opml_files());
    }
}
