<?php
/**
 * @copyright Copyright (C) 2010-2024, the Friendica project
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 */

namespace Friendica\Content;

use DOMDocument;
use DOMXPath;
use Exception;
use Friendica\Core\Cache\Enum\Duration;
use Friendica\Core\Hook;
use Friendica\Core\Renderer;
use Friendica\Database\Database;
use Friendica\Database\DBA;
use Friendica\DI;
use Friendica\Model\Post;
use Friendica\Network\HTTPClient\Client\HttpClientAccept;
use Friendica\Util\DateTimeFormat;
use Friendica\Util\Network;
use Friendica\Util\Proxy;
use Friendica\Util\Strings;

/**
 * Handles all OEmbed content fetching and replacement
 *
 * OEmbed is a standard used to allow an embedded representation of a URL on
 * third party sites
 *
 * @see https://oembed.com
 */
class OEmbed
{
	/**
	 * Get data from an URL to embed its content.
	 *
	 * @param string $embedurl     The URL from which the data should be fetched.
	 *
	 * @return \Friendica\Object\OEmbed
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 */
	public static function fetchURL(string $embedurl): \Friendica\Object\OEmbed
	{
		$embedurl = trim($embedurl, '\'"');

		$a = DI::app();

		$cache_key = 'oembed:' . $a->getThemeInfoValue('videowidth') . ':' . $embedurl;

		$condition = ['url' => Strings::normaliseLink($embedurl), 'maxwidth' => $a->getThemeInfoValue('videowidth')];
		$oembed_record = DBA::selectFirst('oembed', ['content'], $condition);
		if (DBA::isResult($oembed_record)) {
			$json_string = $oembed_record['content'];
		} else {
			$json_string = DI::cache()->get($cache_key);
		}

		// These media files should now be caught in bbcode.php
		// left here as a fallback in case this is called from another source
		$noexts = ['mp3', 'mp4', 'ogg', 'ogv', 'oga', 'ogm', 'webm'];
		$ext = pathinfo(strtolower($embedurl), PATHINFO_EXTENSION);

		$oembed = new \Friendica\Object\OEmbed($embedurl);

		if ($json_string) {
			$oembed->parseJSON($json_string);
		} else {
			$json_string = '';

			if (!in_array($ext, $noexts)) {
				// try oembed autodiscovery
				$html_text = DI::httpClient()->fetch($embedurl, HttpClientAccept::HTML, 15);
				if (!empty($html_text)) {
					$dom = new DOMDocument();
					if (@$dom->loadHTML($html_text)) {
						$xpath = new DOMXPath($dom);
						foreach (
							$xpath->query("//link[@type='application/json+oembed'] | //link[@type='text/json+oembed']")
							as $link)
						{
							$href = $link->getAttributeNode('href')->nodeValue;
							// Both Youtube and Vimeo output OEmbed endpoint URL with HTTP
							// but their OEmbed endpoint is only accessible by HTTPS ¯\_(ツ)_/¯
							$href = str_replace(['http://www.youtube.com/', 'http://player.vimeo.com/'],
								['https://www.youtube.com/', 'https://player.vimeo.com/'], $href);
							$result = DI::httpClient()->fetchFull($href . '&maxwidth=' . $a->getThemeInfoValue('videowidth'));
							if ($result->isSuccess()) {
								$json_string = $result->getBodyString();
								break;
							}
						}
					}
				}
			}

			$json_string = trim($json_string);

			if (!$json_string || $json_string[0] != '{') {
				$json_string = '{"type":"error"}';
			}

			$oembed->parseJSON($json_string);

			if (!empty($oembed->type) && $oembed->type != 'error') {
				DBA::insert('oembed', [
					'url' => Strings::normaliseLink($embedurl),
					'maxwidth' => $a->getThemeInfoValue('videowidth'),
					'content' => $json_string,
					'created' => DateTimeFormat::utcNow()
				], Database::INSERT_UPDATE);
				$cache_ttl = Duration::DAY;
			} else {
				$cache_ttl = Duration::FIVE_MINUTES;
			}

			DI::cache()->set($cache_key, $json_string, $cache_ttl);
		}

		// Always embed the SSL version
		if (!empty($oembed->html)) {
			$oembed->html = str_replace(['http://www.youtube.com/', 'http://player.vimeo.com/'], ['https://www.youtube.com/', 'https://player.vimeo.com/'], $oembed->html);
		}

		Hook::callAll('oembed_fetch_url', $embedurl, $oembed);

		return $oembed;
	}

