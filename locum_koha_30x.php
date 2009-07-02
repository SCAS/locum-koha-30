<?php

/**
 * Locum Koha Connector is a Locum Connector for Koha
 *
 * Copyright 2009 SARL BibLibre
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * Koha is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE.  See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * Koha; if not, write to the Free Software Foundation, Inc., 59 Temple Place,
 * Suite 330, Boston, MA  02111-1307 USA
 */

class locum_koha_30x {

	/**
	 * Grabs bib info from OAI-PMH and returns it in a Locum-ready array.
	 *
	 * @param int $bnum Bib number to scrape
	 * @param boolean $skip_cover Forget about grabbing cover images.  Default: FALSE
	 * @return boolean|array Will either return a Locum-ready array or FALSE
	 */
	public function scrape_bib($bnum, $skip_cover = FALSE) {
		$server = $this->locum_config[ils_config][ils_server];
		$port = $this->locum_config[ils_config][ils_harvest_port];
		
		//$url = 'http://'.$server.':'.$port.'/cgi-bin/koha/oai.pl?verb=GetRecord&metadataPrefix=marcxml&identifier=KOHA-OAI-TEST:' . $bnum;
		//$xml = @simplexml_load_file($url);
		
		//$bib_info_marc = self::parse_marc_subfields($xml->GetRecord->record->metadata->marcxml);
		
		$url = 'http://'.$server.':'.$port.'/cgi-bin/koha/ilsdi.pl?service=GetRecords&id=' . $bnum;
		$xml = @simplexml_load_file($url);

		$bib_info_marc = self::parse_marc_subfields($xml->record->marcxml->record);
		
		// Process record information
		$bib[bnum]           = (int) $bnum;
		$bib[bib_created]    = "2000-10-10"; # not supported by OAI-PMH ?
		//$bib[bib_lastupdate] = substr($xml->GetRecord->record->header->datestamp, 0, 10);
		$bib[bib_lastupdate] = "2000-10-10";
		$bib[bib_prevupdate] = "2000-10-10"; # not supported by koha ?
		$bib[bib_revs]       = 1;            # not supported by koha ?
		
		unset($xml);
		
		// Process MARC fields
		
		$bib[loc_code] = '1';
		$loc_code = self::prepare_marc_values($bib_info_marc['995'], array('e'));
		$bib[loc_code] = $loc_code[0];
		
		// Material Code
		$bib[mat_code] = 'LITT';
		$mat_code = self::prepare_marc_values($bib_info_marc['200'], array('b'));
		$bib[mat_code] = $mat_code[0];

		// Process Author information
		$bib[author] = '';
		$author = self::prepare_marc_values($bib_info_marc['200'], array('f'));
		$bib[author] = $author[0];
		
		// Additional author information
		$bib[addl_author] = '';
		
		// Title information
		$bib[title] = '';
		$title = self::prepare_marc_values($bib_info_marc['200'], array('a'));
		$bib[title] = $title[0];
		
		// Title medium information
		$bib[title_medium] = '';
		
		// Edition information
		$bib[edition] = '';
		$edition = self::prepare_marc_values($bib_info_marc['210'], array('c'));
		$bib[edition] = $edition[0];
		
		// Series information
		$bib[series] = '';
		$series = self::prepare_marc_values($bib_info_marc['225'], array('a'));
		$bib[series] = $series[0];
		
		// Call number
		$bib[callnum] = '';
		$callnum = self::prepare_marc_values($bib_info_marc['995'], array('k'));
		$bib[callnum] = $callnum[0];
		
		// Publication information
		$bib[pub_info] = '';
		
		// Publication year
		$bib[pub_year] = '';
		$pub_year = self::prepare_marc_values($bib_info_marc['210'], array('d'));
		$bib[pub_year] = $pub_year[0];
		
		// ISBN / Std. number
		$bib[stdnum] = '';
		$stdnum = self::prepare_marc_values($bib_info_marc['010'], array('a'));
		$bib[stdnum] = $stdnum[0];
		
		// Grab the cover image URL if we're doing that
		$bib[cover_img] = '';
		if ( ! $skip_cover) {
			if ($bib[stdnum]) { $bib[cover_img] = locum_server::get_cover_img($bib[stdnum]); }
		}
		
		// LCCN
		$bib[lccn] = '';
		$lccn = self::prepare_marc_values($bib_info_marc['680'], array('a'));
		$bib[lccn] = $lccn[0];
		
		// Description
		$bib[descr] = '';
		
		// Notes
		$bib[notes] = '';
		$notes = self::prepare_marc_values($bib_info_marc['300'], array('a'));
		$bib[notes] = serialize($notes);
		
		// Language
		$bib[lang] = '';
		$stdnum = self::prepare_marc_values($bib_info_marc['101'], array('a'));
		$bib[lang] = $stdnum[0];
		
		// Subject headings
		$subjects = array();
		$subj_tags = array('600', '601', '602', '604', '605', '606', '607', '608', '610', '615', '620');
		foreach ($subj_tags as $subj_tag) {
			$subj_arr = self::prepare_marc_values($bib_info_marc[$subj_tag], array('a','b','c','d','e','v','x','y','z'), ' -- ');
			if (is_array($subj_arr)) {
				foreach ($subj_arr as $subj_arr_val) {
					array_push($subjects, $subj_arr_val);
				}
			}
		}
		$bib[subjects] = '';
		if (count($subjects)) { $bib[subjects] = $subjects; }
		
		unset($bib_info_marc);

		return $bib;
	}

