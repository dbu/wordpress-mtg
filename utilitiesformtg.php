<?php
/*
Plugin Name: Utilities for MTG
Description: Small plugin to display Magic The Gathering cards in images or links. Can generate 15 card booster packs from a csv file to be displayed. Display your cube in style!
Version:     1.2.0
Author:      Martin Ekström
*/

add_action('wp_enqueue_scripts', 'add_styles');
function add_styles() {
    wp_register_style('utilitiesformtgcss', plugins_url('styles.css',__FILE__));
    wp_enqueue_style('utilitiesformtgcss');

    wp_register_script('utilitiesformtgjs', plugins_url('utilitiesformtg.js',__FILE__));
    wp_enqueue_script('utilitiesformtgjs');
}

function ufmtg_call_api($method, $url, $data = false)
{
    usleep (300); //Scryfall wants delays between requests not to overload server

    $result = wp_remote_retrieve_body(wp_remote_get($url));

    $parsed_result = json_decode($result, true);
    if (isset($parsed_result['status'])) {
        if ($parsed_result['status'] == 404)
            return "Card not found.";
    }

    return $result;
}

function ufmtg_get_set_from_scryfall($set_code) {
    $search_url = "https://api.scryfall.com/cards/search?unique=cards&q=e%3A" . $set_code . "+is%3Abooster+-t%3Abasic";

    $result = ufmtg_call_api("get", $search_url);
    $parsed_result = json_decode($result, true);

    if (isset($parsed_result['has_more'])) {
        $has_more_result = ufmtg_call_api("get", $parsed_result['next_page']);
        $parsed_more_result = json_decode($has_more_result, true);
        return array_merge($parsed_result['data'], $parsed_more_result['data']);
    }

    return $parsed_result['data'];
}

/**
 * This function can extract strings from a string by sending in what is before and after said string.
 * Used to extract all [scrylink]card[/scrylink] tags in a html table to send a request and cache the cards beforehand.
 *
 * extractCardsFromString(htmlTable, "[scrylink]", "[/scrylink]")
 */
function extractCardsFromString($string, $start, $slut) {
    $cards = explode("|", $string);

    $search_url = "https://api.scryfall.com/cards/search?unique=cards&q=";

    $cards_list = [];
    foreach ($cards as $card) {
        if (strpos($card, ':')) {
            $cardnameAndSet = explode(':', $card);
            $cards_list[] = $cardnameAndSet[0] . "+e%3A" . $cardnameAndSet[1];
        } else {
            $cards_list[] = $card;
        }
    }
    $cards_to_add = '!'.implode('+or+!', $cards_list);
    $search_url .= $cards_to_add;
    $search_url = str_replace(array(' ', "'"), '', $search_url);

    $result = ufmtg_call_api("get", $search_url);
    $parsed_result = json_decode($result, true);
    $data = $parsed_result['data'];

    wp_cache_set("ufmtg_precached", $data);
}


/**
 * This function is made to list an amount of cards by asking for them in a list separated by :
 *
 * [p1p1deck]serra angel:cancel:grunn, the lonely king:disfigure[/p1p1deck]
 */
function ufmtg_build_deck($atts, $content = null) {
    return ufmtg_get_list_from_scryfall($content);
}

/**
 * Render a decklist with quantities and groups.
 *
 * [decklist]
 * // Creatures
 * 2 serra angel
 * 2 grunn, the lonely king
 * // Spells
 * cancel
 * // Sideboard
 * disfigure
 * [/decklist]
 *
 * Sections are denoted by //, numbers are optional
 */
