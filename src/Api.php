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

    public function __construct(string $url = "https://yts.lt", array $trackers = [], int $api_version = 2, bool $debug = false)
    {
        $this->debug = $debug;
        $this->url = $url;
        $this->api_version = $api_version;
        $this->curl = new Curl();
        $this->curl->setOpt(CURLOPT_SSL_VERIFYPEER, false);
        $this->trackers = array_merge($this->trackers, $trackers);
    }

    public function search(string $query, int $page = 1, int $limit = 50): object
    {
        return $this->getData('list_movies', [
            'query_term' => $query,
            'page' => $page,
            'limit' => $limit
        ]);
    }

    private function getData(string $endpoint, array $params = null): object
    {
        $result = (object)[
            'ok' => false,
            'message' => null,
            'torrents' => [],
            'count' => 0,
            'page' => null,
            'limit' => null
        ];

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
            $result['message'] = $this->errorMessage();
            return $result;
        }

        // Fetch query response and update result
        $response = json_decode($this->curl->response);
        if ($response->status == "ok") {
            $result->ok = true;
            $torrents = [];
            if (isset($response->data) && isset($response->data->movies)) {
                $result->count = $response->data->movie_count;
                $result->page = $response->data->page_number;
                $result->limit = $response->data->limit;
                // Parse movies and create individual results for each movie/torrent combo
                foreach ($response->data->movies as $movie) {
                    foreach ($movie->torrents as $one) {
                        $torrent['title'] = "$movie->title_long ($one->type / $one->quality)";
                        $torrent['size'] = $one->size;
                        $torrent['magnet'] = "magnet:?xt=urn:btih:" . $one->hash . "&dn=" .
                            urlencode($movie->title_long) . "&tr=" . implode('&tr', $this->trackers);
                        $torrent['seeds'] = $one->seeds;
                        $torrent['peers'] = $one->peers;
                        $torrents[] = (object)$torrent;
                    }
                }
                $result->torrents = $torrents;
            }
        }
        // Set status message from YTS
        $result->message = $response->status_message;
        
        return $result;
    }

    private function errorMessage(): string
    {
        return 'Curl Error Code: ' . $this->curl->error_code . ' (' . $this->curl->response . ')';
    }
}
