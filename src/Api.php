<?php
declare(strict_types=1);

namespace silasmontgomery\YtsApi;

use Curl\Curl;

class Api
{
    private $debug;
    private $url;
    private $api_version;
    private $curl;
    private $trackers = [
        'udp://open.demonii.com:1337/announce',
        'udp://tracker.openbittorrent.com:80',
        'udp://tracker.coppersurfer.tk:6969',
        'udp://glotorrents.pw:6969/announce',
        'udp://tracker.opentrackr.org:1337/announce',
        'udp://torrent.gresille.org:80/announce',
        'udp://p4p.arenabg.com:1337',
        'udp://tracker.leechers-paradise.org:6969',
    ];
    private $endpoints = [
        'list_movies' => [
            '2' => '/api/v2/list_movies.json'
        ],
        'movie_details' => [
            '2' => '/api/v2/movie_details.json'
        ],
        'movie_suggestions' => [
            '2' => '/api/v2/movie_suggestions.json'
        ],
        'movie_comments' => [
            '2' => '/api/v2/movie_comments.json'
        ],
        'movie_reviews' => [
            '2' => '/api/v2/movie_reviews.json'
        ]
    ];

    public function __construct(string $url = "https://yta.ae", array $trackers = [], int $api_version = 2, bool $debug = false)
    {
        $this->debug = $debug;
        $this->url = $url;
        $this->api_version = $api_version;
        $this->curl = new Curl();
        $this->curl->setOpt(CURLOPT_SSL_VERIFYPEER, false);
        $this->trackers = array_merge($this->trackers, $trackers);
    }

    public function torrentSearch(string $query, int $page = 1, int $limit = 50): string
    {
        return $this->getData('list_movies', [
            'query_term' => $query,
            'page' => $page,
            'limit' => $limit
        ]);
    }

    private function getData(string $endpoint, array $params = null): string
    {
        $query_parts = [];
        if (!is_null($params)) {
            foreach ($params as $param => $value) {
                $query_parts[] = (is_string($param) ? urlencode($param) : $param) . "=" .
                    (is_string($value) ? urlencode($value) : $value);
            }
        }

        $url = $this->url . $this->endpoints[$endpoint][$this->api_version] . 
            (count($query_parts) > 0 ? '?' . implode("&", $query_parts) : null);

        $this->curl->get($url);

        if ($this->debug) {
            var_dump($this->curl->request_headers);
            var_dump($this->curl->response_headers);
        }

        if ($this->curl->error) {
            return $this->errorMessage();
        }

        // Create and add magnet links
        $movies = [];
        $response = json_decode($this->curl->response, true);
        if ($response['status'] == "ok") {
            if (isset($response['data']) && isset($response['data']['movies'])) {
                foreach ($response['data']['movies'] as $movie) {
                    $torrents = [];
                    foreach ($movie['torrents'] as $torrent) {
                        $torrent['magnet'] = "magnet:?xt=urn:btih:" . $torrent['hash'] . "&dn=" .
                            urlencode($movie['title']) . "&tr=" . implode('&tr', $this->trackers);
                        $torrents[] = $torrent;
                    }
                    $movie['torrents'] = $torrents;
                    $movies[] = $movie;
                }
                $response['data']['movies'] = $movies;
            }
        }
        
        return json_encode($response);
    }

    private function errorMessage(): string
    {
        return 'Curl Error Code: ' . $this->curl->error_code . ' (' . $this->curl->response . ')';
    }
}