	/**
	 * Parses item status for a particular bib item.
	 *
	 * @param string $bnum Bib number to query
	 * @return array Returns a Locum-ready availability array
	 */
	public function item_status($bnum) {
		$server = $this->locum_config[ils_config][ils_server];
		$port = $this->locum_config[ils_config][ils_harvest_port];
		
		$url = 'http://'.$server.':'.$port.'/cgi-bin/koha/ilsdi.pl?service=GetRecords&id=' . $bnum;
		$xml = @simplexml_load_file($url);
		
		$is[holds]  = (int) count($xml->record->reserves->reserve);
		$is[order]  = (int) $xml->record->order;
		$is[copies] = (int) count($xml->record->items->item) - count($xml->record->issues->issue);
		
		if ($xml->record->items->item) {
			foreach($xml->record->items->item as $item) {
				$cnum     = (string) $item->itemcallnumber;
				$location = (string) $item->holdingbranchname;
				$is[details][$cnum][$location][avail]++;
				foreach($xml->record->issues->issue as $issue) {
					if ((int) $item->itemnumber == (int) $issue->itemnumber) {
						$is[details][$cnum][$location][due][] = $this->date_to_timestamp($issue->date_due);
						$is[details][$cnum][$location][avail]--;
					}
				}
			}
		}
		
		return $is;
	}

	/**
	 * Returns an array of patron information
	 *
	 * @param string $cardnum Patron card number
	 * @return boolean|array Array of patron information or FALSE if login fails
	 */
	public function patron_info($cardnum) {	
		$server = $this->locum_config[ils_config][ils_server];
		$port = $this->locum_config[ils_config][ils_harvest_port];
		
		$patron_id = $this->patron_id($cardnum);
		if ( ! $patron_id) { return FALSE; }
		
		$url = 'http://'.$server.':'.$port.'/cgi-bin/koha/ilsdi.pl?service=GetPatronInfo&patron_id=' . $patron_id;
		$xml = @simplexml_load_file($url);

		$pi[pnum]      = (string) $xml->borrowernumber;
		$pi[cardnum]   = (int)    $xml->cardnumber;
		$pi[checkouts] = (int)    count($this->patron_checkouts($cardnum, NULL));
		$pi[homelib]   = (string) $xml->branchname;
		$pi[balance]   = (float)  $xml->charges;
		$pi[expires]   = (string) $this->date_to_timestamp($xml->dateexpiry);
		$pi[name]      = (string) $xml->firstname . " " . $xml->surname;
		$pi[address]   = (string) $xml->address;
		$pi[tel1]      = (string) $xml->phone;
		if     ($xml->mobile)   { $pi[tel2] = (string) $xml->mobile; }
		elseif ($xml->phonepro) { $pi[tel2] = (string) $xml->phonepro; }
		$pi[email]     = (string) $xml->email;

		return $pi;
	}

