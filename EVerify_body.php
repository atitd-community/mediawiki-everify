<?php

class EVerify
{
	/**
	 * Keeps count of <v> tags
	 *
	 * @var type
	 */
	public $verified = 0;

	/**
	 * Keeps count of <uv> tags
	 *
	 * @var type
	 */
	public $unverified = 0;

	/**
	 * Special flags for "fully verified" and "verification disabled"
	 *
	 * @var type
	 */
	public $specialFlags = [];

	/**
	 * Sets up all verification tags we're parsing
	 *
	 * @param \Parser $parser
	 */
	public static function onParserFirstCallInit( &$parser ) {
		$parser->verificationVars = new self();

		$parser->setHook('v',  'EVerify::verified' );
		$parser->setHook('uv', 'EVerify::unverified' );

		$parser->setHook('disable-verification', 'EVerify::disableVerification' );
		$parser->setHook('no-verification',      'EVerify::disableVerification' );

		$parser->setHook('fully-verified', 'EVerify::fullyVerified' );
		$parser->setHook('all-verified',   'EVerify::fullyVerified' );
	}

	/**
	 * Saves all variable totals as page properties in the database
	 *
	 * @param \Parser $parser
	 * @param string  $text
	 */
	public static function onParserAfterTidy( &$parser, &$text ) {
		$pageID = $parser->getTitle()->getArticleID();

		// setHook() apparently runs twice so we need to halve all our values
		$verified   = $parser->verificationVars->verified / 2;
		$unverified = $parser->verificationVars->unverified / 2;

		$specialFlags = $parser->verificationVars->specialFlags;

		$parser->getOutput()->setProperty( 'Verified',   $verified );
		$parser->getOutput()->setProperty( 'Unverified', $unverified );

		if(isset($specialFlags['verificationDisabled'])) {
			$parser->getOutput()->setProperty( 'Verification Disabled', $specialFlags['verificationDisabled'] );
		}
		if(isset($specialFlags['fullyVerified'])) {
			$parser->getOutput()->setProperty( 'Fully Verified', $specialFlags['fullyVerified'] );
		}
	}

	/**
	 * Create and return the verification box
	 *
	 * @param OutputPage $outputPage
	 */
	public static function onBeforePageDisplay( OutputPage $out ) {
		$title  = $out->getTitle();
		$pageID = $title->getArticleID();

		$verified     = self::getVerified($pageID);
		$unverified   = self::getUnverified($pageID);
		$specialFlags = self::getSpecialFlags($pageID);

		$verificationBox = self::buildVerificationBox($title, $verified, $unverified, $specialFlags);

		$out->setIndicators( ['zVerify' => $verificationBox] );
	}

	/**
	 * Adds to the verified count and returns marker HTML
	 *
	 * @param $input
	 * @param $params
	 * @param $parser
	 * @param $frame
	 *
	 * @return string
	 */
	public static function verified( $input, $params, $parser, $frame ) {
		if(array_key_exists('w', $params)) {
			$parser->verificationVars->verified += $params['w'];
		} else {
			$parser->verificationVars->verified += 1;
		}

		if(empty($input)) {
			return '<v title="Verified">&#10003;</v>';
		} else {
			return '<v-h title="Verified"> '.self::parse($parser, $input).'</v-h>';
		}
	}

	/**
	 * Adds to the unverified count and returns marker HTML
	 *
	 * @param $input
	 * @param $params
	 * @param $parser
	 * @param $frame
	 *
	 * @return string
	 */
	public static function unverified( $input, $params, $parser, $frame ) {
		if(array_key_exists('w', $params)) {
			$parser->verificationVars->unverified += $params['w'];
		} else {
			$parser->verificationVars->unverified += 1;
		}

		if(empty($input)) {
			return '<uv title="Unverified">?</uv>'.self::addUnverifiedCategory($parser);
		} else {
			return '<uv-h title="Unverified"> '.self::parse($parser, $input).'</uv-h>'.self::addUnverifiedCategory($parser);
		}
	}

	/**
	 * Flags the page to disable verification system
	 *
	 * @param $input
	 * @param $params
	 * @param $parser
	 * @param $frame
	 *
	 * @return string
	 */
	public static function disableVerification( $input, $params, $parser, $frame ) {
		$parser->verificationVars->specialFlags['verificationDisabled'] = 'true';

		return '';
	}

	/**
	 * Flags the page as being fully verified
	 *
	 * @param $input
	 * @param $params
	 * @param $parser
	 * @param $frame
	 *
	 * @return string
	 */
	public static function fullyVerified( $input, $params, $parser, $frame ) {
		$parser->verificationVars->specialFlags['fullyVerified'] = 'true';

		return '';
	}

	/**
	 * Retrieves the verified count for the page
	 *
	 * @param int $pageID
	 *
	 * @return int
	 */
	public static function getVerified( $pageID ) {
		$dbr = wfGetDB( DB_REPLICA );

		$propValue = $dbr->selectField( 'page_props',
										'pp_value',
										array( 'pp_page' => $pageID, 'pp_propname' => "Verified" ),
										__METHOD__
		);

		if ( $propValue === false ) {
			return 0;
		}

		return $propValue;
	}