function ufmtg_build_decklist($atts, $content = null)
{
    // map of card name => positions in decklist (same card can be in main and sideboard)
    $cards = [];
    // deck is section name => [cards]
    $sections = [];
    $content = str_replace(['<br />', '<br/>', '<br>', '<p>', '</p>'], "\n", $content);
    $content = strip_tags($content);
    if (0 !== strncmp('//', $content, 2)) {
        $sectionName = 'Deck';
        $sectionCount = 0;
        $sectionCards = [];
    }

    foreach (explode("\n", $content) as $pos => $row) {
        $pos = 'p'.$pos; // prevent php making the index numeric which would break all pointers when we array_unshift.
        $row = trim($row);
        if ('' === $row) continue;
        if (0 === strncmp('//', $row, 2)) {
            if (count($sectionCards)) {
                array_unshift($sectionCards, $sectionName.' ('.$sectionCount.')');
                $sections[$sectionName] = $sectionCards;
                $sectionCards = [];
            }
            $sectionName = trim(substr($row, 2));
            $sectionCount = 0;
        } else {
            $matches = [];
            preg_match('/(\d?)\s*(.+)/', $row, $matches);
            $cardName = $matches[2];
            $amount = $matches[1];
            $sectionCards[$pos] = '<div class="cardAmountWrap">'.('' === $amount ? '' : $amount.' ');
            $cards[strtolower($cardName)][] = ['section' => $sectionName, 'pos' => $pos];
            $sectionCount += $amount ?: 1;
        }
    }
    if (count($sectionCards)) {
        array_unshift($sectionCards, $sectionName.' ('.$sectionCount.')');
        $sections[$sectionName] = $sectionCards;
    }

    $data = ufmtg_get_data_from_scryfall(array_keys($cards), true);
    foreach ($data as $card) {
        $key = strtolower($card['name']);
        $addresses = [];
        if (array_key_exists($key, $cards)) {
            $addresses = $cards[$key];
            unset($cards[$key]);
        } else {
            if (false !== strpos($key, '//')) {
                $frontKey = trim(substr($key, 0, strpos($key, '//')));
                if (array_key_exists($frontKey, $cards)) {
                    $addresses = $cards[$frontKey];
                    unset($cards[$frontKey]);
                }
            }

            // still not found?
            if (!$addresses) {
                $sections['Error'][] = 'Error with your decklist for card: '.$key;
            }
        }

        $link = ufmtg_build_link($card);
        foreach ($addresses as $address) {
            $sections[$address['section']][$address['pos']] .= $link.'</div>';
        }
    }
    // if there are any entries left in $cards, we did not find them on scryfall
    if (count($cards)) {
        $notFound = array_keys($cards);
        array_unshift($notFound, 'Not Found');
        $sections['Not Found'] = $notFound;
    }

    $deckHtml = '<div class="deck">';
    foreach ($sections as $section) {
        $heading = array_shift($section);
        $deckHtml .= '<div class="deck-section"><h3>'.$heading.'</h3>';
        $deckHtml .= implode("\n", $section);
        $deckHtml .= '</div>';
    }

    return $deckHtml.'</div>';
}

/**
 * Function to get image html for a list of cards separated by :
 */
function ufmtg_get_list_from_scryfall($cardlist, $exact = false) {
    if (is_array($cardlist)) {
        $cards = $cardlist;
    } else {
        $cards = explode(":", $cardlist);
    }

    $data = ufmtg_get_data_from_scryfall($cards, $exact);

    $cardimages = "";
    if (is_array($data)) {
        foreach ($data as $card) {
            $cardimages .= ufmtg_build_img($card);
        }
    }

    return $cardimages;
}

function ufmtg_get_data_from_scryfall(array $cards, $exact)
{
    $search_url = "https://api.scryfall.com/cards/search?unique=cards&q=";

    $glue = '+or+';
    if ($exact) {
        $glue .= '!';
    }
    $card_list = implode($glue, $cards);
    if ($exact) {
        $card_list = '!'.$card_list;
    }
    $card_list = str_replace(array(' ', "'"), '', $card_list);

    $search_url .= $card_list;

    $result = ufmtg_call_api("get", $search_url);
    $parsed_result = json_decode($result, true);

    return $parsed_result['data'];
}

/**
 * Create a scryfall search query for a card, can be accompanied by a certain set by adding :set in the cardname, f.ex Cancel:xln
 */
