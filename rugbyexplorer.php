<?php

/**
 * Plugin Name: RugbyExplorer Temporary
 * Description: Player lineup and Team ladder shortcode.
 * Version: 1.0
 * Author: Jethrolanda
 * Author URI: jethrolanda.com
 * Text Domain: rugbyexplorer-temp
 * Domain Path: /languages/
 * Requires at least: 5.7
 * Requires PHP: 7.2
 */

defined('ABSPATH') || exit;

// 1️⃣ Add the meta box Game Fixture ID
add_action('add_meta_boxes', function () {
  add_meta_box(
    'game_fixture_id',            // ID
    'Fixture ID',                 // Title
    'render_game_fixture_id',     // Callback
    'sp_event',                   // Post type slug (change this)
    'normal',                     // Context ('normal', 'side', 'advanced')
    'default'                     // Priority
  );
});

// 2️⃣ Render the meta box HTML
function render_game_fixture_id()
{
  // Security nonce
  wp_nonce_field('save_match_details', 'match_details_nonce');

  // Get saved values
  global $post;
  $fixture_id = get_post_meta($post->ID, 'fixture_id', true);
?>

  <p>
    <label for="fixture_id"><strong>Fixture ID:</strong></label><br>
    <input
      type="text"
      id="fixture_id"
      name="fixture_id"
      value="<?php echo esc_attr($fixture_id); ?>"
      style="width:100%;"
      placeholder="e.g. bd555ab34f689975d" />
  </p>

<?php
}

// 3️⃣ Save the meta box data
add_action('save_post_sp_event', function ($post_id) {

  // Verify nonce
  if (
    !isset($_POST['match_details_nonce']) ||
    !wp_verify_nonce($_POST['match_details_nonce'], 'save_match_details')
  ) {
    return;
  }

  // Check autosave or permissions
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (!current_user_can('edit_post', $post_id)) return;


  // Sanitize & save
  if (isset($_POST['fixture_id'])) {

    update_post_meta($post_id, 'fixture_id', sanitize_text_field($_POST['fixture_id']));
  }
});

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
    'fixture_id' => get_post_meta(get_the_ID(), 'fixture_id', true)
  );
  $data = getPlayerLineUpData($args);

  if (!empty($data['allMatchStatsSummary'])) {
    require_once('player-lineup-view.php');
  }


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
        "fixture" => $fixture_id,
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

    return $data['data'];
    // echo '<pre>';
    // print_r($data);
    // echo '</pre>';
  }
}

/* TEAM LADDER / COMPETITION LADDER */
// Add field to Add New screen
add_action('sp_league_add_form_fields', function () {
?>
  <div class="form-field">
    <label for="competition_id">Competition ID</label>
    <input type="text" name="competition_id" id="competition_id" value="" placeholder="ex: mLGoqgHnacX2AnmgD">
    <p class="description">You can find this in the xplorer.rugby url parameters ex: comp=mLGoqgHnacX2AnmgD</p>
  </div>
<?php
});

// Add field to Edit screen
add_action('sp_league_edit_form_fields', function ($term) {
  $competition_id = get_term_meta($term->term_id, 'competition_id', true);
?>
  <tr class="form-field">
    <th scope="row">
      <label for="competition_id">Competition ID</label>
    </th>
    <td>
      <input type="text" name="competition_id" id="competition_id" placeholder="ex: mLGoqgHnacX2AnmgD" value="<?php echo esc_attr($competition_id); ?>">
      <p class="description">You can find this in the xplorer.rugby url parameters ex: comp=mLGoqgHnacX2AnmgD</p>
    </td>
  </tr>
<?php
});

add_action('created_sp_league', 'save_competition_meta');
add_action('edited_sp_league', 'save_competition_meta');

function save_competition_meta($term_id)
{
  if (isset($_POST['competition_id'])) {
    update_term_meta($term_id, 'competition_id', sanitize_text_field($_POST['competition_id']));
  }
}

add_shortcode('team_ladder', 'team_ladder');

function team_ladder($atts)
{
  $atts = shortcode_atts(array(
    'season_id' => '',
    'competition_id' => ''
  ), $atts, 'fusesports_fixtures');


  $terms = get_the_terms(get_the_ID(), 'sp_league');
  if (!empty($terms)) {
    $competition_id = get_term_meta($terms[0]->term_id, 'competition_id', true);
  }


  ob_start();

  $args = array(
    'competition_id' => $competition_id
  );
  $data = getCompetitionLadderData($args);

  if (!empty($data['ladderPools'])) {
    require_once('team-ladder-view.php');
  }


  // content
  return ob_get_clean();
}

function getCompetitionLadderData($args)
{
  extract($args);

  $graphql_url = 'https://rugby-au-cms.graphcdn.app/';

  $body = [
    "operationName" => "CompLadderQuery",
    "variables" => [
      "comp" => [
        "id" => $competition_id,
        "sourceType" => "2"
      ]
    ],
    "query" => 'query CompLadderQuery($comp: CompInput) {
      compLadder(comp: $comp) {
        id
        hasPools
        ladderPools {
          id
          poolName
          teams {
            id
            name
            position
            totalMatchPoints
            matchesPlayed
            matchesWon
            matchesLost
            matchesDrawn
            pointsFor
            pointsAgainst
            pointsDifference
            crest
            bonusPoints4T
          }
        }
      }
    }'
  ];

  $args = [
    'body' => json_encode($body),
    'headers' => [
      'Content-Type' => 'application/json',
    ],
    'method' => 'POST',
    'data_format' => 'body',
  ];

  $response = wp_remote_post($graphql_url, $args);

  if (is_wp_error($response)) {
    error_log('GraphQL Request Error: ' . $response->get_error_message());
  } else {
    $data = json_decode(wp_remote_retrieve_body($response), true);

    return $data['data']['compLadder'];
    // echo '<pre>';
    // print_r($data);
    // echo '</pre>';
  }
}