	/**
	 * Retrieves the unverified count for the page
	 *
	 * @param int $pageID
	 *
	 * @return int
	 */
	public static function getUnverified( $pageID ) {
		$dbr = wfGetDB( DB_REPLICA );

		$propValue = $dbr->selectField( 'page_props',
										'pp_value',
										array( 'pp_page' => $pageID, 'pp_propname' => "Unverified" ),
										__METHOD__
		);

		if ( $propValue === false ) {
			return 0;
		}

		return $propValue;
	}

	/**
	 * Gets any special flags for the page such as "verification disabled" or "fully verified"
	 *
	 * @param int $pageID
	 *
	 * @return array
	 */
	public static function getSpecialFlags( $pageID ) {
		$props = [];

		$dbr = wfGetDB( DB_REPLICA );

		$verificationDisabled = $dbr->selectField( 'page_props',
												   'pp_value',
												   array( 'pp_page' => $pageID, 'pp_propname' => "Verification Disabled" ),
												   __METHOD__
		);

		$fullyVerified = $dbr->selectField( 'page_props',
											'pp_value',
											array( 'pp_page' => $pageID, 'pp_propname' => "Fully Verified" ),
											__METHOD__
		);

		if ( $verificationDisabled === 'true' ) {
			$props['verificationDisabled'] = true;
		} else {
			$props['verificationDisabled'] = false;
		}

		if ( $fullyVerified === 'true' ) {
			$props['fullyVerified'] = true;
		} else {
			$props['fullyVerified'] = false;
		}

		return $props;
	}

	/**
	 * Shortcut to escape and parse Wikitext inside <v> or <uv> tags
	 *
	 * @param \Parser $parser
	 * @param         $input
	 *
	 * @return string
	 */
	public static function parse( $parser, $input ) {
		return $parser->recursiveTagParse(htmlspecialchars($input));
	}

	/**
	 * Categorizes a page if a <uv> tag is used
	 *
	 * @param \Parser $parser
	 *
	 * @return string
	 */
	public static function addUnverifiedCategory( $parser ) {
		return self::parse($parser, '[[Category:Pages containing unverified information]]');
	}

	/**
	 * Perform final calculations and generate the verification box's HTML
	 *
	 * @param \Title $title
	 * @param int    $verified
	 * @param int    $unverified
	 * @param array  $specialFlags
	 *
	 * @return string
	 */
	public static function buildVerificationBox($title, $verified, $unverified, $specialFlags) {
		// If the page doesn't exist, or isn't in the main namespace, or has
		// verification explicity disabled, don't show a verification box
		if( !$title->exists() || !$title->inNamespaces([0]) || $specialFlags['verificationDisabled']) {
			return '';
		}

		// Create a specially formatted box if flagged as fully verified
		if($specialFlags['fullyVerified']) {
			$verificationBox = '<div class="verification verfified-full"><a href="https://atitd.wiki/tale9/Project:Verification"><strong>&#10003;</strong> Fully Verified!</a></div>';

			return $verificationBox;
		}

		// Calculates the verification percentage
		$total = (float) $verified + $unverified;
		if($total != 0) {
			$verified = ($verified / $total) * 100;
		} else {
			$verified = 100;
		}

		// Grab CSS styles based on verification percentage
		$styles = self::getVerificationStyles($verified);

		// Build the box
		$verificationBox = '<div class="verification '.$styles.'"><a href="https://atitd.wiki/tale9/Project:Verification"><strong>&#10003;</strong> '.number_format($verified).'% Verified</a></div>';

		return $verificationBox;
	}

	/**
	 * Choose a CSS style based on verification percentage
	 *
	 * @param int $percent
	 *
	 * @return string
	 */
	public static function getVerificationStyles($percent) {
		switch(true) {
			case ($percent == 100):
				return 'verified-100';
				break;
			case ($percent >= 95):
				return 'verified-95';
				break;
			case ($percent >= 90):
				return 'verified-90';
				break;
			case ($percent >= 85):
				return 'verified-85';
				break;
			case ($percent >= 80):
				return 'verified-80';
				break;
			case ($percent >= 75):
				return 'verified-75';
				break;
			case ($percent >= 70):
				return 'verified-70';
				break;
			case ($percent >= 65):
				return 'verified-65';
				break;
			case ($percent >= 60):
				return 'verified-60';
				break;
			case ($percent >= 55):
				return 'verified-55';
				break;
			case ($percent >= 50):
				return 'verified-50';
				break;
			case ($percent >= 45):
				return 'verified-45';
				break;
			case ($percent >= 40):
				return 'verified-40';
				break;
			case ($percent >= 35):
				return 'verified-35';
				break;
			case ($percent >= 30):
				return 'verified-30';
				break;
			case ($percent >= 25):
				return 'verified-25';
				break;
			case ($percent >= 20):
				return 'verified-20';
				break;
			case ($percent >= 15):
				return 'verified-15';
				break;
			case ($percent >= 10):
				return 'verified-10';
				break;
			case ($percent >= 5):
				return 'verified-5';
				break;
			default:
				return 'verified-0';
				break;
		}
	}
}