	/**
	 * Returns an array of patron checkouts
	 *
	 * @param string $cardnum Patron barcode/card number
	 * @param string $pin Patron pin/password
	 * @return boolean|array Array of patron checkouts or FALSE if login fails
	 */
	public function patron_checkouts($cardnum, $pin = NULL) {
		$server = $this->locum_config[ils_config][ils_server];
		$port = $this->locum_config[ils_config][ils_harvest_port];
		
		$patron_id = $this->patron_id($cardnum);
		if ( ! $patron_id) { return FALSE; }
		
		$url = 'http://'.$server.':'.$port.'/cgi-bin/koha/ilsdi.pl?service=GetPatronInfo&patron_id=' . $patron_id . '&show_loans=1';
		$xml = @simplexml_load_file($url);

		$i = 0;
		foreach($xml->loans->loan as $loan) {
			$pc[$i][varname]   = (int)    $loan->itemnumber;
			$pc[$i][inum]      = (int)    $loan->itemnumber;
			$pc[$i][bnum]      = (int)    $loan->biblionumber;
			$pc[$i][title]     = (string) $loan->title;
			$pc[$i][ill]       = 0;
			$pc[$i][numrenews] = (int)    $loan->renewals;
			$pc[$i][duedate]   = (string) $this->date_to_timestamp($loan->date_due);
			$pc[$i][callnum]   = (string) $loan->itemcallnumber;
			$i++;
		}
		
		return $pc;
	}
	
	/**
	 * Returns an array of patron holds
	 *
	 * @param string $cardnum Patron barcode/card number
	 * @param string $pin Patron pin/password
	 * @return boolean|array Array of patron holds or FALSE if login fails
	 */
	public function patron_holds($cardnum, $pin = NULL) {
		$server = $this->locum_config[ils_config][ils_server];
		$port = $this->locum_config[ils_config][ils_harvest_port];
		
		$patron_id = $this->patron_id($cardnum);
		if ( ! $patron_id) { return FALSE; }
		
		$url = 'http://'.$server.':'.$port.'/cgi-bin/koha/ilsdi.pl?service=GetPatronInfo&patron_id=' . $patron_id . '&show_holds=1';
		$xml = @simplexml_load_file($url);

		$i = 0;
		foreach($xml->holds->hold as $hold) {		
			$ph[$i][varname]    = (int)    $hold->itemnumber;
			$ph[$i][inum]       = (int)    $hold->itemnumber;
			$ph[$i][bnum]       = (int)    $hold->biblionumber;
			$ph[$i][title]      = (string) $hold->title;
			$ph[$i][ill]        = 0;
			if     ($hold->found == 'W') { $ph[$i][status] = "Waiting";  } // the reserve has an itemnumber affected, and is on the way
			elseif ($hold->found == 'F') { $ph[$i][status] = "Finished"; } // the reserve has been completed, and is done
			else                         { $ph[$i][status] = "Waiting to be pulled";   } // means the patron requested the 1st available, and we haven't choosen the item
			$ph[$i][pickuploc]  = (string) $hold->branchname;
			$ph[$i][canceldate] = (string) $hold->cancellationdate;
			$i++;
		}
		
		return $ph;
	}
	
	/**
	 * Renews items and returns the renewal result
	 *
	 * @param string $cardnum Patron barcode/card number
	 * @param string $pin Patron pin/password
	 * @param array $items Array of varname => item numbers to be renewed, or NULL for everything.
	 * @return boolean|array Array of item renewal statuses or FALSE if it cannot renew for some reason
	 */
	public function renew_items($cardnum, $pin = NULL, $items = NULL) {
		$server = $this->locum_config[ils_config][ils_server];
		$port = $this->locum_config[ils_config][ils_harvest_port];
		
		$patron_id = $this->patron_id($cardnum);
		if ( ! $patron_id) { return FALSE; }
		
		$itemnumbers = array();
		if ($items == 'all') {
			$checkouts = $this->patron_checkouts($cardnum, $pin);
			foreach($checkouts as $checkout) {
				array_push($itemnumbers, $checkout['inum']);
			}
		}
		elseif (is_array($items)) {
			$itemnumbers = $items;
		}
		else {
			array_push($itemnumbers, $items);
		}
		
		foreach($itemnumbers as $itemnumber) {
			$url = 'http://'.$server.':'.$port.'/cgi-bin/koha/ilsdi.pl?service=RenewLoan&patron_id=' . $patron_id . '&item_id=' . $itemnumber;
			$xml = @simplexml_load_file($url);
		
			$ri[$itemnumber][varname]     = $itemnumber;
			$ri[$itemnumber][num_renews]  = (int) $xml->renewals;
			$ri[$itemnumber][new_duedate] = (string) $this->date_to_timestamp($xml-date_due);
			if ($xml->error) { $ri[$itemnumber][error] = (string) $xml->error; }
		}

		return $ri;
	}
	