	/**
	 * Returns a formatted string from OEmbed object
	 *
	 * @param \Friendica\Object\OEmbed $oembed
	 * @param int $uriid
	 * @return string
	 */
	private static function formatObject(\Friendica\Object\OEmbed $oembed, int $uriid): string
	{
		$ret = '<div class="oembed ' . $oembed->type . '">';

		if (!self::isAllowedURL($oembed->url)) {
			$oembed->html = '';
			if ($oembed->type == 'rich') {
				$oembed->type = 'link';
			}
		}

		switch ($oembed->type) {
			case 'video':
				if ($oembed->thumbnail_url) {
					$tw = (isset($oembed->thumbnail_width) && intval($oembed->thumbnail_width)) ? $oembed->thumbnail_width : 200;
					$th = (isset($oembed->thumbnail_height) && intval($oembed->thumbnail_height)) ? $oembed->thumbnail_height : 180;
					// make sure we don't attempt divide by zero, fallback is a 1:1 ratio
					$tr = (($th) ? $tw / $th : 1);

					$th = 120;
					$tw = $th * $tr;
					$tpl = Renderer::getMarkupTemplate('oembed_video.tpl');
					$ret .= Renderer::replaceMacros($tpl, [
						'$embedurl' => $oembed->embed_url,
						'$escapedhtml' => base64_encode($oembed->html),
						'$tw' => $tw,
						'$th' => $th,
						'$turl' => Post\Link::getByLink($uriid, $oembed->thumbnail_url, Proxy::SIZE_MEDIUM),
					]);
				} else {
					$ret .= Proxy::proxifyHtml($oembed->html);
				}
				break;

			case 'photo':
				$ret .= '<img width="' . $oembed->width . '" src="' . Post\Link::getByLink($uriid, $oembed->url, Proxy::SIZE_MEDIUM) . '">';
				break;

			case 'link':
				break;

			case 'rich':
				$ret .= Proxy::proxifyHtml($oembed->html);
				break;
		}

		// add link to source if not present in "rich" type
		if ($oembed->type != 'rich' || !strpos($oembed->html, $oembed->embed_url)) {
			$ret .= '<h4>';
			if (!empty($oembed->title)) {
				if (!empty($oembed->provider_name)) {
					$ret .= $oembed->provider_name . ": ";
				}

				$ret .= '<a href="' . $oembed->embed_url . '" rel="oembed">' . $oembed->title . '</a>';
				if (!empty($oembed->author_name)) {
					$ret .= ' (' . $oembed->author_name . ')';
				}
			} elseif (!empty($oembed->provider_name) || !empty($oembed->author_name)) {
				$embedlink = "";
				if (!empty($oembed->provider_name)) {
					$embedlink .= $oembed->provider_name;
				}

				if (!empty($oembed->author_name)) {
					if ($embedlink != "") {
						$embedlink .= ": ";
					}

					$embedlink .= $oembed->author_name;
				}
				if (trim($embedlink) == "") {
					$embedlink = $oembed->embed_url;
				}

				$ret .= '<a href="' . $oembed->embed_url . '" rel="oembed">' . $embedlink . '</a>';
			} else {
				$ret .= '<a href="' . $oembed->embed_url . '" rel="oembed">' . $oembed->embed_url . '</a>';
			}
			$ret .= "</h4>";
			if ($oembed->type == 'link') {
				if (!empty($oembed->thumbnail_url)) {
					$ret .= '<img width="' . $oembed->width . '" src="' . Post\Link::getByLink($uriid, $oembed->thumbnail_url, Proxy::SIZE_MEDIUM) . '">';
				}
				if (!empty($oembed->description)) {
					$ret .= '<p>' . $oembed->description . '</p>';
				}
			}
		} elseif (!strpos($oembed->html, $oembed->embed_url)) {
			// add <a> for html2bbcode conversion
			$ret .= '<a href="' . $oembed->embed_url . '" rel="oembed">' . $oembed->title . '</a>';
		}

		$ret .= '</div>';

		return str_replace("\n", "", $ret);
	}

	/**
	 * Converts BBCode to HTML code
	 *
	 * @param string $text
	 * @param int    $uriid
	 * @return string
	 */
	public static function BBCode2HTML(string $text, int $uriid): string
	{
		if (!preg_match_all("/\[embed\](.+?)\[\/embed\]/is", $text, $matches, PREG_SET_ORDER)) {
			return $text;
		}

		foreach ($matches as $match) {
			$data = self::fetchURL($match[1]);
			$text = str_replace($match[0], self::formatObject($data, $uriid), $text);
		}
		return $text; 
	}

	/**
	 * Determines if rich content OEmbed is allowed for the provided URL
	 *
	 * @param string $url
	 * @return boolean
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 */
	private static function isAllowedURL(string $url): bool
	{
		if (!DI::config()->get('system', 'no_oembed_rich_content')) {
			return true;
		}

		$domain = parse_url($url, PHP_URL_HOST);
		if (empty($domain)) {
			return false;
		}

		$str_allowed = DI::config()->get('system', 'allowed_oembed', '');
		if (empty($str_allowed)) {
			return false;
		}

		$allowed = explode(',', $str_allowed);

		return Network::isDomainMatch($domain, $allowed);
	}

	/**
	 * Returns a formatted HTML code from given URL and sets optional title
	 *
	 * @param string $url URL to fetch
	 * @param string $title title (default: what comes from OEmbed object)
	 * @param int    $uriid
	 * @return string Formatted HTML
	 */
	public static function getHTML(string $url, string $title, int $uriid): string
	{
		$o = self::fetchURL($url);

		if (!is_object($o) || property_exists($o, 'type') && $o->type == 'error') {
			throw new Exception('OEmbed failed for URL: ' . $url);
		}

		if (!empty($title)) {
			$o->title = $title;
		}

		$html = self::formatObject($o, $uriid);

		return $html;
	}
}