function ufmtg_create_scryfall_url($name_of_card) {
    $name = $name_of_card;
    $setCode = "";

    //Check if setcode was given
    if (strpos($name, ':') !== false) {
        $explodedString = explode(":", $name);
        $setCode = $explodedString[1];
        $setCode = "&set=" . $setCode;
    }
    $cardname = str_replace(array(" ", "'"), array("+", ""), $name);
    if (strpos($cardname, ':') !== false) {
        $cardname = substr($cardname, 0, strpos($cardname, ':'));
    }
    return "https://api.scryfall.com/cards/named?fuzzy=$cardname$setCode";
}

/**
 * Get a cached card from the wordpress cache
 */
function ufmtg_get_cached_card($name_of_card) {
    //Check if setcode was given
    $set_code = "";
    $cardname = "";
    $set = array();
    if (strpos($name_of_card, ':') !== false) {
        $explodedString = explode(":", $name_of_card);
        $set_code = $explodedString[1];
        $cardname = $explodedString[0];
    } else {
        $cardname = $name_of_card;
    }
    if (wp_cache_get("ufmtg_precached")) {
        $set = wp_cache_get("ufmtg_precached");
        if (!checkForCardInCache($set, $cardname)) {
            return checkForCardInCache($set, $cardname);
        }
    } elseif ($set_code) {
        if (wp_cache_get($set_code)) {
            $set = wp_cache_get($set_code);
        } else {
            $set = ufmtg_get_set_from_scryfall($set_code);
            wp_cache_set($set_code, $set);
        }
    }

    return checkForCardInCache($set, $cardname);
}

function checkForCardInCache($set, $cardname) {
    $cardfound = "false";
    if (is_array($set)) {
        foreach ($set as $cachedcard) {
            if (isset($cachedcard['name'])) {
                if(stripos($cardname, $cachedcard['name'])!==false) {
                    $cardfound = $cachedcard;
                }
            }
        }
    }
    return $cardfound;
}

/**
 * Create the html element for a card image
 */
function ufmtg_build_img($inputcard = "", $front = "", $back = "") {
    if ($front !== "" && $back !== "") {
        $id = mt_rand(10000000, 99999999);

        return '<span class="scryfall_hover-zoom--container"><img id="'. $id . '" src="' . $front . '" class="clickable scryfall_hover-zoom" onclick="swapimage('. $id . ',\'' . $front . '\',\'' . $back .'\');" /></span>';
    }
    if ($inputcard['layout'] == "transform") {
        $front = $inputcard['card_faces'][0]['image_uris']['normal'];
        $back = $inputcard['card_faces'][1]['image_uris']['normal'];

        $id = mt_rand(10000000, 99999999);

        return '<span class="scryfall_hover-zoom--container"><img id="'. $id . '" src="' . $front . '" class="clickable scryfall_hover-zoom" onclick="swapimage('. $id . ',\'' . $front . '\',\'' . $back .'\');" /></span>';
    }

    $front = $inputcard['image_uris']['normal'];

    return '<img src="' . $front . '" class="scryfall_hover-zoom" />';
}

/**
 * Create the html element for a card link
 */
function ufmtg_build_link(array $inputcard)
{
    $realcardname = $inputcard['name'];
    $cardlink = $inputcard['scryfall_uri'];
    if (!$cardlink) {
        return 'Card not found';
    }

    if ('transform' === $inputcard['layout']) {
        $front = $inputcard['card_faces'][0]['image_uris']['normal'];
        $back = $inputcard['card_faces'][1]['image_uris']['normal'];

        return '<span class="scryfall_hover_img"><a href="' . $cardlink . '" target="_blank">' . $realcardname . '<span><img src="' . $front . '" class="scryfall_card-hover-size"/><img src="' . $back . '" class="scryfall_card-hover-size"/></span></a></span>';
    }

    $front = $inputcard['image_uris']['normal'];

    return '<span class="scryfall_hover_img"><a href="' . $cardlink . '" target="_blank">' . $realcardname . '<span><img src="' . $front . '" class="scryfall_card-hover-size"/></span></a></span>';
}