	/**
	 * Cancels holds
	 *
	 * @param string $cardnum Patron barcode/card number
	 * @param string $pin Patron pin/password
	 * @param array Array of varname => item/bib numbers to be cancelled, or NULL for everything.
	 * @return boolean TRUE or FALSE if it cannot cancel for some reason
	 */
	public function cancel_holds($cardnum, $pin = NULL, $items = NULL) {
		$server = $this->locum_config[ils_config][ils_server];
		$port = $this->locum_config[ils_config][ils_harvest_port];
		
		$patron_id = $this->patron_id($cardnum);
		if ( ! $patron_id) { return FALSE; }
		
		$itemnumbers = array();
		if (is_array($items)) {
			$itemnumbers = $items;
		}
		else {
			array_push($itemnumbers, $items);
		}
		
		foreach($itemnumbers as $itemnumber) {
			$url = 'http://'.$server.':'.$port.'/cgi-bin/koha/ilsdi.pl?service=CancelHold&patron_id=' . $patron_id . '&item_id=' . $itemnumber;
			$xml = @simplexml_load_file($url);
			
			if($xml->message != 'Canceled') { return FALSE; }
		}

		return TRUE;
	}

	/**
	 * Places holds
	 *
	 * @param string $cardnum Patron barcode/card number
	 * @param string $bnum Bib item record number to place a hold on
	 * @param string $inum Item number to place a hold on if required (presented as $varname in locum)
	 * @param string $pin Patron pin/password
	 * @param string $pickup_loc Pickup location value
	 * @return boolean TRUE or FALSE if it cannot place the hold for some reason
	 */
	public function place_hold($cardnum, $bnum, $inum = NULL, $pin = NULL, $pickup_loc = NULL) {
		$server = $this->locum_config[ils_config][ils_server];
		$port = $this->locum_config[ils_config][ils_harvest_port];

		if ($inum)
		{
			$patron_id = $this->patron_id($cardnum);
			if ( ! $patron_id) { return FALSE; }
		
			$url = 'http://'.$server.':'.$port.'/cgi-bin/koha/ilsdi.pl?service=HoldItem&patron_id=' . $patron_id . '&bib_id=' . $bnum . '&item_id=' . $inum;
			if ($pickup_loc) { $url .= '&pickup_location=' . $pickup_loc; }
			$xml = @simplexml_load_file($url);
			
			$ph[success] = ($xml->message == 'Success');
			$ph[error]   = (string) $xml->message;
		}
		else
		{
			$url = 'http://'.$server.':'.$port.'/cgi-bin/koha/ilsdi.pl?service=GetRecords&id=' . $bnum;
			$xml = @simplexml_load_file($url);
		
			if ($xml->record->items->item) {
				foreach($xml->record->items->item as $item) {
					$inum = (int) $item->itemnumber;
					$ph[selection][$inum][status]   = "-"; // FIXME needs GetAvailability
					$ph[selection][$inum][varname]  = $inum;
					$ph[selection][$inum][location] = (string) $item->holdingbranchname;
					$ph[selection][$inum][callnum]  = (string) $item->itemcallnumber;
				}
			}
		}

		return $ph;
	}
	
	/**
	 * Returns an array of patron fines
	 *
	 * @param string $cardnum Patron barcode/card number
	 * @param string $pin Patron pin/password
	 * @return boolean|array Array of patron fines or FALSE if login fails
	 */
	public function patron_fines($cardnum, $pin = NULL) {
		$server = $this->locum_config[ils_config][ils_server];
		$port = $this->locum_config[ils_config][ils_harvest_port];
		
		$patron_id = $this->patron_id($cardnum);
		if ( ! $patron_id) { return FALSE; }
		
		$url = 'http://'.$server.':'.$port.'/cgi-bin/koha/ilsdi.pl?service=GetPatronInfo&patron_id=' . $patron_id . '&show_fines=1';
		$xml = @simplexml_load_file($url);
	
		foreach($xml->fines->fine as $fine) {
			$accountno = (int) $fine->{'accountno'};
			$fines[$accountno][varname] = 'fine'.$accountno;
			$fines[$accountno][desc]    = (string) $fine->{'description'};
			$fines[$accountno][amount]  = (float)  $fine->{'amount'};
		}
		
		return $fines;
	}
	
