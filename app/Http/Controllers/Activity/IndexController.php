<?php

namespace App\Http\Controllers\Activity;

use App\Http\Controllers\Controller;
use App\Http\Decorator\ActivityParserTrait;
use App\Http\Decorator\PaginationTrait;
use App\Http\Decorator\RateLimitTrait;
use Github\HttpClient\Message\ResponseMediator;
use GrahamCampbell\GitHub\Facades\GitHub;
use Guzzle\Http\Message\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;

class IndexController extends Controller
{
    use RateLimitTrait;
    use PaginationTrait;
    use ActivityParserTrait;

    public function showIndex(Request $request, $page = 1)
    {
        $me = GitHub::me()->show();

        /** @var Response $response */
        $response = GitHub::connection()->getHttpClient()->get(
            sprintf('/users/%s/received_events?page=%s', $me['login'], (int) $page)
        );

        $activity   = ResponseMediator::getContent($response);
        $pagination = $this->getPagination($response);
        $pending    = Input::get('pending', 0);

        // Save the latest activity ID for live fetching
        if (count($activity)) {
            $request->session()->put('last_event_id', $activity[0]['id']);

            $this->parseActivity($activity, $me, $pending);
        }

        // Get the interval Github allows for polling
        $interval = $response->hasHeader('X-Poll-Interval') ? (string) $response->getHeader('X-Poll-Interval') : 60;

        return view(
            'activity.index',
            [
                'me'         => $me,
                'activity'   => $activity,
                'pagination' => $pagination,
                'page'       => $page,
                'interval'   => (int) $interval * 1000,
                'pending'    => $pending
            ]
        );
    }
}