/**
 * Function to get an image from scryfall just by typing a cardname
 * [scryimg set="xln"]gishath[scryimg]
 */
function ufmtg_get_scryfall_image($atts, $content = null)
{
    $cardlist = ufmtg_get_cached_card($content);
    if (is_string($cardlist)) {
        $result = ufmtg_call_api("get", ufmtg_create_scryfall_url($content));
        $cardlist = json_decode($result, true);
    }

    return ufmtg_build_img($cardlist);
}

function ufmtg_get_scryfall_link($atts, $content = null)
{
    $cardlist = ufmtg_get_cached_card($content);
    if (is_string($cardlist)) {
        $result = ufmtg_call_api("get", ufmtg_create_scryfall_url($content));
        $cardlist = json_decode($result, true);
    }

    return ufmtg_build_link($cardlist);
}

/**
 * This function is made to extract 15 cards from a list of cards in a CSV file online
 * It needs a url to a CSV file published online and the column number where the cardnames are located
 *
 * [p1p1cube column="2"]https://example.com/mycube.csv[/p1p1cube]
 */
function ufmtg_randomize_card_pack($atts, $content = null) {
    $spreadsheet_url = $content;
    $column = (int)$atts['column'] -1; //minus 1 is to make it more intuitive for users, colum A=1, B=2 etc.
    $cube = array();

    if(!ini_set('default_socket_timeout', 15)) {
        echo "unable to change socket timeout";
    }

    if (($handle = fopen($spreadsheet_url, "r")) !== FALSE) {
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $spreadsheet_data[] = $data;
        }
        fclose($handle);
    } else {
        die("Problem reading csv");
    }

    //Get the cards from the right column of the spreadsheet
    foreach ($spreadsheet_data as $card1) {
        $cube[] = $card1[$column];
    }

    //Shuffle them up so they aren't sorted
    $random_pack = array_rand($cube, 15);
    shuffle($random_pack);

    $cardpack = "";
    foreach($random_pack as $cardnum) {
        $cardpack = $cardpack . $cube[$cardnum] . ":";
    }
    $cardpack = substr($cardpack, 0, -1);

    return ufmtg_get_list_from_scryfall($cardpack, true);
}

/**
 * [mtglink]
 */
function ufmtg_get_mtg_link($atts, $content = null) {
    $cardName = $atts['name'];
    $cardLink = $atts['url'];
    $cardBackSide = $atts['backside'] ?: "";
    if ($cardBackSide !== "") {
        return '<span class="scryfall_hover_img"><a href="' . $cardLink . '" target="_blank">' . $cardName . '<span><img src="' . $content . '" class="scryfall_card-hover-size"/><img src="' . $cardBackSide . '" class="scryfall_card-hover-size"/></span></a></span>';
    }

    return '<span class="scryfall_hover_img"><a href="' . $cardLink . '" target="_blank">' . $cardName . '<span><img src="' . $content . '" class="scryfall_card-hover-size"/></span></a></span>';
}

/**
 * [mtgimg]
 */
function ufmtg_get_mtg_image($atts, $content = null) {
    $cardBackSide = $atts['backside'] ?: "";
    return ufmtg_build_img("", $content, $cardBackSide);
}

function ufmtg_precache_cards($atts, $content = null) {
    extractCardsFromString($content, "[scrylink]", "[/scrylink]");
}

add_shortcode('scryimg', 'ufmtg_get_scryfall_image');
add_shortcode('scrylink', 'ufmtg_get_scryfall_link');
add_shortcode('p1p1cube', 'ufmtg_randomize_card_pack');
add_shortcode('p1p1deck', 'ufmtg_build_deck');
add_shortcode('decklist', 'ufmtg_build_decklist');
add_shortcode('mtgimg', 'ufmtg_get_mtg_image');
add_shortcode('mtglink', 'ufmtg_get_mtg_link');
add_shortcode('mtgprecache', 'ufmtg_precache_cards');
