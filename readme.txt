=== Utilities for MTG ===
Contributors: Yunra
Tags: scryfall, mtg, magic, tcg
Donate link: https://www.patreon.com/yunra
Requires at least: 4.9.8
Tested up to: 5.2
Stable tag: 1.2.0
Requires PHP: 5.5.4

Get links and pictures of cards just by typing the cards name.

== Description ==
This plugin is made to spice up your pages with cards from Magic The Gathering.
When you talk about a card in a blogpost it is always nice to display it, now you can easily do that without having to find pictures and create links etc, just type the name in one of the available tags!

It is originally created for thepaupercube.com

== Screenshots ==
1. Displayed cards
2. Hovering with the cursor over one card will zoom it

== Installation ==
Just download and activate the plugin and start using the tags in your pages. No additional configuration or setup needed.

== Usage ==
[scryimg]cardname:set[/scryimg] results in a picture of the card (set is not required)

[scrylink]cardname:set[/scrylink] results in a link to the card on scryfall with an image of the card on hover

[p1p1cube column="2"]http://example.com/mycube.csv[/p1p1cube] will generate 15 cards from the X (where X in this example is 2) column of a csv file (Column B in a google spreadsheet f.ex). In order for this to work, the link between the tags need to be a direct link to a published CSV file.

[p1p1deck]gishath:ghost quarter:grunn, the lonely king:serra angel:cancel[/p1p1deck] - Make a list of cardimages by typing in the names of cards separated by :

[decklist] - Format a list of cards (like [scrylink]). Sections start with "//", amount of cards is optional.
[decklist]
// Lands
2 Karakas
2 City of Traitors
4 Ancient Tomb
// Creatures
3 Matter Reshaper
3 Endbringer
4 Thought-Knot Seer
1 Kozilek, the Great Distortion
// Spells
4 Chalice of the Void
2 Karn, Scion of Urza

// Sideboard
2 Ratchet Bomb
[/decklist]

[mtgimg backside="https://url-to-an-image.com/image.png"]https://url-to-an-image.com/image.png[/mtgimg] - requires an image url between the tags. Can take a second image and use as the backside on f.ex a flipcards.

[mtglink url="https://url-to.link" name="Displayed name"]https://url-to-an-image.com/image.png[/mtglink] - requires an image url between the tags, a name as parameter and a url as parameter. Can take a second image and use as the backside on f.ex flipcards.

[mtgprecache]card|card:set|card|card[/mtgprecache] - Used to pre-cache cards to speed up loading of page. Requires perfect spelling of cardnames.

== Changelog ==

= unreleased =
* Added [decklist] tag for full decklists. The list is rendered with each card like a [scrylink].

= 1.1.1 =
* Fixed a bug where the precache did not accept setcode

= 1.1.0 =
* New shortcode [mtgprecache]card|card|card|card[/mtgprecache] - Used to pre-cache cards to speed up loading of page. Requires perfect spelling of cardnames.

= 1.0.0 =
* Fixes for release to Wordpress Plugins section

= 0.9.1 =
* Fixed display of double faced cards, they are now displayed as a single image and when clicked it will show the backside of the card.
* Fixed a bug where the name matcher was too forgiving and let everything with similair names through.

= 0.9 =
* Added caching if cardnames end with a set tag so if there are more than 1 card from a certain set on a page, the page should load faster since the set is cached and it can take cards from the cache instead.

= 0.8 =
* Fixed loading times of p1p1cube.
* Added new tag [p1p1deck] - Make a list of predefined card images by typing names of cards between the tags, separated by colon (:).

== Additional Info ==

* This plugin is using the Scryfall API (http://www.scryfall.com) for card data and images. Data is not modified and can be found and viewed in full on Scryfall.com. Scryfall TOS: https://scryfall.com/docs/terms

== Credit ==
* Thanks to Adam at thepaupercube.com for using, testing and providing feedback
