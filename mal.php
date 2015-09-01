<?php
	class mal 
	{
		// CHANGE YOUR USERAGENT TO A MAL-ADMIN-APPROVED WHITELISTED USERAGENT OR YOU WILL BE BLOCKED BY THEIR ANTI-SCRAPING SCRIPTS
		static function get($url, $ua = "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:17.0) Gecko/17.0 Firefox/17.0") {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_ENCODING, "");
			curl_setopt($ch, CURLOPT_USERAGENT, $ua);
			curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
			$e = curl_exec($ch);
			curl_close($ch);
			return $e;
		}
		
		static function synopsisFixer($qu) {
			$html = '';
			foreach($qu->childNodes as $child) {
				$tmp = new DOMDocument();
				$tmp->appendChild($tmp->importNode($child, true));
				$html .= $tmp->saveHTML();
			}
			
			$strip_extras = substr(rtrim($html), 18);
			$strip_extras = strip_tags($strip_extras, "<br>");
			$strip_extras = str_replace("Click here\n to update this information.", "", $strip_extras);
			
			return $strip_extras;
		}
	
		static function malStatusToNum($status) {
			$status = strtolower($status);
			
			if(strpos($status, 'finish') !== FALSE) {
				return 2;
			} elseif(strpos($status, 'current') !== FALSE) {
				return 1;
			}
			
			return 0;
		}
	
		static function pulseAnime($id, $json = true) {
			$raw = mal::get("http://myanimelist.net/anime.php?id=$id");
			
			if($raw == FALSE || empty($raw)) return FALSE;
			
			if(strpos($raw, '<div class="badresult">No series found, check the series id and try again.</div>') !== FALSE) { 
				return FALSE; // Series removed or does not exist
			}
			
			$result = (object) array();
			$result->other_titles = (object) array();
			$result->producers = array();
			
			$DOM = new DOMDocument;
			
			libxml_use_internal_errors(true);
			
			if($DOM->loadHTML('<?xml encoding="UTF-8">' . $raw)) {
				$DOM->encoding = 'UTF-8';
				
				$x = new DOMXPath($DOM);
				
				$result->synopsis = mal::synopsisFixer($x->query('//td[@valign="top"]')->item(2));
				
				$titleTag = $x->query('//title')->item(0)->nodeValue;
				$titleTag = explode(' - MyAnimeList.net', $titleTag);
				$titleTag = $titleTag[0];
				
				$imgAttr = $x->query('//td[@class=\'borderClass\']')->item(0)->childNodes->item(0)->childNodes->item(0);
				
				if($imgAttr->nodeName == 'a') {
					$imgAttr = $imgAttr->childNodes->item(0);
				}
				
				$result->title = $titleTag;
				$result->image_url = $imgAttr->getAttribute('src');
				
				$sidebarQuery = $x->query('//td[@class="borderClass"]')->item(0);
				
				foreach($sidebarQuery->childNodes as $child) {
					if(method_exists($child, 'getAttribute') && $child->tagName == 'div' && method_exists($child->firstChild, 'getAttribute') && $child->firstChild->getAttribute('class') == 'dark_text') {
						$sup = $child->getElementsByTagName('sup');
						
						foreach($sup as $s) {
							$child->removeChild($s);
						}
					
						$bardata = explode(':', $child->nodeValue);
						
						if(empty($bardata) || count($bardata) == 1)
							continue;
							
						$typeName = strtolower($bardata[0]);
						
						array_shift($bardata);
						
						$typeValue = rtrim(ltrim(implode(':', $bardata)));
						
						if($typeName == 'producers') {
							foreach($child->childNodes as $producerNode) {
								if(isset($producerNode->tagName) && $producerNode->tagName == 'a' && (strpos(strtolower($producerNode->nodeValue), 'add some') === FALSE)) {
									array_push($result->producers, $producerNode->nodeValue);
								}
							}
						} elseif($typeName == 'synonyms' || $typeName == 'japanese' || $typeName == 'english') {
							$result->other_titles->{$typeName} = explode(', ', $typeValue);
						} elseif($typeName == 'genres') {
							$result->{$typeName} = explode(', ', $typeValue);
						} elseif($typeName == 'score') {
							$result->{$typeName} = (float) $typeValue;
						} elseif($typeName == 'ranked' || $typeName == 'popularity') {
							$result->{$typeName} = intval(substr($typeValue, 1));
						} elseif($typeName == 'members' || $typeName == 'favorites') {
							$result->{$typeName} = intval(str_replace(',', '', $typeValue));
						} else {
							$result->{$typeName} = $typeValue;
						}
					}
				}
			}
			
			if($json === true) {
				$result = json_encode($result, true);
			}
			
			return $result;
		}
	};
?>