	/**
	 * Pays patron fines.
	 *
	 * @param string $cardnum Patron barcode/card number
	 * @param string $pin Patron pin/password
	 * @param array payment_details
	 * @return array Payment result
	 */
	public function pay_patron_fines($cardnum, $pin = NULL, $payment_details) {
		return FALSE;
	}
	
	/**
	 * This is an internal function used to parse MARC values.
	 * This function is called by scrape_bib()
	 *
	 * @param array $value_arr SimpleXML values from XRECORD for that MARC item
	 * @param array $subfields An array of MARC subfields to parse
	 * @param string $delimiter Delimiter to use for storage and indexing purposes.  A space seems to work fine
	 * @return array An array of processed MARC values
	 */
	private function prepare_marc_values($value_arr, $subfields, $delimiter = ' ') {

		// Repeatable values can be returned as an array or a serialized value
		foreach ($subfields as $subfield) {
			if (is_array($value_arr[$subfield])) {

				foreach ($value_arr[$subfield] as $subkey => $subvalue) {

					if (is_array($subvalue)) {
						foreach ($subvalue as $sub_subvalue) {
							if ($i[$subkey]) { $pad[$subkey] = $delimiter; }
							$sv_tmp = preg_replace('/\{(.*?)\}/', '', trim($sub_subvalue));
							$sv_tmp = trim(preg_replace('/</i', '"', $sv_tmp));
							if (trim($sub_subvalue)) { $marc_values[$subkey] .= $pad[$subkey] . $sv_tmp; }
							$i[$subkey] = 1;
						}
					} else {
						if ($i[$subkey]) { $pad[$subkey] = $delimiter; }
						
						// This is a workaround until I can figure out wtf III is doing with encoding.  For now
						// there will be no extended characters:
						$sv_tmp = preg_replace('/\{(.*?)\}/', '', trim($subvalue));

						// Fix odd quote issues.  May be a club method of doing this, but oh well.
						$sv_tmp = trim(preg_replace('/</i', '"', $sv_tmp));

						if (trim($subvalue)) { $marc_values[$subkey] .= $pad[$subkey] . $sv_tmp; }
						$i[$subkey] = 1;
					}
				}	
			}		
		}

		if (is_array($marc_values)) {
			foreach ($marc_values as $mv) {
				$result[] = $mv;
			}
		}
		return $result;
	}

	/**
	 * Does the initial job of creating an array out of the SimpleXML content from OAI-PMH.
	 * This function is called by scrape_bib() and the data is ultimately used by prepare_marc_values()
	 *
	 * @param array $marcxml marcxml value tree from OAI-PMH via SimpleXML
	 * @return array A normalized array of marc and subfield info
	 */
	private function parse_marc_subfields($marcxml) {
		$bim_item = 0;
		foreach ($marcxml->datafield as $datafield) {
			$marc_num = (string) $datafield->attributes()->tag;
			foreach ($datafield->subfield as $subfield) {
				$code = trim((string) $subfield->attributes()->code);
				$data = trim((string) $subfield);
				$marc_sub[$marc_num][$code][$bim_item] = $data;
			}
			$bim_item++;
		}
		return $marc_sub;
	}
	
	/**
	 * Converts YYYY-MM-DD to unix timestamp
	 *
	 * @param string $date Original date in MM-DD-YY format
	 * @return timestamp
	 */	
	private function date_to_timestamp($date) {
		$reg = "/([0-9].*)-([0-9].*)-([0-9].*)/";
		preg_match_all($reg, $date, $matches);
		$time = mktime(0, 0, 0, $matches[2][0], $matches[3][0], $matches[1][0]);
		return $time;
	}
		
	/**
	 * Returns a patron identifier for a given cardnumber
	 *
	 * @param string $cardnum Patron card number
	 * @return boolean|int Patron identifier or FALSE if patron is not found
	 */
	private function patron_id($cardnum) {
		$server = $this->locum_config[ils_config][ils_server];
		$port = $this->locum_config[ils_config][ils_harvest_port];
		
		$url = 'http://'.$server.':'.$port.'/cgi-bin/koha/ilsdi.pl?service=LookupPatron&id=' . $cardnum . '&id_type=cardnumber';
		$xml = @simplexml_load_file($url);
		
		return $xml->id;
	}
}
