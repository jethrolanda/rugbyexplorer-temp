<?php

/**
 * Plugin Name: RugbyExplorer Temporary
 * Description: RugbyExplorer Temporary
 * Version: 1.0
 * Author: Jethrolanda
 * Author URI: jethrolanda.com
 * Text Domain: rugbyexplorer-temp
 * Domain Path: /languages/
 * Requires at least: 5.7
 * Requires PHP: 7.2
 */

defined('ABSPATH') || exit;

add_shortcode('player_lineup', 'player_lineup');


function player_lineup($atts)
{

  $atts = shortcode_atts(array(
    'season_id' => '',
    'competition_id' => ''
  ), $atts, 'fusesports_fixtures');

  wp_enqueue_style('fusesport-api-css');

  ob_start();

  $args = array(
    'fixtue_code' => get_post_meta(get_the_ID(), 'fixture_code', true)
  );
  $data = getPlayerLineUpData($args);
  $test = wp_remote_get('https://api.fusesport.com/comps/' . $atts['competition_id'] . '/get/');
  $body = wp_remote_retrieve_body($test);
  $fixtures = json_decode($body, true);
  // error_log(print_r($fixtures, true));
  require_once('player-lineup-view.php');

  // content
  return ob_get_clean();
}

function getPlayerLineUpData($args = array())
{
  // args example
  // $args = array(
  //   'season' => '2025',
  //   'competition' => 'mLGoqgHnacX2AnmgD',
  //   'team' => 'DZJhdynaY4wSDBQpQ',
  //   'entityId' => '53371',
  //   'type' => 'fixtures' or 'results'
  // );
  extract($args);

  // Rugby Xplorer GraphQL endpoint (update if different)
  $graphql_url = 'https://rugby-au-cms.graphcdn.app/';

  $body = [
    "operationName" => "matchdetailsRugbyComAu",
    "variables" => [
      "comp" => [
        "id" => null,
        "season" => null,
        "fixture" => $fixture_code,
        "sourceType" => "2"
      ]
    ],
    "query" => "query matchdetailsRugbyComAu(\$comp: CompInput) {
      getFixtureItem(comp: \$comp) {
        ...Fixtures_fixture
        __typename
      }
      allMatchCommentary(comp: \$comp) {
        ...Fixture_MatchCommentary
        __typename
      }
      allMatchStatsSummary(comp: \$comp) {
        id
        lineUp {
          id
          players {
            ...MatchLineup_matchplayer
            __typename
          }
          substitutes {
            ...MatchLineup_matchplayer
            __typename
          }
          coaches {
            ...MatchLineup_matchplayer
            __typename
          }
          __typename
        }
        referees {
          ...MatchStatsSummary_matchreferee
          __typename
        }
        pointsSummary {
          id
          tries {
            ...PointSummary_matchpoint
            __typename
          }
          conversions {
            ...PointSummary_matchpoint
            __typename
          }
          penaltyGoals {
            ...PointSummary_matchpoint
            __typename
          }
          fieldGoals {
            ...PointSummary_matchpoint
            __typename
          }
          __typename
        }
        playSummary {
          id
          attack {
            ...MatchPlaySummary_matchplaystat
            __typename
          }
          defence {
            ...MatchPlaySummary_matchplaystat
            __typename
          }
          kicking {
            ...MatchPlaySummary_matchplaystat
            __typename
          }
          breakdown {
            ...MatchPlaySummary_matchplaystat
            __typename
          }
          setPlay {
            ...MatchPlaySummary_matchplaystat
            __typename
          }
          possession {
            ...MatchPlaySummary_matchplaystat
            __typename
          }
          discipline {
            ...MatchPlaySummary_matchplaystat
            __typename
          }
          __typename
        }
        __typename
      }
      allSeasonStat(comp: \$comp) {
        ...MatchPlaySummary_matchplaystat
        __typename
      }
    }

    fragment Fixtures_fixture on FixtureItem {
      id
      compId
      compName
      dateTime
      group
      isLive
      isBye
      round
      roundType
      roundLabel
      season
      status
      venue
      sourceType
      matchLabel
      homeTeam {
        ...Fixtures_team
        __typename
      }
      awayTeam {
        ...Fixtures_team
        __typename
      }
      fixtureMeta {
        ...Fixtures_meta
        __typename
      }
      __typename
    }

    fragment Fixtures_team on Team {
      id
      name
      teamId
      score
      crest
      __typename
    }

    fragment Fixtures_meta on Fixture {
      id
      ticketURL
      ticketsAvailableDate
      isSoldOut
      radioURL
      radioStart
      radioEnd
      streamURL
      streamStart
      streamEnd
      broadcastPartners {
        ...Fixtures_broadcastPartners
        __typename
      }
      __typename
    }

    fragment Fixtures_broadcastPartners on BroadcastPartner {
      id
      name
      link
      photoId
      __typename
    }

    fragment Fixture_MatchCommentary on MatchCommentary {
      id
      minute
      type
      comment
      __typename
    }

    fragment MatchLineup_matchplayer on MatchPlayer {
      id
      name
      position
      shirtNumber
      isHome
      photo {
        id
        url
        alt
        __typename
      }
      link
      captainType
      frontRow
      __typename
    }

    fragment PointSummary_matchpoint on MatchPoint {
      id
      playerName
      isHome
      pointsMinute
      __typename
    }

    fragment MatchPlaySummary_matchplaystat on MatchPlayStat {
      id
      title
      homeValue
      awayValue
      __typename
    }

    fragment MatchStatsSummary_matchreferee on MatchReferee {
      refereeId
      type
      refereeName
      status
      notified
      private
      isActive
      __typename
    }"
  ];

  $response = wp_remote_post($graphql_url, [
    'headers' => [
      'Content-Type' => 'application/json',
    ],
    'body' => wp_json_encode($body),
    'method' => 'POST',
    'timeout' => 30,
  ]);

  if (is_wp_error($response)) {
    error_log('GraphQL Request Error: ' . $response->get_error_message());
  } else {
    $data = json_decode(wp_remote_retrieve_body($response), true);
    echo '<pre>';
    print_r($data);
    echo '</pre>';
  }
}
